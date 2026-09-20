<?php
declare(strict_types=1);

$price = strtoupper((string) ($listing['currency'] ?? 'USD')) . ' ' . number_format((float) ($listing['price'] ?? 0), 0);
if (($listing['transaction_type'] ?? '') === 'rent' && !empty($listing['pricing_period'])) {
    $price .= ' / ' . ucwords(str_replace('_', ' ', (string) $listing['pricing_period']));
}
$relatedListings = is_array($relatedListings ?? null) ? $relatedListings : [];
?>
<section class="listing-detail listing-detail--premium">
    <div class="container app-container">
        <nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('/properties')) ?>">Properties</a><span>/</span><span><?= e((string) $listing['title']) ?></span></nav>

        <header class="property-detail-head">
            <div>
                <div class="property-detail-head__badges">
                    <span class="detail-status"><?= e(($listing['transaction_type'] ?? '') === 'rent' ? 'For rent' : 'For sale') ?></span>
                    <?php if (!empty($listing['negotiable'])): ?><span class="detail-status detail-status--soft">Negotiable</span><?php endif; ?>
                </div>
                <h1><?= e((string) $listing['title']) ?></h1>
                <p><?= app_icon('map') ?><span><?= e((string) ($listing['location_name'] ?? $listing['address_line'] ?? 'Location available on request')) ?></span></p>
            </div>
            <div class="property-detail-head__price"><small>Listed price</small><strong><?= e($price) ?></strong><span>Ref <?= e((string) $listing['reference_code']) ?></span></div>
        </header>

        <div class="detail-gallery detail-gallery--interactive" data-gallery>
            <?php if ($gallery !== []): ?>
                <?php foreach (array_slice($gallery, 0, 5) as $index => $media): ?>
                    <button class="<?= $index === 0 ? 'detail-gallery__primary' : '' ?>" type="button" data-gallery-image data-gallery-src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>" data-gallery-alt="<?= e((string) ($media['alt_text'] ?: $listing['title'])) ?>">
                        <img src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>" alt="<?= e((string) ($media['alt_text'] ?: $listing['title'])) ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                    </button>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="detail-gallery__empty"><?= app_icon('home') ?><span>Property imagery will appear here when the publisher adds media.</span></div>
            <?php endif; ?>
        </div>

        <div data-account-notice hidden></div>

        <div class="detail-layout detail-layout--premium">
            <div class="detail-main">
                <div class="property-engagement property-engagement--premium">
                    <?php if (!empty($engagementState['signed_in'])): ?>
                        <form action="<?= e(url('/account/saved/' . (int) $listing['id'] . (!empty($engagementState['saved']) ? '/remove' : ''))) ?>" method="post" data-account-action>
                            <?= csrf_field() ?>
                            <button class="property-engagement__button <?= !empty($engagementState['saved']) ? 'is-active' : '' ?>" type="submit"><?= app_icon('heart') ?><span><?= !empty($engagementState['saved']) ? 'Saved' : 'Save property' ?></span></button>
                        </form>
                        <form action="<?= e(url('/account/compare/' . (int) $listing['id'] . (!empty($engagementState['compared']) ? '/remove' : ''))) ?>" method="post" data-account-action>
                            <?= csrf_field() ?>
                            <button class="property-engagement__button <?= !empty($engagementState['compared']) ? 'is-active' : '' ?>" type="submit"><?= app_icon('compare') ?><span><?= !empty($engagementState['compared']) ? 'In comparison' : 'Compare' ?></span></button>
                        </form>
                        <details class="property-viewing-request">
                            <summary class="property-engagement__button"><?= app_icon('calendar') ?><span>Request viewing</span></summary>
                            <form action="<?= e(url('/account/viewings/' . (int) $listing['id'])) ?>" method="post" data-account-action>
                                <?= csrf_field() ?>
                                <div class="property-viewing-request__grid">
                                    <label><span>Preferred date</span><input class="form-control" type="date" name="preferred_date" min="<?= e(date('Y-m-d')) ?>" required></label>
                                    <label><span>Preferred time</span><select class="form-select" name="preferred_time_window" required><option value="morning">Morning</option><option value="midday">Midday</option><option value="afternoon">Afternoon</option><option value="evening">Evening</option></select></label>
                                    <label><span>Alternate date</span><input class="form-control" type="date" name="alternate_date" min="<?= e(date('Y-m-d')) ?>"></label>
                                    <label><span>Alternate time</span><select class="form-select" name="alternate_time_window"><option value="">No alternate</option><option value="morning">Morning</option><option value="midday">Midday</option><option value="afternoon">Afternoon</option><option value="evening">Evening</option></select></label>
                                </div>
                                <label><span>Message</span><textarea class="form-control" name="message" rows="3" maxlength="2000" placeholder="Any viewing preferences or questions"></textarea></label>
                                <button class="btn btn-primary btn-sm" type="submit">Submit viewing request</button>
                            </form>
                        </details>
                    <?php else: ?>
                        <a class="property-engagement__signin" href="<?= e(url('/login')) ?>"><?= app_icon('user') ?><span>Sign in to save, compare, and request a viewing</span></a>
                    <?php endif; ?>
                </div>

                <dl class="detail-facts detail-facts--premium">
                    <div><span><?= app_icon('home') ?></span><dt>Type</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $listing['property_type']))) ?></dd></div>
                    <div><span><?= app_icon('users') ?></span><dt>Bedrooms</dt><dd><?= e((string) ($listing['bedrooms'] ?? '—')) ?></dd></div>
                    <div><span><?= app_icon('grid') ?></span><dt>Bathrooms</dt><dd><?= e((string) ($listing['bathrooms'] ?? '—')) ?></dd></div>
                    <div><span><?= app_icon('map') ?></span><dt>Parking</dt><dd><?= e((string) ($listing['parking_spaces'] ?? '—')) ?></dd></div>
                    <div><span><?= app_icon('layers') ?></span><dt>Area</dt><dd><?= e(trim((string) ($listing['area_value'] ?? '') . ' ' . (string) ($listing['area_unit'] ?? '')) ?: '—') ?></dd></div>
                    <div><span><?= app_icon('tag') ?></span><dt>Furnishing</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($listing['furnishing_status'] ?? '—')))) ?></dd></div>
                </dl>

                <?php if (!empty($listing['property_description'])): ?>
                    <section class="detail-section detail-section--clean"><span class="eyebrow">Property overview</span><h2>About this property</h2><p><?= nl2br(e((string) $listing['property_description'])) ?></p></section>
                <?php endif; ?>

                <section class="detail-section detail-section--clean">
                    <span class="eyebrow">Publisher</span>
                    <h2>Listed by an approved company</h2>
                    <div class="detail-link-grid">
                        <a class="detail-company-link" href="<?= e(url('/company/' . rawurlencode((string) $listing['company_slug']))) ?>"><?= app_icon('building') ?><span><strong><?= e((string) $listing['company_name']) ?></strong><small>View company profile</small></span><b>↗</b></a>
                        <?php if (!empty($listing['project_slug'])): ?><a class="detail-company-link" href="<?= e(url('/projects/' . rawurlencode((string) $listing['project_slug']))) ?>"><?= app_icon('layers') ?><span><strong><?= e((string) $listing['project_name']) ?></strong><small>View development project</small></span><b>↗</b></a><?php endif; ?>
                    </div>
                </section>
            </div>

            <aside class="inquiry-card inquiry-card--sticky">
                <div class="inquiry-card__head"><span class="inquiry-card__icon"><?= app_icon('message') ?></span><div><span class="eyebrow">Contact publisher</span><h2>Ask about this property</h2></div></div>
                <p>Send your question directly to the company managing this listing.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?><input type="hidden" name="target_type" value="listing"><input type="hidden" name="target_id" value="<?= e((string) $listing['id']) ?>"><input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="4" required>I am interested in <?= e((string) $listing['title']) ?>.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company"><span><?= app_icon('shield') ?></span><span><strong><?= e((string) $listing['company_name']) ?></strong><small>Approved marketplace publisher</small></span></div>
            </aside>
        </div>

        <?php if ($relatedListings !== []): ?>
            <section class="detail-related">
                <div class="section-heading-row section-heading-row--compact"><div><span class="eyebrow">Keep exploring</span><h2>Similar properties</h2></div><a href="<?= e(url('/properties?type=' . rawurlencode((string) $listing['property_type']))) ?>">More like this</a></div>
                <div class="inventory-grid inventory-grid--detail"><?php foreach ($relatedListings as $item): ?><?= BaramaCoreView::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?><?php endforeach; ?></div>
            </section>
        <?php endif; ?>
    </div>
</section>
