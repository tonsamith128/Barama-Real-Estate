<?php

declare(strict_types=1);

/**
 * Actual Ed25519 signed envelope + real ZIP -> atomic file update -> crash
 * recovery. NEVER connects to MySQL or writes a real website.
 */
if (PHP_SAPI !== 'cli' || !extension_loaded('sodium') || !class_exists(ZipArchive::class)) {
    fwrite(STDERR, "SIGNED_FIXTURE_REQUIRED_PHP_EXTENSIONS_MISSING\n"); exit(1);
}

$base = '/tmp/barama-oneclick-transaction-fixture-' . bin2hex(random_bytes(8));
$site = $base . '/site';
$vault = $base . '/vault';
$app = $site . '/application';
$stage = $app . '/storage/app/updates/staged';
$checkCount = 0;
$check = static function (bool $ok, string $name) use (&$checkCount): void {
    if (!$ok) throw new RuntimeException('SIGNED_FIXTURE_FAIL:' . $name);
    ++$checkCount; echo 'PASS=' . $name . PHP_EOL;
};
$cleanup = static function (string $p) use (&$cleanup): void {
    if (is_link($p) || is_file($p)) { if (!unlink($p)) throw new RuntimeException('FIXTURE_CLEANUP_FAILED'); return; }
    if (!is_dir($p)) return;
    foreach (scandir($p) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') $cleanup($p . '/' . $name);
    }
    if (!rmdir($p)) throw new RuntimeException('FIXTURE_CLEANUP_FAILED');
};

try {
    if (!mkdir($base, 0700) || !mkdir($site, 0700) || !mkdir($vault, 0700)
        || !mkdir($app, 0700) || !mkdir($app.'/config', 0700)
        || !mkdir($app.'/storage', 0700)
        || !mkdir($app.'/storage/app', 0700)
        || !mkdir($app.'/storage/app/updates', 0700)
        || !mkdir($stage, 0700)
        || !mkdir($site.'/assets', 0700) || !mkdir($site.'/assets/css', 0700)) {
        throw new RuntimeException('FIXTURE_SETUP_FAILED');
    }
    file_put_contents($site.'/VERSION', "0.9.22-dev\n");
    file_put_contents($site.'/customer-records.txt', 'CUSTOMER_RECORDS_MUST_STAY_UNTOUCHED');
    $customerSha = hash_file('sha256', $site.'/customer-records.txt');
    define('BASE_PATH', $site);
    define('APP_PATH', $app);
    require dirname(__DIR__).'/application/app/Services/Updates/TrustedReleaseFeed.php';
    require dirname(__DIR__).'/application/app/Services/Updates/SignedReleaseInspector.php';
    require dirname(__DIR__).'/application/app/Services/Updates/VerifiedReleaseFilePlanV2.php';
    require dirname(__DIR__).'/application/app/Services/Updates/OneClickAtomicTransactionV1.php';
    require dirname(__DIR__).'/application/app/Services/Updates/OneClickSignedFixtureBridgeV1.php';

    $keyPair = sodium_crypto_sign_keypair();
    $pub = sodium_crypto_sign_publickey($keyPair);
    $secret = sodium_crypto_sign_secretkey($keyPair);
    file_put_contents($app.'/config/update-publisher-v2-keys.json', json_encode([
        'format'=>'tonsamith-publisher-keys-v2',
        'keys'=>['fixture-key'=>['public'=>base64_encode($pub),'status'=>'active']],
    ], JSON_THROW_ON_ERROR));

    $zipPath = $stage.'/release.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('FIXTURE_ZIP_CREATE_FAILED');
    }
    $zip->addFromString('VERSION', "0.9.23\n");
    $zip->addFromString('assets/css/test-style.css', "body{color:red}\n");
    $zip->close();

    $manifest = [
        'schema'=>'tonsamith-commercial-release-v2',
        'publisher'=>'tonsamith.com',
        'key_id'=>'fixture-key',
        'release_id'=>'fixture-0.9.23',
        'product_slug'=>'barama-real-estate',
        'version'=>'0.9.23',
        'channel'=>'stable',
        'release_type'=>'update',
        'release_status'=>'published',
        'package_sha256'=>hash_file('sha256',$zipPath),
        'package_bytes'=>filesize($zipPath),
        'min_php'=>'8.0.0',
        'minimum_app_version'=>'0.9.0',
        'migration_policy'=>'additive',
        'published_at'=>gmdate('Y-m-d\TH:i:s\Z', time()-120),
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z', time()+86400),
    ];
    $signed = static function (array $m) use ($secret): array {
        $canonical=$m; ksort($canonical,SORT_STRING);
        $payload="TONSAMITH-RELEASE-MANIFEST-V2\n"
            .json_encode($canonical, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return [
            'manifest'=>$m,
            'signature'=>[
                'algorithm'=>'Ed25519',
                'key_id'=>'fixture-key',
                'value'=>base64_encode(sodium_crypto_sign_detached($payload, $secret)),
            ],
        ];
    };
    $original=$signed($manifest);
    $envPath=$stage.'/envelope.json';
    $writeEnv=static function (array $envelope) use ($envPath): void {
        file_put_contents($envPath, json_encode($envelope, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    };
    $writeEnv($original);
    $check(
        \Barama\Services\Updates\SignedReleaseInspector::inspectPaths(
            $envPath, $zipPath, '0.9.22-dev'
        )['version']==='0.9.23',
        'genuine_ephemeral_ed25519_signature_verified'
    );

    $wrong=$manifest; $wrong['product_slug']='not-barama';
    $writeEnv($signed($wrong));
    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            $site,$vault,$envPath,$zipPath,'0.9.22-dev'
        );
        $check(false,'wrong_product_signature_denied');
    } catch (RuntimeException $e) {
        $check($e->getMessage()!=='SIGNED_FIXTURE_FAIL:wrong_product_signature_denied',
            'correctly_signed_wrong_product_denied');
    }
    $check(trim((string)file_get_contents($site.'/VERSION'))==='0.9.22-dev',
        'wrong_product_no_file_mutation');
    $writeEnv($original);
    $tampered=$original; $tampered['signature']['value']=base64_encode(str_repeat("\x00",64));
    $writeEnv($tampered);
    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            $site,$vault,$envPath,$zipPath,'0.9.22-dev'
        );
        $check(false,'bad_signature_denied');
    } catch (RuntimeException $e) {
        $check($e->getMessage()!=='SIGNED_FIXTURE_FAIL:bad_signature_denied','bad_signature_denied');
    }
    $writeEnv($original);
    file_put_contents($zipPath,'tamper',FILE_APPEND);
    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            $site,$vault,$envPath,$zipPath,'0.9.22-dev'
        );
        $check(false,'altered_zip_denied');
    } catch (RuntimeException $e) {
        $check($e->getMessage()!=='SIGNED_FIXTURE_FAIL:altered_zip_denied','altered_zip_denied');
    }
    // Rebuild identical entries and issue a NEW signature binding this new ZIP.
    $zip=new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) {
        throw new RuntimeException('FIXTURE_ZIP_RECREATE_FAILED');
    }
    $zip->addFromString('VERSION',"0.9.23\n");
    $zip->addFromString('assets/css/test-style.css',"body{color:red}\n");
    $zip->close();
    $manifest['package_sha256']=hash_file('sha256',$zipPath);
    $manifest['package_bytes']=filesize($zipPath);
    $writeEnv($signed($manifest));

    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            '/www/wwwroot/baramastaging.tonsamith.com',$vault,$envPath,$zipPath,'0.9.22-dev'
        );
        $check(false,'live_staging_denied');
    } catch (RuntimeException $e) {
        $check($e->getMessage()==='SIGNED_BRIDGE_FIXTURE_ONLY','live_staging_denied');
    }

    $out=\Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
        $site,$vault,$envPath,$zipPath,'0.9.22-dev'
    );
    $check($out['status']==='SIGNED_ISOLATED_FIXTURE_INSTALL_COMMITTED'
        && !$out['live_installation'] && !$out['database_writes']
        && $out['files']===2, 'real_signed_zip_commits_atomic_fixture_update');
    $check(trim((string)file_get_contents($site.'/VERSION'))==='0.9.23',
        'signed_target_version_written');
    $check((string)file_get_contents($site.'/assets/css/test-style.css')==="body{color:red}\n",
        'signed_target_css_written');
    $check(hash_file('sha256',$site.'/customer-records.txt')===$customerSha,
        'customer_data_unchanged');

    // Reset ONLY the disposable clone, then exercise a signed-file crash.
    file_put_contents($site.'/VERSION',"0.9.22-dev\n");
    unlink($site.'/assets/css/test-style.css');
    $txId=null;
    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            $site,$vault,$envPath,$zipPath,'0.9.22-dev',1
        );
        $check(false,'injected_signed_update_crash');
    } catch (RuntimeException $e) {
        if (preg_match('/^FIXTURE_INJECTED_PROCESS_CRASH:(tx-[a-f0-9]{24})$/',$e->getMessage(),$matches)) {
            $txId=$matches[1];
        }
        $check(is_string($txId),'injected_signed_update_crash');
    }
    try {
        \Barama\Services\Updates\OneClickSignedFixtureBridgeV1::rehearse(
            $site,$vault,$envPath,$zipPath,'0.9.22-dev'
        );
        $check(false,'new_release_blocked_before_recovery');
    } catch (RuntimeException $e) {
        $check($e->getMessage()==='FIXTURE_INTERRUPTED_UPDATE_REQUIRES_RECOVERY',
            'new_release_blocked_before_recovery');
    }
    $recovered=\Barama\Services\Updates\OneClickAtomicTransactionV1::recover(
        $site,$vault,$txId
    );
    $check($recovered['status']==='ISOLATED_FILE_CRASH_RECOVERED',
        'signed_fixture_crash_recovered');
    $check(trim((string)file_get_contents($site.'/VERSION'))==='0.9.22-dev'
        && !file_exists($site.'/assets/css/test-style.css'),
        'preupdate_file_baseline_fully_restored');
    $check(hash_file('sha256',$site.'/customer-records.txt')===$customerSha,
        'private_customer_fixture_unchanged_after_recovery');
    echo "SIGNED_V2_TO_ATOMIC_FIXTURE=PASS\n";
    echo "SIGNED_ZIP_CRASH_RECOVERY=PASS\n";
    echo "LIVE_SITES_MODIFIED=NO\n";
    echo "DATABASE_ACCESS=NO\n";
    echo "AUTO_INSTALL_LIVE=DISABLED\n";
    echo "ASSERTIONS=$checkCount\n";
} finally {
    // Fixture path is random and always confined to the dedicated /tmp prefix.
    if (preg_match('~^/tmp/barama-oneclick-transaction-fixture-[a-f0-9]{16}$~D',$base)) {
        $cleanup($base);
    }
}
