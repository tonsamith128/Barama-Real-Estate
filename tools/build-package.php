<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/installer-asset-contract.php';

$installerSource = @file_get_contents(
    $root . '/application/app/Services/InstallerService.php'
);
if (!is_string($installerSource)) {
    throw new RuntimeException('InstallerService source is missing.');
}
baramaAssertInstallerCanonicalAssets($installerSource);

foreach ([
    'application/app/Services/Updates/TrustedReleaseFeed.php',
    'application/app/Services/Updates/SignedReleaseInspector.php',
    'application/app/Services/Updates/PrivateArtifactHttpClientV2.php',
    'application/app/Services/Updates/PrivateArtifactReceiverV2.php',
    'application/app/Services/Updates/PortableUpdateArchiveV1.php',
    'application/app/Services/Updates/PortableUpdateBackupV1.php',
    'application/app/Services/Updates/PortableUpdateFileTransactionV1.php',
    'application/app/Services/Updates/PortableUpdateDownloadV1.php',
    'application/app/Services/Updates/PortableCustomerUpdateV1.php',
    'application/config/update-publisher-v2-keys.json',
    'application/config/update-feed-v2.json',
    'assets/js/barama-portable-auto-updater-v1.js',
    'application/app/Http/Controllers/SystemCenterController.php',
    'application/app/Services/DemoCustomerReleasePolicyV1.php',
] as $updateComponent) {
    if (!is_file($root . '/' . $updateComponent)) {
        throw new RuntimeException('V2 updater component missing from customer source: ' . $updateComponent);
    }
}
// Do not distribute a customer updater with missing, substituted or test
// publisher trust. These are PUBLIC signing keys, never private signing keys.
$catalog = json_decode(
    (string) file_get_contents($root.'/application/config/update-publisher-v2-keys.json'),
    true, 12, JSON_THROW_ON_ERROR
);
$feedConfig = json_decode(
    (string) file_get_contents($root.'/application/config/update-feed-v2.json'),
    true, 8, JSON_THROW_ON_ERROR
);
$expectedFingerprints = [
    'barama-prod-2026-10' => ['retired', 'a79735b477a56e942330277d4988f4ab3359705ee6aaa0ddf1a0e822f4d5bfdf'],
    'barama-prod-2026-10-r2' => ['active', 'e8e345dfa2af3f6746afb881d2e67a896da68c64db9839ca35bd528e99c97148'],
];
if (!is_array($catalog) || ($catalog['format']??null)!=='tonsamith-publisher-keys-v2'
    || !is_array($catalog['keys']??null)
    || array_keys($catalog['keys'])!==array_keys($expectedFingerprints)
    || $feedConfig!==[
        'enabled'=>true,'contract'=>'tonsamith-signed-discovery-v2','channel'=>'stable'
    ]) {
    throw new RuntimeException('Customer V2 publisher trust configuration is invalid.');
}
foreach ($expectedFingerprints as $id=>[$state,$fingerprint]) {
    $entry=$catalog['keys'][$id]??null;
    $decoded=is_array($entry) ? base64_decode((string)($entry['public']??''),true) : false;
    if (($entry['status']??null)!==$state || !is_string($decoded)
        || strlen($decoded)!==32
        || !hash_equals($fingerprint,hash('sha256',$decoded))) {
        throw new RuntimeException('Customer V2 publisher public key fingerprint mismatch.');
    }
}

$version = trim((string) file_get_contents($root . '/VERSION'));

if ($version === '' || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('Package build requirements are unavailable.');
}

$output = $argv[1] ?? ($root . '/build/Barama-Real-Estate-' . $version . '.zip');

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}

if (is_file($output)) {
    unlink($output);
}

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Customer package could not be created.');
}

foreach ([
    '.htaccess',
    'index.php',
    'LICENSE.md',
    'THIRD_PARTY_NOTICES.md',
    'CUSTOMER_PACKAGE.md',
    'CHANGELOG.md',
    'README.md',
    'VERSION',
] as $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path) || !$zip->addFile($path, $relative)) {
        throw new RuntimeException('Required package file is missing: ' . $relative);
    }
}

$allowedStorage = [
    'application/storage/cache/.gitignore' => true,
    'application/storage/logs/.gitignore' => true,
    'application/storage/uploads/.gitignore' => true,
    'application/storage/uploads/.htaccess' => true,
];

foreach (['installer', 'updater', 'application', 'assets', 'documentation'] as $relativeDirectory) {
    $directory = $root . '/' . $relativeDirectory;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $zip->addEmptyDir($relativeDirectory);

    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

        if (
            str_starts_with($relative, 'application/storage/')
            && !isset($allowedStorage[$relative])
        ) {
            continue;
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } elseif (!$zip->addFile($item->getPathname(), $relative)) {
            throw new RuntimeException('Package file could not be added: ' . $relative);
        }
    }
}

$zip->addEmptyDir('tools');
$zip->addFile($root . '/tools/notifications.php', 'tools/notifications.php');

foreach ([
    'application/storage/app',
    'application/storage/app/installation',
    'application/storage/app/license',
    'application/storage/framework',
    'application/storage/framework/cache',
    'application/storage/uploads/private',
] as $directory) {
    $zip->addEmptyDir($directory);
}

$zip->setArchiveComment('Barama Real Estate ' . $version . ' customer package.');

if (!$zip->close()) {
    throw new RuntimeException('Customer package could not be finalized.');
}

$hash = hash_file('sha256', $output);

if (!is_string($hash)) {
    throw new RuntimeException('Customer package checksum could not be generated.');
}

file_put_contents($output . '.sha256', $hash . '  ' . basename($output) . PHP_EOL, LOCK_EX);

fwrite(STDOUT, '[PASS] Customer package built: ' . $output . PHP_EOL);
fwrite(STDOUT, '[PASS] SHA256: ' . $hash . PHP_EOL);
