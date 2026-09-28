<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $permission = $pdo->prepare(
        'INSERT INTO permissions (name, slug)
         VALUES ("Manage platform communications", "communication.manage")
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $permission->execute();

    $administratorId = $pdo
        ->query('SELECT id FROM roles WHERE slug = "administrator" LIMIT 1')
        ->fetchColumn();

    $permissionId = $pdo
        ->query('SELECT id FROM permissions WHERE slug = "communication.manage" LIMIT 1')
        ->fetchColumn();

    if ($administratorId === false || $permissionId === false) {
        throw new RuntimeException(
            'Communication permission dependencies are unavailable.'
        );
    }

    $assign = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, permission_id)
         VALUES (:role_id, :permission_id)'
    );
    $assign->execute([
        'role_id' => (int) $administratorId,
        'permission_id' => (int) $permissionId,
    ]);
};
