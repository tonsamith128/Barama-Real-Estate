<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_security_factors (
    user_id BIGINT UNSIGNED NOT NULL,
    change_passcode_hash VARCHAR(255) NULL,
    change_passcode_enabled TINYINT(1) NOT NULL DEFAULT 0,
    mfa_secret_enc TEXT NULL,
    mfa_pending_secret_enc TEXT NULL,
    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    mfa_recovery_hashes LONGTEXT NULL,
    mfa_confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY user_security_passcode_enabled_index (change_passcode_enabled),
    KEY user_security_mfa_enabled_index (mfa_enabled),
    CONSTRAINT user_security_factors_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
