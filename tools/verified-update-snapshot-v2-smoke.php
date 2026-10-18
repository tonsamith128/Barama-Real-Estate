<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/VerifiedUpdateBackupV2.php';
use Barama\Services\Updates\VerifiedUpdateBackupV2;

$base = sys_get_temp_dir() . '/barama-oneclick-rehearsal-' . bin2hex(random_bytes(9));
$site = $base . '/site';
$vault = $base . '/vault';
$assertions = 0;
$assert = static function (bool $ok, string $label) use (&$assertions): void {
    if (!$ok) {
        fwrite(STDERR, "STOP=FIXTURE_TEST_FAILED name=" . $label . PHP_EOL);
        exit(1);
    }
    ++$assertions;
};
$reject = static function (callable $cb, string $prefix) use ($assert): void {
    try { $cb(); $assert(false, 'Expected failure ' . $prefix); }
    catch (\RuntimeException $e) {
        $assert(str_starts_with($e->getMessage(), $prefix), 'Refusal ' . $prefix);
    }
};
$clean = static function (string $dir) use (&$clean): void {
    if (is_link($dir) || is_file($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') $clean($dir . '/' . $name);
    }
    @rmdir($dir);
};
try {
    mkdir($base,0700);
    mkdir($site,0700);
    mkdir($site.'/assets',0700);
    mkdir($site.'/application',0700);
    mkdir($site.'/application/app',0700);
    mkdir($vault,0700);
    file_put_contents($site.'/VERSION','1.0.0');
    file_put_contents($site.'/assets/main.css','.old{color:blue}');
    $oldCss = '.old{color:blue}';
    $make = static function (array $targets): array {
        return [
            'status'=>'verified_plan_only', 'installation_enabled'=>false,
            'archive_sha256'=>str_repeat('a',64),
            'installed_version'=>'1.0.0','target_version'=>'1.0.1',
            'files'=>count($targets),'targets'=>$targets,
        ];
    };
    $target = static fn(string $path, ?string $old, string $new) => [
        'path'=>$path,'bytes'=>strlen($new),
        'installed_sha256'=>$old === null ? null : hash('sha256',$old),
        'archive_sha256'=>hash('sha256',$new),
    ];
    $plan=$make([
       $target('VERSION','1.0.0','1.0.1'),
       $target('assets/main.css',$oldCss,'.new{color:blue}'),
       $target('application/app/NewClass.php',null,'<?php /* fixture */'),
    ]);
    $out=VerifiedUpdateBackupV2::prepare($plan,$site,$vault);
    $assert($out['status']==='PRIVATE_FILE_BACKUP_VERIFIED','Backup created');
    $assert($out['backed_up_files']===2,'Two baseline files saved');
    $assert($out['bytes']===strlen('1.0.0')+strlen($oldCss),'Backup bytes');
    $assert($out['database_backup']==='NOT_INCLUDED' && $out['installation_enabled']===false, 'No install and DB unchanged');
    $job=$vault.'/'.$out['backup_id'];
    $assert(VerifiedUpdateBackupV2::verify($job,$out['receipt_sha256'])===true,'Receipt and files reverified');
    $assert((fileperms($job)&0077)===0 && (fileperms($job.'/receipt.json')&0077)===0,'Private permissions');
    $manifest=json_decode(file_get_contents($job.'/receipt.json'),true,12,JSON_THROW_ON_ERROR);
    $assert(($manifest['file_count']??null)===3 && ($manifest['existing_file_count']??null)===2,'Accurate counts');
    $assert(is_file($job.'/files/'.hash('sha256','assets/main.css').'.bin'),'Code backup located privately');
    // Isolated recovery rehearsal: restore into the disposable fixture ONLY.
    file_put_contents($site.'/assets/main.css','new-live-fixture');
    $backupFile=$job.'/files/'.hash('sha256','assets/main.css').'.bin';
    copy($backupFile,$site.'/assets/main.css');
    $assert(file_get_contents($site.'/assets/main.css')===$oldCss,'Disposable recovery restored original');
    $assert(file_get_contents($site.'/VERSION')==='1.0.0','Other version untouched');
    $wrong=$plan;
    $wrong['installation_enabled']=true;
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($wrong,$site,$vault),'BACKUP_PLAN_INVALID');
    $wrong=$plan;
    $wrong['targets'][0]['path']='../sneaky.php';
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($wrong,$site,$vault),'BACKUP_PATH_DENIED');
    $wrong=$plan;
    $wrong['targets'][0]['path']='application/database/migrations/0999.php';
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($wrong,$site,$vault),'BACKUP_PATH_DENIED');
    $wrong=$plan;
    $wrong['targets'][1]['installed_sha256']=str_repeat('f',64);
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($wrong,$site,$vault),'BACKUP_FILE_BASELINE_CHANGED');
    symlink('/tmp',$site.'/assets/external');
    $wrong=$make([$target('assets/external/evil.css',null,'a')]);
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($wrong,$site,$vault),'BACKUP_TARGET_SYMLINK');
    unlink($site.'/assets/external');
    $reject(static fn() => VerifiedUpdateBackupV2::prepare($plan,$site,$site),'BACKUP_ROOT_OVERLAP');
    $tamper=$backupFile;
    file_put_contents($tamper,'tampered-backup');
    $reject(static fn() => VerifiedUpdateBackupV2::verify($job,$out['receipt_sha256']),'BACKUP_COPY_TAMPERED');
    // No customer application or database was present or modified by this test.
    echo 'REHEARSAL_FIXTURE_ONLY=YES'.PHP_EOL;
    echo 'DISPOSABLE_CODE_BACKUP_AND_RESTORE=PASS'.PHP_EOL;
    echo 'UNSAFE_PATH_AND_TAMPER_REJECTION=PASS'.PHP_EOL;
    echo 'LIVE_WEBSITE_WRITES=NO'.PHP_EOL;
    echo 'DATABASE_WRITES=NO'.PHP_EOL;
    echo 'ONECLICK_STAGE_BACKUP_REHEARSAL=PASS'.PHP_EOL;
    echo 'TEST_ASSERTIONS='.$assertions.PHP_EOL;
} finally {
    $clean($base);
}
