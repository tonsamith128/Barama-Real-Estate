<?php

declare(strict_types=1);

/**
 * CodeCanyon v1.0.0 fresh-install repair: invoice-template storage.
 * Additive only. Does not mutate existing billing invoices or template rows.
 */
return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS invoice_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key VARCHAR(64) NOT NULL,
    template_name VARCHAR(120) NOT NULL DEFAULT 'Default Invoice',
    invoice_title VARCHAR(80) NOT NULL DEFAULT 'Invoice',
    invoice_prefix VARCHAR(20) NOT NULL DEFAULT 'INV',
    number_pattern VARCHAR(120) NOT NULL DEFAULT '{{prefix}}-{{year}}-{{sequence4}}',
    default_due_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
    default_currency CHAR(3) NOT NULL DEFAULT 'USD',
    logo_url VARCHAR(500) NOT NULL DEFAULT '',
    accent_color CHAR(7) NOT NULL DEFAULT '#5F8CFF',
    seller_name VARCHAR(160) NOT NULL DEFAULT 'Barama',
    seller_email VARCHAR(190) NOT NULL DEFAULT '',
    seller_phone VARCHAR(80) NOT NULL DEFAULT '',
    seller_address TEXT NULL,
    seller_tax_id VARCHAR(120) NOT NULL DEFAULT '',
    customer_label VARCHAR(60) NOT NULL DEFAULT 'Bill To',
    subtotal_label VARCHAR(60) NOT NULL DEFAULT 'Subtotal',
    discount_label VARCHAR(60) NOT NULL DEFAULT 'Discount',
    tax_label VARCHAR(60) NOT NULL DEFAULT 'Tax',
    total_label VARCHAR(60) NOT NULL DEFAULT 'Total',
    payment_instructions TEXT NULL,
    default_notes TEXT NULL,
    footer_text TEXT NULL,
    show_logo TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    show_seller_tax_id TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    show_payment_instructions TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY invoice_templates_template_key_unique (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
