<?php

declare(strict_types=1);

use Barama\Core\Application;
use Barama\Core\Config;
use Barama\Core\Router;

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/application');

$versionFile = BASE_PATH . '/VERSION';
define(
    'APP_VERSION',
    is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '0.0.0'
);

require APP_PATH . '/app/Support/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Barama\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Config::load(APP_PATH . '/config');
date_default_timezone_set((string) config('app.timezone', 'Asia/Phnom_Penh'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name((string) config('app.session_name', 'barama_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

set_exception_handler(static function (Throwable $exception): void {
    $logDirectory = APP_PATH . '/storage/logs';

    if (!is_dir($logDirectory)) {
        @mkdir($logDirectory, 0775, true);
    }

    $message = sprintf(
        "[%s] %s in %s:%d\n%s\n\n",
        date('c'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    @error_log($message, 3, $logDirectory . '/app.log');

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if ((bool) config('app.debug', false)) {
        echo '<h1>Application Error</h1><pre>' . e($exception->getMessage()) . '</pre>';
        return;
    }

    echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
});

$router = new Router();

require APP_PATH . '/routes/web.php';

return new Application($router);
