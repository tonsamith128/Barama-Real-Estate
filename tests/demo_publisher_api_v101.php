<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/DemoPublisherApiV1.php';

use Barama\Services\DemoPublisherApiV1;

function expectDemo(bool $valid, string $name): void {
    if (!$valid) { fwrite(STDERR, "FAIL=$name\n"); exit(1); }
    echo "PASS=$name\n";
}
function rejectedDemo(callable $call, string $name): void {
    try { $call(); } catch (RuntimeException) {
        expectDemo(true, $name);
        return;
    }
    expectDemo(false, $name);
}
$keyPair = sodium_crypto_sign_keypair();
$public = sodium_crypto_sign_publickey($keyPair);
$secret = sodium_crypto_sign_secretkey($keyPair);
$sha = hash('sha256', 'A verified mock demo ZIP');
$signature = base64_encode(sodium_crypto_sign_detached(hex2bin($sha), $secret));
$fingerprint = hash('sha256', $public);
$keyId = substr($fingerprint, 0, 24);
$meta = [
    'id' => 'DCP-20261024-03A1BCBA',
    'product_slug' => 'barama-real-estate',
    'demo_slug' => 'live',
    'name' => 'Official Barama Real Estate Live Demo',
    'version' => '0.9.24',
    'package_sha256' => $sha,
    'package_signature' => $signature,
    'signature_key_id' => $keyId,
    'signature_algorithm' => 'Ed25519',
    'package_bytes' => 33100000,
    'is_latest' => true,
];
$queries = [];
$transport = static function ($method, $path, $payload, $bearer) use (
    $keyId, $public, $meta, &$queries
): array {
    $queries[] = [$method, $path, $payload, $bearer];
    $body = match (true) {
        $path === '/api/demo-import/v1/public-key' => [
            'success' => true, 'signing' => [
                'algorithm' => 'Ed25519',
                'key_id' => $keyId,
                'public_key' => base64_encode($public),
            ]
        ],
        str_starts_with($path, '/api/demo-import/v1/products/barama-real-estate/demos') =>
            ['success' => true, 'product_slug' => 'barama-real-estate',
                'demos' => [$meta], 'signing' => ['key_id' => $keyId]],
        $path === '/api/demo-import/v1/authorize' => [
            'success' => true, 'package' => $meta,
            'download_token' => str_repeat('A', 43),
            'download_url' => '/api/demo-import/v1/packages/DCP-20261024-03A1BCBA/download?token='
                . str_repeat('A', 43)
        ],
        default => ['success' => false]
    };
    return ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR)];
};
$client = new DemoPublisherApiV1($transport);
$identity = $client->signingIdentity();
expectDemo($identity['key_id'] === $keyId &&
    $identity['fingerprint'] === $fingerprint, 'real_publisher_identity_schema');
$catalog = $client->discover(
    'private-test-activation', 'customer.example.com', str_repeat('a', 32));
expectDemo(count($catalog['packages']) === 1 &&
    $catalog['packages'][0]['id'] === $meta['id'], 'licensed_published_catalog');
expectDemo(str_contains($queries[1][1], 'domain=customer.example.com') &&
    !str_contains($queries[1][1], 'private-test-activation') &&
    $queries[1][3] === 'private-test-activation', 'activation_never_in_url');
$authorized = $client->authorize(
    $meta['id'], 'private-test-activation', 'customer.example.com', str_repeat('a', 32));
expectDemo($authorized['metadata']['sha256'] === $sha &&
    $authorized['download_path'] ===
    '/api/demo-import/v1/packages/' . $meta['id'] . '/download?token=' .
    str_repeat('A', 43), 'official_authorization_token_format');
expectDemo(DemoPublisherApiV1::verifyPublishedSignature(
    $sha, $signature, base64_encode($public), $fingerprint
), 'ed25519_publisher_sha256_raw_digest_verified');
expectDemo(!DemoPublisherApiV1::verifyPublishedSignature(
    str_repeat('1', 64), $signature, base64_encode($public), $fingerprint
), 'modified_archive_rejected');
expectDemo(!DemoPublisherApiV1::verifyPublishedSignature(
    $sha, $signature, base64_encode($public), str_repeat('f', 64)
), 'unapproved_signing_key_rejected');
rejectedDemo(fn() => $client->discover('secret', 'test.com', 'short'), 'incorrect_installation_binding_rejected');
rejectedDemo(fn() => $client->authorize('../escape', 'secret',
    'customer.example.com', str_repeat('a', 32)), 'unexpected_package_path_rejected');
$draftClient = new DemoPublisherApiV1(static function ($method,$path,$payload,$bearer)
    use ($keyId): array {
    return ['status' => 200, 'body' => json_encode([
        'success' => true, 'product_slug' => 'barama-real-estate',
        'demos' => [], 'signing' => ['key_id' => $keyId],
    ], JSON_THROW_ON_ERROR)];
});
expectDemo($draftClient->discover('secret', 'customer.example.com',
    str_repeat('a', 32))['packages'] === [], 'publisher_draft_hidden_not_fabricated');
$badKeyClient = new DemoPublisherApiV1(static function (...$args): array {
    return ['status' => 200, 'body' => json_encode([
        'success' => true, 'signing' => [
            'algorithm' => 'Ed25519', 'key_id' => str_repeat('a', 24),
            'public_key' => base64_encode(str_repeat('b', 32)),
        ]
    ], JSON_THROW_ON_ERROR)];
});
rejectedDemo(fn() => $badKeyClient->signingIdentity(), 'publisher_key_fingerprint_mismatch_denied');
// Regression: GET requests must not inherit a POST body from null CURLOPT_POSTFIELDS.
$apiSource = file_get_contents(dirname(__DIR__) . '/application/app/Services/DemoPublisherApiV1.php');
$httpsSource = is_string($apiSource) ? (explode('private function https(', $apiSource, 2)[1] ?? '') : '';
$postBranch = strpos($httpsSource, "if (\$method === 'POST') {");
$postField = strpos($httpsSource, '$options[CURLOPT_POSTFIELDS] =');
$getBranch = strpos($httpsSource, '$options[CURLOPT_HTTPGET] = true;');
expectDemo($postBranch !== false && $postField !== false && $getBranch !== false &&
    $postBranch < $postField && $postField < $getBranch &&
    !str_contains($httpsSource, 'CURLOPT_POSTFIELDS => $payload === null'),
    'http_get_never_adds_postfields');
echo "DEMO_PUBLISHER_API_CONTRACTS=PASS\n";
