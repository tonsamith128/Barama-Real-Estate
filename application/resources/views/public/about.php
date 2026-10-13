<?php

declare(strict_types=1);

$page = is_array($aboutPage ?? null)
    ? $aboutPage
    : \Barama\Repositories\SiteContentRepository::defaults()['about'];
$hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
$sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
$order = is_array($page['section_order'] ?? null)
    ? array_values($page['section_order'])
    : ['story', 'mission_vision', 'values', 'stats', 'team', 'cta'];

$linkHref = static function (string $value): string {
    return str_starts_with($value, '/') ? url($value) : $value;
};
?>
<main class="builtin-public-page builtin-about-page barama-about-a24 barama-about-a25">
    <link rel="stylesheet" href="<?= e(asset('css/about-premium-v117a24.css')) ?>?v=117a24" media="all">
    <link rel="stylesheet" href="<?= e(asset('css/about-visual-v117a25.css')) ?>?v=117a25" media="all">
    <?php if (!empty($hero['enabled'])): ?>
        <section class="builtin-hero">
            <div class="container app-container">
                <div class="builtin-hero__grid">
                    <div class="builtin-hero__copy">
                        <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? 'About Barama')) ?></span>
                        <h1><?= e((string) ($hero['title'] ?? 'About Us')) ?></h1>
                        <p><?= e((string) ($hero['description'] ?? '')) ?></p>
                        <div class="builtin-hero__actions barama-about-a24__actions">
                            <a class="btn btn-primary" href="<?= e(url('/properties')) ?>"><?= app_icon('search') ?><span>Browse properties</span></a>
                            <a class="btn btn-outline-secondary" href="<?= e(url('/contact')) ?>"><?= app_icon('mail') ?><span>Contact us</span></a>
                        </div>
                    </div>
                    <?php if (trim((string) ($hero['image_url'] ?? '')) !== ''): ?>
                        <div class="builtin-hero__media">
                            <img src="<?= e((string) $hero['image_url']) ?>" alt="<?= e((string) ($hero['title'] ?? 'About Barama')) ?>" loading="eager">
                        </div>
                    <?php else: ?>
                        <div class="barama-about-a25__hero-art" aria-hidden="true">
                            <div class="barama-about-a25__hero-orbit"></div>
                            <div class="barama-about-a25__hero-building">
                                <svg viewBox="0 0 310 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M35 270V100L151 42L266 100V270" fill="#E6EEFF" stroke="#759AF3" stroke-width="3" stroke-linejoin="round"/>
                                    <path d="M72 270V119L151 79L230 119V270" fill="#F8FAFF" stroke="#98B4F7" stroke-width="2"/>
                                    <path d="M151 79V270" stroke="#B5C8F6" stroke-width="2"/>
                                    <path d="M92 138H127V175H92ZM174 138H209V175H174ZM92 191H127V228H92ZM174 191H209V228H174Z" fill="#D5E3FF" stroke="#94B2F4" stroke-width="2"/>
                                    <path d="M136 270V227H166V270" fill="#94B5FF" stroke="#5F8DEC" stroke-width="2"/>
                                    <path d="M15 271H291" stroke="#739AF0" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="barama-about-a25__hero-types">
                                <div class="barama-about-a25__hero-type"><?= app_icon('home') ?><span>Properties</span></div>
                                <div class="barama-about-a25__hero-type"><?= app_icon('layers') ?><span>Projects</span></div>
                                <div class="barama-about-a25__hero-type"><?= app_icon('building') ?><span>Companies</span></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($order as $sectionKey): ?>
        <?php $section = is_array($sections[$sectionKey] ?? null) ? $sections[$sectionKey] : []; ?>
        <?php if (empty($section['enabled'])) continue; ?>

        <?php if ($sectionKey === 'story'): ?>
            <section class="builtin-section">
                <div class="container app-container builtin-split">
                    <div class="builtin-section__copy">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Our story')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                        <div class="builtin-prose"><?= nl2br(e((string) ($section['body'] ?? ''))) ?></div>
                    </div>
                    <div class="builtin-split__media<?= trim((string) ($section['image_url'] ?? '')) === '' ? ' is-placeholder' : '' ?>">
                        <?php if (trim((string) ($section['image_url'] ?? '')) !== ''): ?>
                            <img src="<?= e((string) $section['image_url']) ?>" alt="<?= e((string) ($section['title'] ?? 'Our story')) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="barama-about-a25__story-art" aria-hidden="true">
                                <div class="barama-about-a25__story-skyline">
                                    <svg viewBox="0 0 510 210" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 196H500" stroke="#A0BAED" stroke-width="2"/>
                                        <path d="M56 196V91H137V196M154 196V42H258V196M278 196V106H363V196M379 196V71H460V196" fill="#E0EAFF" stroke="#7F9FEB" stroke-width="2.5" stroke-linejoin="round"/>
                                        <path d="M175 67H193M216 67H235M175 93H193M216 93H235M175 119H193M216 119H235M175 145H193M216 145H235M80 118H113M80 147H113M302 132H335M302 161H335M401 98H438M401 127H438M401 156H438" stroke="#83A5EF" stroke-width="5" stroke-linecap="round"/>
                                        <path d="M194 196V167H219V196" fill="#93B4FA" stroke="#638CF0" stroke-width="2"/>
                                        <path d="M154 42L206 12L258 42" stroke="#648CE9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="barama-about-a25__story-caption">
                                    <span class="barama-about-a25__story-caption-icon"><?= app_icon('building') ?></span>
                                    <div><strong>Barama Real Estate</strong><span>Explore properties, projects and companies in one place</span></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'mission_vision'): ?>
            <section class="builtin-section builtin-section--soft">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Purpose')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-card-grid builtin-card-grid--two">
                        <article class="builtin-info-card">
                            <span class="builtin-info-card__icon"><?= app_icon('check') ?></span>
                            <h3><?= e((string) ($section['mission_title'] ?? 'Our mission')) ?></h3>
                            <p><?= e((string) ($section['mission_text'] ?? '')) ?></p>
                        </article>
                        <article class="builtin-info-card">
                            <span class="builtin-info-card__icon"><?= app_icon('eye') ?></span>
                            <h3><?= e((string) ($section['vision_title'] ?? 'Our vision')) ?></h3>
                            <p><?= e((string) ($section['vision_text'] ?? '')) ?></p>
                        </article>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'values'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section barama-about-a25__values">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Our values')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-card-grid builtin-card-grid--four">
                        <?php foreach ($items as $index => $item): ?>
                            <?php if (trim((string) ($item['title'] ?? '')) === '' && trim((string) ($item['description'] ?? '')) === '') continue; ?>
                            <article class="builtin-info-card">
                                <span class="builtin-info-card__number"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
                                <p><?= e((string) ($item['description'] ?? '')) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'stats'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section builtin-section--soft">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Marketplace at a glance')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-stat-grid">
                        <?php foreach ($items as $item): ?>
                            <?php if (trim((string) ($item['value'] ?? '')) === '' && trim((string) ($item['label'] ?? '')) === '') continue; ?>
                            <div class="builtin-stat"><strong><?= e((string) ($item['value'] ?? '')) ?></strong><span><?= e((string) ($item['label'] ?? '')) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'team'): ?>
            <?php $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <section class="builtin-section">
                <div class="container app-container">
                    <div class="builtin-section__head">
                        <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Our team')) ?></span>
                        <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                    </div>
                    <div class="builtin-team-grid">
                        <?php foreach ($items as $item): ?>
                            <?php if (trim((string) ($item['name'] ?? '')) === '') continue; ?>
                            <article class="builtin-team-card">
                                <div class="builtin-team-card__media">
                                    <?php if (trim((string) ($item['image_url'] ?? '')) !== ''): ?>
                                        <img src="<?= e((string) $item['image_url']) ?>" alt="<?= e((string) $item['name']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <?= app_icon('user') ?>
                                    <?php endif; ?>
                                </div>
                                <div><h3><?= e((string) $item['name']) ?></h3><strong><?= e((string) ($item['role'] ?? '')) ?></strong><p><?= e((string) ($item['bio'] ?? '')) ?></p></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($sectionKey === 'cta'): ?>
            <section class="builtin-section barama-about-a25__cta">
                <div class="container app-container">
                    <div class="builtin-cta">
                        <div>
                            <span class="eyebrow"><?= e((string) ($section['eyebrow'] ?? 'Start exploring')) ?></span>
                            <h2><?= e((string) ($section['title'] ?? '')) ?></h2>
                            <p><?= e((string) ($section['description'] ?? '')) ?></p>
                        </div>
                        <div class="builtin-cta__actions">
                            <?php if (trim((string) ($section['primary_label'] ?? '')) !== '' && trim((string) ($section['primary_url'] ?? '')) !== ''): ?>
                                <a class="btn btn-primary" href="<?= e($linkHref((string) $section['primary_url'])) ?>"><?= e((string) $section['primary_label']) ?></a>
                            <?php endif; ?>
                            <?php if (trim((string) ($section['secondary_label'] ?? '')) !== '' && trim((string) ($section['secondary_url'] ?? '')) !== ''): ?>
                                <a class="btn btn-outline-secondary" href="<?= e($linkHref((string) $section['secondary_url'])) ?>"><?= e((string) $section['secondary_label']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</main>
