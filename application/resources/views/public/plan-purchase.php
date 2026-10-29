<?php

declare(strict_types=1);

$plan = is_array($plan ?? null) ? $plan : [];
$companies = is_array($companies ?? null) ? array_values($companies) : [];

$limit = static fn (mixed $value): string =>
    $value === null ? 'Unlimited' : number_format((int) $value);

$availableCompanies = array_values(array_filter(
    $companies,
    static function (array $company): bool {
        $subscription = is_array($company['subscription'] ?? null)
            ? $company['subscription']
            : null;

        if ($subscription === null) {
            return true;
        }

        return !in_array(
            (string) ($subscription['status'] ?? ''),
            ['active', 'paused', 'pending'],
            true
        );
    }
));
?>
<?= barama_css_once('frontend') ?>
<?= barama_css_once('frontend') ?>

<section class="barama-plan-purchase">
    <a class="barama-plan-purchase__back" href="<?= e(url('/plans')) ?>">← Back to plans</a>

    <?php if (!empty($purchaseSuccess)): ?>
        <div class="barama-plan-purchase__notice is-success">
            <strong>Purchase request submitted</strong>
            <span><?= e((string) $purchaseSuccess) ?></span>
            <?php if (!empty($purchaseReference)): ?>
                <small>Reference: <?= e((string) $purchaseReference) ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($purchaseError)): ?>
        <div class="barama-plan-purchase__notice is-error">
            <strong>Unable to submit purchase request</strong>
            <span><?= e((string) $purchaseError) ?></span>
        </div>
    <?php endif; ?>

    <div class="barama-plan-purchase__layout">
        <article class="barama-public-plan is-featured barama-plan-purchase__summary">
            <div class="barama-public-plan__head">
                <span><?= e((string) ($plan['name'] ?? 'Plan')) ?></span>
                <small>Selected plan</small>
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

            <div class="barama-public-plan__price">
                <span><?= e(strtoupper((string) ($plan['currency'] ?? 'USD'))) ?></span>
                <strong><?= e(number_format((float) ($plan['price'] ?? 0), 0)) ?></strong>
                <small>/ <?= e(str_replace('_', ' ', (string) ($plan['billing_interval'] ?? 'monthly'))) ?></small>
            </div>
        </article>

        <section class="barama-plan-purchase__panel">
            <span class="barama-plans-public__eyebrow">Purchase confirmation</span>
            <h1>Select the company for this plan.</h1>
            <p>
                A purchase request does not activate paid entitlements.
                Billing must confirm payment before the subscription becomes active.
            </p>

            <?php if ($companies === []): ?>
                <div class="barama-plan-purchase__empty">
                    <strong>No Company / Vendor workspace is available.</strong>
                    <span>Create or join a company first, then return to Plans &amp; Packages.</span>
                    <a href="<?= e(url('/developer')) ?>">Open Company / Vendor workspace</a>
                </div>
            <?php elseif ($availableCompanies === []): ?>
                <div class="barama-plan-purchase__empty">
                    <strong>No company is currently eligible for a new purchase.</strong>
                    <span>
                        A company with an active, paused, or pending subscription
                        must resolve that subscription before choosing another plan.
                    </span>
                    <a href="<?= e(url('/developer/billing')) ?>">Review Billing &amp; Plan</a>
                </div>
            <?php else: ?>
                <form
                    class="barama-plan-purchase__form"
                    action="<?= e(url('/developer/plan-purchase/' . (int) ($plan['id'] ?? 0))) ?>"
                    method="post"
                >
                    <?= csrf_field() ?>

                    <label>
                        <span>Company</span>
                        <select name="company_id" required>
                            <option value="">Select company</option>
                            <?php foreach ($availableCompanies as $company): ?>
                                <option value="<?= e((string) ($company['id'] ?? '')) ?>">
                                    <?= e((string) ($company['name'] ?? 'Company')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="barama-plan-purchase__terms">
                        <strong>What happens next?</strong>
                        <span>
                            The request appears in Admin → Subscriptions as
                            Pending / self_service. After payment confirmation,
                            billing can activate the subscription.
                        </span>
                    </div>

                    <button type="submit">
                        <span>Submit purchase request</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>
