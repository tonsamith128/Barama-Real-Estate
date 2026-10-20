<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/OneClickFullSiteSnapshotV1.php';
require dirname(__DIR__) . '/application/app/Services/Updates/OneClickFullSiteRestoreRehearsalV1.php';
use Barama\Services\Updates\OneClickFullSiteSnapshotV1 as S;
use Barama\Services\Updates\OneClickFullSiteRestoreRehearsalV1 as R;

function must(bool $test,string $label):void {
    if (!$test) throw new RuntimeException('FULLSITE_RESTORE_FAIL:'.$label);
    echo 'PASS='.$label."\n";
}
function denied(callable $f,string $label):void {
    try {$f();}catch(RuntimeException){must(true,$label);return;}
    throw new RuntimeException('FULLSITE_RESTORE_FAIL_OPEN:'.$label);
}
function cleanup(string $p):void {
    if(is_link($p)||is_file($p)){unlink($p);return;}
    if(!is_dir($p))return;
    foreach(scandir($p)?:[] as $n){
        if($n!=='.'&&$n!=='..')cleanup($p.'/'.$n);
    }
    rmdir($p);
}
$from='/tmp/barama-fullsite-fixture-'.bin2hex(random_bytes(8));
$into='/tmp/barama-fullsite-restore-fixture-'.bin2hex(random_bytes(8));
$site=$from.'/site';$vault=$from.'/vault';$dest=$into.'/site';
try {
    mkdir($from,0700);mkdir($site,0700);mkdir($vault,0700);
    mkdir($site.'/storage',0700);
    mkdir($site.'/storage/uploads',0700);
    mkdir($site.'/storage/empty',0700);
    file_put_contents($site.'/VERSION',"0.9.22-dev\n");
    file_put_contents($site.'/storage/uploads/客户.bin',random_bytes(13));
    file_put_contents($site.'/storage/uploads/empty.txt','');
    chmod($site.'/VERSION',0640);
    chmod($site.'/storage/uploads/客户.bin',0600);
    $baseline=hash_file('sha256',$site.'/storage/uploads/客户.bin');
    $snapshot=S::prepare($site,$vault,'CREATE_DISPOSABLE_FULL_SITE_SNAPSHOT_ONLY');
    must($snapshot['file_count']===3,'three_files_snapshotted');
    must($snapshot['directory_count']===3,'empty_directory_inventory_captured');
    mkdir($into,0700);mkdir($dest,0700);
    denied(fn()=>R::restoreToDisposableClone($site,$vault,$snapshot['snapshot_id'],$snapshot['receipt_sha256'],'/www/wwwroot/baramastaging.tonsamith.com'),'reject_real_staging_as_restore_target');
    denied(fn()=>R::restoreToDisposableClone($site,$vault,$snapshot['snapshot_id'],str_repeat('f',64),$dest),'reject_wrong_receipt_digest');
    $r=R::restoreToDisposableClone($site,$vault,$snapshot['snapshot_id'],$snapshot['receipt_sha256'],$dest);
    must($r['status']==='FULL_SITE_FILES_RESTORED_IN_DISPOSABLE_CLONE'
        && $r['files']===3 && $r['directories']===3,'entire_site_restored_into_private_clone');
    must(file_get_contents($dest.'/VERSION')==="0.9.22-dev\n"
        && hash_file('sha256',$dest.'/storage/uploads/客户.bin')===$baseline,
        'exact_data_and_version_restored');
    must(is_dir($dest.'/storage/empty')
        && ((fileperms($dest.'/VERSION')&0777)===0640)
        && ((fileperms($dest.'/storage/uploads/客户.bin')&0777)===0600),
        'empty_directory_and_file_modes_restored');
    must(hash_file('sha256',$site.'/storage/uploads/客户.bin')===$baseline,
        'original_site_untouched');
    must($r['database_accessed']===false && $r['site_modified']===false
        && $r['sql_consistent_checkpoint_verified']===false
        && $r['install_enabled']===false,'no_false_sql_or_installation_claims');
    denied(fn()=>R::restoreToDisposableClone($site,$vault,$snapshot['snapshot_id'],$snapshot['receipt_sha256'],$dest),'reject_nonempty_restore_target');
    echo "ONECLICK_FULLSITE_RESTORE_REHEARSAL_FIXTURE=PASS\n";
} finally {
    cleanup($into);cleanup($from);
}
