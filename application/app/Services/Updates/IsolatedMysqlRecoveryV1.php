<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Phase 3B: actual MySQL SQL export/restore of generated TEST rows into two
 * disposable schemas in the GitHub Actions MySQL service. NEVER accepts a
 * customer DB or production connection and is not called by any web route.
 * This is NOT a general SQL restorer or production rollback implementation.
 */
final class IsolatedMysqlRecoveryV1
{
    private const MAX_DUMP_BYTES = 1048576;

    public static function guard(): void
    {
        if (PHP_SAPI !== 'cli'
            || getenv('GITHUB_ACTIONS') !== 'true'
            || getenv('GITHUB_REPOSITORY') !== 'tonsamith128/Barama-Real-Estate'
            || getenv('APP_ENV') !== 'testing'
            || getenv('DB_DATABASE') !== 'barama_test'
            || getenv('PACKAGE_DB_DATABASE') !== 'barama_package_test'
            || getenv('PACKAGE_DB_USERNAME') !== 'root'
            || getenv('PACKAGE_DB_HOST') !== '127.0.0.1'
            || getenv('PACKAGE_DB_PORT') !== '3306'
            || getenv('BARAMA_CI_ISOLATED_MYSQL_RECOVERY') !== 'ALLOW_DISPOSABLE_TEST_SCHEMAS_ONLY'
        ) {
            throw new RuntimeException('MYSQL_RECOVERY_CI_ONLY');
        }
        if (!class_exists(PDO::class) || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('MYSQL_RECOVERY_MYSQL_DRIVER_REQUIRED');
        }
        $pass = getenv('PACKAGE_DB_PASSWORD');
        if (!is_string($pass) || $pass === '' || strlen($pass) > 512) {
            throw new RuntimeException('MYSQL_RECOVERY_TEST_CREDENTIAL_MISSING');
        }
    }

    /** @return array<string,mixed> */
    public static function rehearse(): array
    {
        self::guard();
        $suffix = bin2hex(random_bytes(7));
        $source = 'barama_ci_source_' . $suffix;
        $destination = 'barama_ci_target_' . $suffix;
        $tempDir = sys_get_temp_dir() . '/barama-mysql-ci-' . $suffix;
        if (!mkdir($tempDir, 0700) || (fileperms($tempDir) & 0077) !== 0) {
            throw new RuntimeException('MYSQL_RECOVERY_PRIVATE_DIR_FAILED');
        }
        $dumpFile = $tempDir . '/fixture.sql';
        $pdo = null;
        $createdSource = false;
        $createdTarget = false;
        $cleanupFailed = false;
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
                'root', (string) getenv('PACKAGE_DB_PASSWORD'), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 6,
                ]);
            $pdo->exec('CREATE DATABASE `' . $source . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdSource = true;
            $pdo->exec('CREATE DATABASE `' . $destination . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdTarget = true;
            $pdo->exec('CREATE TABLE `' . $source . '`.`fixture_records` ('
                . 'id INT UNSIGNED NOT NULL PRIMARY KEY, '
                . 'label VARCHAR(190) NOT NULL, '
                . 'amount INT NOT NULL, '
                . 'payload LONGBLOB NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $insert = $pdo->prepare('INSERT INTO `' . $source . '`.`fixture_records` '
                . '(id, label, amount, payload) VALUES (?, ?, ?, ?)');
            $rows = [
                [1, 'Apartment α', 250000, "bin\0ary\x01\xFF"],
                [2, 'ផ្ទះល្វែង', 55000, "unicode-utf8-✓"],
                [3, 'Quote O\'Neil', -11, str_repeat("data\x00", 450)],
            ];
            foreach ($rows as $row) {
                $insert->execute($row);
            }
            $sourceRows = self::records($pdo, $source);
            if (count($sourceRows) !== 3) {
                throw new RuntimeException('MYSQL_RECOVERY_SOURCE_ROWS_INVALID');
            }
            // The exporter builds a narrowly scoped SQL fixture; it does not
            // inspect customer tables or call a production mysqldump binary.
            $sql = self::exportFixtureSql($pdo, $sourceRows);
            if ($sql === '' || strlen($sql) > self::MAX_DUMP_BYTES) {
                throw new RuntimeException('MYSQL_RECOVERY_DUMP_SIZE_INVALID');
            }
            $handle = @fopen($dumpFile, 'xb');
            if ($handle === false) {
                throw new RuntimeException('MYSQL_RECOVERY_DUMP_CREATE_FAILED');
            }
            try {
                if (!chmod($dumpFile, 0600)) {
                    throw new RuntimeException('MYSQL_RECOVERY_DUMP_PERMISSION_FAILED');
                }
                $offset = 0;
                while ($offset < strlen($sql)) {
                    $written = fwrite($handle, substr($sql, $offset, 65536));
                    if (!is_int($written) || $written <= 0) {
                        throw new RuntimeException('MYSQL_RECOVERY_DUMP_WRITE_FAILED');
                    }
                    $offset += $written;
                }
                if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new RuntimeException('MYSQL_RECOVERY_DUMP_SYNC_FAILED');
                }
            } finally {
                fclose($handle);
            }
            $digest = hash('sha256', $sql);
            self::checkDump($dumpFile, $digest);
            self::importFixtureSql($pdo, $destination, $dumpFile, $digest);
            $targetRows = self::records($pdo, $destination);
            if ($sourceRows !== $targetRows) {
                throw new RuntimeException('MYSQL_RECOVERY_DATA_MISMATCH');
            }
            // Mutation of the SQL copy must stop BEFORE issuing any SQL.
            $tampered = $sql . '-- tampered' . "\n";
            file_put_contents($dumpFile, $tampered);
            $tamperRejected = false;
            try {
                self::checkDump($dumpFile, $digest);
            } catch (RuntimeException $e) {
                $tamperRejected = $e->getMessage() === 'MYSQL_RECOVERY_DUMP_TAMPERED';
            }
            if (!$tamperRejected) {
                throw new RuntimeException('MYSQL_RECOVERY_TAMPER_NOT_REJECTED');
            }
            return [
                'status' => 'DISPOSABLE_MYSQL_SQL_RECOVERY_VERIFIED',
                'rows' => count($targetRows),
                'source_equals_target' => true,
                'sql_tamper_rejected' => true,
                'fixture_schemas_created' => 2,
                'database_scope' => 'GITHUB_CI_DISPOSABLE_ONLY',
                'source_sql_sha256' => $digest,
                'production_db_access' => false,
                'live_website_writes' => false,
                'automatic_installation_enabled' => false,
            ];
        } finally {
            if ($pdo instanceof PDO) {
                // Names are generated only by random_bytes above. No user input.
                if ($createdTarget) {
                    try { $pdo->exec('DROP DATABASE `' . $destination . '`'); }
                    catch (Throwable) { $cleanupFailed = true; }
                }
                if ($createdSource) {
                    try { $pdo->exec('DROP DATABASE `' . $source . '`'); }
                    catch (Throwable) { $cleanupFailed = true; }
                }
            }
            if (is_file($dumpFile)) {
                if (!unlink($dumpFile)) $cleanupFailed = true;
            }
            if (is_dir($tempDir)) {
                if (!rmdir($tempDir)) $cleanupFailed = true;
            }
            if ($cleanupFailed) {
                throw new RuntimeException('MYSQL_RECOVERY_CLEANUP_FAILED');
            }
        }
    }

    private static function exportFixtureSql(PDO $pdo, array $rows): string
    {
        $sql = "CREATE TABLE `fixture_records` (`id` INT UNSIGNED NOT NULL PRIMARY KEY, "
            . "`label` VARCHAR(190) NOT NULL, `amount` INT NOT NULL, `payload` LONGBLOB NOT NULL) "
            . "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
        foreach ($rows as $row) {
            $sql .= 'INSERT INTO `fixture_records` (`id`,`label`,`amount`,`payload`) VALUES ('
                . (int)$row['id'] . ',' . $pdo->quote($row['label']) . ','
                . (int)$row['amount'] . ',0x' . bin2hex($row['payload']) . ");\n";
        }
        return $sql;
    }

    private static function importFixtureSql(PDO $pdo, string $schema, string $file, string $sha): void
    {
        self::checkDump($file, $sha);
        $sql = file_get_contents($file);
        if (!is_string($sql) || strlen($sql) > self::MAX_DUMP_BYTES) {
            throw new RuntimeException('MYSQL_RECOVERY_IMPORT_INVALID');
        }
        $pdo->exec('USE `' . $schema . '`');
        $lines = explode("\n", trim($sql));
        if (count($lines) !== 4) {
            throw new RuntimeException('MYSQL_RECOVERY_STATEMENT_COUNT_INVALID');
        }
        foreach ($lines as $i => $statement) {
            $prefix = $i === 0 ? 'CREATE TABLE `fixture_records` ' : 'INSERT INTO `fixture_records` ';
            if (!str_starts_with($statement, $prefix) || !str_ends_with($statement, ';')) {
                throw new RuntimeException('MYSQL_RECOVERY_UNEXPECTED_SQL');
            }
            $pdo->exec($statement);
        }
    }

    private static function checkDump(string $path, string $sha): void
    {
        if (is_link($path) || !is_file($path)
            || (fileperms($path) & 0077) !== 0
            || filesize($path) > self::MAX_DUMP_BYTES
            || !hash_equals($sha, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('MYSQL_RECOVERY_DUMP_TAMPERED');
        }
    }

    private static function records(PDO $pdo, string $schema): array
    {
        $rows = $pdo->query('SELECT id,label,amount,payload FROM `' . $schema . '`.`fixture_records` ORDER BY id')->fetchAll();
        return array_map(static fn(array $v): array => [
            'id' => (int)$v['id'],
            'label' => (string)$v['label'],
            'amount' => (int)$v['amount'],
            'payload' => (string)$v['payload'],
        ], $rows);
    }
}
