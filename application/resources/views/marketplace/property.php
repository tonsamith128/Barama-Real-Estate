<?php
declare(strict_types=1);

$price = ($listing['price'] ?? null) !== null
    ? barama_detail_price(
        (string) ($listing['currency'] ?? 'USD'),
        (float) $listing['price'],
        ($listing['transaction_type'] ?? '') === 'rent'
            ? (string) ($listing['pricing_period'] ?? '')
            : null
    )
    : 'Price on request';

$relatedListings = is_array($relatedListings ?? null) ? $relatedListings : [];
$locationLabel = (string) ($listing['location_name'] ?? $listing['address_line'] ?? 'Location available on request');
$typeLabel = ucwords(str_replace('_', ' ', (string) ($listing['property_type'] ?? 'Property')));
$furnishingLabel = ucwords(str_replace('_', ' ', (string) ($listing['furnishing_status'] ?? '—')));
$areaLabel = trim((string) ($listing['area_value'] ?? '') . ' ' . (string) ($listing['area_unit'] ?? '')) ?: '—';
?>
<section class="listing-detail listing-detail--premium single-detail single-detail--property">
    <div class="container app-container">
        <nav class="detail-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/properties')) ?>">Properties</a>
            <span>/</span>
            <span><?= e((string) $listing['title']) ?></span>
        </nav>

        <div class="single-detail__hero-grid">
            <div class="single-detail__primary">
                <?= barama_detail_media_stage(
                    is_array($gallery ?? null) ? $gallery : [],
                    (string) $listing['title'],
                    'home'
                ) ?>
            </div>

            <aside class="detail-summary-card detail-summary-card--sticky" aria-label="Property summary">
                <span class="eyebrow">Listed price</span>
                <strong class="detail-summary-card__price"><?= e($price) ?></strong>
                <?php if (!empty($listing['negotiable'])): ?>
                    <div class="detail-summary-card__badges">
                        <span class="detail-status detail-status--soft">Negotiable</span>
                    </div>
                <?php endif; ?>
                <?php /* BARAMA_SINGLE_DETAIL_CONTENT_BG_V103: transaction badge remains in main content only. */ ?>

                <dl class="detail-summary-list">
                    <div><dt><?= app_icon('tag') ?><span>Reference</span></dt><dd><?= e((string) ($listing['reference_code'] ?? '—')) ?></dd></div>
                    <div><dt><?= app_icon('map') ?><span>Location</span></dt><dd><?= e($locationLabel) ?></dd></div>
                    <div><dt><?= app_icon('home') ?><span>Property type</span></dt><dd><?= e($typeLabel) ?></dd></div>
                    <div><dt><?= app_icon('layers') ?><span>Floor area</span></dt><dd><?= e($areaLabel) ?></dd></div>
                    <?php if (($listing['bedrooms'] ?? null) !== null): ?>
                        <div><dt><?= app_icon('home') ?><span>Bedrooms</span></dt><dd><?= e(rtrim(rtrim((string) $listing['bedrooms'], '0'), '.')) ?></dd></div>
                    <?php endif; ?>
                    <?php if (($listing['bathrooms'] ?? null) !== null): ?>
                        <div><dt><?= app_icon('grid') ?><span>Bathrooms</span></dt><dd><?= e(rtrim(rtrim((string) $listing['bathrooms'], '0'), '.')) ?></dd></div>
                    <?php endif; ?>
                    <?php if (($listing['parking_spaces'] ?? null) !== null): ?>
                        <div><dt><?= app_icon('key') ?><span>Parking</span></dt><dd><?= e((string) $listing['parking_spaces']) ?> space<?= (int) $listing['parking_spaces'] === 1 ? '' : 's' ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($listing['furnishing_status'])): ?>
                        <div><dt><?= app_icon('layers') ?><span>Furnishing</span></dt><dd><?= e($furnishingLabel) ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($listing['year_built'])): ?>
                        <div><dt><?= app_icon('calendar') ?><span>Year built</span></dt><dd><?= e((string) $listing['year_built']) ?></dd></div>
                    <?php endif; ?>
                    <?php if (!empty($listing['project_name'])): ?>
                        <div><dt><?= app_icon('layers') ?><span>Project</span></dt><dd><a href="<?= e(url('/projects/' . rawurlencode((string) $listing['project_slug']))) ?>"><?= e((string) $listing['project_name']) ?></a></dd></div>
                    <?php endif; ?>
                    <div><dt><?= app_icon('building') ?><span>Publisher</span></dt><dd><a href="<?= e(url('/company/' . rawurlencode((string) $listing['company_slug']))) ?>"><?= e((string) $listing['company_name']) ?></a></dd></div>
                </dl>

                <div data-account-notice hidden></div>

                <div class="detail-summary-actions">
                    <?php if (!empty($engagementState['signed_in'])): ?>
                        <form action="<?= e(url('/account/saved/' . (int) $listing['id'] . (!empty($engagementState['saved']) ? '/remove' : ''))) ?>" method="post" data-account-action>
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-secondary w-100 <?= !empty($engagementState['saved']) ? 'is-active' : '' ?>" type="submit">
                                <?= app_icon('heart') ?>
                                <span><?= !empty($engagementState['saved']) ? 'Saved' : 'Save property' ?></span>
                            </button>
                        </form>
                        <form action="<?= e(url('/account/compare/' . (int) $listing['id'] . (!empty($engagementState['compared']) ? '/remove' : ''))) ?>" method="post" data-account-action>
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-secondary w-100 <?= !empty($engagementState['compared']) ? 'is-active' : '' ?>" type="submit">
                                <?= app_icon('compare') ?>
                                <span><?= !empty($engagementState['compared']) ? 'In comparison' : 'Compare' ?></span>
                            </button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn-primary w-100" href="<?= e(url('/login')) ?>">
                            <?= app_icon('user') ?>
                            <span>Sign in to save or compare</span>
                        </a>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <div class="detail-layout detail-layout--premium detail-layout--content">
            <div class="detail-main single-detail__content-surface">
                <div class="single-detail__intro-surface">
                <header class="single-detail__title-block">
                                    <div class="property-detail-head__badges">
                                        <span class="detail-status"><?= e(($listing['transaction_type'] ?? '') === 'rent' ? 'For rent' : 'For sale') ?></span>
                                        <?php if (!empty($listing['negotiable'])): ?>
                                            <span class="detail-status detail-status--soft">Negotiable</span>
                                        <?php endif; ?>
                                    </div>
                                    <h1><?= e((string) $listing['title']) ?></h1>
                                    <p class="single-detail__location">
                                        <?= app_icon('map') ?>
                                        <span><?= e($locationLabel) ?></span>
                                    </p>
                                </header>
                <?php if (!empty($listing['property_description'])): ?>
                    <section class="detail-section detail-section--clean detail-section--first">
                        <span class="eyebrow">Property overview</span>
                        <h2>About this property</h2>
                        <div class="rich-text-content rich-text-content--detail"><?= \Barama\Support\RichText::render((string) $listing['property_description']) ?></div>
                    </section>
                <?php endif; ?>
                </div>

                <section class="detail-section detail-section--clean">
                    <span class="eyebrow">Publisher</span>
                    <h2>Listed by an approved company</h2>
                    <div class="detail-link-grid">
                        <a class="detail-company-link" href="<?= e(url('/company/' . rawurlencode((string) $listing['company_slug']))) ?>">
                            <?= app_icon('building') ?>
                            <span><strong><?= e((string) $listing['company_name']) ?></strong><small>View company profile</small></span>
                            <b>↗</b>
                        </a>
                        <?php if (!empty($listing['project_slug'])): ?>
                            <a class="detail-company-link" href="<?= e(url('/projects/' . rawurlencode((string) $listing['project_slug']))) ?>">
                                <?= app_icon('layers') ?>
                                <span><strong><?= e((string) $listing['project_name']) ?></strong><small>View development project</small></span>
                                <b>↗</b>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>

                
            </div>

            <aside class="inquiry-card inquiry-card--sticky inquiry-card--premium">
                <div class="inquiry-card__head">
                    <span class="inquiry-card__icon"><?= app_icon('message') ?></span>
                    <div><span class="eyebrow">Contact publisher</span><h2>Ask about this property</h2></div>
                </div>
                <p>Send your question directly to the company managing this listing.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_type" value="listing">
                    <input type="hidden" name="target_id" value="<?= e((string) $listing['id']) ?>">
                    <input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">

                    <label>
                        <span class="inquiry-field-label"><?= app_icon('user') ?><span>Name</span></span>
                        <input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160">
                    </label>
                    <label>
                        <span class="inquiry-field-label"><?= app_icon('mail') ?><span>Email</span></span>
                        <input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191">
                    </label>
                    <label>
                        <span class="inquiry-field-label"><?= app_icon('phone') ?><span>Phone</span></span>
                        <input class="form-control" name="contact_phone" maxlength="60">
                    </label>
                    <label>
                        <span class="inquiry-field-label"><?= app_icon('message') ?><span>Message</span></span>
                        <textarea class="form-control" name="message" rows="5" required>I am interested in <?= e((string) $listing['title']) ?>.</textarea>
                    </label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company">
                    <span><?= app_icon('shield') ?></span>
                    <span><strong><?= e((string) $listing['company_name']) ?></strong><small>Approved marketplace publisher</small></span>
                </div>
            </aside>
        </div>

        <?php if ($relatedListings !== []): ?>
                                    <section class="detail-related single-detail__wide-section">
                                        <div class="section-heading-row section-heading-row--compact">
                                            <div><span class="eyebrow">Keep exploring</span><h2>Similar properties</h2></div>
                                            <a href="<?= e(url('/properties?type=' . rawurlencode((string) $listing['property_type']))) ?>">More like this</a>
                                        </div>
                                        <div class="inventory-grid inventory-grid--detail">
                                            <?php foreach ($relatedListings as $item): ?>
                                                <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endif; ?>

    </div>
</section>
