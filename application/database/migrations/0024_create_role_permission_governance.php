<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $roleStatement = $pdo->prepare(
        'INSERT INTO roles (name, slug, is_system)
         VALUES (:name, :slug, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = 1'
    );
    foreach ([
        ['Company', 'company'],
        ['Super Administrator', 'super-administrator'],
    ] as [$name, $slug]) {
        $roleStatement->execute(['name' => $name, 'slug' => $slug]);
    }

    $permission = $pdo->prepare(
        'INSERT INTO permissions (name, slug)
         VALUES ("Manage roles and permissions", "roles.manage")
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $permission->execute();

    $roleIds = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $permissionIds = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach (['user', 'developer', 'company', 'administrator', 'super-administrator'] as $role) {
        if (!isset($roleIds[$role])) {
            throw new RuntimeException("Missing system role [{$role}].");
        }
    }

    $requiredPermissions = [
        'user' => ['account.access'],
        'developer' => [
            'developer.access', 'company.manage', 'project.manage', 'property.manage',
            'listing.manage', 'media.manage', 'inquiry.manage', 'lead.manage',
        ],
        'company' => [
            'account.access', 'developer.access', 'company.manage', 'property.manage',
            'listing.manage', 'media.manage', 'inquiry.manage', 'lead.manage',
        ],
    ];

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, permission_id)
         VALUES (:role_id, :permission_id)'
    );

    foreach ($requiredPermissions as $roleSlug => $slugs) {
        foreach ($slugs as $slug) {
            if (isset($permissionIds[$slug])) {
                $insert->execute([
                    'role_id' => (int) $roleIds[$roleSlug],
                    'permission_id' => (int) $permissionIds[$slug],
                ]);
            }
        }
    }

    foreach ($permissionIds as $slug => $permissionId) {
        if ($slug !== 'roles.manage') {
            $insert->execute([
                'role_id' => (int) $roleIds['administrator'],
                'permission_id' => (int) $permissionId,
            ]);
        }
        $insert->execute([
            'role_id' => (int) $roleIds['super-administrator'],
            'permission_id' => (int) $permissionId,
        ]);
    }

    $bootstrap = $pdo->query(
        'SELECT MIN(users.id)
         FROM users
         INNER JOIN user_roles ON user_roles.user_id = users.id
         INNER JOIN roles ON roles.id = user_roles.role_id
         WHERE roles.slug = "administrator" AND users.status = "active"'
    )->fetchColumn();

    if ($bootstrap !== false && (int) $bootstrap > 0) {
        $insertRole = $pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $insertRole->execute([
            'user_id' => (int) $bootstrap,
            'role_id' => (int) $roleIds['super-administrator'],
        ]);
    }
};
