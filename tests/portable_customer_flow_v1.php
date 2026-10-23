<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableCustomerUpdateV1.php';

use Barama\Services\Updates\PortableCustomerUpdateV1 as Flow;

function checkIt(bool $yes,string $name):void {
    if (!$yes) throw new RuntimeException('FAIL='.$name);
    echo "PASS=".$name."\n";
}
function deny(callable $run,string $name):void {
    try {$run();} catch (RuntimeException) {echo "PASS=".$name."\n";return;}
    throw new RuntimeException('FAIL='.$name);
}
function deleteDir(string $path):void {
    if (is_link($path)||is_file($path)){unlink($path);return;}
    if (!is_dir($path))return;
    foreach(new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $item)deleteDir($item->getPathname());
    rmdir($path);
}
$dir=sys_get_temp_dir().'/barama-flow-test-'.bin2hex(random_bytes(8));
$site=$dir.'/website';
mkdir($site,0700,true);
file_put_contents($site.'/VERSION',"0.9.23\n");
define('BASE_PATH',$site);
define('APP_VERSION','0.9.23');
$_SERVER['DOCUMENT_ROOT']=$site;
try {
    $vault=Flow::vault(false);
    checkIt(!str_starts_with($vault,$site.'/') && !is_dir($vault),'sibling_private_vault_planned');
    $actual=Flow::vault(true);
    checkIt(is_dir($actual)&& (fileperms($actual)&0077)===0,'vault_created_mode_0700');
    // Most shared hosts cannot let web PHP create directories beside the
    // document root; the hosting account can safely pre-provision a private
    // 0700 sibling without granting write permission to the parent.
    chmod($dir,0555);
    try {
        checkIt(Flow::ready(),'preprovisioned_vault_usable_when_parent_read_only');
        checkIt(Flow::vault(false)===$actual,'existing_vault_reused_without_parent_write');
    } finally { chmod($dir,0700); }

    checkIt(Flow::status()['state']==='not_started','no_fake_success');
    file_put_contents($actual.'/current.json',json_encode(['schema'=>'not-trusted','state'=>'completed'])."\n");
    checkIt(Flow::status()['state']==='recovery_review_required','unknown_journal_denied');
    unlink($actual.'/current.json');
    file_put_contents($actual.'/active.json',json_encode([
        'schema'=>'barama-portable-active-v1','job_id'=>'job-'.str_repeat('b',32)
    ])."\n");
    checkIt(Flow::status()['state']==='recovery_review_required','orphaned_active_job_review_required');
    unlink($actual.'/active.json');
    // Directly served public installation locations must be rejected.
    $_SERVER['DOCUMENT_ROOT']=$dir;
    deny(fn()=>Flow::vault(true),'document_root_overlap_denied');
    $_SERVER['DOCUMENT_ROOT']=$site;
    echo "PORTABLE_CUSTOMER_FLOW_FIXTURES=PASS\n";
} finally {deleteDir($dir);}
