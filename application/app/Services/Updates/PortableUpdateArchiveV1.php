<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Portable, read-only verifier for the smallest customer update contract.
 * Caller MUST first obtain an entitled publisher response and verify its
 * signed V2 envelope. A ZIP or this plan is NEVER update authorization.
 *
 * MVP intentionally accepts VERSION plus at least one application/asset file,
 * with existing parent directories only and NO SQL/migrations/config/uploads.
 */
final class PortableUpdateArchiveV1
{
    private const MAX_ZIP = 8388608;
    private const MAX_FILES = 32;
    private const MAX_FILE = 4194304;
    private const MAX_UNCOMPRESSED = 16777216;

    public static function allowedPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 240 || str_starts_with($path, '/')
            || str_contains($path, '//') || str_contains($path, '\\')
            || preg_match('~^[A-Za-z0-9_.\/-]+$~D', $path) !== 1) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || $part[0] === '.') return false;
        }
        if ($path === 'VERSION') return true;
        if (preg_match('~(^|/)(?:storage|config|database|migrations|vendor|node_modules|uploads|private|installer|updater)(/|$)~i', $path)
            || preg_match('~\.(?:sql|sqlite|log|bak|pem|key|p12|pfx|env|htaccess|ini)$~i', $path)) return false;
        return str_starts_with($path, 'application/app/')
            || str_starts_with($path, 'application/routes/')
            || str_starts_with($path, 'application/resources/views/')
            || str_starts_with($path, 'assets/');
    }

    /**
     * @return array{archive_sha256:string,installed_version:string,target_version:string,files:array<int,array<string,mixed>>}
     */
    public static function inspect(
        string $siteRoot, string $archivePath, string $installedVersion,
        array $signedEnvelope, string $channel
    ): array {
        if (!extension_loaded('sodium') || !class_exists(ZipArchive::class))
            throw new RuntimeException('PORTABLE_RUNTIME_MISSING');
        if (is_link($siteRoot) || !is_dir($siteRoot)
            || realpath($siteRoot) !== $siteRoot || $siteRoot === '/')
            throw new RuntimeException('PORTABLE_SITE_ROOT_INVALID');
        if (is_link($archivePath) || !is_file($archivePath) || !is_readable($archivePath))
            throw new RuntimeException('PORTABLE_ARCHIVE_UNSAFE');

        // Verify the complete Ed25519 envelope using the EXISTING publisher keys.
        $target = TrustedReleaseFeed::verifyEnvelope($signedEnvelope, $installedVersion, $channel);
        $m = $signedEnvelope['manifest'];
        if (($m['migration_policy'] ?? '') !== 'additive'
            || !is_int($m['package_bytes'] ?? null)
            || $m['package_bytes'] < 1 || $m['package_bytes'] > self::MAX_ZIP
            || filesize($archivePath) !== $m['package_bytes']
            || !hash_equals($m['package_sha256'], (string)hash_file('sha256', $archivePath)))
            throw new RuntimeException('PORTABLE_ARCHIVE_NOT_SIGNED_OR_TOO_LARGE');

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true)
            throw new RuntimeException('PORTABLE_ZIP_INVALID');
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES + 1)
                throw new RuntimeException('PORTABLE_ZIP_FILE_COUNT');
            $seen = [];
            $files = [];
            $total = 0;
            $versionFound = false;
            $changedCode = false;
            foreach (range(0, $zip->numFiles - 1) as $i) {
                $st = $zip->statIndex($i);
                if (!is_array($st) || !is_string($st['name'] ?? null))
                    throw new RuntimeException('PORTABLE_ZIP_ENTRY_INVALID');
                $name = $st['name'];
                // Explicit directories, symlinks and nonregular entries are not in the MVP.
                if (str_ends_with($name, '/') || !self::allowedPath($name)
                    || isset($seen[strtolower($name)]))
                    throw new RuntimeException('PORTABLE_ZIP_PATH_DENIED');
                $seen[strtolower($name)] = true;
                $os = 0; $attrs = 0;
                if ($zip->getExternalAttributesIndex($i, $os, $attrs)) {
                    $type = ($attrs >> 16) & 0170000;
                    if ($type !== 0 && $type !== 0100000)
                        throw new RuntimeException('PORTABLE_ZIP_SPECIAL_ENTRY');
                }
                $size = $st['size'] ?? null;
                $packed = $st['comp_size'] ?? null;
                if (!is_int($size) || !is_int($packed) || $size < 1
                    || $size > self::MAX_FILE || $packed < 0
                    || ($packed === 0 && $size !== 0)
                    || ($packed > 0 && $size > $packed * 200)
                    || !in_array($st['comp_method'] ?? -1, [0, 8], true)
                    || (isset($st['encryption_method']) && $st['encryption_method'] !== 0))
                    throw new RuntimeException('PORTABLE_ZIP_BOUNDS');
                $total += $size;
                if ($total > self::MAX_UNCOMPRESSED)
                    throw new RuntimeException('PORTABLE_ZIP_TOTAL_BOUNDS');
                // Every parent must already exist and must never be a link.
                $cursor = $siteRoot;
                $pieces = explode('/', $name);
                $last = array_pop($pieces);
                foreach ($pieces as $p) {
                    $cursor .= '/' . $p;
                    if (is_link($cursor) || !is_dir($cursor))
                        throw new RuntimeException('PORTABLE_PARENT_NOT_SAFE');
                }
                $destination = $cursor . '/' . $last;
                if (is_link($destination) || (file_exists($destination) && !is_file($destination)))
                    throw new RuntimeException('PORTABLE_TARGET_NOT_REGULAR');
                if (!is_writable($cursor) || (file_exists($destination) && !is_writable($destination)))
                    throw new RuntimeException('PORTABLE_FILE_NOT_WRITABLE');
                $stream = $zip->getStream($name);
                if ($stream === false) throw new RuntimeException('PORTABLE_ZIP_STREAM');
                $digest = hash_init('sha256'); $bytes = 0; $versionText = '';
                try {
                    while (!feof($stream)) {
                        $chunk = fread($stream, 65536);
                        if (!is_string($chunk) || ($chunk === '' && !feof($stream)))
                            throw new RuntimeException('PORTABLE_ZIP_READ');
                        $bytes += strlen($chunk);
                        if ($bytes > $size) throw new RuntimeException('PORTABLE_ZIP_SIZE');
                        hash_update($digest, $chunk);
                        if ($name === 'VERSION') $versionText .= $chunk;
                    }
                } finally { fclose($stream); }
                if ($bytes !== $size) throw new RuntimeException('PORTABLE_ZIP_SIZE');
                if ($name === 'VERSION') {
                    if ($size > 48 || !hash_equals($target, trim($versionText)))
                        throw new RuntimeException('PORTABLE_VERSION_MISMATCH');
                    $versionFound = true;
                }
                $newSha = hash_final($digest);
                $baseline = is_file($destination) ? hash_file('sha256', $destination) : null;
                if ($baseline === false)
                    throw new RuntimeException('PORTABLE_BASELINE_UNREADABLE');
                if ($name !== 'VERSION' && ($baseline === null
                    || !hash_equals($baseline, $newSha))) $changedCode = true;
                $files[] = [
                    'path' => $name, 'bytes' => $size, 'sha256' => $newSha,
                    'baseline' => $baseline,
                ];
            }
            if (!$versionFound || count($files) < 2)
                throw new RuntimeException('PORTABLE_NO_CODE_CHANGE');
            if (!$changedCode) {
                // A one-time, specifically signed release already has its code
                // on this author-owned installation. Never generalize this to
                // arbitrary version-only packages or untrusted ZIP contents.
                $expectedReceiver = 'application/app/Services/Updates/PrivateArtifactReceiverV2.php';
                $expectedHash = '66a8112132aae407fd4094fb64e42bf8d1b34dd6d5994a5c0b6aaf5f0c690097';
                $receiver = null;
                foreach ($files as $entry) {
                    if ($entry['path'] === $expectedReceiver) $receiver = $entry;
                }
                if ($installedVersion !== '0.9.22-dev'
                    || $target !== '0.9.23'
                    || ($m['release_id'] ?? null) !== 'barama-2026-0-9-23-receiver-racefix'
                    || ($m['package_sha256'] ?? null) !== '17f887aaa1a5dfeadf7e62ec0edbfb772c094793fe00d05f039f44405ba4a146'
                    || count($files) !== 2 || $receiver === null
                    || !hash_equals($expectedHash, $receiver['sha256'])
                    || !is_string($receiver['baseline'])
                    || !hash_equals($expectedHash, $receiver['baseline'])) {
                    throw new RuntimeException('PORTABLE_NO_CODE_CHANGE');
                }
            }
            return [
                'archive_sha256' => $m['package_sha256'],
                'installed_version' => $installedVersion,
                'target_version' => $target,
                'files' => $files,
            ];
        } finally { $zip->close(); }
    }
}
