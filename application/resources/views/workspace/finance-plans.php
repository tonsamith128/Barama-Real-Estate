<?php

declare(strict_types=1);

$plans = is_array($plans ?? null) ? array_values($plans) : [];
$summary = is_array($financeSummary ?? null) ? $financeSummary : [];

$money = static function (mixed $amount, mixed $currency): string {
    return strtoupper((string) $currency)
        . ' '
        . number_format((float) $amount, 2);
};

$limit = static fn (mixed $value): string =>
    $value === null ? 'Unlimited' : number_format((int) $value);

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'active', 'inactive', 'archived'], true)) {
    $statusFilter = '';
}

$filteredPlans = array_values(array_filter(
    $plans,
    static function (array $plan) use ($q, $statusFilter): bool {
        if (
            $statusFilter !== ''
            && strtolower((string) ($plan['status'] ?? '')) !== $statusFilter
        ) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($plan['name'] ?? '')
            . ' '
            . (string) ($plan['slug'] ?? '')
            . ' '
            . (string) ($plan['description'] ?? '')
        );

        return str_contains(
            $haystack,
            strtolower($q)
        );
    }
));

$perPage = 10;
$total = count($filteredPlans);
$pages = max(1, (int) ceil($total / $perPage));
$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$page = $page === false ? 1 : min((int) $page, $pages);
$visiblePlans = array_slice(
    $filteredPlans,
    ($page - 1) * $perPage,
    $perPage
);

$query = array_filter(
    [
        'q' => $q,
        'status' => $statusFilter,
    ],
    static fn (string $value): bool => $value !== ''
);

$pageUrl = static function (int $target) use ($query): string {
    return url(
        '/dashboard/plans?'
        . http_build_query(
            array_merge(
                $query,
                ['page' => max(1, $target)]
            )
        )
    );
};

$activeCount = count(array_filter(
    $plans,
    static fn (array $plan): bool =>
        (string) ($plan['status'] ?? '') === 'active'
));
$inactiveCount = count(array_filter(
    $plans,
    static fn (array $plan): bool =>
        (string) ($plan['status'] ?? '') === 'inactive'
));
$archivedCount = count(array_filter(
    $plans,
    static fn (array $plan): bool =>
        (string) ($plan['status'] ?? '') === 'archived'
));
$subscriptionCount = array_sum(array_map(
    static fn (array $plan): int =>
        (int) ($plan['current_subscriptions'] ?? 0),
    $plans
));

$presets = [
    'basic' => [
        'name' => 'Basic',
        'slug' => 'basic',
        'description' =>
            'Essential marketplace access for small agencies and focused property teams.',
        'billing_interval' => 'monthly',
        'price' => '29.00',
        'currency' => 'USD',
        'listing_limit' => 10,
        'property_limit' => 20,
        'project_limit' => 2,
        'team_member_limit' => 3,
        'featured_listing_limit' => 2,
        'sort_order' => 10,
    ],
    'pro' => [
        'name' => 'Pro',
        'slug' => 'pro',
        'description' =>
            'Expanded marketplace capacity for growing real-estate businesses and multi-project teams.',
        'billing_interval' => 'monthly',
        'price' => '79.00',
        'currency' => 'USD',
        'listing_limit' => 50,
        'property_limit' => 100,
        'project_limit' => 10,
        'team_member_limit' => 10,
        'featured_listing_limit' => 10,
        'sort_order' => 20,
    ],
    'premium' => [
        'name' => 'Premium',
        'slug' => 'premium',
        'description' =>
            'High-capacity commercial access for established agencies, developers, and larger teams.',
        'billing_interval' => 'monthly',
        'price' => '149.00',
        'currency' => 'USD',
        'listing_limit' => 200,
        'property_limit' => 500,
        'project_limit' => 50,
        'team_member_limit' => 30,
        'featured_listing_limit' => 50,
        'sort_order' => 30,
    ],
];

$slugs = array_map(
    static fn (array $plan): string =>
        strtolower((string) ($plan['slug'] ?? '')),
    $plans
);
?>
<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-plans.css') . '?v=140') ?>"
>
<script
    src="<?= e(asset('js/finance-plans-v139.js') . '?v=139') ?>"
    defer
></script>

<link rel="stylesheet" href="<?= e(asset('css/plans-packages.css') . '?v=141') ?>">

<link rel="stylesheet" href="<?= e(asset('css/finance-plans-section-cleanup-v145.css') . '?v=145') ?>">

<link rel="stylesheet" href="<?= e(asset('css/finance-plans-typography-v146.css') . '?v=146') ?>">

<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-plans-focus-v180.css') . '?v=180') ?>"
>
<link rel="stylesheet" href="<?= e(asset('css/finance-plans-premium-white-v181.css') . '?v=181') ?>">
<script src="<?= e(asset('js/finance-plans-actions-v181.js') . '?v=181') ?>" defer></script>
<link rel="stylesheet" href="<?= e(asset('css/finance-plans-modal-theme-v182.css') . '?v=182') ?>">

<section class="workspace-page finance-plans" data-plans-workspace>
    <!-- BARAMA_PLANS_VERTICAL_GLOBAL_OVERFLOW_V100 -->
    <header class="finance-plans__hero">
        <div>
            <span class="eyebrow">
                <?= e($workspaceLabel ?? 'Admin Control Center') ?>
            </span>
            <h1>Plans &amp; Packages</h1>
            <p>
                Configure commercial entitlements independently from payment
                providers. Plans begin inactive so pricing and limits can be
                reviewed before assignment.
            </p>
        </div>

        <button
            class="btn btn-primary finance-plans__create"
            type="button"
            data-plan-open
        >
            <?= app_icon('plus') ?>
            <span>Create Plan</span>
        </button>
    </header>

    <div data-workspace-notice hidden></div>

    <section class="finance-plans__stats" aria-label="Plan overview">
        <article>
            <span><?= app_icon('layers') ?></span>
            <div>
                <strong><?= number_format(count($plans)) ?></strong>
                <small>Total plans</small>
            </div>
        </article>
        <article>
            <span><?= app_icon('check') ?></span>
            <div>
                <strong><?= number_format($activeCount) ?></strong>
                <small>Active plans</small>
            </div>
        </article>
        <article>
            <span><?= app_icon('building') ?></span>
            <div>
                <strong><?= number_format($subscriptionCount) ?></strong>
                <small>Current subscriptions</small>
            </div>
        </article>
        <article>
            <span><?= app_icon('layers') ?></span>
            <div>
                <strong><?= number_format($inactiveCount + $archivedCount) ?></strong>
                <small>Inactive / archived</small>
            </div>
        </article>
    </section>

    <section class="finance-plans__tiers">
        <div class="finance-plans__section-head">
            <div>
                <h2>Pricing plan presets</h2>
                <p>
                    Basic, Pro, and Premium are the standard Barama commercial
                    starting points. Every value remains editable.
                </p>
            </div>
        </div>

        <div class="finance-plans__tier-grid">
            <?php foreach ($presets as $key => $preset): ?>
                <?php
                /* BARAMA_EDITABLE_CONFIGURED_PRESETS_V147 */
                $configuredPlan = null;
                foreach ($plans as $candidatePlan) {
                    if (strtolower((string) ($candidatePlan['slug'] ?? '')) === strtolower((string) $preset['slug'])) {
                        $configuredPlan = $candidatePlan;
                        break;
                    }
                }
                $configured = is_array($configuredPlan);
                $configuredStatus = $configured ? (string) ($configuredPlan['status'] ?? 'inactive') : null;
                $editableConfigured = $configured && $configuredStatus !== 'archived';
                $displayPlan = $configured
                    ? array_merge($preset, [
                        'name' => (string) ($configuredPlan['name'] ?? $preset['name']),
                        'description' => (string) ($configuredPlan['description'] ?? $preset['description']),
                        'billing_interval' => (string) ($configuredPlan['billing_interval'] ?? $preset['billing_interval']),
                        'price' => (string) ($configuredPlan['price'] ?? $preset['price']),
                        'currency' => (string) ($configuredPlan['currency'] ?? $preset['currency']),
                        'listing_limit' => $configuredPlan['listing_limit'] ?? null,
                        'property_limit' => $configuredPlan['property_limit'] ?? null,
                        'project_limit' => $configuredPlan['project_limit'] ?? null,
                        'team_member_limit' => $configuredPlan['team_member_limit'] ?? null,
                        'featured_listing_limit' => $configuredPlan['featured_listing_limit'] ?? null,
                        'sort_order' => (int) ($configuredPlan['sort_order'] ?? $preset['sort_order']),
                    ])
                    : $preset;
                $configuredPayload = $configured
                    ? [
                        'id' => (int) ($configuredPlan['id'] ?? 0),
                        'name' => (string) ($configuredPlan['name'] ?? ''),
                        'slug' => (string) ($configuredPlan['slug'] ?? ''),
                        'description' => (string) ($configuredPlan['description'] ?? ''),
                        'billing_interval' => (string) ($configuredPlan['billing_interval'] ?? 'monthly'),
                        'price' => (string) ($configuredPlan['price'] ?? '0.00'),
                        'currency' => (string) ($configuredPlan['currency'] ?? 'USD'),
                        'listing_limit' => $configuredPlan['listing_limit'] ?? '',
                        'property_limit' => $configuredPlan['property_limit'] ?? '',
                        'project_limit' => $configuredPlan['project_limit'] ?? '',
                        'team_member_limit' => $configuredPlan['team_member_limit'] ?? '',
                        'featured_listing_limit' => $configuredPlan['featured_listing_limit'] ?? '',
                        'sort_order' => (int) ($configuredPlan['sort_order'] ?? 0),
                        'status' => $configuredStatus,
                    ]
                    : null;
                $currency = strtoupper((string) ($displayPlan['currency'] ?? 'USD'));
                $currencyPrefix = match ($currency) {
                    'USD' => '$',
                    'EUR' => '€',
                    'GBP' => '£',
                    'JPY', 'CNY' => '¥',
                    default => $currency . ' ',
                };
                $intervalLabel = match (strtolower((string) ($displayPlan['billing_interval'] ?? 'monthly'))) {
                    'monthly' => '/ month',
                    'quarterly' => '/ quarter',
                    'yearly' => '/ year',
                    'lifetime' => 'one time',
                    'manual' => 'manual',
                    default => '/ ' . strtolower((string) ($displayPlan['billing_interval'] ?? 'month')),
                };
                ?>
                <article class="finance-plan-tier finance-plan-tier--<?= e($key) ?>">
                    <div class="finance-plan-tier__top">
                        <span class="finance-plan-tier__label">
                            <?= e((string) $displayPlan['name']) ?>
                        </span>
                        <span
                            class="finance-plan-tier__state <?= $configured ? 'is-configured' : '' ?>"
                        >
                            <?= $configured ? 'Configured' : 'Preset' ?>
                        </span>
                    </div>

                    <div class="finance-plan-tier__price">
                        <strong><?= e($currencyPrefix) ?><?= e(number_format((float) $displayPlan['price'], 0)) ?></strong>
                        <span><?= e($intervalLabel) ?></span>
                    </div>

                    <p><?= e((string) $displayPlan['description']) ?></p>

                    <div class="finance-plan-tier__limits">
                        <span>
                            <strong><?= e($limit($displayPlan['listing_limit'] ?? null)) ?></strong>
                            Listings
                        </span>
                        <span>
                            <strong><?= e($limit($displayPlan['property_limit'] ?? null)) ?></strong>
                            Properties
                        </span>
                        <span>
                            <strong><?= e($limit($displayPlan['project_limit'] ?? null)) ?></strong>
                            Projects
                        </span>
                        <span>
                            <strong><?= e($limit($displayPlan['team_member_limit'] ?? null)) ?></strong>
                            Team
                        </span>
                        <span>
                            <strong><?= e($limit($displayPlan['featured_listing_limit'] ?? null)) ?></strong>
                            Featured
                        </span>
                    </div>

                    <?php if (!$configured): ?>
                        <button
                            class="btn btn-primary"
                            type="button"
                            data-plan-preset="<?= e($key) ?>"
                        >
                            Create from preset
                        </button>
                    <?php elseif ($editableConfigured): ?>
                        <button
                            class="btn btn-primary"
                            type="button"
                            data-plan-edit="<?= e(json_encode(
                                $configuredPayload,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            )) ?>"
                            title="Edit <?= e((string) $displayPlan['name']) ?> plan"
                        >
                            Edit plan
                        </button>
                    <?php else: ?>
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            disabled
                            title="Archived plans are read-only"
                        >
                            Archived · read-only
                        </button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="finance-plans__library">
        <div class="finance-plans__section-head">
            <div>
                <h2>Configured plans</h2>
                <p>
                    10 plans per page. Activate only after pricing,
                    entitlements, and billing interval are reviewed.
                </p>
            </div>
            <span>
                <?= number_format($total) ?> matching plan<?= $total === 1 ? '' : 's' ?>
            </span>
        </div>

        <form
            class="finance-plans__filters"
            method="get"
            action="<?= e(url('/dashboard/plans')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search plan, slug, or description"
                >
            </label>

            <select name="status" aria-label="Plan status">
                <option value="">All statuses</option>
                <?php foreach ([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'archived' => 'Archived',
                ] as $value => $label): ?>
                    <option
                        value="<?= e($value) ?>"
                        <?= $statusFilter === $value ? 'selected' : '' ?>
                    ><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">Apply</button>
            <a
                class="btn btn-outline-secondary"
                href="<?= e(url('/dashboard/plans')) ?>"
            >Reset</a>
        </form>

        <div class="finance-plans__table-wrap">
            <table class="finance-plans__table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plan</th>
                        <th>Price</th>
                        <th>Interval</th>
                        <th>Listings</th>
                        <th>Properties</th>
                        <th>Projects</th>
                        <th>Team</th>
                        <th>Featured</th>
                        <th>Subscriptions</th>
                        <th>Status</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visiblePlans === []): ?>
                        <tr>
                            <td colspan="12">
                                <div class="finance-plans__empty">
                                    <?= app_icon('layers') ?>
                                    <strong>No plans match this view</strong>
                                    <span>
                                        Create a plan or adjust the current filters.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($visiblePlans as $index => $plan): ?>
                        <?php
                        $status = (string) ($plan['status'] ?? 'inactive');
                        $number = (($page - 1) * $perPage) + $index + 1;
                        $payload = [
                            'id' => (int) ($plan['id'] ?? 0),
                            'name' => (string) ($plan['name'] ?? ''),
                            'slug' => (string) ($plan['slug'] ?? ''),
                            'description' => (string) ($plan['description'] ?? ''),
                            'billing_interval' => (string) ($plan['billing_interval'] ?? 'monthly'),
                            'price' => (string) ($plan['price'] ?? '0.00'),
                            'currency' => (string) ($plan['currency'] ?? 'USD'),
                            'listing_limit' => $plan['listing_limit'] ?? '',
                            'property_limit' => $plan['property_limit'] ?? '',
                            'project_limit' => $plan['project_limit'] ?? '',
                            'team_member_limit' => $plan['team_member_limit'] ?? '',
                            'featured_listing_limit' => $plan['featured_listing_limit'] ?? '',
                            'sort_order' => (int) ($plan['sort_order'] ?? 0),
                            'status' => $status,
                        ];
                        ?>
                        <tr>
                            <td>
                                <span class="finance-plans__number">
                                    <?= $number ?>
                                </span>
                            </td>
                            <td>
                                <strong class="finance-plans__name">
                                    <?= e((string) ($plan['name'] ?? 'Untitled')) ?>
                                </strong>
                                <small><?= e((string) ($plan['slug'] ?? '')) ?></small>
                            </td>
                            <td>
                                <strong>
                                    <?= e($money(
                                        $plan['price'] ?? 0,
                                        $plan['currency'] ?? 'USD'
                                    )) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e(ucwords(str_replace(
                                    '_',
                                    ' ',
                                    (string) ($plan['billing_interval'] ?? '')
                                ))) ?>
                            </td>
                            <td><?= e($limit($plan['listing_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['property_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['project_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['team_member_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['featured_listing_limit'] ?? null)) ?></td>
                            <td><?= number_format((int) ($plan['current_subscriptions'] ?? 0)) ?></td>
                            <td>
                                <span class="finance-plans__status finance-plans__status--<?= e($status) ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Plan actions" title="Actions">
                                        <span aria-hidden="true">&#8942;</span>
                                    </summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status !== 'archived'): ?>
                                            <button
                                                class="table-action"
                                                type="button"
                                                data-plan-edit="<?= e(json_encode(
                                                    $payload,
                                                    JSON_UNESCAPED_SLASHES
                                                    | JSON_UNESCAPED_UNICODE
                                                )) ?>"
                                            >Edit plan</button>

                                            <button
                                                class="table-action"
                                                type="button"
                                                data-plan-duplicate="<?= e(json_encode(
                                                    $payload,
                                                    JSON_UNESCAPED_SLASHES
                                                    | JSON_UNESCAPED_UNICODE
                                                )) ?>"
                                            >Duplicate</button>
                                        <?php endif; ?>

                                        <?php if ($status === 'inactive'): ?>
                                            <form
                                                action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>"
                                                method="post"
                                                data-workspace-form
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="active">
                                                <button
                                                    class="table-action table-action--primary"
                                                    type="submit"
                                                >Activate</button>
                                            </form>
                                            <form
                                                action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>"
                                                method="post"
                                                data-workspace-form
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="archived">
                                                <button
                                                    class="table-action table-action--danger"
                                                    type="submit"
                                                >Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form
                                                action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>"
                                                method="post"
                                                data-workspace-form
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="inactive">
                                                <button
                                                    class="table-action"
                                                    type="submit"
                                                >Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">
                                                Archived
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="finance-plans__pager">
            <span>
                Page <?= $page ?> of <?= $pages ?>
                · <?= $total ?> plan<?= $total === 1 ? '' : 's' ?>
                · 10 per page
            </span>

            <?php if ($pages > 1): ?>
                <nav aria-label="Plan pages">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php
                    $from = max(1, $page - 2);
                    $to = min($pages, $page + 2);
                    ?>
                    <?php for ($i = $from; $i <= $to; $i++): ?>
                        <a
                            href="<?= e($pageUrl($i)) ?>"
                            class="<?= $i === $page ? 'is-active' : '' ?>"
                        ><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="<?= e($pageUrl($page + 1)) ?>">Next</a>
                    <?php endif; ?>
                </nav>

                <form method="get" action="<?= e(url('/dashboard/plans')) ?>">
                    <?php foreach ($query as $key => $value): ?>
                        <input
                            type="hidden"
                            name="<?= e((string) $key) ?>"
                            value="<?= e((string) $value) ?>"
                        >
                    <?php endforeach; ?>
                    <label>
                        <span>Go To</span>
                        <input
                            type="number"
                            name="page"
                            min="1"
                            max="<?= $pages ?>"
                            value="<?= $page ?>"
                        >
                    </label>
                    <button type="submit">Go</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</section>

<div
    class="finance-plan-modal"
    data-plan-modal
    hidden
    aria-hidden="true"
>
    <button
        class="finance-plan-modal__backdrop"
        type="button"
        data-plan-close
        aria-label="Close plan configuration"
    ></button>

    <section
        class="finance-plan-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="plan-modal-title"
    >
        <header>
            <div>
                <span class="eyebrow">Billing configuration</span>
                <h2 id="plan-modal-title" data-plan-title>Create Plan</h2>
                <p data-plan-subtitle>
                    Configure pricing and commercial entitlements.
                </p>
            </div>

            <button
                class="finance-plan-modal__close"
                type="button"
                data-plan-close
                aria-label="Close"
            >×</button>
        </header>

        <div class="finance-plan-modal__body">
            <div class="finance-plan-modal__presets">
                <span>Start from preset</span>
                <?php foreach ($presets as $key => $preset): ?>
                    <button
                        type="button"
                        data-plan-preset="<?= e($key) ?>"
                    ><?= e($preset['name']) ?></button>
                <?php endforeach; ?>
                <button type="button" data-plan-preset="custom">Custom</button>
            </div>

            <form
                action="<?= e(url('/dashboard/plans')) ?>"
                method="post"
                data-workspace-form
                data-plan-form
                class="finance-plan-form"
            >
                <?= csrf_field() ?>

                <div class="finance-plan-form__grid">
                    <label>
                        <span>Plan name</span>
                        <input
                            class="form-control"
                            name="name"
                            maxlength="160"
                            required
                        >
                    </label>

                    <label>
                        <span>Slug</span>
                        <input
                            class="form-control"
                            name="slug"
                            maxlength="160"
                            placeholder="auto-from-name"
                        >
                    </label>

                    <label>
                        <span>Billing interval</span>
                        <select
                            class="form-select"
                            name="billing_interval"
                            required
                        >
                            <?php foreach ([
                                'monthly',
                                'quarterly',
                                'yearly',
                                'lifetime',
                                'manual',
                            ] as $interval): ?>
                                <option value="<?= e($interval) ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $interval))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Price</span>
                        <input
                            class="form-control"
                            name="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value="0.00"
                            required
                        >
                    </label>

                    <label>
                        <span>Currency</span>
                        <input
                            class="form-control"
                            name="currency"
                            maxlength="3"
                            value="USD"
                            required
                        >
                    </label>

                    <label>
                        <span>Sort order</span>
                        <input
                            class="form-control"
                            name="sort_order"
                            type="number"
                            value="0"
                        >
                    </label>
                </div>

                <div class="finance-plan-form__section">
                    <div>
                        <strong>Commercial entitlements</strong>
                        <small>
                            Leave a limit blank for unlimited access.
                        </small>
                    </div>

                    <div class="finance-plan-form__limits">
                        <label>
                            <span>Listings</span>
                            <input
                                class="form-control"
                                name="listing_limit"
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                            >
                        </label>
                        <label>
                            <span>Properties</span>
                            <input
                                class="form-control"
                                name="property_limit"
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                            >
                        </label>
                        <label>
                            <span>Projects</span>
                            <input
                                class="form-control"
                                name="project_limit"
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                            >
                        </label>
                        <label>
                            <span>Team members</span>
                            <input
                                class="form-control"
                                name="team_member_limit"
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                            >
                        </label>
                        <label>
                            <span>Featured listings</span>
                            <input
                                class="form-control"
                                name="featured_listing_limit"
                                type="number"
                                min="0"
                                placeholder="Unlimited"
                            >
                        </label>
                    </div>
                </div>

                <label>
                    <span>Description</span>
                    <textarea
                        class="form-control"
                        name="description"
                        rows="4"
                        maxlength="5000"
                    ></textarea>
                </label>

                <div class="finance-plan-form__notice">
                    <?= app_icon('shield') ?>
                    <span data-plan-notice>
                        New plans are created inactive. Review them before activation.
                    </span>
                </div>

                <footer>
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        data-plan-close
                    >Cancel</button>
                    <button
                        class="btn btn-primary"
                        type="submit"
                        data-plan-submit
                    >
                        <?= app_icon('plus') ?>
                        <span data-plan-submit-label>Create inactive plan</span>
                    </button>
                </footer>
            </form>
        </div>
    </section>
</div>

<script type="application/json" id="finance-plan-presets">
<?= json_encode(
    $presets,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
) ?>
</script>
