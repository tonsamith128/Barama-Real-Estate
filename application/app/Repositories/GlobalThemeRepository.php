<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class GlobalThemeRepository
{
    private const SETTING_KEY = 'ui.default_theme';

    public function get(): string
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $statement->execute(['setting_key' => self::SETTING_KEY]);
        $value = strtolower(trim((string) ($statement->fetchColumn() ?: '')));

        if (in_array($value, ['light', 'dark'], true)) {
            return $value;
        }

        $statement = Database::connection()->prepare(
            'SELECT settings_json
             FROM workspace_preferences
             WHERE workspace = :workspace
             ORDER BY updated_at DESC
             LIMIT 20'
        );
        $statement->execute(['workspace' => 'dashboard']);

        foreach ($statement->fetchAll() as $row) {
            $json = is_array($row) ? (string) ($row['settings_json'] ?? '') : '';
            if ($json === '') {
                continue;
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                continue;
            }

            $candidate = strtolower(trim((string) ($decoded['default_theme'] ?? '')));
            if (in_array($candidate, ['light', 'dark'], true)) {
                return $candidate;
            }
        }

        return 'light';
    }

    public function save(string $theme): void
    {
        $theme = strtolower(trim($theme));

        if (!in_array($theme, ['light', 'dark'], true)) {
            throw new \InvalidArgumentException('Theme must be light or dark.');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "string")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "string",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => self::SETTING_KEY,
            'setting_value' => $theme,
        ]);
    }
}
