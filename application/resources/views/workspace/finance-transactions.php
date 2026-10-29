<?php

declare(strict_types=1);

$transactions = is_array($transactions ?? null) ? $transactions : [];
$companies = is_array($companies ?? null) ? $companies : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$invoices = is_array($invoices ?? null) ? $invoices : [];
$summary = is_array($ledgerSummary ?? null) ? $ledgerSummary : [];

// Finance Transactions Pagination Standard v1.8.30.
$transactionQ = substr(trim((string) ($_GET['transaction_q'] ?? '')), 0, 120);
$transactionStatus = trim((string) ($_GET['transaction_status'] ?? ''));
$allowedTransactionStatuses = ['succeeded', 'pending', 'failed'];

if (
    $transactionStatus !== ''
    && !in_array($transactionStatus, $allowedTransactionStatuses, true)
) {
    $transactionStatus = '';
}

$filteredTransactions = array_values(array_filter(
    $transactions,
    static function (array $transaction) use ($transactionQ, $transactionStatus): bool {
        $status = (string) ($transaction['status'] ?? 'pending');

        if ($transactionStatus !== '' && $status !== $transactionStatus) {
            return false;
        }

        if ($transactionQ === '') {
            return true;
        }

        $amount = (string) ($transaction['amount'] ?? '');
        $currency = (string) ($transaction['currency'] ?? '');

        $haystack = strtolower(
            (string) ($transaction['processed_at'] ?? '')
            . ' '
            . (string) ($transaction['company_name'] ?? '')
            . ' '
            . (string) ($transaction['invoice_number'] ?? '')
            . ' '
            . (string) ($transaction['plan_name'] ?? '')
            . ' '
            . (string) ($transaction['transaction_type'] ?? '')
            . ' '
            . $status
            . ' '
            . $currency
            . ' '
            . $amount
            . ' '
            . (string) ($transaction['source'] ?? '')
            . ' '
            . (string) ($transaction['reference'] ?? '')
            . ' '
            . (string) ($transaction['created_at'] ?? '')
        );

        return str_contains($haystack, strtolower($transactionQ));
    }
));

$transactionPerPage = 10;
$transactionTotal = count($filteredTransactions);
$transactionPages = max(1, (int) ceil($transactionTotal / $transactionPerPage));
$transactionPage = filter_var(
    $_GET['transaction_page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$transactionPage = $transactionPage === false
    ? 1
    : min((int) $transactionPage, $transactionPages);

$visibleTransactions = array_slice(
    $filteredTransactions,
    ($transactionPage - 1) * $transactionPerPage,
    $transactionPerPage
);

$transactionQuery = array_filter(
    [
        'transaction_q' => $transactionQ,
        'transaction_status' => $transactionStatus,
    ],
    static fn (string $value): bool => $value !== ''
);

$transactionPageUrl = static function (int $target) use ($transactionQuery): string {
    return url(
        '/dashboard/transactions?'
        . http_build_query(
            array_merge(
                $transactionQuery,
                ['transaction_page' => max(1, $target)]
            )
        )
    );
};

?>
<?= barama_css_once('admin') ?>

<?= barama_css_once('admin') ?>

<section class="workspace-page finance-vertical-cards-page finance-vertical-cards-page--transactions">
    <!-- BARAMA_FINANCE_VERTICAL_CARDS_V100 -->
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

    <div class="workspace-stat-grid finance-summary-cards">
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

        
        <form
            class="finance-transactions-tools"
            method="get"
            action="<?= e(url('/dashboard/transactions')) ?>"
        >
            <label>
                <?= app_icon('search') ?>
                <input
                    class="form-control"
                    type="search"
                    name="transaction_q"
                    value="<?= e($transactionQ) ?>"
                    placeholder="Search company, invoice, plan, type, source, or reference"
                >
            </label>

            <select
                class="form-select"
                name="transaction_status"
                aria-label="Transaction status"
            >
                <option value="">All statuses</option>
                <?php foreach ($allowedTransactionStatuses as $statusOption): ?>
                    <option
                        value="<?= e($statusOption) ?>"
                        <?= $transactionStatus === $statusOption ? 'selected' : '' ?>
                    ><?= e(ucfirst($statusOption)) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary" type="submit">Apply</button>
            <a
                class="btn btn-outline-secondary"
                href="<?= e(url('/dashboard/transactions')) ?>"
            >Reset</a>
        </form>

<div class="workspace-table-wrap">
            <table class="workspace-table finance-transactions-focus">
                <thead>
                    <tr>
                        <th>Processed</th>
                        <th>Account</th>
                        <th>Context</th>
                        <th>Amount</th>
                        <th>State</th>
                        <th>Trace</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($visibleTransactions === []): ?>
                        <tr><td colspan="6"><div class="workspace-record-empty">No transactions have been recorded yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($visibleTransactions as $transaction): ?>
                        <?php
                        $status = (string) ($transaction['status'] ?? 'pending');
                        $tone = $status === 'succeeded' ? 'approved' : ($status === 'failed' ? 'neutral' : 'pending');
                        ?>
                        <tr>
                            <td><?= e((string) $transaction['processed_at']) ?></td>
                            <td>
                                <div class="finance-transactions-focus__stack">
                                    <strong><?= e((string) $transaction['company_name']) ?></strong>
                                    <small>Plan: <?= e((string) ($transaction['plan_name'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-transactions-focus__stack">
                                    <strong><?= e(ucfirst((string) $transaction['transaction_type'])) ?></strong>
                                    <small>Invoice: <?= e((string) ($transaction['invoice_number'] ?? '—')) ?></small>
                                </div>
                            </td>
                            <td>
                                <strong class="finance-transactions-focus__money"><?= e((string) $transaction['currency']) ?> <?= e(number_format((float) $transaction['amount'], 2)) ?></strong>
                            </td>
                            <td>
                                <div class="finance-transactions-focus__stack">
                                    <span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span>
                                    <small>Source: <?= e((string) $transaction['source']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="finance-transactions-focus__stack">
                                    <strong><?= e((string) ($transaction['reference'] ?? '—')) ?></strong>
                                    <small>Created: <?= e((string) $transaction['created_at']) ?></small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($transactionTotal > 0): ?>
            <div class="finance-transactions-pager">
                <span>
                    Page <?= $transactionPage ?> of <?= $transactionPages ?>
                    &middot; <?= $transactionTotal ?> transaction<?= $transactionTotal === 1 ? '' : 's' ?>
                    &middot; 10 per page
                </span>

                <?php if ($transactionPages > 1): ?>
                    <nav aria-label="Transaction ledger pages">
                        <?php if ($transactionPage > 1): ?>
                            <a href="<?= e($transactionPageUrl($transactionPage - 1)) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $transactionFrom = max(1, $transactionPage - 2);
                        $transactionTo = min($transactionPages, $transactionPage + 2);
                        ?>
                        <?php for ($i = $transactionFrom; $i <= $transactionTo; $i++): ?>
                            <a
                                href="<?= e($transactionPageUrl($i)) ?>"
                                class="<?= $i === $transactionPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($transactionPage < $transactionPages): ?>
                            <a href="<?= e($transactionPageUrl($transactionPage + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        class="finance-transactions-goto"
                        method="get"
                        action="<?= e(url('/dashboard/transactions')) ?>"
                    >
                        <?php foreach ($transactionQuery as $key => $value): ?>
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
                                name="transaction_page"
                                min="1"
                                max="<?= $transactionPages ?>"
                                value="<?= $transactionPage ?>"
                            >
                        </label>
                        <button class="btn btn-outline-secondary" type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </section>
</section>
