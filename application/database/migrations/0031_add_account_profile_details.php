<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM user_profiles')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string) $row['Field']] = true;
    }

    if (!isset($columns['date_of_birth'])) {
        $pdo->exec('ALTER TABLE user_profiles ADD COLUMN date_of_birth DATE NULL AFTER phone');
    }

    if (!isset($columns['address'])) {
        $pdo->exec('ALTER TABLE user_profiles ADD COLUMN address VARCHAR(500) NULL AFTER date_of_birth');
    }

    if (!isset($columns['avatar_media_id'])) {
        $pdo->exec('ALTER TABLE user_profiles ADD COLUMN avatar_media_id BIGINT UNSIGNED NULL AFTER address');
    }

    $index = $pdo->query(
        "SHOW INDEX FROM user_profiles WHERE Key_name = 'user_profiles_avatar_media_index'"
    )->fetch(PDO::FETCH_ASSOC);

    if (!is_array($index)) {
        $pdo->exec('ALTER TABLE user_profiles ADD KEY user_profiles_avatar_media_index (avatar_media_id)');
    }

    $constraint = $pdo->query(
        "SELECT CONSTRAINT_NAME
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'user_profiles'
           AND CONSTRAINT_NAME = 'user_profiles_avatar_media_fk'
         LIMIT 1"
    )->fetchColumn();

    if ($constraint === false) {
        $pdo->exec(
            'ALTER TABLE user_profiles
             ADD CONSTRAINT user_profiles_avatar_media_fk
             FOREIGN KEY (avatar_media_id) REFERENCES media_assets(id)
             ON DELETE SET NULL'
        );
    }
};
