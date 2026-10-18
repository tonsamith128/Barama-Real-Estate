<?php

declare(strict_types=1);

// Disposable SQL-text fixture only: no DB connections, dumps, restores or installations.
require __DIR__.'/../application/app/Services/Updates/PrivateDatabaseBackupV2.php';
use Barama\Services\Updates\PrivateDatabaseBackupV2;

$prefix = sys_get_temp_dir().'/barama-db-safety-fixture-'.bin2hex(random_bytes(7));
$site = $prefix.'/site';
$vault = $prefix.'/vault';
$tests = 0;
$check = static function (bool $condition, string $label) use (&$tests): void {
    if (!$condition) throw new RuntimeException('FIXTURE_ASSERTION_FAILED: '.$label);
    $tests++;
};
$reject = static function (callable $callable, string $expected) use ($check): void {
    try { $callable(); }
    catch (RuntimeException $e) {
        $check(str_starts_with($e->getMessage(), $expected), 'Reject '.$expected);
        return;
    }
    throw new RuntimeException('FIXTURE_DID_NOT_REJECT: '.$expected);
};
$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $child) {
        if ($child !== '.' && $child !== '..') $cleanup($path.'/'.$child);
    }
    rmdir($path);
};
try {
    mkdir($prefix,0700); mkdir($site,0700); mkdir($vault,0700);
    $config = ['site_root'=>$site,'vault_root'=>$vault,'database'=>'barama_fixture'];
    $export = static function(callable $sink): void {
        $sink("-- isolated fake SQL fixture ONLY\n");
        $sink("CREATE TABLE example_fixture (id INTEGER NOT NULL);\n");
    };
    $result = PrivateDatabaseBackupV2::prepare($config,$export);
    $check($result['status']==='DISPOSABLE_DATABASE_FIXTURE_VERIFIED','Fixture labeled correctly');
    $check($result['restore_tested']===false && $result['installation_enabled']===false,'No live restores or installations');
    $job = $vault.'/'.$result['job_id'];
    $check(PrivateDatabaseBackupV2::verify($job,$result['receipt_sha256']),'Receipt + dump verify');
    $check((fileperms($job)&0077)===0 && (fileperms($job.'/database.sql')&0077)===0,'Private perms');
    $receipt = json_decode(file_get_contents($job.'/receipt.json'),true,10,JSON_THROW_ON_ERROR);
    $check(($receipt['state']??null)==='fixture_only' && $receipt['database_writes']===false,'Receipt states fixture');
    $check(!is_file($job.'/.client.cnf'),'No credential file');
    $check(($receipt['database_name_sha256']??null)===hash('sha256','barama_fixture'),'Database name hashed');
    $reject(static fn()=>PrivateDatabaseBackupV2::prepare(array_merge($config,['database'=>'invalid-name']),$export), 'DB_BACKUP_NAME_INVALID');
    $reject(static fn()=>PrivateDatabaseBackupV2::prepare($config,static fn(callable $sink)=>null),'DB_BACKUP_EMPTY_OR_INCOMPLETE');
    $reject(static fn()=>PrivateDatabaseBackupV2::prepare(array_merge($config,['vault_root'=>$site]),$export),'DB_BACKUP_OVERLAPPING_ROOTS');
    $fake = sys_get_temp_dir().'/barama-db-other-'.bin2hex(random_bytes(6)); mkdir($fake,0700);
    $reject(static fn()=>PrivateDatabaseBackupV2::prepare(array_merge($config,['site_root'=>$fake]),$export),'DB_BACKUP_FIXTURE_ROOT_DENIED');
    $cleanup($fake);
    $reject(static fn()=>PrivateDatabaseBackupV2::prepare($config),'DB_BACKUP_STAGING_ONLY');
    $sql = $job.'/database.sql'; $original = file_get_contents($sql);
    file_put_contents($sql,$original.'tampered');
    $reject(static fn()=>PrivateDatabaseBackupV2::verify($job,$result['receipt_sha256']),'DB_BACKUP_SQL_TAMPERED');
    file_put_contents($sql,$original);
    $check(PrivateDatabaseBackupV2::verify($job,$result['receipt_sha256']),'Original restored');
    $receiptFile=$job.'/receipt.json'; $record=file_get_contents($receiptFile);
    file_put_contents($receiptFile,$record.'\n');
    $reject(static fn()=>PrivateDatabaseBackupV2::verify($job,$result['receipt_sha256']),'DB_BACKUP_RECEIPT_TAMPERED');
    file_put_contents($receiptFile,$record);
    $check(PrivateDatabaseBackupV2::verify($job,$result['receipt_sha256']),'Receipt restored');
    $check(!file_exists($site.'/database.sql'),'No fixture SQL installed in site');
    $check(!file_exists($site.'/application'),'No application file modifications');
    echo "REHEARSAL_FIXTURE_ONLY=YES\n";
    echo "DISPOSABLE_DATABASE_BACKUP_AND_VERIFY=PASS\n";
    echo "UNSAFE_DATABASE_NAME_AND_TAMPER_REJECTION=PASS\n";
    echo "DB_CONNECTIONS=NONE\n";
    echo "DATABASE_WRITES=NO\n";
    echo "STAGING_WEBSITE_WRITES=NO\n";
    echo "PRODUCTION_WEBSITE_WRITES=NO\n";
    echo "RESTORE_TESTED=NO\n";
    echo "AUTOMATIC_INSTALLATION_ENABLED=NO\n";
    echo "TEST_ASSERTIONS={$tests}\n";
    echo "DATABASE_SAFETY_REHEARSAL=PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR,'REHEARSAL_STOP_CODE='.$e->getMessage()."\n");
    exit(1);
} finally {
    $cleanup($prefix);
}
