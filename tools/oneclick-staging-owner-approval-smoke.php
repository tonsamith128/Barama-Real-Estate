<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/OneClickStagingOwnerApprovalV1.php';
use Barama\Services\Updates\OneClickStagingOwnerApprovalV1 as A;

function must(bool $yes,string $label):void {
    if(!$yes) throw new RuntimeException('OWNER_STAGING_FAIL:' . $label);
    echo 'PASS=' . $label . "\n";
}
function rejected(callable $f,string $label):void {
    try {$f();} catch (RuntimeException) {must(true,$label);return;}
    throw new RuntimeException('OWNER_STAGING_FAIL_OPEN:' . $label);
}
function removeTree(string $path):void {
    if(is_link($path)||is_file($path)){unlink($path);return;}
    if(!is_dir($path))return;
    foreach(scandir($path)?:[] as $part){
        if($part!=='.'&&$part!=='..')removeTree($path.'/'.$part);
    }
    rmdir($path);
}
$base='/tmp/barama-staging-owner-fixture-'.bin2hex(random_bytes(8));
$site=$base.'/site';$vault=$base.'/vault';
$now=1800000000;
try {
    mkdir($base,0700);mkdir($site,0700);mkdir($vault,0700);
    mkdir($vault.'/used-nonces',0700);
    $pair=sodium_crypto_sign_keypair();
    $public=sodium_crypto_sign_publickey($pair);
    $secret=sodium_crypto_sign_secretkey($pair);
    $catalog=['schema'=>'barama-staging-owner-trust-v1','key_id'=>'owner-test-1','public_key'=>base64_encode($public)];
    file_put_contents($vault.'/owner-trust.json',json_encode($catalog,JSON_THROW_ON_ERROR));
    chmod($vault.'/owner-trust.json',0600);
    $expected=[
        'archive_sha256'=>str_repeat('a',64),
        'database_name_sha256'=>str_repeat('b',64),
        'recovery_checkpoint_sha256'=>str_repeat('c',64),
        'source_version'=>'0.9.22-dev',
        'target_version'=>'0.9.23',
    ];
    $approval=[
        'schema'=>'barama-staging-oneclick-owner-consent-v1',
        'action'=>'approve_one_disposable_staging_fixture',
        'site'=>$site,
        'source_version'=>$expected['source_version'],
        'target_version'=>$expected['target_version'],
        'archive_sha256'=>$expected['archive_sha256'],
        'database_name_sha256'=>$expected['database_name_sha256'],
        'recovery_checkpoint_sha256'=>$expected['recovery_checkpoint_sha256'],
        'issued_at'=>$now-20,
        'expires_at'=>$now+200,
        'nonce'=>bin2hex(random_bytes(16)),
    ];
    $sign=static function(array $body)use($secret):array{
        $copy=$body;ksort($copy,SORT_STRING);
        $canonical="BARAMA-STAGING-ONECLICK-OWNER-V1\n".
            json_encode($copy,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return ['consent'=>$body,'signature'=>[
            'algorithm'=>'Ed25519','key_id'=>'owner-test-1',
            'value'=>base64_encode(sodium_crypto_sign_detached($canonical,$secret))
        ]];
    };
    $env=$sign($approval);
    rejected(fn()=>A::verifyAndConsume('/www/wwwroot/baramarealestate.tonsamith.com',$vault,$env,$expected,$now),'reject_real_production_site');
    rejected(fn()=>A::verifyAndConsume($site,'/root',$env,$expected,$now),'reject_unauthorized_owner_vault');
    rejected(fn()=>A::verifyAndConsume($site,$vault,$env,$expected+['forged'=>'evidence'],$now),'reject_forged_extra_worker_observations');
    $other=$expected;$other['archive_sha256']=str_repeat('d',64);
    rejected(fn()=>A::verifyAndConsume($site,$vault,$env,$other,$now),'reject_changed_release_digest');
    $other=$expected;$other['recovery_checkpoint_sha256']=str_repeat('d',64);
    rejected(fn()=>A::verifyAndConsume($site,$vault,$env,$other,$now),'reject_changed_recovery_checkpoint');
    $other=$expected;$other['database_name_sha256']=str_repeat('d',64);
    rejected(fn()=>A::verifyAndConsume($site,$vault,$env,$other,$now),'reject_changed_database_binding');
    $expired=$approval;$expired['expires_at']=$now-1;
    rejected(fn()=>A::verifyAndConsume($site,$vault,$sign($expired),$expected,$now),'reject_expired_independent_consent');
    $live=$approval;$live['action']='approve_one_signed_staging_upgrade';
    rejected(fn()=>A::verifyAndConsume($site,$vault,$sign($live),$expected,$now),'reject_live_upgrade_action_in_fixture');
    $tampered=$env;$tampered['consent']['target_version']='0.9.24';
    rejected(fn()=>A::verifyAndConsume($site,$vault,$tampered,$expected,$now),'reject_tampered_signed_body');
    $bad=$env;$bad['signature']['value']=base64_encode(random_bytes(64));
    rejected(fn()=>A::verifyAndConsume($site,$vault,$bad,$expected,$now),'reject_forged_signature');
    $valid=A::verifyAndConsume($site,$vault,$env,$expected,$now);
    must($valid['status']==='OWNER_APPROVAL_CONSUMED_WORKER_GATES_REQUIRED'
        && $valid['single_use']===true && $valid['install_enabled']===false
        && $valid['database_writes']===false && $valid['website_writes']===false,
        'valid_owner_consent_consumed_without_live_install');
    $record=$vault.'/used-nonces/nonce-'.$approval['nonce'].'.json';
    must(is_file($record)&&(fileperms($record)&0077)===0
        && str_contains((string)file_get_contents($record),'consumed_pending_independent_worker_gates'),
        'root_private_durable_nonce_record');
    rejected(fn()=>A::verifyAndConsume($site,$vault,$env,$expected,$now),'reject_replay_after_consumption');
    $rekey=$sign(array_replace($approval,['nonce'=>bin2hex(random_bytes(16))]));
    $catalog['public_key']=base64_encode(random_bytes(32));
    file_put_contents($vault.'/owner-trust.json',json_encode($catalog,JSON_THROW_ON_ERROR));
    rejected(fn()=>A::verifyAndConsume($site,$vault,$rekey,$expected,$now),'reject_operator_key_replacement');
    must(count(glob($site.'/*')?:[])===0,'site_untouched');
    echo "ONECLICK_STAGING_OWNER_APPROVAL_SMOKE=PASS\n";
    echo "LIVE_INSTALL_ENABLED=NO\n";
    echo "LIVE_DB_ACCESSED=NO\n";
} finally {
    removeTree($base);
}
