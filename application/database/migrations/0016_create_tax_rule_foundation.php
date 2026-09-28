<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_tax_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    rate_percent DECIMAL(7,4) NOT NULL,
    country_code CHAR(2) NULL,
    region_code VARCHAR(64) NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'inactive',
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_tax_rules_uuid_unique (uuid),
    UNIQUE KEY billing_tax_rules_code_unique (code),
    KEY billing_tax_rules_status_window_index (status, starts_at, ends_at),
    KEY billing_tax_rules_jurisdiction_index (country_code, region_code),
    CONSTRAINT billing_tax_rules_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT billing_tax_rules_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
ALTER TABLE billing_invoices
    ADD COLUMN tax_rule_id BIGINT UNSIGNED NULL AFTER coupon_id,
    ADD COLUMN taxable_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER discount_total,
    ADD COLUMN tax_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000 AFTER taxable_total,
    ADD COLUMN tax_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER tax_rate,
    ADD KEY billing_invoices_tax_rule_index (tax_rule_id),
    ADD CONSTRAINT billing_invoices_tax_rule_fk
        FOREIGN KEY (tax_rule_id) REFERENCES billing_tax_rules(id) ON DELETE SET NULL
SQL);

    $pdo->exec(
        'UPDATE billing_invoices
         SET taxable_total = GREATEST(subtotal - discount_total, 0),
             tax_rate = 0.0000,
             tax_total = 0.00'
    );
};
