<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use RuntimeException;

final class RoleRepository
{
    public function assignRole(int $userId, string $roleSlug): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             SELECT :user_id, roles.id
             FROM roles
             WHERE roles.slug = :role_slug'
        );

        $statement->execute([
            'user_id' => $userId,
            'role_slug' => $roleSlug,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException("Role [{$roleSlug}] could not be assigned.");
        }
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
