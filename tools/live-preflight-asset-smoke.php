<?php

declare(strict_types=1);

// Isolated negative/positive contract tests for candidate-bound asset checking.
// No public website, user records, credentials, or network service is modified.

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "[FAIL] ZipArchive is required.\n");
    exit(1);
}

$preflight = __DIR__ . '/live-server-preflight.php';
$base = sys_get_temp_dir() . '/barama-assets-' . bin2hex(random_bytes(8));
$root = $base . '/site';
$zipPath = $base . '/candidate.zip';
$files = [
    'VERSION' => "0.9.22-dev\n",
    'index.php' => "<?php // QA fixture\n",
    'assets/css/app.css' => "body { color: #111; }\n",
    'assets/css/home.css' => "main { display: block; }\n",
    'assets/js/app.js' => "console.log('fixture');\n",
    'assets/js/vendor/nested.js' => "console.log('nested');\n",
    'application/app/Http/Middleware/ChangeConfirmationMiddleware.php' => "<?php // security handler fixture\n",
    'application/database/migrations/0026_create_email_outbox_event_history.php' => "<?php // migration fixture\n",
];

$ensure = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$removeTree = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink()
            ? rmdir($entry->getPathname())
            : unlink($entry->getPathname());
    }
    rmdir($directory);
};

$runPreflight = static function () use ($preflight, $root, $zipPath): array {
    $args = [
        PHP_BINARY,
        $preflight,
        '--root=' . $root,
        '--url=https://127.0.0.1:1',
        '--phase=preinstall',
        '--package=' . $zipPath,
        '--json',
    ];
    $output = [];
    $exitCode = 0;
    exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $output, $exitCode);
    $report = json_decode((string) end($output), true);
    if (!is_array($report) || !isset($report['checks']) || !is_array($report['checks'])) {
        throw new RuntimeException('Expected preflight JSON output; CLI exit=' . $exitCode);
    }
    return $report['checks'];
};

$getLevels = static function (array $checks, string $title): array {
    return array_values(array_map(
        static fn (array $c): string => (string) $c['level'],
        array_filter($checks, static fn (array $c): bool => ($c['title'] ?? '') === $title)
    ));
};

$makePath = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create isolated fixture directory.');
    }
    return $path;
};

try {
    $ensure(mkdir($root, 0700, true), 'Could not initialize fixture.');
    $archive = new ZipArchive();
    $ensure($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) === true, 'Cannot create fixture ZIP.');
    foreach ($files as $relative => $contents) {
        file_put_contents($makePath($relative), $contents);
        $ensure($archive->addFromString($relative, $contents), 'Cannot add ZIP entry.');
    }
    $ensure($archive->close(), 'Cannot finalize ZIP.');

    $checks = $runPreflight();
    $ensure($getLevels($checks, 'Exact customer asset binding') === ['pass'], 'Approved nested CSS/JS inventory must pass.');
    $ensure($getLevels($checks, 'Candidate critical source parity') === ['pass', 'pass', 'pass'], 'Matching critical PHP source must pass.');
    echo "[PASS] Exact CSS/JS and critical PHP parity accepted.\n";

    unlink($makePath('assets/css/home.css'));
    $ensure($getLevels($runPreflight(), 'Exact customer asset binding') === ['fail'], 'Missing approved CSS must fail.');
    file_put_contents($makePath('assets/css/home.css'), $files['assets/css/home.css']);
    echo "[PASS] Missing approved CSS rejected.\n";

    file_put_contents($makePath('assets/js/extra.js'), 'not approved');
    $ensure($getLevels($runPreflight(), 'Exact customer asset binding') === ['fail'], 'Extra JS must fail.');
    unlink($makePath('assets/js/extra.js'));
    echo "[PASS] Unexpected JS rejected.\n";

    file_put_contents($makePath('assets/js/vendor/nested.js'), 'changed');
    $ensure($getLevels($runPreflight(), 'Exact customer asset binding') === ['fail'], 'Mutated approved JS must fail.');
    file_put_contents($makePath('assets/js/vendor/nested.js'), $files['assets/js/vendor/nested.js']);
    echo "[PASS] CSS/JS content drift rejected.\n";

    $middleware = 'application/app/Http/Middleware/ChangeConfirmationMiddleware.php';
    file_put_contents($makePath($middleware), "<?php // changed handler\n");
    $ensure(in_array('fail', $getLevels($runPreflight(), 'Candidate critical source parity'), true), 'Changed critical middleware must fail.');
    echo "[PASS] Critical middleware drift rejected.\n";

    echo "LIVE_PREFLIGHT_ASSET_SMOKE=PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] Live-preflight asset smoke: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $removeTree($base);
}
