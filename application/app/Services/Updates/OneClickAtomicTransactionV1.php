<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Actual atomic file application and crash recovery on DISPOSABLE CLONES ONLY.
 * The signed plan must already have been authenticated by a separate verifier.
 * Deliberately refuses customer/staging/prod roots, SQL, and privileged web work.
 */
final class OneClickAtomicTransactionV1
{
    private const MAX_FILES = 128;
    private const MAX_BYTES = 16777216;
    private const SHA = '/^[a-f0-9]{64}$/D';
    private const FIXTURE = '~^/tmp/barama-oneclick-transaction-fixture-[a-f0-9]{12,32}$~D';

    /** @param array<string,mixed> $plan @param array<string,string> $contents */
    public static function apply(string $site, string $vault, array $plan, array $contents, ?int $crashAfter = null): array
    {
        [$site, $vault] = self::roots($site, $vault);
        $lock = self::lock($vault);
        try {
            // Refuse a second update until an interrupted transaction is recovered.
            self::noPending($vault);
            // Revalidate every baseline INSIDE the exclusive lock.
            $entries = self::plan($site, $plan, $contents);
            if ($crashAfter !== null && ($crashAfter < 1 || $crashAfter > count($entries))) {
                throw new RuntimeException('FIXTURE_CRASH_BOUND_INVALID');
            }
            $id = 'tx-' . bin2hex(random_bytes(12));
            $job = $vault . '/' . $id;
            if (!mkdir($job, 0700) || !mkdir($job . '/files', 0700)) {
                throw new RuntimeException('TX_PRIVATE_JOB_CREATE_FAILED');
            }
            foreach ($entries as $entry) {
                if ($entry['old_sha256'] !== null) {
                    $source = self::destination($site, $entry['path']);
                    self::save($job . '/files/' . hash('sha256', $entry['path']) . '.bin',
                        (string) file_get_contents($source), false);
                }
            }
            $journal = [
                'schema' => 'barama-oneclick-transaction-fixture-v1',
                'status' => 'prepared',
                'archive_sha256' => $plan['archive_sha256'],
                'installed_version' => $plan['installed_version'],
                'target_version' => $plan['target_version'],
                'database_writes' => false,
                'worker_authorized_for_live_site' => false,
                'changed_count' => 0,
                'entries' => $entries,
            ];
            self::journal($job, $journal);
            foreach ($entries as $i => $entry) {
                $path = self::destination($site, $entry['path']);
                self::assertCurrent($path, $entry['old_sha256']);
                $journal['status'] = 'applying';
                $journal['pending_index'] = $i;
                self::journal($job, $journal);  // write-ahead before replacement
                self::save($path, $contents[$entry['path']], true);
                self::assertCurrent($path, $entry['new_sha256']);
                $journal['changed_count'] = $i + 1;
                unset($journal['pending_index']);
                self::journal($job, $journal);
                if ($crashAfter === $i + 1) {
                    // Simulate a lost process: preserve journal & backups so the
                    // NEXT invocation must recover. No implicit live rollback.
                    throw new RuntimeException('FIXTURE_INJECTED_PROCESS_CRASH:' . $id);
                }
            }
            $journal['status'] = 'files_committed_fixture_only';
            self::journal($job, $journal);
            return [
                'status' => 'ISOLATED_FILE_INSTALL_COMMITTED',
                'transaction_id' => $id,
                'files_replaced' => count($entries),
                'database_writes' => false,
                'live_installation' => false,
            ];
        } finally {
            self::unlock($lock);
        }
    }

    public static function recover(string $site, string $vault, string $id): array
    {
        [$site, $vault] = self::roots($site, $vault);
        if (!preg_match('/^tx-[a-f0-9]{24}$/D', $id)) {
            throw new RuntimeException('RECOVERY_TRANSACTION_ID_INVALID');
        }
        $lock = self::lock($vault);
        try {
            $job = $vault . '/' . $id;
            $file = $job . '/journal.json';
            if (is_link($job) || !is_dir($job) || realpath($job) !== $job || is_link($file)
                || !is_file($file) || (fileperms($file) & 0077) !== 0 || filesize($file) > 1048576) {
                throw new RuntimeException('RECOVERY_JOURNAL_UNTRUSTED');
            }
            try {
                $journal = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw new RuntimeException('RECOVERY_JOURNAL_UNTRUSTED');
            }
            if (!is_array($journal) || ($journal['schema'] ?? null) !== 'barama-oneclick-transaction-fixture-v1'
                || ($journal['database_writes'] ?? null) !== false
                || ($journal['worker_authorized_for_live_site'] ?? null) !== false
                || !in_array($journal['status'] ?? null, ['prepared', 'applying', 'recovery_required'], true)
                || !is_array($journal['entries'] ?? null)
                || count($journal['entries']) < 1 || count($journal['entries']) > self::MAX_FILES) {
                throw new RuntimeException('RECOVERY_NOT_FILE_ONLY_OR_NOT_PENDING');
            }
            $entries = $journal['entries'];
            $seen = [];
            // Preflight ALL files and source backups before changing ANYTHING.
            foreach ($entries as $entry) {
                if (!self::validEntry($entry) || isset($seen[strtolower($entry['path'])])) {
                    throw new RuntimeException('RECOVERY_ENTRY_UNTRUSTED');
                }
                $seen[strtolower($entry['path'])] = true;
                $destination = self::destination($site, $entry['path']);
                $current = self::actualSha($destination);
                if ($current !== $entry['old_sha256'] && $current !== $entry['new_sha256']) {
                    throw new RuntimeException('RECOVERY_UNEXPECTED_FILE_MODIFICATION');
                }
                if ($entry['old_sha256'] !== null) {
                    $backup = $job . '/files/' . hash('sha256', $entry['path']) . '.bin';
                    if (is_link($backup) || !is_file($backup)
                        || (fileperms($backup) & 0077) !== 0
                        || self::actualSha($backup) !== $entry['old_sha256']) {
                        throw new RuntimeException('RECOVERY_BACKUP_TAMPERED');
                    }
                }
            }
            $journal['status'] = 'recovery_required';
            self::journal($job, $journal);
            // Reverse for nested code paths. All destinations already validated.
            foreach (array_reverse($entries) as $entry) {
                $dest = self::destination($site, $entry['path']);
                if ($entry['old_sha256'] === null) {
                    if (self::actualSha($dest) === $entry['new_sha256']) {
                        if (!unlink($dest)) throw new RuntimeException('RECOVERY_REMOVE_FAILED');
                    }
                } else {
                    $data = file_get_contents($job . '/files/' . hash('sha256', $entry['path']) . '.bin');
                    if (!is_string($data) || hash('sha256', $data) !== $entry['old_sha256']) {
                        throw new RuntimeException('RECOVERY_BACKUP_CHANGED');
                    }
                    self::save($dest, $data, true);
                }
                self::assertCurrent($dest, $entry['old_sha256']);
            }
            $journal['status'] = 'recovered_fixture_only';
            $journal['changed_count'] = 0;
            unset($journal['pending_index']);
            self::journal($job, $journal);
            return [
                'status' => 'ISOLATED_FILE_CRASH_RECOVERED',
                'transaction_id' => $id,
                'files_restored' => count($entries),
                'database_writes' => false,
                'live_installation' => false,
            ];
        } finally {
            self::unlock($lock);
        }
    }

    /** Prevent an interrupted transaction from being bypassed by a new update. */
    private static function noPending(string $vault): void
    {
        foreach (new \DirectoryIterator($vault) as $entry) {
            if ($entry->isDot()) continue;
            $name = $entry->getFilename();
            if (!preg_match('/^tx-[a-f0-9]{24}$/D', $name)) continue;
            if ($entry->isLink() || !$entry->isDir()) {
                throw new RuntimeException('FIXTURE_PENDING_JOB_UNTRUSTED');
            }
            $path = $entry->getPathname() . '/journal.json';
            if (is_link($path) || !is_file($path) || filesize($path) > 1048576) {
                throw new RuntimeException('FIXTURE_PENDING_JOURNAL_UNTRUSTED');
            }
            try {
                $record = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw new RuntimeException('FIXTURE_PENDING_JOURNAL_UNTRUSTED');
            }
            if (!is_array($record) || !in_array($record['status'] ?? null,
                    ['files_committed_fixture_only', 'recovered_fixture_only'], true)) {
                throw new RuntimeException('FIXTURE_INTERRUPTED_UPDATE_REQUIRES_RECOVERY');
            }
        }
    }

    /** @return array{string,string} */
    private static function roots(string $site, string $vault): array
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('FIXTURE_CLI_ONLY');
        $base = dirname($site);
        if (!preg_match(self::FIXTURE, $base) || $site !== $base . '/site'
            || $vault !== $base . '/vault' || is_link($base) || is_link($site) || is_link($vault)
            || !is_dir($site) || !is_dir($vault) || realpath($base) !== $base
            || realpath($site) !== $site || realpath($vault) !== $vault
            || (fileperms($vault) & 0077) !== 0) {
            throw new RuntimeException('FIXTURE_ROOT_DENIED');
        }
        return [$site, $vault];
    }

    /** @param array<string,mixed> $plan @param array<string,string> $contents */
    private static function plan(string $site, array $plan, array $contents): array
    {
        if (($plan['status'] ?? null) !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? null) !== false
            || !self::sha($plan['archive_sha256'] ?? null)
            || !is_string($plan['installed_version'] ?? null)
            || !is_string($plan['target_version'] ?? null)
            || version_compare($plan['target_version'], $plan['installed_version'], '<=')
            || !is_array($plan['targets'] ?? null)
            || count($plan['targets']) < 2 || count($plan['targets']) > self::MAX_FILES
            || count($plan['targets']) !== ($plan['files'] ?? null)
            || count($contents) !== count($plan['targets'])) {
            throw new RuntimeException('FIXTURE_SIGNED_PLAN_REQUIRED');
        }
        $result = [];
        $seen = [];
        $total = 0;
        $versionSeen = false;
        foreach ($plan['targets'] as $item) {
            if (!is_array($item) || !self::validEntry([
                'path' => $item['path'] ?? null,
                'old_sha256' => $item['installed_sha256'] ?? null,
                'new_sha256' => $item['archive_sha256'] ?? null,
            ])) throw new RuntimeException('FIXTURE_PLAN_ENTRY_INVALID');
            $path = $item['path'];
            if (isset($seen[strtolower($path)]) || !array_key_exists($path, $contents)) {
                throw new RuntimeException('FIXTURE_PLAN_ENTRY_DUPLICATE');
            }
            $seen[strtolower($path)] = true;
            $data = $contents[$path];
            if (!is_string($data) || hash('sha256', $data) !== $item['archive_sha256']) {
                throw new RuntimeException('FIXTURE_ARCHIVE_TARGET_MISMATCH');
            }
            if ($path === 'VERSION') {
                $versionSeen = true;
                if (trim($data) !== $plan['target_version']) {
                    throw new RuntimeException('FIXTURE_TARGET_VERSION_MISMATCH');
                }
            }
            $total += strlen($data);
            if ($total > self::MAX_BYTES) throw new RuntimeException('FIXTURE_TOTAL_SIZE_EXCEEDED');
            $dest = self::destination($site, $path);
            self::assertCurrent($dest, $item['installed_sha256'] ?? null);
            $result[] = [
                'path' => $path,
                'old_sha256' => $item['installed_sha256'],
                'new_sha256' => $item['archive_sha256'],
            ];
        }
        if (!$versionSeen || count($result) !== count($seen)) {
            throw new RuntimeException('FIXTURE_VERSION_TARGET_REQUIRED');
        }
        return $result;
    }

    private static function validEntry(mixed $item): bool
    {
        if (!is_array($item) || !is_string($item['path'] ?? null)
            || !preg_match('~^[A-Za-z0-9_/-]+(?:\.[A-Za-z0-9_-]+)?$~D', $item['path'])
            || strlen($item['path']) > 512 || str_contains($item['path'], '//')
            || !self::sha($item['new_sha256'] ?? null)
            || (!is_null($item['old_sha256'] ?? null) && !self::sha($item['old_sha256']))) return false;
        $parts = explode('/', $item['path']);
        foreach ($parts as $p) {
            if ($p === '' || $p === '.' || $p === '..' || str_starts_with($p, '.')) return false;
        }
        return $item['path'] === 'VERSION'
            || str_starts_with($item['path'], 'assets/')
            || str_starts_with($item['path'], 'application/app/')
            || str_starts_with($item['path'], 'application/resources/views/');
    }

    private static function destination(string $root, string $path): string
    {
        $cursor = $root;
        foreach (explode('/', $path) as $part) {
            $cursor .= '/' . $part;
            if (is_link($cursor)) throw new RuntimeException('FIXTURE_SYMLINK_DENIED');
            if ($cursor !== $root . '/' . $path && file_exists($cursor) && !is_dir($cursor)) {
                throw new RuntimeException('FIXTURE_PARENT_NOT_DIRECTORY');
            }
        }
        return $cursor;
    }

    private static function actualSha(string $path): ?string
    {
        if (is_link($path)) throw new RuntimeException('FIXTURE_SYMLINK_DENIED');
        if (!file_exists($path)) return null;
        if (!is_file($path)) throw new RuntimeException('FIXTURE_NON_FILE_TARGET');
        $digest = hash_file('sha256', $path);
        if (!is_string($digest)) throw new RuntimeException('FIXTURE_UNREADABLE_TARGET');
        return $digest;
    }

    private static function assertCurrent(string $path, ?string $expected): void
    {
        if (self::actualSha($path) !== $expected) {
            throw new RuntimeException('FIXTURE_FILE_BASELINE_CHANGED');
        }
    }

    private static function sha(mixed $s): bool
    {
        return is_string($s) && preg_match(self::SHA, $s) === 1;
    }

    private static function lock(string $vault)
    {
        $path = $vault . '/transaction.lock';
        if (is_link($path)) throw new RuntimeException('FIXTURE_LOCK_SYMLINK');
        $h = fopen($path, 'c+b');
        if (!is_resource($h) || !flock($h, LOCK_EX | LOCK_NB)) {
            if (is_resource($h)) fclose($h);
            throw new RuntimeException('FIXTURE_UPDATE_ALREADY_RUNNING');
        }
        if (!chmod($path, 0600)) throw new RuntimeException('FIXTURE_LOCK_PERMISSIONS');
        return $h;
    }

    private static function unlock($h): void
    {
        flock($h, LOCK_UN);
        fclose($h);
    }

    private static function journal(string $job, array $value): void
    {
        self::save($job . '/journal.json', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n", true);
    }

    private static function save(string $path, string $bytes, bool $replace): void
    {
        if (is_link($path) || (!$replace && file_exists($path))) {
            throw new RuntimeException('FIXTURE_OUTPUT_UNTRUSTED');
        }
        $tmp = dirname($path) . '/.oneclick-' . bin2hex(random_bytes(8));
        $h = fopen($tmp, 'xb');
        if (!is_resource($h)) throw new RuntimeException('FIXTURE_WRITE_FAILED');
        try {
            if (!chmod($tmp, 0600)) throw new RuntimeException('FIXTURE_CHMOD_FAILED');
            $len = strlen($bytes);
            $offset = 0;
            while ($offset < $len) {
                $n = fwrite($h, substr($bytes, $offset, 65536));
                if (!is_int($n) || $n <= 0) throw new RuntimeException('FIXTURE_WRITE_FAILED');
                $offset += $n;
            }
            if (!fflush($h) || (function_exists('fsync') && !fsync($h))) {
                throw new RuntimeException('FIXTURE_FSYNC_FAILED');
            }
        } finally {
            fclose($h);
        }
        if (is_link($path) || (!$replace && file_exists($path)) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('FIXTURE_ATOMIC_RENAME_FAILED');
        }
    }
}
