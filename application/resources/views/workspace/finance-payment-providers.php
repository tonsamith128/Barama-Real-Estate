<?php

declare(strict_types=1);

$providers = is_array($paymentProviders ?? null) ? $paymentProviders : [];
$events = is_array($webhookEvents ?? null) ? $webhookEvents : [];
$summary = is_array($paymentProviderSummary ?? null) ? $paymentProviderSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Payment Providers</h1>
            <p>Configure provider-neutral webhook endpoints and review immutable incoming payment event receipts.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Generic shared-token adapter
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['providers'] ?? 0))) ?></strong><small>Providers</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_providers'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('pipeline') ?></span><strong><?= e(number_format((int) ($summary['webhook_events'] ?? 0))) ?></strong><small>Webhook events</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['failed_events'] ?? 0))) ?></strong><small>Failed events</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Add payment provider</span></summary>
        <form action="<?= e(url('/dashboard/payment-providers')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>

            <div class="workspace-form__grid">
                <label>
                    <span>Provider code</span>
                    <input class="form-control" name="code" maxlength="24" required placeholder="gateway">
                </label>
                <label>
                    <span>Provider name</span>
                    <input class="form-control" name="name" maxlength="160" required placeholder="Payment Gateway">
                </label>
                <label>
                    <span>Environment</span>
                    <select class="form-select" name="environment" required>
                        <option value="sandbox">Sandbox</option>
                        <option value="live">Live</option>
                    </select>
                </label>
                <label class="workspace-form__span-2">
                    <span>Webhook token <small>32–191 characters; stored only as a password hash</small></span>
                    <input class="form-control" name="webhook_token" type="password" minlength="32" maxlength="191" autocomplete="new-password" required>
                </label>
            </div>

            <button class="btn btn-primary" type="submit">
                <?= app_icon('plus') ?>
                Create inactive provider
            </button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Provider registry</h2>
                <p>Provider tokens cannot be recovered from storage. A provider must be active before its webhook endpoint accepts events.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Environment</th>
                        <th>Adapter</th>
                        <th>Webhook endpoint</th>
                        <th>Events</th>
                        <th>Failures</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($providers === []): ?>
                        <tr><td colspan="9"><div class="workspace-record-empty">No payment providers exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($providers as $provider): ?>
                        <?php $status = (string) ($provider['status'] ?? 'inactive'); ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $provider['name']) ?></strong>
                                <small><?= e((string) $provider['code']) ?></small>
                            </td>
                            <td><?= e(ucfirst((string) $provider['environment'])) ?></td>
                            <td><?= e((string) $provider['adapter_key']) ?></td>
                            <td>
                                <small><?= e(url('/payments/webhooks/' . (string) $provider['code'])) ?></small>
                                <small>Header: X-Barama-Webhook-Token</small>
                            </td>
                            <td><?= e(number_format((int) ($provider['webhook_events'] ?? 0))) ?></td>
                            <td><?= e(number_format((int) ($provider['failed_events'] ?? 0))) ?></td>
                            <td><span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'archived' ? 'neutral' : 'pending')) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $provider['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Provider actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/payment-providers/' . (int) $provider['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/payment-providers/' . (int) $provider['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                                                <button class="table-action table-action--danger" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/payment-providers/' . (int) $provider['id'] . '/status')) ?>" method="post" data-workspace-form>
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
    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Webhook inbox</h2>
                <p>Receipts are deduplicated by provider and event ID. Failed receipts remain available for safe provider retry.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Provider</th>
                        <th>Event ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Transaction</th>
                        <th>Payload hash</th>
                        <th>Processed</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events === []): ?>
                        <tr><td colspan="9"><div class="workspace-record-empty">No webhook events have been received yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($events as $event): ?>
                        <?php
                        $status = (string) ($event['status'] ?? 'received');
                        $tone = $status === 'processed'
                            ? 'approved'
                            : ($status === 'failed' ? 'neutral' : 'pending');
                        ?>
                        <tr>
                            <td><?= e((string) $event['received_at']) ?></td>
                            <td><strong><?= e((string) $event['provider_name']) ?></strong><small><?= e((string) $event['provider_code']) ?></small></td>
                            <td><?= e((string) $event['event_id']) ?></td>
                            <td><?= e((string) $event['event_type']) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) ($event['transaction_reference'] ?? '—')) ?></td>
                            <td><small><?= e(substr((string) $event['payload_sha256'], 0, 16)) ?>…</small></td>
                            <td><?= e((string) ($event['processed_at'] ?? '—')) ?></td>
                            <td><small><?= e((string) ($event['error_message'] ?? '—')) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Generic webhook contract</h2>
                <p>Provider-specific signature adapters will be added separately. The generic adapter accepts JSON with these fields.</p>
            </div>
        </div>
        <div class="workspace-record-empty">
            <code>event_id</code>, <code>event_type</code>, <code>invoice_number</code>,
            <code>amount</code>, <code>currency</code>, optional <code>reference</code>,
            optional <code>processed_at</code>. Supported event types:
            payment.succeeded, payment.failed, refund.succeeded, refund.failed,
            credit.succeeded, credit.failed.
        </div>
    </section>
</section>
