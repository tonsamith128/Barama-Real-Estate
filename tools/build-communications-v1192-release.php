<?php

declare(strict_types=1);

use ZipArchive;

$root = dirname(__DIR__);
$manifestPath = $root . '/docs/releases/admin-communications-v1.19.2.json';

$fail = static function (string $message, int $code = 1): never {
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
};

$pass = static function (string $message): void {
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

if (!class_exists(ZipArchive::class)) {
    $fail('ZipArchive is required to build the Communications release bundle.');
}

if (!is_file($manifestPath)) {
    $fail('Release manifest is missing: ' . $manifestPath);
}

$manifest = json_decode(
    (string) file_get_contents($manifestPath),
    true,
    512,
    JSON_THROW_ON_ERROR
);

if (!is_array($manifest)) {
    $fail('Release manifest is invalid.');
}

$releaseName = 'Barama-Communications-Phase7-v1.19.2';
$output = $root . '/build/releases/' . $releaseName . '.zip';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--output=')) {
        $candidate = trim(substr($argument, 9));

        if ($candidate !== '') {
            $output = $candidate;
        }
    }
}

$outputDirectory = dirname($output);

if (
    !is_dir($outputDirectory)
    && !mkdir($outputDirectory, 0775, true)
    && !is_dir($outputDirectory)
) {
    $fail('Release output directory could not be created.');
}

$gitBlobSha = static function (string $contents): string {
    return sha1('blob ' . strlen($contents) . "\0" . $contents);
};

$productionFiles = [];

foreach ((array) ($manifest['production_files'] ?? []) as $entry) {
    if (!is_array($entry)) {
        $fail('Invalid production file entry in release manifest.');
    }

    $relative = (string) ($entry['path'] ?? '');
    $expectedBlob = strtolower(
        trim((string) ($entry['git_blob_sha'] ?? ''))
    );

    if (
        $relative === ''
        || preg_match('/^[a-f0-9]{40}$/', $expectedBlob) !== 1
    ) {
        $fail('Invalid production file binding in release manifest.');
    }

    $source = $root . '/' . $relative;

    if (!is_file($source) || !is_readable($source)) {
        $fail('Release source file is missing: ' . $relative);
    }

    $contents = file_get_contents($source);

    if (!is_string($contents)) {
        $fail('Release source file is unreadable: ' . $relative);
    }

    $actualBlob = $gitBlobSha($contents);

    if (!hash_equals($expectedBlob, $actualBlob)) {
        $fail(
            'Release source drift detected for '
            . $relative
            . '; expected Git blob '
            . $expectedBlob
            . ', got '
            . $actualBlob
        );
    }

    $productionFiles[$relative] = $source;
}

$helpers = [
    'bin/predeploy.php' => $root . '/tools/communications-v1192-predeploy.php',
    'bin/postdeploy.php' => $root . '/tools/communications-v1192-readonly.php',
    'bin/rollback-db.php' => $root . '/tools/communications-v1192-rollback-db.php',
    'bin/deploy.sh' => $root . '/tools/releases/communications-v1192/deploy.sh',
    'bin/rollback.sh' => $root . '/tools/releases/communications-v1192/rollback.sh',
];

foreach ($helpers as $relative => $path) {
    if (!is_file($path) || !is_readable($path)) {
        $fail('Required release helper is missing: ' . $relative);
    }
}

$readme = <<<'TEXT'
Barama Real Estate — Communications Phase 7 v1.19.2
====================================================

Internal release bundle: Announcements & Audience Delivery.

This is not a CodeCanyon customer package.

Target:
- baramarealestate.tonsamith.com
- /www/wwwroot/baramarealestate.tonsamith.com
- /www/server/php/84/bin/php

Accepted base:
- v1.19.1
- main merge commit 650abde669939d1537f35881c090f0b37cef4c81

Production source target:
- 7ac3776e44650070835cb9ad737e5f71ca12b4d2

Deployment:
1. Upload and extract this bundle under /root.
2. Run: bash bin/deploy.sh
3. Paste the complete output back into ChatGPT.
4. Do not manually merge PR #6 before acceptance.

Safety:
- exact accepted-base Git blob drift guard before copying;
- exact target Git blob binding for every production file;
- PHP lint before file deployment;
- additive, seed-free migration 0020 only;
- database READ ONLY post-deploy verification;
- no Nginx change;
- no service restart or reload;
- timestamped exact-file rollback backup;
- rollback refuses to drop announcement tables after real data exists.
TEXT;

$rollbackNotes = <<<'TEXT'
Rollback
========

A successful deployment creates:

/root/barama-real-estate-backups/admin-communications-phase7-v1.19.2-<timestamp>/

Run the rollback.sh inside that directory.

The rollback fails closed if:
- deployed source no longer matches the v1.19.2 target; or
- announcement/delivery tables contain real rows.

This prevents overwriting later source changes or deleting real communications data.
TEXT;

$diskPayload = [];

foreach ($productionFiles as $relative => $path) {
    $diskPayload['payload/' . $relative] = $path;
}

$diskPayload['manifest/admin-communications-v1.19.2.json'] = $manifestPath;

foreach ($helpers as $relative => $path) {
    $diskPayload[$relative] = $path;
}

$generated = [
    'README.txt' => $readme . PHP_EOL,
    'ROLLBACK.txt' => $rollbackNotes . PHP_EOL,
];

$checksumLines = [];

foreach ($diskPayload as $relative => $path) {
    $hash = hash_file('sha256', $path);

    if (!is_string($hash)) {
        $fail('Could not hash release payload: ' . $relative);
    }

    $checksumLines[] = $hash . '  ' . $relative;
}

foreach ($generated as $relative => $contents) {
    $checksumLines[] = hash('sha256', $contents) . '  ' . $relative;
}

sort($checksumLines, SORT_STRING);
$generated['BUNDLE_CONTENTS.sha256'] =
    implode(PHP_EOL, $checksumLines) . PHP_EOL;

if (is_file($output) && !unlink($output)) {
    $fail('Existing release bundle could not be replaced.');
}

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    $fail('Release ZIP could not be created: ' . $output);
}

$prefix = $releaseName . '/';

foreach (['payload/', 'manifest/', 'bin/'] as $directory) {
    $zip->addEmptyDir($prefix . $directory);
}

foreach ($diskPayload as $relative => $path) {
    if (!$zip->addFile($path, $prefix . $relative)) {
        $zip->close();
        @unlink($output);
        $fail('Could not add release payload: ' . $relative);
    }
}

foreach ($generated as $relative => $contents) {
    if (!$zip->addFromString($prefix . $relative, $contents)) {
        $zip->close();
        @unlink($output);
        $fail('Could not add generated release file: ' . $relative);
    }
}

$zip->setArchiveComment(
    'Barama Real Estate Communications Phase 7 v1.19.2 internal release bundle.'
);

if (!$zip->close()) {
    @unlink($output);
    $fail('Release ZIP could not be finalized.');
}

$bundleHash = hash_file('sha256', $output);

if (!is_string($bundleHash)) {
    @unlink($output);
    $fail('Release ZIP SHA-256 could not be calculated.');
}

file_put_contents(
    $output . '.sha256',
    $bundleHash . '  ' . basename($output) . PHP_EOL,
    LOCK_EX
);

$pass('Release bundle built: ' . $output);
$pass('Bundle SHA-256: ' . $bundleHash);
$pass(
    'Manifest-bound production file count: '
    . count($productionFiles)
);
