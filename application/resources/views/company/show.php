<?php
declare(strict_types=1);

$locationLabel = (string) ($company['location_name'] ?? 'Marketplace company');
$typeLabel = ucwords(str_replace('_', ' ', (string) ($company['company_type'] ?? 'Company')));
$verifiedLabel = !empty($company['verified_at']) ? 'Verified company' : 'Approved company';
$projectCount = count(is_array($projects ?? null) ? $projects : []);
$listingCount = count(is_array($listings ?? null) ? $listings : []);
?>
<section class="listing-detail listing-detail--premium single-detail single-detail--company company-detail">
    <div class="container app-container">
        <nav class="detail-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/company')) ?>">Companies</a>
            <span>/</span>
            <span><?= e((string) $company['name']) ?></span>
        </nav>

        <div class="single-detail__hero-grid">
            <div class="single-detail__primary">
                <?= barama_detail_media_stage(
                    is_array($gallery ?? null) ? $gallery : [],
                    (string) $company['name'],
                    'building'
                ) ?>
            </div>

            <aside class="detail-summary-card detail-summary-card--sticky" aria-label="Company summary">
                <span class="eyebrow">Marketplace company</span>
                <strong class="detail-summary-card__price detail-summary-card__price--text"><?= e($verifiedLabel) ?></strong>
                <div class="detail-summary-card__badges">
                    <span class="detail-status"><?= e($typeLabel) ?></span>
                </div>

                <dl class="detail-summary-list">
                    <div><dt><?= app_icon('map') ?><span>Location</span></dt><dd><?= e($locationLabel) ?></dd></div>
                    <div><dt><?= app_icon('layers') ?><span>Featured projects</span></dt><dd><?= e((string) $projectCount) ?></dd></div>
                    <div><dt><?= app_icon('home') ?><span>Available properties</span></dt><dd><?= e((string) $listingCount) ?></dd></div>
                    <?php if (!empty($company['phone'])): ?><div><dt><?= app_icon('phone') ?><span>Phone</span></dt><dd><a href="tel:<?= e((string) $company['phone']) ?>"><?= e((string) $company['phone']) ?></a></dd></div><?php endif; ?>
                    <?php if (!empty($company['email'])): ?><div><dt><?= app_icon('mail') ?><span>Email</span></dt><dd><a href="mailto:<?= e((string) $company['email']) ?>"><?= e((string) $company['email']) ?></a></dd></div><?php endif; ?>
                    <?php if (!empty($company['website'])): ?><div><dt><?= app_icon('globe') ?><span>Website</span></dt><dd><a href="<?= e((string) $company['website']) ?>" rel="noopener noreferrer" target="_blank">Open website ↗</a></dd></div><?php endif; ?>
                </dl>

                <div class="detail-summary-actions">
                    <a class="btn btn-primary w-100" href="#company-inquiry"><?= app_icon('message') ?><span>Contact company</span></a>
                    <?php if (!empty($company['phone'])): ?><a class="btn btn-outline-secondary w-100" href="tel:<?= e((string) $company['phone']) ?>"><?= app_icon('phone') ?><span>Call company</span></a><?php endif; ?>
                </div>
            </aside>
        </div>

        <div class="detail-layout detail-layout--premium detail-layout--content">
            <div class="detail-main single-detail__content-surface">
                <div class="single-detail__intro-surface">
                <header class="single-detail__title-block">
                                    <div class="property-detail-head__badges">
                                        <span class="detail-status"><?= e($verifiedLabel) ?></span>
                                        <span class="detail-status detail-status--soft"><?= e($typeLabel) ?></span>
                                    </div>
                                    <h1><?= e((string) $company['name']) ?></h1>
                                    <p class="single-detail__location"><?= app_icon('map') ?><span><?= e($locationLabel) ?></span></p>
                                </header>
                <?php if (!empty($company['description'])): ?>
                    <section class="detail-section detail-section--clean detail-section--first">
                        <span class="eyebrow">Company profile</span>
                        <h2>About <?= e((string) $company['name']) ?></h2>
                        <p><?= nl2br(e((string) $company['description'])) ?></p>
                    </section>
                <?php endif; ?>
                </div>

                

                
            </div>

            <aside class="inquiry-card inquiry-card--sticky inquiry-card--premium" id="company-inquiry">
                <div class="inquiry-card__head">
                    <span class="inquiry-card__icon"><?= app_icon('message') ?></span>
                    <div><span class="eyebrow">Contact company</span><h2>Send an inquiry</h2></div>
                </div>
                <p>Contact <?= e((string) $company['name']) ?> through the Barama marketplace.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_type" value="company">
                    <input type="hidden" name="target_id" value="<?= e((string) $company['id']) ?>">
                    <input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">

                    <label><span class="inquiry-field-label"><?= app_icon('user') ?><span>Name</span></span><input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('mail') ?><span>Email</span></span><input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('phone') ?><span>Phone</span></span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('message') ?><span>Message</span></span><textarea class="form-control" name="message" rows="5" required>I would like to learn more about your properties and projects.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company">
                    <span><?= app_icon('shield') ?></span>
                    <span><strong>Approved profile</strong><small>Public marketplace company</small></span>
                </div>
            </aside>
        </div>

        <?php if ($projects !== []): ?>
                            <section class="detail-section detail-section--clean single-detail__wide-section">
                                <div class="section-heading-row section-heading-row--compact">
                                    <div><span class="eyebrow">Developments</span><h2>Published projects</h2></div>
                                    <a href="<?= e(url('/projects?q=' . rawurlencode((string) $company['name']))) ?>">View all</a>
                                </div>
                                <div class="inventory-grid inventory-grid--detail">
                                    <?php foreach ($projects as $project): ?>
                                        <?= \Barama\Core\View::partial('components/inventory-card', [
                                            'kind' => 'project',
                                            'item' => array_merge($project, ['company_name' => $company['name']]),
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
        
        <?php if ($listings !== []): ?>
                            <section class="detail-section detail-section--clean single-detail__wide-section">
                                <div class="section-heading-row section-heading-row--compact">
                                    <div><span class="eyebrow">Marketplace inventory</span><h2>Available properties</h2></div>
                                    <a href="<?= e(url('/properties?q=' . rawurlencode((string) $company['name']))) ?>">View all</a>
                                </div>
                                <div class="inventory-grid inventory-grid--detail">
                                    <?php foreach ($listings as $listing): ?>
                                        <?= \Barama\Core\View::partial('components/inventory-card', [
                                            'kind' => 'listing',
                                            'item' => array_merge($listing, ['company_name' => $company['name']]),
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
    </div>
</section>
