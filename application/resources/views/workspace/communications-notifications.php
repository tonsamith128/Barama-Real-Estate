<?php

declare(strict_types=1);

$notifications = is_array($notifications ?? null) ? $notifications : [];
$summary = is_array($communicationsSummary ?? null) ? $communicationsSummary : [];
?>
<section class="workspace-page notification-center-page">
    <!-- BARAMA_NOTIFICATION_CENTER_LITERAL_NEWLINE_FIX_V102 -->    <!-- BARAMA_NOTIFICATION_CENTER_VERTICAL_CARDS_V100 -->
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Notification Center</h1>
            <p>Review platform-generated user notifications across saved searches, inquiries, viewings, and other delivery workflows.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Read-only notification oversight
        </span>
    </div>

    <div class="workspace-stat-grid notification-center-stats" aria-label="Notification overview">
        <article class="workspace-stat notification-center-stat notification-center-stat--total">
            <span class="notification-center-stat__icon"><?= app_icon('message') ?></span>
            <strong><?= e(number_format((int) ($summary['notifications'] ?? 0))) ?></strong>
            <small>Total notifications</small>
        </article>
        <article class="workspace-stat notification-center-stat notification-center-stat--unread">
            <span class="notification-center-stat__icon"><?= app_icon('alert') ?></span>
            <strong><?= e(number_format((int) ($summary['unread_notifications'] ?? 0))) ?></strong>
            <small>Unread</small>
        </article>
        <article class="workspace-stat notification-center-stat notification-center-stat--pending">
            <span class="notification-center-stat__icon"><?= app_icon('pipeline') ?></span>
            <strong><?= e(number_format((int) ($summary['pending_email'] ?? 0))) ?></strong>
            <small>Pending email</small>
        </article>
        <article class="workspace-stat notification-center-stat notification-center-stat--sent">
            <span class="notification-center-stat__icon"><?= app_icon('check') ?></span>
            <strong><?= e(number_format((int) ($summary['sent_email'] ?? 0))) ?></strong>
            <small>Sent email</small>
        </article>
    </div>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Recent platform notifications</h2>
                <p>Administrator oversight does not modify a recipient's read state.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Created</th>
                        <th>Recipient</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>State</th>
                        <th>Entity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($notifications === []): ?>
                        <tr><td colspan="8"><div class="workspace-record-empty">No notifications have been generated yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><?= e((string) $notification['created_at']) ?></td>
                            <td>
                                <strong><?= e((string) $notification['user_name']) ?></strong>
                                <small><?= e((string) $notification['user_email']) ?></small>
                            </td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string) $notification['category']))) ?></td>
                            <td><?= e((string) $notification['type']) ?></td>
                            <td><strong><?= e((string) $notification['title']) ?></strong></td>
                            <td><?= e((string) $notification['message']) ?></td>
                            <td><span class="status-badge status-badge--<?= e($notification['read_at'] === null ? 'pending' : 'approved') ?>"><?= e($notification['read_at'] === null ? 'Unread' : 'Read') ?></span></td>
                            <td>
                                <?php if ($notification['entity_type'] !== null): ?>
                                    <?= e((string) $notification['entity_type']) ?> #<?= e((string) $notification['entity_id']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
