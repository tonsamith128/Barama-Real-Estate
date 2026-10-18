<?php

declare(strict_types=1);

$root = '/tmp/barama-signed-stage-' . bin2hex(random_bytes(8));
$site = $root . '/site';
$app = $site . '/application';
$stage = $app . '/storage/app/updates/staged';
$cleanup = static function (string $dir) use (&$cleanup): void {
    if (is_link($dir) || is_file($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $p) {
        if ($p !== '.' && $p !== '..') $cleanup($dir . '/' . $p);
    }
    @rmdir($dir);
};
$count = 0;
$check = static function (bool $ok, string $label) use (&$count): void {
    if (!$ok) throw new RuntimeException('SIGNED_PREFLIGHT_FIXTURE_FAILED:' . $label);
    ++$count;
};
$deny = static function (callable $fn, string $expected) use ($check): void {
    try { $fn(); } catch (RuntimeException $e) {
        $check($e->getMessage() === $expected, $expected);
        return;
    }
    throw new RuntimeException('SIGNED_PREFLIGHT_FAIL_OPEN:' . $expected);
};

try {
    mkdir($root, 0700);
    mkdir($site, 0700);
    mkdir($app, 0700);
    mkdir($app . '/config', 0700);
    mkdir($app . '/storage', 0700);
    mkdir($app . '/storage/app', 0700);
    mkdir($app . '/storage/app/updates', 0700);
    mkdir($stage, 0700);
    file_put_contents($site . '/VERSION', "0.9.22-dev\n");
    file_put_contents($site . '/customer-fixture-data', 'CUSTOMER_DATA_UNCHANGED');
    $customerHash = hash_file('sha256', $site . '/customer-fixture-data');
    define('APP_PATH', $app);
    define('BASE_PATH', $site);
    require dirname(__DIR__) . '/application/app/Services/Updates/TrustedReleaseFeed.php';
    require dirname(__DIR__) . '/application/app/Services/Updates/SignedReleaseInspector.php';
    require dirname(__DIR__) . '/application/app/Services/Updates/VerifiedReleaseFilePlanV2.php';
    require dirname(__DIR__) . '/application/app/Services/Updates/StagingSignedReleasePreflightV1.php';
    $class = \Barama\Services\Updates\StagingSignedReleasePreflightV1::class;

    $missing = $class::inspect($site);
    $check($missing['status'] === 'SIGNED_STAGING_INSPECTION_ONLY', 'Read-only mode');
    $check(in_array('SIGNED_RELEASE_NOT_STAGED', $missing['blockers'], true), 'Missing archive rejected');
    $check($missing['automatic_update_enabled'] === false && !$missing['ready_to_install'], 'Installation disabled');
    $check($missing['database_connections'] === false && $missing['website_writes'] === false, 'No customer writes');
    $deny(static fn() => $class::inspect('/www/wwwroot/baramarealestate.tonsamith.com'),
        'SIGNED_PREFLIGHT_ROOT_UNTRUSTED');
    $deny(static fn() => $class::inspect('/tmp'), 'SIGNED_PREFLIGHT_STAGING_ONLY');

    $keypair = sodium_crypto_sign_keypair();
    $public = sodium_crypto_sign_publickey($keypair);
    $secret = sodium_crypto_sign_secretkey($keypair);
    file_put_contents($app . '/config/update-publisher-v2-keys.json', json_encode([
        'format' => 'tonsamith-publisher-keys-v2',
        'keys' => ['fixture-key' => ['public' => base64_encode($public), 'status' => 'active']],
    ], JSON_THROW_ON_ERROR));
    $zipPath = $stage . '/release.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('TEST_ZIP_CREATION_FAILED');
    }
    $zip->addFromString('VERSION', '0.9.23');
    $zip->addFromString('assets/test-style.css', 'body{color:red}');
    $zip->close();

    $manifest = [
        'schema' => 'tonsamith-commercial-release-v2',
        'publisher' => 'tonsamith.com',
        'key_id' => 'fixture-key',
        'release_id' => 'fixture-0.9.23',
        'product_slug' => 'barama-real-estate',
        'version' => '0.9.23',
        'channel' => 'stable',
        'release_type' => 'update',
        'release_status' => 'published',
        'package_sha256' => hash_file('sha256', $zipPath),
        'package_bytes' => filesize($zipPath),
        'min_php' => '8.0.0',
        'minimum_app_version' => '0.9.0',
        'migration_policy' => 'additive',
        'published_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 120),
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
    ];
    $canonical = $manifest;
    ksort($canonical, SORT_STRING);
    $payload = "TONSAMITH-RELEASE-MANIFEST-V2\n"
        . json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $envelope = [
        'manifest' => $manifest,
        'signature' => [
            'algorithm' => 'Ed25519',
            'key_id' => 'fixture-key',
            'value' => base64_encode(sodium_crypto_sign_detached($payload, $secret)),
        ],
    ];
    $envelopePath = $stage . '/envelope.json';
    file_put_contents($envelopePath, json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signed = $class::inspect($site);
    $check($signed['release_staged'] === true, 'Publisher release staged');
    $check($signed['publisher_signature_verified'] === true, 'Genuine Ed25519 verification');
    $check($signed['archive_digest_verified'] === true, 'Archive SHA verified');
    $check($signed['file_plan_verified'] === true, 'Read-only plan generated');
    $check($signed['candidate_version'] === '0.9.23' && $signed['candidate_files'] === 2, 'Version and targets');
    $check($signed['ready_to_install'] === false && $signed['automatic_update_enabled'] === false, 'Signature not install authorization');
    $check(in_array('PUBLISHER_ENTITLEMENT_NOT_RECHECKED', $signed['blockers'], true), 'Entitlement blocker retained');
    $check(in_array('REAL_DATABASE_RESTORE_NOT_VERIFIED', $signed['blockers'], true), 'Database blocker retained');
    $check(in_array('LIVE_UPDATE_ROLLBACK_NOT_VERIFIED', $signed['blockers'], true), 'Rollback blocker retained');
    $check(hash_file('sha256', $site . '/customer-fixture-data') === $customerHash, 'Customer marker untouched');
    $check(trim((string)file_get_contents($site . '/VERSION')) === '0.9.22-dev', 'Installed VERSION not replaced');

    $invalid = $envelope;
    $invalid['signature']['value'] = base64_encode(str_repeat("\x00", SODIUM_CRYPTO_SIGN_BYTES));
    file_put_contents($envelopePath, json_encode($invalid, JSON_THROW_ON_ERROR));
    $denied = $class::inspect($site);
    $check($denied['publisher_signature_verified'] === false, 'Tampered signature rejected');
    $check(in_array('SIGNED_RELEASE_OR_PLAN_REJECTED', $denied['blockers'], true), 'Signature rejection reason');

    file_put_contents($envelopePath, json_encode($envelope, JSON_THROW_ON_ERROR));
    file_put_contents($zipPath, 'tamper', FILE_APPEND);
    $changed = $class::inspect($site);
    $check($changed['archive_digest_verified'] === false && !$changed['ready_to_install'], 'Tampered archive refused');

    unlink($zipPath);
    symlink('/etc/passwd', $zipPath);
    $link = $class::inspect($site);
    $check(in_array('STAGED_RELEASE_PATH_UNTRUSTED', $link['blockers'], true), 'Symlink release rejected');

    echo "VALID_DISPOSABLE_ED25519_SIGNATURE=PASS\n";
    echo "SIGNED_ZIP_READ_ONLY_PLAN=PASS\n";
    echo "TAMPERED_SIGNATURE_AND_ZIP_REJECTED=PASS\n";
    echo "ALL_LIVE_UPDATE_GATES_STILL_BLOCKED=YES\n";
    echo "LIVE_WEBSITE_WRITES=NO\n";
    echo "DATABASE_CONNECTIONS=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo 'TEST_ASSERTIONS=' . $count . "\n";
} finally {
    $cleanup($root);
}
