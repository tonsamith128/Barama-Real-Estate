<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickDashboardGateV1.php';

use Barama\Services\Updates\OneClickDashboardGateV1;

function failGateTest(string $reason): never
{
    fwrite(STDERR, 'FAIL=' . $reason . PHP_EOL);
    exit(1);
}

$cases = [
    'no_signals' => [],
    'all_favorable_but_untrusted' => [
        'version_match' => true,
        'license_recorded' => true,
        'feed_ready' => true,
        'entitlement' => 'Eligible',
        'feed_state' => 'Signed release verified',
        'newer_version' => true,
        'archive_inspected' => true,
        'operator_approved' => true,
        'verified_recovery' => true,
        'root_worker_enabled' => true,
        'install_enabled' => true,
    ],
    'forged_string_truthiness' => [
        'version_match' => 'true',
        'feed_ready' => 'true',
        'newer_version' => 'true',
        'archive_inspected' => 'true',
        'entitlement' => 'Eligible',
        'feed_state' => 'Signed release verified',
    ],
    'publisher_rejected' => [
        'version_match' => true,
        'feed_ready' => true,
        'entitlement' => 'Not entitled',
        'feed_state' => 'Signed release verified',
        'newer_version' => true,
        'archive_inspected' => true,
    ],
    'stale_or_unsigned_feed' => [
        'version_match' => true,
        'feed_ready' => true,
        'entitlement' => 'Eligible',
        'feed_state' => 'Unavailable',
        'newer_version' => false,
    ],
    'session_report_only' => [
        'version_match' => true,
        'license_recorded' => true,
        'feed_ready' => true,
        'entitlement' => 'Eligible',
        'feed_state' => 'Signed release verified',
        'newer_version' => true,
        'archive_inspected' => true,
    ],
];

foreach ($cases as $name => $input) {
    $result = OneClickDashboardGateV1::assess($input);
    if (($result['install_enabled'] ?? null) !== false) {
        failGateTest($name . ':installation_flag_not_false');
    }
    if (($result['status'] ?? '') !== 'Review only - installation disabled') {
        failGateTest($name . ':wrong_state');
    }
    if (count($result['steps'] ?? []) !== 6
        || ($result['steps'][5]['status'] ?? '') !== 'Disabled') {
        failGateTest($name . ':worker_not_disabled');
    }
    foreach (['PRIVILEGED_INSTALLER_NOT_IMPLEMENTED',
        'INDEPENDENT_OPERATOR_APPROVAL_REQUIRED',
        'COMPLETE_BACKUP_AND_RECOVERY_NOT_PROVEN',
        'INDEPENDENT_PACKAGE_AND_ENTITLEMENT_RECHECK_REQUIRED'] as $blocker) {
        if (!in_array($blocker, $result['blockers'] ?? [], true)) {
            failGateTest($name . ':required_blocker_missing_' . $blocker);
        }
    }
    if ($name === 'forged_string_truthiness'
        && ($result['steps'][0]['status'] ?? '') !== 'Review required') {
        failGateTest($name . ':string_became_trusted_boolean');
    }
    echo 'PASS=' . $name . PHP_EOL;
}

$source = file_get_contents(dirname(__DIR__) . '/application/app/Services/Updates/OneClickDashboardGateV1.php');
if (!is_string($source)
    || preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i', $source)) {
    failGateTest('gate_has_mutating_or_execution_entrypoint');
}
echo 'ONECLICK_DASHBOARD_FAIL_CLOSED_SMOKE=PASS' . PHP_EOL;
