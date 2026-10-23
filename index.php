<?php

declare(strict_types=1);



/* BARAMA_CHANGE_CONFIRMATION_PAGE_V100_BOOTSTRAP */
$baramaChangeConfirmationSupport = __DIR__ . '/application/support/BaramaChangeConfirmationPage.php';
if (!is_file($baramaChangeConfirmationSupport)) {
    $baramaChangeConfirmationSupport = dirname(__DIR__) . '/application/support/BaramaChangeConfirmationPage.php';
}
if (is_file($baramaChangeConfirmationSupport)) {
    require_once $baramaChangeConfirmationSupport;
    if (function_exists('barama_change_confirmation_page_buffer')) {
        ob_start('barama_change_confirmation_page_buffer');
    }
}
/* /BARAMA_CHANGE_CONFIRMATION_PAGE_V100_BOOTSTRAP */

/* BARAMA_SESSION_EXPIRED_PAGE_V100_BOOTSTRAP */
$baramaSessionExpiredSupport = __DIR__ . '/application/support/BaramaSessionExpiredPage.php';
if (is_file($baramaSessionExpiredSupport)) {
    require_once $baramaSessionExpiredSupport;
    if (function_exists('barama_session_expired_page_buffer')) {
        ob_start('barama_session_expired_page_buffer');
    }
}
/* /BARAMA_SESSION_EXPIRED_PAGE_V100_BOOTSTRAP */


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
    // BARAMA_PORTABLE_PENDING_BOOTSTRAP_V1
    // File-only installs update VERSION before their independent new-boot
    // verification. Only the authenticated, CSRF-guarded recovery/finish
    // workflow may bypass the legacy database-upgrade redirect.
    $portablePath = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH
    );
    $portableMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $portableScript = str_replace(
        '\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
    );
    $portableBase = rtrim(dirname($portableScript), '/');
    if ($portableBase === '/' || $portableBase === '.') {
        $portableBase = '';
    }
    $portableAllowed = [
        'GET' => [
            '/login',
            '/dashboard/system/auto-updater',
            '/dashboard/system/auto-updater/portable-status',
        ],
        'POST' => [
            '/login',
            '/dashboard/system/auto-updater/portable-finish',
            '/dashboard/system/auto-updater/portable-recover',
        ],
    ];
    $portablePendingRoute = false;
    if (is_string($portablePath)
        && in_array(
            $portablePath,
            array_map(
                static fn (string $path): string => $portableBase . $path,
                $portableAllowed[$portableMethod] ?? []
            ),
            true
        )
    ) {
        try {
            $portableState =
                \Barama\Services\Updates\PortableCustomerUpdateV1::status();
            $portablePendingRoute =
                ($portableState['state'] ?? '') === 'health_pending'
                && ($portableState['version'] ?? '') === APP_VERSION;
        } catch (\Throwable) {
            $portablePendingRoute = false;
        }
    }
    if (!$portablePendingRoute) {

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
