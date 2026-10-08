<?php

declare(strict_types=1);

/**
 * Test the real read-only preflight against disposable local HTTP fixtures.
 * No production files, remote websites or database connections are accessed.
 */
if (!function_exists('proc_open') || !extension_loaded('curl')) {
    fwrite(STDERR, "[FAIL] PHP process support and cURL are required.\n");
    exit(1);
}

$root = sys_get_temp_dir() . '/barama-preflight-health-' . bin2hex(random_bytes(8));
$site = $root . '/site';
$router = $root . '/router.php';
$modePath = $root . '/mode.txt';
$process = null;
$pipes = [];

$remove = static function (string $dir): void {
    if (!is_dir($dir)) return;
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        $entry->isDir() && !$entry->isLink()
            ? rmdir($entry->getPathname())
            : unlink($entry->getPathname());
    }
    rmdir($dir);
};

try {
    if (!mkdir($site . '/application/storage/app/installation', 0700, true)) {
        throw new RuntimeException('Fixture directory could not be created.');
    }
    file_put_contents($site . '/VERSION', "0.9.22-dev\n");
    file_put_contents(
        $site . '/application/storage/app/installation/installed.php',
        "<?php return ['version' => '0.9.22-dev'];\n"
    );
    file_put_contents($modePath, 'ok');

    $routerSource = <<<'ROUTER'
<?php
$mode = trim((string) file_get_contents(__DIR__ . '/mode.txt'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($path === '/health') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    if ($mode !== 'html_noindex_missing') {
        header('X-Robots-Tag: noindex');
    }
    echo '<main data-system-health>System Status</main>';
    return true;
}
if ($path === '/health.json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    if ($mode === 'degraded') {
        http_response_code(503);
        echo '{"status":"degraded"}';
    } elseif ($mode === 'metadata_leak') {
        echo '{"status":"ok","version":"0.9.22-dev"}';
    } elseif ($mode === 'malformed') {
        echo '<html>not json</html>';
    } else {
        echo '{"status":"ok"}';
    }
    return true;
}
http_response_code(404);
echo 'Not Found';
return true;
ROUTER;
    file_put_contents($router, $routerSource . PHP_EOL);

    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!is_resource($listener)) {
        throw new RuntimeException('Could not allocate isolated test port.');
    }
    $socket = stream_socket_get_name($listener, false);
    fclose($listener);
    $port = (int) substr((string) $socket, strrpos((string) $socket, ':') + 1);
    if ($port < 1) throw new RuntimeException('Invalid test port.');

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
        [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch isolated PHP test server.');
    }

    $ready = false;
    for ($i = 0; $i < 35; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Isolated HTTP test server did not start.');

    $run = static function (string $mode) use ($modePath, $site, $port): array {
        file_put_contents($modePath, $mode);
        $command = [
            PHP_BINARY,
            __DIR__ . '/live-server-preflight.php',
            '--root=' . $site,
            '--url=http://127.0.0.1:' . $port,
            '--phase=installed',
            '--allow-http',
            '--json',
        ];
        $args = implode(' ', array_map('escapeshellarg', $command));
        $output = [];
        $result = 0;
        exec(
            'BARAMA_LIVE_PREFLIGHT_TEST_MODE=1 ' . $args . ' 2>&1',
            $output,
            $result
        );
        $report = json_decode((string) end($output), true);
        if (!is_array($report) || !is_array($report['checks'] ?? null)) {
            throw new RuntimeException('Preflight JSON not available for ' . $mode);
        }
        $found = [];
        foreach ($report['checks'] as $check) {
            if (in_array($check['title'] ?? '', ['Public system status page', 'Application health'], true)) {
                $found[$check['title']] = $check['level'];
            }
        }
        return $found;
    };

    foreach ([
        'ok' => ['pass', 'pass'],
        'degraded' => ['pass', 'fail'],
        'metadata_leak' => ['pass', 'fail'],
        'malformed' => ['pass', 'fail'],
        'html_noindex_missing' => ['fail', 'pass'],
    ] as $mode => [$pageLevel, $machineLevel]) {
        $actual = $run($mode);
        if (($actual['Public system status page'] ?? '') !== $pageLevel
            || ($actual['Application health'] ?? '') !== $machineLevel) {
            throw new RuntimeException('Health contract mismatch in fixture ' . $mode);
        }
        echo '[PASS] ' . $mode . ' health contract.\n';
    }
    echo "LIVE_PREFLIGHT_HEALTH_SMOKE=PASS\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Live-preflight health smoke: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($process)) {
        @proc_terminate($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) fclose($pipe);
        }
        @proc_close($process);
    }
    $remove($root);
}
