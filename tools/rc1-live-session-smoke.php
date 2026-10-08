<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tool = $root . '/tools/rc1-live-session.php';

// Keep the acceptance-session smoke bound to the real preflight asset contract.
$assetSmoke = $root . '/tools/live-preflight-asset-smoke.php';
$assetLines = [];
$assetCode = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($assetSmoke) . ' 2>&1',
    $assetLines,
    $assetCode
);
foreach ($assetLines as $line) {
    echo $line . PHP_EOL;
}
if ($assetCode !== 0) {
    fwrite(STDERR, "[FAIL] Exact-asset preflight contract smoke failed.\n");
    exit(1);
}
// Verify the customer-facing HTML status route and minimal JSON machine
// endpoint against a disposable local server before accepting live sessions.
$healthSmoke = $root . '/tools/live-preflight-health-smoke.php';
$healthLines = [];
$healthCode = 1;
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($healthSmoke) . ' 2>&1',
    $healthLines,
    $healthCode
);
foreach ($healthLines as $line) {
    echo $line . PHP_EOL;
}
if ($healthCode !== 0) {
    fwrite(STDERR, "[FAIL] Health endpoint preflight contract smoke failed.\n");
    exit(1);
}

$version = trim((string) @file_get_contents($root . '/VERSION'));
$temp = sys_get_temp_dir()
    . '/barama-live-session-smoke-'
    . bin2hex(random_bytes(5));

if (
    $version === ''
    || (!mkdir($temp, 0700, true) && !is_dir($temp))
) {
    fwrite(
        STDERR,
        "[FAIL] Live-session smoke setup failed.\n"
    );
    exit(1);
}

$commit = '0123456789abcdef0123456789abcdef01234567';
$package = $temp . '/Barama-Real-Estate-' . $version . '.zip';
$manifest = $temp
    . '/Barama-Real-Estate-'
    . $version
    . '.artifact.json';
$artifactPass = $temp . '/artifact-pass.php';
$artifactFail = $temp . '/artifact-fail.php';
$preflight = $temp . '/preflight.php';

file_put_contents($package, "fake package\n", LOCK_EX);
$hash = hash_file('sha256', $package);

file_put_contents(
    $manifest,
    json_encode([
        'schema_version' => 1,
        'product' => 'barama-real-estate',
        'source_version' => $version,
        'source_commit' => $commit,
        'package_filename' => basename($package),
        'package_sha256' => $hash,
        'package_size_bytes' => filesize($package),
        'checksum_filename' => basename($package) . '.sha256',
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);

file_put_contents(
    $artifactPass,
    "<?php\nexit(0);\n",
    LOCK_EX
);

file_put_contents(
    $artifactFail,
    "<?php\nfwrite(STDERR, 'artifact failure\\n');\nexit(1);\n",
    LOCK_EX
);

$preflightFixture = <<<'PHPFIXTURE'
<?php
$mode = getenv('BARAMA_SESSION_SMOKE_MODE') ?: 'ready';
$version = trim((string) getenv('BARAMA_SESSION_SMOKE_VERSION'));
$checks = [[
    'level' => 'pass',
    'title' => 'Safe check',
    'detail' => 'Read-only acceptance check passed.',
]];
$overall = 'ready';
$passed = 1;
$warnings = 0;
$failed = 0;
$code = 0;

if ($mode === 'warn') {
    $checks[] = [
        'level' => 'warn',
        'title' => 'Proxy review',
        'detail' => 'HSTS is managed by the reverse proxy.',
    ];
    $overall = 'ready_with_warnings';
    $warnings = 1;
}

if ($mode === 'fail') {
    $checks[] = [
        'level' => 'fail',
        'title' => 'Route check',
        'detail' => 'Expected route response was not observed.',
    ];
    $overall = 'failed';
    $failed = 1;
    $code = 1;
}

if ($mode === 'unsafe') {
    $checks[] = [
        'level' => 'pass',
        'title' => 'Unsafe check',
        'detail' => 'token=super-secret-value',
    ];
    $passed = 2;
}

echo json_encode([
    'overall' => $overall,
    'phase' => 'preinstall',
    'version' => $version,
    'url_host' => 'acceptance.example.test',
    'passed' => $passed,
    'warnings' => $warnings,
    'failed' => $failed,
    'checks' => $checks,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($code);
PHPFIXTURE;

file_put_contents(
    $preflight,
    $preflightFixture . PHP_EOL,
    LOCK_EX
);

$checks = 0;
$failures = [];

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$fail = static function (
    string $message
) use (&$checks, &$failures): void {
    $checks++;
    $failures[] = $message;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

$run = static function (
    string $artifactTool,
    string $mode,
    string $output
) use (
    $tool,
    $manifest,
    $package,
    $root,
    $commit,
    $preflight,
    $version
): array {
    putenv('BARAMA_RC1_SESSION_TEST_MODE=1');
    putenv(
        'BARAMA_RC1_SESSION_ARTIFACT_TOOL='
        . $artifactTool
    );
    putenv(
        'BARAMA_RC1_SESSION_PREFLIGHT_TOOL='
        . $preflight
    );
    putenv('BARAMA_SESSION_SMOKE_MODE=' . $mode);
    putenv(
        'BARAMA_SESSION_SMOKE_VERSION='
        . $version
    );

    $arguments = [
        PHP_BINARY,
        $tool,
        '--manifest=' . $manifest,
        '--package=' . $package,
        '--root=' . $root,
        '--url=https://acceptance.example.test',
        '--phase=preinstall',
        '--expect-version=' . $version,
        '--expect-commit=' . $commit,
        '--output=' . $output,
    ];

    $command = implode(
        ' ',
        array_map('escapeshellarg', $arguments)
    );
    $lines = [];
    $code = 1;
    exec(
        $command . ' 2>&1',
        $lines,
        $code
    );

    return [
        'code' => $code,
        'output' => implode(PHP_EOL, $lines),
    ];
};

$output = $temp . '/passed.json';
$result = $run($artifactPass, 'ready', $output);
$report = is_file($output)
    ? json_decode(
        (string) file_get_contents($output),
        true
    )
    : null;

if (
    $result['code'] === 0
    && is_array($report)
    && ($report['session_status'] ?? '') === 'passed'
    && ($report['artifact']['source_commit'] ?? '') === $commit
    && preg_match(
        '/^[a-f0-9]{64}$/',
        (string) ($report['session_fingerprint'] ?? '')
    ) === 1
    && !str_contains(
        (string) file_get_contents($output),
        'super-secret-value'
    )
) {
    $pass(
        'Passing session writes a bound sanitized report.'
    );
} else {
    $fail('Passing session report is invalid.');
}

$output = $temp . '/warn.json';
$result = $run($artifactPass, 'warn', $output);
$report = is_file($output)
    ? json_decode(
        (string) file_get_contents($output),
        true
    )
    : null;

if (
    $result['code'] === 3
    && is_array($report)
    && ($report['session_status'] ?? '')
        === 'review_required'
    && ($report['preflight']['warnings'] ?? 0) === 1
) {
    $pass(
        'Warnings require review and cannot silently pass.'
    );
} else {
    $fail('Warnings did not require review.');
}

$output = $temp . '/failed.json';
$result = $run($artifactPass, 'fail', $output);
$report = is_file($output)
    ? json_decode(
        (string) file_get_contents($output),
        true
    )
    : null;

if (
    $result['code'] === 1
    && is_array($report)
    && ($report['session_status'] ?? '') === 'failed'
    && ($report['preflight']['failed'] ?? 0) === 1
) {
    $pass(
        'Failed preflight produces a failed sanitized session report.'
    );
} else {
    $fail(
        'Failed preflight was not preserved safely.'
    );
}

$output = $temp . '/artifact-failed.json';
@unlink($output);
$result = $run(
    $artifactFail,
    'ready',
    $output
);

if (
    $result['code'] !== 0
    && !is_file($output)
) {
    $pass(
        'Artifact binding failure blocks report creation before preflight.'
    );
} else {
    $fail(
        'Artifact binding failure did not fail closed.'
    );
}

$output = $temp . '/unsafe.json';
@unlink($output);
$result = $run(
    $artifactPass,
    'unsafe',
    $output
);

if (
    $result['code'] !== 0
    && !is_file($output)
) {
    $pass(
        'Secret-like preflight evidence is rejected before report creation.'
    );
} else {
    $fail('Unsafe evidence was retained.');
}

foreach (glob($temp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($temp);

fwrite(
    STDOUT,
    sprintf(
        "\nBarama live acceptance session smoke: "
        . "%d check(s), %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
