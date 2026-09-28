<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(160) NOT NULL,
    contact_email VARCHAR(191) NOT NULL,
    contact_phone VARCHAR(60) NULL,
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    source VARCHAR(40) NOT NULL DEFAULT "contact_page",
    status VARCHAR(24) NOT NULL DEFAULT "new",
    ip_hash CHAR(64) NOT NULL,
    user_agent_hash CHAR(64) NOT NULL,
    handled_by_user_id BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY contact_messages_uuid_unique (uuid),
    KEY contact_messages_status_created_index (status, created_at),
    KEY contact_messages_user_index (user_id, created_at),
    KEY contact_messages_handler_index (handled_by_user_id, updated_at),
    CONSTRAINT contact_messages_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT contact_messages_handler_fk
        FOREIGN KEY (handled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
