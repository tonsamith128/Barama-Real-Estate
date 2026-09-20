<?php
declare(strict_types=1);
?>
<section class="listing-detail company-detail">
    <div class="container app-container">
        <nav class="detail-breadcrumb"><a href="<?= e(url('/company')) ?>">Developers</a><span>/</span><span><?= e((string) $company['name']) ?></span></nav>

        <div class="company-profile-head">
            <div class="company-profile-head__media">
                <?php if ($gallery !== []): ?>
                    <img src="<?= e(url('/media/' . rawurlencode((string) $gallery[0]['uuid']))) ?>" alt="<?= e((string) ($gallery[0]['alt_text'] ?: $company['name'])) ?>">
                <?php else: ?>
                    <span><?= app_icon('building') ?></span>
                <?php endif; ?>
            </div>
            <div class="company-profile-head__content">
                <span class="detail-status">Verified marketplace company</span>
                <h1><?= e((string) $company['name']) ?></h1>
                <p><?= e(ucwords(str_replace('_', ' ', (string) $company['company_type']))) ?><?= !empty($company['location_name']) ? ' · ' . e((string) $company['location_name']) : '' ?></p>
                <div class="company-profile-head__actions">
                    <?php if (!empty($company['website'])): ?><a class="btn btn-outline-secondary" href="<?= e((string) $company['website']) ?>" rel="noopener noreferrer" target="_blank">Website</a><?php endif; ?>
                    <?php if (!empty($company['phone'])): ?><a class="btn btn-outline-secondary" href="tel:<?= e((string) $company['phone']) ?>">Call company</a><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="detail-layout">
            <div class="detail-main">
                <?php if (!empty($company['description'])): ?><section class="detail-section"><h2>About the company</h2><p><?= nl2br(e((string) $company['description'])) ?></p></section><?php endif; ?>

                <?php if ($projects !== []): ?>
                    <section class="detail-section"><div class="section-heading-row"><div><span class="eyebrow">Developments</span><h2>Published projects</h2></div><a href="<?= e(url('/projects?q=' . rawurlencode((string) $company['name']))) ?>">View all</a></div><div class="inventory-grid inventory-grid--detail"><?php foreach ($projects as $project): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'project', 'item' => array_merge($project, ['company_name' => $company['name']])]) ?><?php endforeach; ?></div></section>
                <?php endif; ?>

                <?php if ($listings !== []): ?>
                    <section class="detail-section"><div class="section-heading-row"><div><span class="eyebrow">Marketplace inventory</span><h2>Available properties</h2></div><a href="<?= e(url('/properties?q=' . rawurlencode((string) $company['name']))) ?>">View all</a></div><div class="inventory-grid inventory-grid--detail"><?php foreach ($listings as $listing): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => array_merge($listing, ['company_name' => $company['name']])]) ?><?php endforeach; ?></div></section>
                <?php endif; ?>
            </div>

            <aside class="inquiry-card">
                <span class="eyebrow">Contact company</span><h2>Send an inquiry</h2><p>Contact <?= e((string) $company['name']) ?> through the Barama marketplace.</p>
                <div data-inquiry-notice hidden></div>
                <form action="<?= e(url('/inquiries')) ?>" method="post" data-inquiry-form>
                    <?= csrf_field() ?><input type="hidden" name="target_type" value="company"><input type="hidden" name="target_id" value="<?= e((string) $company['id']) ?>"><input class="inquiry-honeypot" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                    <label><span>Name</span><input class="form-control" name="contact_name" required maxlength="160"></label>
                    <label><span>Email</span><input class="form-control" name="contact_email" type="email" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="contact_phone" maxlength="60"></label>
                    <label><span>Message</span><textarea class="form-control" name="message" rows="5" required>I would like to learn more about your properties and projects.</textarea></label>
                    <button class="btn btn-primary w-100" type="submit">Send inquiry</button>
                </form>
            </aside>
        </div>
    </div>
</section>
