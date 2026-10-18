<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Throwable;

/**
 * Staging-only, root-private, CLI logical database backup prerequisite.
 * Does not change a database, restore SQL, replace code, or authorize an update.
 * No customer web entry point. For a future reviewed CLI worker only.
 */
final class PrivateDatabaseBackupV2
{
    private const MAX_SQL_BYTES = 2147483648;
    private const MAX_SECONDS = 300;
    private const STAGING_ROOT = '/www/wwwroot/baramastaging.tonsamith.com';
    private const DUMP_BINARIES = [
        '/usr/bin/mysqldump', '/usr/bin/mariadb-dump',
        '/www/server/mysql/bin/mysqldump', '/www/server/mysql/bin/mariadb-dump',
    ];

    /**
     * Prepare a private backup. A fixture callback is permitted only on an
     * isolated /tmp test root and produces a prominently marked FIXTURE receipt.
     * Production backups require a permitted local mysqldump executable.
     *
     * @param array{site_root:string,vault_root:string,database:string,host?:string,port?:int,user?:string,password?:string,dump_binary?:string} $config
     * @param ?callable(callable(string):void):void $fixtureExporter
     * @return array<string,mixed>  Never includes password or SQL contents.
     */
    public static function prepare(array $config, ?callable $fixtureExporter = null): array
    {
        self::cli();
        $fixture = $fixtureExporter !== null;
        $site = self::directory(self::required($config, 'site_root'), false);
        $vault = self::directory(self::required($config, 'vault_root'), true);
        if ($vault === $site || str_starts_with($vault.'/', $site.'/')
            || str_starts_with($site.'/', $vault.'/')) {
            throw new RuntimeException('DB_BACKUP_OVERLAPPING_ROOTS');
        }
        if ($fixture) {
            if (!str_starts_with($site.'/', sys_get_temp_dir().'/barama-db-safety-fixture-')) {
                throw new RuntimeException('DB_BACKUP_FIXTURE_ROOT_DENIED');
            }
        } elseif ($site !== self::STAGING_ROOT || !str_starts_with($vault.'/', '/root/')) {
            throw new RuntimeException('DB_BACKUP_STAGING_ONLY');
        }
        $db = self::required($config, 'database');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $db) !== 1) {
            throw new RuntimeException('DB_BACKUP_NAME_INVALID');
        }
        // Production export credentials never appear in the report or arguments.
        $binary = '';
        if (!$fixture) {
            $binary = self::required($config, 'dump_binary');
            if (!in_array($binary, self::DUMP_BINARIES, true)
                || is_link($binary) || !is_file($binary) || !is_executable($binary)) {
                throw new RuntimeException('DB_BACKUP_DUMP_BINARY_UNTRUSTED');
            }
            if (!is_string(realpath($binary)) || realpath($binary) !== $binary) {
                throw new RuntimeException('DB_BACKUP_DUMP_BINARY_UNTRUSTED');
            }
            $binStat = stat($binary);
            if (!is_array($binStat) || ($binStat['mode'] & 0022) !== 0) {
                throw new RuntimeException('DB_BACKUP_DUMP_BINARY_UNTRUSTED');
            }
            $user = self::required($config, 'user');
            $pass = $config['password'] ?? null;
            $host = self::required($config, 'host');
            $port = $config['port'] ?? null;
            if (!is_string($pass) || $pass === '' || strlen($pass) > 2048
                || preg_match('/[\x00-\x1f\x7f]/', $pass)
                || preg_match('/^[A-Za-z0-9_.@-]{1,80}$/D', $user) !== 1
                || !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
                || !is_int($port) || $port < 1 || $port > 65535) {
                throw new RuntimeException('DB_BACKUP_CONNECTION_UNSAFE');
            }
        }
        $id = 'db-' . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(8));
        $job = $vault.'/'.$id;
        if (!@mkdir($job, 0700)) {
            throw new RuntimeException('DB_BACKUP_PRIVATE_DIR_FAILED');
        }
        $dump = $job.'/database.sql';
        $configPath = $job.'/.client.cnf';
        $stream = null;
        $complete = false;
        try {
            $stream = @fopen($dump, 'xb');
            if ($stream === false || !chmod($dump, 0600)) {
                throw new RuntimeException('DB_BACKUP_OUTPUT_UNAVAILABLE');
            }
            $total = 0;
            $hash = hash_init('sha256');
            $sink = static function (string $part) use ($stream, &$total, $hash): void {
                $bytes = strlen($part);
                if ($total + $bytes > self::MAX_SQL_BYTES) {
                    throw new RuntimeException('DB_BACKUP_TOO_LARGE');
                }
                $offset = 0;
                while ($offset < $bytes) {
                    $written = fwrite($stream, substr($part, $offset));
                    if (!is_int($written) || $written < 1) {
                        throw new RuntimeException('DB_BACKUP_WRITE_FAILED');
                    }
                    $offset += $written;
                }
                $total += $bytes;
                hash_update($hash, $part);
            };
            if ($fixture) {
                $fixtureExporter($sink);
            } else {
                self::createClientConfig($configPath, $host, $port, $user, $pass);
                self::exportWithMysqlDump($binary, $configPath, $db, $sink);
            }
            if ($total === 0 || !fflush($stream)) {
                throw new RuntimeException('DB_BACKUP_EMPTY_OR_INCOMPLETE');
            }
            if (function_exists('fsync') && !fsync($stream)) {
                throw new RuntimeException('DB_BACKUP_SYNC_FAILED');
            }
            fclose($stream);
            $stream = null;
            $expected = hash_final($hash);
            if (filesize($dump) !== $total
                || !hash_equals($expected, (string) hash_file('sha256', $dump))) {
                throw new RuntimeException('DB_BACKUP_DIGEST_MISMATCH');
            }
            // Never persist a .client.cnf credential file in the backup folder.
            if (is_file($configPath) && !unlink($configPath)) {
                throw new RuntimeException('DB_BACKUP_CREDENTIAL_CLEANUP_FAILED');
            }
            $receipt = [
                'schema' => 'barama-private-db-backup-v2',
                'state' => $fixture ? 'fixture_only' : 'backup_not_restored',
                'database_name_sha256' => hash('sha256', $db),
                'dump_sha256' => $expected,
                'dump_bytes' => $total,
                'created_utc' => gmdate('c'),
                'file' => 'database.sql',
                'database_writes' => false,
                'restore_tested' => false,
                'installation_enabled' => false,
            ];
            $json = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
            self::exclusiveWrite($job.'/receipt.json', $json);
            $receiptSha = hash_file('sha256', $job.'/receipt.json');
            if (!is_string($receiptSha) || !self::verify($job, $receiptSha)) {
                throw new RuntimeException('DB_BACKUP_RECEIPT_VERIFY_FAILED');
            }
            $complete = true;
            return [
                'status' => $fixture ? 'DISPOSABLE_DATABASE_FIXTURE_VERIFIED' : 'PRIVATE_DATABASE_BACKUP_VERIFIED_NOT_RESTORED',
                'job_id' => $id,
                'receipt_sha256' => $receiptSha,
                'dump_sha256' => $expected,
                'bytes' => $total,
                'restore_tested' => false,
                'installation_enabled' => false,
            ];
        } finally {
            if (is_resource($stream)) fclose($stream);
            if (is_file($configPath) || is_link($configPath)) @unlink($configPath);
            if (!$complete) {
                // Interrupted/failed exports are never evidence of a usable backup.
                foreach ([$job.'/receipt.json', $dump] as $partial) {
                    if (is_file($partial) || is_link($partial)) @unlink($partial);
                }
                @rmdir($job);
            }
        }
    }

    public static function verify(string $jobDir, string $receiptSha): bool
    {
        self::cli();
        if (!self::sha($receiptSha)) throw new RuntimeException('DB_BACKUP_RECEIPT_HASH_INVALID');
        $job = self::directory($jobDir, true);
        $receipt = $job.'/receipt.json';
        $sql = $job.'/database.sql';
        foreach ([$receipt, $sql] as $file) {
            if (is_link($file) || !is_file($file) || !is_readable($file)
                || (fileperms($file) & 0077) !== 0) {
                throw new RuntimeException('DB_BACKUP_FILE_UNSAFE');
            }
        }
        if (!hash_equals($receiptSha, (string) hash_file('sha256', $receipt))) {
            throw new RuntimeException('DB_BACKUP_RECEIPT_TAMPERED');
        }
        $raw = file_get_contents($receipt);
        try {
            $record = is_string($raw) ? json_decode($raw, true, 10, JSON_THROW_ON_ERROR) : null;
        } catch (Throwable) {
            throw new RuntimeException('DB_BACKUP_RECEIPT_INVALID');
        }
        if (!is_array($record) || ($record['schema'] ?? null) !== 'barama-private-db-backup-v2'
            || !in_array($record['state'] ?? null, ['fixture_only', 'backup_not_restored'], true)
            || ($record['file'] ?? null) !== 'database.sql'
            || !self::sha($record['dump_sha256'] ?? null)
            || !self::sha($record['database_name_sha256'] ?? null)
            || !is_int($record['dump_bytes'] ?? null) || $record['dump_bytes'] < 1
            || $record['dump_bytes'] > self::MAX_SQL_BYTES
            || ($record['restore_tested'] ?? null) !== false
            || ($record['database_writes'] ?? null) !== false
            || ($record['installation_enabled'] ?? null) !== false
            || is_file($job.'/.client.cnf') || is_link($job.'/.client.cnf')) {
            throw new RuntimeException('DB_BACKUP_RECEIPT_INVALID');
        }
        if (filesize($sql) !== $record['dump_bytes']
            || !hash_equals($record['dump_sha256'], (string) hash_file('sha256', $sql))) {
            throw new RuntimeException('DB_BACKUP_SQL_TAMPERED');
        }
        return true;
    }

    private static function exportWithMysqlDump(string $binary, string $configFile, string $db, callable $sink): void
    {
        // No --databases: the file must not force CREATE DATABASE/USE a live DB.
        // No shell and no password anywhere in process arguments.
        $args = [$binary, '--defaults-file='.$configFile, '--single-transaction',
            '--quick', '--skip-lock-tables', '--no-tablespaces', '--hex-blob',
            '--routines', '--triggers', '--events', '--default-character-set=utf8mb4',
            '--skip-add-drop-table', '--skip-add-drop-database', $db];
        $process = @proc_open($args, [
            0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w'],
        ], $pipes, null, ['PATH'=>'/usr/bin:/bin','LANG'=>'C'], ['bypass_shell'=>true]);
        if (!is_resource($process)) throw new RuntimeException('DB_BACKUP_EXPORT_START_FAILED');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $endAt = microtime(true) + self::MAX_SECONDS;
        $stderrBytes = 0;
        $timedOut = false;
        $bad = false;
        $exportError = null;
        try {
            while (true) {
                $read = [];
                if (!feof($pipes[1])) $read[] = $pipes[1];
                if (!feof($pipes[2])) $read[] = $pipes[2];
                if (!$read) break;
                if (microtime(true) > $endAt) {
                    $timedOut = true;
                    break;
                }
                $write = null; $except = null;
                $n = @stream_select($read, $write, $except, 0, 200000);
                if ($n === false) { $bad = true; break; }
                foreach ($read as $pipe) {
                    $part = fread($pipe, 65536);
                    if ($part === false) { $bad = true; break 2; }
                    if ($pipe === $pipes[1] && $part !== '') $sink($part);
                    if ($pipe === $pipes[2]) $stderrBytes += strlen($part);
                }
            }
        } catch (Throwable $e) {
            // A failed write must not leave the dump process blocked on stdout.
            $exportError = $e;
            @proc_terminate($process);
        } finally {
            if ($timedOut || $bad) @proc_terminate($process);
            fclose($pipes[1]); fclose($pipes[2]);
            $exitCode = proc_close($process);
        }
        if ($exportError !== null) throw new RuntimeException('DB_BACKUP_EXPORT_FAILED', 0, $exportError);
        if ($timedOut || $bad || $exitCode !== 0 || $stderrBytes > 65536) {
            throw new RuntimeException('DB_BACKUP_EXPORT_FAILED');
        }
    }

    private static function createClientConfig(string $path, string $host, int $port, string $user, string $pass): void
    {
        $contents = "[client]\nuser=".self::quote($user)."\npassword=".self::quote($pass)
            ."\nhost=".self::quote($host)."\nport=".$port."\nprotocol=tcp\n";
        self::exclusiveWrite($path, $contents);
    }

    private static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private static function exclusiveWrite(string $path, string $contents): void
    {
        $fp = @fopen($path, 'xb');
        if ($fp === false) throw new RuntimeException('DB_BACKUP_FILE_EXISTS');
        try {
            if (!@chmod($path, 0600)) {
                throw new RuntimeException('DB_BACKUP_CHMOD_FAILED');
            }
            $length = strlen($contents);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($fp, substr($contents, $offset));
                if (!is_int($written) || $written < 1) {
                    throw new RuntimeException('DB_BACKUP_WRITE_FAILED');
                }
                $offset += $written;
            }
            if (!fflush($fp) || (function_exists('fsync') && !fsync($fp))) {
                throw new RuntimeException('DB_BACKUP_SYNC_FAILED');
            }
        } finally { fclose($fp); }
    }

    private static function required(array $config, string $key): string
    {
        $s = $config[$key] ?? null;
        if (!is_string($s) || $s === '' || strlen($s) > 4096) {
            throw new RuntimeException('DB_BACKUP_CONFIG_MISSING');
        }
        return $s;
    }

    private static function directory(string $path, bool $private): string
    {
        if (is_link($path) || !is_dir($path)) throw new RuntimeException('DB_BACKUP_DIRECTORY_INVALID');
        $resolved = realpath($path);
        if (!is_string($resolved) || $resolved === '/' || ($private && (fileperms($resolved) & 0077) !== 0)) {
            throw new RuntimeException('DB_BACKUP_DIRECTORY_UNSAFE');
        }
        return $resolved;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    private static function cli(): void
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('DB_BACKUP_CLI_ONLY');
    }
}
