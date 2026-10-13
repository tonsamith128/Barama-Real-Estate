<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Publisher V2 offline package verifier. A verified manifest never authorizes
 * installation, and this class never downloads, extracts, migrates or writes.
 * Old V1 RSA manifest files are deliberately not accepted.
 */
final class SignedReleaseInspector
{
    private const MAX_BYTES = 536870912;
    private const MAX_ENTRIES = 8000;
    private const MAX_TOTAL = 2147483648;

    public static function locations(): array
    {
        return [
            'key' => APP_PATH . '/config/update-publisher-v2-keys.json',
            'manifest' => APP_PATH . '/storage/app/updates/staged/envelope.json',
            'archive' => APP_PATH . '/storage/app/updates/staged/release.zip',
        ];
    }

    public static function readiness(): array
    {
        $p = self::locations();
        $key = self::safeFile($p['key'], 65536);
        $staged = self::safeFile($p['manifest'], 16384)
            && self::safeFile($p['archive'], self::MAX_BYTES);
        $runtime = extension_loaded('sodium') && class_exists(ZipArchive::class);
        return [
            'trusted_key' => $key && extension_loaded('sodium'),
            'staged_release' => $staged,
            'inspect_enabled' => $key && $staged && $runtime,
            'zip_available' => class_exists(ZipArchive::class),
        ];
    }

    /** @return array{status:string,version:string,channel:string,archive_sha256:string,files:int} */
    public static function inspect(string $installedVersion): array
    {
        $p = self::locations();
        return self::inspectPaths($p['manifest'], $p['archive'], $installedVersion);
    }

    /** Read-only; paths are local, must not be web requests or symlinks. */
    public static function inspectPaths(string $envelopePath, string $archivePath, string $installedVersion): array
    {
        if (!extension_loaded('sodium') || !class_exists(ZipArchive::class)) {
            throw new RuntimeException('UPDATE_VERIFY_RUNTIME_MISSING');
        }
        if (!self::safeFile($envelopePath, 16384) || !self::safeFile($archivePath, self::MAX_BYTES)) {
            throw new RuntimeException('UPDATE_STAGE_NOT_AVAILABLE');
        }
        try {
            $env = json_decode((string) file_get_contents($envelopePath), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('UPDATE_ENVELOPE_INVALID');
        }
        if (!is_array($env) || !is_array($env['manifest'] ?? null)) {
            throw new RuntimeException('UPDATE_ENVELOPE_INVALID');
        }
        $m = $env['manifest'];
        // Uses the already-deployed, independently pinned Publisher V2 key catalog.
        $channel = $m['channel'] ?? null;
        if (!is_string($channel) || !in_array($channel, ['stable', 'beta'], true)) {
            throw new RuntimeException('UPDATE_CHANNEL_INVALID');
        }
        $version = TrustedReleaseFeed::verifyEnvelope($env, $installedVersion, $channel);
        $bytes = filesize($archivePath);
        if ($bytes === false || $bytes !== ($m['package_bytes'] ?? null)
            || !is_string($m['package_sha256'] ?? null)
            || !hash_equals($m['package_sha256'], (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('UPDATE_ARCHIVE_DIGEST_MISMATCH');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('UPDATE_ARCHIVE_INVALID');
        }
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('UPDATE_ARCHIVE_ENTRY_COUNT');
            }
            $seen = [];
            $total = 0;
            $versionData = null;
            $codeFiles = 0;
            $changedCode = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                if (!is_array($st)) throw new RuntimeException('UPDATE_ARCHIVE_INVALID');
                $name = (string) ($st['name'] ?? '');
                $dir = str_ends_with($name, '/');
                $normalized = $dir ? substr($name, 0, -1) : $name;
                if ($normalized === '' || strlen($normalized) > 512
                    || !preg_match('/^[A-Za-z0-9_.\/-]+$/D', $normalized)
                    || str_contains($normalized, '\\')
                    || preg_match('/[\x00-\x1f\x7f]/', $normalized)
                    || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
                    throw new RuntimeException('UPDATE_ARCHIVE_PATH_INVALID');
                }
                foreach (explode('/', $normalized) as $segment) {
                    if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                        throw new RuntimeException('UPDATE_ARCHIVE_PATH_INVALID');
                    }
                }
                $fold = strtolower($normalized);
                if (isset($seen[$fold])) throw new RuntimeException('UPDATE_ARCHIVE_DUPLICATE');
                $seen[$fold] = true;
                // Directory-only members are permitted only as ancestors of allowed code trees.
                $container = $dir && in_array($normalized, [
                    'application', 'application/app', 'application/routes',
                    'application/database', 'application/database/migrations',
                    'application/resources', 'application/resources/views',
                    'assets', 'updater', 'installer',
                ], true);
                $allowed = $container || $normalized === 'VERSION'
                    || str_starts_with($normalized, 'application/app/')
                    || str_starts_with($normalized, 'application/routes/')
                    || str_starts_with($normalized, 'application/database/migrations/')
                    || str_starts_with($normalized, 'application/resources/views/')
                    || str_starts_with($normalized, 'assets/')
                    || in_array($normalized, ['index.php', 'updater/index.php', 'installer/index.php'], true);
                if (!$allowed || preg_match('~(^|/)(storage|config|vendor|node_modules|uploads|private|\.git|\.env|\.htaccess|\.user\.ini)(/|$)~i', $normalized)
                    || preg_match('~\.(?:pem|key|p12|pfx|sql|sqlite|bak|log)$~i', $normalized)) {
                    throw new RuntimeException('UPDATE_ARCHIVE_PROTECTED_PATH');
                }
                if ($dir && preg_match('~\.(?:php|js|css|json)$~i', $normalized)) {
                    throw new RuntimeException('UPDATE_ARCHIVE_FAKE_DIRECTORY');
                }
                $opsys = 0; $attrs = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attrs)) {
                    $type = ($attrs >> 16) & 0170000;
                    if ($type !== 0 && $type !== ($dir ? 0040000 : 0100000)) {
                        throw new RuntimeException('UPDATE_ARCHIVE_SPECIAL_FILE');
                    }
                }
                if (!$dir) {
                    $size = $st['size'] ?? null;
                    $compressed = $st['comp_size'] ?? null;
                    if (!is_int($size) || !is_int($compressed) || $size < 0 || $compressed < 0
                        || $size > self::MAX_TOTAL || ($compressed === 0 && $size !== 0)
                        || ($compressed > 0 && $size > $compressed * 200)) {
                        throw new RuntimeException('UPDATE_ARCHIVE_BOMB');
                    }
                    $total += $size;
                    if ($total > self::MAX_TOTAL || !in_array($st['comp_method'] ?? -1, [0, 8], true)
                        || (isset($st['encryption_method']) && (int) $st['encryption_method'] !== 0)) {
                        throw new RuntimeException('UPDATE_ARCHIVE_LIMIT');
                    }
                    if ($normalized === 'VERSION') {
                        if ($size > 48) throw new RuntimeException('UPDATE_ARCHIVE_VERSION_INVALID');
                        $versionData = $zip->getFromIndex($i);
                    } else {
                        $codeFiles++;
                        if (!$changedCode && $size <= 10000000 && defined('BASE_PATH')) {
                            $livePath = BASE_PATH . '/' . $normalized;
                            if (is_file($livePath) && !is_link($livePath)
                                && filesize($livePath) === $size) {
                                $stream = $zip->getStream($name);
                                if ($stream !== false) {
                                    $hash = hash_init('sha256');
                                    while (!feof($stream)) {
                                        $chunk = fread($stream, 65536);
                                        if (!is_string($chunk) || ($chunk === '' && !feof($stream))) {
                                            fclose($stream); throw new RuntimeException('UPDATE_ARCHIVE_STREAM_ERROR');
                                        }
                                        hash_update($hash, $chunk);
                                    }
                                    fclose($stream);
                                    $changedCode = !hash_equals((string)hash_file('sha256', $livePath), hash_final($hash));
                                }
                            } else {
                                $changedCode = true;
                            }
                        } else if (!$changedCode) {
                            $changedCode = true; // larger or previously absent code must undergo manual review
                        }
                    }
                }
            }
            if ($codeFiles < 1 || !$changedCode || !is_string($versionData)
                || !hash_equals($version, trim($versionData))) {
                throw new RuntimeException('UPDATE_ARCHIVE_VERSION_OR_CODE_MISMATCH');
            }
            return [
                'status' => 'Signed package verified (not installed)',
                'version' => $version,
                'channel' => $channel,
                'archive_sha256' => $m['package_sha256'],
                'files' => count($seen),
            ];
        } finally {
            $zip->close();
        }
    }

    private static function safeFile(string $path, int $limit): bool
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $st = @stat($path);
        return is_array($st) && $st['size'] > 0 && $st['size'] <= $limit
            && (($st['mode'] & 0022) === 0);
    }
}
