<?php

declare(strict_types=1);

$footer = is_array($footerSettings ?? null) ? $footerSettings : \Barama\Repositories\FooterContentRepository::defaults();
$social = is_array($footer['social'] ?? null) ? $footer['social'] : [];
$columnLabels = [
    'brand' => ['number' => '01', 'name' => 'Brand', 'heading_field' => 'brand_heading'],
    'marketplace' => ['number' => '02', 'name' => 'Marketplace', 'heading_field' => 'marketplace_heading'],
    'compliance' => ['number' => '03', 'name' => 'Compliance', 'heading_field' => 'compliance_heading'],
    'help' => ['number' => '04', 'name' => 'Help', 'heading_field' => 'help_heading'],
];
$footerLinks = is_array($footer['links'] ?? null) ? $footer['links'] : [];
$networks = [
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'youtube' => 'YouTube',
    'tiktok' => 'TikTok',
    'x' => 'X / Twitter',
    'linkedin' => 'LinkedIn',
];
?>
<section class="workspace-page barama-footer-editor" aria-labelledby="footer-editor-title">
    <header class="workspace-page__header barama-footer-editor__header">
        <div>
            <span class="eyebrow">Content &amp; Discovery</span>
            <h1 id="footer-editor-title">Footer</h1>
            <p>Customize footer information and social links without changing its existing four-column layout. Styling remains in Appearance → Footer.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener noreferrer"><?= app_icon('eye') ?> View public footer</a>
    </header>
    <?php if (!empty($footerSaved)): ?>
        <p class="barama-footer-editor__notice" role="status">Footer content saved. Open the public website to review the latest changes.</p>
    <?php endif; ?>
    <?php if (($footerError ?? '') !== ''): ?>
        <p class="barama-footer-editor__notice barama-footer-editor__notice--error" role="alert"><?= e((string) $footerError) ?></p>
    <?php endif; ?>

    <form class="barama-footer-editor__form" action="<?= e(url('/dashboard/footer')) ?>" method="post" data-workspace-form>
        <?= csrf_field() ?>
        <section class="barama-footer-editor__section" aria-labelledby="footer-layout-heading">
            <div class="barama-footer-editor__section-head">
                <div>
                    <h2 id="footer-layout-heading">Footer layout</h2>
                    <p>Four fixed columns. Marketplace navigation and Help links remain connected to their existing sources.</p>
                </div>
                <span class="barama-footer-editor__count">4 columns</span>
            </div>
            <input type="hidden" name="footer_links_submitted" value="1">
            <div class="barama-footer-editor__columns" data-footer-columns>
                <?php foreach ($columnLabels as $column => $details): ?>
                    <fieldset class="barama-footer-editor__column" data-footer-column="<?= e($column) ?>">
                        <legend><span><?= e($details['number']) ?></span> <?= e($details['name']) ?></legend>
                        <label for="footer-heading-<?= e($column) ?>">Column heading</label>
                        <input id="footer-heading-<?= e($column) ?>"
                               name="<?= e($details['heading_field']) ?>"
                               maxlength="60" required
                               value="<?= e((string) ($footer[$details['heading_field']] ?? $details['name'])) ?>">
                        <?php if ($column === 'brand'): ?>
                            <label for="footer-brand-description">Footer description</label>
                            <textarea id="footer-brand-description" name="brand_description" rows="3" maxlength="400" required><?= e((string) $footer['brand_description']) ?></textarea>
                            <p class="barama-footer-editor__hint">The brand logo and first-column layout remain unchanged.</p>
                        <?php endif; ?>
                        <div class="barama-footer-editor__list-head">
                            <strong>Menu items</strong>
                            <button type="button" class="barama-footer-editor__add" data-footer-add="<?= e($column) ?>" aria-label="Add <?= e($details['name']) ?> menu item">+ Add item</button>
                        </div>
                        <div class="barama-footer-editor__link-list" data-footer-list="<?= e($column) ?>">
                            <?php foreach ((array) ($footerLinks[$column] ?? []) as $index => $link): ?>
                                <div class="barama-footer-editor__link-row" data-footer-item>
                                    <div class="barama-footer-editor__link-fields">
                                        <label>Label
                                            <input name="footer_links[<?= e($column) ?>][<?= e((string) $index) ?>][label]"
                                                type="text" maxlength="80" required
                                                value="<?= e((string) ($link['label'] ?? '')) ?>"
                                                data-footer-label placeholder="Menu label">
                                        </label>
                                        <label>URL
                                            <input name="footer_links[<?= e($column) ?>][<?= e((string) $index) ?>][url]"
                                                type="text" maxlength="500" required
                                                value="<?= e((string) ($link['url'] ?? '')) ?>"
                                                data-footer-url placeholder="/page or https://example.com">
                                        </label>
                                    </div>
                                    <div class="barama-footer-editor__item-actions" role="group" aria-label="Reorder or remove menu item">
                                        <button type="button" data-footer-up aria-label="Move item up">↑ Up</button>
                                        <button type="button" data-footer-down aria-label="Move item down">↓ Down</button>
                                        <button type="button" data-footer-remove aria-label="Remove menu item">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="barama-footer-editor__empty" data-footer-empty <?= !empty($footerLinks[$column]) ? 'hidden' : '' ?>>No menu items. Use + Add item to create one.</p>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <p class="barama-footer-editor__hint">Use ↑ Up / ↓ Down to reorder links in each column; Remove to delete an item. Changes appear on the public footer after Save. Maximum 15 links per column. Internal paths beginning with / and external HTTPS URLs are supported.</p>
        </section>

        <section class="barama-footer-editor__section" aria-labelledby="footer-bottom-heading">
            <div class="barama-footer-editor__section-head">
                <div>
                    <h2 id="footer-bottom-heading">Footer bottom</h2>
                    <p>Left: copyright information. Right: social icons and the existing theme toggle.</p>
                </div>
            </div>
            <div class="barama-footer-editor__bottom-grid">
                <div class="barama-footer-editor__bottom-panel">
                    <h3>Copyright · Left</h3>
                    <label for="footer-copyright">Copyright information</label>
                    <input id="footer-copyright" name="copyright" maxlength="180" value="<?= e((string) $footer['copyright']) ?>" required>
                    <p class="barama-footer-editor__hint">Use <code>{year}</code> to show the current year automatically.</p>
                </div>
                <div class="barama-footer-editor__bottom-panel">
                    <h3>Social icons · Right</h3>
                    <label for="footer-social-heading">Accessible group label</label>
                    <input id="footer-social-heading" name="social_heading" maxlength="60" value="<?= e((string) $footer['social_heading']) ?>" required>
                    <div class="barama-footer-editor__social-fields">
                        <?php foreach ($networks as $network => $label): ?>
                            <label for="social-<?= e($network) ?>"><?= e($label) ?></label>
                            <input id="social-<?= e($network) ?>" name="social_<?= e($network) ?>" type="url" inputmode="url" maxlength="500" placeholder="https://" value="<?= e((string) ($social[$network] ?? '')) ?>">
                        <?php endforeach; ?>
                    </div>
                    <p class="barama-footer-editor__hint">Only HTTPS links are accepted. Leave a field blank to hide its icon.</p>
                </div>
            </div>
        </section>

        <div class="barama-footer-editor__actions">
            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Footer</button>
            <span>Only footer content is updated. Homepage, navigation, appearance, and existing records remain unchanged.</span>
        </div>
    </form>
</section>

<script src="<?= e(asset('js/admin-footer-links-v100.js') . '?v=100') ?>" defer></script>
