<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, "[PASS] {$message}\n");
};

$fail = static function (string $message) use (&$checks, &$failures): void {
    $checks++;
    $failures[] = $message;
    fwrite(STDERR, "[FAIL] {$message}\n");
};

$required = [
    'index.php',
    'VERSION',
    'application/bootstrap/app.php',
    'application/routes/web.php',
    'application/app/Core/Application.php',
    'application/app/Core/Router.php',
    'application/app/Core/Request.php',
    'application/app/Core/Response.php',
    'application/app/Core/View.php',
    'assets/css/app.css',
    'assets/js/app.js',
];

foreach ($required as $relative) {
    $file = $root . '/' . $relative;
    is_file($file)
        ? $pass("Required file exists: {$relative}")
        : $fail("Missing required file: {$relative}");
}

$collectFiles = static function (string $directory, string $extension) use ($root): array {
    if (!is_dir($directory)) {
        return [];
    }

    $result = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        if (strtolower($item->getExtension()) !== strtolower($extension)) {
            continue;
        }

        $result[] = str_replace($root . '/', '', $item->getPathname());
    }

    sort($result);
    return $result;
};

$cssFiles = $collectFiles($root . '/assets', 'css');
$jsFiles = $collectFiles($root . '/assets', 'js');

$cssFiles === ['assets/css/app.css']
    ? $pass('Exactly one first-party CSS file is present.')
    : $fail('First-party CSS must remain canonical: assets/css/app.css only.');

$jsFiles === ['assets/js/app.js']
    ? $pass('Exactly one first-party JavaScript file is present.')
    : $fail('First-party JavaScript must remain canonical: assets/js/app.js only.');

$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $item) {
    if (!$item->isFile()) {
        continue;
    }

    $relative = str_replace($root . '/', '', $item->getPathname());

    if (str_starts_with($relative, '.git/')) {
        continue;
    }

    $allFiles[] = $relative;
}

$forbiddenNamePattern = '/(?:^|[\/._-])(old|backup|bak|copy|legacy)(?:[\/._-]|$)/i';
$forbidden = array_values(array_filter(
    $allFiles,
    static fn (string $file): bool => preg_match($forbiddenNamePattern, $file) === 1
));

$forbidden === []
    ? $pass('No old/backup/copy/legacy source filenames detected.')
    : $fail('Forbidden duplicate/legacy filenames detected: ' . implode(', ', $forbidden));

$phpFiles = array_values(array_filter(
    $allFiles,
    static fn (string $file): bool => str_ends_with(strtolower($file), '.php')
));

sort($phpFiles);

if (!function_exists('exec')) {
    $fail('PHP exec() is unavailable; syntax lint could not run.');
} else {
    foreach ($phpFiles as $relative) {
        $command = escapeshellarg(PHP_BINARY)
            . ' -l '
            . escapeshellarg($root . '/' . $relative)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        $exitCode === 0
            ? $pass("PHP syntax: {$relative}")
            : $fail("PHP syntax error in {$relative}: " . implode(' ', $output));
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nBarama QA: %d checks, %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
