<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/OneClickFullSiteSnapshotV1.php';
use Barama\Services\Updates\OneClickFullSiteSnapshotV1 as S;

function must(bool $pass, string $name): void {
    if (!$pass) throw new RuntimeException('FULLSITE_FIXTURE_FAIL:' . $name);
    echo 'PASS=' . $name . "\n";
}
function reject(callable $f, string $name): void {
    try { $f(); } catch (RuntimeException) {
        must(true, $name); return;
    }
    throw new RuntimeException('FULLSITE_FIXTURE_FAIL_OPEN:' . $name);
}
function tidy(string $path): void {
    if (is_link($path) || is_file($path)) { if (!unlink($path)) throw new RuntimeException('FULLSITE_CLEANUP_FAILED'); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $p) {
        if ($p !== '.' && $p !== '..') tidy($path . '/' . $p);
    }
    if (!rmdir($path)) throw new RuntimeException('FULLSITE_CLEANUP_FAILED');
}
$base = '/tmp/barama-fullsite-fixture-' . bin2hex(random_bytes(8));
$site = $base . '/site';
$vault = $base . '/vault';
try {
    mkdir($base,0700);mkdir($site,0700);mkdir($vault,0700);
    mkdir($site.'/storage',0700);
    mkdir($site.'/storage/uploads',0700);
    mkdir($site.'/storage/empty-directory',0700);
    file_put_contents($site.'/VERSION',"0.9.22-dev\n");
    file_put_contents($site.'/storage/uploads/customer.txt',"Customer payload\n");
    file_put_contents($site.'/storage/environment.php',"secret-config-private\n");
    $before = hash_file('sha256',$site.'/storage/environment.php');
    $existing = scandir($vault);
    must($existing === ['.','..'], 'class_does_not_auto_backup');
    reject(fn()=>S::prepare($site,$vault,'FORGED'),'requires_explicit_snapshot_consent');
    reject(fn()=>S::prepare('/www/wwwroot/baramarealestate.tonsamith.com',$vault,'CREATE_STAGING_PRIVATE_FULL_SITE_SNAPSHOT_ONLY'),'production_root_denied');
    reject(fn()=>S::prepare($site,'/root','CREATE_DISPOSABLE_FULL_SITE_SNAPSHOT_ONLY'),'foreign_vault_denied');
    $r=S::prepare($site,$vault,'CREATE_DISPOSABLE_FULL_SITE_SNAPSHOT_ONLY');
    must($r['status']==='FULL_SITE_FILE_COPY_VERIFIED_NOT_RESTORED' && $r['file_count']===3,
        'full_site_file_inventory_copied');
    must(!$r['database_included'] && !$r['consistent_db_checkpoint_verified']
        && !$r['restore_tested'] && !$r['install_authorized'],
        'snapshot_does_not_claim_sql_or_install_authorization');
    must(S::verify($site,$vault,$r['snapshot_id'],$r['receipt_sha256']),
        'whole_copy_reverified');
    must($r['directory_count']===3, 'empty_and_nested_directories_recorded');
    $record=json_decode((string)file_get_contents($vault.'/'.$r['snapshot_id'].'/receipt.json'),true,16,JSON_THROW_ON_ERROR);
    must(in_array('storage/empty-directory',array_column($record['directories'],'path'),true),
        'empty_directory_is_preserved_in_inventory');

    must(hash_file('sha256',$site.'/storage/environment.php') === $before,
        'website_never_changed');
    reject(fn()=>S::verify($site,$vault,$r['snapshot_id'],str_repeat('f',64)),
        'wrong_receipt_digest_denied');
    $job=$vault.'/'.$r['snapshot_id'];
    $copy=$job.'/files/'.hash('sha256','storage/uploads/customer.txt').'.bin';
    file_put_contents($copy,'tampered');
    reject(fn()=>S::verify($site,$vault,$r['snapshot_id'],$r['receipt_sha256']),
        'copy_tamper_denied');
    file_put_contents($site.'/NEW.txt','new baseline for separate snapshot');
    symlink('/etc/passwd',$site.'/storage/uploads/external');
    reject(fn()=>S::prepare($site,$vault,'CREATE_DISPOSABLE_FULL_SITE_SNAPSHOT_ONLY'),
        'symlink_in_website_denied');
    echo "FULLSITE_NO_LIVE_SITE_OR_SQL_ACCESS=PASS\n";
    echo "ONECLICK_FULL_SITE_SNAPSHOT_FIXTURE=PASS\n";
} finally {
    tidy($base);
}
