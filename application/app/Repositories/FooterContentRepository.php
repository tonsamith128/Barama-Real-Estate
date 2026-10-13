<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class FooterContentRepository
{
    private const KEY = 'cms.footer_content';

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'brand_heading' => 'Barama Real Estate',
            'brand_description' => 'Property marketplace, developer discovery, and secure real estate management in one focused platform.',
            'marketplace_heading' => 'Marketplace',
            'compliance_heading' => 'Compliance',
            'help_heading' => 'Help',
            'policy_label' => 'Policy Center',
            'privacy_label' => 'Privacy',
            'terms_label' => 'Terms',
            'copyright' => 'Copyright {year} | Barama Real Estate',
            'social_heading' => 'Follow us',
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'youtube' => '',
                'tiktok' => '',
                'x' => '',
                'linkedin' => '',
            ],
        ];
    }

    /** @return array<string,array<int,array{label:string,url:string}>> */
    public static function defaultLinks(array $settings, ?array $navigation = null): array
    {
        $marketplace = [];
        if ($navigation !== null) {
            foreach ($navigation as $item) {
                if (!is_array($item) || (string) ($item['path'] ?? '/') === '/') {
                    continue;
                }
                $marketplace[] = [
                    'label' => (string) ($item['label'] ?? 'Link'),
                    'url' => (string) ($item['path'] ?? '/'),
                ];
            }
        } else {
            $marketplace = [
                ['label' => 'Properties', 'url' => '/properties'],
                ['label' => 'Projects', 'url' => '/projects'],
                ['label' => 'Companies', 'url' => '/companies'],
            ];
        }

        return [
            'brand' => [],
            'marketplace' => $marketplace,
            'compliance' => [
                ['label' => (string) ($settings['policy_label'] ?? 'Policy Center'), 'url' => '/policies'],
                ['label' => (string) ($settings['privacy_label'] ?? 'Privacy'), 'url' => '/policies?=privacy-policy'],
                ['label' => (string) ($settings['terms_label'] ?? 'Terms'), 'url' => '/policies?=terms-of-service'],
            ],
            'help' => [
                ['label' => 'Documentation', 'url' => (string) config('app.documentation_url')],
                ['label' => 'Contact Support', 'url' => (string) config('app.support_url')],
                ['label' => 'System Health', 'url' => '/health'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $statement = Database::connection()->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :key LIMIT 1'
        );
        $statement->execute(['key' => self::KEY]);
        $json = $statement->fetchColumn();
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        $defaults = self::defaults();
        // Do not allow persisted data to introduce new template fields.
        $safe = [];
        foreach ($defaults as $key => $default) {
            if ($key === 'social') {
                $savedSocial = is_array($decoded['social'] ?? null) ? $decoded['social'] : [];
                $social = [];
                foreach ($default as $network => $empty) {
                    $social[$network] = is_string($savedSocial[$network] ?? null)
                        ? $savedSocial[$network] : $empty;
                }
                $safe[$key] = $social;
                continue;
            }
            $safe[$key] = is_string($decoded[$key] ?? null) ? $decoded[$key] : $default;
        }
        // Compatibility: previous CI485 installations stored only four
        // headings and policy labels. On first use, preserve existing public
        // Marketplace navigation, legacy policy labels and Help destinations.
        // Do not write this fallback into the database until Save Footer.
        $navigation = null;
        if (!array_key_exists('links', $decoded)) {
            $navigation = (new SiteContentRepository())->navigation();
        }
        $fallback = self::defaultLinks($safe, $navigation);
        $saved = $decoded['links'] ?? null;
        $links = [];
        foreach ($fallback as $column => $initial) {
            if (!is_array($saved) || !array_key_exists($column, $saved)) {
                $links[$column] = $initial;
                continue;
            }
            $items = [];
            if (is_array($saved[$column])) {
                foreach (array_slice($saved[$column], 0, 15) as $item) {
                    if (is_array($item)
                        && is_string($item['label'] ?? null)
                        && is_string($item['url'] ?? null)) {
                        $items[] = ['label' => $item['label'], 'url' => $item['url']];
                    }
                }
            }
            $links[$column] = $items;
        }
        $safe['links'] = $links;
        return self::$cache = $safe;
    }

    /** @param array<string,mixed> $settings */
    public function save(array $settings): void
    {
        $json = json_encode(
            $settings,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $stmt = Database::connection()->prepare(
            'INSERT INTO application_settings (setting_key, setting_value, value_type)
             VALUES (:key, :value, "json")
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                value_type = "json", updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['key' => self::KEY, 'value' => $json]);
        self::$cache = $settings;
    }
}
