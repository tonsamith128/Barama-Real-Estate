<?php

declare(strict_types=1);

define('BARAMA_START', microtime(true));

$lock = __DIR__
    . '/application/storage/app/installation/installed.php';

if (!is_file($lock)) {
    $script = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
    );
    $base = rtrim(dirname($script), '/');

    if ($base === '/' || $base === '.') {
        $base = '';
    }

    if (!headers_sent()) {
        header(
            'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
        );
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Location: ' . $base . '/installer/', true, 302);
    }

    exit;
}

$app = require __DIR__ . '/application/bootstrap/app.php';
$app->run();
