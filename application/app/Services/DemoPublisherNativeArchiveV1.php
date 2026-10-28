<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;
use ZipArchive;

/**
 * BARAMA_DEMO_NATIVE_READONLY_V102
 * Review of real TonSamith.com publisher archives. A valid ZIP is NOT an
 * installation authorization; publisher signatures are separate metadata.
 */
final class DemoPublisherNativeArchiveV1
{
    private const TABLES = [
        'locations', 'amenities', 'companies', 'projects', 'properties',
        'listings', 'cms_pages', 'project_amenities', 'property_amenities',
        'media_assets', 'media_attachments'
    ];
    private const SETTINGS = [
        'cms.site_content', 'cms.footer_content', 'appearance.frontend'
    ];

    /** @return array<string,mixed> */
    public function inspect(string $path): array
    {
        if (!extension_loaded('zip') || !is_file($path) || is_link($path) ||
            filesize($path) < 500 || filesize($path) > 67108864) {
            throw new RuntimeException('DEMO_NATIVE_ZIP_UNAVAILABLE');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('DEMO_NATIVE_ZIP_INVALID');
        }
        try {
            if ($zip->numFiles < 3 || $zip->numFiles > 503) {
                throw new RuntimeException('DEMO_NATIVE_ZIP_ENTRY_LIMIT');
            }
            $names = [];
            $uncompressed = 0;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $info = $zip->statIndex($i);
                $name = (string)($info['name'] ?? '');
                if (isset($names[$name]) || !preg_match(
                    '~^(?:manifest\.json|content\.json|review\.json|assets/[a-f0-9]{64}\.(?:jpg|png|webp))$~D',
                    $name)) throw new RuntimeException('DEMO_NATIVE_ZIP_ENTRY_REJECTED');
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('DEMO_NATIVE_ZIP_SYMLINK_DENIED');
                }
                $size = (int)($info['size'] ?? -1);
                if ($size < 0 || $size > 8388608) {
                    throw new RuntimeException('DEMO_NATIVE_ZIP_FILE_LIMIT');
                }
                $uncompressed += $size;
                if ($uncompressed > 100663296) {
                    throw new RuntimeException('DEMO_NATIVE_ZIP_UNPACKED_LIMIT');
                }
                $names[$name] = true;
            }
            foreach (['manifest.json','content.json','review.json'] as $required) {
                if (!isset($names[$required])) {
                    throw new RuntimeException('DEMO_NATIVE_ZIP_REQUIRED_MISSING');
                }
            }
            $manifestRaw = $zip->getFromName('manifest.json');
            $payloadRaw = $zip->getFromName('content.json');
            if (!is_string($manifestRaw) || strlen($manifestRaw) > 16384 ||
                !is_string($payloadRaw) || strlen($payloadRaw) > 8388608) {
                throw new RuntimeException('DEMO_NATIVE_JSON_LIMIT');
            }
            $manifest = json_decode($manifestRaw, true, 32, JSON_THROW_ON_ERROR);
            $payload = json_decode($payloadRaw, true, 48, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !is_array($payload) ||
                ($manifest['product'] ?? null) !== 'barama-real-estate' ||
                ($manifest['demo_slug'] ?? null) !== 'live' ||
                ($manifest['schema_version'] ?? null) !== 1 ||
                !preg_match('/^\d+\.\d+\.\d+$/D', (string)($manifest['version'] ?? '')) ||
                ($payload['schema'] ?? null) !== 'barama-demo-content-v1' ||
                !is_array($payload['tables'] ?? null) ||
                !is_array($payload['settings'] ?? null)) {
                throw new RuntimeException('DEMO_NATIVE_SCHEMA_INVALID');
            }
            // Signed manifest declares the application versions tested for
            // this demo. The customer release gate checks the installed app
            // against this list before permitting installation.
            // Legacy publisher manifests may list the application versions
            // used during author QA. Keep this signed metadata well-formed,
            // but do not confuse application release labels with content schema
            // compatibility. New publishers may omit the advisory field.
            $compatible = $manifest['compatible_app_versions'] ?? null;
            if ($compatible !== null) {
                if (!is_array($compatible) || count($compatible) < 1 ||
                    count($compatible) > 20 || array_values($compatible) !== $compatible) {
                    throw new RuntimeException('DEMO_NATIVE_APP_COMPATIBILITY_INVALID');
                }
                foreach ($compatible as $appVersion) {
                    if (!is_string($appVersion) ||
                        preg_match('/^\\d+\\.\\d+\\.\\d+(?:-[A-Za-z0-9.]+)?$/D', $appVersion) !== 1) {
                        throw new RuntimeException('DEMO_NATIVE_APP_COMPATIBILITY_INVALID');
                    }
                }
            }
            // Optional in legacy signed ZIPs. New snapshots must be public-only
            // and strictly validated while the ZIP SHA/signature remains pinned.
            $policyCount = 0;
            if (array_key_exists('policy_center', $payload)) {
                DemoPolicySnapshotV1::validate($payload['policy_center']);
                $policyCount = count($payload['policy_center']['policies']);
            }
            $tables = $payload['tables'];
            $settings = $payload['settings'];
            $tableNames = array_keys($tables);
            $settingNames = array_keys($settings);
            $allowedTables = self::TABLES;
            $allowedSettings = self::SETTINGS;
            sort($tableNames);
            sort($settingNames);
            sort($allowedTables);
            sort($allowedSettings);
            if ($tableNames !== $allowedTables || $settingNames !== $allowedSettings) {
                throw new RuntimeException('DEMO_NATIVE_UNEXPECTED_COMPONENTS');
            }
            $ids = [];
            $columnsByTable = [];
            $rowsCount = 0;
            foreach ($tables as $table => $records) {
                if (!is_array($records) || count($records) > 10000) {
                    throw new RuntimeException('DEMO_NATIVE_ROW_LIMIT');
                }
                $rowsCount += count($records);
                if ($rowsCount > 24000) throw new RuntimeException('DEMO_NATIVE_TOTAL_LIMIT');
                $ids[$table] = [];
                $columnsByTable[$table] = [];
                foreach ($records as $row) {
                    if (!is_array($row) || count($row) > 100) {
                        throw new RuntimeException('DEMO_NATIVE_ROW_INVALID');
                    }
                    if (isset($row['id'])) {
                        $id = (int)$row['id'];
                        if ($id < 1 || isset($ids[$table][$id])) {
                            throw new RuntimeException('DEMO_NATIVE_DUPLICATE_ID');
                        }
                        $ids[$table][$id] = true;
                    } elseif (!in_array($table, ['project_amenities','property_amenities'], true)) {
                        throw new RuntimeException('DEMO_NATIVE_ID_MISSING');
                    }
                    foreach ($row as $field => $value) {
                        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', (string)$field) ||
                            ($value !== null && !is_scalar($value))) {
                            throw new RuntimeException('DEMO_NATIVE_FIELD_INVALID');
                        }
                        $columnsByTable[$table][$field] = true;
                    }
                }
            }
            foreach ([
                'companies' => ['location_id'=>'locations'],
                'projects' => ['company_id'=>'companies','location_id'=>'locations'],
                'properties' => ['company_id'=>'companies','project_id'=>'projects',
                    'location_id'=>'locations'],
                'listings' => ['property_id'=>'properties'],
                'project_amenities' => ['project_id'=>'projects','amenity_id'=>'amenities'],
                'property_amenities' => ['property_id'=>'properties','amenity_id'=>'amenities']
            ] as $table => $references) {
                foreach ($tables[$table] as $row) {
                    foreach ($references as $field => $parent) {
                        if (($row[$field] ?? null) !== null &&
                            !isset($ids[$parent][(int)$row[$field]])) {
                            throw new RuntimeException('DEMO_NATIVE_BROKEN_REFERENCE');
                        }
                    }
                }
            }
            $owners = ['company'=>'companies','project'=>'projects',
                'property'=>'properties','listing'=>'listings','page'=>'cms_pages'];
            foreach ($tables['media_attachments'] as $row) {
                $parent = $owners[$row['entity_type'] ?? ''] ?? '';
                if ($parent === '' || !isset($ids[$parent][(int)($row['entity_id'] ?? 0)]) ||
                    !isset($ids['media_assets'][(int)($row['media_asset_id'] ?? 0)])) {
                    throw new RuntimeException('DEMO_NATIVE_ATTACHMENT_BROKEN');
                }
            }
            $imagePaths = [];
            foreach ($tables['media_assets'] as $row) {
                $pathPart = (string)($row['path'] ?? '');
                if (($row['disk'] ?? null) !== 'private' ||
                    !preg_match('~^demo/([a-f0-9]{64})\.(jpg|png|webp)$~D', $pathPart, $matches) ||
                    ($row['checksum_sha256'] ?? '') !== $matches[1]) {
                    throw new RuntimeException('DEMO_NATIVE_IMAGE_PATH_INVALID');
                }
                $name = 'assets/' . basename($pathPart);
                if (!isset($names[$name])) throw new RuntimeException('DEMO_NATIVE_IMAGE_MISSING');
                $imagePaths[$name] = $matches[1];
            }
            $zipImages = array_filter(array_keys($names),
                static fn(string $name): bool => str_starts_with($name, 'assets/'));
            if (count($zipImages) !== count($imagePaths)) {
                throw new RuntimeException('DEMO_NATIVE_UNREFERENCED_IMAGE');
            }
            foreach ($imagePaths as $name => $expected) {
                $input = $zip->getStream($name);
                if ($input === false) throw new RuntimeException('DEMO_NATIVE_IMAGE_UNREADABLE');
                $hash = hash_init('sha256');
                try {
                    while (!feof($input)) {
                        $piece = fread($input, 65536);
                        if ($piece === false) throw new RuntimeException('DEMO_NATIVE_IMAGE_UNREADABLE');
                        hash_update($hash, $piece);
                    }
                } finally { fclose($input); }
                if (!hash_equals($expected, hash_final($hash))) {
                    throw new RuntimeException('DEMO_NATIVE_IMAGE_TAMPERED');
                }
            }
            return [
                'zip_sha256' => hash_file('sha256', $path),
                'package_version' => (string)$manifest['version'],
                'format' => 'tonsamith-manifest-json',
                'manifest_schema_version' => (int)$manifest['schema_version'],
                'content_schema' => (string)$payload['schema'],
                'columns_by_table' => array_map(
                    static fn (array $fields): array => array_keys($fields), $columnsByTable
                ),
                'record_count' => $rowsCount,
                'table_counts' => array_map('count', $tables),
                'settings_count' => count($settings),
                'policy_records' => $policyCount,
                'compatible_app_versions' => $compatible,
                'media_records' => count($tables['media_assets']),
                'media_images' => count($imagePaths),
                'signature_verified' => false,
                'install_authorized' => false,
            ];
        } finally { $zip->close(); }
    }

    /**
     * Signatures are OUTSIDE the ZIP, over its raw SHA256 digest, and come
     * from the independently verified publisher rather than the ZIP payload.
     */
    public function inspectSigned(string $path, array $metadata,
        array $identity, string $pinnedFingerprint): array
    {
        DemoPublisherApiV1::requirePinnedIdentity($identity, $pinnedFingerprint);
        $result = $this->inspect($path);
        $sha = (string)($metadata['sha256'] ?? '');
        if (!hash_equals($sha, (string)$result['zip_sha256']) ||
            !hash_equals((string)($metadata['key_id'] ?? ''), (string)$identity['key_id']) ||
            !DemoPublisherApiV1::verifyPublishedSignature(
                $sha, (string)($metadata['signature'] ?? ''),
                (string)$identity['public_key'], $pinnedFingerprint)) {
            throw new RuntimeException('DEMO_NATIVE_PUBLISHER_SIGNATURE_DENIED');
        }
        $result['signature_verified'] = true;
        // Still no install grant; the database importer is separately gated.
        return $result;
    }
}
