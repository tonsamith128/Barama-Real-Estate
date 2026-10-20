<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickRecoverySnapshotPairV1.php';

use Barama\Services\Updates\OneClickRecoverySnapshotPairV1 as Recovery;

function expectRecovery(bool $value, string $label): void
{
    if (!$value) {
        fwrite(STDERR, 'FAIL=' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS=' . $label . PHP_EOL;
}

$pair = [
    'status' => 'PRIVATE_CODE_AND_SQL_SNAPSHOTS_VERIFIED_NOT_RESTORED',
    'pair_receipt_sha256' => str_repeat('a', 64),
    'code_receipt_sha256' => str_repeat('b', 64),
    'db_receipt_sha256' => str_repeat('c', 64),
    'signed_release_sha256' => str_repeat('d', 64),
    'installation_enabled' => false,
    'full_site_backup_verified' => false,
    'database_restore_tested' => false,
    'consistent_recovery_point_verified' => false,
];
$required = [
    'FULL_SITE_RESTORE_CAPABILITY_NOT_PROVEN',
    'SQL_RESTORE_REHEARSAL_NOT_PROVEN',
    'COHERENT_CODE_DATABASE_CHECKPOINT_NOT_PROVEN',
    'INDEPENDENT_INSTALL_AUTHORIZATION_NOT_PROVEN',
    'CRASH_SAFE_PRIVILEGED_WORKER_NOT_PROVEN',
];
$assess = static function (array $data, string $label, bool $mustRejectReceipt = false) use ($required): void {
    $result = Recovery::releaseGate($data);
    expectRecovery(($result['status'] ?? '') === 'BACKUP_PAIR_ONLY_INSTALL_BLOCKED'
        && ($result['ready_to_install'] ?? null) === false, $label . '_install_blocked');
    foreach ($required as $requiredBlocker) {
        expectRecovery(in_array($requiredBlocker, $result['blockers'] ?? [], true),
            $label . '_' . $requiredBlocker);
    }
    expectRecovery(in_array('PAIR_RECEIPT_NOT_TRUSTED', $result['blockers'] ?? [], true) === $mustRejectReceipt,
        $label . '_receipt_classification');
};
$assess($pair, 'valid_but_unrestored_pair');

$mutants = [
    'empty' => [],
    'forged_install' => array_replace($pair, ['installation_enabled' => true]),
    'forged_full_site' => array_replace($pair, ['full_site_backup_verified' => true]),
    'forged_restore' => array_replace($pair, ['database_restore_tested' => true]),
    'forged_consistency' => array_replace($pair, ['consistent_recovery_point_verified' => true]),
    'wrong_state' => array_replace($pair, ['status' => 'INSTALL_READY']),
    'short_pair_hash' => array_replace($pair, ['pair_receipt_sha256' => str_repeat('a', 63)]),
    'uppercase_code_hash' => array_replace($pair, ['code_receipt_sha256' => str_repeat('B', 64)]),
    'missing_db_hash' => array_replace($pair, ['db_receipt_sha256' => null]),
    'malformed_release_hash' => array_replace($pair, ['signed_release_sha256' => '../release.zip']),
];
foreach ($mutants as $name => $data) {
    $assess($data, $name, true);
}

$errors = [
    'production_root' => '/www/wwwroot/baramarealestate.tonsamith.com',
    'path_traversal' => '/www/wwwroot/baramastaging.tonsamith.com/../baramastaging.tonsamith.com',
    'arbitrary_root' => '/tmp/barama-staging',
];
foreach ($errors as $name => $root) {
    try {
        Recovery::prepareStaging($root, [], 'PREPARE_STAGING_PRIVATE_SNAPSHOTS_ONLY');
        expectRecovery(false, $name . '_accepted');
    } catch (RuntimeException $e) {
        expectRecovery($e->getMessage() === 'RECOVERY_STAGING_ROOT_APPROVAL_REQUIRED',
            $name . '_denied');
    }
}
try {
    Recovery::prepareStaging('/www/wwwroot/baramastaging.tonsamith.com', [], 'I_APPROVE_INSTALL');
    expectRecovery(false, 'invalid_approval_accepted');
} catch (RuntimeException $e) {
    expectRecovery($e->getMessage() === 'RECOVERY_STAGING_ROOT_APPROVAL_REQUIRED',
        'invalid_approval_denied');
}

foreach ([
    'path_traversal' => '../pair-' . str_repeat('a', 32),
    'forged_pair_id' => 'pair-' . str_repeat('A', 32),
    'foreign_pair' => 'db-' . str_repeat('a', 32),
] as $name => $pairId) {
    try {
        Recovery::verifyStagingPair(
            '/www/wwwroot/baramastaging.tonsamith.com',
            $pairId,
            str_repeat('a', 64)
        );
        expectRecovery(false, $name . '_accepted');
    } catch (RuntimeException $e) {
        expectRecovery($e->getMessage() === 'RECOVERY_STAGING_ROOT_APPROVAL_REQUIRED',
            $name . '_root_boundary_denied');
    }
}
$path = dirname(__DIR__) . '/application/app/Services/Updates/OneClickRecoverySnapshotPairV1.php';
$source = file_get_contents($path);
expectRecovery(is_string($source)
    && str_contains($source, 'VerifiedReleaseFilePlanV2::inspect(')
    && str_contains($source, 'VerifiedUpdateBackupV2::prepare(')
    && str_contains($source, 'VerifiedUpdateBackupV2::verify(')
    && str_contains($source, 'PrivateDatabaseBackupV2::prepare(')
    && str_contains($source, 'PrivateDatabaseBackupV2::verify(')
    && str_contains($source, 'OneClickOfflinePreparationV1::inspect(')
    && str_contains($source, 'RECOVERY_INSTALLED_DATABASE_BINDING_MISMATCH')
    && str_contains($source, 'public static function verifyStagingPair(')
    && str_contains($source, 'paired_snapshots_not_restored')
    && !str_contains($source, 'UpgradeService::upgrade(')
    && !preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|curl_exec)\s*\(/i', $source),
    'backup_pair_is_only_existing_services_no_privileged_executor');
echo 'ONECLICK_MANDATORY_RECOVERY_PAIR_SMOKE=PASS' . PHP_EOL;
