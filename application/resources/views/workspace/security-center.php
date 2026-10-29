<?php
/* BARAMA_SECURITY_CENTER_FLAT_PAGINATION_V133 */
$summary = is_array($securitySummary ?? null) ? $securitySummary : [];
$events = is_array($securityEvents ?? null) ? $securityEvents : [];
$rows = is_array($events['rows'] ?? null) ? $events['rows'] : [];
$eventTypes = is_array($securityEventTypes ?? null) ? $securityEventTypes : [];
$throttleRows = is_array($securityThrottleRows ?? null) ? $securityThrottleRows : [];
$policy = is_array($securityThrottlePolicy ?? null) ? $securityThrottlePolicy : [];
$filters = is_array($securityFilters ?? null) ? $securityFilters : [];
$policy = is_array($securityAlertPolicy ?? null)
    ? $securityAlertPolicy
    : [];
$signals = is_array($securitySignals ?? null)
    ? $securitySignals
    : [];
$trend = is_array($securityTrend ?? null)
    ? $securityTrend
    : [];
$retention = is_array($securityRetention ?? null)
    ? $securityRetention
    : [];
$incidentPage = is_array($securityIncidentPage ?? null)
    ? $securityIncidentPage
    : [];
$incidentWorkbench = is_array($incidentPage['rows'] ?? null)
    ? $incidentPage['rows']
    : [];
$incidentMetrics = is_array($securityIncidentMetrics ?? null)
    ? $securityIncidentMetrics
    : [];
$incidentFilters = is_array($securityIncidentFilters ?? null)
    ? $securityIncidentFilters
    : [];
$incidentSlaPolicy = is_array($securityIncidentSlaPolicy ?? null)
    ? $securityIncidentSlaPolicy
    : [];
$incidentAdministrators = is_array($securityIncidentAdministrators ?? null)
    ? $securityIncidentAdministrators
    : [];
$phase6Policy = is_array($securityPhase6Policy ?? null)
    ? $securityPhase6Policy
    : [];
$incidentWorkload = is_array($securityIncidentWorkload ?? null)
    ? $securityIncidentWorkload
    : [];
$reviewStatus = is_array($securityReviewStatus ?? null)
    ? $securityReviewStatus
    : [];
$latestReview = is_array($securityLatestReview ?? null)
    ? $securityLatestReview
    : [];
$securityNotificationHistory = is_array($securityNotificationHistory ?? null)
    ? $securityNotificationHistory
    : [];


$securityUiPage = static function (
    mixed $value,
    int $pages
): int {
    $page = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $page = $page === false ? 1 : (int) $page;

    return min(max(1, $page), max(1, $pages));
};

$workloadPerPage = 10;
$workloadPages = max(
    1,
    (int) ceil(count($incidentWorkload) / $workloadPerPage)
);
$workloadPage = $securityUiPage(
    $_GET['workload_page'] ?? 1,
    $workloadPages
);
$incidentWorkloadVisible = array_slice(
    $incidentWorkload,
    ($workloadPage - 1) * $workloadPerPage,
    $workloadPerPage
);

$notificationPerPage = 10;
$notificationPages = max(
    1,
    (int) ceil(count($securityNotificationHistory) / $notificationPerPage)
);
$notificationPage = $securityUiPage(
    $_GET['notification_page'] ?? 1,
    $notificationPages
);
$securityNotificationHistoryVisible = array_slice(
    $securityNotificationHistory,
    ($notificationPage - 1) * $notificationPerPage,
    $notificationPerPage
);

$throttlePerPage = 10;
$throttlePages = max(
    1,
    (int) ceil(count($throttleRows) / $throttlePerPage)
);
$throttlePage = $securityUiPage(
    $_GET['throttle_page'] ?? 1,
    $throttlePages
);
$throttleRowsVisible = array_slice(
    $throttleRows,
    ($throttlePage - 1) * $throttlePerPage,
    $throttlePerPage
);

$trendMax = 1;
foreach ($trend as $point) {
    $trendMax = max(
        $trendMax,
        (int) ($point['failed'] ?? 0),
        (int) ($point['locked'] ?? 0),
        (int) ($point['success'] ?? 0)
    );
}

$query = [
    'q' => (string) ($filters['q'] ?? ''),
    'family' => (string) ($filters['family'] ?? ''),
    'event_type' => (string) ($filters['event_type'] ?? ''),
    'date_from' => (string) ($filters['date_from'] ?? ''),
    'date_to' => (string) ($filters['date_to'] ?? ''),
];

$query = array_filter(
    $query,
    static fn (string $value): bool => $value !== ''
);

$exportQuery = http_build_query($query);
$baseQuery = $query;

$incidentQuery = [
    'incident_q' => (string) ($incidentFilters['incident_q'] ?? ''),
    'incident_status' => (string) ($incidentFilters['incident_status'] ?? ''),
    'incident_severity' => (string) ($incidentFilters['incident_severity'] ?? ''),
    'incident_assignee' => (string) ($incidentFilters['incident_assignee'] ?? ''),
    'incident_sla' => (string) ($incidentFilters['incident_sla'] ?? ''),
];
$incidentQuery = array_filter(
    $incidentQuery,
    static fn (string $value): bool => $value !== ''
);
$incidentExportQuery = http_build_query($incidentQuery);
$incidentPageQuery = $incidentQuery;


$securityAuxQuery = array_merge($query, $incidentQuery);
$securityPagerUrl = static function (
    string $pageKey,
    int $page,
    string $anchorId = ''
) use ($securityAuxQuery): string {
    $params = $securityAuxQuery;
    $params[$pageKey] = max(1, $page);
    $url = url('/dashboard/audit?' . http_build_query($params));

    return $anchorId !== '' ? $url . '#' . $anchorId : $url;
};
?>
<?= barama_css_once('admin') ?>

<section class="workspace-page security-center" data-security-center>
    <header class="security-center__hero">
        <div>
            <span class="eyebrow">USERS & SECURITY</span>
            <h1>Admin Security Center</h1>
            <p>
                Review authentication activity, failed sign-ins, account-security events,
                and active login throttles from the existing Barama security infrastructure.
            </p>
        </div>

        <div class="security-center__hero-actions">
            <form
                method="post"
                action="<?= e(url('/dashboard/audit/evaluate')) ?>"
            >
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary" type="submit">
                    <?= app_icon('shield') ?> Evaluate alerts
                </button>
            </form>

            <a
                class="btn btn-outline-secondary security-center__export"
                href="<?= e(url('/dashboard/audit/export' . ($exportQuery !== '' ? '?' . $exportQuery : ''))) ?>"
            >
                <?= app_icon('download') ?> Export filtered CSV
            </a>
        </div>
    </header>

    <div class="security-center__stats">
        <article>
            <span><?= app_icon('shield') ?></span>
            <div><small>Events · 24h</small><strong><?= e((string) ($summary['events_24h'] ?? 0)) ?></strong></div>
        </article>
        <article>
            <span><?= app_icon('alert') ?></span>
            <div><small>Failed sign-ins · 24h</small><strong><?= e((string) ($summary['failed_24h'] ?? 0)) ?></strong></div>
        </article>
        <article>
            <span><?= app_icon('lock') ?></span>
            <div><small>Lock events · 24h</small><strong><?= e((string) ($summary['locked_24h'] ?? 0)) ?></strong></div>
        </article>
        <article>
            <span><?= app_icon('clock') ?></span>
            <div><small>Active lockouts</small><strong><?= e((string) ($summary['active_lockouts'] ?? 0)) ?></strong></div>
        </article>
    </div>

    <?php if (!empty($_GET['security_status'])): ?>
        <div class="security-center__notice is-success">
            <?= app_icon('check') ?>
            <span><?= e((string) $_GET['security_status']) ?></span>
        </div>
    <?php endif; ?>

    <div class="security-center__phase3-grid">
        <section class="security-center__card">
            <div class="security-center__card-head">
                <div>
                    <h2>7-day authentication trend</h2>
                    <p>Successful sign-ins, failed attempts, and lock events from the immutable audit stream.</p>
                </div>
                <span class="security-center__phase-badge">PHASE 3</span>
            </div>

            <div class="security-center__chart" aria-label="Seven-day authentication trend">
                <?php foreach ($trend as $point): ?>
                    <div class="security-center__chart-day">
                        <strong><?= e((string) ($point['label'] ?? '')) ?></strong>

                        <label>
                            <span>Success</span>
                            <progress
                                class="is-success"
                                max="<?= (int) $trendMax ?>"
                                value="<?= (int) ($point['success'] ?? 0) ?>"
                            ></progress>
                            <small><?= e((string) ($point['success'] ?? 0)) ?></small>
                        </label>

                        <label>
                            <span>Failed</span>
                            <progress
                                class="is-failed"
                                max="<?= (int) $trendMax ?>"
                                value="<?= (int) ($point['failed'] ?? 0) ?>"
                            ></progress>
                            <small><?= e((string) ($point['failed'] ?? 0)) ?></small>
                        </label>

                        <label>
                            <span>Locked</span>
                            <progress
                                class="is-locked"
                                max="<?= (int) $trendMax ?>"
                                value="<?= (int) ($point['locked'] ?? 0) ?>"
                            ></progress>
                            <small><?= e((string) ($point['locked'] ?? 0)) ?></small>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="security-center__legend">
                <span><i class="is-success"></i> Success</span>
                <span><i class="is-failed"></i> Failed</span>
                <span><i class="is-locked"></i> Locked</span>
            </div>

            <div class="security-center__signal-strip">
                <div>
                    <small>Window</small>
                    <strong><?= e((string) ($policy['window_minutes'] ?? 15)) ?> min</strong>
                </div>
                <div>
                    <small>Failed</small>
                    <strong><?= e((string) ($signals['failed'] ?? 0)) ?></strong>
                </div>
                <div>
                    <small>Lock events</small>
                    <strong><?= e((string) ($signals['locked'] ?? 0)) ?></strong>
                </div>
                <div>
                    <small>Active lockouts</small>
                    <strong><?= e((string) ($signals['active_lockouts'] ?? 0)) ?></strong>
                </div>
            </div>
        </section>

        <section class="security-center__card">
            <div class="security-center__card-head">
                <div>
                    <h2>Security alert policy</h2>
                    <p>Configure alert sensitivity and delivery without changing authentication behavior.</p>
                </div>
            </div>

            <form
                method="post"
                action="<?= e(url('/dashboard/audit/policy')) ?>"
                class="security-center__policy-form"
            >
                <?= csrf_field() ?>

                <div class="security-center__toggle-row">
                    <label>
                        <input
                            type="checkbox"
                            name="enabled"
                            value="1"
                            <?= !empty($policy['enabled']) ? 'checked' : '' ?>
                        >
                        <span>
                            <strong>Security alerts enabled</strong>
                            <small>Evaluate failed sign-ins and lock activity.</small>
                        </span>
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="in_app_alerts"
                            value="1"
                            <?= !empty($policy['in_app_alerts']) ? 'checked' : '' ?>
                        >
                        <span>
                            <strong>In-app administrator alerts</strong>
                            <small>Send deduplicated alerts to Notification Center.</small>
                        </span>
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            name="email_alerts"
                            value="1"
                            <?= !empty($policy['email_alerts']) ? 'checked' : '' ?>
                        >
                        <span>
                            <strong>Email administrator alerts</strong>
                            <small>Disabled by default; uses the existing mail queue.</small>
                        </span>
                    </label>
                </div>

                <div class="security-center__policy-grid">
                    <label>
                        <span>Failed sign-ins threshold</span>
                        <input
                            type="number"
                            min="2"
                            max="100"
                            name="failed_login_threshold"
                            value="<?= e((string) ($policy['failed_login_threshold'] ?? 5)) ?>"
                        >
                    </label>

                    <label>
                        <span>Login-lock threshold</span>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            name="locked_login_threshold"
                            value="<?= e((string) ($policy['locked_login_threshold'] ?? 2)) ?>"
                        >
                    </label>

                    <label>
                        <span>Active lockouts threshold</span>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            name="active_lockout_threshold"
                            value="<?= e((string) ($policy['active_lockout_threshold'] ?? 1)) ?>"
                        >
                    </label>

                    <label>
                        <span>Evaluation window (minutes)</span>
                        <input
                            type="number"
                            min="5"
                            max="1440"
                            name="window_minutes"
                            value="<?= e((string) ($policy['window_minutes'] ?? 15)) ?>"
                        >
                    </label>

                    <label>
                        <span>Alert cooldown (minutes)</span>
                        <input
                            type="number"
                            min="5"
                            max="1440"
                            name="cooldown_minutes"
                            value="<?= e((string) ($policy['cooldown_minutes'] ?? 60)) ?>"
                        >
                    </label>

                    <label>
                        <span>Retention horizon (days)</span>
                        <input
                            type="number"
                            min="30"
                            max="3650"
                            name="retention_days"
                            value="<?= e((string) ($policy['retention_days'] ?? 365)) ?>"
                        >
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">
                    <?= app_icon('shield') ?> Save security policy
                </button>
            </form>

            <div class="security-center__retention">
                <div>
                    <small>Audit events</small>
                    <strong><?= e((string) ($retention['total'] ?? 0)) ?></strong>
                </div>
                <div>
                    <small>Oldest event</small>
                    <strong><?= e((string) (($retention['oldest_event'] ?? '') ?: '—')) ?></strong>
                </div>
                <div>
                    <small>Beyond horizon</small>
                    <strong><?= e((string) ($retention['older_than_horizon'] ?? 0)) ?></strong>
                </div>
            </div>

            <div class="security-center__privacy-note">
                <?= app_icon('shield') ?>
                <span>
                    Retention is a minimum policy horizon only. Automatic deletion is disabled;
                    Phase 3 does not purge immutable security audit events.
                </span>
            </div>
        </section>
    </div>

    <section
        class="security-center__card security-center__phase6"
        id="security-phase6"
    >
        <div class="security-center__card-head">
            <div>
                <h2>Security operations automation</h2>
                <p>
                    Configure SLA targets, overdue escalation behavior, administrator ownership,
                    and the scheduled security review cadence.
                </p>
            </div>
            <span class="security-center__phase-badge">PHASE 6</span>
        </div>

        <div class="security-center__phase6-layout">
            <form
                method="post"
                action="<?= e(url('/dashboard/audit/incidents/phase6-policy')) ?>"
                class="security-center__phase6-policy"
            >
                <?= csrf_field() ?>

                <div class="security-center__phase6-section">
                    <div>
                        <strong>Configurable SLA targets</strong>
                        <small>Hours until an unresolved incident becomes overdue.</small>
                    </div>

                    <div class="security-center__phase6-sla-grid">
                        <?php foreach ([
                            'critical' => [1, 24],
                            'high' => [1, 72],
                            'medium' => [1, 336],
                            'low' => [1, 720],
                        ] as $severity => [$min, $max]): ?>
                            <label>
                                <span><?= e(ucfirst($severity)) ?></span>
                                <div>
                                    <input
                                        type="number"
                                        min="<?= $min ?>"
                                        max="<?= $max ?>"
                                        name="sla_<?= e($severity) ?>"
                                        value="<?= e((string) (
                                            $phase6Policy['sla'][$severity]
                                            ?? $incidentSlaPolicy[$severity]
                                            ?? 24
                                        )) ?>"
                                    >
                                    <small>hours</small>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="security-center__phase6-section">
                    <div>
                        <strong>Escalation rules</strong>
                        <small>
                            The runner evaluates overdue incidents without creating another incident store.
                        </small>
                    </div>

                    <div class="security-center__phase6-toggles">
                        <?php foreach ([
                            'escalation_enabled' => [
                                'Enable overdue escalation',
                                'Allow manual and scheduled escalation evaluation.',
                            ],
                            'notify_overdue_assignee' => [
                                'Notify overdue assignee',
                                'Send deduplicated in-app security alerts.',
                            ],
                            'auto_assign_unassigned' => [
                                'Auto-assign unassigned incidents',
                                'Assign overdue unowned incidents to the escalation owner.',
                            ],
                            'reassign_overdue' => [
                                'Reassign overdue incidents',
                                'Move already assigned overdue incidents to the escalation owner.',
                            ],
                        ] as $key => [$label, $help]): ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?= e($key) ?>"
                                    value="1"
                                    <?= !empty($phase6Policy[$key]) ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong><?= e($label) ?></strong>
                                    <small><?= e($help) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="security-center__phase6-fields">
                        <label>
                            <span>Escalation owner</span>
                            <select name="escalation_assignee_id">
                                <option value="">No automatic owner</option>
                                <?php foreach ($incidentAdministrators as $administrator): ?>
                                    <?php $administratorId = (int) ($administrator['id'] ?? 0); ?>
                                    <option
                                        value="<?= $administratorId ?>"
                                        <?= (int) ($phase6Policy['escalation_assignee_id'] ?? 0) === $administratorId ? 'selected' : '' ?>
                                    >
                                        <?= e((string) ($administrator['name'] ?? 'Administrator')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>Escalation cooldown</span>
                            <div>
                                <input
                                    type="number"
                                    min="15"
                                    max="1440"
                                    name="cooldown_minutes"
                                    value="<?= e((string) ($phase6Policy['cooldown_minutes'] ?? 240)) ?>"
                                >
                                <small>minutes</small>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="security-center__phase6-section">
                    <div>
                        <strong>Scheduled security review</strong>
                        <small>
                            The CLI runner generates the review only when the configured schedule is due.
                        </small>
                    </div>

                    <div class="security-center__phase6-fields">
                        <label>
                            <span>Frequency</span>
                            <select name="review_frequency">
                                <option
                                    value="daily"
                                    <?= ($phase6Policy['review_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>
                                >Daily</option>
                                <option
                                    value="weekly"
                                    <?= ($phase6Policy['review_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>
                                >Weekly</option>
                            </select>
                        </label>

                        <label>
                            <span>Run hour</span>
                            <select name="review_hour">
                                <?php for ($hour = 0; $hour <= 23; $hour++): ?>
                                    <option
                                        value="<?= $hour ?>"
                                        <?= (int) ($phase6Policy['review_hour'] ?? 8) === $hour ? 'selected' : '' ?>
                                    >
                                        <?= e(sprintf('%02d:00', $hour)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </label>

                        <label>
                            <span>Weekly day</span>
                            <select name="review_weekday">
                                <?php foreach ([
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                    7 => 'Sunday',
                                ] as $weekday => $weekdayLabel): ?>
                                    <option
                                        value="<?= $weekday ?>"
                                        <?= (int) ($phase6Policy['review_weekday'] ?? 1) === $weekday ? 'selected' : '' ?>
                                    ><?= e($weekdayLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">
                    <?= app_icon('shield') ?> Save Phase 6 policy
                </button>
            </form>

            <div class="security-center__phase6-actions">
                <div class="security-center__phase6-status">
                    <div>
                        <small>Review cadence</small>
                        <strong><?= e(ucfirst((string) ($reviewStatus['frequency'] ?? 'daily'))) ?></strong>
                    </div>
                    <div>
                        <small>Last generated</small>
                        <strong><?= e((string) (($reviewStatus['last_generated_at'] ?? '') ?: 'Never')) ?></strong>
                    </div>
                    <div>
                        <small>Next due</small>
                        <strong><?= e((string) (($reviewStatus['next_due_at'] ?? '') ?: '—')) ?></strong>
                    </div>
                    <div>
                        <small>Schedule state</small>
                        <strong class="<?= !empty($reviewStatus['due']) ? 'is-overdue' : 'is-good' ?>">
                            <?= !empty($reviewStatus['due']) ? 'Due' : 'Scheduled' ?>
                        </strong>
                    </div>
                </div>

                <div class="security-center__phase6-run">
                    <form
                        method="post"
                        action="<?= e(url('/dashboard/audit/incidents/escalate')) ?>"
                    >
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary" type="submit">
                            Evaluate escalations now
                        </button>
                    </form>

                    <form
                        method="post"
                        action="<?= e(url('/dashboard/audit/incidents/review')) ?>"
                    >
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary" type="submit">
                            Generate security review
                        </button>
                    </form>
                </div>

                <div
                    class="security-center__phase6-review"
                    id="security-phase6-review"
                >
                    <div>
                        <strong>Latest security review</strong>
                        <small>
                            <?= e((string) (($latestReview['generated_at'] ?? '') ?: 'No review generated yet')) ?>
                        </small>
                    </div>

                    <?php $latestMetrics = is_array($latestReview['metrics'] ?? null) ? $latestReview['metrics'] : []; ?>
                    <div class="security-center__phase6-review-metrics">
                        <span>
                            <small>Open</small>
                            <strong><?= e((string) ($latestMetrics['open'] ?? 0)) ?></strong>
                        </span>
                        <span>
                            <small>Investigating</small>
                            <strong><?= e((string) ($latestMetrics['investigating'] ?? 0)) ?></strong>
                        </span>
                        <span>
                            <small>Overdue</small>
                            <strong><?= e((string) ($latestMetrics['overdue'] ?? 0)) ?></strong>
                        </span>
                        <span>
                            <small>Critical</small>
                            <strong><?= e((string) ($latestMetrics['critical'] ?? 0)) ?></strong>
                        </span>
                    </div>
                </div>

                <div class="security-center__privacy-note">
                    <?= app_icon('shield') ?>
                    <span>
                        Automatic execution is exposed through the installed CLI runner.
                        The installer does not silently modify system cron.
                    </span>
                </div>
            </div>
        </div>

        <div class="security-center__phase6-workload" id="security-phase6-workload">
            <div class="security-center__card-head">
                <div>
                    <h3>Incident ownership workload</h3>
                    <p>Open investigation load by active administrator, including unassigned incidents.</p>
                </div>
            </div>

            <div class="security-center__phase6-workload-table">
                <div class="is-head">
                    <span>Owner</span>
                    <span>Active</span>
                    <span>Investigating</span>
                    <span>Overdue</span>
                    <span>Critical</span>
                </div>

                <?php foreach ($incidentWorkloadVisible as $workload): ?>
                    <div>
                        <span>
                            <strong><?= e((string) ($workload['name'] ?? 'Administrator')) ?></strong>
                            <?php if (!empty($workload['email'])): ?>
                                <small><?= e((string) $workload['email']) ?></small>
                            <?php endif; ?>
                        </span>
                        <span><?= e((string) ($workload['active'] ?? 0)) ?></span>
                        <span><?= e((string) ($workload['investigating'] ?? 0)) ?></span>
                        <span class="<?= (int) ($workload['overdue'] ?? 0) > 0 ? 'is-overdue' : '' ?>">
                            <?= e((string) ($workload['overdue'] ?? 0)) ?>
                        </span>
                        <span><?= e((string) ($workload['critical'] ?? 0)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="security-center__pager-row">
                <span class="security-center__pager-meta">
                    10 per page · <?= count($incidentWorkload) ?> owners
                </span>

                <?php if ($workloadPages > 1): ?>
                    <nav class="security-center__pagination" aria-label="Workload pages">
                        <?php if ($workloadPage > 1): ?>
                            <a href="<?= e($securityPagerUrl('workload_page', $workloadPage - 1, 'security-phase6-workload')) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $workloadFrom = max(1, $workloadPage - 2);
                        $workloadTo = min($workloadPages, $workloadPage + 2);
                        ?>
                        <?php for ($i = $workloadFrom; $i <= $workloadTo; $i++): ?>
                            <a
                                href="<?= e($securityPagerUrl('workload_page', $i, 'security-phase6-workload')) ?>"
                                class="<?= $i === $workloadPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($workloadPage < $workloadPages): ?>
                            <a href="<?= e($securityPagerUrl('workload_page', $workloadPage + 1, 'security-phase6-workload')) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        method="get"
                        action="<?= e(url('/dashboard/audit')) ?>"
                        class="security-center__goto"
                    >
                        <?php foreach ($securityAuxQuery as $key => $value): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="workload_page"
                                min="1"
                                max="<?= $workloadPages ?>"
                                value="<?= $workloadPage ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="security-center__card security-center__incident-ops">
        <div class="security-center__card-head">
            <div>
                <h2>Incident operations</h2>
                <p>Prioritize investigations by workflow, severity, assignee, age, and SLA state.</p>
            </div>
            <span class="security-center__phase-badge">PHASE 5</span>
        </div>

        <div class="security-center__incident-metrics">
            <?php foreach ([
                'open' => 'Open',
                'acknowledged' => 'Acknowledged',
                'investigating' => 'Investigating',
                'overdue' => 'Overdue',
                'critical' => 'Critical',
                'resolved' => 'Resolved',
            ] as $metricKey => $metricLabel): ?>
                <a
                    href="<?= e(url('/dashboard/audit?' . http_build_query(
                        $metricKey === 'overdue'
                            ? ['incident_sla' => 'overdue']
                            : ($metricKey === 'critical'
                                ? ['incident_severity' => 'critical']
                                : ['incident_status' => $metricKey])
                    ))) ?>"
                >
                    <small><?= e($metricLabel) ?></small>
                    <strong><?= e((string) ($incidentMetrics[$metricKey] ?? 0)) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>

        <form
            method="get"
            action="<?= e(url('/dashboard/audit')) ?>"
            class="security-center__incident-filters"
        >
            <label>
                <span>Search incidents</span>
                <input
                    type="search"
                    name="incident_q"
                    maxlength="100"
                    value="<?= e((string) ($incidentFilters['incident_q'] ?? '')) ?>"
                    placeholder="ID, event, actor or assignee"
                >
            </label>

            <label>
                <span>Status</span>
                <select name="incident_status">
                    <option value="">All statuses</option>
                    <?php foreach (['open', 'acknowledged', 'investigating', 'resolved'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($incidentFilters['incident_status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Severity</span>
                <select name="incident_severity">
                    <option value="">All severities</option>
                    <?php foreach (['low', 'medium', 'high', 'critical'] as $severity): ?>
                        <option value="<?= e($severity) ?>" <?= ($incidentFilters['incident_severity'] ?? '') === $severity ? 'selected' : '' ?>>
                            <?= e(ucfirst($severity)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Assignee</span>
                <select name="incident_assignee">
                    <option value="">All assignees</option>
                    <option value="unassigned" <?= ($incidentFilters['incident_assignee'] ?? '') === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($incidentAdministrators as $administrator): ?>
                        <?php $administratorId = (int) ($administrator['id'] ?? 0); ?>
                        <option
                            value="<?= $administratorId ?>"
                            <?= (string) ($incidentFilters['incident_assignee'] ?? '') === (string) $administratorId ? 'selected' : '' ?>
                        ><?= e((string) ($administrator['name'] ?? 'Administrator')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>SLA</span>
                <select name="incident_sla">
                    <option value="">All SLA states</option>
                    <option value="overdue" <?= ($incidentFilters['incident_sla'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="on_track" <?= ($incidentFilters['incident_sla'] ?? '') === 'on_track' ? 'selected' : '' ?>>On track / resolved</option>
                </select>
            </label>

            <button class="btn btn-primary" type="submit">
                <?= app_icon('search') ?> Filter incidents
            </button>
        </form>

        <div class="security-center__incident-toolbar">
            <div>
                <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/audit?incident_status=resolved')) ?>">
                    Resolved archive
                </a>
                <?php if ($incidentQuery !== []): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/audit')) ?>">Clear incident filters</a>
                <?php endif; ?>
            </div>

            <div>
                <a
                    class="btn btn-outline-secondary"
                    href="<?= e(url('/dashboard/audit/incidents/export' . ($incidentExportQuery !== '' ? '?' . $incidentExportQuery : ''))) ?>"
                >
                    <?= app_icon('download') ?> Export incidents
                </a>
                <form
                    id="security-bulk-ack"
                    method="post"
                    action="<?= e(url('/dashboard/audit/incidents/acknowledge')) ?>"
                >
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary" type="submit">
                        Acknowledge selected
                    </button>
                </form>
            </div>
        </div>

        <div class="security-center__sla-policy">
            <strong>SLA targets</strong>
            <span>Critical <?= e((string) ($incidentSlaPolicy['critical'] ?? 1)) ?>h</span>
            <span>High <?= e((string) ($incidentSlaPolicy['high'] ?? 4)) ?>h</span>
            <span>Medium <?= e((string) ($incidentSlaPolicy['medium'] ?? 24)) ?>h</span>
            <span>Low <?= e((string) ($incidentSlaPolicy['low'] ?? 72)) ?>h</span>
        </div>
    </section>

    <div class="security-center__phase4-grid security-center__phase5-grid">
        <section class="security-center__card" id="security-incident-workbench">
            <div class="security-center__card-head">
                <div>
                    <h2>Incident workbench</h2>
                    <p>
                        <?= e((string) ($incidentPage['total'] ?? 0)) ?> matching incidents.
                        Overdue and active investigations are prioritized automatically.
                    </p>
                </div>
            </div>

            <div class="security-center__incident-list">
                <?php if ($incidentWorkbench === []): ?>
                    <p class="security-center__empty">No incidents match the current filters.</p>
                <?php else: ?>
                    <?php foreach ($incidentWorkbench as $incident): ?>
                        <?php
                        $incidentState = is_array($incident['state'] ?? null) ? $incident['state'] : [];
                        $timeline = is_array($incident['timeline'] ?? null) ? $incident['timeline'] : [];
                        $incidentId = (int) ($incident['id'] ?? 0);
                        $incidentStatus = (string) ($incidentState['status'] ?? 'open');
                        $incidentSeverity = (string) ($incidentState['severity'] ?? 'medium');
                        $incidentSlaState = (string) ($incident['sla_state'] ?? 'unknown');
                        ?>
                        <article
                            class="security-center__incident <?= $incidentSlaState === 'overdue' ? 'is-overdue' : '' ?>"
                            id="security-incident-<?= $incidentId ?>"
                        >
                            <div class="security-center__incident-head">
                                <div class="security-center__incident-select">
                                    <?php if (!in_array($incidentStatus, ['acknowledged', 'resolved'], true)): ?>
                                        <input
                                            type="checkbox"
                                            name="incident_ids[]"
                                            value="<?= $incidentId ?>"
                                            form="security-bulk-ack"
                                            aria-label="Select incident #<?= $incidentId ?>"
                                        >
                                    <?php endif; ?>
                                    <div>
                                        <span class="security-center__incident-id">Incident #<?= $incidentId ?></span>
                                        <h3><?= e((string) ($incident['event_type'] ?? 'Security event')) ?></h3>
                                        <p>
                                            <?= e((string) ($incident['created_at'] ?? '')) ?>
                                            · <?= e((string) ($incident['actor'] ?? 'System / Anonymous')) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="security-center__incident-badges">
                                    <span class="security-center__pill is-<?= e($incidentSeverity) ?>"><?= e(ucfirst($incidentSeverity)) ?></span>
                                    <span class="security-center__pill is-<?= e($incidentStatus) ?>"><?= e(ucfirst($incidentStatus)) ?></span>
                                    <span class="security-center__pill is-sla-<?= e($incidentSlaState) ?>">
                                        <?= $incidentSlaState === 'resolved_late' ? 'Resolved late' : e(ucwords(str_replace('_', ' ', $incidentSlaState))) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="security-center__sla-strip">
                                <span><small>Age</small><strong><?= e((string) ($incident['age_label'] ?? 'Unknown')) ?></strong></span>
                                <span><small>SLA</small><strong><?= e((string) ($incident['sla_hours'] ?? 24)) ?>h</strong></span>
                                <span><small>Due</small><strong><?= e((string) (($incident['due_at'] ?? '') ?: '—')) ?></strong></span>
                                <span><small>Assigned</small><strong><?= e((string) (($incidentState['assignee_name'] ?? '') ?: 'Unassigned')) ?></strong></span>
                            </div>

                            <?php if ($incidentSlaState === 'overdue'): ?>
                                <div class="security-center__overdue-warning">
                                    <?= app_icon('alert') ?>
                                    <span>This incident is beyond its <?= e(ucfirst($incidentSeverity)) ?> SLA target.</span>
                                </div>
                            <?php endif; ?>

                            <form
                                method="post"
                                action="<?= e(url('/dashboard/audit/incident/' . $incidentId)) ?>"
                                class="security-center__incident-form"
                            >
                                <?= csrf_field() ?>
                                <label>
                                    <span>Status</span>
                                    <select name="status">
                                        <?php foreach (['open', 'acknowledged', 'investigating', 'resolved'] as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $incidentStatus === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Severity</span>
                                    <select name="severity">
                                        <?php foreach (['low', 'medium', 'high', 'critical'] as $severity): ?>
                                            <option value="<?= e($severity) ?>" <?= $incidentSeverity === $severity ? 'selected' : '' ?>><?= e(ucfirst($severity)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Assign administrator</span>
                                    <select name="assignee_id">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($incidentAdministrators as $administrator): ?>
                                            <?php $administratorId = (int) ($administrator['id'] ?? 0); ?>
                                            <option value="<?= $administratorId ?>" <?= (int) ($incidentState['assignee_id'] ?? 0) === $administratorId ? 'selected' : '' ?>>
                                                <?= e((string) ($administrator['name'] ?? 'Administrator')) ?> · <?= e((string) ($administrator['email'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="security-center__incident-note">
                                    <span>Incident note</span>
                                    <textarea name="note" maxlength="500" rows="3" placeholder="Add investigation context, response notes, or resolution details"></textarea>
                                </label>
                                <button class="btn btn-primary" type="submit"><?= app_icon('shield') ?> Save incident update</button>
                            </form>

                            <div class="security-center__incident-meta">
                                <span><small>Last workflow update</small><strong><?= e((string) (($incidentState['updated_at'] ?? '') ?: 'Not updated yet')) ?></strong></span>
                                <span><small>Resolved</small><strong><?= e((string) (($incidentState['resolved_at'] ?? '') ?: '—')) ?></strong></span>
                                <span><small>Source</small><strong><?= e((string) ($incident['subject'] ?? '—')) ?></strong></span>
                            </div>

                            <details class="security-center__timeline">
                                <summary>Incident timeline · <?= count($timeline) ?> entries</summary>
                                <div class="security-center__timeline-list">
                                    <?php if ($timeline === []): ?>
                                        <p>No workflow actions recorded yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($timeline as $entry): ?>
                                            <?php $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : []; ?>
                                            <div class="security-center__timeline-item">
                                                <i></i>
                                                <div>
                                                    <strong><?= e((string) ($entry['event_type'] ?? 'Incident update')) ?></strong>
                                                    <small><?= e((string) ($entry['created_at'] ?? '')) ?> · <?= e((string) ($entry['actor'] ?? 'System')) ?></small>
                                                    <?php if (!empty($metadata['note'])): ?>
                                                        <p><?= e((string) $metadata['note']) ?></p>
                                                    <?php elseif (!empty($metadata['from']) || !empty($metadata['to'])): ?>
                                                        <p><?= e((string) ($metadata['from'] ?? '—')) ?> → <?= e((string) ($metadata['to'] ?? '—')) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="security-center__pager-row">
                <span class="security-center__pager-meta">
                    10 per page · <?= e((string) ($incidentPage['total'] ?? 0)) ?> incidents
                </span>

                <?php if ((int) ($incidentPage['pages'] ?? 1) > 1): ?>
                    <?php
                    $currentIncidentPage = (int) ($incidentPage['page'] ?? 1);
                    $incidentPages = (int) ($incidentPage['pages'] ?? 1);
                    $incidentFrom = max(1, $currentIncidentPage - 2);
                    $incidentTo = min($incidentPages, $currentIncidentPage + 2);
                    ?>
                    <nav class="security-center__pagination" aria-label="Incident pages">
                        <?php if ($currentIncidentPage > 1): ?>
                            <?php $incidentPreviousQuery = http_build_query($incidentPageQuery + ['incident_page' => $currentIncidentPage - 1]); ?>
                            <a href="<?= e(url('/dashboard/audit?' . $incidentPreviousQuery) . '#security-incident-workbench') ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = $incidentFrom; $i <= $incidentTo; $i++): ?>
                            <?php $incidentPagingQuery = http_build_query($incidentPageQuery + ['incident_page' => $i]); ?>
                            <a
                                href="<?= e(url('/dashboard/audit?' . $incidentPagingQuery) . '#security-incident-workbench') ?>"
                                class="<?= $i === $currentIncidentPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($currentIncidentPage < $incidentPages): ?>
                            <?php $incidentNextQuery = http_build_query($incidentPageQuery + ['incident_page' => $currentIncidentPage + 1]); ?>
                            <a href="<?= e(url('/dashboard/audit?' . $incidentNextQuery) . '#security-incident-workbench') ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form
                        method="get"
                        action="<?= e(url('/dashboard/audit')) ?>"
                        class="security-center__goto"
                    >
                        <?php foreach ($incidentPageQuery as $key => $value): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="incident_page"
                                min="1"
                                max="<?= $incidentPages ?>"
                                value="<?= $currentIncidentPage ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <aside class="security-center__card" id="security-notification-history">
            <div class="security-center__card-head">
                <div>
                    <h2>Security notification history</h2>
                    <p>Recent threshold alerts and incident-assignment notifications.</p>
                </div>
            </div>
            <div class="security-center__notification-history">
                <?php if ($securityNotificationHistory === []): ?>
                    <p class="security-center__empty">No security notifications have been delivered yet.</p>
                <?php else: ?>
                    <?php foreach ($securityNotificationHistoryVisible as $notification): ?>
                        <article>
                            <div>
                                <strong><?= e((string) ($notification['title'] ?? 'Security notification')) ?></strong>
                                <small><?= e((string) ($notification['created_at'] ?? '')) ?> · <?= e((string) ($notification['user_name'] ?? 'Administrator')) ?></small>
                            </div>
                            <span class="security-center__pill <?= empty($notification['read_at']) ? 'is-open' : 'is-resolved' ?>"><?= empty($notification['read_at']) ? 'Unread' : 'Read' ?></span>
                            <p><?= e((string) ($notification['message'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="security-center__pager-row">
                <span class="security-center__pager-meta">
                    10 per page · <?= count($securityNotificationHistory) ?> notifications
                </span>

                <?php if ($notificationPages > 1): ?>
                    <nav class="security-center__pagination" aria-label="Notification pages">
                        <?php if ($notificationPage > 1): ?>
                            <a href="<?= e($securityPagerUrl('notification_page', $notificationPage - 1, 'security-notification-history')) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $notificationFrom = max(1, $notificationPage - 2);
                        $notificationTo = min($notificationPages, $notificationPage + 2);
                        ?>
                        <?php for ($i = $notificationFrom; $i <= $notificationTo; $i++): ?>
                            <a
                                href="<?= e($securityPagerUrl('notification_page', $i, 'security-notification-history')) ?>"
                                class="<?= $i === $notificationPage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($notificationPage < $notificationPages): ?>
                            <a href="<?= e($securityPagerUrl('notification_page', $notificationPage + 1, 'security-notification-history')) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form method="get" action="<?= e(url('/dashboard/audit')) ?>" class="security-center__goto">
                        <?php foreach ($securityAuxQuery as $key => $value): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="notification_page"
                                min="1"
                                max="<?= $notificationPages ?>"
                                value="<?= $notificationPage ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="security-center__privacy-note">
                <?= app_icon('shield') ?>
                <span>Phase 5 triage state remains lightweight configuration; workflow history remains in the immutable security audit stream.</span>
            </div>
        </aside>
    </div>

    <section class="security-center__card">
        <div class="security-center__card-head">
            <div>
                <h2>Security event filters</h2>
                <p>Filter the immutable audit trail by account, event family, exact event type, or date range.</p>
            </div>
            <?php if ($query !== []): ?>
                <a class="security-center__clear" href="<?= e(url('/dashboard/audit')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <form method="get" action="<?= e(url('/dashboard/audit')) ?>" class="security-center__filters">
            <label>
                <span>Search</span>
                <input
                    type="search"
                    name="q"
                    maxlength="100"
                    value="<?= e((string) ($filters['q'] ?? '')) ?>"
                    placeholder="Event, user name or email"
                >
            </label>

            <label>
                <span>Family</span>
                <select name="family">
                    <option value="">All families</option>
                    <?php foreach (['auth' => 'Authentication', 'account' => 'Account', 'admin' => 'Admin'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['family'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Event type</span>
                <select name="event_type">
                    <option value="">All event types</option>
                    <?php foreach ($eventTypes as $eventType): ?>
                        <option value="<?= e((string) $eventType) ?>" <?= ($filters['event_type'] ?? '') === $eventType ? 'selected' : '' ?>>
                            <?= e((string) $eventType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>From</span>
                <input type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
            </label>

            <label>
                <span>To</span>
                <input type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
            </label>

            <button class="btn btn-primary" type="submit">
                <?= app_icon('search') ?> Apply filters
            </button>
        </form>
    </section>

    <?php if (!empty($securityError)): ?>
        <div class="security-center__notice is-error">
            <?= app_icon('alert') ?>
            <span><?= e((string) $securityError) ?></span>
        </div>
    <?php endif; ?>

    <div class="security-center__grid">
        <section class="security-center__card" id="security-events-table">
            <div class="security-center__card-head">
                <div>
                    <h2>Security events</h2>
                    <p>
                        <?= e((string) ($events['total'] ?? 0)) ?> matching events.
                        Client-origin hashes remain hidden.
                    </p>
                </div>
            </div>

            <div class="security-center__table-wrap">
                <table class="security-center__table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Event</th>
                            <th>Actor</th>
                            <th>Subject</th>
                            <th>Client</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="5" class="security-center__empty">No security events match these filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $event): ?>
                            <?php $state = (string) ($event['security_state'] ?? 'information'); ?>
                            <tr>
                                <td>
                                    <time><?= e((string) ($event['created_at'] ?? '')) ?></time>
                                </td>
                                <td>
                                    <span class="security-center__event">
                                        <i class="is-<?= e($state) ?>"></i>
                                        <span>
                                            <strong><?= e((string) ($event['event_type'] ?? '')) ?></strong>
                                            <small><?= e(ucfirst((string) ($event['event_family'] ?? 'event'))) ?></small>
                                        </span>
                                    </span>
                                </td>
                                <td><?= e((string) ($event['actor'] ?? '—')) ?></td>
                                <td><?= e((string) ($event['subject'] ?? '—')) ?></td>
                                <td><span class="security-center__privacy"><?= e((string) ($event['client_state'] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="security-center__pager-row">
                <span class="security-center__pager-meta">
                    10 per page · <?= e((string) ($events['total'] ?? 0)) ?> events
                </span>

                <?php if ((int) ($events['pages'] ?? 1) > 1): ?>
                    <?php
                    $page = (int) ($events['page'] ?? 1);
                    $pages = (int) ($events['pages'] ?? 1);
                    $from = max(1, $page - 2);
                    $to = min($pages, $page + 2);
                    ?>
                    <nav class="security-center__pagination" aria-label="Security event pages">
                        <?php if ($page > 1): ?>
                            <?php $previous = http_build_query($baseQuery + ['page' => $page - 1]); ?>
                            <a href="<?= e(url('/dashboard/audit?' . $previous) . '#security-events-table') ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = $from; $i <= $to; $i++): ?>
                            <?php $pageQuery = http_build_query($baseQuery + ['page' => $i]); ?>
                            <a
                                href="<?= e(url('/dashboard/audit?' . $pageQuery) . '#security-events-table') ?>"
                                class="<?= $i === $page ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <?php $next = http_build_query($baseQuery + ['page' => $page + 1]); ?>
                            <a href="<?= e(url('/dashboard/audit?' . $next) . '#security-events-table') ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form method="get" action="<?= e(url('/dashboard/audit')) ?>" class="security-center__goto">
                        <?php foreach ($baseQuery as $key => $value): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="page"
                                min="1"
                                max="<?= $pages ?>"
                                value="<?= $page ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>

        </section>

        <aside class="security-center__card" id="security-throttle-status">
            <div class="security-center__card-head">
                <div>
                    <h2>Login throttle status</h2>
                    <p>
                        Policy: <?= e((string) ($policy['max_attempts'] ?? 5)) ?> attempts /
                        <?= e((string) ($policy['decay_minutes'] ?? 15)) ?> minutes.
                    </p>
                </div>
            </div>

            <div class="security-center__throttle-list">
                <?php if ($throttleRows === []): ?>
                    <p class="security-center__empty">No login throttle records are currently stored.</p>
                <?php else: ?>
                    <?php foreach ($throttleRowsVisible as $throttle): ?>
                        <?php $throttleState = (string) ($throttle['throttle_state'] ?? 'expired'); ?>
                        <article>
                            <div class="security-center__throttle-head">
                                <code><?= e((string) ($throttle['fingerprint'] ?? '')) ?></code>
                                <span class="security-center__pill is-<?= e($throttleState) ?>"><?= e(ucfirst($throttleState)) ?></span>
                            </div>
                            <dl>
                                <div><dt>Attempts</dt><dd><?= e((string) ($throttle['attempts'] ?? 0)) ?></dd></div>
                                <div><dt>First attempt</dt><dd><?= e((string) ($throttle['first_attempt_at'] ?? '')) ?></dd></div>
                                <div><dt>Last attempt</dt><dd><?= e((string) ($throttle['last_attempt_at'] ?? '')) ?></dd></div>
                                <div><dt>Locked until</dt><dd><?= e((string) ($throttle['locked_until'] ?? '—')) ?></dd></div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="security-center__pager-row">
                <span class="security-center__pager-meta">
                    10 per page · <?= count($throttleRows) ?> throttle records
                </span>

                <?php if ($throttlePages > 1): ?>
                    <nav class="security-center__pagination" aria-label="Throttle pages">
                        <?php if ($throttlePage > 1): ?>
                            <a href="<?= e($securityPagerUrl('throttle_page', $throttlePage - 1, 'security-throttle-status')) ?>">Previous</a>
                        <?php endif; ?>

                        <?php
                        $throttleFrom = max(1, $throttlePage - 2);
                        $throttleTo = min($throttlePages, $throttlePage + 2);
                        ?>
                        <?php for ($i = $throttleFrom; $i <= $throttleTo; $i++): ?>
                            <a
                                href="<?= e($securityPagerUrl('throttle_page', $i, 'security-throttle-status')) ?>"
                                class="<?= $i === $throttlePage ? 'is-active' : '' ?>"
                            ><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($throttlePage < $throttlePages): ?>
                            <a href="<?= e($securityPagerUrl('throttle_page', $throttlePage + 1, 'security-throttle-status')) ?>">Next</a>
                        <?php endif; ?>
                    </nav>

                    <form method="get" action="<?= e(url('/dashboard/audit')) ?>" class="security-center__goto">
                        <?php foreach ($securityAuxQuery as $key => $value): ?>
                            <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                        <?php endforeach; ?>
                        <label>
                            <span>Go To</span>
                            <input
                                type="number"
                                name="throttle_page"
                                min="1"
                                max="<?= $throttlePages ?>"
                                value="<?= $throttlePage ?>"
                            >
                        </label>
                        <button type="submit">Go</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="security-center__privacy-note">
                <?= app_icon('shield') ?>
                <span>
                    Throttle keys are displayed only as shortened fingerprints.
                    Raw email/IP combinations, IP hashes, user-agent hashes, and audit metadata are not exposed here or in CSV exports.
                </span>
            </div>
        </aside>
    </div>
</section>
