<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickRecoveryJournalV1.php';

use Barama\Services\Updates\OneClickRecoveryJournalV1;

function journalAssert(bool $value, string $name): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL=$name\n");
        exit(1);
    }
    echo "PASS=$name\n";
}

$phases = ['REVIEWED', 'APPROVED', 'ARTIFACT_VERIFIED', 'BACKUPS_VERIFIED',
    'PREPARED', 'FILES_APPLYING', 'DB_MIGRATING', 'VERIFYING', 'COMPLETED'];
$events = [];
$alwaysNo = static function (array $r): bool {
    return ($r['installation_authorized'] ?? null) === false
        && ($r['worker_started'] ?? null) === false
        && ($r['automatic_rollback_authorized'] ?? null) === false
        && ($r['database_restore_authorized'] ?? null) === false
        && ($r['operator_review_required'] ?? null) === true
        && ($r['website_writes'] ?? null) === false
        && ($r['database_writes'] ?? null) === false;
};

$empty = OneClickRecoveryJournalV1::assess([]);
journalAssert($empty['status'] === 'REJECTED_UNTRUSTED_JOURNAL' && $alwaysNo($empty), 'empty_journal_denied');
foreach ($phases as $i => $phase) {
    $events[] = [
        'seq' => $i + 1,
        'phase' => $phase,
        'site_root' => OneClickRecoveryJournalV1::SITE,
        'archive_sha256' => str_repeat('f', 64),
        'installed_version' => '0.9.22-dev',
        'target_version' => '0.9.23',
        'operator_approved' => true,
        'recoverable' => true,
        'has_sql_backup' => true,
    ];
    $r = OneClickRecoveryJournalV1::assess($events);
    $correctClass = $i < 5
        ? 'NO_MUTATION_REPORTED_REVERIFY_BEFORE_ANY_ACTION'
        : 'POSSIBLE_PARTIAL_FILE_OR_DB_MUTATION_MANUAL_RECOVERY_REQUIRED';
    journalAssert($r['status'] === 'UNTRUSTED_JOURNAL_STRUCTURE_VALID'
        && $r['events_accepted'] === $i + 1
        && $r['last_phase'] === $phase
        && $r['classification'] === $correctClass
        && $alwaysNo($r), 'crash_after_'.$phase.'_never_auto_recovers_or_installs');
}
$invalid = [];
$t = $events; $t[2]['phase'] = 'COMPLETED'; $invalid['skipped_phase'] = $t;
$t = $events; $t[4]['seq'] = 100; $invalid['nonmonotonic_sequence'] = $t;
$t = $events; $t[6]['archive_sha256'] = str_repeat('e', 64); $invalid['changed_archive'] = $t;
$t = $events; $t[3]['site_root'] = '/www/wwwroot/baramarealestate.tonsamith.com'; $invalid['production_target'] = $t;
$t = $events; $t[8]['target_version'] = '0.9.24'; $invalid['changed_release_version'] = $t;
$t = $events; $t[7]['installed_version'] = '0.9.23'; $invalid['changed_installed_version'] = $t;
$t = $events; $t[8]['archive_sha256'] = strtoupper(str_repeat('f',64)); $invalid['uppercase_digest'] = $t;
$t = $events; $t[] = $events[8]; $invalid['extra_event'] = $t;
$t = $events; $t[2]['target_version'] = '0.9.21'; $invalid['downgrade'] = $t;
$t = $events; $t[2]['phase'] = 'DB_MIGRATING'; $invalid['premature_db_migration'] = $t;
foreach ($invalid as $name => $bad) {
    $r = OneClickRecoveryJournalV1::assess($bad);
    journalAssert($r['status'] === 'REJECTED_UNTRUSTED_JOURNAL' && $alwaysNo($r), $name.'_rejected');
}
$source = file_get_contents(dirname(__DIR__).'/application/app/Services/Updates/OneClickRecoveryJournalV1.php');
journalAssert(is_string($source) && !preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i', $source), 'classifier_is_pure');
echo "ONECLICK_RECOVERY_JOURNAL_SMOKE=PASS\n";
