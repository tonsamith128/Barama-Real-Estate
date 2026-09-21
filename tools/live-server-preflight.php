<?php

declare(strict_types=1);

/**
 * Read-only live-server preflight for an extracted Barama customer package.
 *
 * Usage:
 * php tools/live-server-preflight.php --root=/absolute/package/root --url=https://example.com
 *
 * This script does not read database, SMTP, license, or administrator credentials
 * and does not modify the target installation.
 */

$options = getopt('', ['root:', 'url:']);
$root = rtrim(trim((string) ($options['root'] ?? '')), DIRECTORY_SEPARATOR);
$baseUrl = rtrim(trim((string) ($options['url'] ?? '')), '/');

if ($root === '' || $baseUrl === '') {
    fwrite(
        STDERR,
        "Usage: php tools/live-server-preflight.php --root=/absolute/package/root --url=https://example.com\n"
    );
    exit(2);
}

if (!is_dir($root)) {
    fwrite(STDERR, "[FAIL] Package root does not exist.\n");
    exit(2);
}

$parts = parse_url($baseUrl);

if (
    !is_array($parts)
    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
    || trim((string) ($parts['host'] ?? '')) === ''
) {
    fwrite(STDERR, "[FAIL] --url must be a valid HTTPS application URL.\n");
    exit(2);
}

$failures = [];
$warnings = [];
$passes = 0;

$pass = static function (string $message) use (&$passes): void {
    $passes++;
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

$warn = static function (string $message) use (&$warnings): void {
    $warnings[] = $message;
    fwrite(STDOUT, '[WARN] ' . $message . PHP_EOL);
};

version_compare(PHP_VERSION, '8.2.0', '>=')
    ? $pass('PHP ' . PHP_VERSION . ' satisfies PHP 8.2+.')
    : $fail('PHP 8.2+ is required; detected ' . PHP_VERSION . '.');

foreach ([
    'pdo' => 'PDO',
    'pdo_mysql' => 'PDO MySQL',
    'json' => 'JSON',
    'mbstring' => 'Mbstring',
    'ctype' => 'Ctype',
    'session' => 'Session',
    'openssl' => 'OpenSSL',
    'fileinfo' => 'Fileinfo',
    'curl' => 'cURL',
] as $extension => $label) {
    extension_loaded($extension)
        ? $pass($label . ' extension is loaded.')
        : $fail($label . ' extension is required.');
}

foreach ([
    'index.php',
    '.htaccess',
    'installer/index.php',
    'updater/index.php',
    'VERSION',
    'application/bootstrap/app.php',
    'application/bootstrap/runtime.php',
    'application/app/Services/LicenseManagerClient.php',
    'application/app/Services/UpgradeService.php',
    'application/routes/web.php',
    'assets/css/app.css',
    'assets/js/app.js',
    'documentation/index.html',
] as $relative) {
    $file = $root . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    is_file($file) && is_readable($file)
        ? $pass('Required package file is readable: ' . $relative)
        : $fail('Required package file is missing/unreadable: ' . $relative);
}

$css = glob($root . '/assets/css/*.css') ?: [];
$js = glob($root . '/assets/js/*.js') ?: [];

count($css) === 1 && basename((string) ($css[0] ?? '')) === 'app.css'
    ? $pass('Canonical stylesheet is assets/css/app.css only.')
    : $fail('First-party stylesheet layout is not canonical.');

count($js) === 1 && basename((string) ($js[0] ?? '')) === 'app.js'
    ? $pass('Canonical JavaScript is assets/js/app.js only.')
    : $fail('First-party JavaScript layout is not canonical.');

foreach ([
    'application/storage',
    'application/storage/cache',
    'application/storage/logs',
    'application/storage/uploads',
    'application/storage/uploads/private',
    'application/storage/app',
    'application/storage/app/installation',
    'application/storage/app/license',
    'application/storage/framework',
    'application/storage/framework/cache',
] as $relative) {
    $directory = $root . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir($directory)) {
        $fail('Runtime directory is missing: ' . $relative);
        continue;
    }

    is_writable($directory)
        ? $pass('Runtime directory is writable: ' . $relative)
        : $fail('Runtime directory is not writable by this PHP process: ' . $relative);
}

if (!extension_loaded('curl')) {
    $fail('HTTP preflight cannot run because cURL is unavailable.');
} else {
    /**
     * @return array{status:int,headers:array<string,list<string>>,error:string}
     */
    $request = static function (string $url): array {
        $headers = [];
        $curl = curl_init($url);

        if ($curl === false) {
            return ['status' => 0, 'headers' => [], 'error' => 'cURL initialization failed.'];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Barama-Live-Server-Preflight',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $length = strlen($line);
                $line = trim($line);

                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }

                [$name, $value] = array_map('trim', explode(':', $line, 2));
                $key = strtolower($name);
                $headers[$key] ??= [];
                $headers[$key][] = $value;

                return $length;
            },
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false && $error === '') {
            $error = 'HTTP request failed.';
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'error' => $error,
        ];
    };

    $headerContains = static function (
        array $response,
        string $name,
        string $needle
    ): bool {
        foreach ($response['headers'][strtolower($name)] ?? [] as $value) {
            if (stripos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    };

    $locationEquals = static function (array $response, string $expected): bool {
        $values = $response['headers']['location'] ?? [];

        return isset($values[0]) && $values[0] === $expected;
    };

    $basePath = (string) ($parts['path'] ?? '');
    $basePath = '/' . trim($basePath, '/');
    $basePath = $basePath === '/' ? '' : $basePath;

    $rootResponse = $request($baseUrl . '/');

    if ($rootResponse['error'] !== '') {
        $fail('HTTPS root request failed: ' . $rootResponse['error']);
    } else {
        $rootResponse['status'] === 302
            ? $pass('Uninstalled root returns HTTP 302.')
            : $fail('Uninstalled root must return HTTP 302; received ' . $rootResponse['status'] . '.');

        $locationEquals($rootResponse, $basePath . '/installer/')
            ? $pass('Root redirects to canonical installer URL.')
            : $fail('Root did not redirect to the canonical installer URL.');

        $headerContains($rootResponse, 'Cache-Control', 'no-store')
            ? $pass('Root installer redirect is no-store.')
            : $fail('Root installer redirect is missing no-store cache protection.');

        $headerContains($rootResponse, 'X-Robots-Tag', 'noindex')
            ? $pass('Root installer redirect is noindex.')
            : $fail('Root installer redirect is missing X-Robots-Tag noindex.');
    }

    $installer = $request($baseUrl . '/installer/');

    if ($installer['error'] !== '') {
        $fail('Installer HTTPS request failed: ' . $installer['error']);
    } else {
        $installer['status'] === 200
            ? $pass('Installer returns HTTP 200.')
            : $fail('Installer must return HTTP 200 before installation; received ' . $installer['status'] . '.');

        foreach ([
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'self'",
        ] as $header => $needle) {
            $headerContains($installer, $header, $needle)
                ? $pass('Installer header verified: ' . $header . '.')
                : $fail('Installer header is missing/invalid: ' . $header . '.');
        }

        $headerContains($installer, 'Set-Cookie', 'HttpOnly')
            ? $pass('Installer session cookie is HttpOnly.')
            : $fail('Installer session cookie is missing HttpOnly.');

        $headerContains($installer, 'Set-Cookie', 'SameSite=Lax')
            ? $pass('Installer session cookie uses SameSite=Lax.')
            : $fail('Installer session cookie is missing SameSite=Lax.');

        $headerContains($installer, 'Set-Cookie', 'Secure')
            ? $pass('Installer session cookie is Secure over HTTPS.')
            : $fail('Installer session cookie is missing Secure over HTTPS.');

        $headerContains($installer, 'Strict-Transport-Security', 'max-age=')
            ? $pass('HTTPS installer sends HSTS.')
            : $warn('HSTS was not observed. Confirm whether it is intentionally managed by the reverse proxy/CDN.');
    }

    $asset = $request($baseUrl . '/assets/css/app.css');

    $asset['error'] === '' && $asset['status'] === 200
        ? $pass('Canonical stylesheet is publicly reachable.')
        : $fail('Canonical stylesheet is not reachable with HTTP 200.');

    $protected = $request($baseUrl . '/application/config/app.php');

    if ($protected['error'] !== '') {
        $fail('Protected-path HTTP request failed: ' . $protected['error']);
    } elseif (in_array($protected['status'], [403, 404], true)) {
        $pass('Protected application source path is not publicly readable.');
    } else {
        $fail(
            'Protected application source path must return 403/404; received '
            . $protected['status']
            . '.'
        );
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\nBarama live-server preflight: %d pass(es), %d warning(s), %d failure(s).\n",
        $passes,
        count($warnings),
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
