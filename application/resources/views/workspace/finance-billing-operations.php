<?php

declare(strict_types=1);

$summary = is_array($billingOperationsSummary ?? null) ? $billingOperationsSummary : [];
$reconciliation = is_array($reconciliationInvoices ?? null) ? $reconciliationInvoices : [];
$webhookOperations = is_array($webhookOperationalEvents ?? null) ? $webhookOperationalEvents : [];
?>
<section class="workspace-page">
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

    <div class="workspace-stat-grid">
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
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Total</th>
                        <th>Settled</th>
                        <th>Delta</th>
                        <th>Reconciliation</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reconciliation === []): ?>
                        <tr><td colspan="9"><div class="workspace-record-empty">No invoice reconciliation records require display yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($reconciliation as $invoice): ?>
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
                            <td><?= e(ucfirst((string) $invoice['status'])) ?></td>
                            <td><?= e((string) ($invoice['due_at'] ?? '—')) ?></td>
                            <td><?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['total'], 2)) ?></td>
                            <td><?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['settled_amount'], 2)) ?></td>
                            <td><?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['settlement_delta'], 2)) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucwords(str_replace('_', ' ', $state))) ?></span></td>
                            <td><?= e((string) $invoice['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Webhook attention queue</h2>
                <p>Failed events and receipts still unprocessed after 10 minutes appear here. Retry replays the immutable stored payload; Ignore closes the operational item without creating a transaction.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Provider</th>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Provider status</th>
                        <th>Error</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($webhookOperations === []): ?>
                        <tr><td colspan="8"><div class="workspace-record-empty">No webhook events currently require operational attention.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($webhookOperations as $event): ?>
                        <tr>
                            <td><?= e((string) $event['received_at']) ?></td>
                            <td>
                                <strong><?= e((string) $event['provider_name']) ?></strong>
                                <small><?= e((string) $event['provider_code']) ?></small>
                            </td>
                            <td><?= e((string) $event['event_id']) ?></td>
                            <td><?= e((string) $event['event_type']) ?></td>
                            <td><span class="status-badge status-badge--<?= e((string) $event['status'] === 'failed' ? 'neutral' : 'pending') ?>"><?= e(ucfirst((string) $event['status'])) ?></span></td>
                            <td><?= e(ucfirst((string) $event['provider_status'])) ?></td>
                            <td><small><?= e((string) ($event['error_message'] ?? '—')) ?></small></td>
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
    </section>
</section>
