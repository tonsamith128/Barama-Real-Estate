<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(191) NOT NULL,
    excerpt VARCHAR(500) NULL,
    content_text LONGTEXT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    seo_title VARCHAR(191) NULL,
    seo_description VARCHAR(300) NULL,
    robots_directive VARCHAR(64) NOT NULL DEFAULT 'index,follow',
    published_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY cms_pages_uuid_unique (uuid),
    UNIQUE KEY cms_pages_slug_unique (slug),
    KEY cms_pages_status_index (status),
    KEY cms_pages_published_index (published_at),
    CONSTRAINT cms_pages_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT cms_pages_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $permission = $pdo->prepare(
        'INSERT INTO permissions (name, slug)
         VALUES ("Manage CMS pages", "page.manage")
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $permission->execute();

    $administratorId = $pdo->query(
        'SELECT id FROM roles WHERE slug = "administrator" LIMIT 1'
    )->fetchColumn();
    $permissionId = $pdo->query(
        'SELECT id FROM permissions WHERE slug = "page.manage" LIMIT 1'
    )->fetchColumn();

    if ($administratorId === false || $permissionId === false) {
        throw new RuntimeException('CMS page permission prerequisites are unavailable.');
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
