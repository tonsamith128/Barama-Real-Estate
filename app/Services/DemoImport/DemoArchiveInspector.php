<?php

declare(strict_types=1);

namespace App\Services\DemoImport;

use RuntimeException;
use ZipArchive;

/**
 * Read-only parser of the original publisher ZIP structure.
 * Inspecting a ZIP is never evidence that it is publisher-authenticated.
 */
final class DemoArchiveInspector
{
    private const TABLES = [
        'locations', 'amenities', 'companies', 'projects', 'properties',
        'listings', 'cms_pages', 'project_amenities', 'property_amenities',
        'media_assets', 'media_attachments',
    ];

    /**
     * @return array{sha256:string,manifest:array,content:array,counts:array,images:int,policies:int}
     */
    public function inspect(string $path): array
    {
        if (!is_file($path) || is_link($path) || filesize($path) < 500 || filesize($path) > 67108864) {
            throw new RuntimeException('Invalid demo ZIP size or path.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Demo archive is not a ZIP.');
        }

        try {
            if ($zip->numFiles < 4 || $zip->numFiles > 110) {
                throw new RuntimeException('Unexpected ZIP entry count.');
            }
            $entries = [];
            $unpacked = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) {
                    throw new RuntimeException('Unreadable ZIP entry.');
                }
                $name = (string) $stat['name'];
                if (isset($entries[$name]) ||
                    !preg_match('~^(?:manifest\.json|content\.json|review\.json|assets/[a-f0-9]{64}\.(?:jpg|png|webp))$~D', $name)) {
                    throw new RuntimeException('Unexpected or duplicated ZIP entry.');
                }
                $size = (int) $stat['size'];
                if ($size < 1 || $size > ($name === 'content.json' ? 8388608 : ($name === 'manifest.json' || $name === 'review.json' ? 65536 : 8388608))) {
                    throw new RuntimeException('Oversized or empty ZIP entry.');
                }
                $unpacked += $size;
                if ($unpacked > 100663296) {
                    throw new RuntimeException('Unpacked ZIP size limit exceeded.');
                }
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) &&
                    $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('ZIP symbolic links are forbidden.');
                }
                $entries[$name] = $size;
            }

            foreach (['manifest.json', 'content.json', 'review.json'] as $file) {
                if (!isset($entries[$file])) {
                    throw new RuntimeException('Missing demo manifest or payload.');
                }
            }

            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 24, JSON_THROW_ON_ERROR);
            $content = json_decode((string) $zip->getFromName('content.json'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !is_array($content) ||
                ($manifest['product'] ?? null) !== 'barama-real-estate' ||
                ($manifest['demo_slug'] ?? null) !== 'live' ||
                ($manifest['schema_version'] ?? null) !== 1 ||
                !preg_match('/^\d+\.\d+\.\d+$/D', (string) ($manifest['version'] ?? '')) ||
                ($content['schema'] ?? null) !== 'barama-demo-content-v1' ||
                !is_array($content['tables'] ?? null) || !is_array($content['settings'] ?? null)) {
                throw new RuntimeException('Unsupported demo content schema.');
            }
            $tableNames = array_keys($content['tables']);
            $expected = self::TABLES;
            sort($tableNames);
            sort($expected);
            if ($tableNames !== $expected) {
                throw new RuntimeException('Unexpected demo tables.');
            }

            $counts = [];
            $ids = [];
            $total = 0;
            foreach (self::TABLES as $table) {
                $rows = $content['tables'][$table];
                if (!is_array($rows) || !array_is_list($rows) || count($rows) > 10000) {
                    throw new RuntimeException('Invalid demo table rows.');
                }
                $counts[$table] = count($rows);
                $total += count($rows);
                if ($total > 24000) {
                    throw new RuntimeException('Too many demo rows.');
                }
                $ids[$table] = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Invalid demo record.');
                    }
                    if (in_array($table, ['project_amenities', 'property_amenities'], true)) {
                        continue;
                    }
                    $id = $row['id'] ?? null;
                    if (!is_int($id) || $id <= 0 || isset($ids[$table][$id])) {
                        throw new RuntimeException('Invalid or duplicated demo record ID.');
                    }
                    $ids[$table][$id] = true;
                }
            }

            foreach (['companies' => 'locations', 'projects' => 'companies',
                'properties' => 'companies', 'listings' => 'properties'] as $child => $parent) {
                $field = $parent === 'locations' ? 'location_id' :
                    ($child === 'listings' ? 'property_id' : 'company_id');
                foreach ($content['tables'][$child] as $row) {
                    if ($child === 'companies' && ($row[$field] ?? null) === null) {
                        continue;
                    }
                    if (!isset($ids[$parent][$row[$field] ?? -1])) {
                        throw new RuntimeException('Broken demo relationship: '.$child.'.'.$field);
                    }
                }
            }
            foreach ($content['tables']['projects'] as $row) {
                if (($row['location_id'] ?? null) !== null &&
                    !isset($ids['locations'][$row['location_id']])) {
                    throw new RuntimeException('Unknown project location.');
                }
            }
            foreach ($content['tables']['properties'] as $row) {
                if (($row['location_id'] ?? null) !== null &&
                    !isset($ids['locations'][$row['location_id']])) {
                    throw new RuntimeException('Unknown property location.');
                }
                if (($row['project_id'] ?? null) !== null) {
                    $project = $this->findRow($content['tables']['projects'], (int) $row['project_id']);
                    if ($project === null || ($project['company_id'] ?? null) !== ($row['company_id'] ?? null)) {
                        throw new RuntimeException('Property project belongs to a different company.');
                    }
                }
            }
            foreach ($content['tables']['locations'] as $row) {
                if (($row['parent_id'] ?? null) !== null &&
                    !isset($ids['locations'][$row['parent_id']])) {
                    throw new RuntimeException('Broken location parent.');
                }
            }
            foreach ($content['tables']['media_attachments'] as $row) {
                $type = (string) ($row['entity_type'] ?? '');
                $target = ['company' => 'companies', 'project' => 'projects',
                    'property' => 'properties', 'listing' => 'listings'][$type] ?? null;
                if ($target === null || !isset($ids[$target][$row['entity_id'] ?? -1]) ||
                    !isset($ids['media_assets'][$row['media_asset_id'] ?? -1])) {
                    throw new RuntimeException('Invalid media attachment relationship.');
                }
            }

            $imagePaths = [];
            foreach ($content['tables']['media_assets'] as $asset) {
                $digest = (string) ($asset['checksum_sha256'] ?? '');
                $extension = (string) ($asset['extension'] ?? '');
                if (!preg_match('/^[a-f0-9]{64}$/D', $digest) ||
                    !in_array($extension, ['jpg', 'png', 'webp'], true)) {
                    throw new RuntimeException('Invalid demo image digest.');
                }
                $name = 'assets/'.$digest.'.'.$extension;
                if (!isset($entries[$name])) {
                    throw new RuntimeException('Missing referenced image.');
                }
                $imagePaths[$name] = true;
            }
            if (count($imagePaths) !== count($entries) - 3) {
                throw new RuntimeException('Archive image list does not match content.');
            }

            foreach (array_keys($imagePaths) as $name) {
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Unreadable image.');
                }
                $hash = hash_init('sha256');
                while (!feof($stream)) {
                    $chunk = fread($stream, 65536);
                    if ($chunk === false) {
                        fclose($stream);
                        throw new RuntimeException('Corrupt image stream.');
                    }
                    hash_update($hash, $chunk);
                }
                fclose($stream);
                if (!hash_equals(substr(basename($name), 0, 64), hash_final($hash))) {
                    throw new RuntimeException('Demo image digest mismatch.');
                }
            }

            $policies = $content['policy_center']['policies'] ?? [];
            if (!is_array($policies) || !array_is_list($policies) || count($policies) > 100) {
                throw new RuntimeException('Invalid demo policy snapshot.');
            }
            return [
                'sha256' => hash_file('sha256', $path),
                'manifest' => $manifest,
                'content' => $content,
                'counts' => $counts,
                'images' => count($imagePaths),
                'policies' => count($policies),
            ];
        } finally {
            $zip->close();
        }
    }

    private function findRow(array $rows, int $id): ?array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Publisher signs the raw SHA256 digest bytes outside the ZIP.
     * The fingerprint must be independently pinned in source/config.
     */
    public static function verifiedPublisherSignature(
        string $digest,
        string $signatureB64,
        string $publicKeyB64,
        string $pinnedFingerprint,
    ): bool {
        if (!extension_loaded('sodium') ||
            preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1 ||
            preg_match('/^[a-f0-9]{64}$/D', $pinnedFingerprint) !== 1) {
            return false;
        }
        $signature = base64_decode($signatureB64, true);
        $key = base64_decode($publicKeyB64, true);
        return is_string($signature) && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES &&
            is_string($key) && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES &&
            hash_equals($pinnedFingerprint, hash('sha256', $key)) &&
            sodium_crypto_sign_verify_detached($signature, hex2bin($digest), $key);
    }
}
