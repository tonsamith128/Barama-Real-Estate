<?php
declare(strict_types=1);
?>
<section class="listing-detail listing-detail--premium">
    <div class="container app-container">
        <nav class="detail-breadcrumb"><a href="<?= e(url('/projects')) ?>">Projects</a><span>/</span><span><?= e((string) $project['name']) ?></span></nav>

        <header class="property-detail-head">
            <div><div class="property-detail-head__badges"><span class="detail-status">Published development</span><span class="detail-status detail-status--soft"><?= e(ucwords(str_replace('_', ' ', (string) $project['completion_status']))) ?></span></div><h1><?= e((string) $project['name']) ?></h1><p><?= app_icon('map') ?><span><?= e((string) ($project['location_name'] ?? $project['address_line'] ?? 'Location available on request')) ?></span></p></div>
            <a class="project-company-chip" href="<?= e(url('/company/' . rawurlencode((string) $project['company_slug']))) ?>"><?= app_icon('building') ?><span><small>Developer</small><strong><?= e((string) $project['company_name']) ?></strong></span><b>↗</b></a>
        </header>

        <div class="detail-gallery detail-gallery--interactive" data-gallery>
            <?php if ($gallery !== []): ?>
                <?php foreach (array_slice($gallery, 0, 5) as $index => $media): ?><button class="<?= $index === 0 ? 'detail-gallery__primary' : '' ?>" type="button" data-gallery-image data-gallery-src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>" data-gallery-alt="<?= e((string) ($media['alt_text'] ?: $project['name'])) ?>"><img src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>" alt="<?= e((string) ($media['alt_text'] ?: $project['name'])) ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></button><?php endforeach; ?>
            <?php else: ?><div class="detail-gallery__empty"><?= app_icon('layers') ?><span>Project imagery will appear here when available.</span></div><?php endif; ?>
        </div>

        <div class="detail-layout detail-layout--premium">
            <div class="detail-main">
                <dl class="detail-facts detail-facts--premium">
                    <div><span><?= app_icon('layers') ?></span><dt>Project type</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $project['project_type']))) ?></dd></div>
                    <div><span><?= app_icon('pipeline') ?></span><dt>Stage</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $project['completion_status']))) ?></dd></div>
                    <div><span><?= app_icon('calendar') ?></span><dt>Launch</dt><dd><?= e((string) ($project['launch_date'] ?? '—')) ?></dd></div>
                    <div><span><?= app_icon('check') ?></span><dt>Expected completion</dt><dd><?= e((string) ($project['expected_completion_date'] ?? '—')) ?></dd></div>
                </dl>

                <?php if (!empty($project['description'])): ?><section class="detail-section detail-section--clean"><span class="eyebrow">Development overview</span><h2>About this project</h2><p><?= nl2br(e((string) $project['description'])) ?></p></section><?php endif; ?>

                <?php if ($listings !== []): ?><section class="detail-section detail-section--clean"><div class="section-heading-row section-heading-row--compact"><div><span class="eyebrow">Available inventory</span><h2>Properties in this project</h2></div><a href="<?= e(url('/properties?q=' . rawurlencode((string) $project['name']))) ?>">View all</a></div><div class="inventory-grid inventory-grid--detail"><?php foreach ($listings as $listing): ?><?= BaramaCoreView::partial('components/inventory-card', ['kind' => 'listing', 'item' => array_merge($listing, ['company_name' => $project['company_name']])]) ?><?php endforeach; ?></div></section><?php endif; ?>
            </div>

            <aside class="inquiry-card inquiry-card--sticky">
                <div class="inquiry-card__head"><span class="inquiry-card__icon"><?= app_icon('message') ?></span><div><span class="eyebrow">Contact developer</span><h2>Ask about this project</h2></div></div>
                <p>Send your question directly to the company responsible for this development.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?><input type="hidden" name="target_type" value="project"><input type="hidden" name="target_id" value="<?= e((string) $project['id']) ?>"><input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" value="<?= e((string) ($viewer['name'] ?? '')) ?>" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" value="<?= e((string) ($viewer['email'] ?? '')) ?>" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="4" required>I would like more information about <?= e((string) $project['name']) ?>.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit"><?= app_icon('message') ?><span>Send inquiry</span></button>
                </form>
                <div class="inquiry-card__company"><span><?= app_icon('building') ?></span><span><strong><?= e((string) $project['company_name']) ?></strong><small>Approved marketplace company</small></span></div>
            </aside>
        </div>
    </div>
</section>
