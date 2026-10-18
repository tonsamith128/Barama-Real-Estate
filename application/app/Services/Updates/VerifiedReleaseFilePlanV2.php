<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Read-only file plan for a publisher-signed V2 archive.
 * A plan is NEVER permission to install; backups, DB safety, lock, and recovery
 * require a separately reviewed installer before Update Now can be enabled.
 */
final class VerifiedReleaseFilePlanV2
{
    private const MAX_TARGETS = 8000;

    /**
     * @return array{status:string,installed_version:string,target_version:string,archive_sha256:string,files:int,bytes:int,targets:list<array{path:string,bytes:int,archive_sha256:string,installed_sha256:?string}>,installation_enabled:bool}
     */
    public static function inspect(string $envelopePath, string $archivePath, string $installedVersion): array
    {
        if (!defined('BASE_PATH') || !is_string(BASE_PATH)
            || is_link(BASE_PATH) || !is_dir(BASE_PATH)) {
            throw new RuntimeException('UPDATE_ROOT_UNTRUSTED');
        }
        $root = realpath(BASE_PATH);
        if (!is_string($root) || $root === '/' || !hash_equals($root, rtrim(BASE_PATH, '/'))) {
            throw new RuntimeException('UPDATE_ROOT_UNTRUSTED');
        }
        // The existing verifier authenticates the Ed25519 signature, product,
        // compatibility, full archive digest, and ZIP entry security contract.
        $verified = SignedReleaseInspector::inspectPaths($envelopePath, $archivePath, $installedVersion);
        $signedSha = $verified['archive_sha256'] ?? null;
        $version = $verified['version'] ?? null;
        if (!is_string($signedSha) || preg_match('/^[a-f0-9]{64}$/D', $signedSha) !== 1
            || !is_string($version) || version_compare($version, $installedVersion, '<=')) {
            throw new RuntimeException('UPDATE_SIGNED_RELEASE_INVALID');
        }
        if (is_link($archivePath) || !is_file($archivePath)
            || !hash_equals($signedSha, (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('UPDATE_ARCHIVE_CHANGED');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('UPDATE_ARCHIVE_INVALID');
        }
        $targets = [];
        $seen = [];
        $total = 0;
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_TARGETS) {
                throw new RuntimeException('UPDATE_TARGET_COUNT_INVALID');
            }
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
                    throw new RuntimeException('UPDATE_ENTRY_INVALID');
                }
                $name = $stat['name'];
                if (str_ends_with($name, '/')) {
                    continue;
                }
                self::validateTarget($name);
                $caseFold = strtolower($name);
                if (isset($seen[$caseFold])) {
                    throw new RuntimeException('UPDATE_TARGET_DUPLICATE');
                }
                $seen[$caseFold] = true;
                $size = $stat['size'] ?? null;
                if (!is_int($size) || $size < 0 || $size > 2147483648) {
                    throw new RuntimeException('UPDATE_ENTRY_SIZE_INVALID');
                }
                $total += $size;
                if ($total > 2147483648) {
                    throw new RuntimeException('UPDATE_EXPANDED_SIZE_EXCEEDED');
                }
                $absolute = self::checkedDestination($root, $name);
                $previous = null;
                if (file_exists($absolute)) {
                    if (!is_file($absolute) || is_link($absolute)) {
                        throw new RuntimeException('UPDATE_TARGET_NOT_REGULAR');
                    }
                    $digest = hash_file('sha256', $absolute);
                    if (!is_string($digest)) {
                        throw new RuntimeException('UPDATE_TARGET_UNREADABLE');
                    }
                    $previous = $digest;
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('UPDATE_ENTRY_UNREADABLE');
                }
                $ctx = hash_init('sha256');
                $count = 0;
                try {
                    while (!feof($stream)) {
                        $part = fread($stream, 65536);
                        if (!is_string($part) || ($part === '' && !feof($stream))) {
                            throw new RuntimeException('UPDATE_ENTRY_UNREADABLE');
                        }
                        $count += strlen($part);
                        if ($count > $size) {
                            throw new RuntimeException('UPDATE_ENTRY_SIZE_MISMATCH');
                        }
                        hash_update($ctx, $part);
                    }
                } finally {
                    fclose($stream);
                }
                if ($count !== $size) {
                    throw new RuntimeException('UPDATE_ENTRY_SIZE_MISMATCH');
                }
                $targets[] = [
                    'path' => $name,
                    'bytes' => $size,
                    'archive_sha256' => hash_final($ctx),
                    'installed_sha256' => $previous,
                ];
            }
        } finally {
            $zip->close();
        }
        if ($targets === [] || !hash_equals($signedSha, (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('UPDATE_ARCHIVE_CHANGED');
        }
        return [
            'status' => 'verified_plan_only',
            'installed_version' => $installedVersion,
            'target_version' => $version,
            'archive_sha256' => $signedSha,
            'files' => count($targets),
            'bytes' => $total,
            'targets' => $targets,
            'installation_enabled' => false,
        ];
    }

    /** Refuse all protected runtime and database paths; no file writes. */
    private static function validateTarget(string $path): void
    {
        if ($path === '' || strlen($path) > 512
            || preg_match('~^[A-Za-z0-9_./-]+$~D', $path) !== 1
            || str_contains($path, '//') || str_contains($path, '\\')
            || str_starts_with($path, '/')) {
            throw new RuntimeException('UPDATE_TARGET_PATH_INVALID');
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
                throw new RuntimeException('UPDATE_TARGET_PATH_INVALID');
            }
        }
        if (str_starts_with($path, 'application/database/migrations/')) {
            throw new RuntimeException('UPDATE_MIGRATION_REVIEW_REQUIRED');
        }
        $allowed = $path === 'VERSION'
            || in_array($path, ['index.php', 'updater/index.php', 'installer/index.php'], true)
            || str_starts_with($path, 'application/app/')
            || str_starts_with($path, 'application/routes/')
            || str_starts_with($path, 'application/resources/views/')
            || str_starts_with($path, 'assets/');
        if (!$allowed || preg_match('~(^|/)(storage|config|vendor|node_modules|uploads|private|\.git|\.env)(/|$)~i', $path)
            || preg_match('~\.(?:pem|key|p12|pfx|sql|sqlite|bak|log)$~i', $path)) {
            throw new RuntimeException('UPDATE_TARGET_PROTECTED');
        }
    }

    private static function checkedDestination(string $root, string $path): string
    {
        $cursor = $root;
        $parts = explode('/', $path);
        foreach ($parts as $index => $segment) {
            $cursor .= '/' . $segment;
            if (is_link($cursor)) {
                throw new RuntimeException('UPDATE_TARGET_SYMLINK');
            }
            if (file_exists($cursor) && $index < count($parts) - 1 && !is_dir($cursor)) {
                throw new RuntimeException('UPDATE_TARGET_PARENT_INVALID');
            }
        }
        return $cursor;
    }
}
