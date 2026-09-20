<?php

declare(strict_types=1);

use Barama\Core\Application;
use Barama\Core\Router;

require_once __DIR__ . '/runtime.php';

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

    @error_log(
        $message,
        3,
        $logDirectory . '/app.log'
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if ((bool) config('app.debug', false)) {
        echo '<h1>Application Error</h1><pre>'
            . e($exception->getMessage())
            . '</pre>';

        return;
    }

    echo '<h1>Something went wrong</h1>'
        . '<p>Please try again later.</p>';
});

$router = new Router();

require APP_PATH . '/routes/web.php';

return new Application($router);
