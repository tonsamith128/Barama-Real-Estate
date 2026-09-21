<?php

declare(strict_types=1);

$root = dirname(__DIR__);
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

foreach (['installer', 'updater', 'application', 'assets'] as $relativeDirectory) {
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
