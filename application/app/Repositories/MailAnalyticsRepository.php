<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class MailAnalyticsRepository
{
    /* BARAMA_MAIL_ANALYTICS_V150 */

    /** @return array<string,mixed> */
    public function report(int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = date('Y-m-d H:i:s', time() - ($days * 86400));
        $pdo = Database::connection();

        $summary = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN event_type = 'attempt_started' THEN 1 ELSE 0 END) AS attempts,
                SUM(CASE WHEN event_type = 'attempt_started' AND attempt_number > 1 THEN 1 ELSE 0 END) AS retries,
                SUM(CASE WHEN event_type = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN event_type = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN event_type IN ('manual_requeue','bulk_requeue') THEN 1 ELSE 0 END) AS manual_requeues,
                SUM(CASE WHEN event_type IN ('manual_cancel','bulk_cancel') THEN 1 ELSE 0 END) AS manual_cancels
             FROM email_outbox_events
             WHERE created_at >= :since"
        );
        $summary->execute(['since' => $since]);
        $summaryRow = $summary->fetch(PDO::FETCH_ASSOC);
        $summaryRow = is_array($summaryRow) ? $summaryRow : [];

        $sent = (int) ($summaryRow['sent'] ?? 0);
        $failed = (int) ($summaryRow['failed'] ?? 0);
        $outcomes = $sent + $failed;

        $retryStatement = $pdo->prepare(
            "SELECT id, email_outbox_id, event_type, created_at
             FROM email_outbox_events
             WHERE created_at >= :since
               AND event_type IN ('failed','attempt_started')
             ORDER BY email_outbox_id ASC, created_at ASC, id ASC"
        );
        $retryStatement->execute(['since' => $since]);
        $retryRows = $retryStatement->fetchAll(PDO::FETCH_ASSOC);

        $pendingFailure = [];
        $retryDelays = [];

        foreach ($retryRows as $row) {
            $messageId = (int) ($row['email_outbox_id'] ?? 0);
            if ($messageId < 1) {
                continue;
            }

            $eventType = (string) ($row['event_type'] ?? '');
            $timestamp = strtotime((string) ($row['created_at'] ?? ''));

            if ($timestamp === false) {
                continue;
            }

            if ($eventType === 'failed') {
                $pendingFailure[$messageId] = $timestamp;
                continue;
            }

            if ($eventType === 'attempt_started' && isset($pendingFailure[$messageId])) {
                $delay = $timestamp - (int) $pendingFailure[$messageId];
                if ($delay >= 0) {
                    $retryDelays[] = $delay;
                }
                unset($pendingFailure[$messageId]);
            }
        }

        $averageRetrySeconds = $retryDelays !== []
            ? (int) round(array_sum($retryDelays) / count($retryDelays))
            : null;

        $daily = $pdo->prepare(
            "SELECT
                DATE(created_at) AS day,
                SUM(CASE WHEN event_type = 'attempt_started' THEN 1 ELSE 0 END) AS attempts,
                SUM(CASE WHEN event_type = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN event_type = 'failed' THEN 1 ELSE 0 END) AS failed
             FROM email_outbox_events
             WHERE created_at >= :since
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        $daily->execute(['since' => $since]);
        $dailyRows = $daily->fetchAll(PDO::FETCH_ASSOC);

        $errors = $pdo->prepare(
            "SELECT
                LEFT(COALESCE(NULLIF(TRIM(error_text), ''), 'Unknown delivery failure'), 180) AS error_text,
                COUNT(*) AS total
             FROM email_outbox_events
             WHERE event_type = 'failed'
               AND created_at >= :since
             GROUP BY LEFT(COALESCE(NULLIF(TRIM(error_text), ''), 'Unknown delivery failure'), 180)
             ORDER BY total DESC, error_text ASC
             LIMIT 8"
        );
        $errors->execute(['since' => $since]);
        $errorRows = $errors->fetchAll(PDO::FETCH_ASSOC);

        $distribution = $pdo->prepare(
            "SELECT COALESCE(attempt_number, 0) AS attempt_number, COUNT(*) AS total
             FROM email_outbox_events
             WHERE event_type = 'attempt_started'
               AND created_at >= :since
             GROUP BY COALESCE(attempt_number, 0)
             ORDER BY attempt_number ASC
             LIMIT 12"
        );
        $distribution->execute(['since' => $since]);
        $distributionRows = $distribution->fetchAll(PDO::FETCH_ASSOC);

        $trackingStarted = $pdo->query(
            "SELECT MIN(created_at)
             FROM email_outbox_events
             WHERE event_type = 'history_tracking_started'"
        )->fetchColumn();

        $queueRows = $pdo->query(
            "SELECT status, COUNT(*) AS total
             FROM email_outbox
             GROUP BY status"
        )->fetchAll(PDO::FETCH_ASSOC);

        $queueHealth = [
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
            'sent' => 0,
            'cancelled' => 0,
        ];

        foreach ($queueRows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (array_key_exists($status, $queueHealth)) {
                $queueHealth[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return [
            'available' => true,
            'days' => $days,
            'since' => $since,
            'tracking_started_at' => $trackingStarted !== false ? (string) $trackingStarted : '',
            'attempts' => (int) ($summaryRow['attempts'] ?? 0),
            'retries' => (int) ($summaryRow['retries'] ?? 0),
            'sent' => $sent,
            'failed' => $failed,
            'manual_requeues' => (int) ($summaryRow['manual_requeues'] ?? 0),
            'manual_cancels' => (int) ($summaryRow['manual_cancels'] ?? 0),
            'attempt_success_rate' => $outcomes > 0 ? round(($sent / $outcomes) * 100, 1) : null,
            'average_retry_seconds' => $averageRetrySeconds,
            'daily' => is_array($dailyRows) ? $dailyRows : [],
            'errors' => is_array($errorRows) ? $errorRows : [],
            'attempt_distribution' => is_array($distributionRows) ? $distributionRows : [],
            'queue_health' => $queueHealth,
        ];
    }
}
