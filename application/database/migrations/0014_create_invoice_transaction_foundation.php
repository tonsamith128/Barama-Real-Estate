<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    invoice_number VARCHAR(64) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    issued_at DATETIME NULL,
    due_at DATETIME NULL,
    paid_at DATETIME NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_invoices_uuid_unique (uuid),
    UNIQUE KEY billing_invoices_number_unique (invoice_number),
    KEY billing_invoices_company_status_index (company_id, status),
    KEY billing_invoices_subscription_index (subscription_id),
    KEY billing_invoices_due_index (due_at),
    CONSTRAINT billing_invoices_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT billing_invoices_subscription_fk
        FOREIGN KEY (subscription_id) REFERENCES company_subscriptions(id) ON DELETE SET NULL,
    CONSTRAINT billing_invoices_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT billing_invoices_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_invoice_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY billing_invoice_items_invoice_index (invoice_id),
    CONSTRAINT billing_invoice_items_invoice_fk
        FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    transaction_type VARCHAR(24) NOT NULL,
    status VARCHAR(24) NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'admin',
    reference VARCHAR(191) NULL,
    amount DECIMAL(18,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    processed_at DATETIME NOT NULL,
    notes TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_transactions_uuid_unique (uuid),
    KEY billing_transactions_company_status_index (company_id, status),
    KEY billing_transactions_subscription_index (subscription_id),
    KEY billing_transactions_invoice_index (invoice_id),
    KEY billing_transactions_reference_index (reference),
    KEY billing_transactions_processed_index (processed_at),
    CONSTRAINT billing_transactions_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT billing_transactions_subscription_fk
        FOREIGN KEY (subscription_id) REFERENCES company_subscriptions(id) ON DELETE SET NULL,
    CONSTRAINT billing_transactions_invoice_fk
        FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE SET NULL,
    CONSTRAINT billing_transactions_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
