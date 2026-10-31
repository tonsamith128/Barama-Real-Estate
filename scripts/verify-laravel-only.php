<?php

declare(strict_types=1);

/**
 * Laravel 13 source and distribution guard.
 *
 * Run from the repository or against an extracted customer package:
 *     php scripts/verify-laravel-only.php [package-root]
 *
 * The small Installer/ preflight is intentionally retained: it configures
 * Laravel before Composer, credentials and the database are available.
 * It is not an alternative application runtime.
 */
$root = isset($argv[1]) ? realpath($argv[1]) : dirname(__DIR__);
if (!is_string($root) || !is_dir($root)) {
    fwrite(STDERR, "INVALID_LARAVEL_ROOT\n");
    exit(2);
}

$errors = [];
foreach (['composer.json', 'artisan', 'bootstrap/app.php', 'public/index.php', 'routes/web.php'] as $path) {
    if (!is_file($root.'/'.$path)) {
        $errors[] = 'Missing Laravel file: '.$path;
    }
}

$manifest = is_file($root.'/composer.json')
    ? json_decode((string) file_get_contents($root.'/composer.json'), true) : null;
if (!is_array($manifest) || ($manifest['require']['laravel/framework'] ?? null) !== '^13.0') {
    $errors[] = 'Laravel 13 dependency declaration required.';
}

// The current app has no second router, custom application kernel or root index.
foreach (['application', 'assets', 'updater', 'modules', 'index.php', 'bootstrap.php'] as $name) {
    if (file_exists($root.'/'.$name) || is_link($root.'/'.$name)) {
        $errors[] = 'Retired application path: '.$name;
    }
}

$forbiddenTokens = [
    'Barama'.'\\Core\\',
    'application/bootstrap/runtime.php',
    'application/app/Core/',
    'BARAMA_PORTABLE_PENDING_BOOTSTRAP_V1',
];
$scanDirs = ['app', 'bootstrap', 'routes', 'resources', 'public', 'Installer'];
foreach ($scanDirs as $dir) {
    if (!is_dir($root.'/'.$dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) {
            continue;
        }
        $path = substr($entry->getPathname(), strlen($root) + 1);
        if (str_starts_with($path, 'public/build/')) {
            continue; // Vite-generated, hash-named assets are valid.
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['css', 'js'], true)
            && preg_match('/(?:^|[-_])v\d+(?:[._-]\d+)*\.(?:css|js)$/i', basename($path))) {
            $errors[] = 'Version-suffixed source asset: '.$path;
        }
        if (!in_array($extension, ['php', 'css', 'js', 'blade'], true)) {
            continue;
        }
        $source = file_get_contents($entry->getPathname());
        if ($source === false) {
            $errors[] = 'Unreadable source: '.$path;
            continue;
        }
        foreach ($forbiddenTokens as $token) {
            if (str_contains($source, $token)) {
                $errors[] = 'Retired framework reference in: '.$path;
                break;
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "LARAVEL_ONLY_REJECTED\n".implode("\n", $errors)."\n");
    exit(1);
}
fwrite(STDOUT, "LARAVEL_13_ONLY_PASS\n");
