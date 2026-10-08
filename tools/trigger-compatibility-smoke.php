<?php

declare(strict_types=1);

use Barama\Support\TriggerCapabilityProbe;

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

// Runs exclusively against a disposable CI schema/user. Never invoke this
// probe against an existing customer schema or a live database.
$host = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
$rootUser = (string) (getenv('PACKAGE_DB_USERNAME') ?: '');
$rootPassword = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');

if (getenv('APP_ENV') !== 'testing' || $rootUser !== 'root') {
    fwrite(STDERR, "[FAIL] Restricted DB QA runs only on test MySQL as root.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
    $rootUser,
    $rootPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$suffix = bin2hex(random_bytes(5));
$database = 'barama_hosting_qa_' . $suffix;
$username = 'barama_qa_' . $suffix;
$pass = bin2hex(random_bytes(16));
$databaseQuoted = '`' . $database . '`';
$userQuoted = $pdo->quote($username);
$passQuoted = $pdo->quote($pass);
$createdDatabase = false;
$createdUser = false;

try {
    $pdo->exec('CREATE DATABASE ' . $databaseQuoted . ' CHARACTER SET utf8mb4');
    $createdDatabase = true;
    $pdo->exec('CREATE USER ' . $userQuoted . "@'%' IDENTIFIED BY " . $passQuoted);
    $createdUser = true;
    $pdo->exec(
        'GRANT CREATE, DROP, SELECT ON ' . $databaseQuoted
        . '.* TO ' . $userQuoted . "@'%'"
    );

    $limited = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname='
        . $database . ';charset=utf8mb4',
        $username,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    try {
        TriggerCapabilityProbe::assertSupported($limited);
        throw new RuntimeException('Restricted user unexpectedly created triggers.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'required append-only email audit triggers')) {
            throw $exception;
        }
    }

    // The failed probe must not leave a partial audit table or test objects.
    $count = (int) $limited->query(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn();

    if ($count !== 0) {
        throw new RuntimeException('Restricted-host failure left partial schema objects.');
    }

    echo "[PASS] Restricted customer MySQL user rejected before schema changes.\n";

    $migration = require dirname(__DIR__)
        . '/application/database/migrations/0026_create_email_outbox_event_history.php';
    try {
        $migration($limited);
        throw new RuntimeException('Migration 0026 unexpectedly ran on restricted host.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'required append-only email audit triggers')) {
            throw $exception;
        }
    }

    $count = (int) $limited->query(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn();
    if ($count !== 0) {
        throw new RuntimeException('Migration 0026 created a partial table on restricted hosting.');
    }
    echo "[PASS] Migration 0026 fails before DDL on restricted hosting.\n";

    $pdo->exec('GRANT TRIGGER ON ' . $databaseQuoted . '.* TO ' . $userQuoted . "@'%'");

    // Privileges granted to an account take effect on a new MySQL connection.
    // Keep the first session for the denied case, then reconnect as the same
    // user to exercise the newly granted privileges.
    $limited = new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname='
        . $database . ';charset=utf8mb4',
        $username,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    TriggerCapabilityProbe::assertSupported($limited);

    $count = (int) $limited->query(
        'SELECT COUNT(*) FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn();

    if ($count !== 0) {
        throw new RuntimeException('Supported-host probe failed to clean up its DDL.');
    }

    echo "[PASS] Compatible customer MySQL account creates both triggers and cleans up.\n";
    echo "RESTRICTED_MYSQL_HOSTING_QA=PASS\n";
} finally {
    if ($createdUser) {
        $pdo->exec('DROP USER IF EXISTS ' . $userQuoted . "@'%'");
    }
    if ($createdDatabase) {
        $pdo->exec('DROP DATABASE IF EXISTS ' . $databaseQuoted);
    }
}
