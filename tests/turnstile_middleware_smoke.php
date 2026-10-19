<?php

declare(strict_types=1);

// Offline tests only: no HTTP server, network calls, production source/runtime.
require dirname(__DIR__) . '/application/app/Core/MiddlewareInterface.php';
require dirname(__DIR__) . '/application/app/Core/Request.php';
require dirname(__DIR__) . '/application/app/Core/Response.php';
require dirname(__DIR__) . '/application/app/Security/TurnstileSettingsStore.php';
require dirname(__DIR__) . '/application/app/Security/TurnstileVerifier.php';
require dirname(__DIR__) . '/application/app/Http/Middleware/TurnstileMiddleware.php';

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Middleware\TurnstileMiddleware;
use Barama\Security\TurnstileSettingsStore;

function requireTest(bool $valid, string $label): void
{
    if (!$valid) {
        fwrite(STDERR, 'FAIL=' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS=' . $label . PHP_EOL;
}

function requestFor(string $path, array $body): Request
{
    $_GET = [];
    $_POST = $body;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    return Request::capture();
}

function statusOf(Response $response): int
{
    return (new ReflectionProperty(Response::class, 'status'))->getValue($response);
}

function contentOf(Response $response): string
{
    return (new ReflectionProperty(Response::class, 'content'))->getValue($response);
}

$base = sys_get_temp_dir() . '/br-turnstile-route-' . bin2hex(random_bytes(8));
requireTest(mkdir($base, 0700), 'private_temp_created');

$path = $base . '/turnstile.json';
$key = base64_encode(random_bytes(32));
$settings = new TurnstileSettingsStore($path, $key);
$next = static fn (Request $req): Response => Response::html('NEXT_OK');

try {
    $middleware = new TurnstileMiddleware('login', $settings, null, 'https://barama.example.test');
    $open = $middleware->handle(requestFor('/login', []), $next);
    requireTest(contentOf($open) === 'NEXT_OK', 'disabled_default_keeps_existing_login');
    $settings->save(true, '0x4AAAA_TestPublic', '0x0_TestPrivateToken', ['login','contact']);

    $noToken = $middleware->handle(requestFor('/login', []), $next);
    requireTest(statusOf($noToken) === 422, 'enabled_no_token_denied');

    $longToken = $middleware->handle(
        requestFor('/login', ['cf-turnstile-response' => str_repeat('t', 2049)]), $next
    );
    requireTest(statusOf($longToken) === 422, 'oversized_token_denied');

    $denied = new TurnstileMiddleware(
        'login', $settings, static fn (): bool => false, 'https://barama.example.test'
    );
    requireTest(
        statusOf($denied->handle(
            requestFor('/login', ['cf-turnstile-response' => 'one-time-token']), $next
        )) === 422, 'failed_siteverify_denied'
    );

    $hit = [];
    $allow = new TurnstileMiddleware(
        'login',
        $settings,
        static function (string $secret, string $token, string $host, string $action, ?string $ip)
            use (&$hit): bool {
            $hit = [$secret, $token, $host, $action, $ip];
            return true;
        },
        'https://barama.example.test'
    );
    $result = $allow->handle(
        requestFor('/login', ['cf-turnstile-response' => 'valid-test-token']), $next
    );
    requireTest(statusOf($result) === 200 && contentOf($result) === 'NEXT_OK',
        'verified_token_reaches_controller');
    requireTest($hit === [
        '0x0_TestPrivateToken', 'valid-test-token', 'barama.example.test',
        'login', '127.0.0.1'
    ], 'expected_secret_hostname_action_bound');

    $otherForm = new TurnstileMiddleware('register', $settings, null, 'https://barama.example.test');
    requireTest(contentOf($otherForm->handle(requestFor('/register', []), $next)) === 'NEXT_OK',
        'unselected_form_is_not_blocked');

    $json = new TurnstileMiddleware('contact', $settings, null, 'https://barama.example.test');
    $response = $json->handle(requestFor('/contact/messages', []), $next);
    $decoded = json_decode(contentOf($response), true);
    requireTest(statusOf($response) === 422 && $decoded['ok'] === false,
        'contact_failure_is_json');

    file_put_contents($path, '{invalid json');
    $unavailable = $middleware->handle(requestFor('/login', []), $next);
    requireTest(statusOf($unavailable) === 503, 'corrupted_encrypted_settings_fail_closed');

    $unknown = false;
    try {
        new TurnstileMiddleware('payment-webhook', $settings);
    } catch (InvalidArgumentException) {
        $unknown = true;
    }
    requireTest($unknown, 'unsafe_unknown_form_rejected');

    echo "TURNSTILE_MIDDLEWARE_OFFLINE_SMOKE=PASS\n";
} finally {
    @unlink($path);
    @rmdir($base);
}
