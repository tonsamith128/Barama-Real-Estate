<?php

declare(strict_types=1);


return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS saved_listings (
    user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY saved_listings_listing_index (listing_id),
    CONSTRAINT saved_listings_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT saved_listings_listing_fk
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS comparison_items (
    user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, listing_id),
    KEY comparison_items_listing_index (listing_id),
    KEY comparison_items_user_position_index (user_id, position, created_at),
    CONSTRAINT comparison_items_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT comparison_items_listing_fk
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS recent_listing_views (
    user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    view_count INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, listing_id),
    KEY recent_listing_views_listing_index (listing_id),
    KEY recent_listing_views_user_recent_index (user_id, last_viewed_at),
    CONSTRAINT recent_listing_views_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT recent_listing_views_listing_fk
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS saved_searches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    query_text VARCHAR(100) NULL,
    transaction_type VARCHAR(24) NULL,
    property_type VARCHAR(50) NULL,
    location_id BIGINT UNSIGNED NULL,
    bedrooms_min DECIMAL(5,1) NULL,
    price_min DECIMAL(18,2) NULL,
    price_max DECIMAL(18,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    alert_frequency VARCHAR(24) NOT NULL DEFAULT 'daily',
    alerts_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_checked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY saved_searches_uuid_unique (uuid),
    KEY saved_searches_user_index (user_id, created_at),
    KEY saved_searches_active_alert_index (alerts_enabled, alert_frequency),
    KEY saved_searches_location_index (location_id),
    CONSTRAINT saved_searches_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT saved_searches_location_fk
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS viewing_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time_window VARCHAR(40) NOT NULL,
    alternate_date DATE NULL,
    alternate_time_window VARCHAR(40) NULL,
    message VARCHAR(2000) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    scheduled_for DATETIME NULL,
    company_note VARCHAR(1000) NULL,
    cancelled_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY viewing_requests_uuid_unique (uuid),
    KEY viewing_requests_user_status_index (user_id, status, created_at),
    KEY viewing_requests_company_status_index (company_id, status, created_at),
    KEY viewing_requests_listing_index (listing_id),
    CONSTRAINT viewing_requests_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT viewing_requests_listing_fk
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE RESTRICT,
    CONSTRAINT viewing_requests_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
