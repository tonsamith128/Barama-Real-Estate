<?php

declare(strict_types=1);

$outbox = is_array($outbox ?? null) ? $outbox : [];
$summary = is_array($communicationsSummary ?? null) ? $communicationsSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Mail Queue & Logs</h1>
            <p>Inspect durable email delivery state and safely recover failed or cancelled mail without bypassing the canonical outbox worker.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Durable email outbox
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('pipeline') ?></span><strong><?= e(number_format((int) ($summary['pending_email'] ?? 0))) ?></strong><small>Pending</small></div>
        <div class="workspace-stat"><span><?= app_icon('settings') ?></span><strong><?= e(number_format((int) ($summary['processing_email'] ?? 0))) ?></strong><small>Processing</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['failed_email'] ?? 0))) ?></strong><small>Failed</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['sent_email'] ?? 0))) ?></strong><small>Sent</small></div>
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['cancelled_email'] ?? 0))) ?></strong><small>Cancelled</small></div>
    </div>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Email delivery history</h2>
                <p>Manual requeue resets attempts and returns the message to the normal delivery worker. Sent or actively processing mail cannot be changed here.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Created</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Available</th>
                        <th>Sent</th>
                        <th>Error</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($outbox === []): ?>
                        <tr><td colspan="9"><div class="workspace-record-empty">The email outbox is empty.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($outbox as $message): ?>
                        <?php
                        $status = (string) $message['status'];
                        $tone = $status === 'sent' ? 'approved' : (in_array($status, ['failed', 'cancelled'], true) ? 'neutral' : 'pending');
                        ?>
                        <tr>
                            <td><?= e((string) $message['created_at']) ?></td>
                            <td>
                                <strong><?= e((string) ($message['user_name'] ?? 'External recipient')) ?></strong>
                                <small><?= e((string) $message['to_address']) ?></small>
                            </td>
                            <td><?= e((string) $message['subject']) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e(number_format((int) $message['attempts'])) ?></td>
                            <td><?= e((string) $message['available_at']) ?></td>
                            <td><?= e((string) ($message['sent_at'] ?? '—')) ?></td>
                            <td><small><?= e((string) ($message['last_error'] ?? '—')) ?></small></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Mail actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if (in_array($status, ['failed', 'cancelled'], true)): ?>
                                            <form action="<?= e(url('/dashboard/mail-queue/' . (int) $message['id'] . '/requeue')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <button class="table-action table-action--primary" type="submit">Requeue</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($status, ['pending', 'failed'], true)): ?>
                                            <form action="<?= e(url('/dashboard/mail-queue/' . (int) $message['id'] . '/cancel')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <button class="table-action" type="submit">Cancel</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!in_array($status, ['pending', 'failed', 'cancelled'], true)): ?>
                                            <span class="table-action-placeholder">No manual action</span>
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
