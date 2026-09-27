<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_coupons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    discount_type VARCHAR(24) NOT NULL,
    discount_value DECIMAL(18,2) NOT NULL,
    currency CHAR(3) NULL,
    max_redemptions INT UNSIGNED NULL,
    per_company_limit INT UNSIGNED NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'inactive',
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_coupons_uuid_unique (uuid),
    UNIQUE KEY billing_coupons_code_unique (code),
    KEY billing_coupons_status_window_index (status, starts_at, ends_at),
    CONSTRAINT billing_coupons_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT billing_coupons_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
ALTER TABLE billing_invoices
    ADD COLUMN coupon_id BIGINT UNSIGNED NULL AFTER subscription_id,
    ADD COLUMN discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD KEY billing_invoices_coupon_index (coupon_id),
    ADD CONSTRAINT billing_invoices_coupon_fk
        FOREIGN KEY (coupon_id) REFERENCES billing_coupons(id) ON DELETE SET NULL
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_coupon_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(18,2) NOT NULL,
    currency CHAR(3) NOT NULL,
    redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY billing_coupon_redemptions_invoice_unique (invoice_id),
    KEY billing_coupon_redemptions_coupon_company_index (coupon_id, company_id),
    CONSTRAINT billing_coupon_redemptions_coupon_fk
        FOREIGN KEY (coupon_id) REFERENCES billing_coupons(id) ON DELETE RESTRICT,
    CONSTRAINT billing_coupon_redemptions_invoice_fk
        FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
    CONSTRAINT billing_coupon_redemptions_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT billing_coupon_redemptions_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
