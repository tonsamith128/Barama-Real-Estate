<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS communication_announcements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL,
            title VARCHAR(191) NOT NULL,
            message TEXT NOT NULL,
            cta_label VARCHAR(80) NULL,
            cta_url VARCHAR(500) NULL,
            audience VARCHAR(32) NOT NULL DEFAULT "all",
            priority VARCHAR(16) NOT NULL DEFAULT "normal",
            send_in_app TINYINT(1) NOT NULL DEFAULT 1,
            send_email TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(24) NOT NULL DEFAULT "draft",
            scheduled_at DATETIME NULL,
            published_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            archived_at DATETIME NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            published_by_user_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY communication_announcements_uuid_unique (uuid),
            KEY communication_announcements_status_schedule_index (status, scheduled_at),
            KEY communication_announcements_audience_status_index (audience, status),
            CONSTRAINT communication_announcements_created_by_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT communication_announcements_updated_by_fk FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT communication_announcements_published_by_fk FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS communication_announcement_deliveries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            announcement_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            notification_id BIGINT UNSIGNED NULL,
            email_outbox_id BIGINT UNSIGNED NULL,
            delivery_state VARCHAR(24) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY communication_announcement_delivery_recipient_unique (announcement_id, user_id),
            KEY communication_announcement_delivery_user_index (user_id, created_at),
            CONSTRAINT communication_announcement_deliveries_announcement_fk FOREIGN KEY (announcement_id) REFERENCES communication_announcements(id) ON DELETE CASCADE,
            CONSTRAINT communication_announcement_deliveries_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT communication_announcement_deliveries_notification_fk FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
            CONSTRAINT communication_announcement_deliveries_outbox_fk FOREIGN KEY (email_outbox_id) REFERENCES email_outbox(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
