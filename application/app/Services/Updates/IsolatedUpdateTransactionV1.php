<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Throwable;

/**
 * Phase 3 fixture transaction only. This class rejects ALL actual website roots.
 * It exercises pinned code baselines, private receipts, a lock, per-file atomic
 * replacements, a journal, health hashes and recovery after injected failure.
 * No release acquisition, SQL, database restore or customer installation.
 */
final class IsolatedUpdateTransactionV1
{
    private const MAX_FILES = 128;
    private const MAX_BYTES = 16777216;

    /** @param array<string,string> $replacementBytes */
    public static function rehearse(
        string $siteRoot,
        string $vaultRoot,
        array $plan,
        array $replacementBytes,
        ?int $failAfter = null
    ): array {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('TRANSACTION_CLI_ONLY');
        }
        [$site, $vault] = self::fixtureRoots($siteRoot, $vaultRoot);
        if (!class_exists(VerifiedUpdateBackupV2::class)) {
            throw new RuntimeException('TRANSACTION_BACKUP_SERVICE_MISSING');
        }
        $items = self::targets($site, $plan, $replacementBytes);
        if ($failAfter !== null && ($failAfter < 1 || $failAfter > count($items))) {
            throw new RuntimeException('TRANSACTION_INJECTION_INVALID');
        }
        $lockPath = $vault . '/transaction.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('TRANSACTION_LOCK_UNTRUSTED');
        }
        $lock = @fopen($lockPath, 'cb');
        if ($lock === false) {
            throw new RuntimeException('TRANSACTION_LOCK_OPEN_FAILED');
        }
        try {
            @chmod($lockPath, 0600);
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('TRANSACTION_LOCK_BUSY');
            }
            // Recheck all baselines inside the lock, immediately before snapshot.
            $items = self::targets($site, $plan, $replacementBytes);
            $backup = VerifiedUpdateBackupV2::prepare($plan, $site, $vault);
            $job = $vault . '/' . $backup['backup_id'];
            $receiptSha = $backup['receipt_sha256'];
            if (!VerifiedUpdateBackupV2::verify($job, $receiptSha)) {
                throw new RuntimeException('TRANSACTION_BACKUP_UNVERIFIED');
            }
            $journal = $job . '/transaction.json';
            self::journal($journal, 'prepared', 0);
            $changed = 0;
            try {
                foreach ($items as $entry) {
                    // All paths and baselines must remain stable during operation.
                    self::assertBaseline($site, $entry);
                    self::replace($site, $entry, $replacementBytes[$entry['path']]);
                    $changed++;
                    self::journal($journal, 'replacing', $changed);
                    if ($failAfter === $changed) {
                        throw new RuntimeException('TRANSACTION_INJECTED_FAILURE');
                    }
                }
                foreach ($items as $entry) {
                    self::assertDigest($site, $entry['path'], $entry['archive_sha256']);
                }
                self::journal($journal, 'fixture_committed', $changed);
                return [
                    'status' => 'ISOLATED_FIXTURE_TRANSACTION_PASS',
                    'files_changed' => $changed,
                    'backup_receipt_sha256' => $receiptSha,
                    'database_writes' => false,
                    'production_writes' => false,
                    'automatic_installation_enabled' => false,
                ];
            } catch (Throwable $error) {
                try {
                    if (!VerifiedUpdateBackupV2::verify($job, $receiptSha)) {
                        throw new RuntimeException('TRANSACTION_BACKUP_UNVERIFIED');
                    }
                    // Do not clobber files changed by an unrelated actor.
                    foreach ($items as $entry) {
                        $path = $entry['path'];
                        $current = self::checkedPath($site, $path);
                        $actual = file_exists($current) ? hash_file('sha256', $current) : null;
                        if ($actual !== $entry['installed_sha256'] && $actual !== $entry['archive_sha256']) {
                            throw new RuntimeException('TRANSACTION_RECOVERY_CONFLICT');
                        }
                    }
                    foreach (array_reverse($items) as $entry) {
                        $path = $entry['path'];
                        $original = $entry['installed_sha256'];
                        $destination = self::checkedPath($site, $path);
                        if ($original === null) {
                            if (file_exists($destination) && !unlink($destination)) {
                                throw new RuntimeException('TRANSACTION_RECOVERY_DELETE_FAILED');
                            }
                        } elseif (!is_file($destination) || !hash_equals($original, (string) hash_file('sha256', $destination))) {
                            $copy = $job . '/files/' . hash('sha256', $path) . '.bin';
                            if (is_link($copy) || !is_file($copy) || !hash_equals($original, (string) hash_file('sha256', $copy))) {
                                throw new RuntimeException('TRANSACTION_RECOVERY_COPY_INVALID');
                            }
                            self::replace($site, $entry, (string) file_get_contents($copy), true);
                        }
                    }
                    foreach ($items as $entry) {
                        $dest = self::checkedPath($site, $entry['path']);
                        $sha = is_file($dest) ? hash_file('sha256', $dest) : null;
                        if ($sha !== $entry['installed_sha256']) {
                            throw new RuntimeException('TRANSACTION_RECOVERY_HASH_MISMATCH');
                        }
                    }
                    self::journal($journal, 'fixture_rolled_back', $changed);
                    throw new RuntimeException('TRANSACTION_FIXTURE_ROLLED_BACK:' . $error->getMessage(), 0, $error);
                } catch (RuntimeException $recovery) {
                    if (str_starts_with($recovery->getMessage(), 'TRANSACTION_FIXTURE_ROLLED_BACK:')) {
                        throw $recovery;
                    }
                    // Keep vault and receipt for forensic review; do not claim recovery.
                    self::journal($journal, 'recovery_review_required', $changed);
                    throw $recovery;
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function fixtureRoots(string $site, string $vault): array
    {
        if (is_link($site) || is_link($vault) || !is_dir($site) || !is_dir($vault)) {
            throw new RuntimeException('TRANSACTION_FIXTURE_REQUIRED');
        }
        $a = realpath($site);
        $b = realpath($vault);
        if (!is_string($a) || !is_string($b)
            || !preg_match('~^/tmp/barama-oneclick-txn-[a-f0-9]{12,32}/site$~D', $a)
            || $b !== dirname($a) . '/vault'
            || is_link(dirname($a))
            || (fileperms($a) & 0077) !== 0
            || (fileperms($b) & 0077) !== 0
            || (fileperms(dirname($a)) & 0077) !== 0) {
            throw new RuntimeException('TRANSACTION_FIXTURE_REQUIRED');
        }
        return [$a, $b];
    }

    private static function targets(string $site, array $plan, array $bytes): array
    {
        if (($plan['status'] ?? null) !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? true) !== false
            || !is_array($plan['targets'] ?? null)
            || $plan['targets'] === [] || count($plan['targets']) > self::MAX_FILES
            || count($plan['targets']) !== ($plan['files'] ?? null)
            || count($bytes) !== count($plan['targets'])) {
            throw new RuntimeException('TRANSACTION_PLAN_INVALID');
        }
        $seen = [];
        $total = 0;
        foreach ($plan['targets'] as $item) {
            if (!is_array($item) || !is_string($item['path'] ?? null)) {
                throw new RuntimeException('TRANSACTION_PLAN_INVALID');
            }
            $path = $item['path'];
            $fold = strtolower($path);
            if (isset($seen[$fold]) || !array_key_exists($path, $bytes) || !is_string($bytes[$path])
                || !array_key_exists('installed_sha256', $item)
                || ($item['installed_sha256'] !== null && (!is_string($item['installed_sha256'])
                    || preg_match('/^[a-f0-9]{64}$/D', $item['installed_sha256']) !== 1))
                || !is_string($item['archive_sha256'] ?? null)
                || !hash_equals($item['archive_sha256'], hash('sha256', $bytes[$path]))
                || ($item['bytes'] ?? null) !== strlen($bytes[$path])) {
                throw new RuntimeException('TRANSACTION_DIGEST_OR_PLAN_INVALID');
            }
            $seen[$fold] = true;
            $total += strlen($bytes[$path]);
            if ($total > self::MAX_BYTES) {
                throw new RuntimeException('TRANSACTION_SIZE_EXCEEDED');
            }
            self::assertBaseline($site, $item);
        }
        return $plan['targets'];
    }

    private static function assertBaseline(string $site, array $item): void
    {
        $path = self::checkedPath($site, $item['path']);
        $digest = is_file($path) ? hash_file('sha256', $path) : null;
        if ($digest !== $item['installed_sha256']) {
            throw new RuntimeException('TRANSACTION_BASELINE_CHANGED');
        }
    }

    private static function assertDigest(string $site, string $path, string $sha): void
    {
        $file = self::checkedPath($site, $path);
        if (!is_file($file) || !hash_equals($sha, (string) hash_file('sha256', $file))) {
            throw new RuntimeException('TRANSACTION_POSTCHECK_FAILED');
        }
    }

    private static function checkedPath(string $root, string $path): string
    {
        if (!is_string($path) || $path === '' || strlen($path) > 512
            || preg_match('~^[A-Za-z0-9_./-]+$~D', $path) !== 1
            || str_starts_with($path, '/') || str_contains($path, '//')) {
            throw new RuntimeException('TRANSACTION_PATH_DENIED');
        }
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
                throw new RuntimeException('TRANSACTION_PATH_DENIED');
            }
        }
        $valid = $path === 'VERSION' || in_array($path, ['index.php', 'updater/index.php', 'installer/index.php'], true)
            || str_starts_with($path, 'application/app/') || str_starts_with($path, 'application/routes/')
            || str_starts_with($path, 'application/resources/views/') || str_starts_with($path, 'assets/');
        if (!$valid || preg_match('~(^|/)(config|storage|vendor|uploads|private|node_modules|\.env|\.git)(/|$)~i', $path)
            || str_starts_with($path, 'application/database/migrations/')) {
            throw new RuntimeException('TRANSACTION_PATH_DENIED');
        }
        $cursor = $root;
        foreach ($parts as $part) {
            $cursor .= '/' . $part;
            if (is_link($cursor)) {
                throw new RuntimeException('TRANSACTION_SYMLINK_DENIED');
            }
        }
        if (!is_dir(dirname($cursor))) {
            throw new RuntimeException('TRANSACTION_PARENT_MISSING');
        }
        return $cursor;
    }

    private static function replace(string $site, array $entry, string $bytes, bool $recovery = false): void
    {
        $destination = self::checkedPath($site, $entry['path']);
        $targetSha = $recovery ? $entry['installed_sha256'] : $entry['archive_sha256'];
        if (!is_string($targetSha) || !hash_equals($targetSha, hash('sha256', $bytes))) {
            throw new RuntimeException('TRANSACTION_REPLACEMENT_INVALID');
        }
        $mode = file_exists($destination) ? (fileperms($destination) & 0777) : 0644;
        $tmp = dirname($destination) . '/.barama-txn-' . bin2hex(random_bytes(10));
        $handle = @fopen($tmp, 'xb');
        if ($handle === false) {
            throw new RuntimeException('TRANSACTION_TEMP_OPEN_FAILED');
        }
        try {
            @chmod($tmp, $mode & 0755);
            $done = 0;
            while ($done < strlen($bytes)) {
                $n = fwrite($handle, substr($bytes, $done, 65536));
                if (!is_int($n) || $n <= 0) {
                    throw new RuntimeException('TRANSACTION_TEMP_WRITE_FAILED');
                }
                $done += $n;
            }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new RuntimeException('TRANSACTION_TEMP_FLUSH_FAILED');
            }
        } finally {
            fclose($handle);
        }
        try {
            if (is_link($destination) || (file_exists($destination) && !is_file($destination))
                || !hash_equals($targetSha, (string) hash_file('sha256', $tmp))
                || !rename($tmp, $destination)) {
                throw new RuntimeException('TRANSACTION_ATOMIC_REPLACE_FAILED');
            }
        } finally {
            if (file_exists($tmp)) unlink($tmp);
        }
    }

    private static function journal(string $path, string $state, int $changed): void
    {
        if (is_link($path)) {
            throw new RuntimeException('TRANSACTION_JOURNAL_UNTRUSTED');
        }
        $record = json_encode(['schema'=>'barama-fixture-transaction-v1', 'state'=>$state,
            'changed_files'=>$changed, 'database_writes'=>false, 'live_installation'=>false], JSON_THROW_ON_ERROR) . "\n";
        $tmp = dirname($path) . '/.transaction-' . bin2hex(random_bytes(8));
        $fh = @fopen($tmp, 'xb');
        if ($fh === false) {
            throw new RuntimeException('TRANSACTION_JOURNAL_OPEN_FAILED');
        }
        try {
            @chmod($tmp, 0600);
            $written = fwrite($fh, $record);
            if ($written !== strlen($record) || !fflush($fh)
                || (function_exists('fsync') && !fsync($fh))) {
                throw new RuntimeException('TRANSACTION_JOURNAL_WRITE_FAILED');
            }
        } finally {
            fclose($fh);
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('TRANSACTION_JOURNAL_COMMIT_FAILED');
        }
    }
}
