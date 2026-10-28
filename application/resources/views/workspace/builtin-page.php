<?php

declare(strict_types=1);

$pageKey = ($builtInPageType ?? '') === 'contact' ? 'contact' : 'about';
$defaults = \Barama\Repositories\SiteContentRepository::defaults()[$pageKey];
$pageConfig = is_array($builtInPage ?? null) ? $builtInPage : $defaults;
$hero = is_array($pageConfig['hero'] ?? null) ? $pageConfig['hero'] : [];
$seo = is_array($pageConfig['seo'] ?? null) ? $pageConfig['seo'] : [];
$sections = is_array($pageConfig['sections'] ?? null) ? $pageConfig['sections'] : [];
$order = is_array($pageConfig['section_order'] ?? null)
    ? array_values($pageConfig['section_order'])
    : array_values($defaults['section_order']);
$positions = [];

foreach ($order as $index => $key) {
    $positions[(string) $key] = $index + 1;
}

$isContact = $pageKey === 'contact';
$pageTitleLabel = $isContact ? 'Contact' : 'About';
$publicPath = $isContact ? '/contact' : '/about';
$pageDescription = $isContact
    ? 'Build the dedicated Contact Us experience, contact channels, business information, message form, FAQ, social links, and support inbox.'
    : 'Build the dedicated About Us experience with structured story, mission, values, statistics, team, and calls-to-action.';
$definitions = $isContact
    ? [
        'hero' => ['label' => 'Hero', 'description' => 'Top-level Contact Us headline, introduction, and optional image.', 'icon' => 'mail'],
        'channels' => ['label' => 'Contact Channels', 'description' => 'Phone, email, Telegram and WhatsApp contact options.', 'icon' => 'phone'],
        'office' => ['label' => 'Office & Location', 'description' => 'Address, location note, and external map directions.', 'icon' => 'map'],
        'hours' => ['label' => 'Business Hours', 'description' => 'Seven-day availability schedule with timezone.', 'icon' => 'clock'],
        'form' => ['label' => 'Contact Form', 'description' => 'Secure, rate-limited contact form with inbox storage and optional email routing.', 'icon' => 'message'],
        'faq' => ['label' => 'FAQ', 'description' => 'Five expandable questions to help visitors before contacting you.', 'icon' => 'check'],
        'socials' => ['label' => 'Social Links', 'description' => 'Official social and messaging profiles.', 'icon' => 'globe'],
    ]
    : [
        'hero' => ['label' => 'Hero', 'description' => 'Top-level About Us headline, introduction, and optional image.', 'icon' => 'home'],
        'story' => ['label' => 'Our Story', 'description' => 'Long-form company/platform introduction with optional supporting image.', 'icon' => 'building'],
        'mission_vision' => ['label' => 'Mission & Vision', 'description' => 'Two focused purpose cards describing mission and long-term vision.', 'icon' => 'eye'],
        'values' => ['label' => 'Values', 'description' => 'Up to twelve reorderable cards describing the principles behind the platform.', 'icon' => 'shield'],
        'stats' => ['label' => 'Stats', 'description' => 'Up to eight reorderable marketplace or company metrics.', 'icon' => 'grid'],
        'team' => ['label' => 'Team', 'description' => 'Up to eight team profiles with role, biography, and image URL.', 'icon' => 'users'],
        'cta' => ['label' => 'Call to Action', 'description' => 'Closing message with primary and secondary actions.', 'icon' => 'check'],
    ];

$enabledSections = 0;
foreach ($definitions as $key => $definition) {
    $enabled = $key === 'hero'
        ? !empty($hero['enabled'])
        : !empty($sections[$key]['enabled']);
    if ($enabled) {
        $enabledSections++;
    }
}

$messages = is_array($contactMessages ?? null) ? $contactMessages : [];
$messageSummary = is_array($contactMessageSummary ?? null) ? $contactMessageSummary : [];
?>
<?php if (!$isContact): ?>
<link rel="stylesheet" href="<?= e(asset('css/about-editor.css')) ?>?v=1.0.0-clean2">
<?php endif; ?>
<section class="workspace-page content-config-page builtin-admin-page<?= $isContact ? '' : ' barama-about-admin-a24' ?>">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1><?= e($pageTitleLabel) ?></h1>
            <p><?= e($pageDescription) ?></p>
        </div>
        <div class="builtin-admin-page__header-actions">
            <button class="content-config-button" type="button" data-configure-open="<?= e($pageKey) ?>-seo">
                <?= app_icon('search') ?><span>SEO settings</span>
            </button>
            <a class="workspace-context-note" href="<?= e(url($publicPath)) ?>" target="_blank" rel="noopener noreferrer">
                <?= app_icon('globe') ?> View page
            </a>
        </div>
    </div>

    <div data-workspace-notice hidden></div>
    <?php if (!$isContact): ?>
    <div class="barama-about-admin-a24__notice" role="note">
        <?= app_icon('check') ?><span><strong>Live About editor</strong> — content saves through the existing protected dashboard endpoints. Changes appear on the public About page after saving.</span>
    </div>
    <?php endif; ?>


    <div class="builtin-admin-summary">
        <article><span><?= app_icon('globe') ?></span><div><small>Public page</small><strong><?= !empty($pageConfig['enabled']) ? 'Enabled' : 'Disabled' ?></strong></div></article>
        <article><span><?= app_icon('layers') ?></span><div><small>Visible sections</small><strong><?= e((string) $enabledSections) ?> / <?= e((string) count($definitions)) ?></strong></div></article>
        <article><span><?= app_icon('search') ?></span><div><small>Indexing</small><strong><?= e((string) ($seo['robots'] ?? 'index,follow')) ?></strong></div></article>
        <?php if ($isContact): ?>
            <article><span><?= app_icon('message') ?></span><div><small>New messages</small><strong><?= e(number_format((int) ($messageSummary['new'] ?? 0))) ?></strong></div></article>
        <?php endif; ?>
    </div>

    <section class="builtin-admin-status">
        <div><span class="builtin-admin-status__icon"><?= app_icon($isContact ? 'mail' : 'users') ?></span><div><strong><?= e($pageTitleLabel) ?> page visibility</strong><small>This controls the dedicated public <?= e($publicPath) ?> route. It is independent from normal CMS pages.</small></div></div>
        <form action="<?= e(url('/dashboard/built-in-pages/' . $pageKey . '/settings')) ?>" method="post" data-workspace-form data-toggle-form>
            <?= csrf_field() ?>
            <input type="hidden" name="enabled" value="<?= !empty($pageConfig['enabled']) ? '1' : '0' ?>" data-toggle-value>
            <label class="premium-switch">
                <input type="checkbox" <?= !empty($pageConfig['enabled']) ? 'checked' : '' ?> data-auto-toggle aria-label="Toggle <?= e($pageTitleLabel) ?> page">
                <span class="premium-switch__track"><span></span></span>
                <b><?= !empty($pageConfig['enabled']) ? 'ON' : 'OFF' ?></b>
            </label>
        </form>
    </section>

    <div class="content-config-table-wrap">
        <table class="content-config-table">
            <thead><tr><th>Built-in section</th><th>Current configuration</th><th>Position</th><th>Status</th><th class="content-config-table__action">Action</th></tr></thead>
            <tbody>
            <?php foreach ($definitions as $key => $definition): ?>
                <?php
                $section = $key === 'hero'
                    ? $hero
                    : (is_array($sections[$key] ?? null) ? $sections[$key] : []);
                $enabled = !empty($section['enabled']);
                $summary = trim((string) ($section['title'] ?? ''));
                if ($summary === '') {
                    $summary = (string) $definition['label'];
                }
                ?>
                <tr>
                    <td><div class="content-config-feature"><span class="content-config-feature__icon"><?= app_icon((string) $definition['icon']) ?></span><span><strong><?= e((string) $definition['label']) ?></strong><small><?= e((string) $definition['description']) ?></small></span></div></td>
                    <td><span class="content-config-summary"><?= e($summary) ?></span></td>
                    <td><span class="content-config-position"><?= $key === 'hero' ? 'Fixed' : e((string) ($positions[$key] ?? '—')) ?></span></td>
                    <td>
                        <form action="<?= e(url('/dashboard/built-in-pages/' . $pageKey . '/' . $key . '/toggle')) ?>" method="post" data-workspace-form data-toggle-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="enabled" value="<?= $enabled ? '1' : '0' ?>" data-toggle-value>
                            <label class="premium-switch"><input type="checkbox" <?= $enabled ? 'checked' : '' ?> data-auto-toggle aria-label="Toggle <?= e((string) $definition['label']) ?>"><span class="premium-switch__track"><span></span></span><b><?= $enabled ? 'ON' : 'OFF' ?></b></label>
                        </form>
                    </td>
                    <td class="content-config-table__action"><button class="content-config-button" type="button" data-configure-open="<?= e($pageKey . '-' . $key) ?>"><?= app_icon('settings') ?><span>Configure</span></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <dialog class="config-dialog" id="<?= e($pageKey) ?>-seo" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/' . $pageKey . '/seo')) ?>" method="post" data-workspace-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow">Built-in Page SEO</span><h2><?= e($pageTitleLabel) ?> SEO</h2><p>Configure the dedicated public route without creating a normal CMS page.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label class="config-field-grid__wide"><span>SEO title</span><input class="form-control" name="title" maxlength="191" value="<?= e((string) ($seo['title'] ?? '')) ?>"></label>
                <label class="config-field-grid__wide"><span>SEO description</span><textarea class="form-control" name="description" rows="4" maxlength="300"><?= e((string) ($seo['description'] ?? '')) ?></textarea></label>
                <label><span>Robots directive</span><select class="form-select" name="robots"><?php foreach (['index,follow','noindex,follow','noindex,nofollow'] as $option): ?><option value="<?= e($option) ?>" <?= ($seo['robots'] ?? 'index,follow') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                <label><span>Canonical URL</span><input class="form-control" value="<?= e(url($publicPath)) ?>" readonly></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save SEO</button></footer>
        </form>
    </dialog>

    <dialog class="config-dialog config-dialog--wide" id="<?= e($pageKey) ?>-hero" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/' . $pageKey . '/hero')) ?>" method="post" enctype="multipart/form-data" data-workspace-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head"><div><span class="eyebrow"><?= e($pageTitleLabel) ?> Hero</span><h2>Configure hero</h2><p>All Hero text is editable here. On Contact, upload a replacement photo or use the bundled default.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($hero['eyebrow'] ?? '')) ?>"></label>
                <label class="config-field-grid__wide"><span>Headline</span><input class="form-control" name="title" maxlength="180" required value="<?= e((string) ($hero['title'] ?? '')) ?>"></label>
                <label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="description" rows="4" maxlength="700" required><?= e((string) ($hero['description'] ?? '')) ?></textarea></label>
                <?php if ($isContact): ?>
                    <div class="config-field-grid__wide config-contact-hero-tools">
                        <label><span>Contact hero background image · Upload replacement</span><input class="form-control" type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"></label>
                        <small>JPG, PNG, or WebP · up to 5 MB · at least 600 × 400 pixels. Uploading a new file replaces the selected hero image when you save. No text is embedded in the default photograph.</small>
                        <img src="<?= e(($hero['image_url'] ?? '') !== '' ? (str_starts_with((string) $hero['image_url'], '/') ? url((string) $hero['image_url']) : (string) $hero['image_url']) : asset('images/contact/agent-asian-default-v117a17.webp')) ?>" alt="Current Contact hero image" class="config-contact-hero-preview">
                    </div>
                    <label class="config-field-grid__wide"><span>Image URL (optional alternative)</span><input class="form-control" type="text" name="image_url" maxlength="1000" value="<?= e((string) ($hero['image_url'] ?? '')) ?>" placeholder="Leave blank for default Asian business agent, or enter a HTTPS URL"><small>To restore the bundled default, clear this field and save without uploading.</small></label>
                <?php else: ?>
                    <label class="config-field-grid__wide"><span>Image URL</span><input class="form-control" type="url" name="image_url" maxlength="1000" value="<?= e((string) ($hero['image_url'] ?? '')) ?>" placeholder="https://example.com/about-hero.jpg"></label>
                <?php endif; ?>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save hero</button></footer>
        </form>
    </dialog>

    <?php if (!$isContact): ?>
        <?php $story = is_array($sections['story'] ?? null) ? $sections['story'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="about-story" data-config-dialog>
            <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/about/story')) ?>" method="post" data-workspace-form><?= csrf_field() ?>
                <header class="config-dialog__head"><div><span class="eyebrow">About Section</span><h2>Our Story</h2><p>Use this as the main long-form introduction to Barama or the organization behind it.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                <div class="config-dialog__body config-field-grid">
                    <label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['story'] ?? 1)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label>
                    <label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($story['eyebrow'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string) ($story['title'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Story</span><textarea class="form-control" name="body" rows="10" maxlength="6000"><?= e((string) ($story['body'] ?? '')) ?></textarea></label>
                    <label class="config-field-grid__wide"><span>Image URL</span><input class="form-control" type="url" name="image_url" maxlength="1000" value="<?= e((string) ($story['image_url'] ?? '')) ?>"></label>
                </div>
                <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save story</button></footer>
            </form>
        </dialog>

        <?php $mv = is_array($sections['mission_vision'] ?? null) ? $sections['mission_vision'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="about-mission_vision" data-config-dialog>
            <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/about/mission_vision')) ?>" method="post" data-workspace-form><?= csrf_field() ?>
                <header class="config-dialog__head"><div><span class="eyebrow">About Section</span><h2>Mission & Vision</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                <div class="config-dialog__body config-field-grid">
                    <label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['mission_vision'] ?? 2)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label>
                    <label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($mv['eyebrow'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Section title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string) ($mv['title'] ?? '')) ?>"></label>
                    <label><span>Mission title</span><input class="form-control" name="mission_title" maxlength="100" value="<?= e((string) ($mv['mission_title'] ?? '')) ?>"></label>
                    <label><span>Vision title</span><input class="form-control" name="vision_title" maxlength="100" value="<?= e((string) ($mv['vision_title'] ?? '')) ?>"></label>
                    <label><span>Mission text</span><textarea class="form-control" name="mission_text" rows="5" maxlength="1200"><?= e((string) ($mv['mission_text'] ?? '')) ?></textarea></label>
                    <label><span>Vision text</span><textarea class="form-control" name="vision_text" rows="5" maxlength="1200"><?= e((string) ($mv['vision_text'] ?? '')) ?></textarea></label>
                </div>
                <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save mission & vision</button></footer>
            </form>
        </dialog>

        <?php foreach (['values' => ['Values','Value',12], 'stats' => ['Stats','Stat',8]] as $key => $meta): ?>
            <?php $section = is_array($sections[$key] ?? null) ? $sections[$key] : []; $items = is_array($section['items'] ?? null) ? array_values($section['items']) : []; ?>
            <dialog class="config-dialog config-dialog--wide" id="about-<?= e($key) ?>" data-config-dialog>
                <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/about/' . $key)) ?>" method="post" data-workspace-form><?= csrf_field() ?>
                    <header class="config-dialog__head"><div><span class="eyebrow">About Section</span><h2><?= e($meta[0]) ?></h2><p>Add, remove, or reorder items. Save to publish the new arrangement.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                    <div class="config-dialog__body">
                        <div class="config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions[$key] ?? 99)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($section['eyebrow'] ?? '')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string) ($section['title'] ?? '')) ?>"></label></div>
                        <input type="hidden" name="items_present" value="1">
                        <div class="barama-about-admin-a24__items" data-a24-items data-max="<?= (int) $meta[2] ?>" data-kind="<?= e($key) ?>">
                            <?php foreach ($items as $i => $item): ?>
                                <?php if (!is_array($item) || implode('', array_map('strval', $item)) === '') continue; ?>
                                <section class="barama-about-admin-a24__item" data-a24-item><div class="barama-about-admin-a24__item-head"><strong data-a24-item-title><?= e($meta[1]) ?> <?= (int) ($i+1) ?></strong><div><button class="btn btn-outline-secondary" type="button" data-a24-move="up" aria-label="Move up">↑</button><button class="btn btn-outline-secondary" type="button" data-a24-move="down" aria-label="Move down">↓</button><button class="btn btn-outline-secondary" type="button" data-a24-remove>Remove</button></div></div>
                                    <?php if($key==='stats'): ?><label><span>Value</span><input class="form-control" name="items[<?= $i ?>][value]" maxlength="30" value="<?= e((string) ($item['value'] ?? '')) ?>"></label><label><span>Label</span><input class="form-control" name="items[<?= $i ?>][label]" maxlength="100" value="<?= e((string) ($item['label'] ?? '')) ?>"></label>
                                    <?php else: ?><label><span>Title</span><input class="form-control" name="items[<?= $i ?>][title]" maxlength="110" value="<?= e((string) ($item['title'] ?? '')) ?>"></label><label><span>Description</span><textarea class="form-control" name="items[<?= $i ?>][description]" rows="3" maxlength="360"><?= e((string) ($item['description'] ?? '')) ?></textarea></label><?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-outline-secondary barama-about-admin-a24__add" type="button" data-a24-add data-kind="<?= e($key) ?>">+ Add <?= e(strtolower($meta[1])) ?></button>
                    </div>
                    <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save <?= e(strtolower($meta[0])) ?></button></footer>
                </form>
            </dialog>
        <?php endforeach; ?>

        <?php $team = is_array($sections['team'] ?? null) ? $sections['team'] : []; $teamItems=is_array($team['items']??null)?array_values($team['items']):[]; ?>
        <dialog class="config-dialog config-dialog--wide" id="about-team" data-config-dialog>
            <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/about/team')) ?>" method="post" data-workspace-form><?= csrf_field() ?>
                <header class="config-dialog__head"><div><span class="eyebrow">About Section</span><h2>Team</h2><p>Add, remove or reorder team members. Empty lists remain empty after saving.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                <div class="config-dialog__body">
                    <div class="config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['team'] ?? 99)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($team['eyebrow'] ?? '')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string) ($team['title'] ?? '')) ?>"></label></div>
                    <input type="hidden" name="items_present" value="1">
                    <div class="barama-about-admin-a24__items" data-a24-items data-max="8" data-kind="team">
                        <?php foreach ($teamItems as $i => $item): ?><?php if (!is_array($item) || trim((string)($item['name']??''))==='') continue; ?>
                        <section class="barama-about-admin-a24__item" data-a24-item><div class="barama-about-admin-a24__item-head"><strong data-a24-item-title>Member <?= (int)($i+1) ?></strong><div><button class="btn btn-outline-secondary" type="button" data-a24-move="up" aria-label="Move up">↑</button><button class="btn btn-outline-secondary" type="button" data-a24-move="down" aria-label="Move down">↓</button><button class="btn btn-outline-secondary" type="button" data-a24-remove>Remove</button></div></div>
                        <label><span>Name</span><input class="form-control" name="items[<?= $i ?>][name]" maxlength="120" value="<?= e((string)($item['name']??'')) ?>"></label><label><span>Role</span><input class="form-control" name="items[<?= $i ?>][role]" maxlength="120" value="<?= e((string)($item['role']??'')) ?>"></label><label><span>Bio</span><textarea class="form-control" name="items[<?= $i ?>][bio]" rows="3" maxlength="500"><?= e((string)($item['bio']??'')) ?></textarea></label><label><span>Image URL</span><input class="form-control" type="url" name="items[<?= $i ?>][image_url]" maxlength="1000" value="<?= e((string)($item['image_url']??'')) ?>"></label></section>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-outline-secondary barama-about-admin-a24__add" type="button" data-a24-add data-kind="team">+ Add team member</button>
                </div>
                <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save team</button></footer>
            </form>
        </dialog>

        <?php $cta = is_array($sections['cta'] ?? null) ? $sections['cta'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="about-cta" data-config-dialog>
            <form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/about/cta')) ?>" method="post" data-workspace-form><?= csrf_field() ?>
                <header class="config-dialog__head"><div><span class="eyebrow">About Section</span><h2>Call to Action</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header>
                <div class="config-dialog__body config-field-grid">
                    <label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['cta'] ?? 99)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string) ($cta['eyebrow'] ?? '')) ?>"></label>
                    <label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string) ($cta['title'] ?? '')) ?>"></label><label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="description" rows="4" maxlength="600"><?= e((string) ($cta['description'] ?? '')) ?></textarea></label>
                    <label><span>Primary label</span><input class="form-control" name="primary_label" maxlength="70" value="<?= e((string) ($cta['primary_label'] ?? '')) ?>"></label><label><span>Primary URL</span><input class="form-control" name="primary_url" maxlength="1000" value="<?= e((string) ($cta['primary_url'] ?? '')) ?>"></label>
                    <label><span>Secondary label</span><input class="form-control" name="secondary_label" maxlength="70" value="<?= e((string) ($cta['secondary_label'] ?? '')) ?>"></label><label><span>Secondary URL</span><input class="form-control" name="secondary_url" maxlength="1000" value="<?= e((string) ($cta['secondary_url'] ?? '')) ?>"></label>
                </div>
                <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save CTA</button></footer>
            </form>
        </dialog>
    <?php else: ?>
        <?php $channels = is_array($sections['channels'] ?? null) ? $sections['channels'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-channels" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/channels')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>Contact Channels</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['channels']??1)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($channels['eyebrow']??'')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($channels['title']??'')) ?>"></label><label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="description" rows="3" maxlength="500"><?= e((string)($channels['description']??'')) ?></textarea></label><label><span>Phone</span><input class="form-control" name="phone" maxlength="60" value="<?= e((string)($channels['phone']??'')) ?>"></label><label><span>Email</span><input class="form-control" type="email" name="email" maxlength="191" value="<?= e((string)($channels['email']??'')) ?>"></label><label><span>Telegram URL</span><input class="form-control" type="url" name="telegram_url" maxlength="1000" value="<?= e((string)($channels['telegram_url']??'')) ?>"></label><label><span>WhatsApp URL</span><input class="form-control" type="url" name="whatsapp_url" maxlength="1000" value="<?= e((string)($channels['whatsapp_url']??'')) ?>"></label></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save channels</button></footer></form></dialog>

        <?php $office = is_array($sections['office'] ?? null) ? $sections['office'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-office" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/office')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>Office & Location</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['office']??2)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($office['eyebrow']??'')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($office['title']??'')) ?>"></label><label class="config-field-grid__wide"><span>Address</span><textarea class="form-control" name="address" rows="4" maxlength="600"><?= e((string)($office['address']??'')) ?></textarea></label><label class="config-field-grid__wide"><span>Location note</span><textarea class="form-control" name="location_note" rows="3" maxlength="400"><?= e((string)($office['location_note']??'')) ?></textarea></label><label><span>Map URL</span><input class="form-control" type="url" name="map_url" maxlength="1000" value="<?= e((string)($office['map_url']??'')) ?>"></label><label><span>Map button label</span><input class="form-control" name="map_label" maxlength="70" value="<?= e((string)($office['map_label']??'Open map')) ?>"></label></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save office</button></footer></form></dialog>

        <?php $hours = is_array($sections['hours'] ?? null) ? $sections['hours'] : []; $hourItems=is_array($hours['items']??null)?array_values($hours['items']):[]; ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-hours" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/hours')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>Business Hours</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body"><div class="config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['hours']??3)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($hours['eyebrow']??'')) ?>"></label><label><span>Timezone</span><input class="form-control" name="timezone" maxlength="80" value="<?= e((string)($hours['timezone']??'Asia/Phnom_Penh')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($hours['title']??'')) ?>"></label></div><div class="builtin-hours-editor"><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i=>$day): ?><?php $item=is_array($hourItems[$i]??null)?$hourItems[$i]:[]; ?><label><span><?= e($day) ?></span><input class="form-control" name="items[<?= $i ?>][hours]" maxlength="80" value="<?= e((string)($item['hours']??'')) ?>" placeholder="08:30 – 17:30 or Closed"></label><?php endforeach; ?></div></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save hours</button></footer></form></dialog>

        <?php $form = is_array($sections['form'] ?? null) ? $sections['form'] : []; $subjectText=implode("\n", is_array($form['subjects']??null)?$form['subjects']:[]); ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-form" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/form')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>Contact Form</h2><p>Submissions are stored in the support inbox below. Destination email is optional.</p></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['form']??4)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($form['eyebrow']??'')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($form['title']??'')) ?>"></label><label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="description" rows="4" maxlength="600"><?= e((string)($form['description']??'')) ?></textarea></label><label><span>Button label</span><input class="form-control" name="button_label" maxlength="70" value="<?= e((string)($form['button_label']??'Send message')) ?>"></label><label><span>Destination email</span><input class="form-control" type="email" name="destination_email" maxlength="191" value="<?= e((string)($form['destination_email']??'')) ?>"><small>Optional. Messages are stored even when this is blank.</small></label><label class="config-field-grid__wide"><span>Success message</span><input class="form-control" name="success_message" maxlength="260" value="<?= e((string)($form['success_message']??'')) ?>"></label><label class="config-field-grid__wide"><span>Consent text</span><textarea class="form-control" name="consent_text" rows="2" maxlength="500"><?= e((string)($form['consent_text']??'')) ?></textarea></label><label class="config-field-grid__wide"><span>Subjects — one per line</span><textarea class="form-control" name="subjects" rows="6" maxlength="900"><?= e($subjectText) ?></textarea></label><label class="premium-inline-toggle config-field-grid__wide"><input type="checkbox" name="require_phone" value="1" <?= !empty($form['require_phone'])?'checked':'' ?>><span>Require phone number</span></label></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save contact form</button></footer></form></dialog>

        <?php $faq = is_array($sections['faq'] ?? null) ? $sections['faq'] : []; $faqItems=is_array($faq['items']??null)?array_values($faq['items']):[]; ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-faq" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/faq')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>FAQ</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body"><div class="config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['faq']??5)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($faq['eyebrow']??'')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($faq['title']??'')) ?>"></label></div><div class="homepage-repeat-grid"><?php for($i=0;$i<5;$i++): ?><?php $item=is_array($faqItems[$i]??null)?$faqItems[$i]:[]; ?><section><strong>Question <?= $i+1 ?></strong><label><span>Question</span><input class="form-control" name="items[<?= $i ?>][question]" maxlength="180" value="<?= e((string)($item['question']??'')) ?>"></label><label><span>Answer</span><textarea class="form-control" name="items[<?= $i ?>][answer]" rows="5" maxlength="900"><?= e((string)($item['answer']??'')) ?></textarea></label></section><?php endfor; ?></div></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save FAQ</button></footer></form></dialog>

        <?php $socials = is_array($sections['socials'] ?? null) ? $sections['socials'] : []; ?>
        <dialog class="config-dialog config-dialog--wide" id="contact-socials" data-config-dialog><form class="config-dialog__panel" action="<?= e(url('/dashboard/built-in-pages/contact/socials')) ?>" method="post" data-workspace-form><?= csrf_field() ?><header class="config-dialog__head"><div><span class="eyebrow">Contact Section</span><h2>Social Links</h2></div><button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button></header><div class="config-dialog__body config-field-grid"><label><span>Position</span><select class="form-select" name="position"><?php for($p=1;$p<=count($order);$p++): ?><option value="<?= $p ?>" <?= ($positions['socials']??6)===$p?'selected':'' ?>><?= $p ?></option><?php endfor; ?></select></label><label><span>Eyebrow</span><input class="form-control" name="eyebrow" maxlength="80" value="<?= e((string)($socials['eyebrow']??'')) ?>"></label><label class="config-field-grid__wide"><span>Title</span><input class="form-control" name="title" maxlength="160" value="<?= e((string)($socials['title']??'')) ?>"></label><label class="config-field-grid__wide"><span>Description</span><textarea class="form-control" name="description" rows="3" maxlength="400"><?= e((string)($socials['description']??'')) ?></textarea></label><?php foreach(['facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn','youtube'=>'YouTube','telegram'=>'Telegram','tiktok'=>'TikTok'] as $network=>$label): ?><label><span><?= e($label) ?> URL</span><input class="form-control" type="url" name="<?= e($network) ?>_url" maxlength="1000" value="<?= e((string)($socials[$network . '_url']??'')) ?>"></label><?php endforeach; ?></div><footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save social links</button></footer></form></dialog>

        <section class="builtin-message-inbox">
            <div class="builtin-message-inbox__head"><div><span class="eyebrow">Contact Inbox</span><h2>Recent messages</h2><p>Messages submitted through the dedicated Contact Us page. Update status without deleting the original request.</p></div><div class="builtin-message-inbox__counts"><span>New <strong><?= e(number_format((int)($messageSummary['new']??0))) ?></strong></span><span>Read <strong><?= e(number_format((int)($messageSummary['read']??0))) ?></strong></span><span>Resolved <strong><?= e(number_format((int)($messageSummary['resolved']??0))) ?></strong></span></div></div>
            <?php if($messages===[]): ?><div class="workspace-record-empty">No contact messages have been received yet.</div><?php else: ?>
                <div class="builtin-message-list">
                    <?php foreach($messages as $message): ?>
                        <?php $status=(string)($message['status']??'new'); ?>
                        <article class="builtin-message-card">
                            <div class="builtin-message-card__top"><div><strong><?= e((string)($message['contact_name']??'')) ?></strong><span><?= e((string)($message['contact_email']??'')) ?><?= trim((string)($message['contact_phone']??''))!==''?' · '.e((string)$message['contact_phone']):'' ?></span></div><span class="status-badge status-badge--<?= e($status==='resolved'?'approved':($status==='spam'?'rejected':($status==='new'?'pending':'active'))) ?>"><?= e(ucfirst($status)) ?></span></div>
                            <h3><?= e((string)($message['subject']??'')) ?></h3>
                            <details><summary>Read message</summary><p><?= nl2br(e((string)($message['message']??''))) ?></p></details>
                            <footer><small><?= e((string)($message['created_at']??'')) ?><?= trim((string)($message['handled_by_name']??''))!==''?' · handled by '.e((string)$message['handled_by_name']):'' ?></small><form action="<?= e(url('/dashboard/contact/messages/' . (int)$message['id'] . '/status')) ?>" method="post" data-workspace-form><?= csrf_field() ?><select class="form-select" name="status"><?php foreach(['new','read','resolved','spam'] as $option): ?><option value="<?= e($option) ?>" <?= $status===$option?'selected':'' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?></select><button class="content-config-button" type="submit"><?= app_icon('check') ?><span>Update</span></button></form></footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>

<?php if (!$isContact): ?>
<link rel="stylesheet" href="<?= e(asset('css/about-table-v117a28.css')) ?>?v=117a28" media="all">
<script src="<?= e(asset('js/about-editor.js')) ?>?v=1.0.0-clean2" defer></script>
<?php endif; ?>
