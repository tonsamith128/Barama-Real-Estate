<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_payment_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    method_type VARCHAR(24) NOT NULL,
    handler_key VARCHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'inactive',
    is_configured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    public_config_json LONGTEXT NOT NULL,
    secret_config_ciphertext LONGTEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_payment_methods_uuid_unique (uuid),
    UNIQUE KEY billing_payment_methods_code_unique (code),
    KEY billing_payment_methods_status_index (status, method_type, sort_order),
    CONSTRAINT billing_payment_methods_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT billing_payment_methods_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
INSERT INTO billing_payment_methods
    (uuid, code, name, method_type, handler_key, status, is_configured, sort_order, public_config_json)
VALUES
    (UUID(), 'paypal', 'PayPal', 'automatic', 'paypal', 'inactive', 0, 10,
     '{"environment":"sandbox","currency":"USD"}'),
    (UUID(), 'stripe', 'Stripe', 'automatic', 'stripe', 'inactive', 0, 20,
     '{"environment":"test","currency":"USD","publishable_key":""}'),
    (UUID(), 'offline', 'Offline Payment', 'manual', 'offline', 'inactive', 0, 30,
     '{"display_title":"Offline Payment","instructions":"","proof_required":true,"processing_days":1}'),
    (UUID(), 'bank_transfer', 'Bank Transfer', 'manual', 'bank_transfer', 'inactive', 0, 40,
     '{"bank_name":"","account_name":"","account_number":"","swift_code":"","branch":"","currency":"USD","instructions":""}'),
    (UUID(), 'manual', 'Manual Payment', 'manual', 'manual', 'inactive', 0, 50,
     '{"display_title":"Manual Payment","instructions":"","processing_days":1,"approval_required":true}')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    method_type = VALUES(method_type),
    handler_key = VALUES(handler_key),
    sort_order = VALUES(sort_order)
SQL);
};
