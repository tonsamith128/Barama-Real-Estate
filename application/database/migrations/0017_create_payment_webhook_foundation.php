<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_payment_providers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(24) NOT NULL,
    name VARCHAR(160) NOT NULL,
    adapter_key VARCHAR(64) NOT NULL DEFAULT 'generic_token',
    environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
    webhook_token_hash VARCHAR(255) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'inactive',
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_payment_providers_uuid_unique (uuid),
    UNIQUE KEY billing_payment_providers_code_unique (code),
    KEY billing_payment_providers_status_index (status, environment),
    CONSTRAINT billing_payment_providers_created_by_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT billing_payment_providers_updated_by_fk
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS billing_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(96) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'received',
    payload_json LONGTEXT NOT NULL,
    payload_sha256 CHAR(64) NOT NULL,
    transaction_id BIGINT UNSIGNED NULL,
    error_message VARCHAR(500) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY billing_webhook_events_uuid_unique (uuid),
    UNIQUE KEY billing_webhook_events_provider_event_unique (provider_id, event_id),
    KEY billing_webhook_events_status_index (status, received_at),
    KEY billing_webhook_events_transaction_index (transaction_id),
    CONSTRAINT billing_webhook_events_provider_fk
        FOREIGN KEY (provider_id) REFERENCES billing_payment_providers(id) ON DELETE RESTRICT,
    CONSTRAINT billing_webhook_events_transaction_fk
        FOREIGN KEY (transaction_id) REFERENCES billing_transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
