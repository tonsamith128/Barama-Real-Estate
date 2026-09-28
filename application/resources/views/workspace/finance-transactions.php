<?php

declare(strict_types=1);

$transactions = is_array($transactions ?? null) ? $transactions : [];
$companies = is_array($companies ?? null) ? $companies : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$invoices = is_array($invoices ?? null) ? $invoices : [];
$summary = is_array($ledgerSummary ?? null) ? $ledgerSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Transactions</h1>
            <p>Record provider-neutral payment, refund, and credit events. Transaction rows are immutable after creation.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Immutable financial ledger
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['transactions'] ?? 0))) ?></strong><small>Transactions</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['succeeded_transactions'] ?? 0))) ?></strong><small>Succeeded</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['pending_transactions'] ?? 0))) ?></strong><small>Pending</small></div>
        <div class="workspace-stat"><span><?= app_icon('shield') ?></span><strong><?= e(number_format((int) ($summary['failed_transactions'] ?? 0))) ?></strong><small>Failed</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Record transaction</span></summary>
        <form action="<?= e(url('/dashboard/transactions')) ?>" method="post" data-workspace-form class="workspace-form">
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
                                <?= e((string) $subscription['company_name']) ?> · <?= e((string) $subscription['plan_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Invoice <small>Required for refunds</small></span>
                    <select class="form-select" name="invoice_id">
                        <option value="">No linked invoice</option>
                        <?php foreach ($invoices as $invoice): ?>
                            <option value="<?= e((string) $invoice['id']) ?>">
                                <?= e((string) $invoice['invoice_number']) ?> · <?= e((string) $invoice['company_name']) ?> · <?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['total'], 2)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Type</span>
                    <select class="form-select" name="transaction_type" required>
                        <option value="payment">Payment</option>
                        <option value="credit">Credit</option>
                        <option value="refund">Refund</option>
                    </select>
                </label>

                <label><span>Status</span>
                    <select class="form-select" name="status" required>
                        <option value="succeeded">Succeeded</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </label>

                <label><span>Amount</span><input class="form-control" name="amount" type="number" min="0.01" step="0.01" required></label>
                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="USD" required></label>
                <label><span>Processed at</span><input class="form-control" name="processed_at" type="datetime-local"></label>
                <label><span>Reference</span><input class="form-control" name="reference" maxlength="191" placeholder="Receipt, transfer, or provider reference"></label>
                <label class="workspace-form__span-2"><span>Internal notes</span><textarea class="form-control" name="notes" rows="3" maxlength="5000"></textarea></label>
            </div>

            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Record immutable transaction</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Transaction ledger</h2>
                <p>Corrections are recorded as new balancing transactions rather than editing historical rows.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Processed</th>
                        <th>Company</th>
                        <th>Invoice</th>
                        <th>Plan</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Source</th>
                        <th>Reference</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions === []): ?>
                        <tr><td colspan="10"><div class="workspace-record-empty">No transactions have been recorded yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($transactions as $transaction): ?>
                        <?php
                        $status = (string) ($transaction['status'] ?? 'pending');
                        $tone = $status === 'succeeded' ? 'approved' : ($status === 'failed' ? 'neutral' : 'pending');
                        ?>
                        <tr>
                            <td><?= e((string) $transaction['processed_at']) ?></td>
                            <td><?= e((string) $transaction['company_name']) ?></td>
                            <td><?= e((string) ($transaction['invoice_number'] ?? '—')) ?></td>
                            <td><?= e((string) ($transaction['plan_name'] ?? '—')) ?></td>
                            <td><?= e(ucfirst((string) $transaction['transaction_type'])) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $transaction['currency']) ?> <?= e(number_format((float) $transaction['amount'], 2)) ?></td>
                            <td><?= e((string) $transaction['source']) ?></td>
                            <td><?= e((string) ($transaction['reference'] ?? '—')) ?></td>
                            <td><?= e((string) $transaction['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
