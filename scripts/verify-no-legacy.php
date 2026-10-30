<?php

declare(strict_types=1);

/**
 * Barama Laravel-only source/package guard.
 *
 * Run against Laravel source root, or an extracted customer ZIP root:
 *   php scripts/verify-no-legacy.php [directory]
 *
 * Does not modify any files.
 */
$root = isset($argv[1]) ? realpath($argv[1]) : dirname(__DIR__);
if (!is_string($root) || !is_dir($root)) {
    fwrite(STDERR, "LEGACY_GUARD_INVALID_ROOT\n");
    exit(2);
}

$errors = [];
$legacyPaths = [
    'application', // Old custom PHP framework, separate from Laravel app/
    'installer',   // Standalone Pure PHP installer; Laravel uses domain services
    'updater',     // Standalone Pure PHP updater
    'assets',      // Old top-level bundled CSS and JS
    'index.php',   // Old root front controller; Laravel uses public/index.php
];

foreach ($legacyPaths as $path) {
    if (file_exists($root.'/'.$path) || is_link($root.'/'.$path)) {
        $errors[] = 'Legacy root path: '.$path;
    }
}

foreach (['app', 'bootstrap', 'routes', 'public'] as $dir) {
    $directory = $root.'/'.$dir;
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            $errors[] = 'Cannot read '.$file->getPathname();
            continue;
        }

        if (str_contains($content, 'namespace Barama\\')) {
            $errors[] = 'Legacy namespace: '.$file->getPathname();
        }

        if (str_contains($content, 'application/bootstrap/runtime.php')
            || str_contains($content, 'Barama\\Core\\Router')
            || str_contains($content, 'BARAMA_PORTABLE_PENDING_BOOTSTRAP_V1')
        ) {
            $errors[] = 'Legacy framework reference: '.$file->getPathname();
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "LEGACY_CODE_FOUND\n".implode("\n", $errors)."\n");
    exit(1);
}

fwrite(STDOUT, "LARAVEL_ONLY_NO_LEGACY_PASS\n");
