<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\SiteContentRepository;
use Barama\Support\IdGenerator;
use DomainException;
use InvalidArgumentException;

final class SiteContentManagementService
{
    private const HOMEPAGE_FEATURES = [
        'hero',
        'search',
        'market_pulse',
        'quick_actions',
        'listings',
        'projects',
        'companies',
        'featured_locations',
        'how_it_works',
        'trust_stats',
        'testimonials',
        'blog',
        'subscribe',
    ];

    /** @param array<string,mixed> $input */
    public function updateHomepageFeature(string $feature, array $input): void
    {
        $actor = $this->administrator();
        $feature = $this->homepageFeature($feature);
        $repository = new SiteContentRepository();
        $config = $repository->get();

        if ($feature === 'hero') {
            $current = is_array($config['homepage']['hero'] ?? null)
                ? $config['homepage']['hero']
                : [];
            $config['homepage']['hero'] = $this->validatedHero($input, $current);

            /* Keep legacy keys synchronized for upgrade/downgrade safety. */
            $config['homepage']['hero_eyebrow'] = $config['homepage']['hero']['eyebrow'];
            $config['homepage']['hero_title'] = $config['homepage']['hero']['title'];
            $config['homepage']['hero_description'] = $config['homepage']['hero']['description'];
        } elseif (in_array($feature, ['search', 'market_pulse', 'quick_actions'], true)) {
            $config['homepage']['features'][$feature] = $this->validatedUtilityFeature(
                $feature,
                $input,
                is_array($config['homepage']['features'][$feature] ?? null)
                    ? $config['homepage']['features'][$feature]
                    : []
            );
        } else {
            $config = $this->updateHomepageSection($config, $feature, $input);
        }

        $repository->save($config);
        $this->audit(
            'admin.homepage_feature_updated',
            (int) $actor['id'],
            ['feature' => $feature]
        );
    }

    public function setHomepageFeatureEnabled(string $feature, bool $enabled): void
    {
        $actor = $this->administrator();
        $feature = $this->homepageFeature($feature);
        $repository = new SiteContentRepository();
        $config = $repository->get();

        if ($feature === 'hero') {
            $config['homepage']['hero']['enabled'] = $enabled;
        } elseif (in_array($feature, ['search', 'market_pulse', 'quick_actions'], true)) {
            $config['homepage']['features'][$feature]['enabled'] = $enabled;
        } else {
            $config['homepage']['sections'][$feature]['enabled'] = $enabled;
        }

        $repository->save($config);
        $this->audit(
            'admin.homepage_feature_toggled',
            (int) $actor['id'],
            ['feature' => $feature, 'enabled' => $enabled]
        );
    }

    /** @param array<string,mixed> $input */
    public function updateMarketplacePage(string $page, array $input): void
    {
        $actor = $this->administrator();
        $page = strtolower(trim($page));

        if (!in_array($page, ['properties', 'projects', 'companies'], true)) {
            throw new InvalidArgumentException('Unknown marketplace page.');
        }

        $repository = new SiteContentRepository();
        $config = $repository->get();
        $defaults = SiteContentRepository::defaults()['marketplace_pages'][$page];
        $current = is_array($config['marketplace_pages'][$page] ?? null)
            ? array_replace_recursive($defaults, $config['marketplace_pages'][$page])
            : $defaults;

        $hero = $this->validatedHero(
            $input,
            is_array($current['hero'] ?? null) ? $current['hero'] : [],
            true
        );
        $hero['enabled'] = $this->boolean(
            $input['hero_enabled'] ?? '0'
        );

        $search = [
            'enabled' => $this->boolean(
                $input['search_enabled'] ?? '0'
            ),
            'placeholder' => $this->text(
                $input['search_placeholder'] ?? $current['search']['placeholder'] ?? '',
                120,
                'Marketplace search placeholder'
            ),
            'button_label' => $this->text(
                $input['search_button_label'] ?? $current['search']['button_label'] ?? 'Search',
                40,
                'Marketplace search button label'
            ),
        ];

        $saleRent = [
            'enabled' => $this->boolean(
                $input['sale_rent_enabled'] ?? '0'
            ),
            'all_label' => $this->text(
                $input['sale_rent_all_label'] ?? $current['sale_rent']['all_label'] ?? 'Sale & rent',
                60,
                'Sale and rent label'
            ),
            'sale_label' => $this->text(
                $input['sale_label'] ?? $current['sale_rent']['sale_label'] ?? 'For sale',
                60,
                'Sale label'
            ),
            'rent_label' => $this->text(
                $input['rent_label'] ?? $current['sale_rent']['rent_label'] ?? 'For rent',
                60,
                'Rent label'
            ),
        ];

        $locations = [
            'enabled' => $this->boolean(
                $input['locations_enabled'] ?? '0'
            ),
            'all_label' => $this->text(
                $input['locations_all_label'] ?? $current['locations']['all_label'] ?? 'All locations',
                60,
                'All locations label'
            ),
        ];

        $columns = filter_var(
            $input['grid_columns'] ?? $current['grid']['columns'] ?? 3,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => 4]]
        );
        $columns = $columns === false ? 3 : (int) $columns;

        $pageSize = filter_var(
            $input['grid_page_size'] ?? $current['grid']['page_size'] ?? 18,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 6, 'max_range' => 48]]
        );
        $pageSize = $pageSize === false ? (int) ($defaults['grid']['page_size'] ?? 18) : (int) $pageSize;

        $grid = [
            'columns' => $columns,
            'page_size' => $pageSize,
            'show_result_count' => $this->boolean(
                $input['grid_show_result_count'] ?? '0'
            ),
        ];

        $config['marketplace_pages'][$page] = [
            'hero' => $hero,
            'search' => $search,
            'sale_rent' => $saleRent,
            'locations' => $locations,
            'grid' => $grid,
        ];

        $repository->save($config);

        try {
            $this->audit(
                'admin.marketplace_page_configuration_updated',
                (int) $actor['id'],
                ['page' => $page]
            );
        } catch (\Throwable $exception) {
            error_log(
                '[Barama Marketplace Configuration Audit] '
                . $exception::class
                . ': '
                . $exception->getMessage()
            );
        }
    }

    /** @param array<string,mixed> $input */
    public function createNavigation(array $input): string
    {
        $actor = $this->administrator();
        $repository = new SiteContentRepository();
        $config = $repository->get();
        $navigation = is_array($config['navigation'] ?? null)
            ? array_values($config['navigation'])
            : [];

        if (count($navigation) >= 20) {
            throw new DomainException('Primary navigation is limited to 20 items.');
        }

        $item = $this->validatedNavigationItem($input);
        $item['id'] = IdGenerator::uuid();
        $position = $this->position($input['position'] ?? null, count($navigation) + 1);
        array_splice($navigation, $position - 1, 0, [$item]);
        $config['navigation'] = array_values($navigation);
        $repository->save($config);

        $this->audit(
            'admin.navigation_created',
            (int) $actor['id'],
            ['navigation_id' => $item['id'], 'path' => $item['path']]
        );

        return (string) $item['id'];
    }

    /** @param array<string,mixed> $input */
    public function updateNavigation(string $id, array $input): void
    {
        $actor = $this->administrator();
        $repository = new SiteContentRepository();
        $config = $repository->get();
        $navigation = is_array($config['navigation'] ?? null)
            ? array_values($config['navigation'])
            : [];
        $index = $this->navigationIndex($navigation, $id);
        $item = $this->validatedNavigationItem($input);
        $item['id'] = (string) $navigation[$index]['id'];
        $wasEnabled = !empty($navigation[$index]['enabled']);

        if (!$item['enabled'] && $wasEnabled && $this->enabledNavigationCount($navigation) <= 1) {
            throw new DomainException('Keep at least one primary navigation item enabled.');
        }

        array_splice($navigation, $index, 1);
        $position = $this->position($input['position'] ?? null, count($navigation) + 1);
        array_splice($navigation, $position - 1, 0, [$item]);
        $config['navigation'] = array_values($navigation);
        $repository->save($config);

        $this->audit(
            'admin.navigation_updated',
            (int) $actor['id'],
            ['navigation_id' => $id, 'path' => $item['path']]
        );
    }

    public function setNavigationEnabled(string $id, bool $enabled): void
    {
        $actor = $this->administrator();
        $repository = new SiteContentRepository();
        $config = $repository->get();
        $navigation = is_array($config['navigation'] ?? null)
            ? array_values($config['navigation'])
            : [];
        $index = $this->navigationIndex($navigation, $id);

        if (
            !$enabled
            && !empty($navigation[$index]['enabled'])
            && $this->enabledNavigationCount($navigation) <= 1
        ) {
            throw new DomainException('Keep at least one primary navigation item enabled.');
        }

        $navigation[$index]['enabled'] = $enabled;
        $config['navigation'] = array_values($navigation);
        $repository->save($config);

        $this->audit(
            'admin.navigation_toggled',
            (int) $actor['id'],
            ['navigation_id' => $id, 'enabled' => $enabled]
        );
    }

    public function deleteNavigation(string $id): void
    {
        $actor = $this->administrator();
        $repository = new SiteContentRepository();
        $config = $repository->get();
        $navigation = is_array($config['navigation'] ?? null)
            ? array_values($config['navigation'])
            : [];
        $index = $this->navigationIndex($navigation, $id);

        if (
            !empty($navigation[$index]['enabled'])
            && $this->enabledNavigationCount($navigation) <= 1
        ) {
            throw new DomainException('The last enabled navigation item cannot be deleted.');
        }

        $removed = $navigation[$index];
        array_splice($navigation, $index, 1);
        $config['navigation'] = array_values($navigation);
        $repository->save($config);

        $this->audit(
            'admin.navigation_deleted',
            (int) $actor['id'],
            ['navigation_id' => $id, 'path' => (string) ($removed['path'] ?? '')]
        );
    }

    /** Backward-compatible combined update used only by older packages. */
    public function update(array $input): void
    {
        $this->updateHomepageFeature('hero', $input);
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedHero(
        array $input,
        array $current,
        bool $allowAutomaticImageDefaults = false
    ): array {
        $defaults = SiteContentRepository::defaults()['homepage']['hero'];
        $current = array_replace_recursive($defaults, $current);
        $background = is_array($current['background'] ?? null)
            ? $current['background']
            : $defaults['background'];

        $eyebrow = $this->text($input['hero_eyebrow'] ?? $current['eyebrow'], 80, 'Homepage eyebrow');
        $title = $this->text($input['hero_title'] ?? $current['title'], 140, 'Homepage title');
        $description = $this->text(
            $input['hero_description'] ?? $current['description'],
            500,
            'Homepage description'
        );

        if ($title === '' || $description === '') {
            throw new InvalidArgumentException('Homepage title and description are required.');
        }

        $type = strtolower(trim((string) ($input['background_type'] ?? $background['type'] ?? 'plain')));

        if (!in_array($type, ['plain', 'color', 'image', 'slider', 'video'], true)) {
            throw new InvalidArgumentException('Choose a supported hero background type.');
        }

        $colorPreset = strtolower(trim((string) ($input['color_preset'] ?? $background['color_preset'] ?? 'ocean')));

        if (!in_array($colorPreset, ['ocean', 'aurora', 'sunset', 'violet', 'emerald'], true)) {
            $colorPreset = 'ocean';
        }

        $overlay = filter_var(
            $input['overlay_strength'] ?? $background['overlay_strength'] ?? 44,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 85]]
        );
        $overlay = $overlay === false ? 44 : (int) $overlay;

        $imageSource = strtolower(trim((string) ($input['image_source'] ?? $background['image_source'] ?? 'auto')));
        $allowedImageSources = $allowAutomaticImageDefaults
            ? ['auto', 'url', 'media']
            : ['url', 'media'];
        $imageSource = in_array($imageSource, $allowedImageSources, true)
            ? $imageSource
            : ($allowAutomaticImageDefaults ? 'auto' : 'url');

        if (!empty($input['uploaded_image_uuid'])) {
            $imageSource = 'media';
        }

        $imageUrl = $imageSource === 'url'
            ? $this->optionalExternalUrl(
                $input['image_url'] ?? $background['image_url'] ?? '',
                'Hero image URL'
            )
            : '';
        $imageMediaUuid = $imageSource === 'media'
            ? $this->uuid(
                $input['uploaded_image_uuid']
                    ?? $input['image_media_uuid']
                    ?? $background['image_media_uuid']
                    ?? '',
                'Hero image media'
            )
            : '';

        $sliderStyle = strtolower(trim((string) ($input['slider_style'] ?? $background['slider_style'] ?? 'fade')));

        if (!in_array($sliderStyle, ['fade', 'slide', 'kenburns'], true)) {
            $sliderStyle = 'fade';
        }

        $sliderInterval = filter_var(
            $input['slider_interval'] ?? $background['slider_interval'] ?? 6000,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2500, 'max_range' => 20000]]
        );
        $sliderInterval = $sliderInterval === false ? 6000 : (int) $sliderInterval;
        $sliderAutoplay = $this->boolean(
            $input['slider_autoplay'] ?? ($background['slider_autoplay'] ? '1' : '0')
        );

        $sliderImages = [];
        $existing = is_array($input['slider_existing'] ?? null)
            ? array_values($input['slider_existing'])
            : [];
        $removed = is_array($input['slider_remove'] ?? null)
            ? array_map('intval', array_values($input['slider_remove']))
            : [];

        foreach ($existing as $index => $encoded) {
            if (in_array($index, $removed, true)) {
                continue;
            }

            $item = $this->sliderItem((string) $encoded);
            if ($item !== null) {
                $sliderImages[] = $item;
            }
        }

        $uploadedSlider = is_array($input['uploaded_slider_uuids'] ?? null)
            ? $input['uploaded_slider_uuids']
            : [];

        foreach ($uploadedSlider as $uuid) {
            $value = $this->uuid($uuid, 'Slider media');
            if ($value !== '') {
                $sliderImages[] = ['type' => 'media', 'value' => $value];
            }
        }

        $urlLines = preg_split('/\R+/', trim((string) ($input['slider_urls'] ?? ''))) ?: [];

        foreach ($urlLines as $url) {
            $url = $this->optionalExternalUrl($url, 'Slider image URL');
            if ($url !== '') {
                $sliderImages[] = ['type' => 'url', 'value' => $url];
            }
        }

        $deduped = [];
        foreach ($sliderImages as $item) {
            $key = $item['type'] . ':' . $item['value'];
            $deduped[$key] = $item;
        }
        $sliderImages = array_slice(array_values($deduped), 0, 12);

        $videoSource = strtolower(trim((string) ($input['video_source'] ?? $background['video_source'] ?? 'url')));
        $videoSource = in_array($videoSource, ['url', 'media', 'youtube'], true) ? $videoSource : 'url';
        $videoUrl = $this->optionalExternalUrl(
            $input['video_url'] ?? $background['video_url'] ?? '',
            'Hero video URL'
        );
        $videoMediaUuid = $this->uuid(
            $input['uploaded_video_uuid']
                ?? $input['video_media_uuid']
                ?? $background['video_media_uuid']
                ?? '',
            'Hero video media'
        );
        $youtubeUrl = $this->optionalYoutubeUrl(
            $input['youtube_url'] ?? $background['youtube_url'] ?? ''
        );

        if (!empty($input['uploaded_video_uuid'])) {
            $videoSource = 'media';
        }

        if ($type === 'image' && !$allowAutomaticImageDefaults) {
            if ($imageSource === 'media' && $imageMediaUuid === '') {
                throw new InvalidArgumentException('Upload or select a hero image before enabling Image Background.');
            }
            if ($imageSource === 'url' && $imageUrl === '') {
                throw new InvalidArgumentException('Enter a hero image URL before enabling Image Background.');
            }
        }

        if ($type === 'slider' && $sliderImages === [] && !$allowAutomaticImageDefaults) {
            throw new InvalidArgumentException('Add at least one image before enabling Image Slider Background.');
        }

        if ($type === 'video') {
            $valid = match ($videoSource) {
                'media' => $videoMediaUuid !== '',
                'youtube' => $youtubeUrl !== '',
                default => $videoUrl !== '',
            };
            if (!$valid) {
                throw new InvalidArgumentException('Configure a valid video source before enabling Video Background.');
            }
        }

        return [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $eyebrow,
            'title' => $title,
            'description' => $description,
            'background' => [
                'type' => $type,
                'color_preset' => $colorPreset,
                'overlay_strength' => $overlay,
                'image_source' => $imageSource,
                'image_url' => $imageUrl,
                'image_media_uuid' => $imageMediaUuid,
                'slider_images' => $sliderImages,
                'slider_style' => $sliderStyle,
                'slider_interval' => $sliderInterval,
                'slider_autoplay' => $sliderAutoplay,
                'video_source' => $videoSource,
                'video_url' => $videoUrl,
                'video_media_uuid' => $videoMediaUuid,
                'youtube_url' => $youtubeUrl,
            ],
        ];
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedUtilityFeature(string $feature, array $input, array $current): array
    {
        if ($feature === 'search') {
            return [
                'enabled' => !empty($current['enabled']),
                'placeholder' => $this->text(
                    $input['placeholder'] ?? $current['placeholder'] ?? '',
                    120,
                    'Search placeholder'
                ),
                'button_label' => $this->text(
                    $input['button_label'] ?? $current['button_label'] ?? 'Search',
                    40,
                    'Search button label'
                ),
            ];
        }

        if ($feature === 'market_pulse') {
            return [
                'enabled' => !empty($current['enabled']),
                'title' => $this->text($input['title'] ?? $current['title'] ?? '', 80, 'Marketplace summary title'),
                'subtitle' => $this->text($input['subtitle'] ?? $current['subtitle'] ?? '', 120, 'Marketplace summary subtitle'),
                'note' => $this->text($input['note'] ?? $current['note'] ?? '', 180, 'Marketplace summary note'),
            ];
        }

        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        $currentItems = is_array($current['items'] ?? null) ? $current['items'] : [];
        $result = [];

        foreach (['sale', 'rent', 'project', 'company'] as $key) {
            $row = is_array($items[$key] ?? null) ? $items[$key] : [];
            $fallback = is_array($currentItems[$key] ?? null) ? $currentItems[$key] : [];
            $result[$key] = [
                'title' => $this->text($row['title'] ?? $fallback['title'] ?? '', 80, ucfirst($key) . ' action title'),
                'description' => $this->text($row['description'] ?? $fallback['description'] ?? '', 180, ucfirst($key) . ' action description'),
            ];
        }

        return [
            'enabled' => !empty($current['enabled']),
            'items' => $result,
        ];
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $input @return array<string,mixed> */
    private function updateHomepageSection(array $config, string $section, array $input): array
    {
        $current = is_array($config['homepage']['sections'][$section] ?? null)
            ? $config['homepage']['sections'][$section]
            : [];

        $config['homepage']['sections'][$section] = $this->validatedHomepageSection(
            $section,
            $input,
            $current
        );

        $order = is_array($config['homepage']['section_order'] ?? null)
            ? array_values($config['homepage']['section_order'])
            : array_values(SiteContentRepository::defaults()['homepage']['section_order']);
        $order = array_values(array_filter(
            $order,
            static fn (mixed $value): bool => (string) $value !== $section
        ));
        $position = $this->position($input['position'] ?? null, count($order) + 1);
        array_splice($order, $position - 1, 0, [$section]);
        $config['homepage']['section_order'] = array_values($order);

        return $config;
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $current @return array<string,mixed> */
    private function validatedHomepageSection(string $section, array $input, array $current): array
    {
        $base = [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $this->text(
                $input['eyebrow'] ?? $current['eyebrow'] ?? '',
                80,
                ucfirst(str_replace('_', ' ', $section)) . ' eyebrow'
            ),
            'title' => $this->text(
                $input['title'] ?? $current['title'] ?? '',
                140,
                ucfirst(str_replace('_', ' ', $section)) . ' title'
            ),
            'link_label' => $this->text(
                $input['link_label'] ?? $current['link_label'] ?? '',
                80,
                ucfirst(str_replace('_', ' ', $section)) . ' link label'
            ),
        ];

        if ($section === 'featured_locations') {
            $limit = filter_var(
                $input['limit'] ?? $current['limit'] ?? 6,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 3, 'max_range' => 8]]
            );

            return $base + [
                'limit' => $limit === false ? 6 : (int) $limit,
            ];
        }

        if ($section === 'how_it_works') {
            return $base + [
                'items' => $this->textCards(
                    $input['items'] ?? null,
                    $current['items'] ?? null,
                    3,
                    'How it works'
                ),
            ];
        }

        if ($section === 'trust_stats') {
            $rows = is_array($input['items'] ?? null)
                ? $input['items']
                : (is_array($current['items'] ?? null) ? $current['items'] : []);
            $items = [];

            for ($index = 0; $index < 4; $index++) {
                $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
                $items[] = [
                    'value' => $this->text($row['value'] ?? '', 24, 'Trust stat value'),
                    'label' => $this->text($row['label'] ?? '', 90, 'Trust stat label'),
                ];
            }

            return $base + ['items' => $items];
        }

        if ($section === 'testimonials') {
            $rows = is_array($input['items'] ?? null)
                ? $input['items']
                : (is_array($current['items'] ?? null) ? $current['items'] : []);
            $items = [];

            for ($index = 0; $index < 3; $index++) {
                $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
                $items[] = [
                    'quote' => $this->text($row['quote'] ?? '', 500, 'Testimonial quote'),
                    'name' => $this->text($row['name'] ?? '', 100, 'Testimonial name'),
                    'role' => $this->text($row['role'] ?? '', 120, 'Testimonial role'),
                ];
            }

            return $base + ['items' => $items];
        }

        if ($section === 'blog') {
            $rows = is_array($input['items'] ?? null)
                ? $input['items']
                : (is_array($current['items'] ?? null) ? $current['items'] : []);
            $items = [];

            for ($index = 0; $index < 3; $index++) {
                $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
                $items[] = [
                    'title' => $this->text($row['title'] ?? '', 140, 'Blog card title'),
                    'excerpt' => $this->text($row['excerpt'] ?? '', 320, 'Blog card excerpt'),
                    'url' => $this->optionalLink($row['url'] ?? ''),
                    'image_url' => $this->optionalExternalUrl(
                        $row['image_url'] ?? '',
                        'Blog card image URL'
                    ),
                ];
            }

            return $base + [
                'link_url' => $this->optionalLink(
                    $input['link_url'] ?? $current['link_url'] ?? ''
                ),
                'items' => $items,
            ];
        }

        if ($section === 'subscribe') {
            return [
                'enabled' => !empty($current['enabled']),
                'eyebrow' => $base['eyebrow'],
                'title' => $base['title'],
                'description' => $this->text(
                    $input['description'] ?? $current['description'] ?? '',
                    360,
                    'Subscribe description'
                ),
                'placeholder' => $this->text(
                    $input['placeholder'] ?? $current['placeholder'] ?? '',
                    120,
                    'Subscribe placeholder'
                ),
                'button_label' => $this->text(
                    $input['button_label'] ?? $current['button_label'] ?? 'Subscribe',
                    60,
                    'Subscribe button label'
                ),
                'success_message' => $this->text(
                    $input['success_message'] ?? $current['success_message'] ?? '',
                    220,
                    'Subscribe success message'
                ),
                'consent_text' => $this->text(
                    $input['consent_text'] ?? $current['consent_text'] ?? '',
                    220,
                    'Subscribe consent text'
                ),
            ];
        }

        return $base;
    }

    /** @return array<int,array{title:string,description:string}> */
    private function textCards(mixed $input, mixed $current, int $count, string $label): array
    {
        $rows = is_array($input)
            ? $input
            : (is_array($current) ? $current : []);
        $items = [];

        for ($index = 0; $index < $count; $index++) {
            $row = is_array($rows[$index] ?? null) ? $rows[$index] : [];
            $items[] = [
                'title' => $this->text(
                    $row['title'] ?? '',
                    100,
                    $label . ' title'
                ),
                'description' => $this->text(
                    $row['description'] ?? '',
                    260,
                    $label . ' description'
                ),
            ];
        }

        return $items;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validatedNavigationItem(array $input): array
    {
        $label = $this->text($input['label'] ?? '', 60, 'Navigation label');
        $path = $this->internalPath(trim((string) ($input['path'] ?? '')));

        if ($label === '') {
            throw new InvalidArgumentException('Navigation label is required.');
        }

        return [
            'label' => $label,
            'path' => $path,
            'active_area' => $this->activeArea($path),
            'enabled' => $this->boolean($input['enabled'] ?? '0'),
        ];
    }

    private function homepageFeature(string $feature): string
    {
        $feature = strtolower(trim($feature));

        if (!in_array($feature, self::HOMEPAGE_FEATURES, true)) {
            throw new InvalidArgumentException('Unknown homepage feature.');
        }

        return $feature;
    }

    /** @param array<int,array<string,mixed>> $navigation */
    private function navigationIndex(array $navigation, string $id): int
    {
        foreach ($navigation as $index => $item) {
            if ((string) ($item['id'] ?? '') === $id) {
                return $index;
            }
        }

        throw new InvalidArgumentException('Navigation item was not found.');
    }

    /** @param array<int,array<string,mixed>> $navigation */
    private function enabledNavigationCount(array $navigation): int
    {
        return count(array_filter(
            $navigation,
            static fn (array $item): bool => !empty($item['enabled'])
        ));
    }

    private function position(mixed $value, int $max): int
    {
        $position = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => max(1, $max)]]
        );

        return $position === false ? max(1, $max) : (int) $position;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    private function text(mixed $value, int $max, string $label): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException($label . ' cannot exceed ' . $max . ' characters.');
        }

        return $value;
    }

    private function uuid(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidArgumentException($label . ' reference is invalid.');
        }

        return strtolower($value);
    }

    /** @return array{type:string,value:string}|null */
    private function sliderItem(string $encoded): ?array
    {
        [$type, $value] = array_pad(explode(':', $encoded, 2), 2, '');

        if ($type === 'media') {
            $value = $this->uuid($value, 'Slider media');
        } elseif ($type === 'url') {
            $value = $this->optionalExternalUrl($value, 'Slider image URL');
        } else {
            return null;
        }

        return $value !== '' ? ['type' => $type, 'value' => $value] : null;
    }

    private function optionalLink(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/')) {
            return $this->internalPath($value);
        }

        return $this->optionalExternalUrl($value, 'Blog card URL');
    }

    private function optionalExternalUrl(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 1000) {
            throw new InvalidArgumentException($label . ' is too long.');
        }

        $parts = parse_url($value);

        if (
            !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException($label . ' must be a valid HTTP or HTTPS URL.');
        }

        return $value;
    }

    private function optionalYoutubeUrl(mixed $value): string
    {
        $value = $this->optionalExternalUrl($value, 'YouTube URL');

        if ($value === '') {
            return '';
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (!in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            throw new InvalidArgumentException('Video Background YouTube source must use YouTube.');
        }

        return $value;
    }

    private function internalPath(string $path): string
    {
        if (
            $path === ''
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new InvalidArgumentException('Navigation links must use safe same-site paths beginning with /.');
        }

        $parts = parse_url($path);

        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('Navigation links must stay inside this Barama installation.');
        }

        return mb_substr($path, 0, 255);
    }

    private function activeArea(string $path): string
    {
        $route = (string) parse_url($path, PHP_URL_PATH);

        return match (true) {
            $route === '/' => 'home',
            $route === '/about' => 'about',
            $route === '/contact' => 'contact',
            str_starts_with($route, '/properties') => 'properties',
            str_starts_with($route, '/projects') => 'projects',
            str_starts_with($route, '/company') => 'company',
            default => '',
        };
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('page.manage')
        ) {
            throw new DomainException('Administrator page management permission is required.');
        }

        return $user;
    }

    /** @param array<string,mixed> $metadata */
    private function audit(string $event, int $actorId, array $metadata): void
    {
        (new SecurityAuditService())->record(
            $event,
            $actorId,
            null,
            $metadata
        );
    }
}
