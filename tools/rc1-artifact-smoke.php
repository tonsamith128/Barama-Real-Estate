<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tool = $root . '/tools/rc1-artifact.php';
$sourceVersion = trim(
    (string) @file_get_contents($root . '/VERSION')
);
$sourceCommit = '0123456789abcdef0123456789abcdef01234567';
$wrongCommit = '89abcdef0123456789abcdef0123456789abcdef';

$checks = 0;
$failures = [];

$pass = static function (
    string $message
) use (&$checks): void {
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

if (!function_exists('exec')) {
    fwrite(
        STDERR,
        "[FAIL] PHP exec() is unavailable; "
        . "artifact smoke test cannot run.\n"
    );
    exit(1);
}

if ($sourceVersion === '') {
    fwrite(
        STDERR,
        "[FAIL] Source VERSION is unavailable.\n"
    );
    exit(1);
}

$temp = sys_get_temp_dir()
    . '/barama-rc1-artifact-'
    . bin2hex(random_bytes(6));

if (!mkdir($temp, 0700, true) && !is_dir($temp)) {
    fwrite(
        STDERR,
        "[FAIL] Could not create isolated artifact smoke directory.\n"
    );
    exit(1);
}

$package = $temp
    . '/Barama-Real-Estate-'
    . $sourceVersion
    . '.zip';
$manifest = $temp
    . '/Barama-Real-Estate-'
    . $sourceVersion
    . '.artifact.json';

file_put_contents(
    $package,
    "barama rc candidate artifact smoke\n",
    LOCK_EX
);

$hash = hash_file('sha256', $package);

file_put_contents(
    $package . '.sha256',
    $hash . '  ' . basename($package) . PHP_EOL,
    LOCK_EX
);

$run = static function (
    array $arguments
) use ($tool): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($tool);

    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    $output = [];
    $exitCode = 1;

    exec(
        $command . ' 2>&1',
        $output,
        $exitCode
    );

    return [
        'code' => $exitCode,
        'output' => implode(PHP_EOL, $output),
    ];
};

$result = $run([
    'generate',
    $package,
    '--source-commit=' . $sourceCommit,
    '--manifest=' . $manifest,
]);

$decoded = is_file($manifest)
    ? json_decode(
        (string) file_get_contents($manifest),
        true
    )
    : null;

if (
    $result['code'] === 0
    && is_array($decoded)
    && ($decoded['source_version'] ?? '') === $sourceVersion
    && ($decoded['source_commit'] ?? '') === $sourceCommit
    && ($decoded['package_sha256'] ?? '') === $hash
    && ($decoded['package_filename'] ?? '') === basename($package)
) {
    $pass(
        'Artifact generation binds version, commit, '
        . 'filename and SHA-256.'
    );
} else {
    $fail(
        'Artifact generation did not create '
        . 'the expected binding.'
    );
}

$result = $run([
    'verify',
    $manifest,
    $package,
    '--expect-version=' . $sourceVersion,
    '--expect-commit=' . $sourceCommit,
]);

$result['code'] === 0
    ? $pass(
        'Matching artifact manifest and package verify successfully.'
    )
    : $fail(
        'Matching artifact manifest/package verification failed.'
    );

$result = $run([
    'verify',
    $manifest,
    $package,
    '--expect-version=' . $sourceVersion,
    '--expect-commit=' . $wrongCommit,
]);

$result['code'] !== 0
    ? $pass('Wrong expected source commit is rejected.')
    : $fail('Wrong expected source commit was accepted.');

$originalPackage = (string) file_get_contents($package);

file_put_contents(
    $package,
    $originalPackage . "tampered\n",
    LOCK_EX
);

$result = $run([
    'verify',
    $manifest,
    $package,
]);

$result['code'] !== 0
    ? $pass('Tampered customer package is rejected.')
    : $fail('Tampered customer package was accepted.');

file_put_contents(
    $package,
    $originalPackage,
    LOCK_EX
);

file_put_contents(
    $package . '.sha256',
    $hash . '  ' . basename($package) . PHP_EOL,
    LOCK_EX
);

$manifestData = json_decode(
    (string) file_get_contents($manifest),
    true
);

if (is_array($manifestData)) {
    $manifestData['unexpected'] = 'not allowed';

    file_put_contents(
        $manifest,
        json_encode(
            $manifestData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL,
        LOCK_EX
    );
}

$result = $run([
    'verify',
    $manifest,
    $package,
]);

$result['code'] !== 0
    ? $pass('Unknown manifest schema keys are rejected.')
    : $fail('Unknown manifest schema key was accepted.');

@unlink($manifest);
@unlink($package . '.sha256');
@unlink($package);
@rmdir($temp);

fwrite(
    STDOUT,
    sprintf(
        PHP_EOL
        . 'Barama RC candidate artifact smoke: '
        . '%d check(s), %d failure(s).'
        . PHP_EOL,
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
