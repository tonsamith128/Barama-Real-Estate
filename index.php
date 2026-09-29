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

require __DIR__ . '/application/bootstrap/runtime.php';

$installed = Barama\Core\Installation::metadata();
$installedVersion = trim((string) ($installed['version'] ?? ''));

if ($installedVersion !== APP_VERSION) {
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
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Location: ' . $base . '/updater/', true, 302);
    }

    exit;
}

$app = require __DIR__ . '/application/bootstrap/app.php';

/* BARAMA_GLOBAL_404_V114
 * Nginx error_page internally calls index.php with __barama_error=404.
 * Return a 200 body here; Nginx preserves the external 404 status.
 */
if ((string) ($_GET['__barama_error'] ?? '') === '404') {
    $originalPath = (string) (
        parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'
    );

    Barama\Core\Response::html(
        Barama\Core\View::render('errors/404', [
            'pageTitle' => 'Page not found',
            'metaDescription' => 'The requested page could not be found on Barama Real Estate.',
            'metaRobots' => 'noindex,follow',
            'canonicalUrl' => url($originalPath),
            'activeArea' => '',
        ]),
        200
    )->send();

    exit;
}

$app->run();
