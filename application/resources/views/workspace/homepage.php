<?php

declare(strict_types=1);

$homepage = is_array($homepage ?? null)
    ? $homepage
    : \Barama\Repositories\SiteContentRepository::defaults()['homepage'];
$hero = is_array($homepage['hero'] ?? null) ? $homepage['hero'] : [];
$background = is_array($hero['background'] ?? null) ? $hero['background'] : [];
$features = is_array($homepage['features'] ?? null) ? $homepage['features'] : [];
$sections = is_array($homepage['sections'] ?? null) ? $homepage['sections'] : [];
$order = is_array($homepage['section_order'] ?? null)
    ? array_values($homepage['section_order'])
    : ['listings', 'projects', 'companies'];
$positions = [];

foreach ($order as $index => $sectionKey) {
    $positions[(string) $sectionKey] = $index + 1;
}

$rows = [
    'hero' => [
        'label' => 'Hero',
        'description' => 'Headline, copy and full-width Plain, Color, Image, Slider or Video background.',
        'icon' => 'home',
        'enabled' => !empty($hero['enabled']),
        'summary' => ucfirst((string) ($background['type'] ?? 'plain')) . ' background',
    ],
    'search' => [
        'label' => 'Hero Search',
        'description' => 'Homepage property/company/location search bar and button copy.',
        'icon' => 'search',
        'enabled' => !empty($features['search']['enabled']),
        'summary' => (string) ($features['search']['placeholder'] ?? 'Search'),
    ],
    'market_pulse' => [
        'label' => 'Marketplace Summary',
        'description' => 'Sale, rent, project and company totals shown beside the hero.',
        'icon' => 'grid',
        'enabled' => !empty($features['market_pulse']['enabled']),
        'summary' => (string) ($features['market_pulse']['title'] ?? 'Marketplace'),
    ],
    'quick_actions' => [
        'label' => 'Quick Discovery',
        'description' => 'Buy, Rent, New Developments and Companies shortcut cards.',
        'icon' => 'layers',
        'enabled' => !empty($features['quick_actions']['enabled']),
        'summary' => '4 discovery shortcuts',
    ],
    'listings' => [
        'label' => 'Latest Inventory',
        'description' => 'Fresh property cards and the homepage properties call-to-action.',
        'icon' => 'tag',
        'enabled' => !empty($sections['listings']['enabled']),
        'summary' => (string) ($sections['listings']['title'] ?? 'Fresh on the marketplace'),
    ],
    'projects' => [
        'label' => 'Projects',
        'description' => 'Published development cards and homepage project call-to-action.',
        'icon' => 'layers',
        'enabled' => !empty($sections['projects']['enabled']),
        'summary' => (string) ($sections['projects']['title'] ?? 'Published projects'),
    ],
    'companies' => [
        'label' => 'Companies',
        'description' => 'Approved company cards and homepage directory call-to-action.',
        'icon' => 'building',
        'enabled' => !empty($sections['companies']['enabled']),
        'summary' => (string) ($sections['companies']['title'] ?? 'Approved professionals'),
    ],
];

$quickItems = is_array($features['quick_actions']['items'] ?? null)
    ? $features['quick_actions']['items']
    : [];
$sliderImages = is_array($background['slider_images'] ?? null)
    ? array_values($background['slider_images'])
    : [];
?>
<section class="workspace-page content-config-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Homepage</h1>
            <p>Control public homepage features independently. Use the switch for visibility and Configure for full customization.</p>
        </div>
        <a class="workspace-context-note" href="<?= e(url('/')) ?>" target="_blank" rel="noopener noreferrer">
            <?= app_icon('globe') ?>
            View homepage
        </a>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="content-config-table-wrap">
        <table class="content-config-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Current configuration</th>
                    <th>Status</th>
                    <th class="content-config-table__action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $key => $row): ?>
                    <tr>
                        <td>
                            <div class="content-config-feature">
                                <span class="content-config-feature__icon"><?= app_icon((string) $row['icon']) ?></span>
                                <span>
                                    <strong><?= e((string) $row['label']) ?></strong>
                                    <small><?= e((string) $row['description']) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><span class="content-config-summary"><?= e((string) $row['summary']) ?></span></td>
                        <td>
                            <form action="<?= e(url('/dashboard/homepage/' . $key . '/toggle')) ?>" method="post" data-workspace-form data-toggle-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="enabled" value="<?= !empty($row['enabled']) ? '1' : '0' ?>" data-toggle-value>
                                <label class="premium-switch">
                                    <input type="checkbox" <?= !empty($row['enabled']) ? 'checked' : '' ?> data-auto-toggle aria-label="Toggle <?= e((string) $row['label']) ?>">
                                    <span class="premium-switch__track"><span></span></span>
                                    <b><?= !empty($row['enabled']) ? 'ON' : 'OFF' ?></b>
                                </label>
                            </form>
                        </td>
                        <td class="content-config-table__action">
                            <button class="content-config-button" type="button" data-configure-open="homepage-config-<?= e($key) ?>">
                                <?= app_icon('settings') ?>
                                <span>Configure</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <dialog class="config-dialog config-dialog--wide" id="homepage-config-hero" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/homepage/hero')) ?>" method="post" enctype="multipart/form-data" data-workspace-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head">
                <div><span class="eyebrow">Homepage Hero</span><h2>Configure hero</h2><p>Choose the full hero background type, source and presentation while keeping the marketplace content readable.</p></div>
                <button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button>
            </header>

            <div class="config-dialog__body">
                <div class="config-field-grid">
                    <label><span>Eyebrow</span><input class="form-control" name="hero_eyebrow" maxlength="80" value="<?= e((string) ($hero['eyebrow'] ?? '')) ?>"></label>
                    <label><span>Headline</span><input class="form-control" name="hero_title" maxlength="140" required value="<?= e((string) ($hero['title'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="hero_description" rows="3" maxlength="500" required><?= e((string) ($hero['description'] ?? '')) ?></textarea></label>
                    <label>
                        <span>Background type</span>
                        <select class="form-select" name="background_type" data-hero-background-type>
                            <?php foreach (['plain' => 'Plain Hero', 'color' => 'Color Background', 'image' => 'Image Background', 'slider' => 'Image Slider Background', 'video' => 'Video Background'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($background['type'] ?? 'plain') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Dark overlay</span><input class="form-control" type="number" name="overlay_strength" min="0" max="85" value="<?= e((string) ($background['overlay_strength'] ?? 44)) ?>"><small>0–85%. Applied to image, slider and video backgrounds.</small></label>
                </div>

                <section class="config-source-panel" data-hero-background-panel="plain">
                    <h3>Plain Hero</h3>
                    <p>Uses the current clean hero surface with no extra media background.</p>
                </section>

                <section class="config-source-panel" data-hero-background-panel="color">
                    <h3>Color Background</h3>
                    <label><span>Color preset</span><select class="form-select" name="color_preset">
                        <?php foreach (['ocean' => 'Ocean Blue', 'aurora' => 'Aurora', 'sunset' => 'Sunset', 'violet' => 'Violet', 'emerald' => 'Emerald'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($background['color_preset'] ?? 'ocean') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <div class="hero-preset-strip" aria-label="Color presets">
                        <?php foreach (['ocean','aurora','sunset','violet','emerald'] as $preset): ?><span class="hero-preset hero-preset--<?= e($preset) ?>"></span><?php endforeach; ?>
                    </div>
                </section>

                <section class="config-source-panel" data-hero-background-panel="image">
                    <h3>Image Background</h3>
                    <div class="config-field-grid">
                        <label><span>Source</span><select class="form-select" name="image_source"><option value="url" <?= ($background['image_source'] ?? 'url') === 'url' ? 'selected' : '' ?>>Image URL</option><option value="media" <?= ($background['image_source'] ?? '') === 'media' ? 'selected' : '' ?>>Upload / Media Library</option></select></label>
                        <label><span>Image URL</span><input class="form-control" type="url" name="image_url" maxlength="1000" value="<?= e((string) ($background['image_url'] ?? '')) ?>" placeholder="https://example.com/hero.jpg"></label>
                        <label class="config-field-grid__wide"><span>Upload image</span><input class="form-control" type="file" name="hero_image_file" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG or WebP. The uploaded file is saved to the Barama Media Library.</small></label>
                        <input type="hidden" name="image_media_uuid" value="<?= e((string) ($background['image_media_uuid'] ?? '')) ?>">
                        <?php if (!empty($background['image_media_uuid'])): ?><div class="config-media-current config-field-grid__wide"><?= app_icon('image') ?><span>Current uploaded image: <?= e((string) $background['image_media_uuid']) ?></span></div><?php endif; ?>
                    </div>
                </section>

                <section class="config-source-panel" data-hero-background-panel="slider">
                    <h3>Image Slider Background</h3>
                    <div class="config-field-grid">
                        <label><span>Slider style</span><select class="form-select" name="slider_style">
                            <?php foreach (['fade' => 'Fade', 'slide' => 'Slide', 'kenburns' => 'Ken Burns'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($background['slider_style'] ?? 'fade') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select></label>
                        <label><span>Interval (ms)</span><input class="form-control" type="number" min="2500" max="20000" step="500" name="slider_interval" value="<?= e((string) ($background['slider_interval'] ?? 6000)) ?>"></label>
                        <label class="config-field-grid__wide premium-inline-toggle"><input type="checkbox" name="slider_autoplay" value="1" <?= !empty($background['slider_autoplay']) ? 'checked' : '' ?>><span>Autoplay slider</span></label>
                        <label class="config-field-grid__wide"><span>Upload slider images</span><input class="form-control" type="file" name="hero_slider_files[]" multiple accept="image/jpeg,image/png,image/webp"><small>Upload multiple images at once. Maximum 12 active slider images.</small></label>
                        <label class="config-field-grid__wide"><span>Add image URLs</span><textarea class="form-control" name="slider_urls" rows="3" placeholder="One HTTPS image URL per line"></textarea></label>
                    </div>
                    <?php if ($sliderImages !== []): ?>
                        <div class="config-existing-list">
                            <strong>Current slider images</strong>
                            <?php foreach ($sliderImages as $index => $item): ?>
                                <?php $encoded = (string) ($item['type'] ?? '') . ':' . (string) ($item['value'] ?? ''); ?>
                                <label>
                                    <input type="hidden" name="slider_existing[]" value="<?= e($encoded) ?>">
                                    <input type="checkbox" name="slider_remove[]" value="<?= e((string) $index) ?>">
                                    <span><?= e(ucfirst((string) ($item['type'] ?? 'image'))) ?> · <?= e((string) ($item['value'] ?? '')) ?></span>
                                    <small>Remove</small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="config-source-panel" data-hero-background-panel="video">
                    <h3>Video Background</h3>
                    <div class="config-field-grid">
                        <label><span>Source</span><select class="form-select" name="video_source"><option value="url" <?= ($background['video_source'] ?? 'url') === 'url' ? 'selected' : '' ?>>Video URL</option><option value="media" <?= ($background['video_source'] ?? '') === 'media' ? 'selected' : '' ?>>Upload video</option><option value="youtube" <?= ($background['video_source'] ?? '') === 'youtube' ? 'selected' : '' ?>>YouTube</option></select></label>
                        <label><span>Video URL</span><input class="form-control" type="url" name="video_url" maxlength="1000" value="<?= e((string) ($background['video_url'] ?? '')) ?>" placeholder="https://example.com/hero.mp4"></label>
                        <label class="config-field-grid__wide"><span>Upload video</span><input class="form-control" type="file" name="hero_video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime"><small>Up to 50 MB. Uploaded videos are stored in the Barama Media Library.</small></label>
                        <label class="config-field-grid__wide"><span>YouTube URL</span><input class="form-control" type="url" name="youtube_url" maxlength="1000" value="<?= e((string) ($background['youtube_url'] ?? '')) ?>" placeholder="https://www.youtube.com/watch?v=..."></label>
                        <input type="hidden" name="video_media_uuid" value="<?= e((string) ($background['video_media_uuid'] ?? '')) ?>">
                        <?php if (!empty($background['video_media_uuid'])): ?><div class="config-media-current config-field-grid__wide"><?= app_icon('video') ?><span>Current uploaded video: <?= e((string) $background['video_media_uuid']) ?></span></div><?php endif; ?>
                    </div>
                </section>
            </div>

            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save hero configuration</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="homepage-config-search" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/homepage/search')) ?>" method="post" data-workspace-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Homepage</span><h2>Configure Hero Search</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid"><label class="config-field-grid__wide"><span>Search placeholder</span><input class="form-control" name="placeholder" maxlength="120" value="<?= e((string) ($features['search']['placeholder'] ?? '')) ?>"></label><label><span>Button label</span><input class="form-control" name="button_label" maxlength="40" value="<?= e((string) ($features['search']['button_label'] ?? 'Search')) ?>"></label></div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save configuration</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog" id="homepage-config-market_pulse" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/homepage/market_pulse')) ?>" method="post" data-workspace-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Homepage</span><h2>Configure Marketplace Summary</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid"><label><span>Title</span><input class="form-control" name="title" maxlength="80" value="<?= e((string) ($features['market_pulse']['title'] ?? '')) ?>"></label><label><span>Subtitle</span><input class="form-control" name="subtitle" maxlength="120" value="<?= e((string) ($features['market_pulse']['subtitle'] ?? '')) ?>"></label><label class="config-field-grid__wide"><span>Approval note</span><textarea class="form-control" name="note" rows="3" maxlength="180"><?= e((string) ($features['market_pulse']['note'] ?? '')) ?></textarea></label></div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save configuration</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog config-dialog--wide" id="homepage-config-quick_actions" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/homepage/quick_actions')) ?>" method="post" data-workspace-form>
            <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Homepage</span><h2>Configure Quick Discovery</h2><p>Customize the four fixed marketplace destinations without changing their safe routes.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body"><div class="quick-action-config-grid"><?php foreach (['sale' => 'Buy property','rent' => 'Rent property','project' => 'New developments','company' => 'Companies'] as $key => $label): ?><?php $item = is_array($quickItems[$key] ?? null) ? $quickItems[$key] : []; ?><div><strong><?= e($label) ?></strong><label><span>Title</span><input class="form-control" name="items[<?= e($key) ?>][title]" maxlength="80" value="<?= e((string) ($item['title'] ?? '')) ?>"></label><label><span>Description</span><textarea class="form-control" name="items[<?= e($key) ?>][description]" rows="3" maxlength="180><?= e((string) ($item['description'] ?? '')) ?></textarea></label></div><?php endforeach; ?></div></div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save configuration</button></footer>
        </form>
    </dialog>

    <?php foreach (['listings' => 'Latest Inventory','projects' => 'Projects','companies' => 'Companies'] as $key => $label): ?>
        <?php $section = is_array($sections[$key] ?? null) ? $sections[$key] : []; ?>
        <dialog class="config-dialog" id="homepage-config-<?= e($key) ?>" data-config-dialog>
            <form class="config-dialog__panel" action="<?= e(url('/dashboard/homepage/' . $key)) ?>" method="post" data-workspace-form>
                <?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Homepage Section</span><h2>Configure <?= e($label) ?></h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                <div class="config-dialog__body config-field-grid">
                    <label><span>Display order</span><select class="form-select" name="position"><?php for ($position = 1; $position <= 3; $position++): ?><option value="<?= $position ?>" <?= (int) ($positions[$key] ?? 99) === $position ? 'selected' : '' ?>><?= $position ?></option><?php endfor; ?></select></label>
                    <label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($section['eyebrow'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="140" value="<?= e((string) ($section['title'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Link label</span><input class="form-control" name="link_label" maxlength="80" value="<?= e((string) ($section['link_label'] ?? '')) ?>"></label>
                </div>
                <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save configuration</button></footer>
            </form>
        </dialog>
    <?php endforeach; ?>
</section>
