<?php

declare(strict_types=1);

$summary = is_array($billingOperationsSummary ?? null) ? $billingOperationsSummary : [];
$reconciliation = is_array($reconciliationInvoices ?? null) ? $reconciliationInvoices : [];
$webhookOperations = is_array($webhookOperationalEvents ?? null) ? $webhookOperationalEvents : [];

// Billing Operations Pagination Standard v1.8.15.
$billingOpsPerPage = 10;

$reconciliationTotal = count($reconciliation);
$reconciliationPages = max(1, (int) ceil($reconciliationTotal / $billingOpsPerPage));
$reconciliationPage = filter_var(
    $_GET['reconciliation_page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$reconciliationPage = $reconciliationPage === false
    ? 1
    : min((int) $reconciliationPage, $reconciliationPages);

$visibleReconciliation = array_slice(
    $reconciliation,
    ($reconciliationPage - 1) * $billingOpsPerPage,
    $billingOpsPerPage
);

$webhookOpsTotal = count($webhookOperations);
$webhookOpsPages = max(1, (int) ceil($webhookOpsTotal / $billingOpsPerPage));
$webhookOpsPage = filter_var(
    $_GET['webhook_ops_page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$webhookOpsPage = $webhookOpsPage === false
    ? 1
    : min((int) $webhookOpsPage, $webhookOpsPages);

$visibleWebhookOperations = array_slice(
    $webhookOperations,
    ($webhookOpsPage - 1) * $billingOpsPerPage,
    $billingOpsPerPage
);

$reconciliationPageUrl = static function (
    int $target
) use ($webhookOpsPage): string {
    return url(
        '/dashboard/billing-operations?'
        . http_build_query(
            [
                'reconciliation_page' => max(1, $target),
                'webhook_ops_page' => $webhookOpsPage,
            ]
        )
    );
};

$webhookOpsPageUrl = static function (
    int $target
) use ($reconciliationPage): string {
    return url(
        '/dashboard/billing-operations?'
        . http_build_query(
            [
                'reconciliation_page' => $reconciliationPage,
                'webhook_ops_page' => max(1, $target),
            ]
        )
    );
};

?>
<?= barama_css_once('admin') ?>

<?= barama_css_once('admin') ?>

<?= barama_css_once('admin') ?>

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--billing-operations">
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Billing Operations</h1>
            <p>Review overdue invoices, settlement mismatches, pending ledger activity, and webhook events that need operational attention.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Derived diagnostics · no invoice history rewriting
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid finance-summary-cards">
        <div class="workspace-stat">
            <span><?= app_icon('layers') ?></span>
            <strong><?= e(number_format((int) ($summary['open_invoices'] ?? 0))) ?></strong>
            <small>Open invoices</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('alert') ?></span>
            <strong><?= e(number_format((int) ($summary['overdue_invoices'] ?? 0))) ?></strong>
            <small>Overdue invoices</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('edit') ?></span>
            <strong><?= e(number_format((int) ($summary['overpaid_invoices'] ?? 0))) ?></strong>
            <small>Overpaid invoices</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('alert') ?></span>
            <strong><?= e(number_format((int) ($summary['paid_mismatches'] ?? 0))) ?></strong>
            <small>Paid mismatches</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('pipeline') ?></span>
            <strong><?= e(number_format((int) ($summary['pending_transactions'] ?? 0))) ?></strong>
            <small>Pending transactions</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('alert') ?></span>
            <strong><?= e(number_format((int) ($summary['failed_webhooks'] ?? 0))) ?></strong>
            <small>Failed webhooks</small>
        </div>
        <div class="workspace-stat">
            <span><?= app_icon('layers') ?></span>
            <strong><?= e(number_format((int) ($summary['stale_webhooks'] ?? 0))) ?></strong>
            <small>Stale webhooks</small>
        </div>
    </div>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Invoice reconciliation</h2>
                <p>Settlement is derived from succeeded payment and credit transactions minus succeeded refunds. Positive delta means money is still due; negative delta indicates overpayment.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table finance-billing-ops-invoice-focus">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Company</th>
                        <th>Billing</th>
                        <th>Settlement</th>
                        <th>State</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleReconciliation === []): ?>
                        <tr><td colspan="6"><div class="workspace-record-empty">No invoice reconciliation records require display yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleReconciliation as $invoice): ?>
                        <?php
                        $state = (string) ($invoice['reconciliation_state'] ?? 'review');
                        $tone = in_array($state, ['balanced'], true)
                            ? 'approved'
                            : (in_array($state, ['overpaid', 'paid_mismatch', 'overdue', 'unexpected_settlement'], true)
                                ? 'neutral'
                                : 'pending');
                        ?>
                        <tr>
                            <td><strong><?= e((string) $invoice['invoice_number']) ?></strong></td>
                            <td><?= e((string) $invoice['company_name']) ?></td>
                            <td>
                                <div class="finance-billing-ops-billing">
                                    <strong><?= e(ucfirst((string) $invoice['status'])) ?></strong>
                                    <small>Due: <?= e((string) ($invoice['due_at'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-billing-ops-settlement">
                                    <small>Total: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['total'], 2)) ?></small>
                                    <small>Settled: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['settled_amount'], 2)) ?></small>
                                    <small>Delta: <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['settlement_delta'], 2)) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-badge--<?= e($tone) ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $state))) ?>
                                </span>
                            </td>
                            <td><?= e((string) $invoice['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($reconciliationTotal > 0): ?>
            <div class="finance-billing-ops-pager">
                <span>
                    Page <?= $reconciliationPage ?> of <?= $reconciliationPages ?>
                    &middot; <?= $reconciliationTotal ?> record<?= $reconciliationTotal === 1 ? '' : 's' ?>
                    &middot; 10 per page
                </span>

                <?php if ($reconciliationPages > 1): ?>
                    <nav aria-label="Invoice reconciliation pages">
                        <?php if ($reconciliationPage > 1): ?>
                            <a href="<?= e($reconciliationPageUrl($reconciliationPage - 1)) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $reconciliationFrom = max(1, $reconciliationPage - 2);
                        $reconciliationTo = min($reconciliationPages, $reconciliationPage + 2);
                        ?>
                        <?php for ($i = $reconciliationFrom; $i <= $reconciliationTo; $i++): ?>
                            <a
                                href="<?= e($reconciliationPageUrl($i)) ?>"
                                class="<?= $i === $reconciliationPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($reconciliationPage < $reconciliationPages): ?>
                            <a href="<?= e($reconciliationPageUrl($reconciliationPage + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        class="finance-billing-ops-goto"
                        method="get"
                        action="<?= e(url('/dashboard/billing-operations')) ?>"
                    >
                        <input
                            type="hidden"
                            name="webhook_ops_page"
                            value="<?= $webhookOpsPage ?>"
                        >
                        <label>
                            <span>Go To</span>
                            <input
                                class="form-control"
                                type="number"
                                name="reconciliation_page"
                                min="1"
                                max="<?= $reconciliationPages ?>"
                                value="<?= $reconciliationPage ?>"
                            >
                        </label>
                        <button class="btn btn-outline-secondary" type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Webhook attention queue</h2>
                <p>Failed events and receipts still unprocessed after 10 minutes appear here. Retry replays the immutable stored payload; Ignore closes the operational item without creating a transaction.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table finance-billing-ops-webhook-focus">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Provider</th>
                        <th>Event</th>
                        <th>State</th>
                        <th>Issue</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleWebhookOperations === []): ?>
                        <tr><td colspan="6"><div class="workspace-record-empty">No webhook events currently require operational attention.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleWebhookOperations as $event): ?>
                        <tr>
                            <td><?= e((string) $event['received_at']) ?></td>
                            <td>
                                <strong><?= e((string) $event['provider_name']) ?></strong>
                                <small><?= e((string) $event['provider_code']) ?></small>
                            </td>
                            <td>
                                <div class="finance-billing-ops-event">
                                    <strong><?= e((string) $event['event_id']) ?></strong>
                                    <small><?= e((string) $event['event_type']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-billing-ops-state">
                                    <span class="status-badge status-badge--<?= e((string) $event['status'] === 'failed' ? 'neutral' : 'pending') ?>">
                                        <?= e(ucfirst((string) $event['status'])) ?>
                                    </span>
                                    <small>Provider: <?= e(ucfirst((string) $event['provider_status'])) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-billing-ops-issue">
                                    <small><?= e((string) ($event['error_message'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Webhook operations" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ((string) $event['provider_status'] === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/billing-operations/webhooks/' . (int) $event['id'] . '/retry')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <button class="table-action table-action--primary" type="submit">Retry stored event</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Activate provider before retry</span>
                                        <?php endif; ?>

                                        <form action="<?= e(url('/dashboard/billing-operations/webhooks/' . (int) $event['id'] . '/ignore')) ?>" method="post" data-workspace-form>
                                            <?= csrf_field() ?>
                                            <button class="table-action" type="submit">Ignore event</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($webhookOpsTotal > 0): ?>
            <div class="finance-billing-ops-pager">
                <span>
                    Page <?= $webhookOpsPage ?> of <?= $webhookOpsPages ?>
                    &middot; <?= $webhookOpsTotal ?> event<?= $webhookOpsTotal === 1 ? '' : 's' ?>
                    &middot; 10 per page
                </span>

                <?php if ($webhookOpsPages > 1): ?>
                    <nav aria-label="Webhook attention pages">
                        <?php if ($webhookOpsPage > 1): ?>
                            <a href="<?= e($webhookOpsPageUrl($webhookOpsPage - 1)) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $webhookOpsFrom = max(1, $webhookOpsPage - 2);
                        $webhookOpsTo = min($webhookOpsPages, $webhookOpsPage + 2);
                        ?>
                        <?php for ($i = $webhookOpsFrom; $i <= $webhookOpsTo; $i++): ?>
                            <a
                                href="<?= e($webhookOpsPageUrl($i)) ?>"
                                class="<?= $i === $webhookOpsPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($webhookOpsPage < $webhookOpsPages): ?>
                            <a href="<?= e($webhookOpsPageUrl($webhookOpsPage + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        class="finance-billing-ops-goto"
                        method="get"
                        action="<?= e(url('/dashboard/billing-operations')) ?>"
                    >
                        <input
                            type="hidden"
                            name="reconciliation_page"
                            value="<?= $reconciliationPage ?>"
                        >
                        <label>
                            <span>Go To</span>
                            <input
                                class="form-control"
                                type="number"
                                name="webhook_ops_page"
                                min="1"
                                max="<?= $webhookOpsPages ?>"
                                value="<?= $webhookOpsPage ?>"
                            >
                        </label>
                        <button class="btn btn-outline-secondary" type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </section>
</section>
