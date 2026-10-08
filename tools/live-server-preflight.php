<?php

declare(strict_types=1);

/**
 * Read-only live-server preflight for an extracted Barama customer package.
 *
 * Pre-install:
 * php tools/live-server-preflight.php --root=/absolute/package/root --url=https://example.com
 *
 * Post-install:
 * php tools/live-server-preflight.php --root=/absolute/package/root --url=https://example.com --phase=installed
 *
 * Add --json for a machine-readable, non-secret report.
 */

$options = getopt('', ['root:', 'url:', 'phase::', 'package::', 'json', 'allow-http']);
$root = rtrim(trim((string) ($options['root'] ?? '')), DIRECTORY_SEPARATOR);
$packagePath = trim((string) ($options['package'] ?? ''));
$baseUrl = rtrim(trim((string) ($options['url'] ?? '')), '/');
$phase = strtolower(trim((string) ($options['phase'] ?? 'preinstall')));
$jsonOutput = array_key_exists('json', $options);
$testMode = getenv('BARAMA_LIVE_PREFLIGHT_TEST_MODE') === '1';
$allowHttp = $testMode && array_key_exists('allow-http', $options);

if ($root === '' || $baseUrl === '') {
    fwrite(STDERR, "Usage: php tools/live-server-preflight.php --root=/absolute/package/root --url=https://example.com [--phase=preinstall|installed] [--package=/exact/customer.zip] [--json]\n");
    exit(2);
}

if (!in_array($phase, ['preinstall', 'installed'], true)) {
    fwrite(STDERR, "[FAIL] --phase must be preinstall or installed.\n");
    exit(2);
}

if (!is_dir($root)) {
    fwrite(STDERR, "[FAIL] Package root does not exist.\n");
    exit(2);
}

$parts = parse_url($baseUrl);
$scheme = strtolower((string) ($parts['scheme'] ?? ''));

if (
    !is_array($parts)
    || (!($scheme === 'https' || ($allowHttp && $scheme === 'http')))
    || trim((string) ($parts['host'] ?? '')) === ''
) {
    fwrite(STDERR, "[FAIL] --url must be a valid HTTPS application URL.\n");
    exit(2);
}

$checks = [];
$failures = [];
$warnings = [];
$passes = 0;

$record = static function (string $level, string $title, string $detail = '') use (&$checks, &$failures, &$warnings, &$passes, $jsonOutput): void {
    $checks[] = ['level' => $level, 'title' => $title, 'detail' => $detail];

    if ($level === 'pass') {
        $passes++;
    } elseif ($level === 'warn') {
        $warnings[] = $title;
    } else {
        $failures[] = $title;
    }

    if (!$jsonOutput) {
        $label = strtoupper($level === 'warn' ? 'WARN' : $level);
        fwrite($level === 'fail' ? STDERR : STDOUT, '[' . $label . '] ' . $title . ($detail !== '' ? ': ' . $detail : '') . PHP_EOL);
    }
};

$pass = static fn (string $title, string $detail = '') => $record('pass', $title, $detail);
$warn = static fn (string $title, string $detail = '') => $record('warn', $title, $detail);
$fail = static fn (string $title, string $detail = '') => $record('fail', $title, $detail);

version_compare(PHP_VERSION, '8.2.0', '>=')
    ? $pass('PHP runtime', 'PHP ' . PHP_VERSION . ' satisfies PHP 8.2+.')
    : $fail('PHP runtime', 'PHP 8.2+ is required; detected ' . PHP_VERSION . '.');

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
        ? $pass('Required extension', $label . ' is loaded.')
        : $fail('Required extension', $label . ' is required.');
}

$requiredFiles = [
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
];

foreach ($requiredFiles as $relative) {
    $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    is_file($file) && is_readable($file)
        ? $pass('Required package file', $relative)
        : $fail('Required package file missing/unreadable', $relative);
}

$version = trim((string) @file_get_contents($root . '/VERSION'));
$version !== ''
    ? $pass('Package version', $version)
    : $fail('Package version', 'VERSION is empty or unreadable.');

// Exact asset parity must be checked against the cryptographically bound
// candidate, not an obsolete assumption that only app.css and app.js exist.
if ($packagePath === '') {
    $warn(
        'Exact customer asset binding',
        'Pass --package to verify the approved CSS/JS inventory and source files.'
    );
} elseif (!is_file($packagePath) || !is_readable($packagePath)) {
    $fail('Exact customer asset binding', 'Customer package is missing or unreadable.');
} elseif (!class_exists(ZipArchive::class)) {
    $fail('Exact customer asset binding', 'ZipArchive extension is required.');
} else {
    $archive = new ZipArchive();
    $opened = $archive->open($packagePath);

    if ($opened !== true) {
        $fail('Exact customer asset binding', 'Customer package could not be opened.');
    } else {
        $expectedAssets = [];
        $archiveInvalid = false;

        for ($i = 0; $i < $archive->numFiles; $i++) {
            $name = $archive->getNameIndex($i);
            if (!is_string($name) || !preg_match('~^assets/.+\\.(?:css|js)$~D', $name)) {
                continue;
            }

            if (str_contains($name, '..') || str_contains($name, '\\') || isset($expectedAssets[$name])) {
                $archiveInvalid = true;
                break;
            }

            $contents = $archive->getFromIndex($i);
            if (!is_string($contents)) {
                $archiveInvalid = true;
                break;
            }
            $expectedAssets[$name] = hash('sha256', $contents);
        }

        if ($archiveInvalid || !isset($expectedAssets['assets/css/app.css'], $expectedAssets['assets/js/app.js'])) {
            $fail('Exact customer asset binding', 'Candidate has invalid or missing canonical asset members.');
        } else {
            $actualAssets = [];
            $assetRoot = $root . '/assets';

            if (is_dir($assetRoot)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($assetRoot, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $asset) {
                    if (!$asset->isFile() && !$asset->isLink()) {
                        continue;
                    }
                    $relative = 'assets/' . str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        substr($asset->getPathname(), strlen($assetRoot) + 1)
                    );
                    if (preg_match('~\\.(?:css|js)$~D', $relative)) {
                        $actualAssets[$relative] = $asset->getPathname();
                    }
                }
            }

            $missing = array_diff_key($expectedAssets, $actualAssets);
            $extra = array_diff_key($actualAssets, $expectedAssets);
            $drift = 0;
            foreach (array_intersect_key($actualAssets, $expectedAssets) as $relative => $path) {
                if (is_link($path) || !is_readable($path)) {
                    $drift++;
                    continue;
                }
                $hash = hash_file('sha256', $path);
                if (!is_string($hash) || !hash_equals($expectedAssets[$relative], $hash)) {
                    $drift++;
                }
            }

            if ($missing === [] && $extra === [] && $drift === 0) {
                $cssCount = count(array_filter(array_keys($expectedAssets), static fn (string $name): bool => str_ends_with($name, '.css')));
                $jsCount = count($expectedAssets) - $cssCount;
                $pass('Exact customer asset binding', $cssCount . ' CSS and ' . $jsCount . ' JS assets match the bound ZIP.');
            } else {
                $fail(
                    'Exact customer asset binding',
                    count($missing) . ' missing, ' . count($extra) . ' unexpected, '
                    . $drift . ' changed or unreadable CSS/JS asset(s).'
                );
            }
        }

        // A valid asset set alone must not approve an earlier installed PHP
        // security handler or migration under the same VERSION string.
        foreach ([
            'index.php',
            'application/app/Http/Middleware/ChangeConfirmationMiddleware.php',
            'application/database/migrations/0026_create_email_outbox_event_history.php',
        ] as $relative) {
            $candidate = $archive->getFromName($relative);
            $live = $root . '/' . $relative;

            if (!is_string($candidate) || !is_file($live) || !is_readable($live)) {
                $fail('Candidate critical source parity', $relative . ' is missing or unreadable.');
                continue;
            }

            $actualHash = hash_file('sha256', $live);
            if (is_string($actualHash) && hash_equals(hash('sha256', $candidate), $actualHash)) {
                $pass('Candidate critical source parity', $relative . ' matches the bound ZIP.');
            } else {
                $fail('Candidate critical source parity', $relative . ' differs from the bound ZIP.');
            }
        }
        $archive->close();
    }
}

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
    $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir($directory)) {
        $fail('Runtime directory missing', $relative);
        continue;
    }

    is_writable($directory)
        ? $pass('Runtime directory writable', $relative)
        : $fail('Runtime directory not writable', $relative);
}

$installedLock = $root . '/application/storage/app/installation/installed.php';

if ($phase === 'preinstall') {
    !is_file($installedLock)
        ? $pass('Installation state', 'No installation lock exists before installation.')
        : $fail('Installation state', 'An installation lock already exists.');
} else {
    if (!is_file($installedLock) || !is_readable($installedLock)) {
        $fail('Installation state', 'Installed phase requires a readable installation lock.');
    } else {
        if (!defined('BARAMA_BOOTSTRAP')) {
            define('BARAMA_BOOTSTRAP', true);
        }

        $metadata = require $installedLock;
        $installedVersion = is_array($metadata)
            ? trim((string) ($metadata['version'] ?? ''))
            : '';

        $installedVersion !== '' && hash_equals($version, $installedVersion)
            ? $pass('Installed version', 'Installation metadata matches package VERSION.')
            : $fail('Installed version', 'Installation metadata does not match package VERSION.');
    }

    foreach ([
        'application/storage/app/installation/environment.php',
        'application/storage/app/license/installation-id.php',
        'application/storage/app/license/license.php',
    ] as $relative) {
        $path = $root . '/' . $relative;

        if (!is_file($path) || !is_readable($path)) {
            $fail('Protected runtime state missing/unreadable', $relative);
            continue;
        }

        $permissions = @fileperms($path);
        if (is_int($permissions) && (($permissions & 0007) === 0)) {
            $pass('Protected runtime permissions', $relative . ' is not world-accessible.');
        } else {
            $warn('Protected runtime permissions', $relative . ' should not be world-accessible.');
        }
    }
}

if (!extension_loaded('curl')) {
    $fail('HTTP preflight', 'cURL is unavailable.');
} else {
    $request = static function (string $url): array {
        $headers = [];
        $curl = curl_init($url);

        if ($curl === false) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'cURL initialization failed.'];
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

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => is_string($body) ? $body : '',
            'error' => $error !== '' ? $error : ($body === false ? 'HTTP request failed.' : ''),
        ];
    };

    $headerContains = static function (array $response, string $name, string $needle): bool {
        foreach ($response['headers'][strtolower($name)] ?? [] as $value) {
            if (stripos($value, $needle) !== false) {
                return true;
            }
        }
        return false;
    };

    $locationMatches = static function (array $response, array $expected): bool {
        $value = (string) (($response['headers']['location'][0] ?? ''));
        if ($value === '') {
            return false;
        }
        foreach ($expected as $candidate) {
            if ($value === $candidate || str_ends_with($value, $candidate)) {
                return true;
            }
        }
        return false;
    };

    $securityHeaders = static function (array $response) use ($headerContains): bool {
        foreach ([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'self'",
        ] as $header => $needle) {
            if (!$headerContains($response, $header, $needle)) {
                return false;
            }
        }
        return true;
    };

    $basePath = '/' . trim((string) ($parts['path'] ?? ''), '/');
    $basePath = $basePath === '/' ? '' : $basePath;

    if ($phase === 'preinstall') {
        $rootResponse = $request($baseUrl . '/');
        if ($rootResponse['error'] !== '') {
            $fail('HTTPS root request', $rootResponse['error']);
        } else {
            $rootResponse['status'] === 302
                ? $pass('Uninstalled root response', 'HTTP 302.')
                : $fail('Uninstalled root response', 'Expected HTTP 302; received ' . $rootResponse['status'] . '.');
            $locationMatches($rootResponse, [$basePath . '/installer/'])
                ? $pass('Installer redirect', 'Root redirects to canonical installer URL.')
                : $fail('Installer redirect', 'Root did not redirect to the canonical installer URL.');
            $headerContains($rootResponse, 'Cache-Control', 'no-store')
                ? $pass('Installer redirect cache protection')
                : $fail('Installer redirect cache protection');
            $headerContains($rootResponse, 'X-Robots-Tag', 'noindex')
                ? $pass('Installer redirect indexing protection')
                : $fail('Installer redirect indexing protection');
        }

        $installer = $request($baseUrl . '/installer/');
        if ($installer['error'] !== '') {
            $fail('Installer request', $installer['error']);
        } else {
            $installer['status'] === 200
                ? $pass('Installer response', 'HTTP 200 before installation.')
                : $fail('Installer response', 'Expected HTTP 200; received ' . $installer['status'] . '.');
            $securityHeaders($installer)
                ? $pass('Installer security headers')
                : $fail('Installer security headers');
            foreach (['HttpOnly', 'SameSite=Lax', 'Secure'] as $cookieFlag) {
                if ($cookieFlag === 'Secure' && $allowHttp) {
                    continue;
                }
                $headerContains($installer, 'Set-Cookie', $cookieFlag)
                    ? $pass('Installer session cookie', $cookieFlag)
                    : $fail('Installer session cookie', 'Missing ' . $cookieFlag . '.');
            }
            $headerContains($installer, 'Cache-Control', 'no-store')
                ? $pass('Installer cache protection')
                : $fail('Installer cache protection');
            $headerContains($installer, 'X-Robots-Tag', 'noindex')
                ? $pass('Installer indexing protection')
                : $fail('Installer indexing protection');
        }
    } else {
        foreach (['/', '/properties', '/projects', '/company', '/login', '/register'] as $path) {
            $response = $request($baseUrl . $path);
            if ($response['error'] !== '') {
                $fail('Installed public route', $path . ': ' . $response['error']);
            } elseif ($response['status'] === 200) {
                $pass('Installed public route', $path . ' returns HTTP 200.');
            } else {
                $fail('Installed public route', $path . ' returned HTTP ' . $response['status'] . '.');
            }
        }

        $login = $request($baseUrl . '/login');
        $securityHeaders($login)
            ? $pass('Installed application security headers')
            : $fail('Installed application security headers');

        foreach (['/account', '/developer', '/dashboard'] as $path) {
            $response = $request($baseUrl . $path);
            if ($response['error'] !== '') {
                $fail('Guest access boundary', $path . ': ' . $response['error']);
                continue;
            }

            if ($response['status'] === 302 && $locationMatches($response, [$basePath . '/login', '/login'])) {
                $pass('Guest access boundary', $path . ' redirects to sign in.');
            } else {
                $fail('Guest access boundary', $path . ' must redirect guests to sign in.');
            }
        }

        $installer = $request($baseUrl . '/installer/');
        if ($installer['status'] === 303 && $locationMatches($installer, [$basePath . '/', '/'])) {
            $pass('Installed installer seal', '/installer/ redirects away after installation.');
        } else {
            $fail('Installed installer seal', 'Expected HTTP 303 away from installer; received ' . $installer['status'] . '.');
        }

        $updater = $request($baseUrl . '/updater/');
        if ($updater['status'] === 200) {
            $pass('Updater availability', '/updater/ returns HTTP 200 for the installed current-version state.');
        } else {
            $fail('Updater availability', 'Expected HTTP 200; received ' . $updater['status'] . '.');
        }
        $headerContains($updater, 'Cache-Control', 'no-store')
            ? $pass('Updater cache protection')
            : $fail('Updater cache protection');
        $headerContains($updater, 'X-Robots-Tag', 'noindex')
            ? $pass('Updater indexing protection')
            : $fail('Updater indexing protection');

        // The public /health route is a human-facing status page.
        // Machine monitors must use /health.json, which intentionally reveals
        // only { "status": "ok" } (or "degraded" with HTTP 503).
        $statusPage = $request($baseUrl . '/health');
        if (
            $statusPage['error'] === ''
            && $statusPage['status'] === 200
            && $headerContains($statusPage, 'Content-Type', 'text/html')
            && $headerContains($statusPage, 'Cache-Control', 'no-store')
            && $headerContains($statusPage, 'X-Robots-Tag', 'noindex')
            && str_contains($statusPage['body'], 'data-system-health')
        ) {
            $pass('Public system status page', '/health serves protected HTML status content.');
        } else {
            $fail('Public system status page', '/health must return non-cacheable, non-indexable HTML status content.');
        }

        $health = $request($baseUrl . '/health.json');
        $healthPayload = json_decode($health['body'], true);
        $healthKeys = is_array($healthPayload) ? array_keys($healthPayload) : [];
        sort($healthKeys);

        if (
            $health['error'] === ''
            && $health['status'] === 200
            && $headerContains($health, 'Content-Type', 'application/json')
            && $headerContains($health, 'Cache-Control', 'no-store')
            && $headerContains($health, 'X-Robots-Tag', 'noindex')
            && is_array($healthPayload)
            && $healthKeys === ['status']
            && ($healthPayload['status'] ?? null) === 'ok'
        ) {
            $pass('Application health', '/health.json reports operational status without internal metadata.');
        } else {
            $fail(
                'Application health',
                '/health.json did not return HTTP 200 with the protected minimal operational status; '
                . 'HTTP status ' . $health['status'] . '.'
            );
        }
    }

    $asset = $request($baseUrl . '/assets/css/app.css');
    $asset['error'] === '' && $asset['status'] === 200
        ? $pass('Canonical stylesheet HTTP reachability')
        : $fail('Canonical stylesheet HTTP reachability', 'Expected HTTP 200.');

    foreach (['/application/config/app.php', '/docs/RC1_CHECKLIST.md', '/.github/workflows/quality.yml'] as $path) {
        $response = $request($baseUrl . $path);
        if ($response['error'] !== '') {
            $fail('Protected path request', $path . ': ' . $response['error']);
        } elseif (in_array($response['status'], [403, 404], true)) {
            $pass('Protected path', $path . ' is not publicly readable.');
        } else {
            $fail('Protected path', $path . ' returned HTTP ' . $response['status'] . '.');
        }
    }

    if ($scheme === 'https') {
        $hstsProbe = $request($baseUrl . ($phase === 'installed' ? '/login' : '/installer/'));
        $headerContains($hstsProbe, 'Strict-Transport-Security', 'max-age=')
            ? $pass('HSTS header')
            : $warn('HSTS header', 'Not observed; confirm whether it is intentionally managed by the reverse proxy/CDN.');
    }
}

$overall = $failures === [] ? ($warnings === [] ? 'ready' : 'ready_with_warnings') : 'failed';
$report = [
    'overall' => $overall,
    'phase' => $phase,
    'version' => $version,
    'url_host' => (string) ($parts['host'] ?? ''),
    'passed' => $passes,
    'warnings' => count($warnings),
    'failed' => count($failures),
    'checks' => $checks,
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
} else {
    fwrite(STDOUT, sprintf("\nBarama live-server preflight (%s): %d pass(es), %d warning(s), %d failure(s).\n", $phase, $passes, count($warnings), count($failures)));
}

exit($failures === [] ? 0 : 1);
