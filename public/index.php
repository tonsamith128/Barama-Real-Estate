<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Pre-application installation entry. Never boot Laravel before the visitor
// has configured a fresh installation; Composer and MySQL are not needed here.
$root = dirname(__DIR__);
$installed = is_file($root.'/storage/app/installed.lock');
$configured = is_file($root.'/.env');
$wizardAvailable = is_file($root.'/Installer/index.php')
    && is_file(__DIR__.'/installer/index.php');

if (!$installed && !$configured) {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if ($wizardAvailable) {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            header('Location: /installer/', true, 302);
            exit;
        }
        http_response_code(503);
        header('Retry-After: 60');
        exit('Application installation is required.');
    }
    http_response_code(503);
    exit('Installer files are missing. Restore the installer before configuring the application.');
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
