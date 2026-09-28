<?php
declare(strict_types=1);

$marketStats = is_array($marketStats ?? null) ? $marketStats : [];
$homepageContent = is_array($homepageContent ?? null) ? $homepageContent : [];
$defaults = \Barama\Repositories\SiteContentRepository::defaults()['homepage'];
$homepageContent = array_replace_recursive($defaults, $homepageContent);

$hero = is_array($homepageContent['hero'] ?? null)
    ? $homepageContent['hero']
    : $defaults['hero'];
$background = is_array($hero['background'] ?? null)
    ? $hero['background']
    : $defaults['hero']['background'];
$features = is_array($homepageContent['features'] ?? null)
    ? $homepageContent['features']
    : $defaults['features'];
$homeSections = is_array($homepageContent['sections'] ?? null)
    ? $homepageContent['sections']
    : [];
$homeOrder = is_array($homepageContent['section_order'] ?? null)
    ? array_values($homepageContent['section_order'])
    : ['listings', 'projects', 'companies'];

$heroEyebrow = trim((string) ($hero['eyebrow'] ?? $homepageContent['hero_eyebrow'] ?? 'Barama Real Estate'));
$heroTitle = trim((string) ($hero['title'] ?? $homepageContent['hero_title'] ?? 'Property Discovery Simplified'));
$heroDescription = trim((string) (
    $hero['description']
    ?? $homepageContent['hero_description']
    ?? 'Find approved homes, rental opportunities, new developments, and real estate companies without sorting through unnecessary clutter.'
));

$heroEnabled = !empty($hero['enabled']);
$search = is_array($features['search'] ?? null) ? $features['search'] : [];
$marketPulse = is_array($features['market_pulse'] ?? null) ? $features['market_pulse'] : [];
$quickActions = is_array($features['quick_actions'] ?? null) ? $features['quick_actions'] : [];
$quickItems = is_array($quickActions['items'] ?? null) ? $quickActions['items'] : [];

$backgroundType = strtolower((string) ($background['type'] ?? 'plain'));
if (!in_array($backgroundType, ['plain', 'color', 'image', 'slider', 'video'], true)) {
    $backgroundType = 'plain';
}

$overlayStrength = max(0, min(85, (int) ($background['overlay_strength'] ?? 44)));
$colorPreset = (string) ($background['color_preset'] ?? 'ocean');

$imageSrc = '';
if ($backgroundType === 'image') {
    if (($background['image_source'] ?? '') === 'media' && !empty($background['image_media_uuid'])) {
        $imageSrc = url('/media/' . rawurlencode((string) $background['image_media_uuid']));
    } else {
        $imageSrc = trim((string) ($background['image_url'] ?? ''));
    }
}

$sliderSources = [];
if ($backgroundType === 'slider') {
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
}

$videoSrc = '';
$youtubeEmbed = null;
if ($backgroundType === 'video') {
    $videoSource = (string) ($background['video_source'] ?? 'url');

    if ($videoSource === 'media' && !empty($background['video_media_uuid'])) {
        $videoSrc = url('/media/' . rawurlencode((string) $background['video_media_uuid']));
    } elseif ($videoSource === 'youtube') {
        $youtubeEmbed = barama_youtube_embed_url((string) ($background['youtube_url'] ?? ''));
        if ($youtubeEmbed !== null) {
            $youtubeEmbed .= '&autoplay=1&mute=1&controls=0&playsinline=1&loop=1';
        }
    } else {
        $videoSrc = trim((string) ($background['video_url'] ?? ''));
    }
}

$hasHeroBackground = $backgroundType === 'color'
    || ($backgroundType === 'image' && $imageSrc !== '')
    || ($backgroundType === 'slider' && $sliderSources !== [])
    || ($backgroundType === 'video' && ($videoSrc !== '' || $youtubeEmbed !== null));
?>
<section class="market-home market-home--background-<?= e($backgroundType) ?> <?= $hasHeroBackground ? 'market-home--rich-background' : '' ?>">
    <?php if ($backgroundType === 'color'): ?>
        <div class="market-home__background market-home__background--color hero-bg-preset--<?= e($colorPreset) ?>" aria-hidden="true"></div>
    <?php elseif ($backgroundType === 'image' && $imageSrc !== ''): ?>
        <div class="market-home__background market-home__background--media" aria-hidden="true">
            <img src="<?= e($imageSrc) ?>" alt="" decoding="async">
        </div>
    <?php elseif ($backgroundType === 'slider' && $sliderSources !== []): ?>
        <div
            class="market-home__background market-home__background--slider hero-slider hero-slider--<?= e((string) ($background['slider_style'] ?? 'fade')) ?>"
            data-hero-slider
            data-hero-slider-interval="<?= e((string) ((int) ($background['slider_interval'] ?? 6000))) ?>"
            data-hero-slider-autoplay="<?= !empty($background['slider_autoplay']) ? '1' : '0' ?>"
            aria-hidden="true"
        >
            <?php foreach ($sliderSources as $index => $source): ?>
                <img class="hero-slider__slide <?= $index === 0 ? 'is-active' : '' ?>" src="<?= e($source) ?>" alt="" decoding="async" <?= $index === 0 ? '' : 'loading="lazy"' ?>>
            <?php endforeach; ?>
        </div>
    <?php elseif ($backgroundType === 'video' && ($videoSrc !== '' || $youtubeEmbed !== null)): ?>
        <div class="market-home__background market-home__background--video" aria-hidden="true">
            <?php if ($youtubeEmbed !== null): ?>
                <iframe src="<?= e($youtubeEmbed) ?>" title="" tabindex="-1" allow="autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            <?php else: ?>
                <video autoplay muted loop playsinline preload="metadata">
                    <source src="<?= e($videoSrc) ?>">
                </video>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($hasHeroBackground && $backgroundType !== 'color'): ?>
        <div class="market-home__background-overlay" style="--hero-overlay: <?= e(number_format($overlayStrength / 100, 2, '.', '')) ?>" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container app-container market-home__content">
        <?php if ($heroEnabled || !empty($search['enabled']) || !empty($marketPulse['enabled'])): ?>
            <div class="market-home__hero">
                <div class="market-home__copy">
                    <?php if ($heroEnabled): ?>
                        <span class="eyebrow"><?= e($heroEyebrow) ?></span>
                        <h1><?= e($heroTitle) ?></h1>
                        <p><?= e($heroDescription) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($search['enabled'])): ?>
                        <form class="market-searchbar" action="<?= e(url('/properties')) ?>" method="get" data-marketplace-filter>
                            <label class="market-searchbar__query">
                                <span><?= app_icon('search') ?></span>
                                <input name="q" placeholder="<?= e((string) ($search['placeholder'] ?? 'Search property, company, or location')) ?>" aria-label="<?= e((string) ($search['placeholder'] ?? 'Search property, company, or location')) ?>">
                            </label>
                            <label>
                                <span class="visually-hidden">Offer</span>
                                <select name="offer" aria-label="Offer">
                                    <option value="">Sale & rent</option>
                                    <option value="sale">For sale</option>
                                    <option value="rent">For rent</option>
                                </select>
                            </label>
                            <label>
                                <span class="visually-hidden">Location</span>
                                <select name="location" aria-label="Location">
                                    <option value="">All locations</option>
                                    <?php foreach (array_slice($locations, 0, 100) as $location): ?>
                                        <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">
                                <span><?= e((string) ($search['button_label'] ?? 'Search')) ?></span>
                                <?= app_icon('search') ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="market-home__actions">
                        <a href="<?= e(url('/projects')) ?>"><?= app_icon('layers') ?><span>Explore developments</span></a>
                        <a href="<?= e(url('/company')) ?>"><?= app_icon('building') ?><span>Browse companies</span></a>
                    </div>
                </div>

                <?php if (!empty($marketPulse['enabled'])): ?>
                    <aside class="market-pulse" aria-label="Marketplace overview">
                        <div class="market-pulse__glow" aria-hidden="true"></div>
                        <div class="market-pulse__head">
                            <span><?= app_icon('home') ?></span>
                            <div><strong><?= e((string) ($marketPulse['title'] ?? 'Marketplace')) ?></strong><small><?= e((string) ($marketPulse['subtitle'] ?? 'Approved public inventory')) ?></small></div>
                        </div>
                        <div class="market-pulse__stats">
                            <a href="<?= e(url('/properties?offer=sale')) ?>"><strong><?= e(number_format((int) ($marketStats['sale'] ?? 0))) ?></strong><span>For sale</span></a>
                            <a href="<?= e(url('/properties?offer=rent')) ?>"><strong><?= e(number_format((int) ($marketStats['rent'] ?? 0))) ?></strong><span>For rent</span></a>
                            <a href="<?= e(url('/projects')) ?>"><strong><?= e(number_format((int) ($marketStats['projects'] ?? 0))) ?></strong><span>Projects</span></a>
                            <a href="<?= e(url('/company')) ?>"><strong><?= e(number_format((int) ($marketStats['companies'] ?? 0))) ?></strong><span>Companies</span></a>
                        </div>
                        <?php if (trim((string) ($marketPulse['note'] ?? '')) !== ''): ?>
                            <div class="market-pulse__note"><?= app_icon('shield') ?><span><?= e((string) $marketPulse['note']) ?></span></div>
                        <?php endif; ?>
                    </aside>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($quickActions['enabled'])): ?>
            <div class="market-intent-grid" aria-label="Explore marketplace">
                <?php
                $intentRows = [
                    'sale' => ['href' => '/properties?offer=sale', 'class' => 'sale', 'icon' => 'home', 'title' => 'Buy property', 'description' => 'Browse published homes and investment inventory.'],
                    'rent' => ['href' => '/properties?offer=rent', 'class' => 'rent', 'icon' => 'key', 'title' => 'Rent property', 'description' => 'Find rental listings by location and budget.'],
                    'project' => ['href' => '/projects', 'class' => 'project', 'icon' => 'layers', 'title' => 'New developments', 'description' => 'Explore published projects and available units.'],
                    'company' => ['href' => '/company', 'class' => 'company', 'icon' => 'building', 'title' => 'Companies', 'description' => 'Discover approved developers and agencies.'],
                ];
                ?>
                <?php foreach ($intentRows as $key => $intent): ?>
                    <?php $item = is_array($quickItems[$key] ?? null) ? $quickItems[$key] : []; ?>
                    <a class="market-intent market-intent--<?= e((string) $intent['class']) ?>" href="<?= e(url((string) $intent['href'])) ?>">
                        <span class="market-intent__icon"><?= app_icon((string) $intent['icon']) ?></span>
                        <span><strong><?= e((string) ($item['title'] ?? $intent['title'])) ?></strong><small><?= e((string) ($item['description'] ?? $intent['description'])) ?></small></span>
                        <span class="market-intent__arrow">↗</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($homeOrder as $homeSectionKey): ?>
    <?php
    $homeSection = is_array($homeSections[$homeSectionKey] ?? null)
        ? $homeSections[$homeSectionKey]
        : [];
    $homeEnabled = !array_key_exists('enabled', $homeSection)
        || !empty($homeSection['enabled']);
    ?>

    <?php if ($homeSectionKey === 'listings' && $homeEnabled && $latestListings !== []): ?>
        <section class="marketplace-section marketplace-section--home">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Latest inventory')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'Fresh on the marketplace')) ?></h2>
                    </div>
                    <a href="<?= e(url('/properties')) ?>"><?= e((string) ($homeSection['link_label'] ?? 'View all properties')) ?></a>
                </div>
                <div class="inventory-grid">
                    <?php foreach ($latestListings as $listing): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'projects' && $homeEnabled && $latestProjects !== []): ?>
        <section class="marketplace-section marketplace-section--home marketplace-section--soft">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Developments')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'Published projects')) ?></h2>
                    </div>
                    <a href="<?= e(url('/projects')) ?>"><?= e((string) ($homeSection['link_label'] ?? 'View projects')) ?></a>
                </div>
                <div class="inventory-grid inventory-grid--projects">
                    <?php foreach ($latestProjects as $project): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => $project]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'companies' && $homeEnabled && $featuredCompanies !== []): ?>
        <section class="marketplace-section marketplace-section--home">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Companies')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'Approved professionals')) ?></h2>
                    </div>
                    <a href="<?= e(url('/company')) ?>"><?= e((string) ($homeSection['link_label'] ?? 'View directory')) ?></a>
                </div>
                <div class="inventory-grid inventory-grid--companies">
                    <?php foreach ($featuredCompanies as $company): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'company', 'item' => $company]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
