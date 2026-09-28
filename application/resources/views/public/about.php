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
<main class="builtin-public-page builtin-about-page">
    <?php if (!empty($hero['enabled'])): ?>
        <section class="builtin-hero">
            <div class="container app-container">
                <div class="builtin-hero__grid">
                    <div class="builtin-hero__copy">
                        <span class="eyebrow"><?= e((string) ($hero['eyebrow'] ?? 'About Barama')) ?></span>
                        <h1><?= e((string) ($hero['title'] ?? 'About Us')) ?></h1>
                        <p><?= e((string) ($hero['description'] ?? '')) ?></p>
                        <div class="builtin-hero__actions">
                            <a class="btn btn-primary" href="<?= e(url('/properties')) ?>"><?= app_icon('search') ?><span>Browse properties</span></a>
                            <a class="btn btn-outline-secondary" href="<?= e(url('/contact')) ?>"><?= app_icon('mail') ?><span>Contact us</span></a>
                        </div>
                    </div>
                    <?php if (trim((string) ($hero['image_url'] ?? '')) !== ''): ?>
                        <div class="builtin-hero__media">
                            <img src="<?= e((string) $hero['image_url']) ?>" alt="<?= e((string) ($hero['title'] ?? 'About Barama')) ?>" loading="eager">
                        </div>
                    <?php else: ?>
                        <div class="builtin-hero__visual" aria-hidden="true">
                            <span><?= app_icon('building') ?></span>
                            <span><?= app_icon('home') ?></span>
                            <span><?= app_icon('layers') ?></span>
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
                            <div class="builtin-placeholder-mark"><?= app_icon('building') ?><strong>Barama Real Estate</strong><span>Structured property discovery</span></div>
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
            <section class="builtin-section">
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
            <section class="builtin-section">
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
