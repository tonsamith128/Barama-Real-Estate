<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickLifecycleV1.php';
use Barama\Services\Updates\OneClickLifecycleV1;

function check(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL=$name\n"); exit(1); }
    echo "PASS=$name\n";
}
$completed = ['state'=>'completed','updated_at'=>time()];
$v = OneClickLifecycleV1::project($completed,'0.9.24','0.9.24',false);
check($v['verified'] && $v['completed'] && $v['phase']==='Update installed successfully','exact_completed_version');
$v = OneClickLifecycleV1::project($completed,'0.9.23','0.9.24',false);
check($v['completed'] && !$v['verified'],'mismatched_version_denied');
$v = OneClickLifecycleV1::project($completed,'0.9.24',null,false);
check($v['completed'] && !$v['verified'],'old_session_unbound_target_not_claimed');
$v = OneClickLifecycleV1::project($completed,'0.9.24','0.9.24',true);
check(!$v['completed'] && !$v['verified'],'pending_queue_blocks_old_receipt');
$v = OneClickLifecycleV1::project(['state'=>'failed'],'0.9.24','0.9.24',false);
check($v['needs_review'] && !$v['verified'],'failure_is_not_success');
$v = OneClickLifecycleV1::project(['state'=>'recovery_review_required'],'0.9.24','0.9.24',false);
check($v['needs_review'] && !$v['completed'],'recovery_review_is_not_success');
$v = OneClickLifecycleV1::project(['state'=>'installing'],'0.9.23','0.9.24',true);
check($v['in_progress'] && !$v['verified'],'progress_no_synthetic_success');
$v = OneClickLifecycleV1::project(['state'=>'garbage'],'0.9.24','0.9.24',false);
check($v['state']==='unavailable' && !$v['verified'],'unknown_state_fails_closed');
$v = OneClickLifecycleV1::project($completed,'0.9.24','../0.9.24',false);
check(!$v['verified'],'invalid_target_rejected');
echo "AUTO_UPDATER_LIFECYCLE_TESTS=PASS\n";
