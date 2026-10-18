<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/StagingUpdateReadinessV1.php';
use Barama\Services\Updates\StagingUpdateReadinessV1;

$count = 0;
$check = static function (bool $ok, string $name) use (&$count): void {
    if (!$ok) throw new RuntimeException('READINESS_FIXTURE_FAILED: ' . $name);
    $count++;
};
$reject = static function (callable $cb, array $errors) use ($check): void {
    try { $cb(); } catch (RuntimeException $e) {
        $check(in_array($e->getMessage(), $errors, true), 'Deny untrusted root');
        return;
    }
    throw new RuntimeException('READINESS_FAIL_OPEN');
};
$clean = static function (string $path) use (&$clean): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') $clean($path.'/'.$name);
    }
    @rmdir($path);
};
$base='/tmp/barama-staging-readiness-'.bin2hex(random_bytes(8));
$site=$base.'/site';
try {
    mkdir($base,0700);
    mkdir($site,0700);
    mkdir($site.'/application');
    mkdir($site.'/application/app');
    mkdir($site.'/application/app/Services');
    $serviceDir=$site.'/application/app/Services/Updates';
    mkdir($serviceDir);
    file_put_contents($site.'/VERSION',"0.9.22-dev\n");
    $classes = ['SignedReleaseInspector.php','TrustedReleaseFeed.php',
        'VerifiedReleaseFilePlanV2.php','VerifiedUpdateBackupV2.php',
        'PrivateDatabaseBackupV2.php','IsolatedUpdateTransactionV1.php',
        'IsolatedMysqlRecoveryV1.php'];
    foreach ($classes as $class) file_put_contents($serviceDir.'/'.$class,'<?php // disposable fixture');
    mkdir($site.'/storage');
    $customer = $site.'/storage/customer.json';
    file_put_contents($customer,'CUSTOMER_DATA_UNCHANGED');
    $digest=hash_file('sha256',$customer);
    $result=StagingUpdateReadinessV1::inspect($site);
    $check($result['status']==='READ_ONLY_STAGING_PRECHECK','Read-only status');
    $check($result['site']==='DISPOSABLE_FIXTURE','Fixture only');
    $check($result['installed_version']==='0.9.22-dev','Version');
    $check($result['components_present']===true && count($result['components'])===7,'Component manifest');
    $check($result['signed_release_verified']===false,'No signature claim');
    $check(in_array('SIGNED_RELEASE_NOT_STAGED',$result['blockers'],true),'No signed release');
    $check(in_array('REAL_DATABASE_RESTORE_NOT_VERIFIED',$result['blockers'],true),'SQL restore gate');
    $check(in_array('LIVE_UPDATE_ROLLBACK_NOT_VERIFIED',$result['blockers'],true),'Recovery gate');
    $check(in_array('RELEASE_ENTITLEMENT_AND_SIGNATURE_NOT_VERIFIED',$result['blockers'],true),'Entitlement gate');
    $check($result['ready_to_install']===false && $result['automatic_update_enabled']===false,'No update');
    $check(!$result['database_connections'] && !$result['database_writes'] && !$result['website_writes'],'No changes');
    $check(hash_file('sha256',$customer)===$digest,'Customer data preserved');
    $check(!is_dir($site.'/application/storage'),'Does not create release directory');
    $reject(static fn()=>StagingUpdateReadinessV1::inspect('/www/wwwroot/baramarealestate.tonsamith.com'),['READINESS_ROOT_UNSAFE','READINESS_STAGING_ONLY']);
    $reject(static fn()=>StagingUpdateReadinessV1::inspect('/tmp'),['READINESS_STAGING_ONLY']);
    $bad=$serviceDir.'/VerifiedUpdateBackupV2.php';
    unlink($bad); symlink('/etc/passwd',$bad);
    $changed=StagingUpdateReadinessV1::inspect($site);
    $check(!$changed['components_present'],'Symlink component rejected');
    $check(in_array('UPDATER_COMPONENTS_MISSING',$changed['blockers'],true),'Missing component blocked');
    unlink($bad);
    file_put_contents($bad,'<?php // fixture');
    file_put_contents($site.'/VERSION','');
    $reject(static fn()=>StagingUpdateReadinessV1::inspect($site),['READINESS_VERSION_UNAVAILABLE']);
    echo "STAGING_READINESS_FIXTURE=PASS\n";
    echo "FAIL_CLOSED_WITHOUT_SIGNED_RELEASE=PASS\n";
    echo "REAL_SITE_DATABASE_CONNECTIONS=NONE\n";
    echo "REAL_SITE_WRITES=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo 'TEST_ASSERTIONS='.$count."\n";
} finally { $clean($base); }
