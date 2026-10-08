<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS communication_email_template_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(8) NOT NULL,
    subject_template VARCHAR(255) NOT NULL,
    html_template MEDIUMTEXT NOT NULL,
    text_template MEDIUMTEXT NOT NULL,
    status ENUM('inactive','active','archived') NOT NULL DEFAULT 'inactive',
    created_by_user_id BIGINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY communication_email_template_variants_uuid_unique (uuid),
    UNIQUE KEY communication_email_template_variants_template_locale_unique (template_id, locale),
    KEY communication_email_template_variants_status_index (status),
    CONSTRAINT communication_email_template_variants_template_fk FOREIGN KEY (template_id) REFERENCES communication_email_templates(id) ON DELETE CASCADE,
    CONSTRAINT communication_email_template_variants_created_by_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT communication_email_template_variants_updated_by_fk FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS communication_email_template_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'en',
    revision_number INT UNSIGNED NOT NULL,
    revision_event VARCHAR(40) NOT NULL,
    name VARCHAR(160) NULL,
    description TEXT NULL,
    subject_template VARCHAR(255) NOT NULL,
    html_template MEDIUMTEXT NOT NULL,
    text_template MEDIUMTEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY communication_email_template_revisions_uuid_unique (uuid),
    UNIQUE KEY communication_email_template_revisions_number_unique (template_id, locale, revision_number),
    KEY communication_email_template_revisions_lookup_index (template_id, locale, created_at),
    CONSTRAINT communication_email_template_revisions_template_fk FOREIGN KEY (template_id) REFERENCES communication_email_templates(id) ON DELETE CASCADE,
    CONSTRAINT communication_email_template_revisions_created_by_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
