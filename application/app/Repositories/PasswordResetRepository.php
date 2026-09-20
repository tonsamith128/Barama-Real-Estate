<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class PasswordResetRepository
{
    public function replaceForUser(int $userId, string $tokenHash, string $expiresAt): void
    {
        $pdo = Database::connection();

        $delete = $pdo->prepare(
            'DELETE FROM password_reset_tokens
             WHERE user_id = :user_id
               AND used_at IS NULL'
        );
        $delete->execute(['user_id' => $userId]);

        $insert = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findValid(string $tokenHash, string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT password_reset_tokens.id,
                    password_reset_tokens.user_id,
                    password_reset_tokens.expires_at,
                    users.email
             FROM password_reset_tokens
             INNER JOIN users ON users.id = password_reset_tokens.user_id
             WHERE password_reset_tokens.token_hash = :token_hash
               AND users.email = :email
               AND password_reset_tokens.used_at IS NULL
               AND password_reset_tokens.expires_at > CURRENT_TIMESTAMP
             LIMIT 1'
        );

        $statement->execute([
            'token_hash' => $tokenHash,
            'email' => strtolower(trim($email)),
        ]);

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($record) ? $record : null;
    }

    public function markUsed(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE password_reset_tokens
             SET used_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function deleteByHash(string $tokenHash): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM password_reset_tokens WHERE token_hash = :token_hash'
        );
        $statement->execute(['token_hash' => $tokenHash]);
    }
}
