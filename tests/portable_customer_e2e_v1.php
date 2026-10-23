<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/application/app/Core/Installation.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/TrustedReleaseFeed.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateArchiveV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateBackupV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateFileTransactionV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateDownloadV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableCustomerUpdateV1.php';

use Barama\Services\Updates\PortableCustomerUpdateV1 as Flow;

function affirm(bool $value,string $name):void {
    if (!$value) throw new RuntimeException('E2E_FAIL='.$name);
    echo 'E2E_PASS='.$name."\n";
}
$mode=$argv[1]??'';
$base=$argv[2]??'';
if (PHP_SAPI!=='cli' || !in_array($mode,['init','complete','rollback'],true)
    || !is_string($base) || $base==='' || !str_starts_with($base,sys_get_temp_dir().'/')
    || is_link($base) || !is_dir($base) || realpath($base)!==$base) {
    throw new RuntimeException('E2E_TEMP_FIXTURE_ONLY');
}
$site=$base.'/site';
if ($mode==='init') {
    mkdir($site.'/application/config',0700,true);
    mkdir($site.'/assets/js',0700,true);
    file_put_contents($site.'/VERSION',"0.9.23\n");
    file_put_contents($site.'/assets/js/app.js',"before\n");
}
define('BASE_PATH',$site);
define('APP_PATH',$site.'/application');
define('APP_VERSION',trim((string)file_get_contents($site.'/VERSION')));
define('BARAMA_BOOTSTRAP', true);
$_SERVER['DOCUMENT_ROOT']=$site;
if ($mode==='init') {
    define('BARAMA_PORTABLE_FIXTURE_ONLY',true);
    // Match a normally installed customer: finish() synchronizes installed.php,
    // and the independent rollback request must be able to read its version.
    \Barama\Core\Installation::markInstalled(['version'=>APP_VERSION]);
    affirm(\Barama\Core\Installation::metadata()['version']===APP_VERSION,
        'installed_bootstrap_metadata_created');
    $kp=sodium_crypto_sign_keypair();
    file_put_contents(APP_PATH.'/config/update-publisher-v2-keys.json',
        json_encode([
            'format'=>'tonsamith-publisher-keys-v2',
            'keys'=>['portable-e2e-fixture'=>[
                'public'=>base64_encode(sodium_crypto_sign_publickey($kp)),
                'status'=>'active',
            ]]
        ],JSON_THROW_ON_ERROR));
    chmod(APP_PATH.'/config/update-publisher-v2-keys.json',0600);
    $zipPath=$base.'/fixture.zip';
    $zip=new ZipArchive();
    affirm($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true,'zip_created');
    $zip->addFromString('VERSION',"0.9.24\n");
    $zip->addFromString('assets/js/app.js',"after\n");
    $zip->close();
    $bytes=file_get_contents($zipPath);
    affirm(is_string($bytes),'zip_bytes_available');
    $m=[
        'schema'=>'tonsamith-commercial-release-v2',
        'publisher'=>'tonsamith.com',
        'key_id'=>'portable-e2e-fixture',
        'release_id'=>'portable-e2e-fileonly',
        'product_slug'=>'barama-real-estate',
        'version'=>'0.9.24',
        'channel'=>'stable',
        'release_type'=>'update',
        'release_status'=>'published',
        'package_sha256'=>hash('sha256',$bytes),
        'package_bytes'=>strlen($bytes),
        'min_php'=>'8.2.0',
        'minimum_app_version'=>'0.9.23',
        'migration_policy'=>'additive',
        'published_at'=>gmdate('Y-m-d\TH:i:s\Z',time()-60),
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600),
    ];
    $canonical=$m;ksort($canonical,SORT_STRING);
    $signed="TONSAMITH-RELEASE-MANIFEST-V2\n"
        .json_encode($canonical,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $envelope=[
        'manifest'=>$m,
        'signature'=>[
            'algorithm'=>'Ed25519',
            'key_id'=>'portable-e2e-fixture',
            'value'=>base64_encode(sodium_crypto_sign_detached(
                $signed,sodium_crypto_sign_secretkey($kp)
            )),
        ],
    ];
    $feed=static fn(string $installed):array=>[
        'status'=>'available','entitlement'=>'Eligible',
        'feed'=>'Signed release verified',
        'version'=>'0.9.24',
        'archive_sha256'=>$m['package_sha256'],
        'signed_envelope'=>$envelope,
    ];
    $transfer=static function(array $request,callable $write) use ($bytes):array {
        affirm(($request['release_id']??null)==='portable-e2e-fileonly','fixture_release_bound');
        $write(substr($bytes,0,80));
        $write(substr($bytes,80));
        return ['status'=>200,'mime'=>'application/zip'];
    };
    affirm(Flow::ready(),'fresh_customer_host_ready');
    $download=Flow::begin($feed,$transfer);
    affirm($download['state']==='downloaded','private_authorized_download');
    affirm(Flow::status()['state']==='downloaded','download_survives_status_refresh');
    $installed=Flow::install($feed);
    affirm($installed['state']==='health_pending','verified_install_not_complete_yet');
    affirm(file_get_contents($site.'/VERSION')==="0.9.24\n"
        && file_get_contents($site.'/assets/js/app.js')==="after\n",'real_file_replacements');
    affirm(Flow::status()['state']==='health_pending','durable_health_pending');
    echo "PORTABLE_CUSTOMER_E2E_PHASE1=PASS\n";
} elseif ($mode==='complete') {
    affirm(APP_VERSION==='0.9.24','fresh_request_boots_new_version');
    $done=Flow::finish();
    affirm($done['state']==='completed_file_only','independent_complete_request');
    $status=Flow::status();
    affirm($status['state']==='completed_file_only' && !$status['pending'],
        'durable_completed_status');
    affirm(file_get_contents($site.'/assets/js/app.js')==="after\n",'new_code_verified');
    echo "PORTABLE_CUSTOMER_E2E_COMPLETION=PASS\n";
} else {
    affirm(APP_VERSION==='0.9.24','rollback_request_boots_new_version');
    $r=Flow::recover();
    affirm($r['state']==='rolled_back','explicit_restore_completed');
    affirm(file_get_contents($site.'/VERSION')==="0.9.23\n"
        && file_get_contents($site.'/assets/js/app.js')==="before\n",'code_and_version_restored');
    affirm(Flow::status()['state']==='rolled_back','durable_rollback_status');
    echo "PORTABLE_CUSTOMER_E2E_ROLLBACK=PASS\n";
}
