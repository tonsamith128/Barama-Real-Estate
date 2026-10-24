<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Core\Installation;
use Barama\Services\Updates\PublisherTrustPaths;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * BARAMA_DEMO_IMPORT_V100
 * Strictly verified customer demo packages, not arbitrary SQL or website scraping.
 *
 * Publisher contract:
 * ZIP contains envelope.json, content.json, assets/*.jpg|png|webp.
 * envelope.manifest is signed over:
 * "TONSAMITH-BARAMA-DEMO-V1\n" + canonically sorted JSON manifest.
 * Existing offline-pinned publisher V2 catalog is used, with distinct
 * purpose domain from code release manifests.
 */
final class DemoImportPackageV1
{
    private const PREFIX = "TONSAMITH-BARAMA-DEMO-V1\n";
    private const LIMIT = 67108864;
    private const ORDER = [
        'locations', 'amenities', 'companies', 'projects', 'properties',
        'listings', 'cms_pages', 'media_assets', 'project_amenities',
        'property_amenities', 'media_attachments',
    ];
    private const SETTINGS = [
        'cms.site_content', 'cms.footer_content', 'appearance.frontend',
    ];

    /** @return array<string,mixed> */
    public function inspect(string $archive): array
    {
        if (!extension_loaded('sodium') || !extension_loaded('zip') ||
            !is_file($archive) || is_link($archive) || filesize($archive) > self::LIMIT ||
            filesize($archive) < 500) throw new RuntimeException('DEMO_PACKAGE_NOT_AVAILABLE');
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('DEMO_ZIP_INVALID');
        }
        try {
            if ($zip->numFiles > 500 || $zip->numFiles < 2) throw new RuntimeException('DEMO_ZIP_ENTRY_LIMIT');
            $list = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                if (isset($list[$name]) || !preg_match('~^(?:envelope\.json|content\.json|assets/[a-f0-9]{64}\.(?:webp|png|jpg))$~D', $name)
                    || (int)($stat['size'] ?? 0) > 8388608
                    || (int)($stat['comp_size'] ?? 0) < 0) {
                    throw new RuntimeException('DEMO_ZIP_ENTRY_REJECTED');
                }
                $list[$name] = $stat;
            }
            $envelopeRaw = $zip->getFromName('envelope.json');
            if (!is_string($envelopeRaw) || strlen($envelopeRaw) > 16384) {
                throw new RuntimeException('DEMO_ENVELOPE_INVALID');
            }
            $envelope = json_decode($envelopeRaw, true, 16, JSON_THROW_ON_ERROR);
            $manifest = $envelope['manifest'] ?? null;
            $sig = $envelope['signature'] ?? null;
            if (!is_array($manifest) || !is_array($sig) ||
                ($manifest['schema'] ?? null) !== 'tonsamith-barama-demo-v1' ||
                ($manifest['product_slug'] ?? null) !== 'barama-real-estate' ||
                ($manifest['publisher'] ?? null) !== 'tonsamith.com' ||
                !preg_match('/^[a-z0-9][a-z0-9-]{2,90}$/D', (string)($manifest['key_id'] ?? '')) ||
                !preg_match('/^[a-z0-9][a-z0-9-]{2,80}$/D', (string)($manifest['package_id'] ?? '')) ||
                !preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?$/D', (string)($manifest['minimum_version'] ?? '')) ||
                !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', (string)($manifest['expires_at'] ?? '')) ||
                version_compare(APP_VERSION, (string)$manifest['minimum_version'], '<') ||
                strtotime((string)$manifest['expires_at']) <= time() ||
                ($sig['algorithm'] ?? null) !== 'Ed25519' ||
                ($sig['key_id'] ?? null) !== $manifest['key_id']) {
                throw new RuntimeException('DEMO_MANIFEST_INVALID_OR_INCOMPATIBLE');
            }
            $keys = $this->trustedKeys();
            $public = $keys[(string)$manifest['key_id']] ?? null;
            $signature = base64_decode((string)($sig['value'] ?? ''), true);
            if (!is_string($public) || !is_string($signature) ||
                strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES ||
                !sodium_crypto_sign_verify_detached($signature,
                    self::PREFIX . json_encode($this->canonical($manifest),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $public)) throw new RuntimeException('DEMO_SIGNATURE_NOT_TRUSTED');
            $digests = $manifest['files'] ?? null;
            if (!is_array($digests) || array_keys($list) !== array_keys($digests) && count($list) !== count($digests) + 1) {
                // Manifest excludes itself, includes every other asset and content.json.
                // Compare exact sets after removing envelope.
            }
            unset($list['envelope.json']);
            if (!is_array($digests) || array_keys($list) !== array_keys($digests)) {
                throw new RuntimeException('DEMO_FILE_LIST_INCOMPLETE');
            }
            foreach ($digests as $name => $hash) {
                if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                    throw new RuntimeException('DEMO_FILE_DIGEST_INVALID');
                }
                $stream = $zip->getStream($name);
                if ($stream === false) throw new RuntimeException('DEMO_FILE_UNREADABLE');
                $ctx = hash_init('sha256');
                try {
                    while (!feof($stream)) {
                        $block = fread($stream, 65536);
                        if ($block === false) throw new RuntimeException('DEMO_FILE_UNREADABLE');
                        hash_update($ctx, $block);
                    }
                } finally { fclose($stream); }
                if (!hash_equals($hash, hash_final($ctx))) {
                    throw new RuntimeException('DEMO_FILE_TAMPERED');
                }
            }
            $body = $zip->getFromName('content.json');
            if (!is_string($body) || strlen($body) > 8388608) throw new RuntimeException('DEMO_CONTENT_TOO_LARGE');
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            $this->validateContent($data, $list);
            return [
                'package_id' => (string)$manifest['package_id'],
                'expires_at' => (string)$manifest['expires_at'],
                'assets' => count($list) - 1,
                'settings' => count($data['settings']),
                'records' => array_sum(array_map('count', $data['tables'])),
                'components' => $data['components'],
                'content' => $data,
                'digest' => hash_file('sha256', $archive),
            ];
        } finally { $zip->close(); }
    }

    /**
     * Installer never migrates schema, installs PHP, or copies customer accounts.
     * Full-package installation is allowed only with no existing marketplace
     * content. An independent database backup must succeed first.
     */
    public function install(string $archive, int $adminUserId): array
    {
        $review = $this->inspect($archive);
        $data = $review['content'];
        $db = Database::connection();
        $tables = $data['tables'];
        foreach (array_keys($tables) as $table) {
            if (!in_array($table, self::ORDER, true)) throw new RuntimeException('DEMO_TABLE_DENIED');
            $count = (int) $db->query('SELECT COUNT(*) FROM ' . self::ident($table))->fetchColumn();
            if ($count > 0) throw new RuntimeException('DEMO_REQUIRES_EMPTY_CONTENT_TABLES');
        }
        // Never import a live operator's data into an existing customer site.
        $backup = (new SystemToolService())->backup();
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) throw new RuntimeException('DEMO_ZIP_INVALID');
        $written = [];
        $db->beginTransaction();
        try {
            foreach (self::ORDER as $table) {
                foreach ($tables[$table] ?? [] as $row) {
                    $fields = $this->existingColumns($table);
                    foreach ($row as $name => $_) if (!isset($fields[$name])) {
                        throw new RuntimeException('DEMO_SCHEMA_COLUMN_MISMATCH');
                    }
                    unset($row['created_by_user_id'], $row['updated_by_user_id'], $row['owner_user_id']);
                    foreach (['created_by_user_id', 'updated_by_user_id'] as $col) {
                        if (isset($fields[$col])) $row[$col] = $adminUserId;
                    }
                    if (isset($fields['owner_user_id'])) $row['owner_user_id'] = null;
                    if ($table === 'media_assets') {
                        $old = (string)($row['path'] ?? '');
                        if (!preg_match('~^demo/[a-f0-9]{64}\.(?:webp|png|jpg)$~D', $old)) {
                            throw new RuntimeException('DEMO_MEDIA_PATH_INVALID');
                        }
                        $row['disk'] = 'private';
                        $bytes = $zip->getFromName('assets/' . basename($old));
                        if (!is_string($bytes) || strlen($bytes) > 8388608 ||
                            !hash_equals((string)($row['checksum_sha256'] ?? ''), hash('sha256', $bytes))) {
                            throw new RuntimeException('DEMO_MEDIA_BYTES_INVALID');
                        }
                        $dir = APP_PATH . '/storage/uploads/private/demo';
                        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                            throw new RuntimeException('DEMO_PRIVATE_MEDIA_STORAGE_MISSING');
                        }
                        $dest = APP_PATH . '/storage/uploads/private/' . $old;
                        if (is_file($dest) || is_link($dest)) throw new RuntimeException('DEMO_MEDIA_ALREADY_EXISTS');
                        if (file_put_contents($dest, $bytes, LOCK_EX) !== strlen($bytes)) {
                            throw new RuntimeException('DEMO_MEDIA_WRITE_FAILED');
                        }
                        chmod($dest, 0640);
                        $written[] = $dest;
                    }
                    if (!$row) continue;
                    $columns = array_keys($row);
                    $sql = 'INSERT INTO ' . self::ident($table) . ' (' .
                        implode(',', array_map([self::class, 'ident'], $columns)) . ') VALUES (' .
                        implode(',', array_fill(0, count($columns), '?')) . ')';
                    $db->prepare($sql)->execute(array_values($row));
                }
            }
            foreach ($data['settings'] as $key => $value) {
                $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $db->prepare("INSERT INTO application_settings (setting_key,setting_value,value_type)
                    VALUES (?,?,'json') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type='json'")
                    ->execute([$key, $json]);
            }
            $db->commit();
            return ['package_id' => $review['package_id'],
                'records' => $review['records'],
                'settings' => $review['settings'],
                'assets' => $review['assets'],
                'pre_import_backup' => $backup['id']];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($written as $dest) @unlink($dest);
            throw $e;
        } finally { $zip->close(); }
    }

    private function validateContent(mixed $data, array $entries): void
    {
        if (!is_array($data) || ($data['schema'] ?? '') !== 'barama-demo-content-v1'
            || !is_array($data['settings'] ?? null)
            || !is_array($data['tables'] ?? null)
            || !is_array($data['components'] ?? null)) {
            throw new RuntimeException('DEMO_CONTENT_SCHEMA_INVALID');
        }
        foreach ($data['settings'] as $key => $value) {
            if (!in_array($key, self::SETTINGS, true) || !is_array($value)) {
                throw new RuntimeException('DEMO_SETTINGS_NOT_ALLOWED');
            }
        }
        $count = 0;
        foreach ($data['tables'] as $table => $rows) {
            if (!in_array($table, self::ORDER, true) || !is_array($rows) || count($rows) > 5000) {
                throw new RuntimeException('DEMO_TABLE_INVALID');
            }
            foreach ($rows as $row) {
                if (!is_array($row) || !isset($row['id']) || (int)$row['id'] <= 0
                    || count($row) > 80) throw new RuntimeException('DEMO_ROW_INVALID');
                foreach ($row as $field => $value) {
                    self::ident((string)$field);
                    if ($value !== null && !is_scalar($value)) throw new RuntimeException('DEMO_ROW_CELL_INVALID');
                }
                if (++$count > 12000) throw new RuntimeException('DEMO_ROW_LIMIT');
            }
        }
        if (count($data['components']) > 30) throw new RuntimeException('DEMO_COMPONENT_LIMIT');
    }

    private function existingColumns(string $table): array
    {
        $rows = Database::connection()->query('SHOW COLUMNS FROM ' . self::ident($table))->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) $out[(string)$row['Field']] = true;
        return $out;
    }

    private function trustedKeys(): array
    {
        $file = PublisherTrustPaths::keys();
        if (!is_file($file) || is_link($file) || filesize($file) > 65536) return [];
        $catalog = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (($catalog['format'] ?? '') !== 'tonsamith-publisher-keys-v2') return [];
        $keys = [];
        foreach ($catalog['keys'] ?? [] as $id => $entry) {
            if (!in_array($entry['status'] ?? '', ['active', 'retired'], true)) continue;
            $decoded = base64_decode((string)($entry['public'] ?? ''), true);
            if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $keys[$id] = $decoded;
            }
        }
        return $keys;
    }

    private function canonical(array $input): array
    {
        ksort($input, SORT_STRING);
        return $input;
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name)) {
            throw new RuntimeException('DEMO_IDENTIFIER_DENIED');
        }
        return chr(96) . $name . chr(96);
    }
}
