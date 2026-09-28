<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class NewsletterSubscriberRepository
{
    public function subscribe(
        string $email,
        string $source,
        ?string $ipHash
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO newsletter_subscribers
                (uuid, email, status, source, ip_hash, subscribed_at, unsubscribed_at)
             VALUES
                (:uuid, :email, "active", :source, :ip_hash, CURRENT_TIMESTAMP, NULL)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                status = "active",
                source = VALUES(source),
                ip_hash = VALUES(ip_hash),
                subscribed_at = CURRENT_TIMESTAMP,
                unsubscribed_at = NULL,
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'email' => $email,
            'source' => $source,
            'ip_hash' => $ipHash,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function activeCount(): int
    {
        $statement = Database::connection()->query(
            'SELECT COUNT(*)
             FROM newsletter_subscribers
             WHERE status = "active"'
        );

        return (int) $statement->fetchColumn();
    }
}
