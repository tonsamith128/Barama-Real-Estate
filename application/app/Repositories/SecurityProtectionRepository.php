<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class SecurityProtectionRepository
{
    private const SETTING_KEY = 'security.protection';

    /** @return array<string,mixed> */
    public function get(): array
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :key LIMIT 1'
        );
        $statement->execute(['key' => self::SETTING_KEY]);
        $raw = $statement->fetchColumn();
        $config = self::defaults();

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = array_replace_recursive($config, $decoded);
            }
        }

        return $config;
    }

    /** @param array<string,mixed> $config */
    public function save(array $config): void
    {
        $stored = array_replace_recursive(self::defaults(), $config);
        $json = json_encode(
            $stored,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings (setting_key, setting_value, value_type)
             VALUES (:key, :value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute(['key' => self::SETTING_KEY, 'value' => $json]);
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'spam' => [
                'enabled' => true,
                'window_seconds' => 600,
                'max_requests' => 8,
                'duplicate_max' => 3,
                'block_seconds' => 900,
            ],
            'hack' => [
                'enabled' => true,
                'detect_path_probes' => true,
                'detect_traversal' => true,
                'detect_injection' => true,
                'alerts_enabled' => false,
                'alert_email' => '',
                'alert_cooldown_seconds' => 1800,
            ],
            'right_click' => [
                'enabled' => false,
                'block_context_menu' => true,
                'block_image_drag' => true,
                'block_text_selection' => false,
            ],
        ];
    }
}
