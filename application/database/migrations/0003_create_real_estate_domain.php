<?php

declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    type VARCHAR(32) NOT NULL,
    country_code CHAR(2) NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY locations_uuid_unique (uuid),
    UNIQUE KEY locations_slug_unique (slug),
    KEY locations_parent_index (parent_id),
    KEY locations_type_status_index (type, status),
    CONSTRAINT locations_parent_fk
        FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS amenities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'general',
    icon_key VARCHAR(100) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY amenities_uuid_unique (uuid),
    UNIQUE KEY amenities_slug_unique (slug),
    KEY amenities_category_status_index (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    legal_name VARCHAR(191) NULL,
    company_type VARCHAR(40) NOT NULL DEFAULT 'developer',
    registration_number VARCHAR(120) NULL,
    tax_number VARCHAR(120) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(60) NULL,
    website VARCHAR(255) NULL,
    address_line VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    verified_at DATETIME NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY companies_uuid_unique (uuid),
    UNIQUE KEY companies_slug_unique (slug),
    KEY companies_location_index (location_id),
    KEY companies_type_status_index (company_type, status),
    KEY companies_published_index (published_at),
    CONSTRAINT companies_location_fk
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS company_members (
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    member_role VARCHAR(40) NOT NULL DEFAULT 'member',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    joined_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id, user_id),
    KEY company_members_user_index (user_id),
    KEY company_members_status_index (status),
    CONSTRAINT company_members_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT company_members_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    project_type VARCHAR(50) NOT NULL DEFAULT 'residential',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    completion_status VARCHAR(40) NOT NULL DEFAULT 'planned',
    address_line VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    description TEXT NULL,
    launch_date DATE NULL,
    expected_completion_date DATE NULL,
    completed_at DATE NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY projects_uuid_unique (uuid),
    UNIQUE KEY projects_slug_unique (slug),
    KEY projects_company_status_index (company_id, status),
    KEY projects_location_index (location_id),
    KEY projects_completion_index (completion_status),
    CONSTRAINT projects_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT projects_location_fk
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS project_amenities (
    project_id BIGINT UNSIGNED NOT NULL,
    amenity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, amenity_id),
    KEY project_amenities_amenity_index (amenity_id),
    CONSTRAINT project_amenities_project_fk
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT project_amenities_amenity_fk
        FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS properties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    location_id BIGINT UNSIGNED NULL,
    reference_code VARCHAR(80) NOT NULL,
    title VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    property_type VARCHAR(50) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    bedrooms DECIMAL(5,1) NULL,
    bathrooms DECIMAL(5,1) NULL,
    parking_spaces SMALLINT UNSIGNED NULL,
    floor_number SMALLINT NULL,
    total_floors SMALLINT UNSIGNED NULL,
    area_value DECIMAL(14,2) NULL,
    area_unit VARCHAR(20) NULL,
    land_area_value DECIMAL(14,2) NULL,
    land_area_unit VARCHAR(20) NULL,
    furnishing_status VARCHAR(32) NULL,
    year_built SMALLINT UNSIGNED NULL,
    address_line VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    description TEXT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY properties_uuid_unique (uuid),
    UNIQUE KEY properties_reference_unique (reference_code),
    UNIQUE KEY properties_slug_unique (slug),
    KEY properties_company_status_index (company_id, status),
    KEY properties_project_index (project_id),
    KEY properties_location_index (location_id),
    KEY properties_type_status_index (property_type, status),
    CONSTRAINT properties_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT properties_owner_user_fk
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT properties_project_fk
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT properties_location_fk
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS property_amenities (
    property_id BIGINT UNSIGNED NOT NULL,
    amenity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (property_id, amenity_id),
    KEY property_amenities_amenity_index (amenity_id),
    CONSTRAINT property_amenities_property_fk
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    CONSTRAINT property_amenities_amenity_fk
        FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS listings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    property_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    slug VARCHAR(191) NOT NULL,
    transaction_type VARCHAR(24) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    price DECIMAL(18,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    pricing_period VARCHAR(24) NULL,
    deposit_amount DECIMAL(18,2) NULL,
    negotiable TINYINT(1) NOT NULL DEFAULT 0,
    available_from DATE NULL,
    expires_at DATETIME NULL,
    featured_until DATETIME NULL,
    published_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY listings_uuid_unique (uuid),
    UNIQUE KEY listings_slug_unique (slug),
    KEY listings_property_status_index (property_id, status),
    KEY listings_transaction_status_index (transaction_type, status),
    KEY listings_price_index (currency, price),
    KEY listings_published_index (published_at),
    CONSTRAINT listings_property_fk
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
    CONSTRAINT listings_created_by_user_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    disk VARCHAR(40) NOT NULL DEFAULT 'public',
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    extension VARCHAR(24) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    alt_text VARCHAR(255) NULL,
    checksum_sha256 CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY media_assets_uuid_unique (uuid),
    UNIQUE KEY media_assets_path_unique (path),
    KEY media_assets_creator_index (created_by_user_id),
    KEY media_assets_mime_index (mime_type),
    CONSTRAINT media_assets_creator_fk
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_asset_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    collection VARCHAR(40) NOT NULL DEFAULT 'gallery',
    position INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY media_attachment_unique
        (media_asset_id, entity_type, entity_id, collection),
    KEY media_attachment_entity_index
        (entity_type, entity_id, collection, position),
    CONSTRAINT media_attachments_asset_fk
        FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS inquiries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    property_id BIGINT UNSIGNED NULL,
    listing_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(160) NOT NULL,
    contact_email VARCHAR(191) NULL,
    contact_phone VARCHAR(60) NULL,
    preferred_contact VARCHAR(24) NULL,
    subject VARCHAR(191) NULL,
    message TEXT NOT NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'website',
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    responded_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY inquiries_uuid_unique (uuid),
    KEY inquiries_company_status_index (company_id, status),
    KEY inquiries_listing_index (listing_id),
    KEY inquiries_user_index (user_id),
    KEY inquiries_assignee_status_index (assigned_user_id, status),
    KEY inquiries_created_index (created_at),
    CONSTRAINT inquiries_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT inquiries_project_fk
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT inquiries_property_fk
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    CONSTRAINT inquiries_listing_fk
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
    CONSTRAINT inquiries_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inquiries_assigned_user_fk
        FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    inquiry_id BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    contact_name VARCHAR(160) NOT NULL,
    contact_email VARCHAR(191) NULL,
    contact_phone VARCHAR(60) NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'inquiry',
    stage VARCHAR(32) NOT NULL DEFAULT 'new',
    transaction_type VARCHAR(24) NULL,
    property_type VARCHAR(50) NULL,
    preferred_location_id BIGINT UNSIGNED NULL,
    budget_min DECIMAL(18,2) NULL,
    budget_max DECIMAL(18,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    notes TEXT NULL,
    last_contact_at DATETIME NULL,
    next_follow_up_at DATETIME NULL,
    won_at DATETIME NULL,
    lost_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY leads_uuid_unique (uuid),
    UNIQUE KEY leads_inquiry_unique (inquiry_id),
    KEY leads_company_stage_index (company_id, stage),
    KEY leads_assignee_stage_index (assigned_user_id, stage),
    KEY leads_follow_up_index (next_follow_up_at),
    KEY leads_preferred_location_index (preferred_location_id),
    CONSTRAINT leads_company_fk
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT leads_inquiry_fk
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE SET NULL,
    CONSTRAINT leads_assigned_user_fk
        FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT leads_preferred_location_fk
        FOREIGN KEY (preferred_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
