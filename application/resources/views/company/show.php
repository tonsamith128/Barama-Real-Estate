<?php
declare(strict_types=1);

$companyProjects = is_array($projects ?? null) ? array_values($projects) : [];
$companyListings = is_array($listings ?? null) ? array_values($listings) : [];

$locationLabel = trim((string) ($company['location_name'] ?? ''));
$addressLabel = trim((string) ($company['address_line'] ?? ''));
$locationLabel = $locationLabel !== ''
    ? $locationLabel
    : ($addressLabel !== '' ? $addressLabel : 'Marketplace company');

$typeLabel = ucwords(str_replace('_', ' ', (string) ($company['company_type'] ?? 'Company')));
$verifiedLabel = !empty($company['verified_at']) ? 'Verified company' : 'Approved company';
$projectCount = count($companyProjects);
$listingCount = count($companyListings);
$legalName = trim((string) ($company['legal_name'] ?? ''));
$registrationNumber = trim((string) ($company['registration_number'] ?? ''));
$phone = trim((string) ($company['phone'] ?? ''));
$email = trim((string) ($company['email'] ?? ''));

$verifiedDate = '';
if (!empty($company['verified_at'])) {
    $verifiedTimestamp = strtotime((string) $company['verified_at']);
    if ($verifiedTimestamp !== false) {
        $verifiedDate = date('M j, Y', $verifiedTimestamp);
    }
}

$websiteUrl = trim((string) ($company['website'] ?? ''));
if ($websiteUrl !== '') {
    $websiteScheme = strtolower((string) parse_url($websiteUrl, PHP_URL_SCHEME));
    if (
        filter_var($websiteUrl, FILTER_VALIDATE_URL) === false
        || !in_array($websiteScheme, ['http', 'https'], true)
    ) {
        $websiteUrl = '';
    }
}

$websiteHost = $websiteUrl !== ''
    ? trim((string) parse_url($websiteUrl, PHP_URL_HOST))
    : '';
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
                    <div><dt><?= app_icon('layers') ?><span>Published projects</span></dt><dd><?= e((string) $projectCount) ?> shown</dd></div>
                    <div><dt><?= app_icon('home') ?><span>Available properties</span></dt><dd><?= e((string) $listingCount) ?> shown</dd></div>
                    <?php if ($phone !== ''): ?><div><dt><?= app_icon('phone') ?><span>Phone</span></dt><dd><a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a></dd></div><?php endif; ?>
                    <?php if ($email !== ''): ?><div><dt><?= app_icon('mail') ?><span>Email</span></dt><dd><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div><?php endif; ?>
                    <?php if ($websiteUrl !== ''): ?><div><dt><?= app_icon('globe') ?><span>Website</span></dt><dd><a href="<?= e($websiteUrl) ?>" rel="noopener noreferrer" target="_blank"><?= e($websiteHost !== '' ? $websiteHost : 'Open website') ?> ↗</a></dd></div><?php endif; ?>
                </dl>

                <div class="detail-summary-actions">
                    <a class="btn btn-primary w-100" href="#company-inquiry"><?= app_icon('message') ?><span>Contact company</span></a>
                    <?php if ($phone !== ''): ?><a class="btn btn-outline-secondary w-100" href="tel:<?= e($phone) ?>"><?= app_icon('phone') ?><span>Call company</span></a><?php endif; ?>
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
                            <div class="rich-text-content rich-text-content--detail"><?= \Barama\Support\RichText::render((string) $company['description']) ?></div>
                        </section>
                    <?php endif; ?>
                </div>

                <section class="detail-section detail-section--clean company-information">
                    <span class="eyebrow">Company information</span>
                    <h2>Profile at a glance</h2>
                    <dl class="company-facts-grid">
                        <div>
                            <dt><?= app_icon('building') ?><span>Company type</span></dt>
                            <dd><?= e($typeLabel) ?></dd>
                        </div>
                        <div>
                            <dt><?= app_icon('shield') ?><span>Profile status</span></dt>
                            <dd><?= e($verifiedLabel) ?><?= $verifiedDate !== '' ? ' · ' . e($verifiedDate) : '' ?></dd>
                        </div>
                        <div>
                            <dt><?= app_icon('map') ?><span>Location</span></dt>
                            <dd><?= e($locationLabel) ?></dd>
                        </div>
                        <div>
                            <dt><?= app_icon('building') ?><span>Legal name</span></dt>
                            <dd><?= e($legalName !== '' ? $legalName : (string) $company['name']) ?></dd>
                        </div>
                        <div>
                            <dt><?= app_icon('check') ?><span>Registration</span></dt>
                            <dd><?= e($registrationNumber !== '' ? $registrationNumber : 'Not specified') ?></dd>
                        </div>
                        <div>
                            <dt><?= app_icon('layers') ?><span>Published inventory</span></dt>
                            <dd><?= e((string) $projectCount) ?> project<?= $projectCount === 1 ? '' : 's' ?> · <?= e((string) $listingCount) ?> propert<?= $listingCount === 1 ? 'y' : 'ies' ?></dd>
                        </div>
                    </dl>
                </section>

                <?php if ($phone !== '' || $email !== '' || $websiteUrl !== ''): ?>
                    <section class="detail-section detail-section--clean">
                        <span class="eyebrow">Publisher contact</span>
                        <h2>Contact this company</h2>
                        <div class="detail-link-grid company-contact-grid">
                            <?php if ($phone !== ''): ?>
                                <a class="detail-company-link" href="tel:<?= e($phone) ?>">
                                    <?= app_icon('phone') ?>
                                    <span><strong><?= e($phone) ?></strong><small>Call the company</small></span>
                                    <b>↗</b>
                                </a>
                            <?php endif; ?>
                            <?php if ($email !== ''): ?>
                                <a class="detail-company-link" href="mailto:<?= e($email) ?>">
                                    <?= app_icon('mail') ?>
                                    <span><strong><?= e($email) ?></strong><small>Email the company</small></span>
                                    <b>↗</b>
                                </a>
                            <?php endif; ?>
                            <?php if ($websiteUrl !== ''): ?>
                                <a class="detail-company-link" href="<?= e($websiteUrl) ?>" rel="noopener noreferrer" target="_blank">
                                    <?= app_icon('globe') ?>
                                    <span><strong><?= e($websiteHost !== '' ? $websiteHost : 'Company website') ?></strong><small>Open external website</small></span>
                                    <b>↗</b>
                                </a>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
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

        <?php if ($companyProjects !== []): ?>
            <section class="detail-section detail-section--clean single-detail__wide-section">
                <div class="section-heading-row section-heading-row--compact">
                    <div><span class="eyebrow">Developments</span><h2>Published projects</h2></div>
                    <a href="<?= e(url('/projects?q=' . rawurlencode((string) $company['name']))) ?>">View all</a>
                </div>
                <div class="inventory-grid inventory-grid--detail">
                    <?php foreach ($companyProjects as $project): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', [
                            'kind' => 'project',
                            'item' => array_merge($project, ['company_name' => $company['name']]),
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($companyListings !== []): ?>
            <section class="detail-section detail-section--clean single-detail__wide-section">
                <div class="section-heading-row section-heading-row--compact">
                    <div><span class="eyebrow">Marketplace inventory</span><h2>Available properties</h2></div>
                    <a href="<?= e(url('/properties?q=' . rawurlencode((string) $company['name']))) ?>">View all</a>
                </div>
                <div class="inventory-grid inventory-grid--detail">
                    <?php foreach ($companyListings as $listing): ?>
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
