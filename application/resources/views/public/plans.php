<?php

declare(strict_types=1);

$plans = is_array($plans ?? null) ? array_values($plans) : [];
$limit = static fn (mixed $value): string =>
    $value === null ? 'Unlimited' : number_format((int) $value);

$interval = static function (string $value): string {
    return match ($value) {
        'monthly' => 'month',
        'quarterly' => 'quarter',
        'yearly' => 'year',
        'lifetime' => 'one time',
        'manual' => 'manual',
        default => str_replace('_', ' ', $value),
    };
};

$featuredSlug = null;
foreach ($plans as $plan) {
    if (strtolower((string) ($plan['slug'] ?? '')) === 'pro') {
        $featuredSlug = 'pro';
        break;
    }
}
if ($featuredSlug === null && $plans !== []) {
    $middle = (int) floor((count($plans) - 1) / 2);
    $featuredSlug = strtolower((string) ($plans[$middle]['slug'] ?? ''));
}
?>
<link rel="stylesheet" href="<?= e(asset('css/plans-packages-v140.css') . '?v=140') ?>">
<link rel="stylesheet" href="<?= e(asset('css/plans-packages-v143.css') . '?v=143') ?>">

<section class="barama-plans-public">
    <header class="barama-plans-public__hero">
        <span class="barama-plans-public__eyebrow">Plans &amp; Packages</span>
        <h1>Choose the capacity that fits your real-estate business.</h1>
        <p>
            Compare the active Barama plans using the same pricing and
            entitlement limits managed by the billing system.
        </p>
    </header>

    <?php if ($plans === []): ?>
        <div class="barama-plans-public__empty">
            No public plans are active right now.
        </div>
    <?php else: ?>
        <div class="barama-plans-public__grid">
            <?php foreach ($plans as $plan): ?>
                <?php
                $slug = strtolower((string) ($plan['slug'] ?? ''));
                $featured = $slug === $featuredSlug;
                ?>
                <article class="barama-public-plan<?= $featured ? ' is-featured' : '' ?>">
                    <div class="barama-public-plan__head">
                        <span><?= e((string) ($plan['name'] ?? 'Plan')) ?></span>
                        <?php if ($featured): ?><small>Featured</small><?php endif; ?>
                    </div>

                    <?php if (trim((string) ($plan['description'] ?? '')) !== ''): ?>
                        <p class="barama-public-plan__description">
                            <?= e((string) $plan['description']) ?>
                        </p>
                    <?php endif; ?>

                    <ul class="barama-public-plan__features">
                        <li><strong><?= e($limit($plan['listing_limit'] ?? null)) ?></strong> Listings</li>
                        <li><strong><?= e($limit($plan['property_limit'] ?? null)) ?></strong> Properties</li>
                        <li><strong><?= e($limit($plan['project_limit'] ?? null)) ?></strong> Projects</li>
                        <li><strong><?= e($limit($plan['team_member_limit'] ?? null)) ?></strong> Team members</li>
                        <li><strong><?= e($limit($plan['featured_listing_limit'] ?? null)) ?></strong> Featured listings</li>
                    </ul>

                    <a
                        class="barama-public-plan__select"
                        href="<?= e(url('/developer/plan-purchase/' . (int) $plan['id'])) ?>"
                    >
                        <span>Choose <?= e((string) ($plan['name'] ?? 'plan')) ?></span>
                        <span aria-hidden="true">→</span>
                    </a>

                    <div class="barama-public-plan__price">
                        <span><?= e(strtoupper((string) ($plan['currency'] ?? 'USD'))) ?></span>
                        <strong><?= e(number_format((float) ($plan['price'] ?? 0), 0)) ?></strong>
                        <small>/ <?= e($interval((string) ($plan['billing_interval'] ?? 'monthly'))) ?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="barama-plans-public__note">
        <strong>Purchase workflow</strong>
        <span>
            Sign in, select the company that will use the plan, and submit the
            purchase request. The subscription remains pending until payment is
            confirmed and billing activates it.
        </span>
    </div>
</section>
