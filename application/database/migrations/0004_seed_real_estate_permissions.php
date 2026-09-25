<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $permissions = [
        ['Manage locations', 'location.manage'],
        ['Manage amenities', 'amenity.manage'],
        ['Manage companies', 'company.manage'],
        ['Manage development projects', 'project.manage'],
        ['Manage properties', 'property.manage'],
        ['Manage listings', 'listing.manage'],
        ['Manage media', 'media.manage'],
        ['Manage inquiries', 'inquiry.manage'],
        ['Manage leads', 'lead.manage'],
    ];

    $insertPermission = $pdo->prepare(
        'INSERT INTO permissions (name, slug)
         VALUES (:name, :slug)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );

    foreach ($permissions as [$name, $slug]) {
        $insertPermission->execute([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    $roleIds = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $permissionIds = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach (['developer', 'administrator'] as $role) {
        if (!isset($roleIds[$role])) {
            throw new RuntimeException("Missing role [{$role}] required by domain permissions.");
        }
    }

    $developerPermissions = [
        'company.manage',
        'project.manage',
        'property.manage',
        'listing.manage',
        'media.manage',
        'inquiry.manage',
        'lead.manage',
    ];

    $administratorPermissions = array_column($permissions, 1);

    $assign = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, permission_id)
         VALUES (:role_id, :permission_id)'
    );

    foreach ([
        'developer' => $developerPermissions,
        'administrator' => $administratorPermissions,
    ] as $roleSlug => $permissionSlugs) {
        foreach ($permissionSlugs as $permissionSlug) {
            if (!isset($permissionIds[$permissionSlug])) {
                throw new RuntimeException(
                    "Missing permission [{$permissionSlug}] required by domain role assignment."
                );
            }

            $assign->execute([
                'role_id' => $roleIds[$roleSlug],
                'permission_id' => $permissionIds[$permissionSlug],
            ]);
        }
    }
};
