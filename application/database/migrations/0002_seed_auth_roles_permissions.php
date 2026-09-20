<?php

declare(strict_types=1);

use PDO;
use RuntimeException;

return static function (PDO $pdo): void {
    $roles = [
        ['User', 'user'],
        ['Developer', 'developer'],
        ['Administrator', 'administrator'],
    ];

    $roleStatement = $pdo->prepare(
        'INSERT INTO roles (name, slug, is_system) VALUES (:name, :slug, 1)'
    );

    foreach ($roles as [$name, $slug]) {
        $roleStatement->execute([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    $permissions = [
        ['Access user account', 'account.access'],
        ['Access developer workspace', 'developer.access'],
        ['Access admin dashboard', 'dashboard.access'],
    ];

    $permissionStatement = $pdo->prepare(
        'INSERT INTO permissions (name, slug) VALUES (:name, :slug)'
    );

    foreach ($permissions as [$name, $slug]) {
        $permissionStatement->execute([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    $roleIds = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $permissionIds = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach (['user', 'developer', 'administrator'] as $role) {
        if (!isset($roleIds[$role])) {
            throw new RuntimeException("Missing seeded role [{$role}].");
        }
    }

    foreach (['account.access', 'developer.access', 'dashboard.access'] as $permission) {
        if (!isset($permissionIds[$permission])) {
            throw new RuntimeException("Missing seeded permission [{$permission}].");
        }
    }

    $assignments = [
        'user' => ['account.access'],
        'developer' => ['account.access', 'developer.access'],
        'administrator' => ['account.access', 'developer.access', 'dashboard.access'],
    ];

    $assign = $pdo->prepare(
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
    );

    foreach ($assignments as $roleSlug => $permissionSlugs) {
        foreach ($permissionSlugs as $permissionSlug) {
            $assign->execute([
                'role_id' => $roleIds[$roleSlug],
                'permission_id' => $permissionIds[$permissionSlug],
            ]);
        }
    }
};
