<?php

declare(strict_types=1);

use Barama\Core\Config;
use Barama\Core\RuntimeEnvironment;

if (!defined('BARAMA_BOOTSTRAP')) {
    define('BARAMA_BOOTSTRAP', true);
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/application');
}

if (!defined('APP_VERSION')) {
    $versionFile = BASE_PATH . '/VERSION';

    define(
        'APP_VERSION',
        is_file($versionFile)
            ? trim((string) file_get_contents($versionFile))
            : '0.0.0'
    );
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Barama\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/app/'
        . str_replace('\\', '/', $relative)
        . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require_once APP_PATH . '/app/Support/helpers.php';

RuntimeEnvironment::load();
Config::load(APP_PATH . '/config');

date_default_timezone_set(
    (string) config('app.timezone', 'Asia/Phnom_Penh')
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name(
        (string) config('app.session_name', 'barama_session')
    );

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
