<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;
use RuntimeException;

final class RoleRepository
{
    public function assignRole(int $userId, string $roleSlug): void
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id)
             SELECT :user_id, roles.id
             FROM roles
             WHERE roles.slug = :role_slug'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_slug' => $roleSlug,
        ]);

        if (!$this->userHasRole($userId, $roleSlug)) {
            throw new RuntimeException("Role [{$roleSlug}] could not be assigned.");
        }
    }

    public function removeRole(int $userId, string $roleSlug): void
    {
        $statement = Database::connection()->prepare(
            'DELETE user_roles
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
               AND roles.slug = :role_slug'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_slug' => $roleSlug,
        ]);
    }

    /** @return array<int,string> */
    public function rolesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT roles.slug
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
             ORDER BY roles.slug ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (mixed $value): string => (string) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function activeRoleUserCount(string $roleSlug): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(DISTINCT users.id)
             FROM users
             INNER JOIN user_roles ON user_roles.user_id = users.id
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE roles.slug = :role_slug
               AND users.status = "active"'
        );
        $statement->execute(['role_slug' => $roleSlug]);

        return (int) $statement->fetchColumn();
    }

    public function userHasRole(int $userId, string $roleSlug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
               AND roles.slug = :role_slug
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_slug' => $roleSlug,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function userCan(int $userId, string $permissionSlug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM user_roles
             INNER JOIN role_permissions ON role_permissions.role_id = user_roles.role_id
             INNER JOIN permissions ON permissions.id = role_permissions.permission_id
             WHERE user_roles.user_id = :user_id
               AND permissions.slug = :permission_slug
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug,
        ]);

        return $statement->fetchColumn() !== false;
    }
}
