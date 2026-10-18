<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Throwable;

/**
 * Root-private snapshot prerequisite for signed updater file changes.
 *
 * Source-only: NOT connected to an HTTP route, scheduler, or installer.
 * Capture is allowed on the exact staging site or disposable /tmp fixtures.
 * Restoration is deliberately limited to disposable fixtures.
 */
final class PrivateUpdateFileSnapshotV1
{
    private const STAGING_SITE = '/www/wwwroot/baramastaging.tonsamith.com';
    private const FIXTURE_PREFIX = '/barama-updater-fixture-';
    private const MAX_FILES = 8000;
    private const MAX_FILE_BYTES = 536870912;
    private const MAX_TOTAL_BYTES = 2147483648;

    /**
     * @param list<string> $paths Explicit reviewed paths from a verified archive.
     * @return array{job:string,receipt_sha256:string,files:int,existing:int,bytes:int,fixture_only:bool,restored:bool}
     */
    public static function capture(string $siteRoot, string $privateVault, array $paths, bool $fixture = false): array
    {
        self::cli();
        $site = self::directory($siteRoot);
        $vault = self::directory($privateVault, true);
        self::scope($site, $vault, $fixture);
        if ($paths === [] || count($paths) > self::MAX_FILES || !array_is_list($paths)) {
            throw new RuntimeException('FILE_SNAPSHOT_PATH_COUNT_INVALID');
        }
        $seen = [];
        foreach ($paths as $path) {
            self::relative($path);
            $fold = strtolower($path);
            if (isset($seen[$fold])) throw new RuntimeException('FILE_SNAPSHOT_DUPLICATE_PATH');
            $seen[$fold] = true;
            self::safeLeaf($site, $path);
        }

        $job = $vault . '/files-' . gmdate('Ymd') . '-' . bin2hex(random_bytes(12));
        if (!@mkdir($job, 0700) || !@mkdir($job.'/files', 0700)) {
            throw new RuntimeException('FILE_SNAPSHOT_PRIVATE_CREATE_FAILED');
        }
        try {
            $entries = [];
            $bytes = 0;
            $count = 0;
            foreach ($paths as $path) {
                $src = $site.'/'.$path;
                if (is_dir($src) || (file_exists($src) && !is_file($src))) {
                    throw new RuntimeException('FILE_SNAPSHOT_SOURCE_NOT_REGULAR');
                }
                if (!is_file($src)) {
                    $entries[] = ['path'=>$path, 'existed'=>false];
                    continue;
                }
                if (is_link($src) || !is_readable($src)) throw new RuntimeException('FILE_SNAPSHOT_SOURCE_UNSAFE');
                $size = filesize($src);
                if (!is_int($size) || $size < 0 || $size > self::MAX_FILE_BYTES
                    || $bytes + $size > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('FILE_SNAPSHOT_SIZE_LIMIT');
                }
                $copy = $job.'/files/'.$path;
                self::privateParents($job.'/files', $path);
                $hash = self::copyNew($src, $copy, $size);
                if (!hash_equals($hash, (string) hash_file('sha256', $src))) {
                    throw new RuntimeException('FILE_SNAPSHOT_SOURCE_CHANGED');
                }
                $bytes += $size;
                $count++;
                $entries[] = ['path'=>$path, 'existed'=>true, 'bytes'=>$size, 'sha256'=>$hash];
            }
            $receipt = [
                'schema'=>'barama-private-file-snapshot-v1',
                'state'=>$fixture ? 'fixture_only' : 'staging_only_not_restored',
                'created_at_utc'=>gmdate('c'),
                'files'=>$entries,
                'existing_files'=>$count,
                'total_bytes'=>$bytes,
                'site_writes'=>false,
                'restore_tested'=>false,
                'automatic_installation_enabled'=>false,
            ];
            self::writeNew($job.'/receipt.json', json_encode(
                $receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            )."\n");
            $sha = hash_file('sha256', $job.'/receipt.json');
            if (!is_string($sha) || !self::verify($job, $sha)) {
                throw new RuntimeException('FILE_SNAPSHOT_VERIFY_FAILED');
            }
            return [
                'job'=>$job, 'receipt_sha256'=>$sha, 'files'=>count($entries),
                'existing'=>$count, 'bytes'=>$bytes,
                'fixture_only'=>$fixture, 'restored'=>false,
            ];
        } catch (Throwable $error) {
            self::removeJob($job);
            throw $error;
        }
    }

    /**
     * Verifies pinned receipt and every backed-up byte. Does not restore files.
     */
    public static function verify(string $jobPath, string $receiptSha256): bool
    {
        self::cli();
        self::sha($receiptSha256);
        $job = self::directory($jobPath, true);
        self::jobScope($job);
        if (!preg_match('~^files-[0-9]{8}-[a-f0-9]{24}$~D', basename($job))) {
            throw new RuntimeException('FILE_SNAPSHOT_JOB_INVALID');
        }
        $receiptPath = $job.'/receipt.json';
        if (!self::secureFile($receiptPath)
            || !hash_equals($receiptSha256, (string) hash_file('sha256', $receiptPath))) {
            throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_TAMPERED');
        }
        try {
            $receipt = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_INVALID');
        }
        if (!is_array($receipt) || ($receipt['schema'] ?? null) !== 'barama-private-file-snapshot-v1'
            || !in_array($receipt['state'] ?? null, ['fixture_only', 'staging_only_not_restored'], true)
            || !is_array($receipt['files'] ?? null) || !array_is_list($receipt['files'])
            || count($receipt['files']) < 1 || count($receipt['files']) > self::MAX_FILES
            || ($receipt['site_writes'] ?? null) !== false
            || ($receipt['restore_tested'] ?? null) !== false
            || ($receipt['automatic_installation_enabled'] ?? null) !== false) {
            throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_INVALID');
        }
        $seen = [];
        $total = 0; $existing = 0;
        foreach ($receipt['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)
                || !is_bool($entry['existed'] ?? null)) {
                throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_INVALID');
            }
            $path = $entry['path'];
            self::relative($path);
            if (isset($seen[strtolower($path)])) throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_DUPLICATE');
            $seen[strtolower($path)] = true;
            $copy = $job.'/files/'.$path;
            self::safeLeaf($job.'/files', $path);
            if ($entry['existed'] === false) {
                if (count($entry) !== 2 || file_exists($copy) || is_link($copy)) {
                    throw new RuntimeException('FILE_SNAPSHOT_MISSING_ENTRY_INVALID');
                }
                continue;
            }
            if (!is_int($entry['bytes'] ?? null) || $entry['bytes'] < 0
                || $entry['bytes'] > self::MAX_FILE_BYTES
                || !is_string($entry['sha256'] ?? null)) {
                throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_INVALID');
            }
            self::sha($entry['sha256']);
            if (!self::secureFile($copy) || filesize($copy) !== $entry['bytes']
                || !hash_equals($entry['sha256'], (string) hash_file('sha256', $copy))) {
                throw new RuntimeException('FILE_SNAPSHOT_BYTES_TAMPERED');
            }
            $total += $entry['bytes']; $existing++;
            if ($total > self::MAX_TOTAL_BYTES) throw new RuntimeException('FILE_SNAPSHOT_SIZE_LIMIT');
        }
        if ($total !== ($receipt['total_bytes'] ?? null)
            || $existing !== ($receipt['existing_files'] ?? null)) {
            throw new RuntimeException('FILE_SNAPSHOT_TOTALS_INVALID');
        }
        return true;
    }

    /**
     * Rehearse rollback only on a disposable fixture, never on staging/production.
     * Missing original paths are removed, existing original paths are restored.
     */
    public static function rehearseRestore(string $jobPath, string $receiptSha256, string $fixtureDestination): array
    {
        if (!self::verify($jobPath, $receiptSha256)) throw new RuntimeException('FILE_SNAPSHOT_VERIFY_FAILED');
        $job = self::directory($jobPath, true);
        $dest = self::directory($fixtureDestination);
        if (!self::fixture($dest) || str_starts_with($job.'/', $dest.'/')
            || str_starts_with($dest.'/', $job.'/')) {
            throw new RuntimeException('FILE_SNAPSHOT_RESTORE_FIXTURE_ONLY');
        }
        $record = json_decode((string)file_get_contents($job.'/receipt.json'), true, 32, JSON_THROW_ON_ERROR);
        if ($record['state'] !== 'fixture_only') throw new RuntimeException('FILE_SNAPSHOT_RESTORE_FIXTURE_ONLY');
        $restored = 0; $removed = 0;
        foreach ($record['files'] as $entry) {
            $path = $entry['path'];
            self::safeLeaf($dest, $path);
            $target = $dest.'/'.$path;
            if ($entry['existed'] === false) {
                if (file_exists($target) && !is_file($target)) {
                    throw new RuntimeException('FILE_SNAPSHOT_FIXTURE_TARGET_UNSAFE');
                }
                if (is_file($target) && !unlink($target)) throw new RuntimeException('FILE_SNAPSHOT_FIXTURE_DELETE_FAILED');
                $removed++;
                continue;
            }
            self::privateParents($dest, $path);
            $src = $job.'/files/'.$path;
            if (file_exists($target) && !is_file($target)) throw new RuntimeException('FILE_SNAPSHOT_FIXTURE_TARGET_UNSAFE');
            $temp = dirname($target).'/.snapshot-'.bin2hex(random_bytes(8));
            try {
                $digest = self::copyNew($src, $temp, $entry['bytes']);
                if (!hash_equals($digest, $entry['sha256']) || !rename($temp, $target)) {
                    throw new RuntimeException('FILE_SNAPSHOT_FIXTURE_RESTORE_FAILED');
                }
            } finally { if (is_file($temp)) @unlink($temp); }
            $restored++;
        }
        return ['fixture_only'=>true,'restored'=>$restored,'removed'=>$removed,'live_site_writes'=>false];
    }

    private static function scope(string $site, string $vault, bool $fixture): void
    {
        if ($site === $vault || str_starts_with($site.'/', $vault.'/')
            || str_starts_with($vault.'/', $site.'/')) {
            throw new RuntimeException('FILE_SNAPSHOT_ROOTS_OVERLAP');
        }
        if ($fixture) {
            if (!self::fixture($site) || !self::fixture($vault)) {
                throw new RuntimeException('FILE_SNAPSHOT_FIXTURE_SCOPE_INVALID');
            }
        } elseif ($site !== self::STAGING_SITE || !str_starts_with($vault.'/', '/root/')
            || (function_exists('posix_geteuid') && posix_geteuid() !== 0)) {
            throw new RuntimeException('FILE_SNAPSHOT_STAGING_ONLY');
        }
    }

    private static function fixture(string $path): bool
    {
        return str_starts_with($path.'/', rtrim(sys_get_temp_dir(), '/').self::FIXTURE_PREFIX);
    }

    private static function relative(string $path): void
    {
        if ($path === '' || strlen($path) > 512
            || preg_match('~^[A-Za-z0-9_./-]+$~D', $path) !== 1
            || str_starts_with($path, '/') || str_contains($path, '//')) {
            throw new RuntimeException('FILE_SNAPSHOT_PATH_INVALID');
        }
        foreach (explode('/', $path) as $s) {
            if ($s === '' || $s === '.' || $s === '..' || str_starts_with($s, '.')) {
                throw new RuntimeException('FILE_SNAPSHOT_PATH_INVALID');
            }
        }
        $allowed = $path === 'VERSION'
            || in_array($path, ['index.php','updater/index.php','installer/index.php'], true)
            || str_starts_with($path,'application/app/')
            || str_starts_with($path,'application/routes/')
            || str_starts_with($path,'application/database/migrations/')
            || str_starts_with($path,'application/resources/views/')
            || str_starts_with($path,'assets/');
        if (!$allowed || preg_match('~(^|/)(storage|config|vendor|node_modules|uploads|private)(/|$)~i', $path)
            || preg_match('~\.(?:pem|key|p12|pfx|sql|sqlite|bak|log)$~i', $path)) {
            throw new RuntimeException('FILE_SNAPSHOT_PROTECTED_PATH');
        }
    }

    private static function safeLeaf(string $root, string $path): void
    {
        $at = $root;
        foreach (explode('/', $path) as $component) {
            $at .= '/'.$component;
            if (is_link($at)) throw new RuntimeException('FILE_SNAPSHOT_SYMLINK_DENIED');
            if (file_exists($at) && !is_dir($at) && !is_file($at)) {
                throw new RuntimeException('FILE_SNAPSHOT_SPECIAL_FILE_DENIED');
            }
        }
    }

    private static function directory(string $path, bool $private = false): string
    {
        if (is_link($path) || !is_dir($path)) throw new RuntimeException('FILE_SNAPSHOT_DIRECTORY_INVALID');
        $real = realpath($path);
        if (!is_string($real) || $real === '/' || $real !== rtrim($path, '/')) {
            throw new RuntimeException('FILE_SNAPSHOT_DIRECTORY_UNSAFE');
        }
        if ($private && ((fileperms($real) & 0077) !== 0
            || (function_exists('posix_geteuid') && fileowner($real) !== posix_geteuid()))) {
            throw new RuntimeException('FILE_SNAPSHOT_PRIVATE_PERMISSIONS');
        }
        return $real;
    }

    private static function jobScope(string $job): void
    {
        if (!str_starts_with($job.'/', '/root/')
            && !self::fixture($job)) {
            throw new RuntimeException('FILE_SNAPSHOT_PRIVATE_ROOT_REQUIRED');
        }
    }

    private static function sha(string $hash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('FILE_SNAPSHOT_SHA_INVALID');
        }
    }

    private static function secureFile(string $path): bool
    {
        return !is_link($path) && is_file($path) && is_readable($path)
            && ((fileperms($path) & 0077) === 0);
    }

    private static function privateParents(string $root, string $path): void
    {
        $parent = dirname($path);
        if ($parent === '.') return;
        $at = $root;
        foreach (explode('/', $parent) as $part) {
            $at .= '/'.$part;
            if (is_link($at)) throw new RuntimeException('FILE_SNAPSHOT_PARENT_SYMLINK');
            if (!is_dir($at) && !@mkdir($at, 0700)) {
                throw new RuntimeException('FILE_SNAPSHOT_PARENT_CREATE_FAILED');
            }
        }
    }

    private static function copyNew(string $source, string $target, int $expected): string
    {
        $in = @fopen($source,'rb');
        $out = @fopen($target,'xb');
        if (!is_resource($in) || !is_resource($out)) {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            throw new RuntimeException('FILE_SNAPSHOT_STREAM_OPEN_FAILED');
        }
        try {
            if (!@chmod($target,0600)) throw new RuntimeException('FILE_SNAPSHOT_CHMOD_FAILED');
            $ctx = hash_init('sha256'); $size = 0;
            while (!feof($in)) {
                $part = fread($in,65536);
                if ($part === false || ($part === '' && !feof($in))) {
                    throw new RuntimeException('FILE_SNAPSHOT_READ_FAILED');
                }
                $length = strlen($part); $offset = 0;
                while ($offset < $length) {
                    $n = fwrite($out, substr($part,$offset));
                    if (!is_int($n) || $n <= 0) throw new RuntimeException('FILE_SNAPSHOT_WRITE_FAILED');
                    $offset += $n;
                }
                $size += $length;
                if ($size > $expected) throw new RuntimeException('FILE_SNAPSHOT_SOURCE_CHANGED');
                hash_update($ctx, $part);
            }
            if ($size !== $expected || !fflush($out)
                || (function_exists('fsync') && !fsync($out))) {
                throw new RuntimeException('FILE_SNAPSHOT_INCOMPLETE');
            }
            return hash_final($ctx);
        } finally { fclose($in); fclose($out); }
    }

    private static function writeNew(string $path, string $content): void
    {
        $h = @fopen($path,'xb');
        if (!is_resource($h)) throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_OPEN_FAILED');
        try {
            if (!@chmod($path,0600)) throw new RuntimeException('FILE_SNAPSHOT_CHMOD_FAILED');
            if (fwrite($h,$content) !== strlen($content) || !fflush($h)
                || (function_exists('fsync') && !fsync($h))) {
                throw new RuntimeException('FILE_SNAPSHOT_RECEIPT_WRITE_FAILED');
            }
        } finally { fclose($h); }
    }

    private static function removeJob(string $job): void
    {
        if (!is_dir($job) || is_link($job)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($job, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($job);
    }

    private static function cli(): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('FILE_SNAPSHOT_CLI_ONLY');
    }
}
