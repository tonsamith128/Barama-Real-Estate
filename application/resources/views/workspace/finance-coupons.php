<?php

declare(strict_types=1);

$coupons = is_array($coupons ?? null) ? $coupons : [];
$summary = is_array($couponSummary ?? null) ? $couponSummary : [];

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'active', 'inactive', 'archived'], true)) {
    $statusFilter = '';
}

$filteredCoupons = array_values(array_filter(
    $coupons,
    static function (array $coupon) use ($q, $statusFilter): bool {
        if (
            $statusFilter !== ''
            && strtolower((string) ($coupon['status'] ?? '')) !== $statusFilter
        ) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($coupon['code'] ?? '')
            . ' '
            . (string) ($coupon['name'] ?? '')
            . ' '
            . (string) ($coupon['description'] ?? '')
        );

        return str_contains($haystack, strtolower($q));
    }
));

$perPage = 10;
$total = count($filteredCoupons);
$pages = max(1, (int) ceil($total / $perPage));
$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$page = $page === false ? 1 : min((int) $page, $pages);

$visibleCoupons = array_slice(
    $filteredCoupons,
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
        '/dashboard/coupons?'
        . http_build_query(
            array_merge(
                $query,
                ['page' => max(1, $target)]
            )
        )
    );
};
?>
<?= barama_css_once('admin') ?>

<?= barama_css_once('admin') ?>

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--coupons" data-coupons-workspace>
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Coupons</h1>
            <p>Create controlled percentage or fixed discounts. New coupons start inactive and must be activated before invoice use.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Invoice-time discount snapshots
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['coupons'] ?? 0))) ?></strong><small>Coupons</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_coupons'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('building') ?></span><strong><?= e(number_format((int) ($summary['effective_redemptions'] ?? 0))) ?></strong><small>Effective redemptions</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Create coupon</span></summary>
        <form action="<?= e(url('/dashboard/coupons')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>

            <div class="workspace-form__grid">
                <label><span>Code</span><input class="form-control" name="code" maxlength="64" required placeholder="WELCOME10"></label>
                <label><span>Name</span><input class="form-control" name="name" maxlength="160" required></label>

                <label><span>Discount type</span>
                    <select class="form-select" name="discount_type" required>
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed amount</option>
                    </select>
                </label>

                <label><span>Discount value</span><input class="form-control" name="discount_value" type="number" min="0.01" step="0.01" required></label>
                <label><span>Currency <small>Fixed discounts only</small></span><input class="form-control" name="currency" maxlength="3" placeholder="USD"></label>
                <label><span>Maximum redemptions</span><input class="form-control" name="max_redemptions" type="number" min="1" placeholder="Unlimited"></label>
                <label><span>Per-company limit</span><input class="form-control" name="per_company_limit" type="number" min="1" placeholder="Unlimited"></label>
                <label><span>Starts at</span><input class="form-control" name="starts_at" type="datetime-local"></label>
                <label><span>Ends at</span><input class="form-control" name="ends_at" type="datetime-local"></label>
                <label class="workspace-form__span-2"><span>Description</span><textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea></label>
            </div>

            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create inactive coupon</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Configured coupons</h2>
                <p>Voided invoices remain in redemption history but do not consume effective coupon limits.</p>
            </div>
        </div>

        
        <form
            class="finance-coupons__filters"
            method="get"
            action="<?= e(url('/dashboard/coupons')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search coupon code, name, or description"
                >
            </label>

            <select class="form-select" name="status" aria-label="Coupon status">
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
                href="<?= e(url('/dashboard/coupons')) ?>"
            >Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table finance-coupons__table-focus">
                <thead>
                    <tr>
                        <th>Coupon</th>
                        <th>Offer</th>
                        <th>Availability</th>
                        <th>Usage</th>
                        <th>State</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleCoupons === []): ?>
                        <tr><td colspan="7"><div class="workspace-record-empty">No coupons exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleCoupons as $coupon): ?>
                        <?php
                        $status = (string) ($coupon['status'] ?? 'inactive');
                        $type = (string) ($coupon['discount_type'] ?? 'percent');
                        $discount = $type === 'percent'
                            ? number_format((float) $coupon['discount_value'], 2) . '%'
                            : (string) ($coupon['currency'] ?? '') . ' ' . number_format((float) $coupon['discount_value'], 2);
                        ?>
                        <tr>
                            <td>
                                <div class="finance-coupons__coupon">
                                    <strong><?= e((string) $coupon['code']) ?></strong>
                                    <small><?= e((string) $coupon['name']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-coupons__offer">
                                    <strong><?= e($discount) ?></strong>
                                    <small><?= e(ucfirst($type)) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-coupons__availability">
                                    <small>From: <?= e((string) ($coupon['starts_at'] ?? 'Any time')) ?></small>
                                    <small>Until: <?= e((string) ($coupon['ends_at'] ?? 'No expiry')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-coupons__usage">
                                    <small>
                                        Global:
                                        <?= $coupon['max_redemptions'] === null
                                            ? 'Unlimited'
                                            : e(number_format((int) $coupon['max_redemptions'])) ?>
                                    </small>
                                    <small>
                                        Per company:
                                        <?= $coupon['per_company_limit'] === null
                                            ? 'Unlimited'
                                            : e(number_format((int) $coupon['per_company_limit'])) ?>
                                    </small>
                                    <small>
                                        Effective uses:
                                        <?= e(number_format((int) ($coupon['effective_redemptions'] ?? 0))) ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-coupons__state">
                                    <span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'archived' ? 'neutral' : 'pending')) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= e((string) $coupon['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Coupon actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                                                <button class="table-action table-action--danger" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="inactive">
                                                <button class="table-action" type="submit">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Archived</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="finance-coupons__pager">
            <span>
                Page <?= $page ?> of <?= $pages ?>
                · <?= $total ?> coupon<?= $total === 1 ? '' : 's' ?>
                · 10 per page
            </span>

            <?php if ($pages > 1): ?>
                <nav aria-label="Coupon pages">
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
                class="finance-coupons__goto"
                method="get"
                action="<?= e(url('/dashboard/coupons')) ?>"
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
