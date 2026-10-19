<?php

declare(strict_types=1);

/**
 * R1p13 isolated CI HTTP acceptance.
 * Both application and DB are disposable and unreachable from host ports.
 * Never run against live or archived-production application files.
 */
if (PHP_SAPI !== 'cli' ||
    getenv('BARAMA_R1P13_ISOLATED') !== '1' ||
    getenv('APP_ENV') !== 'testing' ||
    getenv('MAIL_DRIVER') !== 'log') {
    fwrite(STDERR, "STOP=R1P13_HTTP_SANDBOX_GUARD\n");
    exit(1);
}

const BASE = 'http://127.0.0.1:8092';
$jar = sys_get_temp_dir() . '/barama-r1p13-' . bin2hex(random_bytes(10)) . '.cookie';
$passed = 0;

function check(bool $ok, string $name): void
{
    global $passed;
    if (!$ok) {
        fwrite(STDERR, 'STOP=R1P13_HTTP_' . $name . "\n");
        exit(1);
    }
    $passed++;
    echo 'R1P13_' . $name . "=PASS\n";
}

/** @return array{status:int,body:string,location:string} */
function probe(string $path, ?array $post = null): array
{
    global $jar;
    if (!in_array($path, ['/', '/login', '/register', '/dashboard', '/account'], true)) {
        throw new RuntimeException('Unexpected route');
    }
    $ch = curl_init(BASE . $path);
    if ($ch === false) {
        throw new RuntimeException('HTTP client not available');
    }
    $responseHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROXY => '',
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$responseHeaders): int {
            $position = strpos($header, ':');
            if ($position !== false) {
                $responseHeaders[strtolower(trim(substr($header, 0, $position)))] =
                    trim(substr($header, $position + 1));
            }
            return strlen($header);
        },
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post, '', '&', PHP_QUERY_RFC3986));
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => (string) ($responseHeaders['location'] ?? ''),
    ];
}

try {
    $serverReady = false;
    for ($attempt = 0; $attempt < 35; $attempt++) {
        $response = probe('/login');
        if ($response['status'] === 200) {
            $serverReady = true;
            break;
        }
        usleep(200000);
    }
    check($serverReady, 'LOGIN_HTTP_200');

    $home = probe('/');
    check($home['status'] === 200 && str_contains($home['body'], 'Barama'), 'HOME_HTTP_200');

    $register = probe('/register');
    check($register['status'] === 200, 'REGISTER_HTTP_200');

    $unauthorized = probe('/dashboard');
    check(
        in_array($unauthorized['status'], [302, 303], true) &&
        str_contains($unauthorized['location'], '/login'),
        'DASHBOARD_GUEST_REDIRECT'
    );

    $noCsrf = probe('/login', ['email' => 'browser-admin@example.test', 'password' => 'wrong']);
    check($noCsrf['status'] === 419, 'LOGIN_CSRF_REJECTED');

    $login = probe('/login');
    check(
        preg_match('/name="_token"\s+value="([a-f0-9]{64})"/', $login['body'], $tokens) === 1,
        'LOGIN_CSRF_TOKEN_PRESENT'
    );

    $bad = probe('/login', [
        '_token' => $tokens[1],
        'email' => 'browser-admin@example.test',
        'password' => 'invalid-qa-password'
    ]);
    check(in_array($bad['status'], [422, 429], true), 'INVALID_PASSWORD_REJECTED');

    $good = probe('/login', [
        '_token' => $tokens[1],
        'email' => 'browser-admin@example.test',
        'password' => 'Barama-Browser-QA-123!'
    ]);
    check(
        in_array($good['status'], [302, 303], true) &&
        str_contains($good['location'], '/dashboard'),
        'TEST_ADMIN_LOGIN'
    );

    $dashboard = probe('/dashboard');
    check($dashboard['status'] === 200, 'AUTHENTICATED_DASHBOARD_HTTP_200');

    echo "R1P13_ISOLATED_HTTP_AUTH_ACCEPTANCE=PASS\n";
    echo "R1P13_HTTP_CHECKS_PASSED={$passed}\n";
    echo "PRODUCTION_ACCOUNTS_USED=NO\n";
    echo "PRODUCTION_DATABASE_TARGETED=NO\n";
} finally {
    if (file_exists($jar)) {
        @unlink($jar);
    }
}
