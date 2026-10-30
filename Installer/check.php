<?php
declare(strict_types=1);

/**
 * Standalone Barama installer readiness gate.
 * Does not load Laravel, require Composer, execute migrations, or modify files.
 * CLI: php Installer/check.php
 * Browser: /Installer/check.php (only if the webroot exposes this directory).
 */
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$root = dirname(__DIR__);
$checks = [
    'PHP 8.3 or newer' => PHP_VERSION_ID >= 80300,
    '64-bit PHP' => PHP_INT_SIZE >= 8,
    'OpenSSL' => extension_loaded('openssl'),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Mbstring' => extension_loaded('mbstring'),
    'Tokenizer' => extension_loaded('tokenizer'),
    'XML' => extension_loaded('xml'),
    'Ctype' => extension_loaded('ctype'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'Composer vendor/autoload.php' => is_file($root.'/vendor/autoload.php'),
    'Laravel bootstrap' => is_file($root.'/bootstrap/app.php'),
    'Laravel artisan' => is_file($root.'/artisan'),
    'Writable storage' => is_dir($root.'/storage') && is_writable($root.'/storage'),
    'Writable bootstrap/cache' => is_dir($root.'/bootstrap/cache') && is_writable($root.'/bootstrap/cache'),
    'Compiled Vite assets' => is_file($root.'/public/build/manifest.json'),
    'Fresh installation only' => !is_file($root.'/.env'),
];
$failed = false;
echo "Barama Laravel 13 installer preflight\n";
echo "PHP: ".PHP_VERSION."\n";
foreach ($checks as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ').$name."\n";
    $failed = $failed || !$ok;
}
echo $failed
    ? "NOT INSTALLABLE: resolve failing checks; the interactive installer is not implemented.\n"
    : "PREFLIGHT PASS ONLY: the interactive installer and installation tests remain pending.\n";
if (PHP_SAPI === 'cli') {
    exit($failed ? 1 : 0);
}
http_response_code($failed ? 503 : 200);
