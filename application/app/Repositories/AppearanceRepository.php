<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class AppearanceRepository
{
    private const SETTING_KEY = 'appearance.frontend';

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $statement = Database::connection()->prepare(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $statement->execute(['setting_key' => self::SETTING_KEY]);
        $json = $statement->fetchColumn();
        $defaults = self::defaults();

        if (!is_string($json) || trim($json) === '') {
            self::$cache = $defaults;
            return self::$cache;
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            self::$cache = $defaults;
            return self::$cache;
        }

        self::$cache = array_replace_recursive($defaults, $decoded);

        return self::$cache;
    }

    /** @param array<string,mixed> $config */
    public function save(array $config): void
    {
        $json = json_encode(
            $config,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => self::SETTING_KEY,
            'setting_value' => $json,
        ]);

        self::$cache = $config;
    }

    /** @param array<string,mixed>|null $config */
    public static function version(?array $config = null): string
    {
        $config ??= (new self())->get();

        return substr(
            hash(
                'sha256',
                json_encode(
                    $config,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ) ?: '{}'
            ),
            0,
            16
        );
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'typography' => [
                'enabled' => true,
                'body_line_height' => 1.6,
                'heading_line_height' => 1.08,
                'body_letter_spacing' => 0.0,
                'heading_letter_spacing' => -0.035,
            ],
            'font_family' => [
                'enabled' => true,
                'body' => 'inter',
                'heading' => 'inter',
            ],
            'font_size' => [
                'enabled' => true,
                'base_px' => 16,
                'small_scale' => 0.875,
                'nav_scale' => 0.9,
            ],
            'color' => [
                'enabled' => true,
                'light' => [
                    'background' => '#f6f8fb',
                    'surface' => '#ffffff',
                    'surface_soft' => '#f0f4f8',
                    'text' => '#172033',
                    'muted' => '#657086',
                    'border' => '#dde3ea',
                    'primary' => '#165dff',
                    'primary_strong' => '#0f4bd4',
                    'success' => '#0c9b72',
                    'danger' => '#dc3545',
                    'link' => '#165dff',
                ],
                'dark' => [
                    'background' => '#0d1420',
                    'surface' => '#131d2b',
                    'surface_soft' => '#182537',
                    'text' => '#edf4ff',
                    'muted' => '#9eacc0',
                    'border' => '#26364b',
                    'primary' => '#6d98ff',
                    'primary_strong' => '#8aabff',
                    'success' => '#4ecba4',
                    'danger' => '#ff6b7a',
                    'link' => '#8aabff',
                ],
            ],
            'background' => [
                'enabled' => true,
                'style' => 'solid',
                'light_to' => '#eef4ff',
                'dark_to' => '#101d31',
                'pattern' => 'none',
                'fixed' => false,
            ],
            'font_style' => [
                'enabled' => true,
                'body_weight' => 400,
                'heading_weight' => 850,
                'nav_weight' => 700,
                'heading_transform' => 'none',
                'smoothing' => true,
            ],
            'border' => [
                'enabled' => true,
                'width' => 1,
                'style' => 'solid',
                'opacity' => 100,
            ],
            'shadow' => [
                'enabled' => true,
                'level' => 'soft',
                'hover' => true,
            ],
            'preset' => [
                'enabled' => false,
                'name' => 'barama',
            ],
            'skin' => [
                'enabled' => true,
                'name' => 'modern',
            ],
            'layout' => [
                'enabled' => true,
                'container_width' => 1220,
                'page_gutter' => 24,
                'section_spacing' => 1.0,
                'density' => 'comfortable',
            ],
            'radius_spacing' => [
                'enabled' => true,
                'radius_sm' => 12,
                'radius_md' => 18,
                'radius_lg' => 28,
                'control_radius' => 12,
                'card_radius' => 18,
                'gap_scale' => 1.0,
            ],
            'buttons' => [
                'enabled' => true,
                'style' => 'solid',
                'radius' => 12,
                'weight' => 800,
                'uppercase' => false,
            ],
            'cards' => [
                'enabled' => true,
                'style' => 'elevated',
                'radius' => 18,
                'border' => true,
                'hover_lift' => true,
            ],
            'forms' => [
                'enabled' => true,
                'style' => 'outlined',
                'height' => 48,
                'radius' => 12,
                'focus_ring' => true,
            ],
            'header' => [
                'enabled' => true,
                'sticky' => true,
                'blur' => 16,
                'height' => 76,
                'nav_style' => 'soft',
                'shadow' => false,
            ],
            'footer' => [
                'enabled' => true,
                'style' => 'standard',
                'border' => true,
                'spacing' => 48,
            ],
            'motion' => [
                'enabled' => true,
                'transitions' => true,
                'duration' => 160,
                'hover_lift' => true,
                'smooth_scroll' => false,
                'respect_reduced_motion' => true,
            ],
        ];
    }
}
