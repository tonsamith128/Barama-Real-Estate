<?php

declare(strict_types=1);

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$plans = is_array($plans ?? null) ? $plans : [];
$companies = is_array($companies ?? null) ? $companies : [];
$summary = is_array($financeSummary ?? null) ? $financeSummary : [];

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'active', 'paused', 'cancelled', 'expired'], true)) {
    $statusFilter = '';
}

$filteredSubscriptions = array_values(array_filter(
    $subscriptions,
    static function (array $subscription) use ($q, $statusFilter): bool {
        if (
            $statusFilter !== ''
            && strtolower((string) ($subscription['status'] ?? '')) !== $statusFilter
        ) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($subscription['company_name'] ?? '')
            . ' '
            . (string) ($subscription['plan_name'] ?? '')
            . ' '
            . (string) ($subscription['source'] ?? '')
            . ' '
            . (string) ($subscription['external_reference'] ?? '')
        );

        return str_contains($haystack, strtolower($q));
    }
));

$perPage = 10;
$total = count($filteredSubscriptions);
$pages = max(1, (int) ceil($total / $perPage));
$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$page = $page === false ? 1 : min((int) $page, $pages);

$visibleSubscriptions = array_slice(
    $filteredSubscriptions,
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
        '/dashboard/subscriptions?'
        . http_build_query(
            array_merge(
                $query,
                ['page' => max(1, $target)]
            )
        )
    );
};
?>
<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-subscriptions-pagination-v182.css') . '?v=182') ?>"
>

<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-subscriptions-focus-v183.css') . '?v=183') ?>"
>

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--subscriptions" data-subscriptions-workspace>
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Subscriptions</h1>
            <p>Assign active plans to companies and control the manual subscription lifecycle before payment gateways are introduced.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Provider-neutral subscription lifecycle
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_subscriptions'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['paused_subscriptions'] ?? 0))) ?></strong><small>Paused</small></div>
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['ended_subscriptions'] ?? 0))) ?></strong><small>Ended</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Assign company subscription</span></summary>
        <form action="<?= e(url('/dashboard/subscriptions')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>
            <div class="workspace-form__grid">
                <label><span>Company</span>
                    <select class="form-select" name="company_id" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) ($company['current_subscription_count'] ?? 0) > 0 ? 'disabled' : '' ?>>
                                <?= e((string) $company['name']) ?><?= (int) ($company['current_subscription_count'] ?? 0) > 0 ? ' · current subscription exists' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Active plan</span>
                    <select class="form-select" name="plan_id" required>
                        <option value="">Select plan</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= e((string) $plan['id']) ?>">
                                <?= e((string) $plan['name']) ?> · <?= e((string) $plan['currency']) ?> <?= e(number_format((float) $plan['price'], 2)) ?> / <?= e((string) $plan['billing_interval']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Starts at</span><input class="form-control" name="starts_at" type="datetime-local"></label>
                <label><span>Renews at</span><input class="form-control" name="renews_at" type="datetime-local"></label>
                <label><span>Expires at</span><input class="form-control" name="expires_at" type="datetime-local"></label>
                <label><span>External reference</span><input class="form-control" name="external_reference" maxlength="191" placeholder="Optional manual/provider reference"></label>
                <label class="workspace-form__span-2"><span>Internal notes</span><textarea class="form-control" name="notes" rows="3" maxlength="5000"></textarea></label>
            </div>
            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Assign active subscription</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Company subscriptions</h2>
                <p>Cancelled and expired records are retained as immutable lifecycle history.</p>
            </div>
        </div>

        
        <form
            class="finance-subscriptions__filters"
            method="get"
            action="<?= e(url('/dashboard/subscriptions')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search company, plan, source, or reference"
                >
            </label>

            <select class="form-select" name="status" aria-label="Subscription status">
                <option value="">All statuses</option>
                <?php foreach ([
                    'active' => 'Active',
                    'paused' => 'Paused',
                    'cancelled' => 'Cancelled',
                    'expired' => 'Expired',
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
                href="<?= e(url('/dashboard/subscriptions')) ?>"
            >Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table finance-subscriptions__table-focus">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Plan</th>
                        <th>Lifecycle</th>
                        <th>Billing Context</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleSubscriptions === []): ?>
                        <tr><td colspan="6"><div class="workspace-record-empty">No company subscriptions exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleSubscriptions as $subscription): ?>
                        <?php $status = (string) ($subscription['status'] ?? 'active'); ?>
                        <tr>
                            <td>
                                <div class="finance-subscriptions__company">
                                    <strong><?= e((string) $subscription['company_name']) ?></strong>
                                    <small><?= e((string) $subscription['company_status']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-subscriptions__plan">
                                    <strong><?= e((string) $subscription['plan_name']) ?></strong>
                                    <small>
                                        <?= e((string) $subscription['currency']) ?>
                                        <?= e(number_format((float) $subscription['price'], 2)) ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-subscriptions__lifecycle">
                                    <span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'paused' ? 'pending' : 'neutral')) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                    <div class="finance-subscriptions__lifecycle-meta">
                                        <small>Starts: <?= e((string) $subscription['starts_at']) ?></small>
                                        <small>Renews: <?= e((string) ($subscription['renews_at'] ?? '—')) ?></small>
                                        <small>Expires: <?= e((string) ($subscription['expires_at'] ?? '—')) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="finance-subscriptions__billing-context">
                                    <strong><?= e((string) $subscription['source']) ?></strong>
                                    <div class="finance-subscriptions__billing-meta">
                                        <small>Reference: <?= e((string) ($subscription['external_reference'] ?? '—')) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e((string) $subscription['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Subscription actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'pending'): ?>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate after payment</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                <button class="table-action table-action--danger" type="submit">Cancel request</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="paused">
                                                <button class="table-action" type="submit">Pause</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                <button class="table-action table-action--danger" type="submit">Cancel</button>
                                            </form>
                                        <?php elseif ($status === 'paused'): ?>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Resume</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                <button class="table-action table-action--danger" type="submit">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Lifecycle ended</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="finance-subscriptions__pager">
            <span>
                Page <?= $page ?> of <?= $pages ?>
                · <?= $total ?> subscription<?= $total === 1 ? '' : 's' ?>
                · 10 per page
            </span>

            <?php if ($pages > 1): ?>
                <nav aria-label="Subscription pages">
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
            <?php endif; ?>

            <form
                class="finance-subscriptions__goto"
                method="get"
                action="<?= e(url('/dashboard/subscriptions')) ?>"
            >
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
                        class="form-control"
                        type="number"
                        name="page"
                        min="1"
                        max="<?= $pages ?>"
                        value="<?= $page ?>"
                    >
                </label>
                <button class="btn btn-outline-secondary" type="submit">Go</button>
            </form>
        </div>

    </section>
</section>
