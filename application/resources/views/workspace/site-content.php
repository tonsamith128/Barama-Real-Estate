<?php

declare(strict_types=1);

$config = is_array($siteContent ?? null)
    ? $siteContent
    : \Barama\Repositories\SiteContentRepository::defaults();

$homepage = is_array($config['homepage'] ?? null) ? $config['homepage'] : [];
$sections = is_array($homepage['sections'] ?? null) ? $homepage['sections'] : [];
$order = is_array($homepage['section_order'] ?? null)
    ? array_values($homepage['section_order'])
    : ['listings', 'projects', 'companies'];
$navigation = is_array($config['navigation'] ?? null)
    ? array_values($config['navigation'])
    : [];

while (count($navigation) < 6) {
    $navigation[] = [
        'label' => '',
        'path' => '',
        'active_area' => '',
        'enabled' => false,
    ];
}

$positions = [];
foreach ($order as $index => $sectionKey) {
    $positions[(string) $sectionKey] = $index + 1;
}
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Homepage & Navigation</h1>
            <p>Manage public homepage copy, section visibility/order, and same-site primary navigation without changing marketplace ownership or routing.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('globe') ?>
            Public site configuration
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <form action="<?= e(url('/dashboard/homepage')) ?>" method="post" data-workspace-form>
        <?= csrf_field() ?>

        <section class="workspace-record-panel">
            <div class="workspace-record-panel__head">
                <h2>Homepage hero</h2>
                <span>The marketplace search form and approval safeguards remain fixed; only public copy is configurable here.</span>
            </div>

            <div class="workspace-admin-moderation__form">
                <label>
                    <span>Eyebrow</span>
                    <input class="form-control" name="hero_eyebrow" maxlength="80" value="<?= e((string) ($homepage['hero_eyebrow'] ?? '')) ?>">
                </label>

                <label>
                    <span>Headline</span>
                    <input class="form-control" name="hero_title" maxlength="140" required value="<?= e((string) ($homepage['hero_title'] ?? '')) ?>">
                </label>

                <label class="workspace-admin-moderation__note">
                    <span>Description</span>
                    <textarea class="form-control" name="hero_description" rows="4" maxlength="500" required><?= e((string) ($homepage['hero_description'] ?? '')) ?></textarea>
                </label>
            </div>
        </section>

        <section class="workspace-record-panel">
            <div class="workspace-record-panel__head">
                <h2>Homepage sections</h2>
                <span>Control the three existing marketplace sections without creating unsupported custom block types.</span>
            </div>

            <?php foreach (['listings' => 'Latest listings', 'projects' => 'Projects', 'companies' => 'Companies'] as $key => $label): ?>
                <?php $section = is_array($sections[$key] ?? null) ? $sections[$key] : []; ?>
                <div class="workspace-admin-moderation__form">
                    <label>
                        <span><?= e($label) ?> order</span>
                        <select class="form-select" name="section_order[<?= e($key) ?>]">
                            <?php for ($position = 1; $position <= 3; $position++): ?>
                                <option value="<?= $position ?>" <?= (int) ($positions[$key] ?? 99) === $position ? 'selected' : '' ?>><?= $position ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>

                    <label>
                        <span>Eyebrow</span>
                        <input class="form-control" name="sections[<?= e($key) ?>][eyebrow]" maxlength="80" value="<?= e((string) ($section['eyebrow'] ?? '')) ?>">
                    </label>

                    <label>
                        <span>Title</span>
                        <input class="form-control" name="sections[<?= e($key) ?>][title]" maxlength="140" value="<?= e((string) ($section['title'] ?? '')) ?>">
                    </label>

                    <label>
                        <span>Link label</span>
                        <input class="form-control" name="sections[<?= e($key) ?>][link_label]" maxlength="80" value="<?= e((string) ($section['link_label'] ?? '')) ?>">
                    </label>

                    <label>
                        <input type="checkbox" name="sections[<?= e($key) ?>][enabled]" value="1" <?= !empty($section['enabled']) ? 'checked' : '' ?>>
                        <span>Show this section on the homepage</span>
                    </label>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="workspace-record-panel">
            <div class="workspace-record-panel__head">
                <h2>Primary navigation</h2>
                <span>Up to six links. Only internal Barama paths beginning with <code>/</code> are accepted.</span>
            </div>

            <?php foreach (array_slice($navigation, 0, 6) as $index => $item): ?>
                <div class="workspace-admin-moderation__form">
                    <label>
                        <span>Item <?= $index + 1 ?> label</span>
                        <input class="form-control" name="nav_label[]" maxlength="60" value="<?= e((string) ($item['label'] ?? '')) ?>">
                    </label>

                    <label>
                        <span>Internal path</span>
                        <input class="form-control" name="nav_path[]" maxlength="255" placeholder="/properties" value="<?= e((string) ($item['path'] ?? '')) ?>">
                    </label>

                    <label>
                        <input type="checkbox" name="nav_enabled[<?= $index ?>]" value="1" <?= !empty($item['enabled']) ? 'checked' : '' ?>>
                        <span>Enabled</span>
                    </label>
                </div>
            <?php endforeach; ?>
        </section>

        <button class="btn btn-primary" type="submit">
            <?= app_icon('check') ?>
            Save homepage & navigation
        </button>
    </form>
</section>
