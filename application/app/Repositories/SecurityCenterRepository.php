<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class SecurityCenterRepository
{
    /** @return array<string,int> */
    public function summary(): array
    {
        $pdo = Database::connection();

        $events = $pdo->query(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(created_at >= CURRENT_TIMESTAMP - INTERVAL 1 DAY), 0) AS events_24h,
                COALESCE(SUM(
                    event_type = "auth.login_failed"
                    AND created_at >= CURRENT_TIMESTAMP - INTERVAL 1 DAY
                ), 0) AS failed_24h,
                COALESCE(SUM(
                    event_type = "auth.login_locked"
                    AND created_at >= CURRENT_TIMESTAMP - INTERVAL 1 DAY
                ), 0) AS locked_24h
             FROM security_audit_events'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $activeLockouts = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM login_attempts
             WHERE locked_until IS NOT NULL
               AND locked_until > CURRENT_TIMESTAMP'
        )->fetchColumn();

        return [
            'total' => (int) ($events['total'] ?? 0),
            'events_24h' => (int) ($events['events_24h'] ?? 0),
            'failed_24h' => (int) ($events['failed_24h'] ?? 0),
            'locked_24h' => (int) ($events['locked_24h'] ?? 0),
            'active_lockouts' => $activeLockouts,
        ];
    }

    /** @return array<int,string> */
    public function eventTypes(): array
    {
        $statement = Database::connection()->query(
            'SELECT DISTINCT event_type
             FROM security_audit_events
             WHERE event_type <> ""
             ORDER BY event_type ASC
             LIMIT 200'
        );

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $statement->fetchAll(PDO::FETCH_COLUMN)
        )));
    }

    /**
     * @param array<string,string> $filters
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   total:int,page:int,pages:int,per_page:int
     * }
     */
    public function events(
        array $filters,
        int $page = 1,
        int $perPage = 25
    ): array {
        [$where, $params] = $this->eventWhere($filters);

        $perPage = max(10, min(50, $perPage));
        $page = max(1, $page);

        $count = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM security_audit_events e
             LEFT JOIN users actor ON actor.id = e.actor_user_id
             LEFT JOIN users subject ON subject.id = e.subject_user_id'
             . $where
        );
        $this->bind($count, $params);
        $count->execute();

        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare(
            'SELECT
                e.id,
                e.event_type,
                SUBSTRING_INDEX(e.event_type, ".", 1) AS event_family,
                e.actor_user_id,
                e.subject_user_id,
                CASE
                    WHEN actor.id IS NULL THEN "System / Anonymous"
                    ELSE CONCAT(actor.name, " · ", actor.email)
                END AS actor,
                CASE
                    WHEN subject.id IS NULL THEN "—"
                    ELSE CONCAT(subject.name, " · ", subject.email)
                END AS subject,
                CASE
                    WHEN e.event_type = "auth.login_failed" THEN "failed"
                    WHEN e.event_type = "auth.login_locked" THEN "locked"
                    WHEN e.event_type = "auth.login_success" THEN "success"
                    ELSE "information"
                END AS security_state,
                CASE
                    WHEN e.ip_hash IS NOT NULL
                      OR e.user_agent_hash IS NOT NULL
                    THEN "Protected client fingerprint stored"
                    ELSE "No client fingerprint"
                END AS client_state,
                e.created_at
             FROM security_audit_events e
             LEFT JOIN users actor ON actor.id = e.actor_user_id
             LEFT JOIN users subject ON subject.id = e.subject_user_id'
             . $where .
            ' ORDER BY e.created_at DESC, e.id DESC
              LIMIT :limit OFFSET :offset'
        );

        $this->bind($statement, $params);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportEvents(
        array $filters,
        int $limit = 5000
    ): array {
        [$where, $params] = $this->eventWhere($filters);
        $limit = max(1, min(5000, $limit));

        $statement = Database::connection()->prepare(
            'SELECT
                e.id,
                e.event_type,
                SUBSTRING_INDEX(e.event_type, ".", 1) AS event_family,
                CASE
                    WHEN actor.id IS NULL THEN "System / Anonymous"
                    ELSE CONCAT(actor.name, " · ", actor.email)
                END AS actor,
                CASE
                    WHEN subject.id IS NULL THEN "—"
                    ELSE CONCAT(subject.name, " · ", subject.email)
                END AS subject,
                CASE
                    WHEN e.ip_hash IS NOT NULL
                      OR e.user_agent_hash IS NOT NULL
                    THEN "protected"
                    ELSE "not_recorded"
                END AS client_state,
                e.created_at
             FROM security_audit_events e
             LEFT JOIN users actor ON actor.id = e.actor_user_id
             LEFT JOIN users subject ON subject.id = e.subject_user_id'
             . $where .
            ' ORDER BY e.created_at DESC, e.id DESC
              LIMIT ' . $limit
        );

        $this->bind($statement, $params);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function trend(int $days = 7): array
    {
        $days = max(3, min(30, $days));
        $start = date(
            'Y-m-d 00:00:00',
            strtotime('-' . ($days - 1) . ' days')
        );

        $statement = Database::connection()->prepare(
            'SELECT
                DATE(created_at) AS event_date,
                COALESCE(SUM(event_type = "auth.login_failed"), 0) AS failed,
                COALESCE(SUM(event_type = "auth.login_locked"), 0) AS locked,
                COALESCE(SUM(event_type = "auth.login_success"), 0) AS success
             FROM security_audit_events
             WHERE created_at >= :start
               AND event_type IN (
                    "auth.login_failed",
                    "auth.login_locked",
                    "auth.login_success"
               )
             GROUP BY DATE(created_at)
             ORDER BY event_date ASC'
        );
        $statement->execute(['start' => $start]);

        $indexed = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexed[(string) $row['event_date']] = [
                'failed' => (int) $row['failed'],
                'locked' => (int) $row['locked'],
                'success' => (int) $row['success'],
            ];
        }

        $rows = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $counts = $indexed[$date] ?? [
                'failed' => 0,
                'locked' => 0,
                'success' => 0,
            ];

            $rows[] = [
                'date' => $date,
                'label' => date('M j', strtotime($date)),
                'failed' => (int) $counts['failed'],
                'locked' => (int) $counts['locked'],
                'success' => (int) $counts['success'],
            ];
        }

        return $rows;
    }

    /** @return array<string,int> */
    public function signals(int $windowMinutes): array
    {
        $windowMinutes = max(5, min(1440, $windowMinutes));
        $since = date(
            'Y-m-d H:i:s',
            time() - ($windowMinutes * 60)
        );

        $statement = Database::connection()->prepare(
            'SELECT
                COALESCE(SUM(event_type = "auth.login_failed"), 0) AS failed,
                COALESCE(SUM(event_type = "auth.login_locked"), 0) AS locked,
                COALESCE(SUM(event_type = "auth.login_success"), 0) AS success
             FROM security_audit_events
             WHERE created_at >= :since'
        );
        $statement->execute(['since' => $since]);
        $events = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        $activeLockouts = (int) Database::connection()->query(
            'SELECT COUNT(*)
             FROM login_attempts
             WHERE locked_until IS NOT NULL
               AND locked_until > CURRENT_TIMESTAMP'
        )->fetchColumn();

        return [
            'failed' => (int) ($events['failed'] ?? 0),
            'locked' => (int) ($events['locked'] ?? 0),
            'success' => (int) ($events['success'] ?? 0),
            'active_lockouts' => $activeLockouts,
        ];
    }

    /** @return array<string,mixed> */
    public function retentionStatus(int $retentionDays): array
    {
        $retentionDays = max(30, min(3650, $retentionDays));

        $statement = Database::connection()->query(
            'SELECT
                COUNT(*) AS total,
                MIN(created_at) AS oldest_event,
                MAX(created_at) AS newest_event
             FROM security_audit_events'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        $cutoff = date(
            'Y-m-d H:i:s',
            time() - ($retentionDays * 86400)
        );

        $older = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM security_audit_events
             WHERE created_at < :cutoff'
        );
        $older->execute(['cutoff' => $cutoff]);

        return [
            'retention_days' => $retentionDays,
            'total' => (int) ($row['total'] ?? 0),
            'oldest_event' => (string) ($row['oldest_event'] ?? ''),
            'newest_event' => (string) ($row['newest_event'] ?? ''),
            'older_than_horizon' => (int) $older->fetchColumn(),
            'auto_delete' => false,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function incidentCandidates(int $limit = 100): array
    {
        $limit = max(5, min(500, $limit));

        $statement = Database::connection()->query(
            'SELECT
                e.id,
                e.event_type,
                e.actor_user_id,
                e.subject_user_id,
                CASE
                    WHEN actor.id IS NULL THEN "System / Anonymous"
                    ELSE CONCAT(actor.name, " · ", actor.email)
                END AS actor,
                CASE
                    WHEN subject.id IS NULL THEN "—"
                    ELSE CONCAT(subject.name, " · ", subject.email)
                END AS subject,
                CASE
                    WHEN incident.setting_key IS NULL THEN 0
                    ELSE 1
                END AS tracked,
                incident.updated_at AS workflow_updated_at,
                e.created_at
             FROM security_audit_events e
             LEFT JOIN users actor ON actor.id = e.actor_user_id
             LEFT JOIN users subject ON subject.id = e.subject_user_id
             LEFT JOIN application_settings incident
                ON incident.setting_key = CONCAT(
                    "security.incident.",
                    e.id
                )
             WHERE e.event_type IN (
                "auth.login_failed",
                "auth.login_locked"
             )
             ORDER BY
                CASE
                    WHEN incident.setting_key IS NOT NULL
                     AND incident.setting_value NOT LIKE
                        "%\"status\":\"resolved\"%"
                    THEN 0
                    WHEN incident.setting_key IS NOT NULL
                    THEN 1
                    ELSE 2
                END,
                incident.updated_at DESC,
                e.created_at DESC,
                e.id DESC
             LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function eventById(int $eventId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, event_type, actor_user_id, subject_user_id, created_at
             FROM security_audit_events
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $eventId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function incidentTimeline(int $eventId, int $limit = 20): array
    {
        $limit = max(5, min(50, $limit));
        $needle = '%"incident_event_id":' . $eventId . '%';

        $statement = Database::connection()->prepare(
            'SELECT
                e.id,
                e.event_type,
                e.actor_user_id,
                CASE
                    WHEN actor.id IS NULL THEN "System"
                    ELSE CONCAT(actor.name, " · ", actor.email)
                END AS actor,
                e.metadata_json,
                e.created_at
             FROM security_audit_events e
             LEFT JOIN users actor ON actor.id = e.actor_user_id
             WHERE e.event_type LIKE "admin.security_incident_%"
               AND e.metadata_json LIKE :needle
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['needle' => $needle]);

        return array_map(
            static function (array $row): array {
                $metadata = [];
                $decoded = json_decode(
                    (string) ($row['metadata_json'] ?? ''),
                    true
                );

                if (is_array($decoded)) {
                    $metadata = $decoded;
                }

                return [
                    'id' => (int) $row['id'],
                    'event_type' => (string) $row['event_type'],
                    'actor' => (string) $row['actor'],
                    'metadata' => $metadata,
                    'created_at' => (string) $row['created_at'],
                ];
            },
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function securityNotificationHistory(int $limit = 20): array
    {
        $limit = max(5, min(100, $limit));

        $statement = Database::connection()->query(
            'SELECT
                n.id,
                n.user_id,
                u.name AS user_name,
                u.email AS user_email,
                n.type,
                n.title,
                n.message,
                n.action_url,
                n.read_at,
                n.created_at
             FROM notifications n
             INNER JOIN users u ON u.id = n.user_id
             WHERE n.category = "security_alerts"
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function throttleRows(int $limit = 30): array
    {
        $limit = max(5, min(100, $limit));
        $decayMinutes = max(
            1,
            min(1440, (int) config('auth.login_decay_minutes', 15))
        );

        $statement = Database::connection()->query(
            'SELECT
                id,
                CONCAT(LEFT(key_hash, 8), "…", RIGHT(key_hash, 4))
                    AS fingerprint,
                attempts,
                first_attempt_at,
                last_attempt_at,
                locked_until,
                CASE
                    WHEN locked_until IS NOT NULL
                     AND locked_until > CURRENT_TIMESTAMP
                    THEN "locked"
                    WHEN last_attempt_at >=
                         (CURRENT_TIMESTAMP - INTERVAL '
                         . $decayMinutes . ' MINUTE)
                    THEN "monitoring"
                    ELSE "expired"
                END AS throttle_state
             FROM login_attempts
             ORDER BY
                CASE
                    WHEN locked_until IS NOT NULL
                     AND locked_until > CURRENT_TIMESTAMP
                    THEN 0 ELSE 1
                END,
                last_attempt_at DESC
             LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> */
    public function throttlePolicy(): array
    {
        return [
            'max_attempts' => max(
                1,
                (int) config('auth.login_max_attempts', 5)
            ),
            'decay_minutes' => max(
                1,
                (int) config('auth.login_decay_minutes', 15)
            ),
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{0:string,1:array<string,string>}
     */
    private function eventWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        $search = mb_substr(
            trim((string) ($filters['q'] ?? '')),
            0,
            100
        );

        if ($search !== '') {
            $conditions[] =
                'CONCAT_WS(
                    " ",
                    e.event_type,
                    actor.name,
                    actor.email,
                    subject.name,
                    subject.email
                 ) LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $family = strtolower(trim(
            (string) ($filters['family'] ?? '')
        ));

        if (in_array($family, ['auth', 'account', 'admin'], true)) {
            $conditions[] = 'e.event_type LIKE :family';
            $params['family'] = $family . '.%';
        }

        $eventType = mb_substr(
            trim((string) ($filters['event_type'] ?? '')),
            0,
            100
        );

        if ($eventType !== '') {
            $conditions[] = 'e.event_type = :event_type';
            $params['event_type'] = $eventType;
        }

        $dateFrom = $this->validDate(
            (string) ($filters['date_from'] ?? '')
        );

        if ($dateFrom !== null) {
            $conditions[] = 'e.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = $this->validDate(
            (string) ($filters['date_to'] ?? '')
        );

        if ($dateTo !== null) {
            $conditions[] = 'e.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        return [
            $conditions === []
                ? ''
                : ' WHERE ' . implode(' AND ', $conditions),
            $params,
        ];
    }

    private function validDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        if (
            !$parsed
            || $parsed->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                'Security event dates must use YYYY-MM-DD.'
            );
        }

        return $value;
    }

    /** @param array<string,string> $params */
    private function bind(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }
}
