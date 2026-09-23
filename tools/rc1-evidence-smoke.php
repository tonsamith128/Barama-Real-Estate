<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tool = $root . '/tools/rc1-evidence.php';
$canonical = $root . '/docs/RC1_ACCEPTANCE.json';

$checks = 0;
$failures = [];

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$fail = static function (string $message) use (&$checks, &$failures): void {
    $checks++;
    $failures[] = $message;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

if (!function_exists('exec')) {
    fwrite(STDERR, "[FAIL] PHP exec() is unavailable; RC1 evidence smoke test cannot run.\n");
    exit(1);
}

$tempDir = sys_get_temp_dir()
    . '/barama-rc1-evidence-'
    . bin2hex(random_bytes(6));

if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
    fwrite(STDERR, "[FAIL] Could not create isolated RC1 evidence test directory.\n");
    exit(1);
}

$tempRecord = $tempDir . '/RC1_ACCEPTANCE.json';
$canonicalBefore = hash_file('sha256', $canonical);

if (!copy($canonical, $tempRecord)) {
    fwrite(STDERR, "[FAIL] Could not copy the RC1 acceptance fixture.\n");
    @rmdir($tempDir);
    exit(1);
}

$previousTestMode = getenv('BARAMA_RC1_TEST_MODE');
putenv('BARAMA_RC1_TEST_MODE=1');

$run = static function (array $arguments) use ($tool): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($tool);

    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    $output = [];
    $exitCode = 1;
    exec($command . ' 2>&1', $output, $exitCode);

    return [
        'code' => $exitCode,
        'output' => implode(PHP_EOL, $output),
    ];
};

$dryRunBefore = hash_file('sha256', $tempRecord);
$result = $run([
    '--gate=automated.foundation_qa',
    '--status=pass',
    '--evidence=Quality run 123 completed all Foundation QA steps for commit 0123456789ab.',
    '--record=' . $tempRecord,
]);

if ($result['code'] === 0 && hash_file('sha256', $tempRecord) === $dryRunBefore) {
    $pass('Dry-run validates evidence without changing the record.');
} else {
    $fail('Dry-run changed the record or returned a failure.');
}

$result = $run([
    '--gate=automated.foundation_qa',
    '--status=pass',
    '--evidence=Quality run 123 completed all Foundation QA steps for commit 0123456789ab.',
    '--record=' . $tempRecord,
    '--apply',
]);

$applied = json_decode((string) file_get_contents($tempRecord), true);

if (
    $result['code'] === 0
    && is_array($applied)
    && ($applied['automated']['foundation_qa']['status'] ?? null) === 'pass'
    && ($applied['automated']['foundation_qa']['evidence'] ?? '') !== ''
    && preg_match(
        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
        (string) ($applied['updated_at'] ?? '')
    ) === 1
) {
    $pass('Apply mode records one approved gate and updates the timestamp.');
} else {
    $fail('Apply mode did not persist the expected approved gate.');
}

$result = $run([
    '--gate=automated.foundation_qa',
    '--status=pass',
    '--evidence=token=secret-value-that-must-not-enter-git',
    '--record=' . $tempRecord,
]);

$result['code'] !== 0
    ? $pass('Secret-like evidence is rejected.')
    : $fail('Secret-like evidence was accepted.');

$result = $run([
    '--gate=automated.foundation_qa',
    '--status=pending',
    '--evidence=stale evidence',
    '--record=' . $tempRecord,
]);

$result['code'] !== 0
    ? $pass('Pending gates cannot retain evidence.')
    : $fail('Pending gate accepted stale evidence.');

$result = $run([
    '--gate=live_server.not_a_real_gate',
    '--status=pass',
    '--evidence=Example non-secret evidence.',
    '--record=' . $tempRecord,
]);

$result['code'] !== 0
    ? $pass('Unknown RC1 gates are rejected.')
    : $fail('Unknown RC1 gate was accepted.');

$result = $run([
    '--gate=automated.foundation_qa',
    '--status=pending',
    '--record=' . $tempRecord,
    '--apply',
]);

$reset = json_decode((string) file_get_contents($tempRecord), true);

if (
    $result['code'] === 0
    && is_array($reset)
    && ($reset['automated']['foundation_qa']['status'] ?? null) === 'pending'
    && ($reset['automated']['foundation_qa']['evidence'] ?? null) === ''
) {
    $pass('Resetting a gate to pending clears its evidence.');
} else {
    $fail('Resetting a gate to pending did not clear evidence.');
}

$canonicalAfter = hash_file('sha256', $canonical);

$canonicalBefore === $canonicalAfter
    ? $pass('Smoke testing never mutates the canonical acceptance record.')
    : $fail('Canonical RC1 acceptance record changed during smoke testing.');

if ($previousTestMode === false) {
    putenv('BARAMA_RC1_TEST_MODE');
} else {
    putenv('BARAMA_RC1_TEST_MODE=' . $previousTestMode);
}

@unlink($tempRecord);
@rmdir($tempDir);

fwrite(
    STDOUT,
    sprintf(
        PHP_EOL
        . 'Barama RC1 evidence smoke: %d check(s), %d failure(s).'
        . PHP_EOL,
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
