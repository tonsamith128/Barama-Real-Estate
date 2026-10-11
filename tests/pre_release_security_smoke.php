<?php

declare(strict_types=1);

/**
 * No network, no production data, and no update installation.
 * Execute with: php tests/pre_release_security_smoke.php
 */
require dirname(__DIR__) . '/application/app/Security/TurnstileVerifier.php';
require dirname(__DIR__) . '/application/app/Services/SignedUpdateManifest.php';

use Barama\Security\TurnstileVerifier;
use Barama\Services\SignedUpdateManifest;

function demand(bool $condition, string $name): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL=' . $name . PHP_EOL);
        exit(1);
    }
    echo 'PASS=' . $name . PHP_EOL;
}

$turnstile = new TurnstileVerifier();
demand(!$turnstile->verify('', 'token', 'example.com', 'login'), 'turnstile_missing_secret_rejected');
demand(!$turnstile->verify('secret', '', 'example.com', 'login'), 'turnstile_missing_token_rejected');
demand(!$turnstile->verify('secret', str_repeat('a', 2049), 'example.com', 'login'), 'turnstile_long_token_rejected');
demand(!$turnstile->verify('secret', 'token', 'not/a/hostname', 'login'), 'turnstile_invalid_host_rejected');
demand(!$turnstile->verify('secret', 'token', 'example.com', 'invalid action'), 'turnstile_invalid_action_rejected');

demand(TurnstileVerifier::acceptsResult([
    'success' => true, 'hostname' => 'baramarealestate.tonsamith.com',
    'action' => 'login'
], 'baramarealestate.tonsamith.com', 'login'), 'turnstile_valid_result_accepted');
demand(!TurnstileVerifier::acceptsResult([
    'success' => false, 'hostname' => 'baramarealestate.tonsamith.com',
    'action' => 'login'
], 'baramarealestate.tonsamith.com', 'login'), 'turnstile_false_success_rejected');
demand(!TurnstileVerifier::acceptsResult([
    'success' => true, 'hostname' => 'attacker.example.com',
    'action' => 'login'
], 'baramarealestate.tonsamith.com', 'login'), 'turnstile_wrong_hostname_rejected');
demand(!TurnstileVerifier::acceptsResult([
    'success' => true, 'hostname' => 'baramarealestate.tonsamith.com',
    'action' => 'register'
], 'baramarealestate.tonsamith.com', 'login'), 'turnstile_wrong_action_rejected');

$key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
demand($key !== false, 'openssl_private_test_key_generated');
$public = openssl_pkey_get_details($key)['key'] ?? '';
demand(is_string($public) && $public !== '', 'openssl_public_test_key_generated');

$path = tempnam(sys_get_temp_dir(), 'barama-test-');
demand(is_string($path), 'test_temp_file_created');
file_put_contents($path, 'test-update-archive');
$bytes = (int) filesize($path);
$sha = hash_file('sha256', $path);
$manifest = json_encode([
    'product' => 'barama-real-estate',
    'version' => '1.0.1',
    'url' => 'https://release.example.test/barama/1.0.1.zip',
    'sha256' => $sha,
    'bytes' => $bytes,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
demand(openssl_sign($manifest, $signature, $key, OPENSSL_ALGO_SHA256), 'test_manifest_signed');

$verifier = new SignedUpdateManifest();
$valid = $verifier->verify(
    $manifest, base64_encode($signature), $public,
    'barama-real-estate', '1.0.0', 'release.example.test'
);
demand(is_array($valid) && $valid['version'] === '1.0.1', 'valid_signature_accepted');
demand($verifier->verify(
    $manifest . ' ', base64_encode($signature), $public,
    'barama-real-estate', '1.0.0', 'release.example.test'
) === null, 'tampered_manifest_rejected');
demand($verifier->verify(
    $manifest, base64_encode($signature), $public,
    'wrong-product', '1.0.0', 'release.example.test'
) === null, 'wrong_product_rejected');
demand($verifier->verify(
    $manifest, base64_encode($signature), $public,
    'barama-real-estate', '1.0.1', 'release.example.test'
) === null, 'non_newer_version_rejected');
demand($verifier->verify(
    $manifest, base64_encode($signature), $public,
    'barama-real-estate', '1.0.0', 'other.example.test'
) === null, 'wrong_download_host_rejected');
demand($verifier->verifyPackage($path, $sha, $bytes), 'package_checksum_accepted');
demand(!$verifier->verifyPackage($path, str_repeat('0', 64), $bytes), 'bad_checksum_rejected');
demand(!$verifier->verifyPackage($path, $sha, $bytes + 1), 'bad_size_rejected');
unlink($path);

require dirname(__DIR__) . '/application/app/Security/TurnstileSettingsStore.php';

$testRoot = sys_get_temp_dir() . '/barama-turnstile-' . bin2hex(random_bytes(8));
demand(mkdir($testRoot, 0700), 'turnstile_test_private_directory');
$testConfig = $testRoot . '/turnstile.json';
$key = base64_encode(random_bytes(32));
$store = new \Barama\Security\TurnstileSettingsStore($testConfig, $key);

demand($store->status()['enabled'] === false, 'turnstile_defaults_disabled');
demand($store->status()['configured'] === false, 'turnstile_defaults_unconfigured');

$testSecret = '0x0_Offline_Secret_For_Encrypted_Testing';
$testSiteKey = '0x4AAAA_Test_Site_Key';
$store->save(false, $testSiteKey, $testSecret, ['login', 'register']);

demand($store->status()['configured'] === true, 'turnstile_encrypted_settings_configured');
demand($store->status()['enabled'] === false, 'turnstile_settings_remain_disabled');
demand($store->read()['secret'] === $testSecret, 'turnstile_secret_decryption');
demand(
    !str_contains((string) file_get_contents($testConfig), $testSecret),
    'turnstile_plaintext_never_stored'
);

$originalSettings = (string) file_get_contents($testConfig);
$tamperedSettings = str_replace('"enabled":false', '"enabled":true', $originalSettings);
demand($tamperedSettings !== $originalSettings, 'turnstile_tamper_fixture_constructed');
file_put_contents($testConfig, $tamperedSettings);

$tamperRejected = false;
try {
    $store->read();
} catch (\RuntimeException) {
    $tamperRejected = true;
}
demand($tamperRejected, 'turnstile_authenticated_metadata_tamper_rejected');
file_put_contents($testConfig, $originalSettings);

$wrongKeyRejected = false;
try {
    (new \Barama\Security\TurnstileSettingsStore(
        $testConfig, base64_encode(random_bytes(32))
    ))->read();
} catch (\RuntimeException) {
    $wrongKeyRejected = true;
}
demand($wrongKeyRejected, 'turnstile_wrong_encryption_key_rejected');

@unlink($testConfig);
@rmdir($testRoot);

echo 'PRE_RELEASE_SECURITY_SMOKE=PASS' . PHP_EOL;
