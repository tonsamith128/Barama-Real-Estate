<?php
/* BARAMA_A46_REAL_DATA_DASHBOARD */
declare(strict_types=1);

if (($workspaceType ?? '') !== 'admin' || ($activeSection ?? '') !== 'overview') {
    return;
}

$a46Esc = static fn (mixed $v): string => e((string) $v);
$a46AllowedModules = [
    '/dashboard/approvals', '/dashboard/companies', '/dashboard/properties',
    '/dashboard/projects', '/dashboard/listings', '/dashboard/inquiries',
    '/dashboard/leads', '/dashboard/viewings', '/dashboard/users', '/dashboard/audit',
];
$a46Metrics = [];
foreach ((array) ($stats ?? []) as $stat) {
    if (!is_array($stat)) {
        continue;
    }
    $href = trim((string) ($stat['href'] ?? ''));
    $route = explode('?', $href, 2)[0];
    if ($href === '' || !in_array($route, $a46AllowedModules, true)
        || !is_numeric($stat['value'] ?? null)) {
        continue;
    }
    $a46Metrics[] = [
        'label' => (string) ($stat['label'] ?? ''),
        'value' => (int) $stat['value'],
        'icon' => (string) ($stat['icon'] ?? 'chart'),
        'href' => $href,
    ];
}

/* BARAMA_A46R3_COMPACT_METRICS_AND_ICON_ACTIONS */
// Display-only abbreviation: all DB values and link targets remain exact.
$a46CompactNumber = static function (int $value): string {
    $absolute = abs($value);
    foreach (['T' => 1000000000000, 'B' => 1000000000, 'M' => 1000000, 'K' => 1000] as $suffix => $base) {
        if ($absolute >= $base) {
            $scaled = round($absolute / $base, 1);
            return ($value < 0 ? '-' : '')
                . rtrim(rtrim(number_format($scaled, 1, '.', ''), '0'), '.')
                . $suffix;
        }
    }
    return number_format($value);
};
// SVGs are fixed presentation assets, not sourced from database records.
$a46CtaIcons = [
    'listings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    'inquiries' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 11.4a7.5 7.5 0 0 1-7.5 7.5 8.3 8.3 0 0 1-3.4-.7L4 20l1.7-5a7.7 7.7 0 0 1-.7-3.6 7.5 7.5 0 1 1 15 0Z"/><path d="M9 11.5h7"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.8a5.5 5.5 0 0 1 11 0V20M16 5a3 3 0 0 1 0 6M17 14a4.5 4.5 0 0 1 3.5 4.4V20"/></svg>',
    'viewings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M9 15l2 2 4-4"/></svg>',
];
$a46CtaLink = static function (string $icon, string $target, string $label) use ($a46CtaIcons, $a46Esc): string {
    return '<a class="a46-chart-action" href="' . $a46Esc(url($target))
        . '" aria-label="' . $a46Esc($label) . '" title="' . $a46Esc($label) . '">'
        . ($a46CtaIcons[$icon] ?? '') . '</a>';
};

// These are SELECT-only queries against existing Barama entities. Fail closed per widget.
$a46Read = static function (string $sql, array $parameters = []): ?array {
    try {
        $stmt = \Barama\Core\Database::connection()->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $exception) {
        error_log('[Barama A46 dashboard] SELECT failed: ' . $exception::class);
        return null;
    }
};

$a46Status = $a46Read(
    'SELECT status, COUNT(*) AS total FROM listings WHERE deleted_at IS NULL GROUP BY status ORDER BY total DESC'
);
$a46ViewStatus = $a46Read(
    'SELECT status, COUNT(*) AS total FROM viewing_requests GROUP BY status ORDER BY total DESC'
);
$a46InquiryDaily = $a46Read(
    'SELECT DATE(created_at) AS day, COUNT(*) AS total FROM inquiries
     WHERE created_at >= CURRENT_DATE - INTERVAL 13 DAY GROUP BY DATE(created_at) ORDER BY day ASC'
);
$a46UserDaily = $a46Read(
    'SELECT DATE(created_at) AS day, COUNT(*) AS total FROM users
     WHERE created_at >= CURRENT_DATE - INTERVAL 13 DAY GROUP BY DATE(created_at) ORDER BY day ASC'
);
$a46Dates = [];
for ($i = 13; $i >= 0; $i--) {
    $date = new \DateTimeImmutable('-' . $i . ' days');
    $a46Dates[] = $date->format('Y-m-d');
}
$a46ToSeries = static function (?array $data) use ($a46Dates): array {
    if ($data === null) {
        return [];
    }
    $map = [];
    foreach ($data as $row) {
        $day = (string) ($row['day'] ?? '');
        if (in_array($day, $a46Dates, true)) {
            $map[$day] = (int) ($row['total'] ?? 0);
        }
    }
    return array_map(static fn ($day): array => [
        'day' => $day, 'count' => $map[$day] ?? 0,
    ], $a46Dates);
};
$a46Inquiries = $a46ToSeries($a46InquiryDaily);
$a46Registrations = $a46ToSeries($a46UserDaily);

/* BARAMA_A46R2_READABLE_ACTIVITY_VERIFIED_LINKS
   Keep canonical event_type intact for logging, audit and filters. Display labels are presentation only.
   Unknown event types are humanized without inventing outcomes.
*/
$a46SecurityTitles = [
    'auth.login_success' => 'Successful sign-in',
    'auth.login_failed' => 'Unsuccessful sign-in',
    'auth.login_locked' => 'Sign-in temporarily locked',
    'auth.logout' => 'Signed out',
    'auth.registered' => 'Account registered',
    'auth.email_verification_sent' => 'Verification email sent',
    'auth.email_verified' => 'Email address verified',
    'auth.password_reset_requested' => 'Password reset requested',
    'auth.password_reset_sent' => 'Password reset email sent',
    'auth.password_reset_completed' => 'Password reset completed',
    'auth.password_reset_success' => 'Password successfully reset',
    'admin.mail_settings_test_sent' => 'Mail settings test sent',
];
$a46SecurityLabel = static function (string $original) use ($a46SecurityTitles): string {
    $key = strtolower(trim($original));
    if (isset($a46SecurityTitles[$key])) {
        return $a46SecurityTitles[$key];
    }
    $normalized = preg_replace('/[^a-z0-9._-]+/i', ' ', $original) ?? '';
    $parts = preg_split('/[.]+/', strtolower(trim($normalized))) ?: [];
    if (count($parts) > 1 && in_array($parts[0], ['auth', 'admin', 'security', 'user', 'account'], true)) {
        array_shift($parts);
    }
    $description = trim(implode(' ', $parts));
    $description = preg_replace('/[_-]+/', ' ', $description) ?? '';
    $description = trim(preg_replace('/\s+/', ' ', $description) ?? '');
    return $description !== '' ? ucfirst($description) : 'Security event recorded';
};
// An Open action must reference a real record and an existing admin detail route.
// When its parent record was removed, link to the existing module instead and label it Browse.
$a46RecordSources = [
    'audit' => ['sql' => 'SELECT e.id AS record_id FROM security_audit_events e WHERE e.id = :id LIMIT 1', 'list' => '/dashboard/audit'],
    'company' => ['sql' => 'SELECT c.id AS record_id FROM companies c WHERE c.id = :id AND c.deleted_at IS NULL LIMIT 1', 'list' => '/dashboard/companies'],
    'project' => ['sql' => 'SELECT p.id AS record_id FROM projects p INNER JOIN companies c ON c.id = p.company_id WHERE p.id = :id AND p.deleted_at IS NULL AND c.deleted_at IS NULL LIMIT 1', 'list' => '/dashboard/projects'],
    'property' => ['sql' => 'SELECT p.id AS record_id FROM properties p INNER JOIN companies c ON c.id = p.company_id WHERE p.id = :id AND p.deleted_at IS NULL AND c.deleted_at IS NULL LIMIT 1', 'list' => '/dashboard/properties'],
    'listing' => ['sql' => 'SELECT l.id AS record_id FROM listings l INNER JOIN properties p ON p.id = l.property_id WHERE l.id = :id AND l.deleted_at IS NULL AND p.deleted_at IS NULL LIMIT 1', 'list' => '/dashboard/listings'],
    'inquiry' => ['sql' => 'SELECT i.id AS record_id FROM inquiries i WHERE i.id = :id LIMIT 1', 'list' => '/dashboard/inquiries'],
    'lead' => ['sql' => 'SELECT l.id AS record_id FROM leads l WHERE l.id = :id AND l.deleted_at IS NULL LIMIT 1', 'list' => '/dashboard/leads'],
    'viewing' => ['sql' => 'SELECT v.id AS record_id FROM viewing_requests v WHERE v.id = :id LIMIT 1', 'list' => '/dashboard/viewings'],
    'user' => ['sql' => 'SELECT u.id AS record_id FROM users u WHERE u.id = :id LIMIT 1', 'list' => '/dashboard/users'],
];
$a46ResolveActivityLink = static function (string $resource, int $id) use ($a46Read, $a46RecordSources): array {
    $source = $a46RecordSources[$resource] ?? null;
    if ($source === null || $id < 1) {
        return ['url' => '/dashboard/audit', 'action' => 'View log'];
    }
    $rows = $a46Read($source['sql'], ['id' => $id]);
    if ($rows !== null && isset($rows[0]['record_id']) && (int) $rows[0]['record_id'] === $id) {
        return ['url' => '/dashboard/manage/' . $resource . '/' . $id, 'action' => 'Open'];
    }
    return ['url' => $source['list'], 'action' => 'Browse'];
};

// BARAMA_A46R1_ACTIVITY_PAGINATION: server-side pagination across actual recorded events.
// All SQL below is read-only and all table/column names come from a hard-coded allowlist.
$a46FilterTypes = ['All', 'Security', 'Moderation', 'Inquiry', 'Lead', 'Viewing', 'Listing', 'User'];
$a46FilterRaw = $_GET['activity_type'] ?? 'All';
$a46Filter = is_string($a46FilterRaw) ? trim($a46FilterRaw) : 'All';
if (!in_array($a46Filter, $a46FilterTypes, true)) {
    $a46Filter = 'All';
}
$a46PerPage = 10;
$a46RequestedPageRaw = $_GET['activity_page'] ?? 1;
$a46RequestedPage = filter_var($a46RequestedPageRaw, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$a46RequestedPage = $a46RequestedPage === false ? 1 : $a46RequestedPage;

// Each event source has its own count, so the total is never derived from a
// truncated preview. No additional rows are created or modified by the dashboard.
$a46EventDefinitions = [
    'Security' => [
        'count' => 'SELECT COUNT(*) AS total FROM security_audit_events',
        'select' => 'SELECT "Security" AS event_kind, id, event_type AS detail, event_type AS status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 7 AS source_priority
                     FROM security_audit_events',
        'path' => '/dashboard/audit', 'title' => 'Audit event', 'target' => 'security',
    ],
    'Moderation' => [
        'count' => 'SELECT COUNT(*) AS total FROM entity_status_history',
        'select' => 'SELECT "Moderation" AS event_kind, id, "" AS detail, to_status AS status,
                     created_at, entity_type, entity_id, 6 AS source_priority
                     FROM entity_status_history',
        'path' => '/dashboard/approvals', 'title' => 'Status change', 'target' => 'history',
    ],
    'Inquiry' => [
        'count' => 'SELECT COUNT(*) AS total FROM inquiries',
        'select' => 'SELECT "Inquiry" AS event_kind, id, "" AS detail, status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 5 AS source_priority
                     FROM inquiries',
        'path' => '/dashboard/inquiries', 'title' => 'Inquiry received', 'target' => 'inquiry',
    ],
    'Lead' => [
        'count' => 'SELECT COUNT(*) AS total FROM leads WHERE deleted_at IS NULL',
        'select' => 'SELECT "Lead" AS event_kind, id, "" AS detail, stage AS status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 4 AS source_priority
                     FROM leads WHERE deleted_at IS NULL',
        'path' => '/dashboard/leads', 'title' => 'Lead created', 'target' => 'lead',
    ],
    'Viewing' => [
        'count' => 'SELECT COUNT(*) AS total FROM viewing_requests',
        'select' => 'SELECT "Viewing" AS event_kind, id, "" AS detail, status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 3 AS source_priority
                     FROM viewing_requests',
        'path' => '/dashboard/viewings', 'title' => 'Viewing requested', 'target' => 'viewing',
    ],
    'Listing' => [
        'count' => 'SELECT COUNT(*) AS total FROM listings WHERE deleted_at IS NULL',
        'select' => 'SELECT "Listing" AS event_kind, id, "" AS detail, status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 2 AS source_priority
                     FROM listings WHERE deleted_at IS NULL',
        'path' => '/dashboard/listings', 'title' => 'Listing created', 'target' => 'listing',
    ],
    'User' => [
        'count' => 'SELECT COUNT(*) AS total FROM users',
        'select' => 'SELECT "User" AS event_kind, id, "" AS detail, status,
                     created_at, NULL AS entity_type, NULL AS entity_id, 1 AS source_priority
                     FROM users',
        'path' => '/dashboard/users', 'title' => 'User registered', 'target' => 'user',
    ],
];
$a46SelectedSources = $a46Filter === 'All'
    ? $a46EventDefinitions
    : [$a46Filter => $a46EventDefinitions[$a46Filter]];
$a46AvailableSources = [];
$a46TotalActivities = 0;
$a46UnavailableSources = 0;
foreach ($a46SelectedSources as $kind => $definition) {
    $result = $a46Read($definition['count']);
    if ($result === null || !isset($result[0]['total'])) {
        ++$a46UnavailableSources;
        continue;
    }
    $a46AvailableSources[$kind] = $definition;
    $a46TotalActivities += max(0, (int) $result[0]['total']);
}
$a46TotalPages = max(1, (int) ceil($a46TotalActivities / $a46PerPage));
$a46ActivityPage = min($a46RequestedPage, $a46TotalPages);
$a46Offset = ($a46ActivityPage - 1) * $a46PerPage;
$a46Events = [];
$a46ActivityQueryFailed = false;
if ($a46AvailableSources !== [] && $a46TotalActivities > 0) {
    $a46Union = implode(' UNION ALL ', array_map(
        static fn (array $definition): string => $definition['select'],
        array_values($a46AvailableSources)
    ));
    $a46PageRows = $a46Read(
        'SELECT * FROM (' . $a46Union . ') a46_platform_activity
         ORDER BY created_at DESC, source_priority DESC, id DESC
         LIMIT ' . $a46PerPage . ' OFFSET ' . $a46Offset
    );
    if ($a46PageRows === null) {
        $a46ActivityQueryFailed = true;
    } else {
        foreach ($a46PageRows as $record) {
            $kind = (string) ($record['event_kind'] ?? '');
            $definition = $a46AvailableSources[$kind] ?? null;
            $id = (int) ($record['id'] ?? 0);
            $created = (string) ($record['created_at'] ?? '');
            if ($definition === null || $id < 1 || $created === '') {
                continue;
            }
            $status = (string) ($record['status'] ?? '—');
            $entity = (string) ($record['entity_type'] ?? '');
            $entityId = (int) ($record['entity_id'] ?? 0);
            $target = $definition['target'] === 'history' ? $entity : ($definition['target'] === 'security' ? 'audit' : $definition['target']);
            $targetId = $definition['target'] === 'history' ? $entityId : $id;
            $resolvedLink = $a46ResolveActivityLink($target, $targetId);
            $subject = $definition['target'] === 'history'
                ? (ucfirst($entity ?: 'Record') . ' #' . $entityId)
                : (($kind === 'Security' ? 'Security event' : ucfirst($kind)) . ' #' . $id);
            $rawType = $kind === 'Security' ? (string) ($record['detail'] ?? '') : '';
            $detail = match ($kind) {
                'Security' => $a46SecurityLabel($rawType),
                'Moderation' => 'Status changed for ' . $subject,
                'Inquiry' => 'Inquiry received',
                'Lead' => 'Lead created',
                'Viewing' => 'Viewing requested',
                'Listing' => 'Listing created',
                'User' => 'Account registered',
                default => 'Activity recorded',
            };
            $a46Events[] = [
                'type' => $kind,
                'detail' => $detail,
                'event_code' => $rawType,
                'subject' => $subject,
                'status' => $kind === 'Security' ? 'Recorded' : $status,
                'date' => $created,
                'url' => $resolvedLink['url'],
                'action' => $resolvedLink['action'],
                'time' => strtotime($created) ?: 0,
                'id' => $id,
            ];
        }
    }
}
$a46ActivityStart = $a46TotalActivities === 0 ? 0 : $a46Offset + 1;
$a46ActivityEnd = min($a46Offset + $a46PerPage, $a46TotalActivities);
$a46PageLink = static function (int $page) use ($a46Filter): string {
    return url('/dashboard') . '?' . http_build_query([
        'activity_type' => $a46Filter,
        'activity_page' => $page,
    ]) . '#a46-recent-activity';
};

$a46Approval = [];
foreach ((array) ($rows ?? []) as $record) {
    if (!is_array($record) || (int) ($record['id'] ?? 0) < 1) {
        continue;
    }
    $type = (string) ($record['entity_type'] ?? '');
    if (!in_array($type, ['company', 'project', 'property', 'listing'], true)) {
        continue;
    }
    $a46Approval[] = $record;
}
$a46Approval = array_slice($a46Approval, 0, 5);
$a46OpenInquiries = $a46Read(
    'SELECT id, status, created_at FROM inquiries WHERE status IN ("new","read","replied") ORDER BY created_at DESC, id DESC LIMIT 5'
) ?? [];
$a46Upcoming = $a46Read(
    'SELECT id, status, COALESCE(scheduled_for, CAST(preferred_date AS DATETIME)) AS appointment_at
     FROM viewing_requests WHERE status IN ("pending","confirmed","rescheduled")
       AND (scheduled_for >= NOW() OR (scheduled_for IS NULL AND preferred_date >= CURRENT_DATE))
     ORDER BY appointment_at ASC, id ASC LIMIT 5'
) ?? [];

$a46RenderBreakdown = static function (array $rows, string $name) use ($a46Esc): void {
    $total = array_sum(array_map(static fn (array $r): int => max(0, (int) ($r['total'] ?? 0)), $rows));
    if ($total === 0) {
        echo '<p class="a46-no-data">No records in this category yet.</p>';
        return;
    }
    foreach ($rows as $row) {
        $count = max(0, (int) ($row['total'] ?? 0));
        $label = ucwords(str_replace('_', ' ', (string) ($row['status'] ?? 'Other')));
        $width = round($count * 100 / $total, 2);
        echo '<div class="a46-breakdown-row"><div class="a46-breakdown-label"><span>' . $a46Esc($label)
            . '</span><strong>' . number_format($count) . '</strong></div>'
            . '<div class="a46-bar-track"><span style="width:' . $width . '%"></span></div></div>';
    }
};
$a46RenderDaily = static function (array $series) use ($a46Esc): void {
    $peak = max(1, ...array_column($series, 'count'));
    $count = array_sum(array_column($series, 'count'));
    echo '<div class="a46-chart-total">' . number_format($count) . ' in the last 14 days</div>';
    echo '<div class="a46-daybars" role="img" aria-label="Daily record counts for the last 14 days">';
    foreach ($series as $datum) {
        $height = round(max(0, (int) $datum['count']) / $peak * 100, 2);
        $day = $a46Esc(date('M j', strtotime($datum['day'])));
        echo '<div class="a46-daybar"><span style="height:' . $height . '%" title="'
            . $day . ': ' . (int) $datum['count'] . '"></span></div>';
    }
    echo '</div><div class="a46-chart-axis"><span>' . $a46Esc(date('M j', strtotime($series[0]['day'])))
        . '</span><span>' . $a46Esc(date('M j', strtotime($series[count($series)-1]['day']))) . '</span></div>';
};
?>
<div class="barama-a46" data-a46-live-dashboard>
    <header class="a46-heading">
        <div><span class="a46-eyebrow">ADMIN CONTROL CENTER</span><h1>Platform overview</h1></div>
        <nav class="a46-head-actions" aria-label="Dashboard actions">
            <a href="<?= e(url('/dashboard/approvals')) ?>">Review approvals</a>
            <a href="<?= e(url('/dashboard/audit')) ?>">Security log</a>
        </nav>
    </header>

    <section aria-label="Live marketplace statistics" class="a46-metrics">
        <?php foreach ($a46Metrics as $stat): ?>
            <a class="a46-metric" href="<?= $a46Esc(url($stat['href'])) ?>" aria-label="<?= $a46Esc($stat['label'] . ': ' . number_format($stat['value'])) ?>" title="<?= $a46Esc($stat['label'] . ': ' . number_format($stat['value'])) ?>">
                <span class="a46-metric-copy"><strong aria-hidden="true"><?= $a46Esc($a46CompactNumber($stat['value'])) ?></strong><span class="a46-metric-label"><?= $a46Esc($stat['label']) ?></span></span>
                <span class="a46-metric-icon" aria-hidden="true"><?= app_icon($stat['icon']) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="a46-charts" aria-label="Live platform data charts">
        <?php if ($a46Status !== null): ?>
        <article class="a46-chart"><div class="a46-chart-header"><h2>Listings by status</h2><?= $a46CtaLink('listings', '/dashboard/listings', 'View listings') ?></div>
            <?php $a46RenderBreakdown($a46Status, 'Listings'); ?>
        </article>
        <?php endif; ?>
        <?php if ($a46Inquiries !== []): ?>
        <article class="a46-chart"><div class="a46-chart-header"><h2>Inquiry activity</h2><?= $a46CtaLink('inquiries', '/dashboard/inquiries', 'View inquiries') ?></div>
            <?php $a46RenderDaily($a46Inquiries); ?>
        </article>
        <?php endif; ?>
        <?php if ($a46Registrations !== []): ?>
        <article class="a46-chart"><div class="a46-chart-header"><h2>User registrations</h2><?= $a46CtaLink('users', '/dashboard/users', 'View users') ?></div>
            <?php $a46RenderDaily($a46Registrations); ?>
        </article>
        <?php endif; ?>
        <?php if ($a46ViewStatus !== null): ?>
        <article class="a46-chart"><div class="a46-chart-header"><h2>Viewings by status</h2><?= $a46CtaLink('viewings', '/dashboard/viewings', 'View appointments') ?></div>
            <?php $a46RenderBreakdown($a46ViewStatus, 'Viewings'); ?>
        </article>
        <?php endif; ?>
    </section>

    <?php if ($a46Approval !== [] || $a46OpenInquiries !== [] || $a46Upcoming !== []): ?>
    <div class="a46-queues" aria-label="Actionable platform queues">
        <?php if ($a46Approval !== []): ?>
        <section class="a46-queue"><div class="a46-section-head"><h2>Pending approvals</h2><a href="<?= e(url('/dashboard/approvals')) ?>">Open queue</a></div>
            <?php foreach ($a46Approval as $record): ?>
                <a class="a46-queue-link" href="<?= e(url('/dashboard/manage/' . rawurlencode((string) $record['entity_type']) . '/' . (int) $record['id'])) ?>"><span><?= $a46Esc((string) ($record['title'] ?? 'Record #' . $record['id'])) ?><small><?= $a46Esc(ucfirst((string) ($record['entity_type'] ?? 'Record'))) ?></small></span><span>Review</span></a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php if ($a46OpenInquiries !== []): ?>
        <section class="a46-queue"><div class="a46-section-head"><h2>Open inquiries</h2><?= $a46CtaLink('inquiries', '/dashboard/inquiries', 'View inquiries') ?></div>
            <?php foreach ($a46OpenInquiries as $record): ?>
                <a class="a46-queue-link" href="<?= e(url('/dashboard/manage/inquiry/' . (int) $record['id'])) ?>"><span>Inquiry #<?= (int) $record['id'] ?><small><?= $a46Esc(ucfirst((string) $record['status'])) ?></small></span><span>Open</span></a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php if ($a46Upcoming !== []): ?>
        <section class="a46-queue"><div class="a46-section-head"><h2>Upcoming viewings</h2><a href="<?= e(url('/dashboard/viewings')) ?>">View all</a></div>
            <?php foreach ($a46Upcoming as $record): ?>
                <a class="a46-queue-link" href="<?= e(url('/dashboard/manage/viewing/' . (int) $record['id'])) ?>"><span>Viewing #<?= (int) $record['id'] ?><small><?= $a46Esc(date('M j, Y H:i', strtotime((string) $record['appointment_at']))) ?></small></span><span>Open</span></a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <section class="a46-activity" id="a46-recent-activity" aria-label="Recent real platform activity">
        <div class="a46-section-head a46-activity-header"><div><h2>Recent platform activity</h2><span>Actual recorded events · newest first</span></div>
            <form method="get" action="<?= e(url('/dashboard')) ?>#a46-recent-activity" class="a46-filter"><input type="hidden" name="activity_page" value="1"><label for="a46-activity-type">Event type</label><select name="activity_type" id="a46-activity-type">
                <?php foreach ($a46FilterTypes as $type): ?>
                    <option value="<?= $a46Esc($type) ?>" <?= $a46Filter === $type ? 'selected' : '' ?>><?= $a46Esc($type === 'All' ? 'All activities' : $type) ?></option>
                <?php endforeach; ?>
            </select><button type="submit">Filter</button></form>
        </div>
        <div class="a46-table-scroll"><table><thead><tr><th>Time</th><th>Type</th><th>Activity</th><th>Record</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php if ($a46ActivityQueryFailed || $a46UnavailableSources > 0 && $a46AvailableSources === []): ?>
            <tr><td colspan="6" class="a46-empty">Activity records are temporarily unavailable.</td></tr>
        <?php elseif ($a46Events === []): ?>
            <tr><td colspan="6" class="a46-empty">No recorded activity matches this filter.</td></tr>
        <?php else: ?>
            <?php foreach ($a46Events as $event): ?>
            <tr><td class="a46-time"><?= $a46Esc(date('M j, Y H:i', $event['time'])) ?></td><td><?= $a46Esc($event['type']) ?></td><td<?php if ($event['event_code'] !== ''): ?> title="Event code: <?= $a46Esc($event['event_code']) ?>"<?php endif; ?>><?= $a46Esc($event['detail']) ?></td><td><?= $a46Esc($event['subject']) ?></td><td><?= $a46Esc(ucwords(str_replace(['_', '.'], ' ', $event['status']))) ?></td><td><a href="<?= $a46Esc(url($event['url'])) ?>"><?= $a46Esc($event['action']) ?></a></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody></table></div>
        <nav class="a46-pagination" aria-label="Recent platform activity pagination">
            <div class="a46-pagination-status" role="status">
                <?php if (!$a46ActivityQueryFailed): ?>
                    Showing <?= number_format($a46ActivityStart) ?>–<?= number_format($a46ActivityEnd) ?>
                    of <?= number_format($a46TotalActivities) ?> activities
                    <span>· 10 per page</span>
                    <?php if ($a46UnavailableSources > 0): ?><span>· Some activity sources unavailable</span><?php endif; ?>
                <?php else: ?>
                    Activity count temporarily unavailable
                <?php endif; ?>
            </div>
            <?php if (!$a46ActivityQueryFailed && $a46TotalPages > 1): ?>
            <div class="a46-pages" aria-label="Activity pages">
                <?php if ($a46ActivityPage > 1): ?>
                    <a class="a46-page-link" href="<?= $a46Esc($a46PageLink($a46ActivityPage - 1)) ?>" rel="prev">Previous</a>
                <?php endif; ?>
                <?php
                $a46WindowStart = max(1, min($a46ActivityPage - 2, $a46TotalPages - 4));
                $a46WindowEnd = min($a46TotalPages, $a46WindowStart + 4);
                ?>
                <?php if ($a46WindowStart > 1): ?>
                    <a class="a46-page-link" href="<?= $a46Esc($a46PageLink(1)) ?>">1</a>
                <?php endif; ?>
                <?php for ($a46Number = $a46WindowStart; $a46Number <= $a46WindowEnd; ++$a46Number): ?>
                    <?php if ($a46Number === $a46ActivityPage): ?>
                        <span class="a46-page-current" aria-current="page"><?= $a46Number ?></span>
                    <?php else: ?>
                        <a class="a46-page-link" href="<?= $a46Esc($a46PageLink($a46Number)) ?>"><?= $a46Number ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($a46WindowEnd < $a46TotalPages): ?>
                    <a class="a46-page-link" href="<?= $a46Esc($a46PageLink($a46TotalPages)) ?>"><?= $a46TotalPages ?></a>
                <?php endif; ?>
                <?php if ($a46ActivityPage < $a46TotalPages): ?>
                    <a class="a46-page-link" href="<?= $a46Esc($a46PageLink($a46ActivityPage + 1)) ?>" rel="next">Next</a>
                <?php endif; ?>
            </div>
            <form class="a46-page-jump" method="get" action="<?= e(url('/dashboard')) ?>#a46-recent-activity">
                <input type="hidden" name="activity_type" value="<?= $a46Esc($a46Filter) ?>">
                <label for="a46-go-to-page">Go to page</label>
                <input id="a46-go-to-page" name="activity_page" type="number"
                       min="1" max="<?= $a46TotalPages ?>" value="<?= $a46ActivityPage ?>"
                       inputmode="numeric" required aria-label="Go to activity page">
                <button type="submit">Go</button>
            </form>
            <?php endif; ?>
        </nav>
    </section>
</div>
