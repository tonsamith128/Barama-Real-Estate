<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class SecurityAuditRepository
{
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
