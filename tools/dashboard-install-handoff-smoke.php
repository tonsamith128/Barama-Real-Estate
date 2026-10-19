<?php

declare(strict_types=1);

require __DIR__ . '/../application/app/Services/Updates/DashboardInstallHandoffV1.php';

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
