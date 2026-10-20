<?php

declare(strict_types=1);

require_once __DIR__ . '/OneClickOfflinePreparationV1.php';

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
$src = file_get_contents(__DIR__.'/OneClickOfflinePreparationV1.php');
assertCase(is_string($src) && str_contains($src, "'installation_enabled' => false")
    && str_contains($src, "'root_worker_started' => false")
    && str_contains($src, "'restore_rehearsal_verified' => false"),
    'install_and_recovery_never_claimed_complete');
assertCase(!preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i', $src),
    'no_mutating_or_execution_entrypoints');
echo 'ONECLICK_OFFLINE_PREPARATION_SMOKE=PASS'.PHP_EOL;
