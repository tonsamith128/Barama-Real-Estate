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
    : array_values(\Barama\Repositories\SiteContentRepository::defaults()['homepage']['section_order']);

/* BARAMA_HERO_COPY_V1122 */
$heroEyebrow = trim((string) ($hero['eyebrow'] ?? $homepageContent['hero_eyebrow'] ?? 'Approved Real Estate'));
$heroTitle = trim((string) ($hero['title'] ?? $homepageContent['hero_title'] ?? 'Property Discovery'));
$heroDescription = trim((string) (
    $hero['description']
    ?? $homepageContent['hero_description']
    ?? 'Find approved homes, rental opportunities, and new developments.'
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
?>
<link rel="stylesheet" href="<?= e(asset('css/homepage-agent-v1112.css') . '?v=1112') ?>">
<link rel="stylesheet" href="<?= e(asset('css/homepage-search-icon-v117a29.css') . '?v=117a29') ?>">
<!-- BARAMA_HOMEPAGE_AGENT_V114 -->
<!-- BARAMA_HOMEPAGE_AGENT_V116 -->
<!-- BARAMA_HOMEPAGE_AGENT_V119 -->
<!-- BARAMA_HOMEPAGE_AGENT_V1110 -->
<!-- BARAMA_HOMEPAGE_AGENT_V1111 -->
<!-- BARAMA_HOMEPAGE_AGENT_V1112 -->
<section class="market-home market-home--background-<?= e($backgroundType) ?> <?= $hasHeroBackground ? 'market-home--rich-background' : '' ?>">
    <?php if ($heroEnabled && $backgroundType === 'color'): ?>
        <div class="market-home__background market-home__background--color hero-bg-preset--<?= e($colorPreset) ?>" aria-hidden="true"></div>
    <?php elseif ($heroEnabled && $backgroundType === 'image' && $imageSrc !== ''): ?>
        <div class="market-home__background market-home__background--media" aria-hidden="true">
            <img src="<?= e($imageSrc) ?>" alt="" decoding="async">
        </div>
    <?php elseif ($heroEnabled && $backgroundType === 'slider' && $sliderSources !== []): ?>
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
    <?php elseif ($heroEnabled && $backgroundType === 'video' && ($videoSrc !== '' || $youtubeEmbed !== null)): ?>
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
        <div class="market-home__background-overlay" data-barama-hero-overlay="<?= e(number_format($overlayStrength / 100, 2, '.', '')) ?>" aria-hidden="true"></div>
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
                        <form class="market-searchbar barama-search-v115" action="<?= e(url('/properties')) ?>" method="get" data-marketplace-filter>
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
                            <?php /* BARAMA_PUBLIC_LOCATION_PICKER_HOME_V112 */ ?>

                            <?= \Barama\Core\View::partial('components/public-location-picker', [

                                'name' => 'location',

                                'value' => '',

                                'label' => 'All locations',

                            ]) ?>
                            <button type="submit">
                                <span><?= e((string) ($search['button_label'] ?? 'Search')) ?></span>
                                <?= app_icon('search') ?>
                            </button>
                            <?php /* BARAMA_HOMEPAGE_SEARCH_V115 */ ?>
                            <button type="button" class="barama-home-advanced" data-public-location-advanced-trigger aria-label="Advanced location search">
                                <?= app_icon('settings') ?><span>Advanced</span>
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
                <?php if (!empty($marketPulse['enabled'])): ?>
                    <aside class="market-home__agent market-home__agent--real market-home__agent--fullbody" aria-label="Professional Asian property agent">
                        <img src="<?= e(asset('images/marketplace-asian-agent-fullbody-v116.svg') . '?v=116') ?>" alt="Full-body professional Asian real estate businesswoman holding a tablet">
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
    <?php elseif ($homeSectionKey === 'featured_locations' && $homeEnabled): ?>
        <?php
        /* BARAMA_HOME_LOCATION_MARQUEE_V1124 */
        $locationCards = array_slice(
            is_array($popularLocationCards ?? null) ? array_values($popularLocationCards) : [],
            0,
            20
        );
        ?>
        <?php if (count($locationCards) === 20): ?>
            <section class="marketplace-section marketplace-section--home homepage-location-marquee">
                <div class="container app-container">
                    <div class="section-heading-row section-heading-row--compact">
                        <div>
                            <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Explore locations')) ?></span>
                            <h2><?= e((string) ($homeSection['title'] ?? 'Popular places to discover')) ?></h2>
                        </div>
                        <a href="<?= e(url('/properties')) ?>"><?= e((string) ($homeSection['link_label'] ?? 'Browse all properties')) ?></a>
                    </div>
                    <?= \Barama\Core\View::partial('components/popular-location-marquee', ['items' => $locationCards]) ?>
                </div>
            </section>
        <?php endif; ?>
    <?php elseif ($homeSectionKey === 'how_it_works' && $homeEnabled): ?>
        <?php $items = is_array($homeSection['items'] ?? null) ? array_values($homeSection['items']) : []; ?>
        <section class="marketplace-section marketplace-section--home marketplace-section--soft homepage-how">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'How it works')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'A simpler way to discover real estate')) ?></h2>
                    </div>
                </div>
                <div class="homepage-how-grid">
                    <?php foreach (array_slice($items, 0, 3) as $index => $item): ?>
                        <?php if (trim((string) ($item['title'] ?? '')) !== '' || trim((string) ($item['description'] ?? '')) !== ''): ?>
                            <article class="homepage-how-card">
                                <span><?= e((string) ($index + 1)) ?></span>
                                <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
                                <p><?= e((string) ($item['description'] ?? '')) ?></p>
                            </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'trust_stats' && $homeEnabled): ?>
        <?php $items = is_array($homeSection['items'] ?? null) ? array_values($homeSection['items']) : []; ?>
        <section class="marketplace-section marketplace-section--home homepage-trust">
            <div class="container app-container">
                <div class="homepage-trust__head">
                    <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Marketplace confidence')) ?></span>
                    <h2><?= e((string) ($homeSection['title'] ?? 'Built around approved public inventory')) ?></h2>
                </div>
                <div class="homepage-trust-grid">
                    <?php foreach (array_slice($items, 0, 4) as $item): ?>
                        <?php if (trim((string) ($item['value'] ?? '')) !== '' || trim((string) ($item['label'] ?? '')) !== ''): ?>
                            <div><strong><?= e((string) ($item['value'] ?? '')) ?></strong><span><?= e((string) ($item['label'] ?? '')) ?></span></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'testimonials' && $homeEnabled): ?>
        <?php $items = is_array($homeSection['items'] ?? null) ? array_values($homeSection['items']) : []; ?>
        <section class="marketplace-section marketplace-section--home marketplace-section--soft homepage-testimonials">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Testimonials')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'What marketplace users say')) ?></h2>
                    </div>
                </div>
                <div class="homepage-testimonial-grid">
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                        <?php if (trim((string) ($item['quote'] ?? '')) !== ''): ?>
                            <blockquote class="homepage-testimonial">
                                <span aria-hidden="true">“</span>
                                <p><?= e((string) ($item['quote'] ?? '')) ?></p>
                                <footer><strong><?= e((string) ($item['name'] ?? '')) ?></strong><small><?= e((string) ($item['role'] ?? '')) ?></small></footer>
                            </blockquote>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'blog' && $homeEnabled): ?>
        <?php
        $items = is_array($homeSection['items'] ?? null) ? array_values($homeSection['items']) : [];
        $sectionUrl = trim((string) ($homeSection['link_url'] ?? ''));
        $sectionHref = $sectionUrl !== ''
            ? (str_starts_with($sectionUrl, '/') ? url($sectionUrl) : $sectionUrl)
            : '';
        ?>
        <section class="marketplace-section marketplace-section--home homepage-blog">
            <div class="container app-container">
                <div class="section-heading-row section-heading-row--compact">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Insights')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'Latest from Barama')) ?></h2>
                    </div>
                    <?php if ($sectionHref !== '' && trim((string) ($homeSection['link_label'] ?? '')) !== ''): ?>
                        <a href="<?= e($sectionHref) ?>"><?= e((string) $homeSection['link_label']) ?></a>
                    <?php endif; ?>
                </div>
                <div class="homepage-blog-grid">
                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                        <?php
                        $articleUrl = trim((string) ($item['url'] ?? ''));
                        $articleHref = $articleUrl !== ''
                            ? (str_starts_with($articleUrl, '/') ? url($articleUrl) : $articleUrl)
                            : '';
                        ?>
                        <?php if (trim((string) ($item['title'] ?? '')) !== ''): ?>
                            <article class="homepage-blog-card">
                                <?php if (trim((string) ($item['image_url'] ?? '')) !== ''): ?>
                                    <img src="<?= e((string) $item['image_url']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <div class="homepage-blog-card__placeholder"><?= app_icon('edit') ?></div>
                                <?php endif; ?>
                                <div>
                                    <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
                                    <p><?= e((string) ($item['excerpt'] ?? '')) ?></p>
                                    <?php if ($articleHref !== ''): ?><a href="<?= e($articleHref) ?>">Read more <span>↗</span></a><?php endif; ?>
                                </div>
                            </article>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php elseif ($homeSectionKey === 'subscribe' && $homeEnabled): ?>
        <section class="marketplace-section marketplace-section--home homepage-subscribe" id="subscribe">
            <div class="container app-container">
                <div class="homepage-subscribe__panel">
                    <div>
                        <span class="eyebrow"><?= e((string) ($homeSection['eyebrow'] ?? 'Stay informed')) ?></span>
                        <h2><?= e((string) ($homeSection['title'] ?? 'Get marketplace updates')) ?></h2>
                        <p><?= e((string) ($homeSection['description'] ?? '')) ?></p>
                    </div>
                    <div>
                        <?php
                        $subscribeSuccess = (string) ($_GET['subscribed'] ?? '') === '1';
                        $subscribeError = trim((string) ($_GET['subscribe_error'] ?? ''));
                        ?>
                        <div class="homepage-subscribe__notice <?= $subscribeError !== '' ? 'is-error' : ($subscribeSuccess ? 'is-success' : '') ?>" data-home-subscribe-notice<?= (!$subscribeSuccess && $subscribeError === '') ? ' hidden' : '' ?> aria-live="polite"><?= $subscribeError !== '' ? e($subscribeError) : ($subscribeSuccess ? e((string) ($homeSection['success_message'] ?? 'Thank you. You are subscribed to Barama updates.')) : '') ?></div>
                        <form action="<?= e(url('/subscribe')) ?>" method="post" data-home-subscribe-form data-success-message="<?= e((string) ($homeSection['success_message'] ?? 'Thank you. You are subscribed to Barama updates.')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="source" value="homepage">
                            <input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                            <label>
                                <span class="visually-hidden">Email address</span>
                                <input type="email" name="email" maxlength="191" required autocomplete="email" placeholder="<?= e((string) ($homeSection['placeholder'] ?? 'Enter your email address')) ?>">
                            </label>
                            <button class="btn btn-primary" type="submit"><?= app_icon('mail') ?><span><?= e((string) ($homeSection['button_label'] ?? 'Subscribe')) ?></span></button>
                        </form>
                        <?php if (trim((string) ($homeSection['consent_text'] ?? '')) !== ''): ?><small><?= e((string) $homeSection['consent_text']) ?></small><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
