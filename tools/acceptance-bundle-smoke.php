<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$builder = $root . '/tools/build-acceptance-bundle.php';
$version = trim((string) @file_get_contents($root . '/VERSION'));
$commit = '0123456789abcdef0123456789abcdef01234567';
$wrongCommit = '89abcdef0123456789abcdef0123456789abcdef';
$temp = sys_get_temp_dir()
    . '/barama-acceptance-bundle-'
    . bin2hex(random_bytes(5));

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

if (
    $version === ''
    || !class_exists(ZipArchive::class)
    || !function_exists('exec')
    || (!mkdir($temp, 0700, true) && !is_dir($temp))
) {
    fwrite(
        STDERR,
        "[FAIL] Acceptance-bundle smoke requirements are unavailable.\n"
    );
    exit(1);
}

$package = $temp . '/Barama-Real-Estate-' . $version . '.zip';
$artifact = $temp
    . '/Barama-Real-Estate-'
    . $version
    . '.artifact.json';
$output = $temp
    . '/Barama-Real-Estate-aaPanel-Acceptance-'
    . $version
    . '.zip';

file_put_contents($package, "fake exact customer package\n", LOCK_EX);
$packageHash = hash_file('sha256', $package);

file_put_contents(
    $package . '.sha256',
    $packageHash . '  ' . basename($package) . PHP_EOL,
    LOCK_EX
);

file_put_contents(
    $artifact,
    json_encode([
        'schema_version' => 1,
        'product' => 'barama-real-estate',
        'source_version' => $version,
        'source_commit' => $commit,
        'package_filename' => basename($package),
        'package_sha256' => $packageHash,
        'package_size_bytes' => filesize($package),
        'checksum_filename' => basename($package) . '.sha256',
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);

$run = static function (
    string $commitValue,
    string $outputPath
) use ($builder, $package, $artifact): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($builder)
        . ' '
        . escapeshellarg($package)
        . ' '
        . escapeshellarg($artifact)
        . ' '
        . escapeshellarg('--source-commit=' . $commitValue)
        . ' '
        . escapeshellarg('--output=' . $outputPath)
        . ' 2>&1';

    $lines = [];
    $code = 1;
    exec($command, $lines, $code);

    return [
        'code' => $code,
        'output' => implode(PHP_EOL, $lines),
    ];
};

$result = $run($commit, $output);

if (
    $result['code'] === 0
    && is_file($output)
    && is_file($output . '.sha256')
) {
    $pass('Bound aaPanel acceptance bundle builds successfully.');
} else {
    $fail('Acceptance bundle build failed.');
}

$zip = new ZipArchive();
$prefix = 'Barama-Real-Estate-aaPanel-Acceptance-' . $version . '/';
$entries = [];

if ($zip->open($output) === true) {
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if (is_string($name)) {
            $entries[] = $name;
        }
    }
}

$required = [
    $prefix . 'INTERNAL-QA-ONLY.txt',
    $prefix . 'README.md',
    $prefix . 'ROLLBACK.md',
    $prefix . 'BUNDLE_MANIFEST.json',
    $prefix . 'BUNDLE_CONTENTS.sha256',
    $prefix . 'candidate/' . basename($package),
    $prefix . 'candidate/' . basename($package) . '.sha256',
    $prefix . 'candidate/' . basename($artifact),
    $prefix . 'tools/rc1-acceptance-lib.php',
    $prefix . 'tools/rc1-artifact.php',
    $prefix . 'tools/live-server-preflight.php',
    $prefix . 'tools/rc1-live-session.php',
    $prefix . 'bin/verify-bundle.sh',
    $prefix . 'bin/preinstall-readonly.sh',
    $prefix . 'bin/installed-readonly.sh',
];

$missing = array_values(array_diff($required, $entries));

$missing === []
    ? $pass('Acceptance bundle contains the complete bounded payload.')
    : $fail(
        'Acceptance bundle is missing: ' . implode(', ', $missing)
    );

$forbidden = [
    'docs/RC1_ACCEPTANCE.json',
    'tools/rc1-evidence.php',
    'tools/rc1-evidence-smoke.php',
    '.github/',
    'application/',
    'assets/',
];

$forbiddenFound = [];

foreach ($entries as $entry) {
    $relative = str_starts_with($entry, $prefix)
        ? substr($entry, strlen($prefix))
        : $entry;

    foreach ($forbidden as $needle) {
        if (
            $relative === $needle
            || str_starts_with($relative, $needle)
        ) {
            $forbiddenFound[] = $relative;
        }
    }
}

$forbiddenFound === []
    ? $pass(
        'Acceptance bundle excludes RC1 state, customer runtime and CI source.'
    )
    : $fail(
        'Forbidden acceptance-bundle content detected: '
        . implode(', ', array_unique($forbiddenFound))
    );

$bundleManifest = json_decode(
    (string) $zip->getFromName(
        $prefix . 'BUNDLE_MANIFEST.json'
    ),
    true
);
$preinstall = (string) $zip->getFromName(
    $prefix . 'bin/preinstall-readonly.sh'
);
$installed = (string) $zip->getFromName(
    $prefix . 'bin/installed-readonly.sh'
);

if (
    is_array($bundleManifest)
    && ($bundleManifest['target_domain'] ?? '')
        === 'baramarealestate.tonsamith.com'
    && ($bundleManifest['target_root'] ?? '')
        === '/www/wwwroot/baramarealestate.tonsamith.com'
    && ($bundleManifest['source_commit'] ?? '') === $commit
    && str_contains(
        $preinstall,
        'SITE="/www/wwwroot/baramarealestate.tonsamith.com"'
    )
    && str_contains($preinstall, 'PHASE="preinstall"')
    && str_contains($installed, 'PHASE="installed"')
    && !str_contains($preinstall, 'rm -rf')
    && !str_contains($installed, 'rm -rf')
    && !str_contains($preinstall, 'mysql ')
    && !str_contains($installed, 'mysql ')
) {
    $pass(
        'Server helpers are hard-scoped and contain no destructive deployment.'
    );
} else {
    $fail('Server helper scope/safety contract is invalid.');
}

$checksumText = (string) $zip->getFromName(
    $prefix . 'BUNDLE_CONTENTS.sha256'
);
$checksumOk = true;

foreach (preg_split('/\\R/', trim($checksumText)) ?: [] as $line) {
    if ($line === '') {
        continue;
    }

    if (
        preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $match) !== 1
    ) {
        $checksumOk = false;
        break;
    }

    $content = $zip->getFromName($prefix . $match[2]);

    if (
        !is_string($content)
        || !hash_equals($match[1], hash('sha256', $content))
    ) {
        $checksumOk = false;
        break;
    }
}

$checksumOk
    ? $pass('Bundle payload checksum inventory verifies.')
    : $fail('Bundle payload checksum inventory is invalid.');

$zip->close();

$wrongOutput = $temp . '/wrong.zip';
$wrong = $run($wrongCommit, $wrongOutput);

if ($wrong['code'] !== 0 && !is_file($wrongOutput)) {
    $pass('Wrong source commit fails closed before bundle creation.');
} else {
    $fail('Wrong source commit was accepted.');
}

foreach (glob($temp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($temp);

fwrite(
    STDOUT,
    sprintf(
        "\nBarama aaPanel acceptance bundle smoke: "
        . "%d check(s), %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
