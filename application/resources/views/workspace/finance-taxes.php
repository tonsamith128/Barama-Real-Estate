<?php

declare(strict_types=1);

$taxRules = is_array($taxRules ?? null) ? $taxRules : [];
$summary = is_array($taxSummary ?? null) ? $taxSummary : [];

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'active', 'inactive', 'archived'], true)) {
    $statusFilter = '';
}

$filteredTaxRules = array_values(array_filter(
    $taxRules,
    static function (array $rule) use ($q, $statusFilter): bool {
        if (
            $statusFilter !== ''
            && strtolower((string) ($rule['status'] ?? '')) !== $statusFilter
        ) {
            return false;
        }

        if ($q === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($rule['code'] ?? '')
            . ' '
            . (string) ($rule['name'] ?? '')
            . ' '
            . (string) ($rule['description'] ?? '')
            . ' '
            . (string) ($rule['country_code'] ?? '')
            . ' '
            . (string) ($rule['region_code'] ?? '')
        );

        return str_contains($haystack, strtolower($q));
    }
));

$perPage = 10;
$total = count($filteredTaxRules);
$pages = max(1, (int) ceil($total / $perPage));
$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$page = $page === false ? 1 : min((int) $page, $pages);

$visibleTaxRules = array_slice(
    $filteredTaxRules,
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
        '/dashboard/taxes?'
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

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--taxes" data-taxes-workspace>
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Tax Rules</h1>
            <p>Create exclusive percentage tax rules and apply them as immutable invoice snapshots.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Applied after discounts
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['tax_rules'] ?? 0))) ?></strong><small>Tax rules</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_tax_rules'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('edit') ?></span><strong><?= e(number_format((int) ($summary['taxed_invoices'] ?? 0))) ?></strong><small>Taxed invoices</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Create tax rule</span></summary>
        <form action="<?= e(url('/dashboard/taxes')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>
            <div class="workspace-form__grid">
                <label><span>Code</span><input class="form-control" name="code" maxlength="64" required placeholder="VAT10"></label>
                <label><span>Name</span><input class="form-control" name="name" maxlength="160" required></label>
                <label><span>Rate (%)</span><input class="form-control" name="rate_percent" type="number" min="0.0001" max="100" step="0.0001" required></label>
                <label><span>Country code <small>Optional</small></span><input class="form-control" name="country_code" maxlength="2" placeholder="KH"></label>
                <label><span>Region code <small>Optional</small></span><input class="form-control" name="region_code" maxlength="64"></label>
                <label><span>Starts at</span><input class="form-control" name="starts_at" type="datetime-local"></label>
                <label><span>Ends at</span><input class="form-control" name="ends_at" type="datetime-local"></label>
                <label class="workspace-form__span-2"><span>Description</span><textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea></label>
            </div>
            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create inactive tax rule</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Configured tax rules</h2>
                <p>Historical invoices keep their stored tax rate and amount even if this rule changes status later.</p>
            </div>
        </div>

        
        <form
            class="finance-taxes__filters"
            method="get"
            action="<?= e(url('/dashboard/taxes')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search code, name, country, or region"
                >
            </label>

            <select class="form-select" name="status" aria-label="Tax rule status">
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
                href="<?= e(url('/dashboard/taxes')) ?>"
            >Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Rule</th>
                        <th>Rate</th>
                        <th>Jurisdiction</th>
                        <th>Window</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleTaxRules === []): ?>
                        <tr><td colspan="7"><div class="workspace-record-empty">No tax rules exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleTaxRules as $rule): ?>
                        <?php $status = (string) ($rule['status'] ?? 'inactive'); ?>
                        <tr>
                            <td><strong><?= e((string) $rule['code']) ?></strong><small><?= e((string) $rule['name']) ?></small></td>
                            <td><?= e(number_format((float) $rule['rate_percent'], 4)) ?>%</td>
                            <td><?= e(trim((string) (($rule['country_code'] ?? '') . ' ' . ($rule['region_code'] ?? ''))) ?: '—') ?></td>
                            <td>
                                <small>From: <?= e((string) ($rule['starts_at'] ?? 'Any time')) ?></small>
                                <small>Until: <?= e((string) ($rule['ends_at'] ?? 'No expiry')) ?></small>
                            </td>
                            <td><span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'archived' ? 'neutral' : 'pending')) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $rule['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Tax rule actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/taxes/' . (int) $rule['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/taxes/' . (int) $rule['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                                                <button class="table-action table-action--danger" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/taxes/' . (int) $rule['id'] . '/status')) ?>" method="post" data-workspace-form>
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

        <div class="finance-taxes__pager">
            <span>
                Page <?= $page ?> of <?= $pages ?>
                · <?= $total ?> tax rule<?= $total === 1 ? '' : 's' ?>
                · 10 per page
            </span>

            <?php if ($pages > 1): ?>
                <nav aria-label="Tax rule pages">
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
                class="finance-taxes__goto"
                method="get"
                action="<?= e(url('/dashboard/taxes')) ?>"
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
