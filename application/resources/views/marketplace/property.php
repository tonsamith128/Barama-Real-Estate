<?php
declare(strict_types=1);

$price = strtoupper((string) ($listing['currency'] ?? 'USD')) . ' ' . number_format((float) ($listing['price'] ?? 0), 0);
if (($listing['transaction_type'] ?? '') === 'rent' && !empty($listing['pricing_period'])) {
    $price .= ' / ' . ucwords(str_replace('_', ' ', (string) $listing['pricing_period']));
}
?>
<section class="listing-detail">
    <div class="container app-container">
        <nav class="detail-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/properties')) ?>">Properties</a>
            <span>/</span>
            <span><?= e((string) $listing['title']) ?></span>
        </nav>

        <div class="detail-gallery">
            <?php if ($gallery !== []): ?>
                <?php foreach (array_slice($gallery, 0, 5) as $index => $media): ?>
                    <figure class="<?= $index === 0 ? 'detail-gallery__primary' : '' ?>">
                        <img
                            src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>"
                            alt="<?= e((string) ($media['alt_text'] ?: $listing['title'])) ?>"
                            <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                        >
                    </figure>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="detail-gallery__empty">
                    <?= app_icon('home') ?>
                    <span>Property imagery will appear here when the publisher adds media.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-layout">
            <div class="detail-main">
                <span class="detail-status"><?= e(($listing['transaction_type'] ?? '') === 'rent' ? 'For rent' : 'For sale') ?></span>
                <h1><?= e((string) $listing['title']) ?></h1>
                <p class="detail-location">
                    <?= e((string) ($listing['location_name'] ?? $listing['address_line'] ?? '')) ?>
                </p>
                <div class="detail-price"><?= e($price) ?></div>

                <dl class="detail-facts">
                    <div><dt>Type</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $listing['property_type']))) ?></dd></div>
                    <div><dt>Bedrooms</dt><dd><?= e((string) ($listing['bedrooms'] ?? '—')) ?></dd></div>
                    <div><dt>Bathrooms</dt><dd><?= e((string) ($listing['bathrooms'] ?? '—')) ?></dd></div>
                    <div><dt>Parking</dt><dd><?= e((string) ($listing['parking_spaces'] ?? '—')) ?></dd></div>
                    <div><dt>Area</dt><dd><?= e(trim((string) ($listing['area_value'] ?? '') . ' ' . (string) ($listing['area_unit'] ?? '')) ?: '—') ?></dd></div>
                    <div><dt>Reference</dt><dd><?= e((string) $listing['reference_code']) ?></dd></div>
                </dl>

                <?php if (!empty($listing['property_description'])): ?>
                    <section class="detail-section">
                        <h2>About this property</h2>
                        <p><?= nl2br(e((string) $listing['property_description'])) ?></p>
                    </section>
                <?php endif; ?>

                <section class="detail-section">
                    <h2>Listed by</h2>
                    <a class="detail-company-link" href="<?= e(url('/company/' . rawurlencode((string) $listing['company_slug']))) ?>">
                        <?= app_icon('building') ?>
                        <span>
                            <strong><?= e((string) $listing['company_name']) ?></strong>
                            <small>View company profile</small>
                        </span>
                    </a>
                    <?php if (!empty($listing['project_slug'])): ?>
                        <a class="detail-company-link" href="<?= e(url('/projects/' . rawurlencode((string) $listing['project_slug']))) ?>">
                            <?= app_icon('layers') ?>
                            <span>
                                <strong><?= e((string) $listing['project_name']) ?></strong>
                                <small>View development project</small>
                            </span>
                        </a>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="inquiry-card">
                <span class="eyebrow">Contact publisher</span>
                <h2>Ask about this property</h2>
                <p>Send a marketplace inquiry directly to the company managing this listing.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_type" value="listing">
                    <input type="hidden" name="target_id" value="<?= e((string) $listing['id']) ?>">
                    <input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="5" required>I am interested in <?= e((string) $listing['title']) ?>.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit">Send inquiry</button>
                </form>
            </aside>
        </div>
    </div>
</section>
