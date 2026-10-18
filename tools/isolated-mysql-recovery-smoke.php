<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/IsolatedMysqlRecoveryV1.php';

use Barama\Services\Updates\IsolatedMysqlRecoveryV1;

$mode = $argv[1] ?? '';
if ($mode === '--guard') {
    // No connection attempt is allowed without an actual GitHub Actions MySQL
    // service. An aaPanel production host MUST reject this class.
    try {
        IsolatedMysqlRecoveryV1::guard();
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'MYSQL_RECOVERY_CI_ONLY') {
            throw $exception;
        }
        echo "PRODUCTION_RUNTIME_GUARD=PASS\n";
        echo "LIVE_DB_CONNECTIONS=NONE\n";
        echo "DB_WRITES=NO\n";
        echo "AUTO_INSTALL=DISABLED\n";
        exit(0);
    }
    throw new RuntimeException('CI_ONLY_GUARD_NOT_ENFORCED');
}
if ($mode !== '--ci') {
    throw new RuntimeException('USAGE: --guard OR --ci');
}

$result = IsolatedMysqlRecoveryV1::rehearse();
if (($result['status'] ?? '') !== 'DISPOSABLE_MYSQL_SQL_RECOVERY_VERIFIED'
    || ($result['rows'] ?? null) !== 3
    || ($result['fixture_schemas_created'] ?? null) !== 2
    || ($result['source_equals_target'] ?? null) !== true
    || ($result['sql_tamper_rejected'] ?? null) !== true
    || ($result['production_db_access'] ?? null) !== false
    || ($result['live_website_writes'] ?? null) !== false
    || ($result['automatic_installation_enabled'] ?? null) !== false) {
    throw new RuntimeException('MYSQL_RECOVERY_RESULT_UNTRUSTED');
}

echo "MYSQL_DISPOSABLE_SQL_EXPORT=PASS\n";
echo "MYSQL_DISPOSABLE_SQL_IMPORT=PASS\n";
echo "MYSQL_BLOB_AND_UNICODE_PARITY=PASS\n";
echo "MYSQL_SQL_TAMPER_REJECTION=PASS\n";
echo "MYSQL_SOURCE_AND_TARGET_EQUAL=PASS\n";
echo "MYSQL_SCHEMAS_CLEANUP=PASS\n";
echo "LIVE_DB_CONNECTIONS=NONE\n";
echo "LIVE_WEBSITE_WRITES=NO\n";
echo "AUTO_INSTALL=DISABLED\n";
echo "ISOLATED_MYSQL_RECOVERY=PASS\n";
