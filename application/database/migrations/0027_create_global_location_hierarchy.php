<?php

declare(strict_types=1);

/* BARAMA_GLOBAL_LOCATION_HIERARCHY_V110 */
return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS location_data_packs (
    country_code CHAR(2) NOT NULL,
    source VARCHAR(80) NOT NULL,
    source_version VARCHAR(40) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'installed',
    record_count INT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64) NOT NULL,
    installed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (country_code),
    KEY location_data_packs_status_index (status, record_count),
    CONSTRAINT location_data_packs_country_fk
      FOREIGN KEY (country_code) REFERENCES location_countries(country_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS location_hierarchy_registry (
    external_code VARCHAR(40) NOT NULL,
    country_code CHAR(2) NOT NULL,
    parent_external_code VARCHAR(40) NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(80) NOT NULL,
    source_type VARCHAR(80) NULL,
    created_location TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (external_code),
    KEY location_hierarchy_registry_source_location_index (source, location_id),
    KEY location_hierarchy_registry_country_parent_index (country_code, parent_external_code),
    KEY location_hierarchy_registry_location_index (location_id),
    CONSTRAINT location_hierarchy_registry_country_fk
      FOREIGN KEY (country_code) REFERENCES location_countries(country_code) ON DELETE CASCADE,
    CONSTRAINT location_hierarchy_registry_location_fk
      FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
