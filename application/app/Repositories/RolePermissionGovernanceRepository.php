<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class RolePermissionGovernanceRepository
{
    /** @return array<int,array<string,mixed>> */
    public function roles(): array
    {
        $statement = Database::connection()->query(
            'SELECT roles.id, roles.name, roles.slug, roles.is_system,
                    COUNT(DISTINCT user_roles.user_id) AS member_count,
                    COUNT(DISTINCT CASE WHEN users.status = "active" THEN users.id END) AS active_member_count
             FROM roles
             LEFT JOIN user_roles ON user_roles.role_id = roles.id
             LEFT JOIN users ON users.id = user_roles.user_id
             GROUP BY roles.id, roles.name, roles.slug, roles.is_system
             ORDER BY roles.id ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array{id:int,name:string,slug:string}> */
    public function permissions(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, slug FROM permissions ORDER BY slug ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,array<int,string>> */
    public function permissionMap(): array
    {
        $statement = Database::connection()->query(
            'SELECT roles.slug AS role_slug, permissions.slug AS permission_slug
             FROM role_permissions
             INNER JOIN roles ON roles.id = role_permissions.role_id
             INNER JOIN permissions ON permissions.id = role_permissions.permission_id
             ORDER BY roles.slug ASC, permissions.slug ASC'
        );

        $map = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $role = (string) ($row['role_slug'] ?? '');
            $permission = (string) ($row['permission_slug'] ?? '');
            if ($role !== '' && $permission !== '') {
                $map[$role][] = $permission;
            }
        }

        return $map;
    }

    /** @param array<int,string> $permissionSlugs */
    public function syncPermissions(string $roleSlug, array $permissionSlugs): void
    {
        $pdo = Database::connection();
        $role = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $role->execute(['slug' => $roleSlug]);
        $roleId = $role->fetchColumn();

        if ($roleId === false) {
            throw new RuntimeException('The selected role no longer exists.');
        }

        $available = $pdo->query('SELECT slug, id FROM permissions')
            ->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        foreach ($permissionSlugs as $slug) {
            if (!isset($available[$slug])) {
                throw new RuntimeException('Unknown permission [' . $slug . '].');
            }
        }

        try {
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $delete->execute(['role_id' => (int) $roleId]);

            $insert = $pdo->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );
            foreach ($permissionSlugs as $slug) {
                $insert->execute([
                    'role_id' => (int) $roleId,
                    'permission_id' => (int) $available[$slug],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
