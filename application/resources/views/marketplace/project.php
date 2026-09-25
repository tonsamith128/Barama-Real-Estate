<?php
declare(strict_types=1);

$locationLabel = (string) ($project['location_name'] ?? $project['address_line'] ?? 'Location available on request');
$projectType = ucwords(str_replace('_', ' ', (string) ($project['project_type'] ?? 'Development')));
$stageLabel = ucwords(str_replace('_', ' ', (string) ($project['completion_status'] ?? 'Published')));

$startingListing = null;
foreach (is_array($listings ?? null) ? $listings : [] as $candidate) {
    if (!isset($candidate['price'])) {
        continue;
    }

    if ($startingListing === null || (float) $candidate['price'] < (float) $startingListing['price']) {
        $startingListing = $candidate;
    }
}

$startingPrice = null;
if (is_array($startingListing)) {
    $startingPrice = barama_detail_price(
        (string) ($startingListing['currency'] ?? 'USD'),
        (float) $startingListing['price'],
        ($startingListing['transaction_type'] ?? '') === 'rent'
            ? (string) ($startingListing['pricing_period'] ?? '')
            : null
    );
}
?>
<section class="listing-detail listing-detail--premium single-detail single-detail--project">
    <div class="container app-container">
        <nav class="detail-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(url('/projects')) ?>">Projects</a>
            <span>/</span>
            <span><?= e((string) $project['name']) ?></span>
        </nav>

        <div class="single-detail__hero-grid">
            <div class="single-detail__primary">
                <?= barama_detail_media_stage(
                    is_array($gallery ?? null) ? $gallery : [],
                    (string) $project['name'],
                    'layers'
                ) ?>
            </div>

            <aside class="detail-summary-card detail-summary-card--sticky" aria-label="Project summary">
                <span class="eyebrow"><?= $startingPrice !== null ? 'Starting from' : 'Availability' ?></span>
                <?php if ($startingPrice !== null): ?>
                    <strong class="detail-summary-card__price"><?= e($startingPrice) ?></strong>
                <?php else: ?>
                    <strong class="detail-summary-card__price detail-summary-card__price--text">Contact developer</strong>
                <?php endif; ?>

                <div class="detail-summary-card__badges">
                    <span class="detail-status"><?= e($stageLabel) ?></span>
                    <span class="detail-status detail-status--soft"><?= e($projectType) ?></span>
                </div>

                <dl class="detail-summary-list">
                    <div><dt><?= app_icon('building') ?><span>Developer</span></dt><dd><a href="<?= e(url('/company/' . rawurlencode((string) $project['company_slug']))) ?>"><?= e((string) $project['company_name']) ?></a></dd></div>
                    <div><dt><?= app_icon('map') ?><span>Location</span></dt><dd><?= e($locationLabel) ?></dd></div>
                    <div><dt><?= app_icon('calendar') ?><span>Launch date</span></dt><dd><?= e((string) ($project['launch_date'] ?? '—')) ?></dd></div>
                    <div><dt><?= app_icon('check') ?><span>Completion</span></dt><dd><?= e((string) ($project['expected_completion_date'] ?? '—')) ?></dd></div>
                    <div><dt><?= app_icon('home') ?><span>Available properties</span></dt><dd><?= e((string) count(is_array($listings ?? null) ? $listings : [])) ?> shown</dd></div>
                </dl>

                <div class="detail-summary-actions">
                    <a class="btn btn-primary w-100" href="#project-inquiry"><?= app_icon('message') ?><span>Ask about this project</span></a>
                    <a class="btn btn-outline-secondary w-100" href="<?= e(url('/company/' . rawurlencode((string) $project['company_slug']))) ?>"><?= app_icon('building') ?><span>View developer</span></a>
                </div>
            </aside>
        </div>

        <div class="detail-layout detail-layout--premium detail-layout--content">
            <div class="detail-main single-detail__content-surface">
                <div class="single-detail__intro-surface">
                <header class="single-detail__title-block">
                                    <div class="property-detail-head__badges">
                                        <span class="detail-status">Published development</span>
                                        <span class="detail-status detail-status--soft"><?= e($stageLabel) ?></span>
                                    </div>
                                    <h1><?= e((string) $project['name']) ?></h1>
                                    <p class="single-detail__location"><?= app_icon('map') ?><span><?= e($locationLabel) ?></span></p>
                                </header>
                <?php if (!empty($project['description'])): ?>
                    <section class="detail-section detail-section--clean detail-section--first">
                        <span class="eyebrow">Development overview</span>
                        <h2>About this project</h2>
                        <p><?= nl2br(e((string) $project['description'])) ?></p>
                    </section>
                <?php endif; ?>
                </div>

                
            </div>

            <aside class="inquiry-card inquiry-card--sticky inquiry-card--premium" id="project-inquiry">
                <div class="inquiry-card__head">
                    <span class="inquiry-card__icon"><?= app_icon('message') ?></span>
                    <div><span class="eyebrow">Contact developer</span><h2>Ask about this project</h2></div>
                </div>
                <p>Send your question directly to the company responsible for this development.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_type" value="project">
                    <input type="hidden" name="target_id" value="<?= e((string) $project['id']) ?>">
                    <input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">

                    <label><span class="inquiry-field-label"><?= app_icon('user') ?><span>Name</span></span><input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('mail') ?><span>Email</span></span><input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('phone') ?><span>Phone</span></span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span class="inquiry-field-label"><?= app_icon('message') ?><span>Message</span></span><textarea class="form-control" name="message" rows="5" required>I would like more information about <?= e((string) $project['name']) ?>.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company">
                    <span><?= app_icon('building') ?></span>
                    <span><strong><?= e((string) $project['company_name']) ?></strong><small>Approved marketplace company</small></span>
                </div>
            </aside>
        </div>

        <?php if ($listings !== []): ?>
                            <section class="detail-section detail-section--clean single-detail__wide-section">
                                <div class="section-heading-row section-heading-row--compact">
                                    <div><span class="eyebrow">Available inventory</span><h2>Properties in this project</h2></div>
                                    <a href="<?= e(url('/properties?q=' . rawurlencode((string) $project['name']))) ?>">View all</a>
                                </div>
                                <div class="inventory-grid inventory-grid--detail">
                                    <?php foreach ($listings as $listing): ?>
                                        <?= \Barama\Core\View::partial('components/inventory-card', [
                                            'kind' => 'listing',
                                            'item' => array_merge($listing, ['company_name' => $project['company_name']]),
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
    </div>
</section>
