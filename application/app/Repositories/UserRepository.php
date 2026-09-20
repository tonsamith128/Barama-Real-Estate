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
            'SELECT id, uuid, name, email, password_hash, status, email_verified_at, last_login_at, created_at, updated_at
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
            'SELECT id, uuid, name, email, password_hash, status, email_verified_at, last_login_at, created_at, updated_at
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
             VALUES (:uuid, :name, :email, :password_hash, :status)'
        );

        $statement->execute([
            'uuid' => $this->uuidV4(),
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'status' => 'active',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateLastLogin(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
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
