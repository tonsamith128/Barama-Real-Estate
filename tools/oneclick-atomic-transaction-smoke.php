<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/Updates/OneClickAtomicTransactionV1.php';

use Barama\Services\Updates\OneClickAtomicTransactionV1 as T;

function must(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL_' . $label);
    echo 'PASS=' . $label . "\n";
}
function blocked(callable $fn, string $message, string $label): void {
    try { $fn(); } catch (RuntimeException $e) {
        must(str_starts_with($e->getMessage(), $message), $label);
        return;
    }
    throw new RuntimeException('FAIL_OPEN_' . $label);
}
function makeFixture(): array {
    $base = '/tmp/barama-oneclick-transaction-fixture-' . bin2hex(random_bytes(7));
    mkdir($base,0700);
    $site=$base.'/site';$vault=$base.'/vault';
    mkdir($site,0700);mkdir($vault,0700);
    mkdir($site.'/assets',0700);mkdir($site.'/assets/css',0700);
    file_put_contents($site.'/VERSION', "0.9.22-dev\n");
    file_put_contents($site.'/assets/css/app.css', "before\n");
    $new=['VERSION'=>"0.9.23\n",'assets/css/app.css'=>"after\n", 'assets/js/new.js'=>"hello\n"];
    mkdir($site.'/assets/js', 0700);
    $old = [
        'VERSION'=>hash_file('sha256',$site.'/VERSION'),
        'assets/css/app.css'=>hash_file('sha256',$site.'/assets/css/app.css'),
        'assets/js/new.js'=>null,
    ];
    $targets=[];
    foreach($new as $path=>$content) $targets[]=['path'=>$path,'archive_sha256'=>hash('sha256',$content),'installed_sha256'=>$old[$path]];
    $plan=['status'=>'verified_plan_only','archive_sha256'=>str_repeat('a',64),
        'installed_version'=>'0.9.22-dev','target_version'=>'0.9.23',
        'files'=>count($targets),'installation_enabled'=>false,'targets'=>$targets];
    return [$base,$site,$vault,$plan,$new];
}
function cleanup(string $dir): void {
    if(is_link($dir)||is_file($dir)) {unlink($dir);return;}
    foreach(scandir($dir)?:[] as $i)if($i!=='.'&&$i!=='..')cleanup($dir.'/'.$i);
    rmdir($dir);
}
$fixtures=[];
try {
    [$root,$site,$vault,$plan,$new]=makeFixture();$fixtures[]=$root;
    blocked(fn()=>T::apply('/www/wwwroot/baramastaging.tonsamith.com',$vault,$plan,$new), 'FIXTURE_ROOT_DENIED','real_staging_root_denied');
    blocked(fn()=>T::apply('/www/wwwroot/baramarealestate.tonsamith.com',$vault,$plan,$new), 'FIXTURE_ROOT_DENIED','production_root_denied');
    blocked(fn()=>T::apply($site,'/root', $plan,$new), 'FIXTURE_ROOT_DENIED','arbitrary_vault_denied');
    $evil=$plan;$evil['targets'][1]['path']='assets/../../storage/app/license/license.php';
    $evilContents=$new;$evilContents['assets/../../storage/app/license/license.php']=$evilContents['assets/css/app.css'];unset($evilContents['assets/css/app.css']);
    blocked(fn()=>T::apply($site,$vault,$evil,$evilContents),'FIXTURE_PLAN_ENTRY_INVALID','traversal_rejected');
    $evil=$plan;$evil['targets'][1]['archive_sha256']=str_repeat('b',64);
    blocked(fn()=>T::apply($site,$vault,$evil,$new),'FIXTURE_ARCHIVE_TARGET_MISMATCH','tampered_release_bytes_rejected');
    $result=T::apply($site,$vault,$plan,$new);
    must($result['status']==='ISOLATED_FILE_INSTALL_COMMITTED' && $result['database_writes']===false,'real_atomic_file_application_disposable_site');
    foreach($new as $p=>$b)must(file_get_contents($site.'/'.$p)===$b,'result_hash_'.$p);
    blocked(fn()=>T::recover($site,$vault,$result['transaction_id']),'RECOVERY_NOT_FILE_ONLY_OR_NOT_PENDING','completed_transaction_cannot_rollback_implicitly');
    blocked(fn()=>T::recoverCommittedAfterSqlFixture($site,$vault,$result['transaction_id'],'FORGED'),
        'COMMITTED_RECOVERY_OPERATOR_AND_SQL_PROOF_REQUIRED','unverified_sql_recovery_denied');
    $completed = T::recoverCommittedAfterSqlFixture(
        $site,$vault,$result['transaction_id'],'DISPOSABLE_SQL_RESTORED_AND_VERSION_VERIFIED');
    must($completed['status']==='ISOLATED_COMPLETED_CODE_RESTORED_AFTER_SQL',
        'completed_transaction_restored_from_verified_journal');
    must(file_get_contents($site.'/VERSION')==="0.9.22-dev\n"
        && file_get_contents($site.'/assets/css/app.css')==="before\n"
        && !file_exists($site.'/assets/js/new.js'),
        'completed_transaction_exact_baseline_recovered');
    blocked(fn()=>T::recoverCommittedAfterSqlFixture($site,$vault,$result['transaction_id'],
        'DISPOSABLE_SQL_RESTORED_AND_VERSION_VERIFIED'),
        'COMMITTED_RECOVERY_NOT_ELIGIBLE','completed_recovery_replay_denied');

    [$root,$site,$vault,$plan,$new]=makeFixture();$fixtures[]=$root;
    $committed = T::apply($site,$vault,$plan,$new);
    blocked(fn()=>T::recoverCommittedAfterSqlFixture($site,$vault,$committed['transaction_id'],
        'DISPOSABLE_SQL_RESTORED_AND_VERSION_VERIFIED',1),
        'COMMITTED_RECOVERY_INJECTED_CRASH:', 'completed_recovery_crash_injected');
    must(file_get_contents($site.'/VERSION')==="0.9.23\n",
        'completed_recovery_preserves_new_version_until_files_restored');
    blocked(fn()=>T::apply($site,$vault,$plan,$new),
        'FIXTURE_INTERRUPTED_UPDATE_REQUIRES_RECOVERY',
        'competing_update_denied_during_completed_recovery');
    $resumed = T::recoverCommittedAfterSqlFixture($site,$vault,$committed['transaction_id'],
        'DISPOSABLE_SQL_RESTORED_AND_VERSION_VERIFIED');
    must($resumed['status']==='ISOLATED_COMPLETED_CODE_RESTORED_AFTER_SQL',
        'completed_recovery_resumes_after_crash');
    must(file_get_contents($site.'/VERSION')==="0.9.22-dev\n"
        && file_get_contents($site.'/assets/css/app.css')==="before\n"
        && !file_exists($site.'/assets/js/new.js'),
        'completed_recovery_exact_baseline_after_resume');

    [$root,$site,$vault,$plan,$new]=makeFixture();$fixtures[]=$root;
    $tx='';
    try {T::apply($site,$vault,$plan,$new,2);} catch (RuntimeException $e) {
        must(str_starts_with($e->getMessage(),'FIXTURE_INJECTED_PROCESS_CRASH:'),'injected_after_two_files');
        $tx=substr($e->getMessage(),strlen('FIXTURE_INJECTED_PROCESS_CRASH:'));
    }
    must(file_get_contents($site.'/VERSION')==="0.9.22-dev\n"
        && file_get_contents($site.'/assets/css/app.css')==="after\n"
        && file_get_contents($site.'/assets/js/new.js')==="hello\n",
        'version_stays_old_until_all_code_files_are_written');
    blocked(fn()=>T::apply($site,$vault,$plan,$new),'FIXTURE_INTERRUPTED_UPDATE_REQUIRES_RECOVERY','competing_update_blocked_after_crash');
    $recovered=T::recover($site,$vault,$tx);
    must($recovered['status']==='ISOLATED_FILE_CRASH_RECOVERED','power_loss_recovered_using_private_journal');
    must(file_get_contents($site.'/VERSION')==="0.9.22-dev\n" && file_get_contents($site.'/assets/css/app.css')==="before\n" && !file_exists($site.'/assets/js/new.js'),'exact_preupdate_files_restored');
    blocked(fn()=>T::recover($site,$vault,$tx),'RECOVERY_NOT_FILE_ONLY_OR_NOT_PENDING','replay_recovery_denied');
    $again=T::apply($site,$vault,$plan,$new);
    must($again['status']==='ISOLATED_FILE_INSTALL_COMMITTED','fresh_update_after_recovery_succeeds');

    [$root,$site,$vault,$plan,$new]=makeFixture();$fixtures[]=$root;
    try {T::apply($site,$vault,$plan,$new,1);} catch(RuntimeException $e) {
        $tx=substr($e->getMessage(),strlen('FIXTURE_INJECTED_PROCESS_CRASH:'));
    }
    file_put_contents($site.'/VERSION',"tampered third-party\n");
    blocked(fn()=>T::recover($site,$vault,$tx),'RECOVERY_UNEXPECTED_FILE_MODIFICATION','external_change_not_overwritten');

    [$root,$site,$vault,$plan,$new]=makeFixture();$fixtures[]=$root;
    unlink($site.'/assets/css/app.css');symlink('/etc/passwd',$site.'/assets/css/app.css');
    blocked(fn()=>T::apply($site,$vault,$plan,$new),'FIXTURE_SYMLINK_DENIED','symlink_target_denied');
    echo "ONECLICK_ATOMIC_TRANSACTION_ISOLATED_SMOKE=PASS\n";
} finally {
    foreach($fixtures as $folder) cleanup($folder);
}
