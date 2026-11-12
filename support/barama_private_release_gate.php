<?php

declare(strict_types=1);

/**
 * Validates private acceptance manifest completeness and evidence SHA-256.
 * It never verifies that real devices were tested or signatures are genuine.
 * It cannot authorize a production deployment.
 */
const BARAMA_REQUIRED_ACCEPTANCE = [
    'original_backup_two_restores',
    'legacy_customer_data_reconciliation',
    'private_media_sha256',
    'historical_financial_semantics',
    'historical_permission_and_mfa_review',
    'financial_independent_approval',
    'security_independent_approval',
    'privacy_independent_approval',
    'physical_aapanel_nginx_php',
    'physical_cpanel_apache_php',
    'physical_iphone_safari',
    'physical_android_chrome',
    'physical_tablet_browser',
    'apple_google_calendar_import',
    'stacked_pr_reconciliation',
];

try {
    if (PHP_SAPI !== 'cli' || count($argv) !== 3 ||
        $argv[1] !== '--review' ||
        getenv('APP_ENV') !== 'testing' ||
        getenv('BARAMA_REHEARSAL_APPROVED') !== 'TWO_DISPOSABLE_CLONES_VERIFIED') {
        throw new RuntimeException('PRIVATE_TESTING_ONLY');
    }
    if (!str_starts_with($argv[2], '--manifest=')) {
        throw new RuntimeException('MANIFEST_REQUIRED');
    }
    $path = substr($argv[2], strlen('--manifest='));
    if ($path === '' || is_link($path) || !is_file($path) ||
        filesize($path) < 1 || filesize($path) > 1048576) {
        throw new RuntimeException('PRIVATE_MANIFEST_REQUIRED');
    }
    $directory = realpath(dirname($path));
    if ($directory === false || !str_ends_with($directory, 'barama-private-rehearsal-evidence')
        || str_starts_with($directory, '/www/wwwroot/')
        || (fileperms($directory) & 0777) !== 0700) {
        throw new RuntimeException('OWNER_ONLY_PRIVATE_VAULT_REQUIRED');
    }
    $manifest = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    $sha = (string) ($manifest['commit'] ?? '');
    if (($manifest['schema'] ?? null) !== 'barama-private-acceptance-v1'
        || ($manifest['scope'] ?? null) !== 'isolated_qa_only'
        || !preg_match('/\A[a-f0-9]{40}\z/D', $sha)
        || $sha !== getenv('BARAMA_EXPECTED_GIT_SHA')
        || !preg_match('/\A[a-f0-9]{64}\z/D', (string) ($manifest['source_backup_sha256'] ?? ''))) {
        throw new RuntimeException('BACKUP_AND_COMMIT_IDENTITY_REQUIRED');
    }
    $operator = (string) ($manifest['operator_id'] ?? '');
    $reviewer = (string) ($manifest['reviewer_id'] ?? '');
    if (!preg_match('/\A[a-zA-Z0-9_-]{4,64}\z/D', $operator)
        || !preg_match('/\A[a-zA-Z0-9_-]{4,64}\z/D', $reviewer)
        || $operator === $reviewer) {
        throw new RuntimeException('DIFFERENT_OPERATOR_REVIEWER_LABELS_REQUIRED');
    }
    $checks = $manifest['checks'] ?? null;
    if (!is_array($checks) || array_keys($checks) !== BARAMA_REQUIRED_ACCEPTANCE) {
        throw new RuntimeException('REQUIRED_ACCEPTANCE_CONTROLS_MISSING');
    }
    $incomplete = 0;
    $files = [];
    foreach (BARAMA_REQUIRED_ACCEPTANCE as $name) {
        $entry = $checks[$name];
        if (!is_array($entry) ||
            !in_array(($entry['status'] ?? null), ['PASS','FAIL','NOT_TESTED'], true)) {
            throw new RuntimeException('INVALID_CONTROL_STATUS');
        }
        if ($entry['status'] !== 'PASS') {
            $incomplete++;
            continue;
        }
        $filename = (string) ($entry['evidence_file'] ?? '');
        $hash = (string) ($entry['evidence_sha256'] ?? '');
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{0,100}\z/D', $filename)
            || str_contains($filename, '..')
            || !preg_match('/\A[a-f0-9]{64}\z/D', $hash)
            || isset($files[$filename]) || is_link($directory.'/'.$filename)
            || !is_file($directory.'/'.$filename)
            || filesize($directory.'/'.$filename) > 200000
            || filesize($directory.'/'.$filename) < 1
            || !hash_equals($hash, hash_file('sha256', $directory.'/'.$filename))) {
            throw new RuntimeException('EVIDENCE_FILE_OR_HASH_INVALID');
        }
        $files[$filename] = true;
    }
    echo 'PRIVATE_ACCEPTANCE_EVIDENCE='.($incomplete === 0
        ? 'STRUCTURALLY_COMPLETE_NOT_INDEPENDENTLY_VERIFIED'
        : 'INCOMPLETE')."\n";
    echo 'INCOMPLETE_CONTROLS='.$incomplete."\n";
    echo "SIGNER_IDENTITY_AUTHENTICATED=NO\n";
    echo "ACTUAL_HOST_DEVICE_EXECUTION_VERIFIED=NO\n";
    echo "PRODUCTION_RELEASE=BLOCKED\n";
    exit($incomplete === 0 ? 0 : 2);
} catch (Throwable $error) {
    fwrite(STDERR, "PRIVATE_ACCEPTANCE_EVIDENCE=DENIED\n");
    fwrite(STDERR, "PRODUCTION_RELEASE=BLOCKED\n");
    exit(3);
}
