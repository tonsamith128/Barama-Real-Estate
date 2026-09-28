<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS communication_email_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL,
            template_key VARCHAR(100) NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            subject_template VARCHAR(255) NOT NULL,
            html_template MEDIUMTEXT NOT NULL,
            text_template MEDIUMTEXT NOT NULL,
            status ENUM("inactive", "active", "archived") NOT NULL DEFAULT "inactive",
            created_by_user_id BIGINT UNSIGNED NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY communication_email_templates_uuid_unique (uuid),
            UNIQUE KEY communication_email_templates_key_unique (template_key),
            KEY communication_email_templates_status_index (status),
            CONSTRAINT communication_email_templates_created_by_fk
                FOREIGN KEY (created_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL,
            CONSTRAINT communication_email_templates_updated_by_fk
                FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
