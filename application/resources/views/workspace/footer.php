<?php

declare(strict_types=1);

$footer = is_array($footerSettings ?? null) ? $footerSettings : \Barama\Repositories\FooterContentRepository::defaults();
$social = is_array($footer['social'] ?? null) ? $footer['social'] : [];
$networks = [
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'youtube' => 'YouTube',
    'tiktok' => 'TikTok',
    'x' => 'X / Twitter',
    'linkedin' => 'LinkedIn',
];
?>
<link rel="stylesheet" href="<?= e(asset('css/admin-footer-editor-v100.css') . '?v=100') ?>">
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

    <form class="barama-footer-editor__form" action="<?= e(url('/dashboard/footer')) ?>" method="post">
        <?= csrf_field() ?>
        <section class="barama-footer-editor__section" aria-labelledby="footer-layout-heading">
            <div class="barama-footer-editor__section-head">
                <div>
                    <h2 id="footer-layout-heading">Footer layout</h2>
                    <p>Four fixed columns. Marketplace navigation and Help links remain connected to their existing sources.</p>
                </div>
                <span class="barama-footer-editor__count">4 columns</span>
            </div>
            <div class="barama-footer-editor__columns">
                <fieldset>
                    <legend><span>01</span> Brand</legend>
                    <p class="barama-footer-editor__hint">Logo and layout are preserved; edit the description only.</p>
                    <label for="footer-brand-description">Footer info</label>
                    <textarea id="footer-brand-description" name="brand_description" rows="4" maxlength="400" required><?= e((string) $footer['brand_description']) ?></textarea>
                </fieldset>
                <fieldset>
                    <legend><span>02</span> Marketplace</legend>
                    <p class="barama-footer-editor__hint">Current public Navigation links remain intact.</p>
                    <label for="footer-marketplace-heading">Column heading</label>
                    <input id="footer-marketplace-heading" name="marketplace_heading" maxlength="60" value="<?= e((string) $footer['marketplace_heading']) ?>" required>
                    <p class="barama-footer-editor__hint">Edit column links through <a href="<?= e(url('/dashboard/navigation')) ?>">Navigation</a>.</p>
                </fieldset>
                <fieldset>
                    <legend><span>03</span> Compliance</legend>
                    <p class="barama-footer-editor__hint">The policy links use Barama’s canonical Policy Center URLs.</p>
                    <label for="footer-compliance-heading">Column heading</label>
                    <input id="footer-compliance-heading" name="compliance_heading" maxlength="60" value="<?= e((string) $footer['compliance_heading']) ?>" required>
                    <label for="footer-policy-label">Policy Center label</label>
                    <input id="footer-policy-label" name="policy_label" maxlength="60" value="<?= e((string) $footer['policy_label']) ?>" required>
                    <label for="footer-privacy-label">Privacy label</label>
                    <input id="footer-privacy-label" name="privacy_label" maxlength="60" value="<?= e((string) $footer['privacy_label']) ?>" required>
                    <label for="footer-terms-label">Terms label</label>
                    <input id="footer-terms-label" name="terms_label" maxlength="60" value="<?= e((string) $footer['terms_label']) ?>" required>
                    <p class="barama-footer-editor__hint">Links: <code>/policies</code>, <code>/policies?=privacy-policy</code>, <code>/policies?=terms-of-service</code></p>
                </fieldset>
                <fieldset>
                    <legend><span>04</span> Help</legend>
                    <p class="barama-footer-editor__hint">Documentation, Contact Support and System Health links are preserved.</p>
                    <label for="footer-help-heading">Column heading</label>
                    <input id="footer-help-heading" name="help_heading" maxlength="60" value="<?= e((string) $footer['help_heading']) ?>" required>
                    <p class="barama-footer-editor__hint">Support destinations continue to use the existing application configuration.</p>
                </fieldset>
            </div>
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
