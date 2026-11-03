<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $old = [
        'eyebrow' => 'Barama Real Estate',
        'title' => 'Property Discovery Simplified',
        'description' => 'Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.',
    ];
    $new = [
        'eyebrow' => 'Approved Real Estate',
        'title' => 'Property Discovery',
        'description' => 'Find approved homes, rental opportunities, and new developments.',
    ];

    $statement = $pdo->prepare(
        'SELECT setting_value FROM application_settings WHERE setting_key = :setting_key LIMIT 1'
    );
    $statement->execute(['setting_key' => 'cms.site_content']);
    $raw = $statement->fetchColumn();

    if ($raw === false || !is_string($raw) || trim($raw) === '') {
        $config = [
            'homepage' => [
                'hero_eyebrow' => $new['eyebrow'],
                'hero_title' => $new['title'],
                'hero_description' => $new['description'],
                'hero' => $new,
            ],
        ];
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        $config = $decoded;
        if (!isset($config['homepage']) || !is_array($config['homepage'])) {
            $config['homepage'] = [];
        }

        $homepage =& $config['homepage'];
        $legacyMap = [
            'hero_eyebrow' => 'eyebrow',
            'hero_title' => 'title',
            'hero_description' => 'description',
        ];
        foreach ($legacyMap as $legacyKey => $field) {
            if (isset($homepage[$legacyKey]) && (string) $homepage[$legacyKey] === $old[$field]) {
                $homepage[$legacyKey] = $new[$field];
            }
        }

        if (isset($homepage['hero']) && is_array($homepage['hero'])) {
            foreach (['eyebrow', 'title', 'description'] as $field) {
                if (isset($homepage['hero'][$field]) && (string) $homepage['hero'][$field] === $old[$field]) {
                    $homepage['hero'][$field] = $new[$field];
                }
            }
        }
        unset($homepage);
    }

    $json = json_encode(
        $config,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $save = $pdo->prepare(
        'INSERT INTO application_settings (setting_key, setting_value, value_type)
         VALUES (:setting_key, :setting_value, "json")
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = "json",
            updated_at = CURRENT_TIMESTAMP'
    );
    $save->execute([
        'setting_key' => 'cms.site_content',
        'setting_value' => $json,
    ]);
};
