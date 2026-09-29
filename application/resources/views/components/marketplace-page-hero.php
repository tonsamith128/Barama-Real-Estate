<?php

declare(strict_types=1);

$pageKind = in_array((string) ($pageKind ?? ''), ['properties', 'projects', 'companies'], true)
    ? (string) $pageKind
    : 'properties';

$defaults = \Barama\Repositories\SiteContentRepository::defaults()['marketplace_pages'][$pageKind] ?? [];
$pageConfig = array_replace_recursive(
    is_array($defaults) ? $defaults : [],
    is_array($pageConfig ?? null) ? $pageConfig : []
);

$hero = is_array($pageConfig['hero'] ?? null) ? $pageConfig['hero'] : [];
$background = is_array($hero['background'] ?? null) ? $hero['background'] : [];
$searchConfig = is_array($pageConfig['search'] ?? null) ? $pageConfig['search'] : [];
$saleRentConfig = is_array($pageConfig['sale_rent'] ?? null) ? $pageConfig['sale_rent'] : [];
$locationsConfig = is_array($pageConfig['locations'] ?? null) ? $pageConfig['locations'] : [];
$gridConfig = is_array($pageConfig['grid'] ?? null) ? $pageConfig['grid'] : [];

$heroEnabled = !empty($hero['enabled']);
$searchEnabled = !empty($searchConfig['enabled']);
$saleRentEnabled = !empty($saleRentConfig['enabled']);
$locationsEnabled = !empty($locationsConfig['enabled']);
$showResultCount = !array_key_exists('show_result_count', $gridConfig) || !empty($gridConfig['show_result_count']);

$backgroundType = strtolower((string) ($background['type'] ?? 'plain'));
if (!in_array($backgroundType, ['plain', 'color', 'image', 'slider', 'video'], true)) {
    $backgroundType = 'plain';
}

$overlayStrength = max(0, min(85, (int) ($background['overlay_strength'] ?? 44)));
$colorPreset = (string) ($background['color_preset'] ?? 'ocean');

$bundledHeroDefaults = match ($pageKind) {
    'projects' => [
        url('/assets/images/marketplace-defaults/projects-hero-01.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/projects-hero-02.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/projects-hero-03.webp') . '?v=111',
    ],
    'companies' => [
        url('/assets/images/marketplace-defaults/companies-hero-01.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/companies-hero-02.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/companies-hero-03.webp') . '?v=111',
    ],
    default => [
        url('/assets/images/marketplace-defaults/properties-hero-01.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/properties-hero-02.webp') . '?v=111',
        url('/assets/images/marketplace-defaults/properties-hero-03.webp') . '?v=111',
    ],
};

$automaticHeroSources = $bundledHeroDefaults;

$imageSrc = '';
if ($heroEnabled && $backgroundType === 'image') {
    if (($background['image_source'] ?? '') === 'media' && !empty($background['image_media_uuid'])) {
        $imageSrc = url('/media/' . rawurlencode((string) $background['image_media_uuid']));
    } else {
        $imageSrc = trim((string) ($background['image_url'] ?? ''));
    }

    if ($imageSrc === '') {
        $imageSrc = (string) ($automaticHeroSources[0] ?? '');
    }
}

$sliderSources = [];
if ($heroEnabled && $backgroundType === 'slider') {
    foreach ((array) ($background['slider_images'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = (string) ($item['type'] ?? '');
        $value = trim((string) ($item['value'] ?? ''));

        if ($value === '') {
            continue;
        }

        $sliderSources[] = $type === 'media'
            ? url('/media/' . rawurlencode($value))
            : $value;
    }

    if ($sliderSources === []) {
        $sliderSources = $automaticHeroSources;
    }
}

$videoSrc = '';
$youtubeEmbed = null;
if ($heroEnabled && $backgroundType === 'video') {
    $videoSource = (string) ($background['video_source'] ?? 'url');

    if ($videoSource === 'media' && !empty($background['video_media_uuid'])) {
        $videoSrc = url('/media/' . rawurlencode((string) $background['video_media_uuid']));
    } elseif ($videoSource === 'youtube') {
        $youtubeEmbed = barama_youtube_embed_url((string) ($background['youtube_url'] ?? ''));
        if ($youtubeEmbed !== null) {
            $youtubePath = (string) parse_url($youtubeEmbed, PHP_URL_PATH);
            $youtubeId = basename($youtubePath);
            $youtubeEmbed .= '&autoplay=1&mute=1&controls=0&playsinline=1&loop=1'
                . ($youtubeId !== '' ? '&playlist=' . rawurlencode($youtubeId) : '');
        }
    } else {
        $videoSrc = trim((string) ($background['video_url'] ?? ''));
    }
}

$hasHeroBackground = $heroEnabled && (
    $backgroundType === 'color'
    || ($backgroundType === 'image' && $imageSrc !== '')
    || ($backgroundType === 'slider' && $sliderSources !== [])
    || ($backgroundType === 'video' && ($videoSrc !== '' || $youtubeEmbed !== null))
);

$hasFilterControls = $searchEnabled || $locationsEnabled || ($pageKind === 'properties' && $saleRentEnabled);
$showHeroShell = $heroEnabled || $hasFilterControls || ($pageKind !== 'properties' && $saleRentEnabled);

if (!$showHeroShell) {
    return;
}

$allLocationsLabel = trim((string) ($locationsConfig['all_label'] ?? 'All locations'));
$allLocationsLabel = $allLocationsLabel !== '' ? $allLocationsLabel : 'All locations';
$searchPlaceholder = trim((string) ($searchConfig['placeholder'] ?? 'Search'));
$searchButtonLabel = trim((string) ($searchConfig['button_label'] ?? 'Search'));
$saleRentAllLabel = trim((string) ($saleRentConfig['all_label'] ?? 'Sale & rent'));
$saleLabel = trim((string) ($saleRentConfig['sale_label'] ?? 'For sale'));
$rentLabel = trim((string) ($saleRentConfig['rent_label'] ?? 'For rent'));
?>
<section class="marketplace-hero marketplace-hero--discovery marketplace-hero--<?= e($pageKind) ?> marketplace-config-hero marketplace-config-hero--background-<?= e($backgroundType) ?> <?= $hasHeroBackground ? 'marketplace-config-hero--rich-background' : '' ?>">
    <?php if ($heroEnabled && $backgroundType === 'color'): ?>
        <div class="marketplace-config-hero__background marketplace-config-hero__background--color hero-bg-preset--<?= e($colorPreset) ?>" aria-hidden="true"></div>
    <?php elseif ($heroEnabled && $backgroundType === 'image' && $imageSrc !== ''): ?>
        <div class="marketplace-config-hero__background marketplace-config-hero__background--media" aria-hidden="true">
            <img src="<?= e($imageSrc) ?>" alt="" decoding="async">
        </div>
    <?php elseif ($heroEnabled && $backgroundType === 'slider' && $sliderSources !== []): ?>
        <div
            class="marketplace-config-hero__background marketplace-config-hero__background--slider hero-slider hero-slider--<?= e((string) ($background['slider_style'] ?? 'fade')) ?>"
            data-hero-slider
            data-hero-slider-interval="<?= e((string) ((int) ($background['slider_interval'] ?? 6000))) ?>"
            data-hero-slider-autoplay="<?= !empty($background['slider_autoplay']) ? '1' : '0' ?>"
            aria-hidden="true"
        >
            <?php foreach ($sliderSources as $index => $source): ?>
                <img class="hero-slider__slide <?= $index === 0 ? 'is-active' : '' ?>" src="<?= e($source) ?>" alt="" decoding="async" <?= $index === 0 ? '' : 'loading="lazy"' ?>>
            <?php endforeach; ?>
        </div>
    <?php elseif ($heroEnabled && $backgroundType === 'video' && ($videoSrc !== '' || $youtubeEmbed !== null)): ?>
        <div class="marketplace-config-hero__background marketplace-config-hero__background--video" aria-hidden="true">
            <?php if ($youtubeEmbed !== null): ?>
                <iframe src="<?= e($youtubeEmbed) ?>" title="" tabindex="-1" allow="autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            <?php else: ?>
                <video autoplay muted loop playsinline preload="metadata"><source src="<?= e($videoSrc) ?>"></video>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($hasHeroBackground && $backgroundType !== 'color'): ?>
        <div class="marketplace-config-hero__overlay" style="--marketplace-hero-overlay: <?= e(number_format($overlayStrength / 100, 2, '.', '')) ?>" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container app-container marketplace-config-hero__content">
        <?php if ($heroEnabled): ?>
            <div class="marketplace-hero__grid">
                <div>
                    <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? '')) ?></span>
                    <h1><?= e((string) ($hero['title'] ?? '')) ?></h1>
                    <p><?= e((string) ($hero['description'] ?? '')) ?></p>
                </div>
                <?php if ($showResultCount): ?>
                    <div class="marketplace-hero__count">
                        <strong><?= e(number_format((int) ($total ?? 0))) ?></strong>
                        <?php if ($pageKind === 'projects'): ?>
                            <span>Published project<?= (int) ($total ?? 0) === 1 ? '' : 's' ?></span>
                        <?php elseif ($pageKind === 'companies'): ?>
                            <span>Approved compan<?= (int) ($total ?? 0) === 1 ? 'y' : 'ies' ?></span>
                        <?php else: ?>
                            <span>Published listing<?= (int) ($total ?? 0) === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasFilterControls): ?>
            <form class="discovery-filter <?= $pageKind === 'properties' ? '' : 'discovery-filter--compact' ?> marketplace-config-filter" method="get" data-marketplace-filter aria-label="<?= e(ucfirst($pageKind)) ?> filters">
                <div class="discovery-filter__primary discovery-filter__primary--dynamic">
                    <?php if ($searchEnabled): ?>
                        <label class="discovery-filter__search">
                            <span><?= app_icon('search') ?></span>
                            <input
                                name="q"
                                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                                placeholder="<?= e($searchPlaceholder) ?>"
                                aria-label="<?= e($searchPlaceholder) ?>"
                            >
                        </label>
                    <?php endif; ?>

                    <?php if ($pageKind === 'properties' && $saleRentEnabled): ?>
                        <label>
                            <span class="visually-hidden">Offer</span>
                            <select name="offer">
                                <option value=""><?= e($saleRentAllLabel) ?></option>
                                <option value="sale" <?= ($filters['offer'] ?? '') === 'sale' ? 'selected' : '' ?>><?= e($saleLabel) ?></option>
                                <option value="rent" <?= ($filters['offer'] ?? '') === 'rent' ? 'selected' : '' ?>><?= e($rentLabel) ?></option>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ($pageKind === 'projects' && $searchEnabled): ?>
                        <label>
                            <span class="visually-hidden">Project type</span>
                            <select name="type">
                                <option value="">All project types</option>
                                <?php foreach ((array) ($projectTypes ?? []) as $type): ?>
                                    <option value="<?= e((string) $type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', (string) $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php elseif ($pageKind === 'companies' && $searchEnabled): ?>
                        <label>
                            <span class="visually-hidden">Company type</span>
                            <select name="type">
                                <option value="">All company types</option>
                                <?php foreach ((array) ($companyTypes ?? []) as $type): ?>
                                    <option value="<?= e((string) $type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', (string) $type))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ($locationsEnabled): ?>
                        <label>
                            <span class="visually-hidden">Location</span>
                            <select name="location">
                                <option value=""><?= e($allLocationsLabel) ?></option>
                                <?php foreach ((array) ($locations ?? []) as $location): ?>
                                    <option value="<?= e((string) ($location['id'] ?? '')) ?>" <?= (string) ($filters['location'] ?? '') === (string) ($location['id'] ?? '') ? 'selected' : '' ?>><?= e((string) ($location['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ($pageKind === 'projects' && $searchEnabled): ?>
                        <label>
                            <span class="visually-hidden">Stage</span>
                            <select name="completion">
                                <option value="">All stages</option>
                                <?php foreach ((array) ($completionStatuses ?? []) as $status): ?>
                                    <option value="<?= e((string) $status) ?>" <?= ($filters['completion'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', (string) $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <button class="btn btn-primary" type="submit"><?= app_icon('search') ?><span><?= e($searchButtonLabel) ?></span></button>
                </div>

                <?php if ($pageKind === 'properties' && $searchEnabled): ?>
                    <?php
                    $advancedFilters = array_filter([
                        'type' => (string) ($filters['type'] ?? ''),
                        'bedrooms' => (string) ($filters['bedrooms'] ?? ''),
                        'min_price' => (string) ($filters['min_price'] ?? ''),
                        'max_price' => (string) ($filters['max_price'] ?? ''),
                        'currency' => (string) ($filters['currency'] ?? ''),
                        'sort' => ($filters['sort'] ?? 'newest') !== 'newest' ? (string) ($filters['sort'] ?? '') : '',
                    ]);
                    ?>
                    <details class="discovery-filter__more" <?= $advancedFilters !== [] ? 'open' : '' ?>>
                        <summary><?= app_icon('grid') ?><span>More filters</span><small><?= e((string) count($advancedFilters)) ?> active</small></summary>
                        <div class="discovery-filter__advanced">
                            <label><span>Property type</span><select name="type"><option value="">All types</option><?php foreach ((array) ($propertyTypes ?? []) as $type): ?><option value="<?= e((string) $type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', (string) $type))) ?></option><?php endforeach; ?></select></label>
                            <label><span>Bedrooms</span><select name="bedrooms"><option value="">Any</option><?php foreach ([1,2,3,4,5] as $bed): ?><option value="<?= e((string) $bed) ?>" <?= (string) ($filters['bedrooms'] ?? '') === (string) $bed ? 'selected' : '' ?>><?= e((string) $bed) ?>+</option><?php endforeach; ?></select></label>
                            <label><span>Min price</span><input type="number" min="0" step="100" name="min_price" value="<?= e((string) ($filters['min_price'] ?? '')) ?>" placeholder="No minimum"></label>
                            <label><span>Max price</span><input type="number" min="0" step="100" name="max_price" value="<?= e((string) ($filters['max_price'] ?? '')) ?>" placeholder="No maximum"></label>
                            <label><span>Currency</span><input name="currency" maxlength="3" value="<?= e((string) ($filters['currency'] ?? '')) ?>" placeholder="Any"></label>
                            <label><span>Sort</span><select name="sort"><option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option><option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option><option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option><option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest</option></select></label>
                            <div class="discovery-filter__advanced-actions"><button class="btn btn-primary btn-sm" type="submit">Apply filters</button><a href="<?= e(url('/properties')) ?>">Clear all</a></div>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="discovery-filter__links">
                        <a href="<?= e(url($pageKind === 'companies' ? '/company' : '/' . $pageKind)) ?>">Clear filters</a>
                        <?php if ($pageKind === 'projects'): ?><a href="<?= e(url('/company')) ?>">Browse companies</a><?php endif; ?>
                        <?php if ($pageKind === 'companies'): ?><a href="<?= e(url('/projects')) ?>">Explore projects</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($pageKind !== 'properties' && $saleRentEnabled): ?>
            <nav class="marketplace-hero-intents" aria-label="Property sale and rent shortcuts">
                <span><?= app_icon('home') ?> Property discovery</span>
                <a href="<?= e(url('/properties')) ?>"><?= e($saleRentAllLabel) ?></a>
                <a href="<?= e(url('/properties?offer=sale')) ?>"><?= e($saleLabel) ?></a>
                <a href="<?= e(url('/properties?offer=rent')) ?>"><?= e($rentLabel) ?></a>
            </nav>
        <?php endif; ?>
    </div>
</section>
