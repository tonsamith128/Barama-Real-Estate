<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class EmailVerificationRepository
{
    public function replaceForUser(
        int $userId,
        string $email,
        string $tokenHash,
        string $expiresAt
    ): void {
        $pdo = Database::connection();

        $pdo->prepare(
            'UPDATE email_verification_tokens
             SET used_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND used_at IS NULL'
        )->execute(['user_id' => $userId]);

        $statement = $pdo->prepare(
            'INSERT INTO email_verification_tokens
                (user_id, email, token_hash, expires_at)
             VALUES
                (:user_id, :email, :token_hash, :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'email' => strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function recentlyIssued(int $userId, int $seconds): bool
    {
        $after = date('Y-m-d H:i:s', time() - max(1, $seconds));
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM email_verification_tokens
             WHERE user_id = :user_id
               AND used_at IS NULL
               AND created_at >= :after
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'after' => $after]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed>|null */
    public function findValid(int $userId, string $tokenHash): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.id, t.user_id, t.email, t.expires_at
             FROM email_verification_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.user_id = :user_id
               AND t.token_hash = :token_hash
               AND t.used_at IS NULL
               AND t.expires_at > CURRENT_TIMESTAMP
               AND u.status = "active"
               AND u.email = t.email
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markUsed(int $id): void
    {
        Database::connection()->prepare(
            'UPDATE email_verification_tokens
             SET used_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['id' => $id]);
    }
}
