<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $column = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "users"
           AND COLUMN_NAME = "auth_version"'
    );
    $column->execute();

    if ((int) $column->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE users
             ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status'
        );
    }

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(191) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY email_verification_token_hash_unique (token_hash),
    KEY email_verification_user_index (user_id),
    KEY email_verification_expires_index (expires_at),
    CONSTRAINT email_verification_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS security_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    subject_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY security_audit_event_type_index (event_type),
    KEY security_audit_actor_index (actor_user_id),
    KEY security_audit_subject_index (subject_user_id),
    KEY security_audit_created_index (created_at),
    CONSTRAINT security_audit_actor_fk
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT security_audit_subject_fk
        FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
