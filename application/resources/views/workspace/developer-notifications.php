<?php declare(strict_types=1); ?>
<section class="workspace-page barama-notification-center-v111">
    <header class="workspace-page__header barama-notification-center-v111__header">
        <div>
            <span class="eyebrow">Company / Vendor Workspace</span>
            <h1>Notifications</h1>
            <p>Updates for your signed-in account and company activity.</p>
        </div>
        <?php if ($notificationUnread > 0): ?>
            <form method="post" action="<?= e(url('/developer/notifications/read-all')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary" type="submit">Mark all read (<?= (int)$notificationUnread ?>)</button>
            </form>
        <?php endif; ?>
    </header>
    <div class="barama-notification-center-v111__content">
        <form class="barama-notification-center-v111__filters" action="<?= e(url('/developer/notifications')) ?>" method="get" role="search">
            <label><span>Search notifications</span><input class="form-control" name="q" type="search" maxlength="80" value="<?= e((string)$notificationQuery) ?>" placeholder="Search title or message"></label>
            <label><span>State</span><select class="form-select" name="state">
                <?php foreach (['all' => 'All notifications', 'unread' => 'Unread', 'read' => 'Read'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $notificationState === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/developer/notifications')) ?>">Reset</a>
        </form>
        <div class="barama-notification-table-v112__wrap">
            <table class="barama-notification-table-v112" aria-label="Your notifications">
                <thead><tr><th>Notification</th><th>Category</th><th>Received</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php if ($notificationItems === []): ?>
                    <tr><td colspan="5" class="barama-notification-table-v112__empty">No notifications match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($notificationItems as $item): ?>
                    <?php $unread = empty($item['read_at']); ?>
                    <tr class="<?= $unread ? 'is-unread' : '' ?>">
                        <td data-label="Notification"><strong><?= e((string)($item['title'] ?? 'Notification')) ?></strong><small><?= e((string)($item['message'] ?? '')) ?></small></td>
                        <td data-label="Category"><?= e(ucwords(str_replace('_', ' ', (string)($item['category'] ?? 'General')))) ?></td>
                        <td data-label="Received"><time><?= e((string)($item['created_at'] ?? '')) ?></time></td>
                        <td data-label="Status"><span class="barama-notification-table-v112__badge <?= $unread ? 'is-unread' : 'is-read' ?>"><?= $unread ? 'Unread' : 'Read' ?></span></td>
                        <td data-label="Action">
                            <form action="<?= e(url('/notifications/' . (int)$item['id'] . '/open')) ?>" method="post">
                                <?= csrf_field() ?><input type="hidden" name="source" value="developer"><button type="submit" class="barama-notification-table-v112__open">Open <span aria-hidden="true">→</span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
