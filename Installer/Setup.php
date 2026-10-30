<?php

declare(strict_types=1);

namespace Barama\Installer;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Standalone clean-install service. No Laravel dependency before finish().
 * Never run against an existing database or existing .env.
 */
final class Setup
{
    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function installed(): bool
    {
        return is_file(self::root().'/storage/app/installed.lock');
    }

    /**
     * Accept either the preferred public/ document root, or an explicitly
     * protected cPanel root bridge. The second mode requires a rewrite-set
     * server variable; merely creating .htaccess is not sufficient.
     */
    public static function documentRootAllowed(array $server): bool
    {
        $root = realpath(self::root());
        $public = realpath(self::root().'/public');
        $documentRoot = realpath((string) ($server['DOCUMENT_ROOT'] ?? ''));
        if ($public === false || $documentRoot === false) {
            return false;
        }

        if ($documentRoot === $public) {
            return true;
        }

        if ($root === false || $documentRoot !== $root) {
            return false;
        }

        $uri = (string) ($server['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || !in_array($path, ['/installer', '/installer/'], true)) {
            return false;
        }

        $config = $root.'/.htaccess';
        if (!is_file($config) ||
            !str_contains((string) file_get_contents($config), '# BARAMA_PROTECTED_ROOT_BRIDGE')) {
            return false;
        }

        foreach ($server as $name => $value) {
            if (preg_match('/^(?:REDIRECT_)*BARAMA_PROTECTED_ROOT_BRIDGE$/D', (string) $name) === 1 &&
                $value === '1') {
                return true;
            }
        }

        return false;
    }

    public static function requirements(): array
    {
        $root = self::root();
        return [
            'PHP 8.3 or newer' => PHP_VERSION_ID >= 80300,
            '64-bit PHP' => PHP_INT_SIZE >= 8,
            'OpenSSL' => extension_loaded('openssl'),
            'PDO / MySQL' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
            'Mbstring' => extension_loaded('mbstring'),
            'Tokenizer' => extension_loaded('tokenizer'),
            'XML' => extension_loaded('xml'),
            'Ctype' => extension_loaded('ctype'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'Laravel bootstrap' => is_file($root.'/bootstrap/app.php') && is_file($root.'/artisan'),
            'Composer dependencies' => is_file($root.'/vendor/autoload.php'),
            'Compiled frontend assets' => is_file($root.'/public/build/manifest.json'),
            'Writable application root' => is_writable($root),
            'Writable storage/app' => is_dir($root.'/storage/app') && is_writable($root.'/storage/app'),
            'Writable storage/framework' => is_dir($root.'/storage/framework') && is_writable($root.'/storage/framework'),
            'Writable bootstrap/cache' => is_dir($root.'/bootstrap/cache') && is_writable($root.'/bootstrap/cache'),
            'No existing .env' => !file_exists($root.'/.env'),
            'No cached config' => !is_file($root.'/bootstrap/cache/config.php'),
            'Not installed' => !self::installed(),
        ];
    }

    public static function requirementsPassed(): bool
    {
        foreach (self::requirements() as $value) {
            if ($value !== true) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a server-side access token. The browser must never receive its value.
     * Retrieve using File Manager: storage/app/installer.token.
     */
    public static function ensureToken(): void
    {
        if (self::installed()) {
            return;
        }
        $path = self::root().'/storage/app/installer.token';
        if (is_file($path)) {
            return;
        }
        if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            throw new RuntimeException('Private storage is not writable.');
        }
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (is_file($path)) {
                return;
            }
            throw new RuntimeException('Cannot create private installer token.');
        }
        chmod($path, 0600);
        try {
            if (fwrite($handle, bin2hex(random_bytes(32)).PHP_EOL) === false) {
                throw new RuntimeException('Cannot write private installer token.');
            }
        } finally {
            fclose($handle);
        }
    }

    public static function verifyToken(string $candidate): bool
    {
        $path = self::root().'/storage/app/installer.token';
        if (self::installed() || !is_file($path) || strlen(trim($candidate)) !== 64) {
            return false;
        }
        $saved = trim((string) file_get_contents($path));
        return strlen($saved) === 64 && hash_equals($saved, trim($candidate));
    }

    public static function validateDatabase(array $db): void
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,255}$/D', (string) ($db['host'] ?? ''))) {
            throw new RuntimeException('Invalid database host.');
        }
        $port = filter_var($db['port'] ?? '', FILTER_VALIDATE_INT);
        if ($port === false || $port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid database port.');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', (string) ($db['database'] ?? ''))) {
            throw new RuntimeException('Invalid database name.');
        }
        if (!is_string($db['username'] ?? null) || trim($db['username']) === '' || strlen($db['username']) > 80) {
            throw new RuntimeException('Invalid database username.');
        }
        if (!is_string($db['password'] ?? null) || strlen($db['password']) > 1024) {
            throw new RuntimeException('Invalid database password.');
        }
    }

    public static function emptyDatabase(array $db): bool
    {
        self::validateDatabase($db);
        $dsn = 'mysql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['database'].';charset=utf8mb4';
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $count = $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        return $count !== false && (int) $count === 0;
    }

    public static function validateApplication(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if ($parts === false || !is_array($parts)) {
            throw new RuntimeException('Invalid application URL.');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if (($scheme !== 'https' && !($isLocal && $scheme === 'http'))
            || $host === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || (isset($parts['user']) || isset($parts['pass']))
            || isset($parts['query']) || isset($parts['fragment'])
            || strlen($url) > 255
        ) {
            throw new RuntimeException('Use an HTTPS application URL (HTTP only for localhost).');
        }
        return $url;
    }

    /**
     * Derive the canonical application URL from the host's configured vhost.
     * Do not trust arbitrary HTTP_HOST values as an application origin.
     */
    public static function detectedApplicationUrl(array $server): string
    {
        $serverName = strtolower(trim((string) ($server['SERVER_NAME'] ?? '')));
        $requestHost = strtolower(trim((string) ($server['HTTP_HOST'] ?? '')));
        if ($serverName === '' || $requestHost === '' ||
            !preg_match('/^[a-z0-9.:-]{1,253}$/D', $requestHost) ||
            !preg_match('/^[a-z0-9.:-]{1,253}$/D', $serverName)) {
            throw new RuntimeException('Cannot identify this website. Check the domain settings in cPanel.');
        }
        $name = preg_replace('/:\d+$/', '', $serverName);
        $host = preg_replace('/:\d+$/', '', $requestHost);
        if ($name !== $host) {
            throw new RuntimeException('The browser domain does not match this hosting account’s configured website.');
        }
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $https = (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off')
            || (int) ($server['SERVER_PORT'] ?? 0) === 443;
        if (!$https && !$local) {
            throw new RuntimeException('HTTPS must be enabled to install this application.');
        }
        $port = str_contains($requestHost, ':') && !str_starts_with($requestHost, '[')
            ? ':' . substr(strrchr($requestHost, ':'), 1) : '';
        if (!$local && !in_array($port, ['', ':443'], true)) {
            throw new RuntimeException('The production URL must use standard HTTPS.');
        }
        return self::validateApplication(($https ? 'https' : 'http').'://'.$host.($local ? $port : ''));
    }

    public static function validateAdministrator(array $admin): void
    {
        if (!is_string($admin['name'] ?? null) || strlen(trim($admin['name'])) < 2
            || mb_strlen(trim($admin['name'])) > 100) {
            throw new RuntimeException('Administrator name must be 2–100 characters.');
        }
        if (!is_string($admin['email'] ?? null)
            || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)
            || strlen($admin['email']) > 190) {
            throw new RuntimeException('Enter a valid administrator email address.');
        }
        $password = $admin['password'] ?? null;
        if (!is_string($password) || strlen($password) < 12 || strlen($password) > 128
            || !hash_equals($password, (string) ($admin['password_confirmation'] ?? ''))) {
            throw new RuntimeException('Password must be 12–128 characters and match confirmation.');
        }
    }

    private static function envValue(string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            throw new RuntimeException('Environment value contains invalid characters.');
        }
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    public static function environment(array $db, string $url, string $key): string
    {
        self::validateDatabase($db);
        $url = self::validateApplication($url);
        $values = [
            'APP_NAME' => 'Barama Real Estate',
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:'.$key,
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'APP_LOCALE' => 'en',
            'APP_FALLBACK_LOCALE' => 'en',
            'APP_TIMEZONE' => 'Asia/Phnom_Penh',
            'LOG_CHANNEL' => 'stack',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => (string) $db['database'],
            'DB_USERNAME' => (string) $db['username'],
            'DB_PASSWORD' => (string) $db['password'],
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
        ];
        $contents = '';
        foreach ($values as $name => $value) {
            $contents .= $name.'='.self::envValue($value)."\n";
        }
        return $contents;
    }

    public static function finish(array $db, string $url, array $admin): void
    {
        self::validateAdministrator($admin);
        self::validateDatabase($db);
        $url = self::validateApplication($url);
        $root = self::root();
        $guard = @fopen($root.'/storage/app/installer.mutex', 'c');
        if ($guard === false || !flock($guard, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('An installation is already in progress.');
        }
        try {
            if (!self::requirementsPassed()) {
                throw new RuntimeException('Preflight no longer passes; installation was cancelled.');
            }
            if (!self::emptyDatabase($db)) {
                throw new RuntimeException('Database is not empty; use a new dedicated database.');
            }

            // No plaintext administrator password is written to the session or disk.
            $key = base64_encode(random_bytes(32));
            $content = self::environment($db, $url, $key);
            $tmp = $root.'/.env.install-'.bin2hex(random_bytes(8));
            $handle = @fopen($tmp, 'x');
            if ($handle === false) {
                throw new RuntimeException('Unable to create temporary environment file.');
            }
            chmod($tmp, 0600);
            try {
                if (fwrite($handle, $content) !== strlen($content)) {
                    throw new RuntimeException('Unable to write environment configuration.');
                }
            } finally {
                fclose($handle);
            }
            if (file_exists($root.'/.env') || !rename($tmp, $root.'/.env')) {
                @unlink($tmp);
                throw new RuntimeException('Unable to activate environment configuration.');
            }

            // Laravel is booted only after .env has been created.
            require_once $root.'/vendor/autoload.php';
            $app = require $root.'/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            if ($kernel->call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException('Database migrations did not succeed.');
            }

            $user = new \App\Models\User();
            $user->name = trim($admin['name']);
            $user->email = strtolower(trim($admin['email']));
            $user->password = \Illuminate\Support\Facades\Hash::make($admin['password']);
            $user->email_verified_at = now();
            $user->system_role = 'super_admin';
            $user->save();

            if (!$user->exists || !$user->isSystemAdministrator()) {
                throw new RuntimeException('Administrator account validation failed.');
            }

            $lock = $root.'/storage/app/installed.lock';
            $metadata = json_encode([
                'product' => 'Barama Real Estate',
                'installed_at' => gmdate('c'),
                'schema' => 'laravel-13-clean-install',
            ], JSON_THROW_ON_ERROR)."\n";
            $lockHandle = @fopen($lock, 'x');
            if ($lockHandle === false) {
                throw new RuntimeException('Installation lock could not be created.');
            }
            chmod($lock, 0600);
            try {
                if (fwrite($lockHandle, $metadata) !== strlen($metadata)) {
                    throw new RuntimeException('Installation lock write failed.');
                }
            } finally {
                fclose($lockHandle);
            }
            @unlink($root.'/storage/app/installer.token');
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }
}
