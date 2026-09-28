<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

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

        $rawHomepage = is_array($decoded['homepage'] ?? null)
            ? $decoded['homepage']
            : [];
        $homepage = array_replace_recursive($defaults['homepage'], $rawHomepage);

        /* Backward compatibility with the original Homepage & Navigation model. */
        if (!is_array($rawHomepage['hero'] ?? null)) {
            $homepage['hero']['eyebrow'] = (string) (
                $rawHomepage['hero_eyebrow']
                ?? $defaults['homepage']['hero']['eyebrow']
            );
            $homepage['hero']['title'] = (string) (
                $rawHomepage['hero_title']
                ?? $defaults['homepage']['hero']['title']
            );
            $homepage['hero']['description'] = (string) (
                $rawHomepage['hero_description']
                ?? $defaults['homepage']['hero']['description']
            );
        }

        if (!is_array($homepage['section_order'] ?? null)) {
            $homepage['section_order'] = $defaults['homepage']['section_order'];
        }

        $navigationInput = is_array($decoded['navigation'] ?? null)
            ? array_values($decoded['navigation'])
            : $defaults['navigation'];
        $navigation = [];

        foreach ($navigationInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $path = trim((string) ($item['path'] ?? ''));
            $id = trim((string) ($item['id'] ?? ''));

            if ($id === '') {
                $id = substr(
                    hash('sha256', $index . '|' . $label . '|' . $path),
                    0,
                    16
                );
            }

            $navigation[] = [
                'id' => $id,
                'label' => $label,
                'path' => $path,
                'active_area' => trim((string) ($item['active_area'] ?? '')),
                'enabled' => !empty($item['enabled']),
            ];
        }

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

    /** @return array<int,array{id:string,label:string,path:string,active_area:string,enabled:bool}> */
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
                /* Legacy keys remain readable by older packages during upgrade. */
                'hero_eyebrow' => 'Barama Real Estate',
                'hero_title' => 'Property Discovery Simplified',
                'hero_description' => 'Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.',
                'hero' => [
                    'enabled' => true,
                    'eyebrow' => 'Barama Real Estate',
                    'title' => 'Property Discovery Simplified',
                    'description' => 'Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.',
                    'background' => [
                        'type' => 'plain',
                        'color_preset' => 'ocean',
                        'overlay_strength' => 44,
                        'image_source' => 'url',
                        'image_url' => '',
                        'image_media_uuid' => '',
                        'slider_images' => [],
                        'slider_style' => 'fade',
                        'slider_interval' => 6000,
                        'slider_autoplay' => true,
                        'video_source' => 'url',
                        'video_url' => '',
                        'video_media_uuid' => '',
                        'youtube_url' => '',
                    ],
                ],
                'features' => [
                    'search' => [
                        'enabled' => true,
                        'placeholder' => 'Search property, company, or location',
                        'button_label' => 'Search',
                    ],
                    'market_pulse' => [
                        'enabled' => true,
                        'title' => 'Marketplace',
                        'subtitle' => 'Approved public inventory',
                        'note' => 'Public records appear only after marketplace approval.',
                    ],
                    'quick_actions' => [
                        'enabled' => true,
                        'items' => [
                            'sale' => [
                                'title' => 'Buy property',
                                'description' => 'Browse published homes and investment inventory.',
                            ],
                            'rent' => [
                                'title' => 'Rent property',
                                'description' => 'Find rental listings by location and budget.',
                            ],
                            'project' => [
                                'title' => 'New developments',
                                'description' => 'Explore published projects and available units.',
                            ],
                            'company' => [
                                'title' => 'Companies',
                                'description' => 'Discover approved developers and agencies.',
                            ],
                        ],
                    ],
                ],
                'section_order' => [
                    'listings',
                    'projects',
                    'companies',
                    'featured_locations',
                    'how_it_works',
                    'trust_stats',
                    'testimonials',
                    'blog',
                    'subscribe',
                ],
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
                    'featured_locations' => [
                        'enabled' => false,
                        'eyebrow' => 'Explore locations',
                        'title' => 'Popular places to discover',
                        'link_label' => 'Browse all properties',
                        'limit' => 6,
                    ],
                    'how_it_works' => [
                        'enabled' => false,
                        'eyebrow' => 'How it works',
                        'title' => 'A simpler way to discover real estate',
                        'link_label' => '',
                        'items' => [
                            [
                                'title' => 'Search approved inventory',
                                'description' => 'Browse published properties, projects and companies from one marketplace.',
                            ],
                            [
                                'title' => 'Review the details',
                                'description' => 'Compare locations, pricing, project information and approved company profiles.',
                            ],
                            [
                                'title' => 'Connect directly',
                                'description' => 'Send an inquiry to the responsible company when you are ready to learn more.',
                            ],
                        ],
                    ],
                    'trust_stats' => [
                        'enabled' => false,
                        'eyebrow' => 'Marketplace confidence',
                        'title' => 'Built around approved public inventory',
                        'link_label' => '',
                        'items' => [
                            ['value' => '100%', 'label' => 'Approval-first publishing'],
                            ['value' => '1', 'label' => 'Unified marketplace'],
                            ['value' => '24/7', 'label' => 'Self-service discovery'],
                            ['value' => 'Direct', 'label' => 'Company inquiries'],
                        ],
                    ],
                    'testimonials' => [
                        'enabled' => false,
                        'eyebrow' => 'Testimonials',
                        'title' => 'What marketplace users say',
                        'link_label' => '',
                        'items' => [
                            [
                                'quote' => 'Barama makes property discovery feel structured and easy to follow.',
                                'name' => 'Marketplace customer',
                                'role' => 'Buyer',
                            ],
                            [
                                'quote' => 'The approved-company workflow gives our listings a professional public presentation.',
                                'name' => 'Property company',
                                'role' => 'Developer',
                            ],
                            [
                                'quote' => 'Projects, companies and available properties are much easier to explore in one place.',
                                'name' => 'Marketplace visitor',
                                'role' => 'Researching property',
                            ],
                        ],
                    ],
                    'blog' => [
                        'enabled' => false,
                        'eyebrow' => 'Insights',
                        'title' => 'Latest from Barama',
                        'link_label' => 'View all insights',
                        'items' => [
                            [
                                'title' => 'How to compare property opportunities',
                                'excerpt' => 'A practical checklist for reviewing location, price, company information and project context.',
                                'url' => '',
                                'image_url' => '',
                            ],
                            [
                                'title' => 'Understanding new developments',
                                'excerpt' => 'What to review when exploring a published project and its available property inventory.',
                                'url' => '',
                                'image_url' => '',
                            ],
                            [
                                'title' => 'Working with approved companies',
                                'excerpt' => 'Use company profiles and direct inquiries to continue your property research with confidence.',
                                'url' => '',
                                'image_url' => '',
                            ],
                        ],
                    ],
                    'subscribe' => [
                        'enabled' => false,
                        'eyebrow' => 'Stay informed',
                        'title' => 'Get marketplace updates',
                        'description' => 'Receive occasional updates about newly published property opportunities and marketplace improvements.',
                        'placeholder' => 'Enter your email address',
                        'button_label' => 'Subscribe',
                        'success_message' => 'Thank you. You are subscribed to Barama updates.',
                        'consent_text' => 'You can unsubscribe from future updates at any time.',
                    ],
                ],
            ],
            'navigation' => [
                ['id' => 'home', 'label' => 'Home', 'path' => '/', 'active_area' => 'home', 'enabled' => true],
                ['id' => 'properties', 'label' => 'Properties', 'path' => '/properties', 'active_area' => 'properties', 'enabled' => true],
                ['id' => 'projects', 'label' => 'Projects', 'path' => '/projects', 'active_area' => 'projects', 'enabled' => true],
                ['id' => 'companies', 'label' => 'Companies', 'path' => '/company', 'active_area' => 'company', 'enabled' => true],
            ],
        ];
    }
}
