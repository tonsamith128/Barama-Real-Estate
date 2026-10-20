<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Pure, read-only classifier for proposed privileged-update journal records.
 * Never authenticates a record, repairs it, rolls back, or authorizes a worker.
 * This is an isolated state-machine contract, NOT a live updater.
 */
final class OneClickRecoveryJournalV1
{
    public const SITE = '/www/wwwroot/baramastaging.tonsamith.com';

    private const PHASES = [
        'REVIEWED',
        'APPROVED',
        'ARTIFACT_VERIFIED',
        'BACKUPS_VERIFIED',
        'PREPARED',
        'FILES_APPLYING',
        'DB_MIGRATING',
        'VERIFYING',
        'COMPLETED',
    ];

    /**
     * @param list<array<string,mixed>> $events
     * @return array<string,mixed> Untrusted-data interpretation only.
     */
    public static function assess(array $events): array
    {
        $out = [
            'status' => 'REJECTED_UNTRUSTED_JOURNAL',
            'last_phase' => 'NONE',
            'classification' => 'HOLD_FOR_OPERATOR',
            'events_accepted' => 0,
            'installation_authorized' => false,
            'worker_started' => false,
            'automatic_rollback_authorized' => false,
            'database_restore_authorized' => false,
            'operator_review_required' => true,
            'website_writes' => false,
            'database_writes' => false,
        ];
        if ($events === [] || count($events) > count(self::PHASES)
            || array_keys($events) !== range(0, count($events) - 1)) {
            return $out;
        }

        $bound = null;
        foreach ($events as $i => $event) {
            if (!is_array($event) || array_keys($event) === []
                || ($event['seq'] ?? null) !== ($i + 1)
                || ($event['phase'] ?? null) !== self::PHASES[$i]
                || ($event['site_root'] ?? null) !== self::SITE
                || !is_string($event['archive_sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $event['archive_sha256']) !== 1
                || !is_string($event['installed_version'] ?? null)
                || !is_string($event['target_version'] ?? null)
                || preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $event['installed_version']) !== 1
                || preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $event['target_version']) !== 1
                || version_compare($event['target_version'], $event['installed_version'], '<=')) {
                return $out;
            }
            $binding = [$event['site_root'], $event['archive_sha256'],
                $event['installed_version'], $event['target_version']];
            if ($bound !== null && $bound !== $binding) {
                return $out;
            }
            $bound = $binding;
        }

        $count = count($events);
        $last = self::PHASES[$count - 1];
        $out['status'] = 'UNTRUSTED_JOURNAL_STRUCTURE_VALID';
        $out['last_phase'] = $last;
        $out['events_accepted'] = $count;
        // Pre-mutation observations do not establish consent to run a worker.
        // Once a file/database phase is recorded (or its completion is claimed),
        // silent retry and file-only rollback are forbidden.
        $out['classification'] = $count <= 5
            ? 'NO_MUTATION_REPORTED_REVERIFY_BEFORE_ANY_ACTION'
            : 'POSSIBLE_PARTIAL_FILE_OR_DB_MUTATION_MANUAL_RECOVERY_REQUIRED';
        return $out;
    }

    private function __construct()
    {
    }
}
