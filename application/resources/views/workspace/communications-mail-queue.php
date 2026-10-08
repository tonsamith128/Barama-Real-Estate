<?php
declare(strict_types=1);
$outbox = is_array($outbox ?? null) ? $outbox : [];
$summary = is_array($communicationsSummary ?? null) ? $communicationsSummary : [];
$filters = is_array($mailQueueFilters ?? null) ? $mailQueueFilters : [];
$pagination = is_array($mailQueuePagination ?? null) ? $mailQueuePagination : [];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['pages'] ?? 1));
$perPage = 10;
$totalMessages = max(0, (int) ($pagination['total'] ?? count($outbox)));
$rangeStart = $totalMessages > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$rangeEnd = $totalMessages > 0 ? min($totalMessages, $currentPage * $perPage) : 0;
$activeStatus = (string) ($filters['status'] ?? '');
$hasFilters = trim((string) ($filters['recipient'] ?? '')) !== ''
    || trim((string) ($filters['subject'] ?? '')) !== ''
    || $activeStatus !== ''
    || trim((string) ($filters['date_from'] ?? '')) !== ''
    || trim((string) ($filters['date_to'] ?? '')) !== '';

/* BARAMA_MAIL_QUEUE_OPERATIONS_V110 */
/* BARAMA_MAIL_QUEUE_OPERATIONS_V120 */
$queueUrl = static function (array $overrides = []) use ($filters, $perPage): string {
    $params = [
        'recipient' => trim((string) ($filters['recipient'] ?? '')),
        'subject' => trim((string) ($filters['subject'] ?? '')),
        'status' => trim((string) ($filters['status'] ?? '')),
        'date_from' => trim((string) ($filters['date_from'] ?? '')),
        'date_to' => trim((string) ($filters['date_to'] ?? '')),
        'per_page' => $perPage,
    ];

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    $params = array_filter($params, static fn (mixed $value): bool => $value !== '' && $value !== null);
    $query = http_build_query($params);
    return url('/dashboard/mail-queue') . ($query !== '' ? '?' . $query : '');
};
?>
<!-- BARAMA_MAIL_QUEUE_VERTICAL_CARDS_V100 -->
<link rel="stylesheet" href="<?= e(asset('css/admin-mail-queue-compact-v100.css') . '?v=100') ?>">
<section class="workspace-page mail-queue-page" data-mail-queue-page>
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Mail Queue &amp; Logs</h1>
            <p>Inspect durable email delivery state, review message details, and safely recover eligible mail without bypassing the canonical outbox worker.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Durable email outbox
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="mail-queue-metrics" aria-label="Mail queue summary">
        <?php
        $metricCards = [
            'pending' => ['icon' => 'pipeline', 'count' => (int) ($summary['pending_email'] ?? 0), 'label' => 'Pending'],
            'processing' => ['icon' => 'settings', 'count' => (int) ($summary['processing_email'] ?? 0), 'label' => 'Processing'],
            'failed' => ['icon' => 'alert', 'count' => (int) ($summary['failed_email'] ?? 0), 'label' => 'Failed'],
            'sent' => ['icon' => 'check', 'count' => (int) ($summary['sent_email'] ?? 0), 'label' => 'Sent'],
            'cancelled' => ['icon' => 'layers', 'count' => (int) ($summary['cancelled_email'] ?? 0), 'label' => 'Cancelled'],
        ];
        ?>
        <?php foreach ($metricCards as $metricStatus => $metric): ?>
            <?php
            $metricActive = $activeStatus === $metricStatus;
            $metricHref = $queueUrl(['status' => $metricActive ? '' : $metricStatus, 'page' => 1]);
            ?>
            <a
                class="mail-queue-metric mail-queue-metric--<?= e($metricStatus) ?><?= $metricActive ? ' is-active' : '' ?>"
                href="<?= e($metricHref) ?>"
                aria-label="<?= e(($metricActive ? 'Clear ' : 'Filter by ') . strtolower((string) $metric['label']) . ' email') ?>"
                <?= $metricActive ? 'aria-current="true"' : '' ?>
            >
                <span class="mail-queue-metric__icon" aria-hidden="true"><?= app_icon((string) $metric['icon']) ?></span>
                <strong class="mail-queue-metric__value"><?= e(number_format((int) $metric['count'])) ?></strong>
                <span class="mail-queue-metric__label"><?= e((string) $metric['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <section class="workspace-table-section mail-queue-history">
        <div class="workspace-table-head mail-queue-history__head">
            <div>
                <h2>Email delivery history</h2>
                <p>Open any message for content and lifecycle details. Manual actions remain limited to statuses that the canonical delivery service allows.</p>
            </div>
            <div class="mail-queue-history__count" aria-live="polite">
                <strong><?= e(number_format($totalMessages)) ?></strong>
                <span><?= $totalMessages === 1 ? 'message' : 'messages' ?></span>
            </div>
        </div>

        <?php if ((int) ($summary['pending_email'] ?? 0) > 0): ?>
            <div class="mail-queue-worker-note" role="status">
                <span class="mail-queue-worker-note__icon" aria-hidden="true"><?= app_icon('clock') ?></span>
                <div>
                    <strong>Pending means queued, not sent.</strong>
                    <p>Messages with 0 attempts are waiting for the scheduled outbox worker. Confirm an aaPanel Cron job runs <code>php tools/notifications.php run</code> from the Barama installation using its PHP CLI. New SMTP settings alone do not send queued messages.</p>
                </div>
                <a href="<?= e(url('/dashboard/settings/mail')) ?>">Mail Setup</a>
            </div>
        <?php endif; ?>

        <form class="mail-queue-filters" action="<?= e(url('/dashboard/mail-queue')) ?>" method="get" aria-label="Filter email delivery history" data-mail-queue-filter-form>
            <label class="mail-queue-filter mail-queue-filter--wide"><span>Recipient</span><input type="search" name="recipient" value="<?= e((string) ($filters['recipient'] ?? '')) ?>" placeholder="Name or email address" autocomplete="off"></label>
            <label class="mail-queue-filter mail-queue-filter--wide"><span>Subject</span><input type="search" name="subject" value="<?= e((string) ($filters['subject'] ?? '')) ?>" placeholder="Email subject" autocomplete="off"></label>
            <label class="mail-queue-filter"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (['pending', 'processing', 'failed', 'sent', 'cancelled'] as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= $activeStatus === $statusOption ? 'selected' : '' ?>><?= e(ucfirst($statusOption)) ?></option><?php endforeach; ?></select></label>
            <label class="mail-queue-filter"><span>From</span><input type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>"></label>
            <label class="mail-queue-filter"><span>To</span><input type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>"></label>
            <input type="hidden" name="per_page" value="10">
            <div class="mail-queue-filter-actions"><button class="mail-queue-filter-submit" type="submit"><?= app_icon('search') ?><span>Filter</span></button><?php if ($hasFilters): ?><a class="mail-queue-filter-clear" href="<?= e(url('/dashboard/mail-queue')) ?>">Clear</a><?php endif; ?></div>
        </form>

        <div class="mail-queue-presets" data-mail-queue-presets>
            <div class="mail-queue-presets__label">
                <span>Saved views</span>
                <small>Stored in this browser only</small>
            </div>
            <select aria-label="Saved Mail Queue views" data-mail-queue-preset-list>
                <option value="">Choose a saved view</option>
            </select>
            <button type="button" data-mail-queue-preset-save>Save current</button>
            <button type="button" data-mail-queue-preset-delete disabled>Delete</button>
        </div>

        <div class="mail-queue-filter-summary">
            <span>Showing <?= e(number_format($rangeStart)) ?>–<?= e(number_format($rangeEnd)) ?> of <?= e(number_format($totalMessages)) ?> · 10 per page</span>
            <?php if ($hasFilters): ?><span class="mail-queue-filter-summary__active">Filtered view</span><?php endif; ?>
        </div>

        <form id="mail-queue-bulk-form" class="mail-queue-bulk" action="<?= e(url('/dashboard/mail-queue/bulk')) ?>" method="post" data-mail-queue-bulk-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="" data-mail-queue-bulk-action-value>
            <div class="mail-queue-bulk__selection">
                <span><strong data-mail-queue-selected-count>0</strong> selected</span>
                <small>Bulk actions are enabled only when every selected message is eligible.</small>
            </div>
            <div class="mail-queue-bulk__actions">
                <button type="submit" value="requeue" data-mail-queue-bulk-action="requeue" disabled>Requeue selected</button>
                <button type="submit" value="cancel" data-mail-queue-bulk-action="cancel" disabled>Cancel selected</button>
            </div>
        </form>

        <div class="workspace-table-wrap">
            <table class="workspace-table mail-queue-table">
                <thead>
                    <tr>
                        <th class="mail-queue-table__select"><input type="checkbox" aria-label="Select all eligible messages on this page" data-mail-queue-select-all></th>
                        <th>Created</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Available</th>

                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($outbox === []): ?>
                        <tr><td colspan="8"><div class="workspace-record-empty"><?= $hasFilters ? 'No email messages match these filters.' : 'The email outbox is empty.' ?><?php if ($hasFilters): ?><a href="<?= e(url('/dashboard/mail-queue')) ?>">Clear filters</a><?php endif; ?></div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($outbox as $message): ?>
                        <?php
                        $status = (string) $message['status'];
                        $tone = $status === 'sent' ? 'approved' : (in_array($status, ['failed', 'cancelled'], true) ? 'neutral' : 'pending');
                        $bulkEligible = in_array($status, ['pending', 'failed', 'cancelled'], true);
                        ?>
                        <tr>
                            <td class="mail-queue-table__select">
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="<?= e((string) (int) $message['id']) ?>"
                                    form="mail-queue-bulk-form"
                                    data-mail-queue-select
                                    data-status="<?= e($status) ?>"
                                    aria-label="Select <?= e((string) $message['subject']) ?>"
                                    <?= $bulkEligible ? '' : 'disabled' ?>
                                >
                            </td>
                            <td><?= e((string) $message['created_at']) ?></td>
                            <td>
                                <strong><?= e((string) ($message['user_name'] ?? 'External recipient')) ?></strong>
                                <small><?= e((string) $message['to_address']) ?></small>
                            </td>
                            <td class="mail-queue-table__subject"><?= e((string) $message['subject']) ?></td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"<?= $status === 'pending' ? ' title="Queued: waiting for the scheduled delivery worker."' : '' ?>><?= e(ucfirst($status)) ?></span><?php if ($status === 'pending' && (int) ($message['attempts'] ?? 0) === 0): ?><small class="mail-queue-status-hint">Not attempted</small><?php endif; ?></td>
                            <td><?= e(number_format((int) $message['attempts'])) ?></td>
                            <td><?= e((string) $message['available_at']) ?></td>

                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Mail actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <button class="table-action" type="button" data-mail-queue-detail data-id="<?= e((string) (int) $message['id']) ?>">View details</button>
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
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php $pageStart = max(1, $currentPage - 2); $pageEnd = min($totalPages, $currentPage + 2); ?>
            <nav class="mail-queue-pagination" aria-label="Email delivery history pages">
                <div class="mail-queue-pagination__meta">Page <?= e(number_format($currentPage)) ?> of <?= e(number_format($totalPages)) ?></div>
                <div class="mail-queue-pagination__links">
                    <a class="mail-queue-page-link<?= $currentPage <= 1 ? ' is-disabled' : '' ?>" href="<?= e($currentPage > 1 ? $queueUrl(['page' => $currentPage - 1]) : '#') ?>" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= app_icon('chevron-left') ?><span>Previous</span></a>
                    <?php for ($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++): ?><a class="mail-queue-page-link mail-queue-page-link--number<?= $pageNumber === $currentPage ? ' is-active' : '' ?>" href="<?= e($queueUrl(['page' => $pageNumber])) ?>" <?= $pageNumber === $currentPage ? 'aria-current="page"' : '' ?>><?= e((string) $pageNumber) ?></a><?php endfor; ?>
                    <a class="mail-queue-page-link<?= $currentPage >= $totalPages ? ' is-disabled' : '' ?>" href="<?= e($currentPage < $totalPages ? $queueUrl(['page' => $currentPage + 1]) : '#') ?>" <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>><span>Next</span><?= app_icon('chevron-right') ?></a>
                </div>
                <form class="mail-queue-pagination__goto" action="<?= e(url('/dashboard/mail-queue')) ?>" method="get" aria-label="Go to mail history page">
                    <?php foreach (['recipient', 'subject', 'status', 'date_from', 'date_to'] as $filterName): ?>
                        <?php $filterValue = trim((string) ($filters[$filterName] ?? '')); ?>
                        <?php if ($filterValue !== ''): ?>
                            <input type="hidden" name="<?= e($filterName) ?>" value="<?= e($filterValue) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="per_page" value="10">
                    <label for="mail-queue-page-number">Go to</label>
                    <input id="mail-queue-page-number" type="number" name="page" min="1" max="<?= e((string) $totalPages) ?>" value="<?= e((string) $currentPage) ?>" required inputmode="numeric" aria-label="Mail queue page number">
                    <button type="submit">Go</button>
                </form>
            </nav>
        <?php endif; ?>
    </section>

    <div class="mail-queue-drawer" data-mail-queue-drawer hidden>
        <button class="mail-queue-drawer__backdrop" type="button" aria-label="Close message details" data-mail-queue-drawer-close></button>
        <aside class="mail-queue-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="mail-queue-drawer-title" tabindex="-1" data-mail-queue-drawer-panel>
            <header class="mail-queue-drawer__header">
                <div>
                    <span class="mail-queue-drawer__eyebrow">Message details</span>
                    <h2 id="mail-queue-drawer-title" data-mail-queue-detail-subject>Loading…</h2>
                </div>
                <button class="mail-queue-drawer__close" type="button" aria-label="Close" data-mail-queue-drawer-close>&times;</button>
            </header>
            <div class="mail-queue-drawer__body">
                <div class="mail-queue-detail-status-row">
                    <span class="status-badge status-badge--neutral" data-mail-queue-detail-status>Loading</span>
                    <span data-mail-queue-detail-id></span>
                </div>

                <div class="mail-queue-detail-copy-grid">
                    <div><span>Recipient</span><strong data-mail-queue-detail-recipient></strong><button type="button" data-mail-queue-copy="recipient">Copy</button></div>
                    <div><span>Subject</span><strong data-mail-queue-detail-subject-copy></strong><button type="button" data-mail-queue-copy="subject">Copy</button></div>
                </div>

                <dl class="mail-queue-detail-grid">
                    <div><dt>Attempts</dt><dd data-mail-queue-detail-attempts>—</dd></div>
                    <div><dt>Created</dt><dd data-mail-queue-detail-created>—</dd></div>
                    <div><dt>Available</dt><dd data-mail-queue-detail-available>—</dd></div>
                    <div><dt>Last updated</dt><dd data-mail-queue-detail-updated>—</dd></div>
                    <div><dt>Processing lock</dt><dd data-mail-queue-detail-locked>—</dd></div>
                    <div><dt>Sent</dt><dd data-mail-queue-detail-sent>—</dd></div>
                </dl>

                <section class="mail-queue-detail-section">
                    <div class="mail-queue-detail-section__head"><h3>Lifecycle timeline</h3><small>Derived from the current outbox row</small></div>
                    <ol class="mail-queue-timeline" data-mail-queue-detail-timeline></ol>
                </section>

                <section class="mail-queue-detail-section" data-mail-queue-error-section hidden>
                    <div class="mail-queue-detail-section__head"><h3>Latest delivery error</h3></div>
                    <pre class="mail-queue-error" data-mail-queue-detail-error></pre>
                </section>

                <section class="mail-queue-detail-section">
                    <div class="mail-queue-detail-tabs" role="tablist" aria-label="Message content">
                        <button class="is-active" type="button" role="tab" aria-selected="true" data-mail-queue-content-tab="text">Plain text</button>
                        <button type="button" role="tab" aria-selected="false" data-mail-queue-content-tab="html">HTML source</button>
                    </div>
                    <pre class="mail-queue-content-source" data-mail-queue-content-panel="text"></pre>
                    <pre class="mail-queue-content-source" data-mail-queue-content-panel="html" hidden></pre>
                </section>
            </div>
        </aside>
    </div>
</section>
<script>
/* BARAMA_MAIL_QUEUE_OPERATIONS_V120_JS */
(() => {
    'use strict';
    const page = document.querySelector('[data-mail-queue-page]');
    if (!page) return;

    const notice = page.querySelector('[data-workspace-notice]');
    const showNotice = (message, type = 'success') => {
        if (!notice) return;
        notice.hidden = false;
        notice.classList.remove('is-success', 'is-error');
        notice.classList.add(type === 'error' ? 'is-error' : 'is-success');
        notice.textContent = message;
        notice.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };

    const bulkForm = page.querySelector('[data-mail-queue-bulk-form]');
    const selectAll = page.querySelector('[data-mail-queue-select-all]');
    const items = Array.from(page.querySelectorAll('[data-mail-queue-select]'));
    const countTarget = page.querySelector('[data-mail-queue-selected-count]');
    const actionValue = page.querySelector('[data-mail-queue-bulk-action-value]');
    const bulkButtons = Array.from(page.querySelectorAll('[data-mail-queue-bulk-action]'));

    const selectedItems = () => items.filter((item) => item.checked && !item.disabled);
    const updateBulkState = () => {
        const selected = selectedItems();
        const statuses = selected.map((item) => item.dataset.status || '');
        if (countTarget) countTarget.textContent = String(selected.length);
        if (selectAll) {
            const eligible = items.filter((item) => !item.disabled);
            selectAll.checked = eligible.length > 0 && selected.length === eligible.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < eligible.length;
        }
        bulkButtons.forEach((button) => {
            const action = button.dataset.mailQueueBulkAction;
            const allowed = action === 'requeue'
                ? statuses.every((status) => status === 'failed' || status === 'cancelled')
                : statuses.every((status) => status === 'pending' || status === 'failed');
            button.disabled = selected.length === 0 || !allowed;
        });
    };

    selectAll?.addEventListener('change', () => {
        items.forEach((item) => { if (!item.disabled) item.checked = selectAll.checked; });
        updateBulkState();
    });
    items.forEach((item) => item.addEventListener('change', updateBulkState));
    updateBulkState();

    bulkForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitter = event.submitter;
        const action = submitter?.dataset.mailQueueBulkAction || '';
        if (!action || submitter?.disabled) return;
        const selected = selectedItems();
        if (selected.length < 1) {
            showNotice('Select at least one eligible mail item.', 'error');
            return;
        }
        const verb = action === 'requeue' ? 'requeue' : 'cancel';
        if (!window.confirm(`Confirm ${verb} for ${selected.length} selected message${selected.length === 1 ? '' : 's'}?`)) return;
        if (actionValue) actionValue.value = action;
        submitter.disabled = true;
        try {
            const response = await fetch(bulkForm.action, {
                method: 'POST', body: new FormData(bulkForm), credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || payload?.ok !== true) throw new Error(payload?.message || 'Bulk action could not be completed.');
            showNotice(payload?.data?.message || 'Bulk mail action completed.');
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            showNotice(error instanceof Error ? error.message : 'Bulk action could not be completed.', 'error');
            updateBulkState();
        }
    });

    const drawer = page.querySelector('[data-mail-queue-drawer]');
    const drawerPanel = page.querySelector('[data-mail-queue-drawer-panel]');
    let detailData = null;
    const text = (selector, value) => {
        const node = page.querySelector(selector);
        if (node) node.textContent = value ?? '—';
    };
    const closeDrawer = () => {
        if (!drawer) return;
        drawer.hidden = true;
        document.body.classList.remove('mail-queue-drawer-open');
    };
    page.querySelectorAll('[data-mail-queue-drawer-close]').forEach((button) => button.addEventListener('click', closeDrawer));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer && !drawer.hidden) closeDrawer(); });

    const renderDetail = (data) => {
        detailData = data;
        text('[data-mail-queue-detail-subject]', data.subject || 'Untitled email');
        text('[data-mail-queue-detail-subject-copy]', data.subject || '');
        text('[data-mail-queue-detail-recipient]', data.recipient_label || data.to_address || '');
        text('[data-mail-queue-detail-status]', (data.status || '').replace(/^./, (c) => c.toUpperCase()));
        text('[data-mail-queue-detail-id]', `Queue #${data.id}`);
        text('[data-mail-queue-detail-attempts]', String(data.attempts ?? 0));
        text('[data-mail-queue-detail-created]', data.created_at || '—');
        text('[data-mail-queue-detail-available]', data.available_at || '—');
        text('[data-mail-queue-detail-updated]', data.updated_at || '—');
        text('[data-mail-queue-detail-locked]', data.locked_at || '—');
        text('[data-mail-queue-detail-sent]', data.sent_at || '—');
        text('[data-mail-queue-content-panel="text"]', data.text_body || 'No plain-text body is stored for this message.');
        text('[data-mail-queue-content-panel="html"]', data.html_body || 'No HTML body is stored for this message.');
        const statusNode = page.querySelector('[data-mail-queue-detail-status]');
        if (statusNode) statusNode.className = `status-badge status-badge--${data.status === 'sent' ? 'approved' : (['failed','cancelled'].includes(data.status) ? 'neutral' : 'pending')}`;
        const errorSection = page.querySelector('[data-mail-queue-error-section]');
        const errorNode = page.querySelector('[data-mail-queue-detail-error]');
        const hasError = Boolean((data.last_error || '').trim());
        if (errorSection) errorSection.hidden = !hasError;
        if (errorNode) errorNode.textContent = hasError ? data.last_error : '';
        const timeline = page.querySelector('[data-mail-queue-detail-timeline]');
        if (timeline) {
            timeline.innerHTML = '';
            (Array.isArray(data.timeline) ? data.timeline : []).forEach((event) => {
                const item = document.createElement('li');
                const dot = document.createElement('span'); dot.className = 'mail-queue-timeline__dot';
                const body = document.createElement('div');
                const label = document.createElement('strong'); label.textContent = event.label || 'Event';
                const meta = document.createElement('small'); meta.textContent = [event.time || '', event.detail || ''].filter(Boolean).join(' · ');
                body.append(label, meta); item.append(dot, body); timeline.append(item);
            });
        }
    };

    page.querySelectorAll('[data-mail-queue-detail]').forEach((button) => {
        button.addEventListener('click', async () => {
            const id = button.dataset.id;
            if (!id || !drawer) return;
            drawer.hidden = false;
            document.body.classList.add('mail-queue-drawer-open');
            drawerPanel?.focus();
            text('[data-mail-queue-detail-subject]', 'Loading…');
            try {
                const response = await fetch(`/dashboard/mail-queue/${encodeURIComponent(id)}/details`, {
                    credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok || payload?.ok !== true) throw new Error(payload?.message || 'Message details could not be loaded.');
                renderDetail(payload.data || {});
            } catch (error) {
                closeDrawer();
                showNotice(error instanceof Error ? error.message : 'Message details could not be loaded.', 'error');
            }
        });
    });

    page.querySelectorAll('[data-mail-queue-content-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.mailQueueContentTab;
            page.querySelectorAll('[data-mail-queue-content-tab]').forEach((tab) => {
                const active = tab === button; tab.classList.toggle('is-active', active); tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            page.querySelectorAll('[data-mail-queue-content-panel]').forEach((panel) => { panel.hidden = panel.dataset.mailQueueContentPanel !== target; });
        });
    });

    const copyValue = async (value) => {
        if (!value) return;
        try { await navigator.clipboard.writeText(value); }
        catch (_) {
            const area = document.createElement('textarea'); area.value = value; area.style.position = 'fixed'; area.style.opacity = '0'; document.body.append(area); area.select(); document.execCommand('copy'); area.remove();
        }
        showNotice('Copied to clipboard.');
    };
    page.querySelectorAll('[data-mail-queue-copy]').forEach((button) => button.addEventListener('click', () => {
        if (!detailData) return;
        const field = button.dataset.mailQueueCopy;
        copyValue(field === 'recipient' ? (detailData.to_address || '') : (detailData.subject || ''));
    }));

    const filterForm = page.querySelector('[data-mail-queue-filter-form]');
    const presetList = page.querySelector('[data-mail-queue-preset-list]');
    const presetSave = page.querySelector('[data-mail-queue-preset-save]');
    const presetDelete = page.querySelector('[data-mail-queue-preset-delete]');
    const storageKey = 'barama-mail-queue-filter-presets-v120';
    const loadPresets = () => {
        try {
            const raw = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(raw) ? raw.slice(0, 8) : [];
        } catch (_) { return []; }
    };
    let presets = loadPresets();
    const renderPresets = () => {
        if (!presetList) return;
        const selected = presetList.value;
        presetList.innerHTML = '<option value="">Choose a saved view</option>';
        presets.forEach((preset, index) => {
            const option = document.createElement('option'); option.value = String(index); option.textContent = preset.name; presetList.append(option);
        });
        if (presets[selected]) presetList.value = selected;
        if (presetDelete) presetDelete.disabled = presetList.value === '';
    };
    renderPresets();
    presetList?.addEventListener('change', () => {
        if (presetDelete) presetDelete.disabled = presetList.value === '';
        const preset = presets[Number(presetList.value)];
        if (!preset || !filterForm) return;
        Object.entries(preset.filters || {}).forEach(([name, value]) => {
            const field = filterForm.elements.namedItem(name); if (field) field.value = String(value ?? '');
        });
        filterForm.submit();
    });
    presetSave?.addEventListener('click', () => {
        if (!filterForm) return;
        const proposed = window.prompt('Name this Mail Queue view:');
        const name = (proposed || '').trim().slice(0, 40);
        if (!name) return;
        const data = new FormData(filterForm);
        const filters = {};
        ['recipient','subject','status','date_from','date_to','per_page'].forEach((key) => { filters[key] = data.get(key) || ''; });
        const existing = presets.findIndex((preset) => preset.name.toLowerCase() === name.toLowerCase());
        const entry = {name, filters};
        if (existing >= 0) presets[existing] = entry; else presets = [...presets, entry].slice(-8);
        localStorage.setItem(storageKey, JSON.stringify(presets));
        renderPresets(); showNotice('Saved Mail Queue view.');
    });
    presetDelete?.addEventListener('click', () => {
        if (!presetList || presetList.value === '') return;
        const index = Number(presetList.value); if (!Number.isInteger(index) || !presets[index]) return;
        presets.splice(index, 1); localStorage.setItem(storageKey, JSON.stringify(presets)); renderPresets(); showNotice('Saved view deleted.');
    });
})();
</script>
