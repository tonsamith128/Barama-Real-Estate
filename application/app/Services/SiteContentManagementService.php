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
    private function validatedHero(array $input, array $current): array
    {
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

        $imageSource = strtolower(trim((string) ($input['image_source'] ?? $background['image_source'] ?? 'url')));
        $imageSource = in_array($imageSource, ['url', 'media'], true) ? $imageSource : 'url';
        $imageUrl = $this->optionalExternalUrl(
            $input['image_url'] ?? $background['image_url'] ?? '',
            'Hero image URL'
        );
        $imageMediaUuid = $this->uuid(
            $input['uploaded_image_uuid']
                ?? $input['image_media_uuid']
                ?? $background['image_media_uuid']
                ?? '',
            'Hero image media'
        );

        if (!empty($input['uploaded_image_uuid'])) {
            $imageSource = 'media';
        }

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

        if ($type === 'image') {
            if ($imageSource === 'media' && $imageMediaUuid === '') {
                throw new InvalidArgumentException('Upload or select a hero image before enabling Image Background.');
            }
            if ($imageSource === 'url' && $imageUrl === '') {
                throw new InvalidArgumentException('Enter a hero image URL before enabling Image Background.');
            }
        }

        if ($type === 'slider' && $sliderImages === []) {
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

        $config['homepage']['sections'][$section] = [
            'enabled' => !empty($current['enabled']),
            'eyebrow' => $this->text($input['eyebrow'] ?? $current['eyebrow'] ?? '', 80, ucfirst($section) . ' eyebrow'),
            'title' => $this->text($input['title'] ?? $current['title'] ?? '', 140, ucfirst($section) . ' title'),
            'link_label' => $this->text($input['link_label'] ?? $current['link_label'] ?? '', 80, ucfirst($section) . ' link label'),
        ];

        $order = is_array($config['homepage']['section_order'] ?? null)
            ? array_values($config['homepage']['section_order'])
            : ['listings', 'projects', 'companies'];
        $order = array_values(array_filter(
            $order,
            static fn (mixed $value): bool => (string) $value !== $section
        ));
        $position = $this->position($input['position'] ?? null, count($order) + 1);
        array_splice($order, $position - 1, 0, [$section]);
        $config['homepage']['section_order'] = array_values($order);

        return $config;
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
