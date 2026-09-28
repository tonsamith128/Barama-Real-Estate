<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class NotificationRepository
{
    /** @return array<string,bool> */
    public function preferences(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT in_app_enabled, email_enabled, saved_search_alerts,
                    viewing_updates, inquiry_updates
             FROM notification_preferences
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'saved_search_alerts' => true,
                'viewing_updates' => true,
                'inquiry_updates' => true,
            ];
        }

        return [
            'in_app_enabled' => (bool) $row['in_app_enabled'],
            'email_enabled' => (bool) $row['email_enabled'],
            'saved_search_alerts' => (bool) $row['saved_search_alerts'],
            'viewing_updates' => (bool) $row['viewing_updates'],
            'inquiry_updates' => (bool) $row['inquiry_updates'],
        ];
    }

    /** @param array<string,bool> $preferences */
    public function savePreferences(int $userId, array $preferences): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO notification_preferences
                (user_id, in_app_enabled, email_enabled, saved_search_alerts,
                 viewing_updates, inquiry_updates)
             VALUES
                (:user_id, :in_app_enabled, :email_enabled, :saved_search_alerts,
                 :viewing_updates, :inquiry_updates)
             ON DUPLICATE KEY UPDATE
                in_app_enabled = VALUES(in_app_enabled),
                email_enabled = VALUES(email_enabled),
                saved_search_alerts = VALUES(saved_search_alerts),
                viewing_updates = VALUES(viewing_updates),
                inquiry_updates = VALUES(inquiry_updates)'
        );
        $statement->execute([
            'user_id' => $userId,
            'in_app_enabled' => $preferences['in_app_enabled'] ? 1 : 0,
            'email_enabled' => $preferences['email_enabled'] ? 1 : 0,
            'saved_search_alerts' => $preferences['saved_search_alerts'] ? 1 : 0,
            'viewing_updates' => $preferences['viewing_updates'] ? 1 : 0,
            'inquiry_updates' => $preferences['inquiry_updates'] ? 1 : 0,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function user(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function companyRecipients(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT u.id, u.name, u.email
             FROM company_members cm
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.company_id = :company_id
               AND cm.status = "active"
               AND u.status = "active"
             ORDER BY u.id ASC'
        );
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $data */
    public function createNotification(array $data): ?int
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO notifications
                (uuid, user_id, category, type, title, message, action_url,
                 entity_type, entity_id, dedupe_key)
             VALUES
                (:uuid, :user_id, :category, :type, :title, :message, :action_url,
                 :entity_type, :entity_id, :dedupe_key)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'user_id' => $data['user_id'],
            'category' => $data['category'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'dedupe_key' => $data['dedupe_key'],
        ]);

        if ($statement->rowCount() === 0) {
            $existing = Database::connection()->prepare(
                'SELECT id
                 FROM notifications
                 WHERE user_id = :user_id AND dedupe_key = :dedupe_key
                 LIMIT 1'
            );
            $existing->execute([
                'user_id' => $data['user_id'],
                'dedupe_key' => $data['dedupe_key'],
            ]);
            $id = $existing->fetchColumn();

            return $id !== false ? (int) $id : null;
        }

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function queueEmail(array $data): ?int
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO email_outbox
                (uuid, user_id, notification_id, to_address, subject,
                 html_body, text_body, status, available_at, dedupe_key)
             VALUES
                (:uuid, :user_id, :notification_id, :to_address, :subject,
                 :html_body, :text_body, "pending", CURRENT_TIMESTAMP, :dedupe_key)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'user_id' => $data['user_id'],
            'notification_id' => $data['notification_id'],
            'to_address' => $data['to_address'],
            'subject' => $data['subject'],
            'html_body' => $data['html_body'],
            'text_body' => $data['text_body'],
            'dedupe_key' => $data['dedupe_key'],
        ]);

        return $statement->rowCount() > 0
            ? (int) Database::connection()->lastInsertId()
            : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function notifications(int $userId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, category, type, title, message, action_url,
                    entity_type, entity_id, read_at, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function notificationsForAccount(
        int $userId,
        string $search = '',
        string $state = '',
        string $category = '',
        int $limit = 150
    ): array {
        $where = [
            'user_id = :user_id',
            'category IN ("saved_search_alerts", "viewing_updates", "inquiry_updates")',
            'CONCAT_WS(" ", title, message, type) LIKE :search',
        ];
        $params = [
            'user_id' => $userId,
            'search' => '%' . trim($search) . '%',
        ];

        if ($state === 'unread') {
            $where[] = 'read_at IS NULL';
        } elseif ($state === 'read') {
            $where[] = 'read_at IS NOT NULL';
        }

        if (in_array($category, ['saved_search_alerts', 'viewing_updates', 'inquiry_updates'], true)) {
            $where[] = 'category = :category';
            $params['category'] = $category;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, uuid, category, type, title, message, action_url,
                    entity_type, entity_id, read_at, created_at
             FROM notifications
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );

        foreach ($params as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
        $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications
             SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE notifications
             SET read_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function adminNotifications(int $limit = 1000): array
    {
        $statement = Database::connection()->prepare(
            'SELECT n.id, n.uuid, n.user_id, u.name AS user_name,
                    u.email AS user_email, n.category, n.type,
                    n.title, n.message, n.action_url,
                    n.entity_type, n.entity_id, n.read_at, n.created_at
             FROM notifications n
             INNER JOIN users u ON u.id = n.user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(
            ':limit',
            max(1, min(2000, $limit)),
            PDO::PARAM_INT
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function adminOutbox(int $limit = 1500): array
    {
        $statement = Database::connection()->prepare(
            'SELECT o.id, o.uuid, o.user_id, u.name AS user_name,
                    o.notification_id, o.to_address, o.subject,
                    o.status, o.attempts, o.available_at, o.locked_at,
                    o.sent_at, o.last_error, o.dedupe_key,
                    o.created_at, o.updated_at
             FROM email_outbox o
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT :limit'
        );
        $statement->bindValue(
            ':limit',
            max(1, min(3000, $limit)),
            PDO::PARAM_INT
        );
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> */
    public function communicationsSummary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                (SELECT COUNT(*) FROM notifications) AS notifications,
                (
                    SELECT COUNT(*)
                    FROM notifications
                    WHERE read_at IS NULL
                ) AS unread_notifications,
                (
                    SELECT COUNT(*)
                    FROM email_outbox
                    WHERE status = "pending"
                ) AS pending_email,
                (
                    SELECT COUNT(*)
                    FROM email_outbox
                    WHERE status = "processing"
                ) AS processing_email,
                (
                    SELECT COUNT(*)
                    FROM email_outbox
                    WHERE status = "failed"
                ) AS failed_email,
                (
                    SELECT COUNT(*)
                    FROM email_outbox
                    WHERE status = "sent"
                ) AS sent_email,
                (
                    SELECT COUNT(*)
                    FROM email_outbox
                    WHERE status = "cancelled"
                ) AS cancelled_email'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(
                static fn (mixed $value): int => (int) $value,
                $row
            )
            : [];
    }

    /** @return array<string,mixed>|null */
    public function outboxMessage(int $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT *
                FROM email_outbox
                WHERE id = :id
                LIMIT 1';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function requeueEmail(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE email_outbox
             SET status = "pending",
                 attempts = 0,
                 available_at = CURRENT_TIMESTAMP,
                 locked_at = NULL,
                 sent_at = NULL,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function cancelEmail(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE email_outbox
             SET status = "cancelled",
                 locked_at = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function outboxCandidates(int $limit, int $maxAttempts, int $staleMinutes): array
    {
        $staleBefore = date('Y-m-d H:i:s', time() - max(60, $staleMinutes * 60));

        $statement = Database::connection()->prepare(
            'SELECT id
             FROM email_outbox
             WHERE attempts < :max_attempts
               AND available_at <= CURRENT_TIMESTAMP
               AND (
                    status IN ("pending", "failed")
                    OR (status = "processing" AND locked_at < :stale_before)
               )
             ORDER BY available_at ASC, id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':max_attempts', max(1, $maxAttempts), PDO::PARAM_INT);
        $statement->bindValue(':stale_before', $staleBefore);
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function claimEmail(int $id, int $maxAttempts, int $staleMinutes): ?array
    {
        $staleBefore = date('Y-m-d H:i:s', time() - max(60, $staleMinutes * 60));
        $pdo = Database::connection();

        $claim = $pdo->prepare(
            'UPDATE email_outbox
             SET status = "processing",
                 attempts = attempts + 1,
                 locked_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND attempts < :max_attempts
               AND available_at <= CURRENT_TIMESTAMP
               AND (
                    status IN ("pending", "failed")
                    OR (status = "processing" AND locked_at < :stale_before)
               )'
        );
        $claim->execute([
            'id' => $id,
            'max_attempts' => max(1, $maxAttempts),
            'stale_before' => $staleBefore,
        ]);

        if ($claim->rowCount() === 0) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT *
             FROM email_outbox
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markEmailSent(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE email_outbox
             SET status = "sent",
                 sent_at = CURRENT_TIMESTAMP,
                 locked_at = NULL,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function markEmailFailed(int $id, string $error, string $availableAt): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE email_outbox
             SET status = "failed",
                 available_at = :available_at,
                 locked_at = NULL,
                 last_error = :last_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'available_at' => $availableAt,
            'last_error' => mb_substr($error, 0, 1000),
        ]);
    }

    public function outboxCount(string $status): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM email_outbox WHERE status = :status'
        );
        $statement->execute(['status' => $status]);

        return (int) $statement->fetchColumn();
    }
}
