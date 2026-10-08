<?php

declare(strict_types=1);

$invoices = is_array($invoices ?? null) ? $invoices : [];
$companies = is_array($companies ?? null) ? $companies : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$summary = is_array($ledgerSummary ?? null) ? $ledgerSummary : [];

// Finance Invoices Pagination Standard v1.8.24.
$invoiceQ = substr(trim((string) ($_GET['invoice_q'] ?? '')), 0, 120);
$invoiceStatus = trim((string) ($_GET['invoice_status'] ?? ''));
$allowedInvoiceStatuses = ['draft', 'open', 'paid', 'void'];

if ($invoiceStatus !== '' && !in_array($invoiceStatus, $allowedInvoiceStatuses, true)) {
    $invoiceStatus = '';
}

$filteredInvoices = array_values(array_filter(
    $invoices,
    static function (array $invoice) use ($invoiceQ, $invoiceStatus): bool {
        $status = (string) ($invoice['status'] ?? 'draft');

        if ($invoiceStatus !== '' && $status !== $invoiceStatus) {
            return false;
        }

        if ($invoiceQ === '') {
            return true;
        }

        $haystack = strtolower(
            (string) ($invoice['invoice_number'] ?? '')
            . ' '
            . (string) ($invoice['company_name'] ?? '')
            . ' '
            . (string) ($invoice['plan_name'] ?? '')
            . ' '
            . (string) ($invoice['coupon_code'] ?? '')
            . ' '
            . (string) ($invoice['tax_rule_code'] ?? '')
            . ' '
            . $status
        );

        return str_contains($haystack, strtolower($invoiceQ));
    }
));

$invoicePerPage = 10;
$invoiceTotal = count($filteredInvoices);
$invoicePages = max(1, (int) ceil($invoiceTotal / $invoicePerPage));
$invoicePage = filter_var(
    $_GET['invoice_page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$invoicePage = $invoicePage === false
    ? 1
    : min((int) $invoicePage, $invoicePages);

$visibleInvoices = array_slice(
    $filteredInvoices,
    ($invoicePage - 1) * $invoicePerPage,
    $invoicePerPage
);

$invoiceQuery = array_filter(
    [
        'invoice_q' => $invoiceQ,
        'invoice_status' => $invoiceStatus,
    ],
    static fn (string $value): bool => $value !== ''
);

$invoicePageUrl = static function (int $target) use ($invoiceQuery): string {
    return url(
        '/dashboard/invoices?'
        . http_build_query(
            array_merge(
                $invoiceQuery,
                ['invoice_page' => max(1, $target)]
            )
        )
    );
};

?>
<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-invoices-pagination-v1824.css') . '?v=1824') ?>"
>

<link
    rel="stylesheet"
    href="<?= e(asset('css/finance-invoices-focus-v1827.css') . '?v=1827') ?>"
>

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--invoices">
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Invoices</h1>
            <p>Create provider-neutral invoices, open them for collection, and mark them paid only after successful settlement is recorded.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('layers') ?>
            <!-- BARAMA_INVOICE_TEMPLATE_V150 -->
<button type="button" class="invoice-template-trigger" data-invoice-template-open aria-haspopup="dialog">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 10h6M9 14h6M9 18h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Invoice Template</span>
</button>
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['invoices'] ?? 0))) ?></strong><small>Invoices</small></div>
        <div class="workspace-stat"><span><?= app_icon('edit') ?></span><strong><?= e(number_format((int) ($summary['draft_invoices'] ?? 0))) ?></strong><small>Draft</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['open_invoices'] ?? 0))) ?></strong><small>Open</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['paid_invoices'] ?? 0))) ?></strong><small>Paid</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Create invoice</span></summary>
        <form action="<?= e(url('/dashboard/invoices')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>

            <div class="workspace-form__grid">
                <label><span>Company</span>
                    <select class="form-select" name="company_id" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Subscription <small>Optional</small></span>
                    <select class="form-select" name="subscription_id">
                        <option value="">No linked subscription</option>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <option value="<?= e((string) $subscription['id']) ?>">
                                <?= e((string) $subscription['company_name']) ?> · <?= e((string) $subscription['plan_name']) ?> · <?= e((string) $subscription['status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="workspace-form__span-2">
                    <span>Line description</span>
                    <input class="form-control" name="description" maxlength="255" required placeholder="Subscription, service, or approved billable item">
                </label>

                <label><span>Quantity</span><input class="form-control" name="quantity" type="number" min="0.01" step="0.01" value="1.00" required></label>
                <label><span>Unit amount</span><input class="form-control" name="unit_amount" type="number" min="0" step="0.01" required></label>
                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="USD" required></label>
                <label><span>Coupon code <small>Optional</small></span><input class="form-control" name="coupon_code" maxlength="64" placeholder="WELCOME10"></label>
                <label><span>Tax rule code <small>Optional</small></span><input class="form-control" name="tax_rule_code" maxlength="64" placeholder="VAT10"></label>
                <label><span>Due at</span><input class="form-control" name="due_at" type="datetime-local"></label>
                <label class="workspace-form__span-2"><span>Internal notes</span><textarea class="form-control" name="notes" rows="3" maxlength="5000"></textarea></label>
            </div>

            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create draft invoice</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Invoice ledger</h2>
                <p>Amounts are immutable after creation in this foundation. Paid state requires sufficient successful settlement.</p>
            </div>
        </div>

        
        <form
            class="finance-invoices-tools"
            method="get"
            action="<?= e(url('/dashboard/invoices')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="invoice_q"
                    value="<?= e($invoiceQ) ?>"
                    placeholder="Search invoice, company, plan, coupon, or tax rule"
                >
            </label>

            <select
                class="form-select"
                name="invoice_status"
                aria-label="Invoice status"
            >
                <option value="">All statuses</option>
                <?php foreach ($allowedInvoiceStatuses as $statusOption): ?>
                    <option
                        value="<?= e($statusOption) ?>"
                        <?= $invoiceStatus === $statusOption ? 'selected' : '' ?>
                    ><?= e(ucfirst($statusOption)) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">Apply</button>
            <a
                class="btn btn-outline-secondary"
                href="<?= e(url('/dashboard/invoices')) ?>"
            >Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table finance-invoices-focus">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Account</th>
                        <th>Pricing</th>
                        <th>Tax</th>
                        <th>Settlement</th>
                        <th>Lifecycle</th>
                        <th>Dates</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleInvoices === []): ?>
                        <tr><td colspan="8"><div class="workspace-record-empty">No invoices have been created yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleInvoices as $invoice): ?>
                        <?php
                        $status = (string) ($invoice['status'] ?? 'draft');
                        $tone = $status === 'paid'
                            ? 'approved'
                            : ($status === 'open' ? 'pending' : ($status === 'void' ? 'neutral' : 'draft'));
                        ?>
                        <tr>
                            <td>
                                <div class="finance-invoices-focus__stack">
                                    <strong><?= e((string) $invoice['invoice_number']) ?></strong>
                                    <small><?= e(number_format((int) ($invoice['item_count'] ?? 0))) ?> item(s)</small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-invoices-focus__stack">
                                    <strong><?= e((string) $invoice['company_name']) ?></strong>
                                    <small>Plan: <?= e((string) ($invoice['plan_name'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-invoices-focus__stack finance-invoices-focus__money">
                                    <small>Subtotal: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['subtotal'], 2)) ?></small>
                                    <small>Discount: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) ($invoice['discount_total'] ?? 0), 2)) ?></small>
                                    <small>Coupon: <?= e((string) ($invoice['coupon_code'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-invoices-focus__stack finance-invoices-focus__money">
                                    <small>Taxable: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) ($invoice['taxable_total'] ?? 0), 2)) ?></small>
                                    <small>Tax: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) ($invoice['tax_total'] ?? 0), 2)) ?> · <?= e(number_format((float) ($invoice['tax_rate'] ?? 0), 4)) ?>%</small>
                                    <small>Rule: <?= e((string) ($invoice['tax_rule_code'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-invoices-focus__stack finance-invoices-focus__money">
                                    <strong>Total: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['total'], 2)) ?></strong>
                                    <small>Settled: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) ($invoice['settled_amount'] ?? 0), 2)) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span>
                            </td>
                            <td>
                                <div class="finance-invoices-focus__stack">
                                    <small>Issued: <?= e((string) ($invoice['issued_at'] ?? '—')) ?></small>
                                    <small>Due: <?= e((string) ($invoice['due_at'] ?? '—')) ?></small>
                                    <small>Paid: <?= e((string) ($invoice['paid_at'] ?? '—')) ?></small>
                                    <small>Updated: <?= e((string) $invoice['updated_at']) ?></small>
                                </div>
                            </td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Invoice actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'draft'): ?>
                                            <form action="<?= e(url('/dashboard/invoices/' . (int) $invoice['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="open">
                                                <button class="table-action table-action--primary" type="submit">Open invoice</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/invoices/' . (int) $invoice['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="void">
                                                <button class="table-action table-action--danger" type="submit">Void</button>
                                            </form>
                                        <?php elseif ($status === 'open'): ?>
                                            <form action="<?= e(url('/dashboard/invoices/' . (int) $invoice['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="paid">
                                                <button class="table-action table-action--primary" type="submit">Mark paid</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/invoices/' . (int) $invoice['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="void">
                                                <button class="table-action table-action--danger" type="submit">Void</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Lifecycle complete</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($invoiceTotal > 0): ?>
            <div class="finance-invoices-pager">
                <span>
                    Page <?= $invoicePage ?> of <?= $invoicePages ?>
                    &middot; <?= $invoiceTotal ?> invoice<?= $invoiceTotal === 1 ? '' : 's' ?>
                    &middot; 10 per page
                </span>

                <?php if ($invoicePages > 1): ?>
                    <nav aria-label="Invoice ledger pages">
                        <?php if ($invoicePage > 1): ?>
                            <a href="<?= e($invoicePageUrl($invoicePage - 1)) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $invoiceFrom = max(1, $invoicePage - 2);
                        $invoiceTo = min($invoicePages, $invoicePage + 2);
                        ?>
                        <?php for ($i = $invoiceFrom; $i <= $invoiceTo; $i++): ?>
                            <a
                                href="<?= e($invoicePageUrl($i)) ?>"
                                class="<?= $i === $invoicePage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($invoicePage < $invoicePages): ?>
                            <a href="<?= e($invoicePageUrl($invoicePage + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        class="finance-invoices-goto"
                        method="get"
                        action="<?= e(url('/dashboard/invoices')) ?>"
                    >
                        <?php foreach ($invoiceQuery as $key => $value): ?>
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
                                name="invoice_page"
                                min="1"
                                max="<?= $invoicePages ?>"
                                value="<?= $invoicePage ?>"
                            >
                        </label>
                        <button class="btn btn-outline-secondary" type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </section>
</section>

<?php
require __DIR__ . '/partials/invoice-template.php';
?>
