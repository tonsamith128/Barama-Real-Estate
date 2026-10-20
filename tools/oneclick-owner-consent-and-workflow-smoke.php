<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickOwnerConsentFixtureV1.php';
require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickAuthorizedFixtureInstallV1.php';

use Barama\Services\Updates\OneClickOwnerConsentFixtureV1 as Consent;

$base='/tmp/barama-oneclick-consent-fixture-'.bin2hex(random_bytes(8));
$site=$base.'/site';$vault=$base.'/vault';$clock=time();
$assert=static function(bool $value,string $label):void{
    if(!$value)throw new RuntimeException('OWNER_CONSENT_SMOKE_FAILED:'.$label);
    echo 'PASS='.$label.PHP_EOL;
};
$clean=static function(string $p)use(&$clean):void{
    if(is_link($p)||is_file($p)){if(!unlink($p))throw new RuntimeException('CLEANUP_FAILED');return;}
    if(!is_dir($p))return;
    foreach(scandir($p)?:[] as $n)if($n!=='.'&&$n!=='..')$clean($p.'/'.$n);
    if(!rmdir($p))throw new RuntimeException('CLEANUP_FAILED');
};
try{
    mkdir($base,0700);mkdir($site,0700);mkdir($vault,0700);mkdir($vault.'/used-nonces',0700);
    $keypair=sodium_crypto_sign_keypair();$secret=sodium_crypto_sign_secretkey($keypair);
    $pub=sodium_crypto_sign_publickey($keypair);
    $keyPath=$vault.'/owner-public-key.json';
    file_put_contents($keyPath,json_encode([
        'schema'=>'barama-fixture-owner-key-v1','key_id'=>'fixture-owner',
        'public_key'=>base64_encode($pub),
    ],JSON_THROW_ON_ERROR));
    chmod($keyPath,0600);
    $sha=str_repeat('a',64);
    $approval=[
        'schema'=>'barama-owner-oneclick-consent-v1',
        'action'=>'approve_one_disposable_signed_update',
        'site'=>$site,'source_version'=>'0.9.22-dev','target_version'=>'0.9.23',
        'archive_sha256'=>$sha,'issued_at'=>$clock-10,'expires_at'=>$clock+120,
        'nonce'=>bin2hex(random_bytes(16)),
    ];
    $sign=static function(array $data)use($secret):array{
        $sorted=$data;ksort($sorted,SORT_STRING);
        $text="BARAMA-OWNER-ONECLICK-CONSENT-V1\n".json_encode($sorted,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        return ['consent'=>$data,'signature'=>[
            'algorithm'=>'Ed25519','key_id'=>'fixture-owner',
            'value'=>base64_encode(sodium_crypto_sign_detached($text,$secret)),
        ]];
    };
    $good=$sign($approval);
    $run=static fn(array $body,string $expectedSha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',int $now=0)=>Consent::consumeFixture(
        $site,$vault,$body,$expectedSha,'0.9.22-dev','0.9.23',$now ?: $clock
    );
    foreach([
        'forged_signature'=>array_replace_recursive($good,['signature'=>['value'=>base64_encode(str_repeat("\0",64))]]),
        'new_version_without_resigning'=>array_replace_recursive($good,['consent'=>['target_version'=>'0.9.24']]),
        'forged_install_permission'=>array_replace_recursive($good,['consent'=>['install_enabled'=>true]]),
    ] as $label=>$bad){
        try{$run($bad);throw new RuntimeException('ACCEPTED_BAD:'.$label);}
        catch(RuntimeException $e){$assert(!str_starts_with($e->getMessage(),'ACCEPTED_BAD:'),$label.'_rejected');}
    }
    try{$run($good,str_repeat('b',64));throw new RuntimeException('ACCEPTED_WRONG_SHA');}
    catch(RuntimeException $e){$assert($e->getMessage()==='CONSENT_SCOPE_OR_EXPIRY_INVALID','release_binding_rejected');}
    try{$run($good,$sha,$clock+600);throw new RuntimeException('ACCEPTED_EXPIRED');}
    catch(RuntimeException $e){$assert($e->getMessage()==='CONSENT_SCOPE_OR_EXPIRY_INVALID','expired_consent_rejected');}
    $done=$run($good,$sha);
    $assert($done['status']==='INDEPENDENT_CONSENT_RECORDED_FIXTURE_ONLY'
        && $done['single_use']===true && $done['live_install_authorized']===false,'valid_signed_consent_consumed_fixture_only');
    try{$run($good,$sha);throw new RuntimeException('ACCEPTED_REPLAY');}
    catch(RuntimeException $e){$assert($e->getMessage()==='CONSENT_REPLAY_DENIED','replay_denied');}
    try{Consent::consumeFixture('/www/wwwroot/baramastaging.tonsamith.com',$vault,$good,$sha,'0.9.22-dev','0.9.23',$clock);throw new RuntimeException('ACCEPTED_LIVE_ROOT');}
    catch(RuntimeException $e){$assert($e->getMessage()==='CONSENT_DISPOSABLE_FIXTURE_ONLY','live_root_rejected');}
    $source=file_get_contents(dirname(__DIR__).'/application/app/Services/Updates/OneClickAuthorizedFixtureInstallV1.php');
    $assert(is_string($source)
        && str_contains($source,'VerifiedReleaseFilePlanV2::inspect(')
        && str_contains($source,'OneClickOwnerConsentFixtureV1::consumeFixture(')
        && str_contains($source,'OneClickSignedFixtureBridgeV1::rehearse(')
        && !str_contains($source,'UpgradeService::upgrade('),'fixture_workflow_requires_both_signature_checks');
    echo 'ONECLICK_OWNER_CONSENT_AND_SIGNED_WORKFLOW_SMOKE=PASS'.PHP_EOL;
}finally{$clean($base);}
