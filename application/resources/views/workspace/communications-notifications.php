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

    <section class="workspace-table-section barama-notification-admin-v112">
        <div class="workspace-table-head">
            <div><h2>Recent platform notifications</h2><p>Oversight is read-only for other recipients. Open your own notifications to acknowledge them.</p></div>
        </div>
        <form class="barama-notification-admin-v112__filters" method="get" action="<?= e(url('/dashboard/notifications')) ?>">
            <label><span>Search notifications</span><input type="search" name="q" maxlength="80" value="<?= e((string)($notificationSearch ?? '')) ?>" placeholder="Search title, message or recipient"></label>
            <label><span>Status</span><select name="state"><option value="all">All statuses</option><option value="unread" <?= ($notificationState ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= ($notificationState ?? '') === 'read' ? 'selected' : '' ?>>Read</option></select></label>
            <button type="submit" class="btn btn-primary">Filter</button><a href="<?= e(url('/dashboard/notifications')) ?>" class="btn btn-outline-secondary">Reset</a>
        </form>
        <div class="barama-notification-table-v112__wrap">
            <table class="barama-notification-table-v112" aria-label="Platform notifications">
                <thead><tr><th>Notification</th><th>Recipient</th><th>Category</th><th>Created</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if ($notifications === []): ?><tr><td colspan="6" class="barama-notification-table-v112__empty">No notifications match your filters.</td></tr><?php endif; ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php $unread = empty($notification['read_at']); $owned = (int)$notification['user_id'] === (int)($notificationViewerId ?? 0); ?>
                        <tr class="<?= $unread ? 'is-unread' : '' ?>">
                            <td data-label="Notification"><strong><?= e((string)$notification['title']) ?></strong><small><?= e((string)$notification['message']) ?></small></td>
                            <td data-label="Recipient"><strong><?= e((string)$notification['user_name']) ?></strong><small><?= e((string)$notification['user_email']) ?></small></td>
                            <td data-label="Category"><?= e(ucwords(str_replace('_', ' ', (string)$notification['category']))) ?></td>
                            <td data-label="Created"><time><?= e((string)$notification['created_at']) ?></time></td>
                            <td data-label="Status"><span class="barama-notification-table-v112__badge <?= $unread ? 'is-unread' : 'is-read' ?>"><?= $unread ? 'Unread' : 'Read' ?></span></td>
                            <td data-label="Action">
                                <?php if ($owned): ?><form method="post" action="<?= e(url('/notifications/' . (int)$notification['id'] . '/open')) ?>"><?= csrf_field() ?><input type="hidden" name="source" value="dashboard"><button class="barama-notification-table-v112__open" type="submit">Open <span aria-hidden="true">→</span></button></form>
                                <?php else: ?><span class="barama-notification-table-v112__readonly" title="Another recipient's read status is private">Read-only</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
