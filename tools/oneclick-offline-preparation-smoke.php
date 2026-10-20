<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickOfflinePreparationV1.php';

use Barama\Services\Updates\OneClickOfflinePreparationV1;

function assertCase(bool $ok, string $name): void {
    if (!$ok) {
        fwrite(STDERR, 'FAIL=' . $name . PHP_EOL);
        exit(1);
    }
    echo 'PASS=' . $name . PHP_EOL;
}

// No actual updater bootstrap is permitted in this isolated test.
foreach (['/', '/tmp/barama-staging', '/www/wwwroot/baramarealestate.tonsamith.com',
          '/www/wwwroot/baramastaging.tonsamith.com/../baramastaging.tonsamith.com'] as $root) {
    try {
        OneClickOfflinePreparationV1::inspect($root);
        assertCase(false, 'untrusted_root_rejected');
    } catch (RuntimeException $e) {
        assertCase($e->getMessage() === 'ONECLICK_PREFLIGHT_STAGING_ONLY',
            'untrusted_root_rejected');
    }
}
try {
    OneClickOfflinePreparationV1::inspect('/www/wwwroot/baramastaging.tonsamith.com',
        '/root/forged-vault', str_repeat('f', 64), '/root/forged-db', str_repeat('f', 64), str_repeat('f', 64));
    assertCase(false, 'unbootstrapped_forged_evidence_rejected');
} catch (RuntimeException $e) {
    assertCase($e->getMessage() === 'ONECLICK_PREFLIGHT_STAGING_ONLY',
        'unbootstrapped_forged_evidence_rejected');
}
$ref = new ReflectionClass(OneClickOfflinePreparationV1::class);
$sha = $ref->getMethod('sha');
assertCase($sha->invoke(null, str_repeat('a', 64)), 'sha256_format_accepted');
assertCase(!$sha->invoke(null, str_repeat('A', 64)), 'uppercase_hash_rejected');
assertCase(!$sha->invoke(null, str_repeat('a', 63)), 'short_hash_rejected');
assertCase(!$sha->invoke(null, 'path-injection'), 'hash_path_injection_rejected');
$dir = $ref->getMethod('safeDirectory');
assertCase(!$dir->invoke(null, '/root/not-a-real-dir', true), 'missing_private_vault_rejected');
assertCase(!$dir->invoke(null, '/tmp', true), 'non_root_vault_rejected');
assertCase(!$dir->invoke(null, '/root/../tmp', true), 'traversal_vault_rejected');
$tmp = sys_get_temp_dir().'/barama-oneclick-readonly-fixture-'.bin2hex(random_bytes(6));
mkdir($tmp, 0700);
try {
    $receipt = $ref->getMethod('readReceipt');
    file_put_contents($tmp.'/receipt.json', "{\"schema\":\"fixture\"}\n");
    chmod($tmp.'/receipt.json', 0600);
    assertCase(($receipt->invoke(null, $tmp)['schema'] ?? '') === 'fixture', 'valid_json_fixture_read');
    chmod($tmp.'/receipt.json', 0644);
    try { $receipt->invoke(null, $tmp); assertCase(false, 'insecure_receipt_rejected'); }
    catch (RuntimeException $e) {assertCase($e->getMessage() === 'ONECLICK_RECEIPT_UNTRUSTED', 'insecure_receipt_rejected');}
    unlink($tmp.'/receipt.json');
    symlink('/dev/null', $tmp.'/receipt.json');
    try { $receipt->invoke(null, $tmp); assertCase(false, 'symlink_receipt_rejected'); }
    catch (RuntimeException $e) {assertCase($e->getMessage() === 'ONECLICK_RECEIPT_UNTRUSTED', 'symlink_receipt_rejected');}
} finally {
    if (file_exists($tmp.'/receipt.json') || is_link($tmp.'/receipt.json')) unlink($tmp.'/receipt.json');
    rmdir($tmp);
}
$coverage = $ref->getMethod('codeReceiptMatchesPlan');
$plan = [
    'archive_sha256' => str_repeat('a', 64),
    'target_version' => '0.9.23',
    'targets' => [
        ['path' => 'VERSION', 'archive_sha256' => str_repeat('b', 64), 'installed_sha256' => str_repeat('c', 64)],
        ['path' => 'assets/css/app.css', 'archive_sha256' => str_repeat('d', 64), 'installed_sha256' => null],
    ],
];
$receipt = [
    'release_archive_sha256' => str_repeat('a', 64),
    'installed_version' => '0.9.22-dev',
    'target_version' => '0.9.23',
    'state' => 'prepared_not_installed',
    'files' => [
        ['path' => 'VERSION', 'baseline' => str_repeat('c', 64), 'new_sha256' => str_repeat('b', 64), 'exists' => true],
        ['path' => 'assets/css/app.css', 'baseline' => null, 'new_sha256' => str_repeat('d', 64), 'exists' => false],
    ],
];
assertCase($coverage->invoke(null, $receipt, $plan, '0.9.22-dev') === true, 'complete_signed_plan_coverage');
$mutants = [];
$t = $receipt; $t['release_archive_sha256'] = str_repeat('e', 64); $mutants['wrong_archive'] = $t;
$t = $receipt; $t['target_version'] = '0.9.24'; $mutants['wrong_target_version'] = $t;
$t = $receipt; $t['files'][0]['baseline'] = str_repeat('e', 64); $mutants['wrong_installed_baseline'] = $t;
$t = $receipt; $t['files'][1]['new_sha256'] = str_repeat('e', 64); $mutants['wrong_target_hash'] = $t;
$t = $receipt; $t['files'][1]['exists'] = true; $mutants['false_existing_file'] = $t;
$t = $receipt; $t['files'][1]['path'] = 'assets/css/foreign.css'; $mutants['foreign_target'] = $t;
$t = $receipt; $t['files'][] = $t['files'][0]; $mutants['duplicate_extra_target'] = $t;
$t = $receipt; array_pop($t['files']); $mutants['missing_target'] = $t;
$t = $receipt; $t['files'][1] = $t['files'][0]; $mutants['duplicate_target'] = $t;
foreach ($mutants as $name => $mutant) {
    assertCase($coverage->invoke(null, $mutant, $plan, '0.9.22-dev') === false, $name.'_rejected');
}
$src = file_get_contents(dirname(__DIR__).'/application/app/Services/Updates/OneClickOfflinePreparationV1.php');
assertCase(is_string($src) && str_contains($src, "'installation_enabled' => false")
    && str_contains($src, "'root_worker_started' => false")
    && str_contains($src, "'restore_rehearsal_verified' => false"),
    'install_and_recovery_never_claimed_complete');
assertCase(!preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i', $src),
    'no_mutating_or_execution_entrypoints');
echo 'ONECLICK_OFFLINE_PREPARATION_SMOKE=PASS'.PHP_EOL;
