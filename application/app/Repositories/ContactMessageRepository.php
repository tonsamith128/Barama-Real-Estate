<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class ContactMessageRepository
{
    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO contact_messages
                (uuid, user_id, contact_name, contact_email, contact_phone,
                 subject, message, source, status, ip_hash, user_agent_hash)
             VALUES
                (:uuid, :user_id, :contact_name, :contact_email, :contact_phone,
                 :subject, :message, "contact_page", "new", :ip_hash, :user_agent_hash)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'user_id' => $data['user_id'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_hash' => $data['ip_hash'],
            'user_agent_hash' => $data['user_agent_hash'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row = Database::connection()->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = "new"), 0) AS new_count,
                    COALESCE(SUM(status = "read"), 0) AS read_count,
                    COALESCE(SUM(status = "resolved"), 0) AS resolved_count,
                    COALESCE(SUM(status = "spam"), 0) AS spam_count
             FROM contact_messages'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'new' => (int) ($row['new_count'] ?? 0),
            'read' => (int) ($row['read_count'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
            'spam' => (int) ($row['spam_count'] ?? 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $statement = Database::connection()->query(
            'SELECT m.id, m.uuid, m.user_id, m.contact_name, m.contact_email,
                    m.contact_phone, m.subject, m.message, m.status,
                    m.created_at, m.updated_at, m.resolved_at,
                    COALESCE(handler.name, "") AS handled_by_name
             FROM contact_messages m
             LEFT JOIN users handler ON handler.id = m.handled_by_user_id
             ORDER BY
                CASE m.status
                    WHEN "new" THEN 0
                    WHEN "read" THEN 1
                    WHEN "resolved" THEN 2
                    ELSE 3
                END,
                m.created_at DESC
             LIMIT ' . $limit
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM contact_messages WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function setStatus(int $id, string $status, int $actorUserId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE contact_messages
             SET status = :status,
                 handled_by_user_id = :handled_by_user_id,
                 resolved_at = CASE
                    WHEN :resolved_status = "resolved" THEN COALESCE(resolved_at, CURRENT_TIMESTAMP)
                    ELSE NULL
                 END,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'handled_by_user_id' => $actorUserId,
            'resolved_status' => $status,
            'id' => $id,
        ]);
    }
}
