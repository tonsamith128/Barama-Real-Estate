<?php
declare(strict_types=1);
?>
<section class="listing-detail listing-detail--premium company-detail">
    <div class="container app-container">
        <nav class="detail-breadcrumb"><a href="<?= e(url('/company')) ?>">Companies</a><span>/</span><span><?= e((string) $company['name']) ?></span></nav>

        <header class="company-profile-premium">
            <div class="company-profile-premium__media">
                <?php if ($gallery !== []): ?><button type="button" data-gallery-image data-gallery-src="<?= e(url('/media/' . rawurlencode((string) $gallery[0]['uuid']))) ?>" data-gallery-alt="<?= e((string) ($gallery[0]['alt_text'] ?: $company['name'])) ?>"><img src="<?= e(url('/media/' . rawurlencode((string) $gallery[0]['uuid']))) ?>" alt="<?= e((string) ($gallery[0]['alt_text'] ?: $company['name'])) ?>"></button><?php else: ?><span><?= app_icon('building') ?></span><?php endif; ?>
            </div>
            <div class="company-profile-premium__content">
                <div class="property-detail-head__badges"><span class="detail-status"><?= !empty($company['verified_at']) ? 'Verified company' : 'Approved company' ?></span><span class="detail-status detail-status--soft"><?= e(ucwords(str_replace('_', ' ', (string) $company['company_type']))) ?></span></div>
                <h1><?= e((string) $company['name']) ?></h1>
                <p><?= app_icon('map') ?><span><?= e((string) ($company['location_name'] ?? 'Marketplace company')) ?></span></p>
                <div class="company-profile-premium__actions">
                    <?php if (!empty($company['website'])): ?><a class="btn btn-outline-secondary" href="<?= e((string) $company['website']) ?>" rel="noopener noreferrer" target="_blank"><?= app_icon('globe') ?> Website</a><?php endif; ?>
                    <?php if (!empty($company['phone'])): ?><a class="btn btn-outline-secondary" href="tel:<?= e((string) $company['phone']) ?>"><?= app_icon('message') ?> Call company</a><?php endif; ?>
                </div>
            </div>
        </header>

        <div class="detail-layout detail-layout--premium">
            <div class="detail-main">
                <?php if (!empty($company['description'])): ?><section class="detail-section detail-section--clean"><span class="eyebrow">Company profile</span><h2>About <?= e((string) $company['name']) ?></h2><p><?= nl2br(e((string) $company['description'])) ?></p></section><?php endif; ?>

                <?php if ($projects !== []): ?><section class="detail-section detail-section--clean"><div class="section-heading-row section-heading-row--compact"><div><span class="eyebrow">Developments</span><h2>Published projects</h2></div><a href="<?= e(url('/projects?q=' . rawurlencode((string) $company['name']))) ?>">View all</a></div><div class="inventory-grid inventory-grid--detail"><?php foreach ($projects as $project): ?><?= BaramaCoreView::partial('components/inventory-card', ['kind' => 'project', 'item' => array_merge($project, ['company_name' => $company['name']])]) ?><?php endforeach; ?></div></section><?php endif; ?>

                <?php if ($listings !== []): ?><section class="detail-section detail-section--clean"><div class="section-heading-row section-heading-row--compact"><div><span class="eyebrow">Marketplace inventory</span><h2>Available properties</h2></div><a href="<?= e(url('/properties?q=' . rawurlencode((string) $company['name']))) ?>">View all</a></div><div class="inventory-grid inventory-grid--detail"><?php foreach ($listings as $listing): ?><?= BaramaCoreView::partial('components/inventory-card', ['kind' => 'listing', 'item' => array_merge($listing, ['company_name' => $company['name']])]) ?><?php endforeach; ?></div></section><?php endif; ?>
            </div>

            <aside class="inquiry-card inquiry-card--sticky">
                <div class="inquiry-card__head"><span class="inquiry-card__icon"><?= app_icon('message') ?></span><div><span class="eyebrow">Contact company</span><h2>Send an inquiry</h2></div></div>
                <p>Contact <?= e((string) $company['name']) ?> through the Barama marketplace.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?><input type="hidden" name="target_type" value="company"><input type="hidden" name="target_id" value="<?= e((string) $company['id']) ?>"><input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="4" required>I would like to learn more about your properties and projects.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company"><span><?= app_icon('shield') ?></span><span><strong>Approved profile</strong><small>Public marketplace company</small></span></div>
            </aside>
        </div>
    </div>
</section>
