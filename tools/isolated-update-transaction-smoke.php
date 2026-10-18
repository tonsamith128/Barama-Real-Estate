<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/VerifiedUpdateBackupV2.php';
require dirname(__DIR__) . '/application/app/Services/Updates/IsolatedUpdateTransactionV1.php';

use Barama\Services\Updates\IsolatedUpdateTransactionV1 as Txn;

$assertions = 0;
$check = static function (bool $ok, string $name) use (&$assertions): void {
    if (!$ok) {fwrite(STDERR, "FAILED=" . $name . "\n");exit(1);}
    $assertions++;
};
$expect = static function (callable $f, string $prefix) use ($check): void {
    try {$f(); $check(false, 'Expected failure ' . $prefix);}
    catch (RuntimeException $e) {
        $check(str_starts_with($e->getMessage(), $prefix), 'Reject ' . $prefix . ', got ' . $e->getMessage());
    }
};
$clean = static function (string $p) use (&$clean): void {
    if (is_link($p) || is_file($p)) { @unlink($p);return; }
    if (!is_dir($p)) return;
    foreach (scandir($p) ?: [] as $name) {
        if ($name!=='.'&&$name!=='..')$clean($p.'/'.$name);
    }
    @rmdir($p);
};

$root='/tmp/barama-oneclick-txn-'.bin2hex(random_bytes(10));
$site=$root.'/site';$vault=$root.'/vault';
$target = static fn(string $path, ?string $old, string $new):array=>[
    'path'=>$path,'bytes'=>strlen($new),
    'installed_sha256'=>$old===null?null:hash('sha256',$old),
    'archive_sha256'=>hash('sha256',$new),
];
$make = static fn(array $targets):array=>[
    'status'=>'verified_plan_only','installation_enabled'=>false,
    'archive_sha256'=>str_repeat('a',64),
    'installed_version'=>'1.0.0','target_version'=>'1.0.1',
    'files'=>count($targets),'targets'=>$targets,
];
try {
    mkdir($root,0700); mkdir($site,0700);mkdir($vault,0700);mkdir($site.'/assets',0700);
    mkdir($site.'/storage',0700);
    file_put_contents($site.'/VERSION','1.0.0');
    file_put_contents($site.'/assets/app.css','oldCSS');
    file_put_contents($site.'/storage/customer.json','CUSTOMER_DATA_PRESERVED');
    $p=$make([$target('VERSION','1.0.0','1.0.1'),$target('assets/app.css','oldCSS','newCSS'),$target('assets/new.css',null,'fresh')]);
    $data=['VERSION'=>'1.0.1','assets/app.css'=>'newCSS','assets/new.css'=>'fresh'];
    $success=Txn::rehearse($site,$vault,$p,$data);
    $check($success['status']==='ISOLATED_FIXTURE_TRANSACTION_PASS','Success');
    $check($success['files_changed']===3,'Three changed');
    $check($success['database_writes']===false && $success['automatic_installation_enabled']===false,'Disabled');
    $check(file_get_contents($site.'/VERSION')==='1.0.1','New version');
    $check(file_get_contents($site.'/assets/app.css')==='newCSS','New css');
    $check(file_get_contents($site.'/assets/new.css')==='fresh','New file');
    $check(file_get_contents($site.'/storage/customer.json')==='CUSTOMER_DATA_PRESERVED','Data untouched');
    // Create a fresh fixture baseline for injected failure (old versions restored, additions removed).
    file_put_contents($site.'/VERSION','1.0.0');file_put_contents($site.'/assets/app.css','oldCSS');unlink($site.'/assets/new.css');
    $expect(static fn()=>Txn::rehearse($site,$vault,$p,$data,2),'TRANSACTION_FIXTURE_ROLLED_BACK:TRANSACTION_INJECTED_FAILURE');
    $check(file_get_contents($site.'/VERSION')==='1.0.0','Recovered VERSION');
    $check(file_get_contents($site.'/assets/app.css')==='oldCSS','Recovered CSS');
    $check(!file_exists($site.'/assets/new.css'),'Addition absent');
    $check(file_get_contents($site.'/storage/customer.json')==='CUSTOMER_DATA_PRESERVED','Data preserved after rollback');
    $backupJobs=glob($vault.'/backup-*')?:[];
    $check(count($backupJobs)===2,'Two private backup jobs');
    foreach($backupJobs as $job) {
        $journal=json_decode((string)file_get_contents($job.'/transaction.json'),true);
        $check(in_array($journal['state']??'',['fixture_committed','fixture_rolled_back'],true),'Journal state');
        $check((fileperms($job.'/transaction.json')&0077)===0,'Private journal');
    }
    $expect(static fn()=>Txn::rehearse('/www/wwwroot/baramarealestate.tonsamith.com',$vault,$p,$data),'TRANSACTION_FIXTURE_REQUIRED');
    $wrong=$p;$wrong['targets'][1]['path']='../config/database.php';
    $expect(static fn()=>Txn::rehearse($site,$vault,$wrong,$data),'TRANSACTION_DIGEST_OR_PLAN_INVALID');
    $wrong=$p;$wrong['targets'][0]['installed_sha256']=str_repeat('f',64);
    $expect(static fn()=>Txn::rehearse($site,$vault,$wrong,$data),'TRANSACTION_BASELINE_CHANGED');
    $wrongData=$data;$wrongData['assets/app.css']='bad hash';
    $expect(static fn()=>Txn::rehearse($site,$vault,$p,$wrongData),'TRANSACTION_DIGEST_OR_PLAN_INVALID');
    symlink('/tmp',$site.'/assets/link');
    $linkPlan=$make([$target('assets/link/escape.txt',null,'bad')]);
    $expect(static fn()=>Txn::rehearse($site,$vault,$linkPlan,['assets/link/escape.txt'=>'bad']),'TRANSACTION_SYMLINK_DENIED');
    unlink($site.'/assets/link');
    $lock=fopen($vault.'/transaction.lock','cb');flock($lock,LOCK_EX|LOCK_NB);
    $expect(static fn()=>Txn::rehearse($site,$vault,$p,$data),'TRANSACTION_LOCK_BUSY');
    flock($lock,LOCK_UN);fclose($lock);
    $expect(static fn()=>Txn::rehearse($site,$vault,$p,$data,4),'TRANSACTION_INJECTION_INVALID');
    $check(file_get_contents($site.'/VERSION')==='1.0.0','Failed prechecks do not modify data');
    echo 'ISOLATED_TRANSACTION_REHEARSAL=PASS'.PHP_EOL;
    echo 'INJECTED_FAILURE_AUTOMATIC_RECOVERY=PASS'.PHP_EOL;
    echo 'PRIVATE_SNAPSHOT_JOURNAL=PASS'.PHP_EOL;
    echo 'LIVE_WEBSITE_WRITES=NO'.PHP_EOL;
    echo 'REAL_DATABASE_WRITES=NO'.PHP_EOL;
    echo 'TEST_ASSERTIONS='.$assertions.PHP_EOL;
} finally { $clean($root); }
