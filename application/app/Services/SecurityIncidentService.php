<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\SecurityCenterRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class SecurityIncidentService
{
    private const SETTING_PREFIX = 'security.incident.';
    private const PHASE6_POLICY_KEY = 'security.incident.phase6_policy';
    private const REVIEW_STATE_KEY = 'security.incident.review_state';
    private const REVIEW_LATEST_KEY = 'security.incident.review_latest';
    private const ESCALATION_PREFIX = 'security.incident.escalation.';

    /** @return array<int,array{id:int,name:string,email:string}> */
    public function administrators(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = "administrator"
               AND u.status = "active"
             ORDER BY u.name ASC, u.id ASC'
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function workbench(int $limit = 10): array
    {
        $repository = new SecurityCenterRepository();
        $events = $repository->incidentCandidates($limit);
        $result = [];

        foreach ($events as $event) {
            $eventId = (int) $event['id'];
            $state = $this->state($eventId, (string) $event['event_type']);

            $result[] = array_merge($event, [
                'state' => $state,
                'timeline' => $repository->incidentTimeline($eventId, 12),
            ]);
        }

        return $result;
    }

    /**
     * @param array<string,string> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function index(
        array $filters,
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $perPage = max(5, min(25, $perPage));
        $rows = $this->incidentInventory(350);

        $filtered = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesFilters($row, $filters)
        ));

        usort(
            $filtered,
            static function (array $left, array $right): int {
                $leftState = (array) ($left['state'] ?? []);
                $rightState = (array) ($right['state'] ?? []);
                $statusWeight = [
                    'investigating' => 0,
                    'open' => 1,
                    'acknowledged' => 2,
                    'resolved' => 3,
                ];
                $leftOverdue = ($left['sla_state'] ?? '') === 'overdue' ? 0 : 1;
                $rightOverdue = ($right['sla_state'] ?? '') === 'overdue' ? 0 : 1;

                if ($leftOverdue !== $rightOverdue) {
                    return $leftOverdue <=> $rightOverdue;
                }

                $leftStatus = $statusWeight[(string) ($leftState['status'] ?? 'open')] ?? 9;
                $rightStatus = $statusWeight[(string) ($rightState['status'] ?? 'open')] ?? 9;

                if ($leftStatus !== $rightStatus) {
                    return $leftStatus <=> $rightStatus;
                }

                return strcmp(
                    (string) ($right['created_at'] ?? ''),
                    (string) ($left['created_at'] ?? '')
                );
            }
        );

        $total = count($filtered);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($filtered, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @return array<string,int> */
    public function metrics(): array
    {
        $rows = $this->incidentInventory(350);
        $metrics = [
            'total' => count($rows),
            'open' => 0,
            'acknowledged' => 0,
            'investigating' => 0,
            'resolved' => 0,
            'overdue' => 0,
            'critical' => 0,
        ];

        foreach ($rows as $row) {
            $state = (array) ($row['state'] ?? []);
            $status = (string) ($state['status'] ?? 'open');
            $severity = (string) ($state['severity'] ?? 'medium');

            if (isset($metrics[$status])) {
                $metrics[$status]++;
            }

            if (($row['sla_state'] ?? '') === 'overdue') {
                $metrics['overdue']++;
            }

            if ($severity === 'critical') {
                $metrics['critical']++;
            }
        }

        return $metrics;
    }

    /**
     * @param array<int,mixed> $ids
     * @return array{updated:int,skipped:int}
     */
    public function acknowledgeMany(array $ids, int $actorUserId): array
    {
        if ($this->administrator($actorUserId) === null) {
            throw new DomainException('Administrator security permission is required.');
        }

        $normalized = [];
        foreach ($ids as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $normalized[(int) $id] = true;
            }
        }

        $incidentIds = array_keys($normalized);
        if ($incidentIds === []) {
            throw new InvalidArgumentException('Select at least one security incident.');
        }
        if (count($incidentIds) > 50) {
            throw new InvalidArgumentException('Bulk acknowledgment is limited to 50 incidents at a time.');
        }

        $updated = 0;
        $skipped = 0;
        $repository = new SecurityCenterRepository();

        foreach ($incidentIds as $eventId) {
            $event = $repository->eventById((int) $eventId);
            if ($event === null) {
                $skipped++;
                continue;
            }
            $eventType = (string) $event['event_type'];
            if (!in_array($eventType, ['auth.login_failed', 'auth.login_locked'], true)) {
                $skipped++;
                continue;
            }
            $state = $this->state((int) $eventId, $eventType);
            if (in_array((string) ($state['status'] ?? 'open'), ['acknowledged', 'resolved'], true)) {
                $skipped++;
                continue;
            }

            $this->update((int) $eventId, $actorUserId, [
                'status' => 'acknowledged',
                'severity' => (string) ($state['severity'] ?? 'medium'),
                'assignee_id' => $state['assignee_id'] ?? '',
                'note' => '',
            ]);
            $updated++;
        }

        (new SecurityAuditService())->record(
            'admin.security_incident_bulk_acknowledged',
            $actorUserId,
            null,
            [
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'requested_count' => count($incidentIds),
            ]
        );

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /** @param array<string,string> $filters @return array<int,array<string,mixed>> */
    public function export(array $filters, int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->incidentInventory(500);
        $filtered = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesFilters($row, $filters)
        ));
        return array_slice($filtered, 0, $limit);
    }

    /** @return array<string,mixed> */
    public function phase6Policy(): array
    {
        $defaults = [
            'sla' => [
                'critical' => 1,
                'high' => 4,
                'medium' => 24,
                'low' => 72,
            ],
            'escalation_enabled' => true,
            'notify_overdue_assignee' => true,
            'auto_assign_unassigned' => false,
            'reassign_overdue' => false,
            'escalation_assignee_id' => null,
            'cooldown_minutes' => 240,
            'review_frequency' => 'daily',
            'review_hour' => 8,
            'review_weekday' => 1,
        ];

        $stored = $this->settingJson(self::PHASE6_POLICY_KEY);

        if ($stored === null) {
            return $defaults;
        }

        $sla = is_array($stored['sla'] ?? null)
            ? $stored['sla']
            : [];

        return [
            'sla' => [
                'critical' => (int) ($sla['critical'] ?? 1),
                'high' => (int) ($sla['high'] ?? 4),
                'medium' => (int) ($sla['medium'] ?? 24),
                'low' => (int) ($sla['low'] ?? 72),
            ],
            'escalation_enabled' =>
                (bool) ($stored['escalation_enabled'] ?? true),
            'notify_overdue_assignee' =>
                (bool) ($stored['notify_overdue_assignee'] ?? true),
            'auto_assign_unassigned' =>
                (bool) ($stored['auto_assign_unassigned'] ?? false),
            'reassign_overdue' =>
                (bool) ($stored['reassign_overdue'] ?? false),
            'escalation_assignee_id' =>
                !empty($stored['escalation_assignee_id'])
                    ? (int) $stored['escalation_assignee_id']
                    : null,
            'cooldown_minutes' =>
                max(15, min(1440, (int) ($stored['cooldown_minutes'] ?? 240))),
            'review_frequency' =>
                in_array(
                    (string) ($stored['review_frequency'] ?? 'daily'),
                    ['daily', 'weekly'],
                    true
                )
                    ? (string) $stored['review_frequency']
                    : 'daily',
            'review_hour' =>
                max(0, min(23, (int) ($stored['review_hour'] ?? 8))),
            'review_weekday' =>
                max(1, min(7, (int) ($stored['review_weekday'] ?? 1))),
        ];
    }

    /** @return array<string,int> */
    public function slaPolicy(): array
    {
        $policy = $this->phase6Policy();
        return (array) $policy['sla'];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function savePhase6Policy(
        array $input,
        int $actorUserId
    ): array {
        if ($this->administrator($actorUserId) === null) {
            throw new DomainException(
                'Administrator security permission is required.'
            );
        }

        $sla = [
            'critical' => $this->boundedInt(
                $input['sla_critical'] ?? null,
                1,
                24,
                'Critical SLA'
            ),
            'high' => $this->boundedInt(
                $input['sla_high'] ?? null,
                1,
                72,
                'High SLA'
            ),
            'medium' => $this->boundedInt(
                $input['sla_medium'] ?? null,
                1,
                336,
                'Medium SLA'
            ),
            'low' => $this->boundedInt(
                $input['sla_low'] ?? null,
                1,
                720,
                'Low SLA'
            ),
        ];

        if (
            $sla['critical'] > $sla['high']
            || $sla['high'] > $sla['medium']
            || $sla['medium'] > $sla['low']
        ) {
            throw new InvalidArgumentException(
                'SLA hours must increase from Critical to Low severity.'
            );
        }

        $assigneeId = filter_var(
            $input['escalation_assignee_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $assignee = null;

        if ($assigneeId !== false && $assigneeId !== null) {
            $assignee = $this->administrator((int) $assigneeId);

            if ($assignee === null) {
                throw new InvalidArgumentException(
                    'Escalation owner must be an active administrator.'
                );
            }
        }

        $frequency = strtolower(trim(
            (string) ($input['review_frequency'] ?? 'daily')
        ));

        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            throw new InvalidArgumentException(
                'Security review frequency is invalid.'
            );
        }

        $policy = [
            'sla' => $sla,
            'escalation_enabled' =>
                $this->checkbox($input, 'escalation_enabled'),
            'notify_overdue_assignee' =>
                $this->checkbox($input, 'notify_overdue_assignee'),
            'auto_assign_unassigned' =>
                $this->checkbox($input, 'auto_assign_unassigned'),
            'reassign_overdue' =>
                $this->checkbox($input, 'reassign_overdue'),
            'escalation_assignee_id' =>
                $assignee !== null ? (int) $assignee['id'] : null,
            'cooldown_minutes' => $this->boundedInt(
                $input['cooldown_minutes'] ?? null,
                15,
                1440,
                'Escalation cooldown'
            ),
            'review_frequency' => $frequency,
            'review_hour' => $this->boundedInt(
                $input['review_hour'] ?? null,
                0,
                23,
                'Review hour'
            ),
            'review_weekday' => $this->boundedInt(
                $input['review_weekday'] ?? 1,
                1,
                7,
                'Review weekday'
            ),
        ];

        $this->saveSettingJson(self::PHASE6_POLICY_KEY, $policy);

        (new SecurityAuditService())->record(
            'admin.security_phase6_policy_updated',
            $actorUserId,
            null,
            [
                'critical_sla_hours' => $sla['critical'],
                'high_sla_hours' => $sla['high'],
                'medium_sla_hours' => $sla['medium'],
                'low_sla_hours' => $sla['low'],
                'escalation_enabled' =>
                    $policy['escalation_enabled'] ? 1 : 0,
                'auto_assign_unassigned' =>
                    $policy['auto_assign_unassigned'] ? 1 : 0,
                'reassign_overdue' =>
                    $policy['reassign_overdue'] ? 1 : 0,
                'cooldown_minutes' => $policy['cooldown_minutes'],
                'review_frequency' => $frequency,
                'review_hour' => $policy['review_hour'],
                'review_weekday' => $policy['review_weekday'],
            ]
        );

        return $policy;
    }

    /** @return array<int,array<string,mixed>> */
    public function workloadSummary(): array
    {
        $administrators = $this->administrators();
        $rows = $this->incidentInventory(350);
        $summary = [];

        $summary[0] = [
            'id' => 0,
            'name' => 'Unassigned',
            'email' => '',
            'active' => 0,
            'overdue' => 0,
            'critical' => 0,
            'investigating' => 0,
        ];

        foreach ($administrators as $administrator) {
            $id = (int) $administrator['id'];
            $summary[$id] = [
                'id' => $id,
                'name' => (string) $administrator['name'],
                'email' => (string) $administrator['email'],
                'active' => 0,
                'overdue' => 0,
                'critical' => 0,
                'investigating' => 0,
            ];
        }

        foreach ($rows as $row) {
            $state = (array) ($row['state'] ?? []);

            if ((string) ($state['status'] ?? 'open') === 'resolved') {
                continue;
            }

            $assigneeId = (int) ($state['assignee_id'] ?? 0);

            if (!isset($summary[$assigneeId])) {
                $summary[$assigneeId] = [
                    'id' => $assigneeId,
                    'name' => (string) (
                        $state['assignee_name'] ?? 'Former administrator'
                    ),
                    'email' => '',
                    'active' => 0,
                    'overdue' => 0,
                    'critical' => 0,
                    'investigating' => 0,
                ];
            }

            $summary[$assigneeId]['active']++;

            if (($row['sla_state'] ?? '') === 'overdue') {
                $summary[$assigneeId]['overdue']++;
            }

            if ((string) ($state['severity'] ?? '') === 'critical') {
                $summary[$assigneeId]['critical']++;
            }

            if ((string) ($state['status'] ?? '') === 'investigating') {
                $summary[$assigneeId]['investigating']++;
            }
        }

        usort(
            $summary,
            static function (array $left, array $right): int {
                if ($left['overdue'] !== $right['overdue']) {
                    return $right['overdue'] <=> $left['overdue'];
                }

                if ($left['active'] !== $right['active']) {
                    return $right['active'] <=> $left['active'];
                }

                return strcmp($left['name'], $right['name']);
            }
        );

        return array_values($summary);
    }

    /**
     * @return array{evaluated:int,escalated:int,assigned:int,notified:int}
     */
    public function evaluateEscalations(
        ?int $actorUserId = null,
        bool $automated = false
    ): array {
        if (
            !$automated
            && (
                $actorUserId === null
                || $this->administrator($actorUserId) === null
            )
        ) {
            throw new DomainException(
                'Administrator security permission is required.'
            );
        }

        $policy = $this->phase6Policy();

        if (empty($policy['escalation_enabled'])) {
            return [
                'evaluated' => 0,
                'escalated' => 0,
                'assigned' => 0,
                'notified' => 0,
            ];
        }

        $rows = $this->incidentInventory(350);
        $evaluated = 0;
        $escalated = 0;
        $assigned = 0;
        $notified = 0;
        $cooldownSeconds =
            (int) $policy['cooldown_minutes'] * 60;
        $escalationOwnerId = (int) (
            $policy['escalation_assignee_id'] ?? 0
        );

        foreach ($rows as $row) {
            $state = (array) ($row['state'] ?? []);

            if (
                (string) ($state['status'] ?? 'open') === 'resolved'
                || (string) ($row['sla_state'] ?? '') !== 'overdue'
            ) {
                continue;
            }

            $evaluated++;
            $eventId = (int) $row['id'];
            $escalationState = $this->settingJson(
                self::ESCALATION_PREFIX . $eventId
            ) ?? [];

            $lastAt = strtotime(
                (string) ($escalationState['last_escalated_at'] ?? '')
            );

            if (
                $lastAt
                && (time() - $lastAt) < $cooldownSeconds
            ) {
                continue;
            }

            $currentAssigneeId = (int) (
                $state['assignee_id'] ?? 0
            );
            $targetId = $currentAssigneeId;

            if (
                $currentAssigneeId === 0
                && !empty($policy['auto_assign_unassigned'])
                && $escalationOwnerId > 0
            ) {
                $targetId = $escalationOwnerId;
            } elseif (
                $currentAssigneeId > 0
                && !empty($policy['reassign_overdue'])
                && $escalationOwnerId > 0
                && $currentAssigneeId !== $escalationOwnerId
            ) {
                $targetId = $escalationOwnerId;
            }

            if ($targetId !== $currentAssigneeId) {
                $this->assignIncidentForEscalation(
                    $row,
                    $state,
                    $targetId,
                    $actorUserId
                );
                $state = $this->state(
                    $eventId,
                    (string) $row['event_type']
                );
                $assigned++;
            }

            $recipientId = (int) ($state['assignee_id'] ?? 0);

            if (
                $recipientId > 0
                && !empty($policy['notify_overdue_assignee'])
            ) {
                $recipient = $this->administrator($recipientId);

                if (
                    $recipient !== null
                    && $this->notifyEscalation(
                        $row,
                        $state,
                        $recipient,
                        (int) $policy['cooldown_minutes']
                    )
                ) {
                    $notified++;
                }
            }

            (new SecurityAuditService())->record(
                'admin.security_incident_escalated',
                $actorUserId,
                $recipientId > 0 ? $recipientId : null,
                [
                    'incident_event_id' => $eventId,
                    'severity' =>
                        (string) ($state['severity'] ?? 'medium'),
                    'status' =>
                        (string) ($state['status'] ?? 'open'),
                    'sla_hours' => (int) ($row['sla_hours'] ?? 0),
                    'age_hours' => (float) ($row['age_hours'] ?? 0),
                    'automated' => $automated ? 1 : 0,
                ]
            );

            $this->saveSettingJson(
                self::ESCALATION_PREFIX . $eventId,
                [
                    'last_escalated_at' => date('Y-m-d H:i:s'),
                    'assignee_id' =>
                        (int) ($state['assignee_id'] ?? 0),
                    'severity' =>
                        (string) ($state['severity'] ?? 'medium'),
                    'status' =>
                        (string) ($state['status'] ?? 'open'),
                ]
            );

            $escalated++;
        }

        return [
            'evaluated' => $evaluated,
            'escalated' => $escalated,
            'assigned' => $assigned,
            'notified' => $notified,
        ];
    }

    /** @return array<string,mixed> */
    public function reviewStatus(): array
    {
        $policy = $this->phase6Policy();
        $state = $this->settingJson(self::REVIEW_STATE_KEY) ?? [];
        $lastGeneratedAt =
            (string) ($state['last_generated_at'] ?? '');
        $nextDueAt = $this->nextReviewDue(
            $lastGeneratedAt,
            $policy
        );

        return [
            'last_generated_at' => $lastGeneratedAt,
            'next_due_at' => $nextDueAt,
            'due' => time() >= (strtotime($nextDueAt) ?: 0),
            'frequency' => (string) $policy['review_frequency'],
            'hour' => (int) $policy['review_hour'],
            'weekday' => (int) $policy['review_weekday'],
        ];
    }

    /** @return array<string,mixed> */
    public function latestReview(): array
    {
        return $this->settingJson(self::REVIEW_LATEST_KEY) ?? [];
    }

    /** @return array<string,mixed> */
    public function runSecurityReview(
        ?int $actorUserId = null,
        bool $force = false,
        bool $automated = false
    ): array {
        if (
            !$automated
            && (
                $actorUserId === null
                || $this->administrator($actorUserId) === null
            )
        ) {
            throw new DomainException(
                'Administrator security permission is required.'
            );
        }

        $status = $this->reviewStatus();

        if (!$force && empty($status['due'])) {
            return [
                'generated' => false,
                'reason' => 'not_due',
                'next_due_at' => (string) $status['next_due_at'],
            ];
        }

        $metrics = $this->metrics();
        $workload = $this->workloadSummary();
        $rows = $this->incidentInventory(350);
        $overdue = array_values(array_filter(
            $rows,
            static fn (array $row): bool =>
                ($row['sla_state'] ?? '') === 'overdue'
        ));

        $report = [
            'generated' => true,
            'generated_at' => date('Y-m-d H:i:s'),
            'automated' => $automated,
            'metrics' => $metrics,
            'workload' => array_slice($workload, 0, 10),
            'overdue_incidents' => array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'severity' => (string) (
                        $row['state']['severity'] ?? 'medium'
                    ),
                    'status' => (string) (
                        $row['state']['status'] ?? 'open'
                    ),
                    'age' => (string) ($row['age_label'] ?? ''),
                    'assignee' => (string) (
                        $row['state']['assignee_name'] ?? ''
                    ),
                ],
                array_slice($overdue, 0, 20)
            ),
        ];

        $this->saveSettingJson(self::REVIEW_LATEST_KEY, $report);
        $this->saveSettingJson(
            self::REVIEW_STATE_KEY,
            ['last_generated_at' => $report['generated_at']]
        );

        $notificationRepository = new NotificationRepository();
        $summary = sprintf(
            'Security review: %d active, %d overdue, %d critical, %d resolved.',
            (int) ($metrics['total'] ?? 0)
                - (int) ($metrics['resolved'] ?? 0),
            (int) ($metrics['overdue'] ?? 0),
            (int) ($metrics['critical'] ?? 0),
            (int) ($metrics['resolved'] ?? 0)
        );
        $bucket = date('Ymd-H', strtotime($report['generated_at']));

        foreach ($this->administrators() as $administrator) {
            $notificationRepository->createNotification([
                'user_id' => (int) $administrator['id'],
                'category' => 'security_alerts',
                'type' => 'security.review.generated',
                'title' => 'Security review generated',
                'message' => $summary,
                'action_url' => '/dashboard/audit#security-phase6-review',
                'entity_type' => 'security',
                'entity_id' => null,
                'dedupe_key' => 'security:review:'
                    . $bucket
                    . ':user:' . (int) $administrator['id'],
            ]);
        }

        (new SecurityAuditService())->record(
            'admin.security_review_generated',
            $actorUserId,
            null,
            [
                'automated' => $automated ? 1 : 0,
                'active_incidents' =>
                    (int) ($metrics['total'] ?? 0)
                    - (int) ($metrics['resolved'] ?? 0),
                'overdue_incidents' =>
                    (int) ($metrics['overdue'] ?? 0),
                'critical_incidents' =>
                    (int) ($metrics['critical'] ?? 0),
            ]
        );

        $report['next_due_at'] =
            $this->reviewStatus()['next_due_at'];

        return $report;
    }

    /** @return array<string,mixed> */
    public function runAutomation(): array
    {
        return [
            'escalation' =>
                $this->evaluateEscalations(null, true),
            'review' =>
                $this->runSecurityReview(null, false, true),
        ];
    }


    /** @return array<int,array<string,mixed>> */
    private function incidentInventory(int $limit): array
    {
        $repository = new SecurityCenterRepository();
        $events = $repository->incidentCandidates($limit);
        $rows = [];

        foreach ($events as $event) {
            $eventId = (int) $event['id'];
            $eventType = (string) $event['event_type'];
            $state = $this->state($eventId, $eventType);
            $sla = $this->sla((string) ($event['created_at'] ?? ''), $state);
            $rows[] = array_merge($event, [
                'state' => $state,
                'sla_hours' => $sla['sla_hours'],
                'age_hours' => $sla['age_hours'],
                'age_label' => $sla['age_label'],
                'due_at' => $sla['due_at'],
                'sla_state' => $sla['sla_state'],
                'timeline' => $repository->incidentTimeline($eventId, 12),
            ]);
        }

        return $rows;
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function sla(string $createdAt, array $state): array
    {
        $policy = $this->slaPolicy();
        $severity = (string) ($state['severity'] ?? 'medium');
        $slaHours = (int) ($policy[$severity] ?? 24);
        $created = strtotime($createdAt);

        if (!$created) {
            return [
                'sla_hours' => $slaHours,
                'age_hours' => 0.0,
                'age_label' => 'Unknown',
                'due_at' => '',
                'sla_state' => 'unknown',
            ];
        }

        $resolvedAt = strtotime((string) ($state['resolved_at'] ?? ''));
        $end = $resolvedAt ?: time();
        $ageSeconds = max(0, $end - $created);
        $due = $created + ($slaHours * 3600);
        $status = (string) ($state['status'] ?? 'open');

        if ($status === 'resolved') {
            $slaState = $end > $due ? 'resolved_late' : 'resolved';
        } else {
            $slaState = time() > $due ? 'overdue' : 'on_track';
        }

        return [
            'sla_hours' => $slaHours,
            'age_hours' => round($ageSeconds / 3600, 2),
            'age_label' => $this->durationLabel($ageSeconds),
            'due_at' => gmdate('Y-m-d H:i:s', $due),
            'sla_state' => $slaState,
        ];
    }

    private function durationLabel(int $seconds): string
    {
        if ($seconds < 3600) {
            return max(1, (int) floor($seconds / 60)) . 'm';
        }
        if ($seconds < 86400) {
            return round($seconds / 3600, 1) . 'h';
        }
        return round($seconds / 86400, 1) . 'd';
    }

    /** @param array<string,string> $filters */
    private function matchesFilters(array $row, array $filters): bool
    {
        $state = (array) ($row['state'] ?? []);
        $query = mb_strtolower(trim((string) ($filters['incident_q'] ?? '')));

        if ($query !== '') {
            $haystack = mb_strtolower(implode(' ', [
                (string) ($row['id'] ?? ''),
                (string) ($row['event_type'] ?? ''),
                (string) ($row['actor'] ?? ''),
                (string) ($row['subject'] ?? ''),
                (string) ($state['assignee_name'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                return false;
            }
        }

        $status = strtolower(trim((string) ($filters['incident_status'] ?? '')));
        if ($status !== '' && (string) ($state['status'] ?? 'open') !== $status) {
            return false;
        }

        $severity = strtolower(trim((string) ($filters['incident_severity'] ?? '')));
        if ($severity !== '' && (string) ($state['severity'] ?? 'medium') !== $severity) {
            return false;
        }

        $assigneeRaw = trim((string) ($filters['incident_assignee'] ?? ''));
        if ($assigneeRaw === 'unassigned' && (int) ($state['assignee_id'] ?? 0) !== 0) {
            return false;
        }
        if ($assigneeRaw !== '' && $assigneeRaw !== 'unassigned') {
            $assignee = filter_var($assigneeRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($assignee === false || (int) ($state['assignee_id'] ?? 0) !== (int) $assignee) {
                return false;
            }
        }

        $sla = strtolower(trim((string) ($filters['incident_sla'] ?? '')));
        if ($sla === 'overdue' && ($row['sla_state'] ?? '') !== 'overdue') {
            return false;
        }
        if ($sla === 'on_track' && !in_array((string) ($row['sla_state'] ?? ''), ['on_track', 'resolved'], true)) {
            return false;
        }

        return true;
    }

    /** @return array<string,mixed> */
    public function update(
        int $eventId,
        int $actorUserId,
        array $input
    ): array {
        if ($this->administrator($actorUserId) === null) {
            throw new DomainException(
                'Administrator security permission is required.'
            );
        }

        $repository = new SecurityCenterRepository();
        $event = $repository->eventById($eventId);

        if ($event === null) {
            throw new InvalidArgumentException(
                'Security event was not found.'
            );
        }

        $eventType = (string) $event['event_type'];

        if (!in_array(
            $eventType,
            ['auth.login_failed', 'auth.login_locked'],
            true
        )) {
            throw new DomainException(
                'Only failed or locked authentication events can be managed as incidents.'
            );
        }

        $previous = $this->state($eventId, $eventType);
        $status = strtolower(trim((string) ($input['status'] ?? 'open')));
        $severity = strtolower(trim((string) ($input['severity'] ?? 'medium')));
        $assigneeId = filter_var(
            $input['assignee_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $note = trim((string) ($input['note'] ?? ''));

        if (!in_array(
            $status,
            ['open', 'acknowledged', 'investigating', 'resolved'],
            true
        )) {
            throw new InvalidArgumentException(
                'Incident status is invalid.'
            );
        }

        if (!in_array(
            $severity,
            ['low', 'medium', 'high', 'critical'],
            true
        )) {
            throw new InvalidArgumentException(
                'Incident severity is invalid.'
            );
        }

        $assignee = null;

        if ($assigneeId !== false && $assigneeId !== null) {
            $assignee = $this->administrator((int) $assigneeId);

            if ($assignee === null) {
                throw new InvalidArgumentException(
                    'Incident assignee must be an active administrator.'
                );
            }
        }

        if (mb_strlen($note) > 500) {
            throw new InvalidArgumentException(
                'Incident notes cannot exceed 500 characters.'
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $state = [
            'incident_event_id' => $eventId,
            'source_event_type' => $eventType,
            'status' => $status,
            'severity' => $severity,
            'assignee_id' => $assignee !== null
                ? (int) $assignee['id']
                : null,
            'assignee_name' => $assignee !== null
                ? (string) $assignee['name']
                : '',
            'updated_by' => $actorUserId,
            'updated_at' => $now,
            'resolved_at' => $status === 'resolved'
                ? ($previous['resolved_at'] ?: $now)
                : null,
        ];

        $this->saveState($eventId, $state);
        $audit = new SecurityAuditService();
        $baseMetadata = [
            'incident_event_id' => $eventId,
            'source_event_type' => $eventType,
        ];

        if (empty($previous['persisted'])) {
            $audit->record(
                'admin.security_incident_created',
                $actorUserId,
                null,
                $baseMetadata + [
                    'status' => $status,
                    'severity' => $severity,
                ]
            );
        }

        if ((string) $previous['status'] !== $status) {
            $audit->record(
                'admin.security_incident_status_changed',
                $actorUserId,
                null,
                $baseMetadata + [
                    'from' => (string) $previous['status'],
                    'to' => $status,
                ]
            );
        }

        if ((string) $previous['severity'] !== $severity) {
            $audit->record(
                'admin.security_incident_severity_changed',
                $actorUserId,
                null,
                $baseMetadata + [
                    'from' => (string) $previous['severity'],
                    'to' => $severity,
                ]
            );
        }

        $previousAssignee = (int) ($previous['assignee_id'] ?? 0);
        $newAssignee = (int) ($state['assignee_id'] ?? 0);

        if ($previousAssignee !== $newAssignee) {
            $audit->record(
                'admin.security_incident_assigned',
                $actorUserId,
                $newAssignee > 0 ? $newAssignee : null,
                $baseMetadata + [
                    'from_assignee_id' => $previousAssignee,
                    'to_assignee_id' => $newAssignee,
                ]
            );
        }

        if ($note !== '') {
            $audit->record(
                'admin.security_incident_note_added',
                $actorUserId,
                null,
                $baseMetadata + [
                    'note' => $note,
                ]
            );
        }

        if ($assignee !== null) {
            $this->notifyAssignee(
                $eventId,
                $state,
                $assignee,
                $note
            );
        }

        return $state;
    }

    /** @return array<string,mixed> */
    public function state(int $eventId, string $eventType = ''): array
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $statement->execute([
            'setting_key' => self::SETTING_PREFIX . $eventId,
        ]);

        $value = $statement->fetchColumn();

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_merge(
                    $this->defaultState($eventId, $eventType),
                    $decoded,
                    ['persisted' => true]
                );
            }
        }

        return $this->defaultState($eventId, $eventType);
    }

    /** @return array<string,mixed> */
    private function defaultState(int $eventId, string $eventType): array
    {
        return [
            'incident_event_id' => $eventId,
            'source_event_type' => $eventType,
            'status' => 'open',
            'severity' => $eventType === 'auth.login_locked'
                ? 'high'
                : 'medium',
            'assignee_id' => null,
            'assignee_name' => '',
            'updated_by' => null,
            'updated_at' => '',
            'resolved_at' => null,
            'persisted' => false,
        ];
    }

    /** @param array<string,mixed> $state */
    private function saveState(int $eventId, array $state): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => self::SETTING_PREFIX . $eventId,
            'setting_value' => json_encode(
                $state,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function settingJson(string $key): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $value */
    private function saveSettingJson(string $key, array $value): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    /** @param array<string,mixed> $input */
    private function checkbox(array $input, string $key): bool
    {
        return in_array(
            strtolower(trim((string) ($input[$key] ?? ''))),
            ['1', 'on', 'true', 'yes'],
            true
        );
    }

    private function boundedInt(
        mixed $value,
        int $min,
        int $max,
        string $label
    ): int {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => $min,
                'max_range' => $max,
            ]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                $label . ' must be between '
                . $min . ' and ' . $max . '.'
            );
        }

        return (int) $validated;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $state
     */
    private function assignIncidentForEscalation(
        array $row,
        array $state,
        int $targetId,
        ?int $actorUserId
    ): void {
        $target = $this->administrator($targetId);

        if ($target === null) {
            return;
        }

        $previousAssignee = (int) ($state['assignee_id'] ?? 0);
        $state['assignee_id'] = (int) $target['id'];
        $state['assignee_name'] = (string) $target['name'];
        $state['updated_by'] = $actorUserId;
        $state['updated_at'] = date('Y-m-d H:i:s');

        $this->saveState((int) $row['id'], $state);

        (new SecurityAuditService())->record(
            'admin.security_incident_assigned',
            $actorUserId,
            (int) $target['id'],
            [
                'incident_event_id' => (int) $row['id'],
                'from_assignee_id' => $previousAssignee,
                'to_assignee_id' => (int) $target['id'],
                'escalation' => 1,
            ]
        );

        $this->notifyAssignee(
            (int) $row['id'],
            $state,
            $target,
            'Automatic overdue escalation assignment.'
        );
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $state
     * @param array{id:int,name:string,email:string} $recipient
     */
    private function notifyEscalation(
        array $row,
        array $state,
        array $recipient,
        int $cooldownMinutes
    ): bool {
        $bucketSeconds = max(900, $cooldownMinutes * 60);
        $bucket = (int) floor(time() / $bucketSeconds);
        $repository = new NotificationRepository();
        $notificationId = $repository->createNotification([
            'user_id' => (int) $recipient['id'],
            'category' => 'security_alerts',
            'type' => 'security.incident.overdue',
            'title' => 'Security incident overdue',
            'message' => sprintf(
                'Incident #%d (%s) is overdue after %s and requires review.',
                (int) $row['id'],
                ucfirst((string) ($state['severity'] ?? 'medium')),
                (string) ($row['age_label'] ?? 'its SLA')
            ),
            'action_url' =>
                '/dashboard/audit#security-incident-'
                . (int) $row['id'],
            'entity_type' => 'security',
            'entity_id' => (int) $row['id'],
            'dedupe_key' => implode(':', [
                'security',
                'incident-overdue',
                (string) $row['id'],
                (string) $bucket,
            ]),
        ]);

        return $notificationId !== null;
    }

    /** @param array<string,mixed> $policy */
    private function nextReviewDue(
        string $lastGeneratedAt,
        array $policy
    ): string {
        $hour = max(
            0,
            min(23, (int) ($policy['review_hour'] ?? 8))
        );
        $frequency =
            (string) ($policy['review_frequency'] ?? 'daily');

        if ($lastGeneratedAt === '') {
            $today = strtotime(
                date('Y-m-d')
                . sprintf(' %02d:00:00', $hour)
            );
            $candidate = $today ?: time();

            if ($candidate <= time()) {
                return date('Y-m-d H:i:s');
            }

            return date('Y-m-d H:i:s', $candidate);
        }

        $last = strtotime($lastGeneratedAt) ?: time();

        if ($frequency === 'weekly') {
            $weekday = max(
                1,
                min(7, (int) ($policy['review_weekday'] ?? 1))
            );
            $daysUntil = ($weekday - (int) date('N', $last) + 7) % 7;
            $daysUntil = $daysUntil === 0 ? 7 : $daysUntil;
            $nextDate = date(
                'Y-m-d',
                $last + ($daysUntil * 86400)
            );
            $next = strtotime(
                $nextDate . sprintf(' %02d:00:00', $hour)
            ) ?: ($last + 604800);

            return date('Y-m-d H:i:s', $next);
        }

        $nextDate = date('Y-m-d', $last + 86400);
        $next = strtotime(
            $nextDate . sprintf(' %02d:00:00', $hour)
        ) ?: ($last + 86400);

        return date('Y-m-d H:i:s', $next);
    }

    /** @return array{id:int,name:string,email:string}|null */
    private function administrator(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :id
               AND r.slug = "administrator"
               AND u.status = "active"
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
            ]
            : null;
    }

    /**
     * @param array<string,mixed> $state
     * @param array{id:int,name:string,email:string} $assignee
     */
    private function notifyAssignee(
        int $eventId,
        array $state,
        array $assignee,
        string $note
    ): void {
        $signature = hash(
            'sha256',
            implode('|', [
                (string) $eventId,
                (string) $state['status'],
                (string) $state['severity'],
                (string) $assignee['id'],
                $note,
            ])
        );

        (new NotificationRepository())->createNotification([
            'user_id' => (int) $assignee['id'],
            'category' => 'security_alerts',
            'type' => 'security.incident_assigned',
            'title' => 'Security incident assigned',
            'message' => sprintf(
                'Incident #%d is %s severity and currently %s.',
                $eventId,
                (string) $state['severity'],
                (string) $state['status']
            ),
            'action_url' => '/dashboard/audit#security-incident-' . $eventId,
            'entity_type' => 'security',
            'entity_id' => $eventId,
            'dedupe_key' => 'security:incident:'
                . $eventId . ':' . substr($signature, 0, 32),
        ]);
    }
}
