<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'composer.json',
    'artisan',
    'bootstrap/app.php',
    'public/index.php',
    'routes/web.php',
    'routes/frontend.php',
    'routes/auth.php',
    'routes/admin.php',
    'app/Modules/Frontend/Home/HomeController.php',
    'resources/views/frontend/home/index.blade.php',
    'resources/css/frontend/app.css',
    'resources/css/admin/app.css',
    'tests/Feature/FoundationHomeTest.php',
];
$errors = [];
foreach ($required as $path) {
    if (!is_file($root.'/'.$path)) {
        $errors[] = 'Missing '.$path;
    }
}
foreach (['frontend', 'admin'] as $area) {
    $css = $root.'/resources/css/'.$area.'/app.css';
    if (is_file($css) && !str_contains((string) file_get_contents($css), '/* ===== '.strtoupper($area).' / ENTRY / IMPORTS ===== */')) {
        $errors[] = 'Missing CSS section header in '.$area;
    }
}
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
if (($composer['require']['laravel/framework'] ?? null) !== '^13.0') {
    $errors[] = 'Incorrect Laravel version';
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo "FOUNDATION_STRUCTURE_PASS\n";

