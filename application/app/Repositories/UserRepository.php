<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class UserRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, name, email, password_hash, status, auth_version,
                    email_verified_at, last_login_at, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, name, email, password_hash, status, auth_version,
                    email_verified_at, last_login_at, created_at, updated_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (uuid, name, email, password_hash, status)
             VALUES (:uuid, :name, :email, :password_hash, "active")'
        );
        $statement->execute([
            'uuid' => $this->uuidV4(),
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateLastLogin(int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['password_hash' => $passwordHash, 'id' => $userId]);
    }

    public function updatePasswordAndInvalidateSessions(
        int $userId,
        string $passwordHash
    ): int {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 auth_version = auth_version + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['password_hash' => $passwordHash, 'id' => $userId]);

        return $this->authVersion($userId);
    }

    public function markEmailVerified(int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE users
             SET email_verified_at = COALESCE(email_verified_at, CURRENT_TIMESTAMP),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    public function incrementAuthVersion(int $userId): int
    {
        Database::connection()->prepare(
            'UPDATE users
             SET auth_version = auth_version + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['id' => $userId]);

        return $this->authVersion($userId);
    }

    public function setStatusAndInvalidate(int $userId, string $status): int
    {
        Database::connection()->prepare(
            'UPDATE users
             SET status = :status,
                 auth_version = auth_version + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['status' => $status, 'id' => $userId]);

        return $this->authVersion($userId);
    }

    private function authVersion(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT auth_version FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);

        return max(1, (int) $statement->fetchColumn());
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
