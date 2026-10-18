<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Standalone file-only private backup from an already signed, read-only plan.
 * No update, install, deletion, SQL access, release download, or site mutation.
 * A validated plan is NOT authorization for an update. CLI worker only.
 */
final class VerifiedUpdateBackupV2
{
    private const MAX_FILES = 8000;
    private const MAX_FILE_BYTES = 2147483648;
    private const MAX_TOTAL_BYTES = 2147483648;

    public static function prepare(array $plan, string $siteRoot, string $vaultRoot): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('BACKUP_CLI_ONLY');
        }
        $site = self::directory($siteRoot, false);
        $vault = self::directory($vaultRoot, true);
        if ($vault === $site || str_starts_with($vault . '/', $site . '/')
            || str_starts_with($site . '/', $vault . '/')) {
            throw new RuntimeException('BACKUP_ROOT_OVERLAP');
        }
        self::validatePlan($plan);
        $items = [];
        $total = 0;
        foreach ($plan['targets'] as $item) {
            $path = $item['path'];
            $source = self::destination($site, $path);
            $sha = $item['installed_sha256'];
            $exists = file_exists($source);
            if (($sha === null) === $exists) {
                throw new RuntimeException('BACKUP_FILE_BASELINE_CHANGED');
            }
            if ($exists) {
                if (!is_file($source) || is_link($source)) {
                    throw new RuntimeException('BACKUP_FILE_NOT_REGULAR');
                }
                $length = filesize($source);
                if (!is_int($length) || $length < 0 || $length > self::MAX_FILE_BYTES) {
                    throw new RuntimeException('BACKUP_FILE_SIZE_INVALID');
                }
                $total += $length;
                if ($total > self::MAX_TOTAL_BYTES) {
                    throw new RuntimeException('BACKUP_TOTAL_SIZE_INVALID');
                }
                $found = hash_file('sha256', $source);
                if (!is_string($found) || !hash_equals($sha, $found)) {
                    throw new RuntimeException('BACKUP_FILE_BASELINE_CHANGED');
                }
            }
            $items[] = ['path' => $path, 'baseline' => $sha, 'exists' => $exists,
                'bytes' => $exists ? $length : 0, 'new_sha256' => $item['archive_sha256']];
        }
        $id = 'backup-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(8));
        $job = $vault . '/' . $id;
        if (!mkdir($job, 0700) || !mkdir($job . '/files', 0700)) {
            throw new RuntimeException('BACKUP_PRIVATE_CREATE_FAILED');
        }
        try {
            $entries = [];
            foreach ($items as $entry) {
                if (!$entry['exists']) continue;
                // Flat names avoid ever creating target-controlled subdirectories.
                $relative = hash('sha256', $entry['path']) . '.bin';
                $source = self::destination($site, $entry['path']);
                self::copyAndVerify($source, $job . '/files/' . $relative,
                    $entry['baseline'], $entry['bytes']);
                $entry['backup_file'] = $relative;
                $entries[] = $entry;
            }
            // Ensure additions remain absent after any slow file copying.
            foreach ($items as $entry) {
                if (!$entry['exists']) {
                    if (file_exists(self::destination($site, $entry['path']))) {
                        throw new RuntimeException('BACKUP_FILE_BASELINE_CHANGED');
                    }
                    $entries[] = $entry;
                }
            }
            // Originals must still match plan after the snapshot.
            foreach ($items as $entry) {
                if (!$entry['exists']) continue;
                $source = self::destination($site, $entry['path']);
                $now = is_file($source) && !is_link($source) ? hash_file('sha256', $source) : false;
                if (!is_string($now) || !hash_equals($entry['baseline'], $now)) {
                    throw new RuntimeException('BACKUP_FILE_CHANGED_DURING_COPY');
                }
            }
            usort($entries, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
            $receipt = [
                'schema' => 'barama-signed-update-code-backup-v1',
                'state' => 'prepared_not_installed',
                'release_archive_sha256' => $plan['archive_sha256'],
                'installed_version' => $plan['installed_version'],
                'target_version' => $plan['target_version'],
                'created_utc' => gmdate('c'),
                'file_count' => count($entries),
                'existing_file_count' => count(array_filter($items, static fn($x) => $x['exists'])),
                'total_existing_bytes' => $total,
                'database_backup' => 'NOT_INCLUDED',
                'installation_enabled' => false,
                'files' => $entries,
            ];
            $json = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
            self::exclusiveWrite($job . '/receipt.json', $json);
            $receiptSha = hash_file('sha256', $job . '/receipt.json');
            if (!is_string($receiptSha) || !self::verify($job, $receiptSha)) {
                throw new RuntimeException('BACKUP_RECEIPT_REVERIFY_FAILED');
            }
            return [
                'backup_id' => $id,
                'receipt_sha256' => $receiptSha,
                'backed_up_files' => $receipt['existing_file_count'],
                'bytes' => $total,
                'database_backup' => 'NOT_INCLUDED',
                'installation_enabled' => false,
                'status' => 'PRIVATE_FILE_BACKUP_VERIFIED',
            ];
        } catch (\Throwable $exception) {
            // Preserve partial backup for private operator inspection; no live mutation.
            throw $exception;
        }
    }

    public static function verify(string $jobDirectory, string $expectedReceiptSha): bool
    {
        if (PHP_SAPI !== 'cli' || !self::sha($expectedReceiptSha)) {
            throw new RuntimeException('BACKUP_VERIFY_INPUT_INVALID');
        }
        $job = self::directory($jobDirectory, true);
        $receiptPath = $job . '/receipt.json';
        if (is_link($receiptPath) || !is_file($receiptPath)
            || (fileperms($receiptPath) & 0077) !== 0
            || !hash_equals($expectedReceiptSha, (string) hash_file('sha256', $receiptPath))) {
            throw new RuntimeException('BACKUP_RECEIPT_TAMPERED');
        }
        $json = file_get_contents($receiptPath);
        $data = is_string($json) ? json_decode($json, true, 12, JSON_THROW_ON_ERROR) : null;
        if (!is_array($data) || ($data['schema'] ?? null) !== 'barama-signed-update-code-backup-v1'
            || ($data['state'] ?? null) !== 'prepared_not_installed'
            || ($data['database_backup'] ?? null) !== 'NOT_INCLUDED'
            || ($data['installation_enabled'] ?? null) !== false
            || !self::sha($data['release_archive_sha256'] ?? null)
            || !is_array($data['files'] ?? null)
            || count($data['files']) !== ($data['file_count'] ?? null)) {
            throw new RuntimeException('BACKUP_RECEIPT_INVALID');
        }
        $filesRoot = self::directory($job . '/files', true);
        $count = 0;
        $bytes = 0;
        $names = [];
        foreach ($data['files'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
                throw new RuntimeException('BACKUP_ENTRY_INVALID');
            }
            self::allowedPath($entry['path']);
            if (isset($names[strtolower($entry['path'])])) {
                throw new RuntimeException('BACKUP_ENTRY_DUPLICATE');
            }
            $names[strtolower($entry['path'])] = true;
            $exists = $entry['exists'] ?? null;
            if (!is_bool($exists) || !self::sha($entry['new_sha256'] ?? null)) {
                throw new RuntimeException('BACKUP_ENTRY_INVALID');
            }
            if (!$exists) {
                if (!array_key_exists('baseline', $entry) || $entry['baseline'] !== null || ($entry['bytes'] ?? -1) !== 0
                    || array_key_exists('backup_file', $entry)) {
                    throw new RuntimeException('BACKUP_ENTRY_INVALID');
                }
                continue;
            }
            if (!self::sha($entry['baseline'] ?? null)
                || !is_int($entry['bytes'] ?? null) || $entry['bytes'] < 0
                || ($entry['backup_file'] ?? '') !== hash('sha256', $entry['path']) . '.bin') {
                throw new RuntimeException('BACKUP_ENTRY_INVALID');
            }
            $copy = $filesRoot . '/' . $entry['backup_file'];
            if (is_link($copy) || !is_file($copy)
                || (fileperms($copy) & 0077) !== 0
                || filesize($copy) !== $entry['bytes']
                || !hash_equals($entry['baseline'], (string) hash_file('sha256', $copy))) {
                throw new RuntimeException('BACKUP_COPY_TAMPERED');
            }
            $bytes += $entry['bytes'];
            ++$count;
        }
        if ($count !== ($data['existing_file_count'] ?? null)
            || $bytes !== ($data['total_existing_bytes'] ?? null)) {
            throw new RuntimeException('BACKUP_TOTALS_INVALID');
        }
        return true;
    }

    private static function validatePlan(array $plan): void
    {
        if (($plan['status'] ?? '') !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? true) !== false
            || !self::sha($plan['archive_sha256'] ?? null)
            || !is_string($plan['installed_version'] ?? null)
            || !is_string($plan['target_version'] ?? null)
            || version_compare($plan['target_version'], $plan['installed_version'], '<=')
            || !is_array($plan['targets'] ?? null) || $plan['targets'] === []
            || count($plan['targets']) > self::MAX_FILES
            || count($plan['targets']) !== ($plan['files'] ?? null)) {
            throw new RuntimeException('BACKUP_PLAN_INVALID');
        }
        $seen = [];
        foreach ($plan['targets'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)) {
                throw new RuntimeException('BACKUP_PLAN_INVALID');
            }
            self::allowedPath($entry['path']);
            $fold = strtolower($entry['path']);
            if (isset($seen[$fold]) || !self::sha($entry['archive_sha256'] ?? null)
                || !array_key_exists('installed_sha256', $entry)
                || ($entry['installed_sha256'] !== null && !self::sha($entry['installed_sha256']))
                || !is_int($entry['bytes'] ?? null) || $entry['bytes'] < 0) {
                throw new RuntimeException('BACKUP_PLAN_INVALID');
            }
            $seen[$fold] = true;
        }
    }

    private static function allowedPath(string $path): void
    {
        if ($path === '' || strlen($path) > 512
            || preg_match('~^[A-Za-z0-9_./-]+$~D', $path) !== 1
            || str_starts_with($path, '/') || str_contains($path, '//')) {
            throw new RuntimeException('BACKUP_PATH_DENIED');
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
                throw new RuntimeException('BACKUP_PATH_DENIED');
            }
        }
        $allowed = $path === 'VERSION'
            || in_array($path, ['index.php', 'updater/index.php', 'installer/index.php'], true)
            || str_starts_with($path, 'application/app/')
            || str_starts_with($path, 'application/routes/')
            || str_starts_with($path, 'application/resources/views/')
            || str_starts_with($path, 'assets/');
        if (!$allowed || preg_match('~(^|/)(storage|config|vendor|node_modules|uploads|private|\.git|\.env)(/|$)~i', $path)
            || preg_match('~\.(?:pem|key|p12|pfx|sql|sqlite|bak|log)$~i', $path)) {
            throw new RuntimeException('BACKUP_PATH_DENIED');
        }
    }

    private static function destination(string $root, string $path): string
    {
        self::allowedPath($path);
        $cursor = $root;
        foreach (explode('/', $path) as $piece) {
            $cursor .= '/' . $piece;
            if (is_link($cursor)) {
                throw new RuntimeException('BACKUP_TARGET_SYMLINK');
            }
        }
        return $cursor;
    }

    private static function directory(string $path, bool $private): string
    {
        if (is_link($path) || !is_dir($path)) {
            throw new RuntimeException('BACKUP_DIR_INVALID');
        }
        $real = realpath($path);
        if (!is_string($real) || $real === '/' || ($private && (fileperms($real) & 0077) !== 0)) {
            throw new RuntimeException('BACKUP_DIR_UNSAFE');
        }
        return $real;
    }

    private static function sha(mixed $sha): bool
    {
        return is_string($sha) && preg_match('/^[a-f0-9]{64}$/D', $sha) === 1;
    }

    private static function copyAndVerify(string $src, string $dest, string $expectedSha, int $expectedBytes): void
    {
        if (is_link($src)) throw new RuntimeException('BACKUP_TARGET_SYMLINK');
        $in = fopen($src, 'rb');
        $out = fopen($dest, 'xb');
        if ($in === false || $out === false) throw new RuntimeException('BACKUP_IO_FAILURE');
        chmod($dest, 0600);
        try {
            $stat = fstat($in);
            $diskStat = lstat($src);
            if (!is_array($stat) || !is_array($diskStat)
                || ($stat['mode'] & 0170000) !== 0100000
                || $stat['ino'] !== $diskStat['ino']
                || $stat['size'] !== $expectedBytes) {
                throw new RuntimeException('BACKUP_INPUT_CHANGED');
            }
            $digest = hash_init('sha256');
            $copied = 0;
            while (!feof($in)) {
                $block = fread($in, 65536);
                if (!is_string($block) || ($block === '' && !feof($in))) {
                    throw new RuntimeException('BACKUP_READ_FAILED');
                }
                $length = strlen($block);
                if ($copied + $length > $expectedBytes
                    || ($length !== 0 && fwrite($out, $block) !== $length)) {
                    throw new RuntimeException('BACKUP_WRITE_FAILED');
                }
                hash_update($digest, $block);
                $copied += $length;
            }
            if (!fflush($out) || $copied !== $expectedBytes
                || !hash_equals($expectedSha, hash_final($digest))) {
                throw new RuntimeException('BACKUP_COPY_MISMATCH');
            }
            if (function_exists('fsync')) fsync($out);
        } finally {
            fclose($in);
            fclose($out);
        }
        if (!hash_equals($expectedSha, (string) hash_file('sha256', $dest))) {
            throw new RuntimeException('BACKUP_COPY_TAMPERED');
        }
    }

    private static function exclusiveWrite(string $path, string $contents): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) throw new RuntimeException('BACKUP_RECEIPT_EXISTS');
        chmod($path, 0600);
        try {
            if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
                throw new RuntimeException('BACKUP_RECEIPT_WRITE_FAILED');
            }
            if (function_exists('fsync')) fsync($handle);
        } finally {
            fclose($handle);
        }
    }
}
