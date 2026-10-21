<?php

declare(strict_types=1);

require __DIR__ . '/../application/app/Services/Updates/DashboardInstallHandoffV1.php';
require __DIR__ . '/../application/app/Services/Updates/DashboardUpdateRequestV1.php';

use Barama\Services\Updates\DashboardInstallHandoffV1 as Guard;

const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
$now = 1800000000;
$baseFeed = [
    'status' => 'available', 'entitlement' => 'Eligible',
    'feed' => 'Signed release verified', 'version' => '0.9.24',
    'archive_sha256' => str_repeat('a', 64), 'at' => $now,
];
$baseCandidate = [
    'status' => 'PUBLISHER_RELEASE_AND_STAGED_ZIP_MATCH_READ_ONLY',
    'publisher_release_matched_to_staged_zip' => true,
    'publisher_entitlement_confirmed_for_discovery' => true,
    'signed_zip_verified' => true,
    'candidate_version' => '0.9.24',
    'ready_to_install' => false, 'auto_install_enabled' => false,
    'blockers' => ['AUTOMATIC_INSTALLATION_DISABLED'],
];

function result(string $root, string $version, array $feed, array $candidate): array
{
    global $now;
    return Guard::review($root, $version, $feed, $candidate, $now);
}
function check(bool $yes, string $name): void
{
    if (!$yes) throw new RuntimeException('FAIL=' . $name);
    echo 'PASS=' . $name . "\n";
}
$ok = result(STAGING, '0.9.23', $baseFeed, $baseCandidate);
check($ok['state'] === 'CANDIDATE_VERIFIED_FOR_REVIEW' && $ok['candidate_reviewed'] === true, 'fresh_exact_signed_candidate_review');
foreach (['install_authorized','install_executed','root_worker_invoked','website_writes','database_writes'] as $key) {
    check($ok[$key] === false, 'non_execution_' . $key);
}
$roots = ['/', '/www/wwwroot/tonsamith.com', STAGING.'/', STAGING.'/../baramastaging.tonsamith.com', '/tmp/example'];
foreach ($roots as $i => $root) check(result($root,'0.9.23',$baseFeed,$baseCandidate)['state'] === 'BLOCKED', 'reject_other_root_'.$i);
foreach (['','0.9.24','0.9.25','0.9.23/../','0.9.22-dev?'] as $i => $installed) {
    check(result(STAGING,$installed,$baseFeed,$baseCandidate)['state'] === 'BLOCKED','reject_bad_current_'.$i);
}
$variants = [
    ['status','unavailable'], ['entitlement','Not entitled'], ['feed','No release published'],
    ['archive_sha256','ABC'], ['archive_sha256',str_repeat('A',64)], ['at',$now-121],
    ['at',$now+31], ['at','1800000000'], ['version','0.9.23'], ['version','0.9.22'],
    ['version','0.9.24/unsafe'],
];
foreach ($variants as $i => [$field,$value]) {
    $f=$baseFeed; $f[$field]=$value;
    check(result(STAGING,'0.9.23',$f,$baseCandidate)['state'] === 'BLOCKED','reject_feed_variant_'.$i);
}
$variants = [
    ['status','PUBLISHER_CANDIDATE_NOT_CONFIRMED'],
    ['publisher_release_matched_to_staged_zip',false],
    ['publisher_entitlement_confirmed_for_discovery',false],
    ['signed_zip_verified',false], ['candidate_version','0.9.25'],
];
foreach ($variants as $i => [$field,$value]) {
    $c=$baseCandidate; $c[$field]=$value;
    check(result(STAGING,'0.9.23',$baseFeed,$c)['state'] === 'BLOCKED','reject_candidate_variant_'.$i);
}
check(result(STAGING,'0.9.23',$baseFeed,[])['state']==='BLOCKED','reject_absent_candidate');
// Inputs that falsely self-declare installation enabled cannot grant privilege.
$c=$baseCandidate; $c['ready_to_install']=true; $c['auto_install_enabled']=true;
$review=result(STAGING,'0.9.23',$baseFeed,$c);
check(!$review['install_authorized'] && !$review['root_worker_invoked'],'ignore_untrusted_install_flags');
echo 'DASHBOARD_HANDOFF_READ_ONLY_SMOKE=PASS' . "\n";

echo "========== DISPOSABLE UNTRUSTED INBOX TESTS ==========\n";
putenv('BARAMA_HANDOFF_TEST=1');
$parent = '/tmp/barama-ticket-fixture-' . bin2hex(random_bytes(8));
$inbox = $parent . '/inbox';
$created = @mkdir($parent, 0711);
if (!$created) throw new RuntimeException('FIXTURE_PARENT_FAILED');
chmod($parent, 0711);
if (!@mkdir($inbox, 0700)) throw new RuntimeException('FIXTURE_INBOX_FAILED');
chmod($inbox, 0700);
try {
    $ticket = \Barama\Services\Updates\DashboardUpdateRequestV1::fixture(
        $ok, '0.9.23', $parent, $inbox, $now
    );
    check($ticket['state'] === 'QUEUED_FOR_MANUAL_REVIEW_ONLY', 'ticket_is_not_install');
    check(preg_match('/^[a-f0-9]{32}$/D', $ticket['request_id']) === 1, 'opaque_ticket_identifier');
    $file = $inbox . '/' . $ticket['request_id'] . '.json';
    check(is_file($file) && !is_link($file), 'ticket_regular_file');
    check((fileperms($file) & 0777) === 0600, 'ticket_private_permissions');
    $data = json_decode(file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
    check($data['action'] === 'review_only' && $data['site'] === STAGING, 'ticket_staging_only');
    check($data['expires_at'] === $now + 120, 'ticket_short_lived');
    check(!isset($data['admin_password']) && !isset($data['activation_token'])
        && !isset($data['install_command']), 'ticket_contains_no_credentials_or_commands');
    try {
        \Barama\Services\Updates\DashboardUpdateRequestV1::enqueue($ok, STAGING, '0.9.23');
        throw new RuntimeException('CLI_ENQUEUE_UNEXPECTEDLY_ACCEPTED');
    } catch (RuntimeException $e) {
        check($e->getMessage() === 'STAGING_REVIEW_ONLY', 'no_cli_web_enqueue_bypass');
    }
    $modified = $ok;
    $modified['install_authorized'] = true;
    try {
        \Barama\Services\Updates\DashboardUpdateRequestV1::fixture(
            $modified, '0.9.23', $parent, $inbox, $now);
        throw new RuntimeException('FORGED_INSTALL_UNEXPECTEDLY_ACCEPTED');
    } catch (RuntimeException $e) {
        check($e->getMessage() === 'REVIEW_CANDIDATE_INVALID', 'forged_install_authority_rejected');
    }
    chmod($inbox, 0777);
    try {
        \Barama\Services\Updates\DashboardUpdateRequestV1::fixture(
            $ok, '0.9.23', $parent, $inbox, $now);
        throw new RuntimeException('INSECURE_INBOX_ACCEPTED');
    } catch (RuntimeException $e) {
        check($e->getMessage() === 'INBOX_NOT_PROVISIONED', 'unsafe_inbox_mode_rejected');
    }
    chmod($inbox, 0700);
} finally {
    foreach (glob($inbox.'/*') ?: [] as $file) @unlink($file);
    @rmdir($inbox);
    @rmdir($parent);
    putenv('BARAMA_HANDOFF_TEST');
}
echo "DASHBOARD_TICKET_DISPOSABLE_SMOKE=PASS\n";

echo "========== DASHBOARD AUTHORIZED ROUTE AND UI CONTRACT ==========\n";
$routes = file_get_contents(__DIR__ . '/../application/routes/web.php');
$controller = file_get_contents(__DIR__ . '/../application/app/Http/Controllers/SystemCenterController.php');
$view = file_get_contents(__DIR__ . '/../application/resources/views/workspace/system-center.php');
check(is_string($routes) && strpos($routes,
    "'/dashboard/system/auto-updater/request-review', [SystemCenterController::class, 'requestStagingInstallReview'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]") !== false,
    'review_route_auth_permission_and_csrf');
check(is_string($controller) && strpos($controller,'public function requestStagingInstallReview(') !== false,
    'staging_review_handler_exists');
check(is_string($controller) && strpos($controller,
    '\\Barama\\Services\\Updates\\TrustedReleaseFeed::check(') !== false,
    'request_handler_requires_fresh_publisher_verification');
check(strpos($controller, '$site !== \'/www/wwwroot/baramastaging.tonsamith.com\'') !== false,
    'request_handler_pinned_to_staging');
check(is_string($view) && strpos($view,
    "url('/dashboard/system/auto-updater/request-review')") !== false,
    'staging_review_form_rendered');
check(strpos($view, 'Staging review requests do not install updates') !== false,
    'customer_feedback_not_an_install_claim');
echo "DASHBOARD_REVIEW_ROUTE_CONTRACT=PASS\n";
