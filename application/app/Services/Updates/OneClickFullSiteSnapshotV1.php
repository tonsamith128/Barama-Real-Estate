<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * COMPLETE SITE FILE snapshot, separate from SQL, for a future authorized update.
 * Does not freeze traffic, restore files, import SQL or authorize installation.
 * Staging mode demands root CLI and a separately prepared root-private vault.
 * Fixture mode is confined to a random /tmp disposable site.
 *
 * Calling this class does NOT create a backup; prepare() requires explicit
 * approval. No website files are modified by either method.
 */
final class OneClickFullSiteSnapshotV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const STAGING_VAULT = '/root/Barama-OneClick-Recovery/full-site';
    private const FIXTURE = '~^/tmp/barama-fullsite-fixture-[a-f0-9]{16}$~D';
    private const HASH = '/^[a-f0-9]{64}$/D';
    private const MAX_FILES = 200000;
    private const MAX_BYTES = 53687091200;
    private const MAX_ONE_FILE = 2147483648;

    /** @return array<string,mixed> Safe metadata only; never file contents. */
    public static function prepare(string $site, string $vault, string $approval): array
    {
        self::guard($site, $vault);
        $fixture = str_starts_with($site, '/tmp/');
        $expected = $fixture
            ? 'CREATE_DISPOSABLE_FULL_SITE_SNAPSHOT_ONLY'
            : 'CREATE_STAGING_PRIVATE_FULL_SITE_SNAPSHOT_ONLY';
        if (!hash_equals($expected, $approval)) {
            throw new RuntimeException('FULL_SITE_OWNER_APPROVAL_REQUIRED');
        }
        $id = 'fullsite-' . bin2hex(random_bytes(12));
        $job = $vault . '/' . $id;
        if (!mkdir($job, 0700) || !mkdir($job . '/files', 0700)) {
            throw new RuntimeException('FULL_SITE_PRIVATE_JOB_CREATE_FAILED');
        }
        // Failure retains an untrusted partial private job for operator review.
        // Capture empty directories as well as file data, including permissions.
        $folders = self::folders($site);
        $list = [];
        $total = 0;
        foreach (self::walk($site) as $rel => $original) {
            if (count($list) >= self::MAX_FILES) {
                throw new RuntimeException('FULL_SITE_FILE_COUNT_LIMIT');
            }
            $before = self::regular($original);
            $size = (int) $before['size'];
            if ($size > self::MAX_ONE_FILE || $total + $size > self::MAX_BYTES) {
                throw new RuntimeException('FULL_SITE_SIZE_LIMIT');
            }
            $dest = $job . '/files/' . hash('sha256', $rel) . '.bin';
            $copy = self::copyVerified($original, $dest, $before);
            $list[] = [
                'path' => $rel,
                'size' => $size,
                'sha256' => $copy,
                'mode' => $before['mode'] & 0777,
            ];
            $total += $size;
        }
        usort($list, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        // New/deleted/modified source files or directories during copying
        // MUST invalidate the snapshot rather than accept an incomplete site.
        if (self::folders($site) !== $folders) {
            throw new RuntimeException('FULL_SITE_CHANGED_DURING_COPY');
        }
        $current = [];
        foreach (self::walk($site) as $rel => $original) {
            $stat = self::regular($original);
            if (count($current) >= self::MAX_FILES) throw new RuntimeException('FULL_SITE_CHANGED_DURING_COPY');
            $current[$rel] = ['size' => $stat['size'], 'sha256' => hash_file('sha256', $original)];
        }
        if (count($current) !== count($list)) throw new RuntimeException('FULL_SITE_CHANGED_DURING_COPY');
        foreach ($list as $item) {
            $actual = $current[$item['path']] ?? null;
            if (!is_array($actual) || $actual['size'] !== $item['size']
                || !is_string($actual['sha256'])
                || !hash_equals($item['sha256'], $actual['sha256'])) {
                throw new RuntimeException('FULL_SITE_CHANGED_DURING_COPY');
            }
        }
        $receipt = [
            'schema' => 'barama-full-site-file-snapshot-v1',
            'state' => 'full_site_files_snapshotted_not_restored',
            'site_root' => $site,
            'created_utc' => gmdate('c'),
            'file_count' => count($list),
            'total_bytes' => $total,
            'files' => $list,
            'directories' => $folders,
            'directory_count' => count($folders),
            'full_site_file_inventory_verified' => true,
            'database_included' => false,
            'consistent_db_checkpoint_verified' => false,
            'restore_tested' => false,
            'install_authorized' => false,
            'fixture' => $fixture,
        ];
        $content = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        self::write($job . '/receipt.json', $content);
        $digest = hash_file('sha256', $job . '/receipt.json');
        if (!is_string($digest) || !self::verify($site, $vault, $id, $digest)) {
            throw new RuntimeException('FULL_SITE_SNAPSHOT_REVERIFY_FAILED');
        }
        return [
            'status' => 'FULL_SITE_FILE_COPY_VERIFIED_NOT_RESTORED',
            'snapshot_id' => $id,
            'receipt_sha256' => $digest,
            'file_count' => count($list),
            'directory_count' => count($folders),
            'total_bytes' => $total,
            'database_included' => false,
            'consistent_db_checkpoint_verified' => false,
            'restore_tested' => false,
            'install_authorized' => false,
        ];
    }

    public static function verify(string $site, string $vault, string $id, string $sha): bool
    {
        self::guard($site, $vault);
        if (!preg_match('/^fullsite-[a-f0-9]{24}$/D', $id)
            || preg_match(self::HASH, $sha) !== 1) {
            throw new RuntimeException('FULL_SITE_RECEIPT_BINDING_INVALID');
        }
        $job = $vault . '/' . $id;
        $file = $job . '/receipt.json';
        self::privateDir($job);
        self::privateDir($job . '/files');
        clearstatcache(true, $file);
        if (is_link($file) || !is_file($file)
            || (fileperms($file) & 0077) !== 0
            || filesize($file) > 67108864
            || !hash_equals($sha, (string) hash_file('sha256', $file))) {
            throw new RuntimeException('FULL_SITE_RECEIPT_TAMPERED');
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 10, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('FULL_SITE_RECEIPT_INVALID');
        }
        if (!is_array($data)
            || ($data['schema'] ?? null) !== 'barama-full-site-file-snapshot-v1'
            || ($data['state'] ?? null) !== 'full_site_files_snapshotted_not_restored'
            || ($data['site_root'] ?? null) !== $site
            || ($data['full_site_file_inventory_verified'] ?? false) !== true
            || ($data['database_included'] ?? true) !== false
            || ($data['consistent_db_checkpoint_verified'] ?? true) !== false
            || ($data['restore_tested'] ?? true) !== false
            || ($data['install_authorized'] ?? true) !== false
            || ($data['fixture'] ?? null) !== str_starts_with($site, '/tmp/')
            || !is_array($data['files'] ?? null)
            || count($data['files']) > self::MAX_FILES
            || ($data['file_count'] ?? -1) !== count($data['files'])
            || !is_array($data['directories'] ?? null)
            || count($data['directories']) > self::MAX_FILES
            || ($data['directory_count'] ?? -1) !== count($data['directories'])) {
            throw new RuntimeException('FULL_SITE_RECEIPT_INVALID');
        }
        $dirNames = [];
        foreach ($data['directories'] as $folder) {
            if (!is_array($folder) || !is_string($folder['path'] ?? null)
                || $folder['path'] === '' || strlen($folder['path']) > 2048
                || str_starts_with($folder['path'], '/')
                || str_contains($folder['path'], "\0")
                || preg_match('~(?:^|/)\.\.?(?:/|$)~', $folder['path'])
                || !is_int($folder['mode'] ?? null)
                || ($folder['mode'] & ~0777) !== 0
                || isset($dirNames[$folder['path']])) {
                throw new RuntimeException('FULL_SITE_RECEIPT_FOLDER_INVALID');
            }
            $dirNames[$folder['path']] = true;
        }
        $sum = 0; $paths = [];
        foreach ($data['files'] as $item) {
            if (!is_array($item) || !is_string($item['path'] ?? null)
                || $item['path'] === '' || strlen($item['path']) > 2048
                || str_starts_with($item['path'], '/') || str_contains($item['path'], "\0")
                || preg_match('~(?:^|/)\.\.?(?:/|$)~', $item['path'])
                || !is_int($item['size'] ?? null)
                || $item['size'] < 0 || $item['size'] > self::MAX_ONE_FILE
                || !is_string($item['sha256'] ?? null)
                || preg_match(self::HASH, $item['sha256']) !== 1
                || !is_int($item['mode'] ?? null) || ($item['mode'] & ~0777) !== 0
                || isset($paths[$item['path']])) {
                throw new RuntimeException('FULL_SITE_RECEIPT_FILE_INVALID');
            }
            $paths[$item['path']] = true;
            $sum += $item['size'];
            if ($sum > self::MAX_BYTES) throw new RuntimeException('FULL_SITE_RECEIPT_SIZE_INVALID');
            $copied = $job . '/files/' . hash('sha256', $item['path']) . '.bin';
            clearstatcache(true, $copied);
            if (is_link($copied) || !is_file($copied)
                || (fileperms($copied) & 0077) !== 0
                || filesize($copied) !== $item['size']
                || !hash_equals($item['sha256'], (string) hash_file('sha256', $copied))) {
                throw new RuntimeException('FULL_SITE_COPY_TAMPERED');
            }
        }
        if (($data['total_bytes'] ?? -1) !== $sum) {
            throw new RuntimeException('FULL_SITE_RECEIPT_SIZE_INVALID');
        }
        // Reject unlisted extra data in private snapshot folder.
        $count = 0;
        foreach (new FilesystemIterator($job . '/files', FilesystemIterator::SKIP_DOTS) as $entry) {
            $name = $entry->getFilename();
            if (!preg_match('/^[a-f0-9]{64}\.bin$/D', $name)
                || $entry->isLink() || !$entry->isFile()) {
                throw new RuntimeException('FULL_SITE_EXTRA_COPY_INVALID');
            }
            $count++;
        }
        return $count === count($data['files']);
    }

    /** @return list<array{path:string,mode:int}> */
    private static function folders(string $site): array
    {
        $out = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink() || is_link($path)) {
                throw new RuntimeException('FULL_SITE_SYMLINK_OR_SPECIAL_FILE');
            }
            if (!$entry->isDir()) continue;
            $relative = substr($path, strlen($site) + 1);
            if ($relative === '' || str_contains($relative, "\0")) {
                throw new RuntimeException('FULL_SITE_PATH_INVALID');
            }
            clearstatcache(true, $path);
            $st = lstat($path);
            if (!is_array($st) || ($st['mode'] & 0170000) !== 0040000) {
                throw new RuntimeException('FULL_SITE_DIRECTORY_CHANGED');
            }
            $out[] = ['path' => $relative, 'mode' => $st['mode'] & 0777];
            if (count($out) > self::MAX_FILES) {
                throw new RuntimeException('FULL_SITE_DIRECTORY_COUNT_LIMIT');
            }
        }
        usort($out, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $out;
    }

    /** @return iterable<string,string> */
    private static function walk(string $site): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (is_link($path) || $entry->isLink() || !$entry->isFile()) {
                throw new RuntimeException('FULL_SITE_SYMLINK_OR_SPECIAL_FILE');
            }
            $relative = substr($path, strlen($site) + 1);
            if (!is_string($relative) || $relative === '' || str_contains($relative, "\0")) {
                throw new RuntimeException('FULL_SITE_PATH_INVALID');
            }
            yield $relative => $path;
        }
    }

    /** @return array<string,int> */
    private static function regular(string $path): array
    {
        clearstatcache(true, $path);
        if (is_link($path)) throw new RuntimeException('FULL_SITE_SYMLINK_OR_SPECIAL_FILE');
        $s = lstat($path);
        if (!is_array($s) || ($s['mode'] & 0170000) !== 0100000) {
            throw new RuntimeException('FULL_SITE_SYMLINK_OR_SPECIAL_FILE');
        }
        return $s;
    }

    private static function copyVerified(string $src, string $dest, array $before): string
    {
        $read = fopen($src, 'rb');
        if (!is_resource($read)) throw new RuntimeException('FULL_SITE_SOURCE_OPEN_FAILED');
        try {
            $opened = fstat($read);
            if (!is_array($opened) || $opened['ino'] !== $before['ino']
                || $opened['dev'] !== $before['dev']
                || $opened['size'] !== $before['size']) {
                throw new RuntimeException('FULL_SITE_SOURCE_CHANGED');
            }
            $write = fopen($dest, 'xb');
            if (!is_resource($write)) throw new RuntimeException('FULL_SITE_COPY_CREATE_FAILED');
            try {
                if (!chmod($dest, 0600)) throw new RuntimeException('FULL_SITE_COPY_MODE_FAILED');
                $hash = hash_init('sha256');
                $bytes = 0;
                while (!feof($read)) {
                    $chunk = fread($read, 65536);
                    if (!is_string($chunk) || ($chunk === '' && !feof($read))) {
                        throw new RuntimeException('FULL_SITE_READ_FAILED');
                    }
                    $len = strlen($chunk);
                    $bytes += $len;
                    if ($bytes > $before['size']) throw new RuntimeException('FULL_SITE_SOURCE_CHANGED');
                    hash_update($hash, $chunk);
                    for ($i = 0; $i < $len;) {
                        $n = fwrite($write, substr($chunk, $i));
                        if (!is_int($n) || $n < 1) throw new RuntimeException('FULL_SITE_COPY_WRITE_FAILED');
                        $i += $n;
                    }
                }
                if ($bytes !== $before['size'] || !fflush($write)
                    || (function_exists('fsync') && !fsync($write))) {
                    throw new RuntimeException('FULL_SITE_SOURCE_CHANGED');
                }
                $digest = hash_final($hash);
            } finally {
                fclose($write);
            }
            $after = self::regular($src);
            if ($after['ino'] !== $before['ino']
                || $after['dev'] !== $before['dev']
                || $after['size'] !== $before['size']
                || $after['mtime'] !== $before['mtime']
                || !hash_equals($digest, (string) hash_file('sha256', $src))) {
                throw new RuntimeException('FULL_SITE_SOURCE_CHANGED');
            }
            return $digest;
        } finally {
            fclose($read);
        }
    }

    private static function write(string $path, string $data): void
    {
        $h = fopen($path, 'xb');
        if (!is_resource($h)) throw new RuntimeException('FULL_SITE_RECEIPT_WRITE_FAILED');
        try {
            if (!chmod($path, 0600) || fwrite($h, $data) !== strlen($data)
                || !fflush($h) || (function_exists('fsync') && !fsync($h))) {
                throw new RuntimeException('FULL_SITE_RECEIPT_WRITE_FAILED');
            }
        } finally {
            fclose($h);
        }
    }

    private static function guard(string $site, string $vault): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('FULL_SITE_CLI_ONLY');
        $base = dirname($site);
        $fixture = preg_match(self::FIXTURE, $base) === 1
            && $site === $base . '/site'
            && $vault === $base . '/vault';
        $staging = $site === self::STAGING && $vault === self::STAGING_VAULT
            && function_exists('posix_geteuid') && posix_geteuid() === 0;
        if (!$fixture && !$staging) throw new RuntimeException('FULL_SITE_ROOT_DENIED');
        self::privateDir($site, false);
        if ($fixture) self::privateDir($base);
        self::privateDir($vault);
        if (str_starts_with($vault.'/', $site.'/')
            || str_starts_with($site.'/', $vault.'/')) {
            throw new RuntimeException('FULL_SITE_OVERLAPPING_ROOTS');
        }
    }

    private static function privateDir(string $path, bool $private = true): void
    {
        clearstatcache(true, $path);
        if (is_link($path) || !is_dir($path) || realpath($path) !== $path
            || ($private && (fileperms($path) & 0077) !== 0)) {
            throw new RuntimeException('FULL_SITE_DIRECTORY_UNTRUSTED');
        }
    }

    private function __construct()
    {
    }
}
