<?php
declare(strict_types=1);
?>
<section class="listing-detail">
    <div class="container app-container">
        <nav class="detail-breadcrumb"><a href="<?= e(url('/projects')) ?>">Projects</a><span>/</span><span><?= e((string) $project['name']) ?></span></nav>
        <div class="detail-gallery">
            <?php if ($gallery !== []): ?>
                <?php foreach (array_slice($gallery, 0, 5) as $index => $media): ?>
                    <figure class="<?= $index === 0 ? 'detail-gallery__primary' : '' ?>"><img src="<?= e(url('/media/' . rawurlencode((string) $media['uuid']))) ?>" alt="<?= e((string) ($media['alt_text'] ?: $project['name'])) ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>></figure>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="detail-gallery__empty"><?= app_icon('layers') ?><span>Project imagery will appear here when available.</span></div>
            <?php endif; ?>
        </div>

        <div class="detail-layout">
            <div class="detail-main">
                <span class="detail-status">Published development</span>
                <h1><?= e((string) $project['name']) ?></h1>
                <p class="detail-location"><?= e((string) ($project['location_name'] ?? $project['address_line'] ?? '')) ?></p>
                <dl class="detail-facts">
                    <div><dt>Type</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $project['project_type']))) ?></dd></div>
                    <div><dt>Stage</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) $project['completion_status']))) ?></dd></div>
                    <div><dt>Launch</dt><dd><?= e((string) ($project['launch_date'] ?? '—')) ?></dd></div>
                    <div><dt>Expected completion</dt><dd><?= e((string) ($project['expected_completion_date'] ?? '—')) ?></dd></div>
                </dl>
                <?php if (!empty($project['description'])): ?><section class="detail-section"><h2>About this development</h2><p><?= nl2br(e((string) $project['description'])) ?></p></section><?php endif; ?>
                <section class="detail-section">
                    <h2>Developer</h2>
                    <a class="detail-company-link" href="<?= e(url('/company/' . rawurlencode((string) $project['company_slug']))) ?>"><?= app_icon('building') ?><span><strong><?= e((string) $project['company_name']) ?></strong><small>View company profile</small></span></a>
                </section>
                <?php if ($listings !== []): ?>
                    <section class="detail-section">
                        <div class="section-heading-row"><div><span class="eyebrow">Available inventory</span><h2>Properties in this project</h2></div></div>
                        <div class="inventory-grid inventory-grid--detail">
                            <?php foreach ($listings as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $listing]) ?><?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
            <aside class="inquiry-card">
                <span class="eyebrow">Contact developer</span><h2>Ask about this project</h2><p>Send your question to the company responsible for this development.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?><input type="hidden" name="target_type" value="project"><input type="hidden" name="target_id" value="<?= e((string) $project['id']) ?>"><input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="5" required>I would like more information about <?= e((string) $project['name']) ?>.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit">Send inquiry</button>
                </form>
            </aside>
        </div>
    </div>
</section>
