<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class SiteContentRepository
{
    private const SETTING_KEY = 'cms.site_content';

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

        $homepage = is_array($decoded['homepage'] ?? null)
            ? array_replace_recursive($defaults['homepage'], $decoded['homepage'])
            : $defaults['homepage'];

        if (!is_array($homepage['section_order'] ?? null)) {
            $homepage['section_order'] = $defaults['homepage']['section_order'];
        }

        $navigation = is_array($decoded['navigation'] ?? null)
            ? array_values($decoded['navigation'])
            : $defaults['navigation'];

        self::$cache = [
            'homepage' => $homepage,
            'navigation' => $navigation,
        ];

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

    /** @return array<int,array{label:string,path:string,active_area:string,enabled:bool}> */
    public function navigation(): array
    {
        $navigation = $this->get()['navigation'] ?? [];

        if (!is_array($navigation)) {
            return [];
        }

        return array_values(array_filter(
            $navigation,
            static fn (mixed $item): bool =>
                is_array($item)
                && !empty($item['enabled'])
                && trim((string) ($item['label'] ?? '')) !== ''
                && trim((string) ($item['path'] ?? '')) !== ''
        ));
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'homepage' => [
                'hero_eyebrow' => 'Barama Real Estate',
                'hero_title' => 'Property Discovery Simplified',
                'hero_description' => 'Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.',
                'section_order' => ['listings', 'projects', 'companies'],
                'sections' => [
                    'listings' => [
                        'enabled' => true,
                        'eyebrow' => 'Latest inventory',
                        'title' => 'Fresh on the marketplace',
                        'link_label' => 'View all properties',
                    ],
                    'projects' => [
                        'enabled' => true,
                        'eyebrow' => 'Developments',
                        'title' => 'Published projects',
                        'link_label' => 'View projects',
                    ],
                    'companies' => [
                        'enabled' => true,
                        'eyebrow' => 'Companies',
                        'title' => 'Approved professionals',
                        'link_label' => 'View directory',
                    ],
                ],
            ],
            'navigation' => [
                ['label' => 'Home', 'path' => '/', 'active_area' => 'home', 'enabled' => true],
                ['label' => 'Properties', 'path' => '/properties', 'active_area' => 'properties', 'enabled' => true],
                ['label' => 'Projects', 'path' => '/projects', 'active_area' => 'projects', 'enabled' => true],
                ['label' => 'Companies', 'path' => '/company', 'active_area' => 'company', 'enabled' => true],
            ],
        ];
    }
}
