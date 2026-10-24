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
            unset($list['envelope.json']);
            if (!is_array($digests) || !array_key_exists('content.json', $list)
                || count($list) !== count($digests)) {
                throw new RuntimeException('DEMO_FILE_LIST_INCOMPLETE');
            }
            $expectedNames = array_keys($list);
            $signedNames = array_keys($digests);
            sort($expectedNames, SORT_STRING);
            sort($signedNames, SORT_STRING);
            if ($expectedNames !== $signedNames) {
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
        // Share the System Tool lock across backup and installation; do not
        // reacquire it while creating the mandatory pre-import snapshot.
        return (new SystemToolService())->exclusiveWork(
            fn (): array => $this->installLocked($archive, $adminUserId)
        );
    }

    private function installLocked(string $archive, int $adminUserId): array
    {
        return $this->applyVerifiedContent($archive, $this->inspect($archive), $adminUserId);
    }

    /**
     * BARAMA_NATIVE_PUBLISHER_INSTALL_V107
     * The native publisher format is signed OUTSIDE the ZIP over its exact
     * SHA-256. Never treat structural inspection alone as an install grant.
     *
     * @param array<string,mixed> $metadata Published licensed catalog metadata
     * @param array<string,string> $identity Independently pinned signing identity
     */
    public function installNative(
        string $archive, array $metadata, array $identity,
        string $pinnedFingerprint, int $adminUserId
    ): array {
        return (new SystemToolService())->exclusiveWork(
            fn (): array => $this->installNativeLocked(
                $archive, $metadata, $identity, $pinnedFingerprint, $adminUserId
            )
        );
    }

    private function installNativeLocked(
        string $archive, array $metadata, array $identity,
        string $pinnedFingerprint, int $adminUserId
    ): array {
        if ($adminUserId < 1 || !preg_match(
            '/^DCP-[A-Za-z0-9-]{8,80}$/D', (string)($metadata['id'] ?? '')
        )) {
            throw new RuntimeException('DEMO_INSTALL_AUTHORIZATION_INVALID');
        }
        $native = (new DemoPublisherNativeArchiveV1())->inspectSigned(
            $archive, $metadata, $identity, $pinnedFingerprint
        );
        if (($native['signature_verified'] ?? false) !== true ||
            !hash_equals((string)$native['zip_sha256'], (string)($metadata['sha256'] ?? ''))) {
            throw new RuntimeException('DEMO_NATIVE_SIGNATURE_REQUIRED');
        }
        if (($native['package_version'] ?? '') !== ($metadata['version'] ?? null)) {
            throw new RuntimeException('DEMO_NATIVE_PUBLISHER_VERSION_MISMATCH');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('DEMO_NATIVE_ZIP_INVALID');
        }
        try {
            $contents = $zip->getFromName('content.json');
            if (!is_string($contents) || strlen($contents) > 8388608) {
                throw new RuntimeException('DEMO_NATIVE_CONTENT_LIMIT');
            }
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
            // Match the already authenticated native archive's bounded contract.
            // Legacy envelope packages retain their original stricter limits.
            $this->validateContent($data, [], 10000, 24000, 100);
            $counts = array_map('count', $data['tables']);
            if (array_sum($counts) !== (int)$native['record_count'] ||
                count($data['settings']) !== (int)$native['settings_count']) {
                throw new RuntimeException('DEMO_NATIVE_REVIEW_MISMATCH');
            }
        } finally {
            $zip->close();
        }
        // Identical bounded transaction, backup, fresh-site guard and media
        // cleanup used by the legacy signed format. Nothing is installed merely
        // by calling inspectSigned() or downloading the archive.
        return $this->applyVerifiedContent($archive, [
            'content' => $data,
            'package_id' => (string)$metadata['id'],
            'records' => $native['record_count'],
            'assets' => $native['media_images'],
            'settings' => $native['settings_count'],
        ], $adminUserId);
    }

    /** Shared installation path; caller must independently verify its format. */
    private function applyVerifiedContent(string $archive, array $review, int $adminUserId): array
    {
        $data = $review['content'];
        $db = Database::connection();
        // Convert author-side placeholders to the current customer domain only.
        $appUrl = rtrim((string)(Installation::runtimeConfig()['APP_URL'] ?? ''), '/');
        if (!preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{2,5})?$~D', $appUrl)) {
            throw new RuntimeException('DEMO_CUSTOMER_DOMAIN_UNSAFE');
        }
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
                $rows = $tables[$table] ?? [];
                if (!$rows) continue;
                // A 5,000+ row demo must not query SHOW COLUMNS on every INSERT.
                $fields = $this->existingColumns($table);
                foreach ($rows as $row) {
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
                        $extension = pathinfo($old, PATHINFO_EXTENSION);
                        $expectedMime = match ($extension) {
                            'jpg' => 'image/jpeg', 'png' => 'image/png',
                            'webp' => 'image/webp', default => '',
                        };
                        if ($expectedMime === '' ||
                            ($row['mime_type'] ?? null) !== $expectedMime ||
                            (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $expectedMime ||
                            @getimagesizefromstring($bytes) === false) {
                            throw new RuntimeException('DEMO_IMAGE_CONTENT_REJECTED');
                        }
                        $dir = APP_PATH . '/storage/uploads/private/demo';
                        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                            throw new RuntimeException('DEMO_PRIVATE_MEDIA_STORAGE_MISSING');
                        }
                        $dest = APP_PATH . '/storage/uploads/private/' . $old;
                        $this->writeVerifiedDemoMediaOnce(
                            $dest, $bytes, (string)$row['checksum_sha256'], $written
                        );
                    }
                    $row = $this->rewriteRecipientUrls($row, $appUrl);
                    if (!$row) continue;
                    $columns = array_keys($row);
                    $sql = 'INSERT INTO ' . self::ident($table) . ' (' .
                        implode(',', array_map([self::class, 'ident'], $columns)) . ') VALUES (' .
                        implode(',', array_fill(0, count($columns), '?')) . ')';
                    $db->prepare($sql)->execute(array_values($row));
                }
            }
            foreach ($data['settings'] as $key => $value) {
                $json = json_encode($this->rewriteRecipientUrls($value, $appUrl),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
            foreach (array_keys($written) as $dest) @unlink($dest);
            throw $e;
        } finally { $zip->close(); }
    }

    /**
     * BARAMA_DEMO_SHARED_MEDIA_V110
     * Content-addressed images may be referenced by more than one DB row.
     * Reuse only files created in this import; never adopt old site files.
     *
     * @param array<string,bool> $written Exact paths created by this import.
     */
    private function writeVerifiedDemoMediaOnce(
        string $dest, string $bytes, string $expectedSha, array &$written
    ): void {
        if (isset($written[$dest])) {
            if (!is_file($dest) || is_link($dest) ||
                !hash_equals($expectedSha, (string)hash_file('sha256', $dest))) {
                throw new RuntimeException('DEMO_MEDIA_SHARED_ASSET_CHANGED');
            }
            return;
        }
        if (file_exists($dest) || is_link($dest)) {
            throw new RuntimeException('DEMO_MEDIA_ALREADY_EXISTS');
        }
        // Exclusive creation refuses races and protects customer files.
        $handle = @fopen($dest, 'xb');
        if ($handle === false) {
            throw new RuntimeException('DEMO_MEDIA_EXCLUSIVE_CREATE_FAILED');
        }
        // Track immediately, including partial writes, for rollback cleanup.
        $written[$dest] = true;
        try {
            $totalBytes = strlen($bytes);
            $offset = 0;
            while ($offset < $totalBytes) {
                $size = fwrite($handle, substr($bytes, $offset, 65536));
                if (!is_int($size) || $size < 1) {
                    throw new RuntimeException('DEMO_MEDIA_WRITE_FAILED');
                }
                $offset += $size;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('DEMO_MEDIA_WRITE_FAILED');
            }
        } finally {
            fclose($handle);
        }
        if (!chmod($dest, 0640)) {
            throw new RuntimeException('DEMO_MEDIA_PERMISSIONS_FAILED');
        }
    }

    private function rewriteRecipientUrls(mixed $value, string $url): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->rewriteRecipientUrls($item, $url);
            }
            return $value;
        }
        if (is_string($value)) {
            return str_replace('{{APP_URL}}', $url, $value);
        }
        return $value;
    }

    private function validateContent(
        mixed $data, array $entries, int $maxTableRows = 5000,
        int $maxTotalRows = 12000, int $maxRowFields = 80
    ): void {
        // Native ZIP bounds are validated first by DemoPublisherNativeArchiveV1.
        // These caller-selected limits preserve legacy compatibility and prevent
        // the importer from rejecting a legitimate publisher-signed native ZIP.
        if ($maxTableRows > 10000 || $maxTotalRows > 24000 || $maxRowFields > 100 ||
            $maxTableRows < 1 || $maxTotalRows < 1 || $maxRowFields < 1) {
            throw new RuntimeException('DEMO_CONTENT_LIMIT_INVALID');
        }
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
            if (!in_array($table, self::ORDER, true) || !is_array($rows) || count($rows) > $maxTableRows) {
                throw new RuntimeException('DEMO_TABLE_INVALID');
            }
            foreach ($rows as $row) {
                $pivot = in_array($table, ['project_amenities','property_amenities'], true);
                $parent = $table === 'project_amenities' ? 'project_id' : 'property_id';
                if (!is_array($row) || count($row) > $maxRowFields ||
                    ($pivot
                      ? (!isset($row[$parent], $row['amenity_id']) ||
                         (int)$row[$parent] <= 0 || (int)$row['amenity_id'] <= 0)
                      : (!isset($row['id']) || (int)$row['id'] <= 0))) {
                    throw new RuntimeException('DEMO_ROW_INVALID');
                }
                foreach ($row as $field => $value) {
                    self::ident((string)$field);
                    if ($value !== null && !is_scalar($value)) throw new RuntimeException('DEMO_ROW_CELL_INVALID');
                }
                if (++$count > $maxTotalRows) throw new RuntimeException('DEMO_ROW_LIMIT');
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
