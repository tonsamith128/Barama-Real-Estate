<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$fail = static function (string $message, int $code = 1): never {
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
};

$pass = static function (string $message): void {
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$usage = static function (): void {
    fwrite(
        STDOUT,
        "Usage:\n"
        . "  php tools/rc1-artifact.php generate PACKAGE.zip --source-commit=<40-hex> [--manifest=PATH]\n"
        . "  php tools/rc1-artifact.php verify MANIFEST.json PACKAGE.zip [--expect-version=VERSION] [--expect-commit=<40-hex>]\n"
    );
};

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    $usage();
    exit(0);
}

$action = strtolower(trim((string) ($argv[1] ?? '')));

if (!in_array($action, ['generate', 'verify'], true)) {
    $usage();
    exit(2);
}

$parseOptions = static function (array $arguments): array {
    $options = [];
    $positionals = [];

    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--')) {
            $positionals[] = $argument;
            continue;
        }

        $option = substr($argument, 2);
        [$name, $value] = array_pad(explode('=', $option, 2), 2, '');
        $options[$name] = $value;
    }

    return [$positionals, $options];
};

[$positionals, $options] = $parseOptions(array_slice($argv, 2));

$validCommit = static fn (string $value): bool =>
    preg_match('/^[a-f0-9]{40}$/', strtolower($value)) === 1;

$validVersion = static fn (string $value): bool =>
    preg_match(
        '/^(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)'
        . '(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/',
        $value
    ) === 1;

$expectedManifestKeys = [
    'schema_version',
    'product',
    'source_version',
    'source_commit',
    'package_filename',
    'package_sha256',
    'package_size_bytes',
    'checksum_filename',
    'generated_at',
];

$readManifest = static function (string $path) use ($fail): array {
    if (!is_file($path) || !is_readable($path)) {
        $fail('Artifact manifest is missing or unreadable: ' . $path);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (!is_array($decoded)) {
        $fail('Artifact manifest is not valid JSON.');
    }

    return $decoded;
};

$validateManifest = static function (
    array $manifest
) use ($fail, $validCommit, $validVersion, $expectedManifestKeys): void {
    $keys = array_keys($manifest);
    sort($keys);
    $expected = $expectedManifestKeys;
    sort($expected);

    if ($keys !== $expected) {
        $fail('Artifact manifest keys do not match schema version 1.');
    }

    if (($manifest['schema_version'] ?? null) !== 1) {
        $fail('Artifact manifest schema_version must be 1.');
    }

    if (($manifest['product'] ?? '') !== 'barama-real-estate') {
        $fail('Artifact manifest product must be barama-real-estate.');
    }

    $version = trim((string) ($manifest['source_version'] ?? ''));

    if (!$validVersion($version)) {
        $fail('Artifact manifest source_version is invalid.');
    }

    $commit = strtolower(trim((string) ($manifest['source_commit'] ?? '')));

    if (!$validCommit($commit)) {
        $fail(
            'Artifact manifest source_commit must be a 40-character '
            . 'hexadecimal commit SHA.'
        );
    }

    $filename = trim((string) ($manifest['package_filename'] ?? ''));

    if ($filename !== 'Barama-Real-Estate-' . $version . '.zip') {
        $fail(
            'Artifact manifest package_filename does not match '
            . 'source_version.'
        );
    }

    $hash = strtolower(trim((string) ($manifest['package_sha256'] ?? '')));

    if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        $fail('Artifact manifest package_sha256 is invalid.');
    }

    $size = $manifest['package_size_bytes'] ?? null;

    if (!is_int($size) || $size < 1) {
        $fail(
            'Artifact manifest package_size_bytes must be a positive integer.'
        );
    }

    if (($manifest['checksum_filename'] ?? '') !== $filename . '.sha256') {
        $fail('Artifact manifest checksum_filename is invalid.');
    }

    $generatedAt = trim((string) ($manifest['generated_at'] ?? ''));

    if (
        preg_match(
            '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/',
            $generatedAt
        ) !== 1
        || strtotime($generatedAt) === false
    ) {
        $fail(
            'Artifact manifest generated_at must use UTC ISO-8601 format.'
        );
    }
};

$verifyPackage = static function (
    array $manifest,
    string $packagePath
) use ($fail): void {
    if (!is_file($packagePath) || !is_readable($packagePath)) {
        $fail('Exact customer ZIP is missing or unreadable: ' . $packagePath);
    }

    if (basename($packagePath) !== $manifest['package_filename']) {
        $fail('Package filename does not match the artifact manifest.');
    }

    $size = filesize($packagePath);

    if (!is_int($size) || $size !== $manifest['package_size_bytes']) {
        $fail('Package size does not match the artifact manifest.');
    }

    $hash = hash_file('sha256', $packagePath);

    if (
        !is_string($hash)
        || !hash_equals(
            $manifest['package_sha256'],
            strtolower($hash)
        )
    ) {
        $fail('Package SHA-256 does not match the artifact manifest.');
    }

    $sidecar = $packagePath . '.sha256';

    if (!is_file($sidecar) || !is_readable($sidecar)) {
        $fail('Package SHA-256 sidecar is missing or unreadable.');
    }

    $expectedSidecar = $manifest['package_sha256']
        . '  '
        . $manifest['package_filename'];
    $actualSidecar = trim((string) file_get_contents($sidecar));

    if (!hash_equals($expectedSidecar, $actualSidecar)) {
        $fail(
            'Package SHA-256 sidecar does not match the artifact manifest.'
        );
    }
};

if ($action === 'generate') {
    $packagePath = (string) ($positionals[0] ?? '');
    $sourceCommit = strtolower(
        trim((string) ($options['source-commit'] ?? ''))
    );

    if ($packagePath === '' || $sourceCommit === '') {
        $fail(
            'generate requires PACKAGE.zip and '
            . '--source-commit=<40-hex>.',
            2
        );
    }

    if (!$validCommit($sourceCommit)) {
        $fail(
            '--source-commit must be a 40-character hexadecimal commit SHA.',
            2
        );
    }

    $sourceVersion = trim(
        (string) @file_get_contents($root . '/VERSION')
    );

    if (!$validVersion($sourceVersion)) {
        $fail('Source VERSION is missing or invalid.');
    }

    $expectedFilename = 'Barama-Real-Estate-'
        . $sourceVersion
        . '.zip';

    if (basename($packagePath) !== $expectedFilename) {
        $fail(
            'Package filename must match source VERSION: '
            . $expectedFilename
        );
    }

    if (!is_file($packagePath) || !is_readable($packagePath)) {
        $fail('Exact customer ZIP is missing or unreadable.');
    }

    $hash = hash_file('sha256', $packagePath);
    $size = filesize($packagePath);

    if (!is_string($hash) || !is_int($size) || $size < 1) {
        $fail('Package hash/size could not be calculated.');
    }

    $sidecar = $packagePath . '.sha256';
    $expectedSidecar = strtolower($hash)
        . '  '
        . $expectedFilename;

    if (
        !is_file($sidecar)
        || !hash_equals(
            $expectedSidecar,
            trim((string) file_get_contents($sidecar))
        )
    ) {
        $fail(
            'Package SHA-256 sidecar is missing or does not match '
            . 'the exact ZIP.'
        );
    }

    $manifest = [
        'schema_version' => 1,
        'product' => 'barama-real-estate',
        'source_version' => $sourceVersion,
        'source_commit' => $sourceCommit,
        'package_filename' => $expectedFilename,
        'package_sha256' => strtolower($hash),
        'package_size_bytes' => $size,
        'checksum_filename' => $expectedFilename . '.sha256',
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    $validateManifest($manifest);

    $manifestPath = trim(
        (string) ($options['manifest'] ?? '')
    );

    if ($manifestPath === '') {
        $manifestPath = preg_replace(
            '/\\.zip$/',
            '.artifact.json',
            $packagePath
        ) ?: ($packagePath . '.artifact.json');
    }

    $directory = dirname($manifestPath);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0775, true)
        && !is_dir($directory)
    ) {
        $fail('Artifact manifest directory could not be created.');
    }

    $encoded = json_encode(
        $manifest,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    $temporary = tempnam($directory, '.barama-artifact-');

    if (
        $temporary === false
        || file_put_contents(
            $temporary,
            $encoded,
            LOCK_EX
        ) !== strlen($encoded)
    ) {
        if (is_string($temporary)) {
            @unlink($temporary);
        }

        $fail('Artifact manifest could not be written.');
    }

    if (!@rename($temporary, $manifestPath)) {
        @unlink($temporary);
        $fail('Artifact manifest could not be finalized atomically.');
    }

    $pass(
        'RC candidate artifact manifest generated: '
        . $manifestPath
    );
    $pass('Bound package SHA-256: ' . $manifest['package_sha256']);
    $pass('Bound source commit: ' . $manifest['source_commit']);
    exit(0);
}

$manifestPath = (string) ($positionals[0] ?? '');
$packagePath = (string) ($positionals[1] ?? '');

if ($manifestPath === '' || $packagePath === '') {
    $fail(
        'verify requires MANIFEST.json and PACKAGE.zip.',
        2
    );
}

$manifest = $readManifest($manifestPath);
$validateManifest($manifest);
$verifyPackage($manifest, $packagePath);

$expectVersion = trim(
    (string) ($options['expect-version'] ?? '')
);

if ($expectVersion !== '') {
    if (!$validVersion($expectVersion)) {
        $fail('--expect-version is invalid.', 2);
    }

    if (!hash_equals(
        $expectVersion,
        $manifest['source_version']
    )) {
        $fail(
            'Artifact source_version does not match --expect-version.'
        );
    }
}

$expectCommit = strtolower(
    trim((string) ($options['expect-commit'] ?? ''))
);

if ($expectCommit !== '') {
    if (!$validCommit($expectCommit)) {
        $fail(
            '--expect-commit must be a 40-character hexadecimal commit SHA.',
            2
        );
    }

    if (!hash_equals(
        $expectCommit,
        $manifest['source_commit']
    )) {
        $fail(
            'Artifact source_commit does not match --expect-commit.'
        );
    }
}

$pass('RC candidate artifact binding verified.');
$pass('Package: ' . $manifest['package_filename']);
$pass('SHA-256: ' . $manifest['package_sha256']);
$pass('Source version: ' . $manifest['source_version']);
$pass('Source commit: ' . $manifest['source_commit']);
