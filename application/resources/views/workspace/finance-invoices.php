<?php

declare(strict_types=1);

$invoices = is_array($invoices ?? null) ? $invoices : [];
$companies = is_array($companies ?? null) ? $companies : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$summary = is_array($ledgerSummary ?? null) ? $ledgerSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Invoices</h1>
            <p>Create provider-neutral invoices, open them for collection, and mark them paid only after successful settlement is recorded.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('layers') ?>
            Draft → Open → Paid / Void
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
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

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Company</th>
                        <th>Plan</th>
                        <th>Total</th>
                        <th>Settled</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Paid</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoices === []): ?>
                        <tr><td colspan="11"><div class="workspace-record-empty">No invoices have been created yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $status = (string) ($invoice['status'] ?? 'draft');
                        $tone = $status === 'paid'
                            ? 'approved'
                            : ($status === 'open' ? 'pending' : ($status === 'void' ? 'neutral' : 'draft'));
                        ?>
                        <tr>
                            <td><strong><?= e((string) $invoice['invoice_number']) ?></strong><small><?= e(number_format((int) ($invoice['item_count'] ?? 0))) ?> item(s)</small></td>
                            <td><?= e((string) $invoice['company_name']) ?></td>
                            <td><?= e((string) ($invoice['plan_name'] ?? '—')) ?></td>
                            <td><?= e((string) $invoice['currency']) ?> <?= e(number_format((float) $invoice['total'], 2)) ?></td>
                            <td><?= e((string) $invoice['currency']) ?> <?= e(number_format((float) ($invoice['settled_amount'] ?? 0), 2)) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) ($invoice['issued_at'] ?? '—')) ?></td>
                            <td><?= e((string) ($invoice['due_at'] ?? '—')) ?></td>
                            <td><?= e((string) ($invoice['paid_at'] ?? '—')) ?></td>
                            <td><?= e((string) $invoice['updated_at']) ?></td>
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
    </section>
</section>
