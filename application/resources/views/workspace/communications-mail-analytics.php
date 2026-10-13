<?php

declare(strict_types=1);

/* BARAMA_MAIL_ANALYTICS_V150 */

$analytics = is_array($mailAnalytics ?? null) ? $mailAnalytics : [];
$days = in_array((int) ($mailAnalyticsDays ?? 30), [7, 30, 90], true)
    ? (int) $mailAnalyticsDays
    : 30;
$available = (bool) ($analytics['available'] ?? false);
$daily = is_array($analytics['daily'] ?? null) ? $analytics['daily'] : [];
$errors = is_array($analytics['errors'] ?? null) ? $analytics['errors'] : [];
$distribution = is_array($analytics['attempt_distribution'] ?? null)
    ? $analytics['attempt_distribution']
    : [];
$queueHealth = is_array($analytics['queue_health'] ?? null)
    ? $analytics['queue_health']
    : [];

$formatDuration = static function (?int $seconds): string {
    if ($seconds === null) {
        return '—';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return number_format($seconds / 60, 1) . 'm';
    }
    if ($seconds < 86400) {
        return number_format($seconds / 3600, 1) . 'h';
    }
    return number_format($seconds / 86400, 1) . 'd';
};

$maxDaily = 1;
foreach ($daily as $row) {
    $maxDaily = max($maxDaily, (int) ($row['sent'] ?? 0), (int) ($row['failed'] ?? 0));
}

$maxDistribution = 1;
foreach ($distribution as $row) {
    $maxDistribution = max($maxDistribution, (int) ($row['total'] ?? 0));
}
?>
<section class="workspace-page mail-analytics-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Mail Analytics</h1>
            <p>Read-only delivery observability from immutable outbox event history. This page is isolated from Mail Queue operations.</p>
        </div>
        <div class="mail-analytics-page__header-actions">
            <a class="mail-analytics-page__queue-link" href="<?= e(url('/dashboard/mail-queue')) ?>">
                <?= app_icon('pipeline') ?>
                <span>Mail Queue &amp; Logs</span>
            </a>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Read-only analytics
            </span>
        </div>
    </div>

    <div class="mail-analytics-toolbar" aria-label="Analytics reporting window">
        <div>
            <strong>Reporting window</strong>
            <span>Calculated from immutable delivery events only.</span>
        </div>
        <nav class="mail-analytics-window-switcher" aria-label="Select analytics window">
            <?php foreach ([7, 30, 90] as $option): ?>
                <a
                    class="<?= $days === $option ? 'is-active' : '' ?>"
                    href="<?= e(url('/dashboard/mail-analytics?days=' . $option)) ?>"
                    <?= $days === $option ? 'aria-current="page"' : '' ?>
                ><?= e((string) $option) ?> days</a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if (!$available): ?>
        <section class="mail-analytics-unavailable" role="status">
            <span><?= app_icon('alert') ?></span>
            <div>
                <h2>Analytics temporarily unavailable</h2>
                <p>The reporting query failed safely. Mail Queue operations are unaffected and remain available.</p>
            </div>
        </section>
    <?php else: ?>
        <div class="mail-analytics-metrics" aria-label="Delivery analytics summary">
            <article class="mail-analytics-metric">
                <span>Attempt success</span>
                <strong><?= ($analytics['attempt_success_rate'] ?? null) !== null ? e(number_format((float) $analytics['attempt_success_rate'], 1)) . '%' : '—' ?></strong>
                <small>Sent outcomes ÷ sent + failed outcomes</small>
            </article>
            <article class="mail-analytics-metric">
                <span>Attempts</span>
                <strong><?= e(number_format((int) ($analytics['attempts'] ?? 0))) ?></strong>
                <small>Worker delivery attempts observed</small>
            </article>
            <article class="mail-analytics-metric">
                <span>Retries</span>
                <strong><?= e(number_format((int) ($analytics['retries'] ?? 0))) ?></strong>
                <small>Attempts numbered 2 or higher</small>
            </article>
            <article class="mail-analytics-metric">
                <span>Avg. retry delay</span>
                <strong><?= e($formatDuration(($analytics['average_retry_seconds'] ?? null) !== null ? (int) $analytics['average_retry_seconds'] : null)) ?></strong>
                <small>Observed failure → next attempt</small>
            </article>
            <article class="mail-analytics-metric">
                <span>Failed attempts</span>
                <strong><?= e(number_format((int) ($analytics['failed'] ?? 0))) ?></strong>
                <small><?= e(number_format((int) ($analytics['sent'] ?? 0))) ?> successful sends</small>
            </article>
            <article class="mail-analytics-metric">
                <span>Manual actions</span>
                <strong><?= e(number_format((int) ($analytics['manual_requeues'] ?? 0) + (int) ($analytics['manual_cancels'] ?? 0))) ?></strong>
                <small><?= e(number_format((int) ($analytics['manual_requeues'] ?? 0))) ?> requeues · <?= e(number_format((int) ($analytics['manual_cancels'] ?? 0))) ?> cancels</small>
            </article>
        </div>

        <div class="mail-analytics-grid">
            <article class="mail-analytics-panel mail-analytics-panel--wide">
                <header>
                    <div>
                        <h2>Daily delivery outcomes</h2>
                        <p>Sent and failed events recorded during the selected period.</p>
                    </div>
                </header>

                <?php if ($daily === []): ?>
                    <div class="mail-analytics-empty">No delivery outcomes were recorded in this window.</div>
                <?php else: ?>
                    <div class="mail-analytics-trends">
                        <?php foreach ($daily as $row): ?>
                            <?php
                            $sent = (int) ($row['sent'] ?? 0);
                            $failed = (int) ($row['failed'] ?? 0);
                            $sentWidth = $sent > 0 ? max(3, (int) round(($sent / $maxDaily) * 100)) : 0;
                            $failedWidth = $failed > 0 ? max(3, (int) round(($failed / $maxDaily) * 100)) : 0;
                            ?>
                            <div class="mail-analytics-trend">
                                <time><?= e((string) ($row['day'] ?? '')) ?></time>
                                <div class="mail-analytics-trend__bars">
                                    <span class="mail-analytics-trend__sent" data-barama-width="<?= e((string) $sentWidth) ?>"></span>
                                    <span class="mail-analytics-trend__failed" data-barama-width="<?= e((string) $failedWidth) ?>"></span>
                                </div>
                                <span><b><?= e((string) $sent) ?></b> / <?= e((string) $failed) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mail-analytics-legend">
                        <span><i class="is-sent"></i> Sent</span>
                        <span><i class="is-failed"></i> Failed</span>
                    </div>
                <?php endif; ?>
            </article>

            <article class="mail-analytics-panel">
                <header>
                    <div>
                        <h2>Current queue health</h2>
                        <p>Current outbox state, separate from historical outcomes.</p>
                    </div>
                </header>
                <div class="mail-analytics-health">
                    <?php foreach (['pending', 'processing', 'failed', 'sent', 'cancelled'] as $status): ?>
                        <div>
                            <span><?= e(ucfirst($status)) ?></span>
                            <strong><?= e(number_format((int) ($queueHealth[$status] ?? 0))) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="mail-analytics-panel">
                <header>
                    <div>
                        <h2>Top failure reasons</h2>
                        <p>Stored error text from failed attempt events.</p>
                    </div>
                </header>
                <?php if ($errors === []): ?>
                    <div class="mail-analytics-empty">No failed-attempt events in this window.</div>
                <?php else: ?>
                    <ol class="mail-analytics-errors">
                        <?php foreach ($errors as $row): ?>
                            <li>
                                <span><?= e((string) ($row['error_text'] ?? 'Unknown delivery failure')) ?></span>
                                <strong><?= e(number_format((int) ($row['total'] ?? 0))) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </article>

            <article class="mail-analytics-panel">
                <header>
                    <div>
                        <h2>Attempt distribution</h2>
                        <p>How far observed messages progress into retries.</p>
                    </div>
                </header>
                <?php if ($distribution === []): ?>
                    <div class="mail-analytics-empty">No delivery attempts in this window.</div>
                <?php else: ?>
                    <div class="mail-analytics-distribution">
                        <?php foreach ($distribution as $row): ?>
                            <?php
                            $attempt = max(0, (int) ($row['attempt_number'] ?? 0));
                            $total = (int) ($row['total'] ?? 0);
                            $width = max(3, (int) round(($total / $maxDistribution) * 100));
                            ?>
                            <div>
                                <span><?= $attempt > 0 ? 'Attempt ' . e((string) $attempt) : 'Unnumbered' ?></span>
                                <div><i data-barama-width="<?= e((string) $width) ?>"></i></div>
                                <strong><?= e(number_format($total)) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    <?php endif; ?>

    <section class="mail-analytics-boundary">
        <div>
            <?= app_icon('shield') ?>
            <span>
                Immutable event tracking started:
                <strong><?= trim((string) ($analytics['tracking_started_at'] ?? '')) !== '' ? e((string) $analytics['tracking_started_at']) : 'Not available' ?></strong>
            </span>
        </div>
        <p>Metrics before that timestamp are not represented as attempt-level history. Template and transport/provider dimensions are intentionally omitted because they are not yet persisted per delivery attempt.</p>
    </section>
</section>
