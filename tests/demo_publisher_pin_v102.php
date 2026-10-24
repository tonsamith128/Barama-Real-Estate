<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/DemoPublisherApiV1.php';

use Barama\Services\DemoPublisherApiV1;

function checkPin(bool $condition, string $name): void {
    if (!$condition) { fwrite(STDERR, "FAIL=$name\n"); exit(1); }
    echo "PASS=$name\n";
}
function rejectsPin(callable $operation, string $name): void {
    try { $operation(); }
    catch (RuntimeException $e) {
        checkPin($e->getMessage() === 'DEMO_PUBLISHER_PIN_MISMATCH', $name);
        return;
    }
    checkPin(false, $name);
}

$expectedRealFingerprint = 'be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287';
checkPin(hash_equals(DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256, $expectedRealFingerprint),
    'independently_verified_publisher_pin_matches_20261024_receipt');

$pair = sodium_crypto_sign_keypair();
$raw = sodium_crypto_sign_publickey($pair);
$fp = hash('sha256', $raw);
$identity = [
    'key_id' => substr($fp, 0, 24),
    'fingerprint' => $fp,
    'public_key' => base64_encode($raw)
];
DemoPublisherApiV1::requirePinnedIdentity($identity, $fp);
echo "PASS=valid_publisher_identity_passes_matching_pin\n";

rejectsPin(fn() => DemoPublisherApiV1::requirePinnedIdentity(
    $identity, str_repeat('f', 64)), 'substituted_pubkey_fingerprint_denied');
rejectsPin(fn() => DemoPublisherApiV1::requirePinnedIdentity(
    [...$identity, 'key_id' => str_repeat('a', 24)], $fp),
    'malformed_publisher_key_id_denied');
rejectsPin(fn() => DemoPublisherApiV1::requirePinnedIdentity(
    [...$identity, 'public_key' => base64_encode(str_repeat('z', 32))], $fp),
    'pubkey_not_matching_claimed_hash_denied');
rejectsPin(fn() => DemoPublisherApiV1::requirePinnedIdentity(
    $identity, 'NOT_A_VALID_HASH'), 'invalid_reference_pin_denied');

echo "DEMO_INDEPENDENT_PUBLISHER_PIN_CONTRACT=PASS\n";
