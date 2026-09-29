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

        $marketplacePages = [];
        foreach ($defaults['marketplace_pages'] as $pageKey => $pageDefaults) {
            $rawPage = is_array($decoded['marketplace_pages'][$pageKey] ?? null)
                ? $decoded['marketplace_pages'][$pageKey]
                : [];
            $marketplacePages[$pageKey] = array_replace_recursive($pageDefaults, $rawPage);
        }

        $rawAbout = is_array($decoded['about'] ?? null)
            ? $decoded['about']
            : [];
        $about = array_replace_recursive($defaults['about'], $rawAbout);

        if (!is_array($about['section_order'] ?? null)) {
            $about['section_order'] = $defaults['about']['section_order'];
        }

        $rawContact = is_array($decoded['contact'] ?? null)
            ? $decoded['contact']
            : [];
        $contact = array_replace_recursive($defaults['contact'], $rawContact);

        if (!is_array($contact['section_order'] ?? null)) {
            $contact['section_order'] = $defaults['contact']['section_order'];
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

            $activeArea = trim((string) ($item['active_area'] ?? ''));

            if ($activeArea === '') {
                $route = (string) parse_url($path, PHP_URL_PATH);
                $activeArea = match ($route) {
                    '/' => 'home',
                    '/about' => 'about',
                    '/contact' => 'contact',
                    default => '',
                };
            }

            $navigation[] = [
                'id' => $id,
                'label' => $label,
                'path' => $path,
                'active_area' => $activeArea,
                'enabled' => !empty($item['enabled']),
            ];
        }

        self::$cache = [
            'homepage' => $homepage,
            'marketplace_pages' => $marketplacePages,
            'about' => $about,
            'contact' => $contact,
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
                        'link_url' => '',
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
            'marketplace_pages' => [
                'properties' => [
                    'hero' => [
                        'enabled' => true,
                        'eyebrow' => 'Property Marketplace',
                        'title' => 'Find Your Property',
                        'description' => 'Search approved sale and rental listings by location, property type, bedrooms, and budget.',
                        'background' => [
                            'type' => 'color',
                            'color_preset' => 'ocean',
                            'overlay_strength' => 38,
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
                    'search' => [
                        'enabled' => true,
                        'placeholder' => 'Property, company, reference, location',
                        'button_label' => 'Search',
                    ],
                    'sale_rent' => [
                        'enabled' => true,
                        'all_label' => 'Sale & rent',
                        'sale_label' => 'For sale',
                        'rent_label' => 'For rent',
                    ],
                    'locations' => [
                        'enabled' => true,
                        'all_label' => 'All locations',
                    ],
                    'grid' => [
                        'columns' => 3,
                        'page_size' => 18,
                        'show_result_count' => true,
                    ],
                ],
                'projects' => [
                    'hero' => [
                        'enabled' => true,
                        'eyebrow' => 'New Developments',
                        'title' => 'Explore Approved Projects',
                        'description' => 'Discover published developments by location, project type, and construction stage.',
                        'background' => [
                            'type' => 'color',
                            'color_preset' => 'violet',
                            'overlay_strength' => 38,
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
                    'search' => [
                        'enabled' => true,
                        'placeholder' => 'Project, company, location',
                        'button_label' => 'Search',
                    ],
                    'sale_rent' => [
                        'enabled' => true,
                        'all_label' => 'Sale & rent',
                        'sale_label' => 'For sale',
                        'rent_label' => 'For rent',
                    ],
                    'locations' => [
                        'enabled' => true,
                        'all_label' => 'All locations',
                    ],
                    'grid' => [
                        'columns' => 3,
                        'page_size' => 16,
                        'show_result_count' => true,
                    ],
                ],
                'companies' => [
                    'hero' => [
                        'enabled' => true,
                        'eyebrow' => 'Company Directory',
                        'title' => 'Discover Approved Companies',
                        'description' => 'Explore public profiles with published projects and active marketplace inventory.',
                        'background' => [
                            'type' => 'color',
                            'color_preset' => 'emerald',
                            'overlay_strength' => 38,
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
                    'search' => [
                        'enabled' => true,
                        'placeholder' => 'Company or location',
                        'button_label' => 'Search',
                    ],
                    'sale_rent' => [
                        'enabled' => true,
                        'all_label' => 'Sale & rent',
                        'sale_label' => 'For sale',
                        'rent_label' => 'For rent',
                    ],
                    'locations' => [
                        'enabled' => true,
                        'all_label' => 'All locations',
                    ],
                    'grid' => [
                        'columns' => 3,
                        'page_size' => 16,
                        'show_result_count' => true,
                    ],
                ],
            ],
            'about' => [
                'enabled' => true,
                'seo' => [
                    'title' => 'About Us · Barama Real Estate',
                    'description' => 'Learn how Barama Real Estate helps people discover approved property, projects, and real-estate companies in one structured marketplace.',
                    'robots' => 'index,follow',
                ],
                'hero' => [
                    'enabled' => true,
                    'eyebrow' => 'About Barama',
                    'title' => 'A clearer way to discover real estate',
                    'description' => 'Barama Real Estate brings approved marketplace inventory, development projects, and company profiles together so property discovery feels structured, transparent, and easier to navigate.',
                    'image_url' => '',
                ],
                'section_order' => [
                    'story',
                    'mission_vision',
                    'values',
                    'stats',
                    'team',
                    'cta',
                ],
                'sections' => [
                    'story' => [
                        'enabled' => true,
                        'eyebrow' => 'Our story',
                        'title' => 'Built around useful property information',
                        'body' => 'Barama Real Estate is designed as a focused marketplace where visitors can explore approved properties, projects, and real-estate companies without unnecessary complexity. The platform connects public discovery with structured company-managed inventory and direct inquiries.',
                        'image_url' => '',
                    ],
                    'mission_vision' => [
                        'enabled' => true,
                        'eyebrow' => 'Purpose',
                        'title' => 'What guides the marketplace',
                        'mission_title' => 'Our mission',
                        'mission_text' => 'Make real-estate discovery easier to understand by presenting approved inventory, clear context, and direct ways to connect with responsible companies.',
                        'vision_title' => 'Our vision',
                        'vision_text' => 'Create a dependable digital marketplace where property information is organized, accessible, and useful for customers and real-estate professionals.',
                    ],
                    'values' => [
                        'enabled' => true,
                        'eyebrow' => 'Our values',
                        'title' => 'Principles behind the platform',
                        'items' => [
                            ['title' => 'Clarity', 'description' => 'Present property and company information in a clean, understandable structure.'],
                            ['title' => 'Trust', 'description' => 'Keep publication tied to marketplace review and visible ownership context.'],
                            ['title' => 'Usefulness', 'description' => 'Prioritize information and tools that help people continue their property research.'],
                            ['title' => 'Connection', 'description' => 'Make it easy for visitors to contact the responsible company when they are ready.'],
                        ],
                    ],
                    'stats' => [
                        'enabled' => false,
                        'eyebrow' => 'Marketplace at a glance',
                        'title' => 'A focused platform for real-estate discovery',
                        'items' => [
                            ['value' => '1', 'label' => 'Unified marketplace'],
                            ['value' => '100%', 'label' => 'Approval-first publishing'],
                            ['value' => '24/7', 'label' => 'Self-service discovery'],
                            ['value' => 'Direct', 'label' => 'Company inquiries'],
                        ],
                    ],
                    'team' => [
                        'enabled' => false,
                        'eyebrow' => 'Our team',
                        'title' => 'People behind Barama',
                        'items' => [
                            ['name' => '', 'role' => '', 'bio' => '', 'image_url' => ''],
                            ['name' => '', 'role' => '', 'bio' => '', 'image_url' => ''],
                            ['name' => '', 'role' => '', 'bio' => '', 'image_url' => ''],
                            ['name' => '', 'role' => '', 'bio' => '', 'image_url' => ''],
                        ],
                    ],
                    'cta' => [
                        'enabled' => true,
                        'eyebrow' => 'Start exploring',
                        'title' => 'Find the right property context',
                        'description' => 'Browse approved marketplace inventory, explore developments, or connect with a real-estate company.',
                        'primary_label' => 'Browse properties',
                        'primary_url' => '/properties',
                        'secondary_label' => 'Contact us',
                        'secondary_url' => '/contact',
                    ],
                ],
            ],
            'contact' => [
                'enabled' => true,
                'seo' => [
                    'title' => 'Contact Us · Barama Real Estate',
                    'description' => 'Contact Barama Real Estate for marketplace support, general questions, partnerships, and platform assistance.',
                    'robots' => 'index,follow',
                ],
                'hero' => [
                    'enabled' => true,
                    'eyebrow' => 'Contact Barama',
                    'title' => 'How can we help?',
                    'description' => 'Send a message for marketplace support, general questions, partnership inquiries, or help using Barama Real Estate.',
                    'image_url' => '',
                ],
                'section_order' => [
                    'channels',
                    'office',
                    'hours',
                    'form',
                    'faq',
                    'socials',
                ],
                'sections' => [
                    'channels' => [
                        'enabled' => true,
                        'eyebrow' => 'Contact options',
                        'title' => 'Reach the Barama team',
                        'description' => 'Choose the contact option that works best for you.',
                        'phone' => '',
                        'email' => '',
                        'telegram_url' => '',
                        'whatsapp_url' => '',
                    ],
                    'office' => [
                        'enabled' => true,
                        'eyebrow' => 'Office',
                        'title' => 'Visit or locate us',
                        'address' => '',
                        'location_note' => '',
                        'map_url' => '',
                        'map_label' => 'Open map',
                    ],
                    'hours' => [
                        'enabled' => true,
                        'eyebrow' => 'Business hours',
                        'title' => 'When we are available',
                        'timezone' => 'Asia/Phnom_Penh',
                        'items' => [
                            ['day' => 'Monday', 'hours' => '08:30 – 17:30'],
                            ['day' => 'Tuesday', 'hours' => '08:30 – 17:30'],
                            ['day' => 'Wednesday', 'hours' => '08:30 – 17:30'],
                            ['day' => 'Thursday', 'hours' => '08:30 – 17:30'],
                            ['day' => 'Friday', 'hours' => '08:30 – 17:30'],
                            ['day' => 'Saturday', 'hours' => '09:00 – 12:00'],
                            ['day' => 'Sunday', 'hours' => 'Closed'],
                        ],
                    ],
                    'form' => [
                        'enabled' => true,
                        'eyebrow' => 'Send a message',
                        'title' => 'Tell us what you need',
                        'description' => 'Use this form for general support, partnership questions, or platform assistance. Property-specific questions should be sent from the relevant listing, project, or company page.',
                        'button_label' => 'Send message',
                        'success_message' => 'Thank you. Your message has been received and is now in our support inbox.',
                        'destination_email' => '',
                        'require_phone' => false,
                        'consent_text' => 'I agree that the information I submit may be used to respond to this request.',
                        'subjects' => [
                            'General question',
                            'Marketplace support',
                            'Partnership inquiry',
                            'Company onboarding',
                            'Technical issue',
                        ],
                    ],
                    'faq' => [
                        'enabled' => true,
                        'eyebrow' => 'Common questions',
                        'title' => 'Before you send a message',
                        'items' => [
                            ['question' => 'How do I ask about a property?', 'answer' => 'Open the published property, project, or company page and use its direct inquiry option so your message reaches the responsible company with the correct context.'],
                            ['question' => 'Can a company publish directly?', 'answer' => 'Marketplace records follow the configured review workflow before public publication.'],
                            ['question' => 'Where should I report a technical issue?', 'answer' => 'Choose Technical issue in the contact form and include the page or action that caused the problem.'],
                            ['question' => 'Can I discuss a partnership?', 'answer' => 'Yes. Choose Partnership inquiry and provide enough context for the Barama team to review your request.'],
                            ['question' => 'When will I receive a reply?', 'answer' => 'Response times depend on the subject and configured business hours. Your message is stored in the administration inbox for follow-up.'],
                        ],
                    ],
                    'socials' => [
                        'enabled' => false,
                        'eyebrow' => 'Follow Barama',
                        'title' => 'Official social channels',
                        'description' => 'Add only official Barama social profiles.',
                        'facebook_url' => '',
                        'instagram_url' => '',
                        'linkedin_url' => '',
                        'youtube_url' => '',
                        'telegram_url' => '',
                        'tiktok_url' => '',
                    ],
                ],
            ],
            'navigation' => [
                ['id' => 'home', 'label' => 'Home', 'path' => '/', 'active_area' => 'home', 'enabled' => true],
                ['id' => 'properties', 'label' => 'Properties', 'path' => '/properties', 'active_area' => 'properties', 'enabled' => true],
                ['id' => 'projects', 'label' => 'Projects', 'path' => '/projects', 'active_area' => 'projects', 'enabled' => true],
                ['id' => 'companies', 'label' => 'Companies', 'path' => '/company', 'active_area' => 'company', 'enabled' => true],
                ['id' => 'about', 'label' => 'About', 'path' => '/about', 'active_area' => 'about', 'enabled' => true],
                ['id' => 'contact', 'label' => 'Contact', 'path' => '/contact', 'active_area' => 'contact', 'enabled' => true],
            ],
        ];
    }
}
