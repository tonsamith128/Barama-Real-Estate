<?php

declare(strict_types=1);

/**
 * Tests the genuine public catalog parser and offline no-clobber install
 * entirely inside a disposable CI tree. Ephemeral public key is TEST ONLY.
 */
if (getenv('GITHUB_ACTIONS')!=='true'
    || getenv('GITHUB_REPOSITORY')!=='tonsamith128/Barama-Real-Estate'
    || !function_exists('posix_geteuid') || posix_geteuid()!==0) {
    throw new RuntimeException('TEST_REQUIRES_ROOT_CI');
}
$dir='/tmp/barama-trust-bootstrap-'.bin2hex(random_bytes(8));
$site=$dir.'/site';
$catalog=$dir.'/author-public-keys.json';
$cleanup=static function(string $path)use(&$cleanup):void {
    if(is_link($path)||is_file($path)){@unlink($path);return;}
    if(!is_dir($path))return;
    foreach(scandir($path)?:[] as $name) {
        if ($name!=='.' && $name!=='..')$cleanup($path.'/'.$name);
    }
    @rmdir($path);
};
$count=0;
$check=static function(bool $condition,string $what)use(&$count):void {
    if(!$condition)throw new RuntimeException('TRUST_TEST_FAILED:'.$what);
    $count++;
};
$denied=static function(callable $fn,string $expected)use($check):void {
    try{$fn();}catch(RuntimeException $e){
        $check($e->getMessage()===$expected,$expected);return;
    }
    throw new RuntimeException('TRUST_TEST_FAIL_OPEN:'.$expected);
};
try {
    mkdir($dir,0700);
    mkdir($site,0700);
    mkdir($site.'/application',0700);
    mkdir($site.'/application/config',0700);
    file_put_contents($site.'/VERSION',"0.9.22-dev\n");
    file_put_contents($site.'/customer-marker','NO_CUSTOMER_CHANGE');
    $fingerprint=hash_file('sha256',$site.'/customer-marker');
    define('BASE_PATH',$site);
    define('APP_PATH',$site.'/application');
    require dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustBootstrapV1.php';
    // Execute actual CLI in the disposable site, catching bootstrap regressions.
    $binDir=$site.'/application/bin';
    $svcDir=$site.'/application/app/Services/Updates';
    mkdir($binDir,0700,true);
    mkdir($svcDir,0700,true);
    copy(dirname(__DIR__).'/application/bin/publisher-trust-bootstrap.php',
        $binDir.'/publisher-trust-bootstrap.php');
    copy(dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustBootstrapV1.php',
        $svcDir.'/PublisherTrustBootstrapV1.php');
    $pipes=[];
    $process=proc_open([PHP_BINARY,$binDir.'/publisher-trust-bootstrap.php','--check'],
        [1=>['pipe','w'],2=>['pipe','w']],$pipes,$site);
    if(!is_resource($process))throw new RuntimeException('CLI_BOOTSTRAP_FIXTURE_FAILED');
    $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
    $exit=proc_close($process);
    $check($exit===0,'Standalone CLI missing-config readiness succeeds');
    $check(str_contains($stdout,'TRUST_STATUS=PUBLISHER_TRUST_NOT_PROVISIONED'),'Correct CLI status');
    $check(str_contains($stdout,'AUTO_INSTALL=DISABLED'),'Actual CLI cannot install update');
    $check($stderr==='','No PHP fatal in standalone CLI');
    $klass=\Barama\Services\Updates\PublisherTrustBootstrapV1::class;
    $r=$klass::check($site);
    $check($r['status']==='PUBLISHER_TRUST_NOT_PROVISIONED','Missing settings visible');
    $check(count($r['missing'])===2,'Exactly two settings absent');
    $denied(static fn()=>$klass::check('/www/wwwroot/baramarealestate.tonsamith.com'),
        'PUBLISHER_TRUST_STAGING_ONLY');
    $keypair=sodium_crypto_sign_keypair(); // test-only ephemeral keypair, never exported.
    $public=sodium_crypto_sign_publickey($keypair);
    $obj=[
      'format'=>'tonsamith-publisher-keys-v2',
      'keys'=>['ci-public-fixture'=>['public'=>base64_encode($public),'status'=>'active']],
    ];
    $raw=json_encode($obj,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    file_put_contents($catalog,$raw);
    chmod($catalog,0600);
    $digest=hash('sha256',$raw);
    $denied(static fn()=>$klass::provision($site,$catalog,str_repeat('a',64),'',false),
        'PUBLISHER_TRUST_INDEPENDENT_FINGERPRINT_MISMATCH');
    $check(!is_file(APP_PATH.'/config/update-publisher-v2-keys.json'),'Wrong fingerprint does not write');
    $verified=$klass::provision($site,$catalog,$digest,'',false);
    $check($verified['status']==='PUBLISHER_TRUST_CATALOG_VALIDATED_READ_ONLY','Offline verify');
    $check($verified['active_public_key_count']===1,'One active key');
    $check(!$verified['written'],'Verify is read-only');
    $denied(static fn()=>$klass::provision($site,$catalog,$digest,'WRONG_APPROVAL',true),
        'PUBLISHER_TRUST_EXPLICIT_OWNER_APPROVAL_REQUIRED');
    $obj['keys']['ci-public-fixture']['private']=base64_encode(random_bytes(64));
    file_put_contents($catalog,json_encode($obj,JSON_THROW_ON_ERROR));
    $invalidHash=hash_file('sha256',$catalog);
    $denied(static fn()=>$klass::provision($site,$catalog,$invalidHash,'',false),
        'PUBLISHER_TRUST_CATALOG_INVALID');
    $obj['keys']['ci-public-fixture']=[
        'public'=>base64_encode($public),'status'=>'revoked',
    ];
    file_put_contents($catalog,json_encode($obj,JSON_THROW_ON_ERROR));
    $revokedHash=hash_file('sha256',$catalog);
    $denied(static fn()=>$klass::provision($site,$catalog,$revokedHash,'',false),
        'PUBLISHER_TRUST_NO_ACTIVE_KEY');
    file_put_contents($catalog,$raw);
    $link=$dir.'/linked-catalog.json';
    symlink($catalog,$link);
    $denied(static fn()=>$klass::provision($site,$link,$digest,'',false),
        'PUBLISHER_TRUST_CATALOG_PATH_UNTRUSTED');
    $done=$klass::provision($site,$catalog,$digest,
        'APPROVE_INDEPENDENTLY_VERIFIED_PUBLISHER_PUBLIC_KEYS',true);
    $check($done['status']==='PUBLISHER_TRUST_STAGING_CONFIG_INSTALLED','Initial install');
    $check($done['written']===true,'Writes public catalog and feed only');
    $check($done['database_connections']===false && $done['network_requests']===false,'Offline');
    $check($done['automatic_install']===false,'Updater remains blocked');
    $ok=$klass::check($site);
    $check($ok['status']==='PUBLISHER_TRUST_CONFIG_PRESENT','Config ready');
    $check($ok['feed_contract_valid'] && $ok['active_public_key_count']===1,'Settings valid');
    $keys=APP_PATH.'/config/update-publisher-v2-keys.json';
    $feed=APP_PATH.'/config/update-feed-v2.json';
    $check(hash_file('sha256',$keys)===$digest,'Exact publisher bytes pinned');
    $check((fileperms($keys)&0022)===0 && (fileperms($feed)&0022)===0,'Safe permissions');
    $denied(static fn()=>$klass::provision($site,$catalog,$digest,
        'APPROVE_INDEPENDENTLY_VERIFIED_PUBLISHER_PUBLIC_KEYS',true),
        'PUBLISHER_TRUST_EXISTING_CONFIG_REQUIRES_REVIEW');
    $check(hash_file('sha256',$keys)===$digest,'No silent replacement');
    $check(hash_file('sha256',$site.'/customer-marker')===$fingerprint,'Customer data untouched');
    $check(trim((string)file_get_contents($site.'/VERSION'))==='0.9.22-dev','Version unchanged');
    echo "AUTHOR_PINNED_CATALOG_DIGEST_ENFORCED=PASS\n";
    echo "MALFORMED_OR_PRIVATE_KEYS=REJECTED\n";
    echo "PRODUCTION_TRUST_BOOTSTRAP=BLOCKED\n";
    echo "PUBLISHER_NETWORK_CALLS=NO\n";
    echo "DATABASE_CONNECTIONS=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo "TEST_ASSERTIONS=$count\n";
}finally{$cleanup($dir);}
