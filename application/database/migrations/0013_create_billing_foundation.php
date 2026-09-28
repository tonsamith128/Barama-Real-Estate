<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description TEXT NULL,
    billing_interval VARCHAR(24) NOT NULL DEFAULT 'monthly',
    price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    listing_limit INT UNSIGNED NULL,
    property_limit INT UNSIGNED NULL,
    project_limit INT UNSIGNED NULL,
    team_member_limit INT UNSIGNED NULL,
    featured_listing_limit INT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'inactive',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY subscription_plans_uuid_unique (uuid),
    UNIQUE KEY subscription_plans_slug_unique (slug),
    KEY subscription_plans_status_sort_index (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS company_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    source VARCHAR(32) NOT NULL DEFAULT 'admin',
    external_reference VARCHAR(191) NULL,
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    renews_at DATETIME NULL,
    expires_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY company_subscriptions_uuid_unique (uuid),
    KEY company_subscriptions_company_status_index (company_id, status),
    KEY company_subscriptions_plan_status_index (plan_id, status),
    KEY company_subscriptions_expiry_index (expires_at),
    CONSTRAINT company_subscriptions_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT company_subscriptions_plan_fk
        FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    CONSTRAINT company_subscriptions_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT company_subscriptions_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $permission = $pdo->prepare(
        'INSERT INTO permissions (name, slug)
         VALUES ("Manage billing and subscriptions", "billing.manage")
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $permission->execute();

    $administratorId = $pdo
        ->query('SELECT id FROM roles WHERE slug = "administrator" LIMIT 1')
        ->fetchColumn();

    $permissionId = $pdo
        ->query('SELECT id FROM permissions WHERE slug = "billing.manage" LIMIT 1')
        ->fetchColumn();

    if ($administratorId === false || $permissionId === false) {
        throw new RuntimeException('Billing permission dependencies are unavailable.');
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
