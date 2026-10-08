<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class SecurityAuditRepository
{
    /** @return array<int,array<string,mixed>> */
    public function recentForUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min(25, $limit));

        $statement = Database::connection()->prepare(
            'SELECT id, event_type, metadata_json, created_at
             FROM security_audit_events
             WHERE actor_user_id = :actor_user_id
                OR subject_user_id = :subject_user_id
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'actor_user_id' => $userId,
            'subject_user_id' => $userId,
        ]);

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static function (array $row): array {
                $metadata = [];

                if (!empty($row['metadata_json'])) {
                    $decoded = json_decode(
                        (string) $row['metadata_json'],
                        true
                    );

                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                return [
                    'id' => (int) $row['id'],
                    'event_type' => (string) $row['event_type'],
                    'metadata' => $metadata,
                    'created_at' => (string) $row['created_at'],
                ];
            },
            is_array($rows) ? $rows : []
        );
    }
    /** @param array<string,mixed> $data */
    public function record(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO security_audit_events
                (actor_user_id, subject_user_id, event_type, ip_hash,
                 user_agent_hash, metadata_json)
             VALUES
                (:actor_user_id, :subject_user_id, :event_type, :ip_hash,
                 :user_agent_hash, :metadata_json)'
        );
        $statement->execute([
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'subject_user_id' => $data['subject_user_id'] ?? null,
            'event_type' => (string) $data['event_type'],
            'ip_hash' => $data['ip_hash'] ?? null,
            'user_agent_hash' => $data['user_agent_hash'] ?? null,
            'metadata_json' => $data['metadata_json'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
