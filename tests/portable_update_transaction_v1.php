<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/TrustedReleaseFeed.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateArchiveV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateBackupV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateFileTransactionV1.php';

use Barama\Services\Updates\PortableUpdateFileTransactionV1 as Txn;

function ok(bool $condition,string $label): void {
    if (!$condition) throw new RuntimeException('FAIL='.$label);
    echo 'PASS='.$label."\n";
}
function blocked(callable $run,string $label): void {
    try { $run(); } catch (RuntimeException) { echo 'PASS='.$label."\n";return; }
    throw new RuntimeException('FAIL='.$label);
}
function rmrf(string $p): void {
    if (is_link($p)||is_file($p)) { unlink($p);return; }
    if (!is_dir($p)) return;
    foreach (new FilesystemIterator($p,FilesystemIterator::SKIP_DOTS) as $x)
        rmrf($x->getPathname());
    rmdir($p);
}
function signFixture(string $sha,int $bytes,string $secret): array {
    $manifest=[
        'schema'=>'tonsamith-commercial-release-v2',
        'publisher'=>'tonsamith.com',
        'key_id'=>'portable-transaction-test',
        'release_id'=>'portable-txn-test',
        'product_slug'=>'barama-real-estate',
        'version'=>'0.9.24',
        'channel'=>'stable',
        'release_type'=>'update',
        'release_status'=>'published',
        'package_sha256'=>$sha,
        'package_bytes'=>$bytes,
        'min_php'=>'8.2.0',
        'minimum_app_version'=>'0.9.23',
        'migration_policy'=>'additive',
        'published_at'=>gmdate('Y-m-d\TH:i:s\Z',time()-60),
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600),
    ];
    $sort=$manifest;ksort($sort,SORT_STRING);
    $signed='TONSAMITH-RELEASE-MANIFEST-V2'."\n".json_encode(
        $sort,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR
    );
    return [
        'manifest'=>$manifest,
        'signature'=>[
            'algorithm'=>'Ed25519',
            'key_id'=>'portable-transaction-test',
            'value'=>base64_encode(sodium_crypto_sign_detached($signed,$secret))
        ]
    ];
}

if (!extension_loaded('sodium')||!class_exists(ZipArchive::class))
    throw new RuntimeException('TEST_REQUIRES_SODIUM_AND_ZIP');
define('BARAMA_PORTABLE_FIXTURE_ONLY',true);
$base=sys_get_temp_dir().'/barama-portable-txn-'.bin2hex(random_bytes(7));
$site=$base.'/site';$vault=$base.'/private-vault';
mkdir($site.'/application/config',0700,true);
mkdir($site.'/assets/js',0700,true);
mkdir($vault,0700,true);chmod($vault,0700);
define('BASE_PATH',$site);define('APP_PATH',$site.'/application');
$kp=sodium_crypto_sign_keypair();
file_put_contents(APP_PATH.'/config/update-publisher-v2-keys.json',json_encode([
    'format'=>'tonsamith-publisher-keys-v2',
    'keys'=>['portable-transaction-test'=>[
        'public'=>base64_encode(sodium_crypto_sign_publickey($kp)),
        'status'=>'active'
    ]]
],JSON_THROW_ON_ERROR));
chmod(APP_PATH.'/config/update-publisher-v2-keys.json',0600);
file_put_contents($site.'/VERSION',"0.9.23\n");
file_put_contents($site.'/assets/js/app.js',"before\n");
$archive=$base.'/fixture-release.zip';
$z=new ZipArchive();
if ($z->open($archive,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)
    throw new RuntimeException('FIXTURE_ZIP_CREATION_FAILED');
$z->addFromString('VERSION',"0.9.24\n");
$z->addFromString('assets/js/app.js',"after\n");
$z->close();
$env=signFixture(hash_file('sha256',$archive),filesize($archive),
    sodium_crypto_sign_secretkey($kp));
$eligible=static fn($version)=>[
    'status'=>'available','entitlement'=>'Eligible',
    'feed'=>'Signed release verified','signed_envelope'=>$env,
];
$denied=static fn($version)=>[
    'status'=>'ineligible','entitlement'=>'Not entitled',
    'feed'=>'Not available'
];
try {
    blocked(fn()=>Txn::apply($site,$archive,$vault,'0.9.23',$env,'stable',$denied),
        'fresh_unauthorized_audience_rejected');
    ok(file_get_contents($site.'/VERSION')==="0.9.23\n",
        'denied_request_never_changed_version');
    $r=Txn::apply($site,$archive,$vault,'0.9.23',$env,'stable',$eligible);
    ok($r['state']==='files_applied_health_pending' && $r['success']===false,
        'no_false_completed_before_health_check');
    ok(file_get_contents($site.'/VERSION')==="0.9.24\n"
        && file_get_contents($site.'/assets/js/app.js')==="after\n",
        'verified_file_transaction_applied');
    ok(is_file($vault.'/active.json'),'durable_recovery_marker');
    blocked(fn()=>Txn::apply($site,$archive,$vault,'0.9.24',$env,'stable',$eligible),
        'second_update_cannot_override_active_job');
    $rolled=Txn::recover($site,$vault,$r['job_id']);
    ok($rolled['state']==='rolled_back'
        && file_get_contents($site.'/VERSION')==="0.9.23\n"
        && file_get_contents($site.'/assets/js/app.js')==="before\n",
        'explicit_private_restore');
    ok(!file_exists($vault.'/active.json'),'marker_removed_after_verified_recovery');
    $again=Txn::apply($site,$archive,$vault,'0.9.23',$env,'stable',$eligible);
    file_put_contents($site.'/assets/js/app.js',"third-party-change\n");
    blocked(fn()=>Txn::recover($site,$vault,$again['job_id']),
        'unrelated_live_change_not_clobbered');
    ok(file_get_contents($site.'/assets/js/app.js')==="third-party-change\n"
        && is_file($vault.'/active.json'),
        'manual_review_keeps_evidence');
    file_put_contents($site.'/assets/js/app.js',"after\n");
    $res=Txn::recover($site,$vault,$again['job_id']);
    ok($res['state']==='rolled_back' && !is_file($vault.'/active.json'),
        'recovery_can_resume_when_safe');
    echo "PORTABLE_FILE_TRANSACTION_TESTS=PASS\n";
} finally { rmrf($base); }
