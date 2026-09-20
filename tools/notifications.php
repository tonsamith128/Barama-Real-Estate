<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Services\OutboxDeliveryService;
use Barama\Services\SavedSearchAlertService;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$command = strtolower(trim((string) ($argv[1] ?? 'run')));
$frequency = strtolower(trim((string) ($argv[2] ?? 'all')));
$force = in_array('--force', $argv, true);
$limitArgument = null;

foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches) === 1) {
        $limitArgument = (int) $matches[1];
    }
}

$limit = max(1, min(500, $limitArgument ?? 100));
$pdo = Database::connection();
$lockName = 'barama:notification-jobs';

$lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
$lockStatement->execute(['lock_name' => $lockName]);

if ((int) $lockStatement->fetchColumn() !== 1) {
    fwrite(STDERR, "[SKIP] Another Barama notification job is already running.\n");
    exit(0);
}

try {
    if ($command === 'alerts') {
        if (!in_array($frequency, ['all', 'daily', 'weekly'], true)) {
            fwrite(STDERR, "Usage: php tools/notifications.php alerts [all|daily|weekly] [--force] [--limit=N]\n");
            exit(2);
        }

        $result = (new SavedSearchAlertService())->process(
            $frequency,
            $limit,
            $force
        );

        fwrite(
            STDOUT,
            sprintf(
                "[PASS] Alerts: %d search(es) processed, %d new match(es), %d digest notification(s).\n",
                $result['processed'],
                $result['matched'],
                $result['notified']
            )
        );
        exit(0);
    }

    if ($command === 'outbox') {
        $outboxLimit = ctype_digit((string) ($argv[2] ?? ''))
            ? max(1, min(500, (int) $argv[2]))
            : $limit;

        $result = (new OutboxDeliveryService())->deliver($outboxLimit);

        fwrite(
            STDOUT,
            sprintf(
                "[PASS] Outbox: %d processed, %d sent, %d failed.\n",
                $result['processed'],
                $result['sent'],
                $result['failed']
            )
        );
        exit($result['failed'] > 0 ? 1 : 0);
    }

    if ($command !== 'run') {
        fwrite(
            STDERR,
            "Usage: php tools/notifications.php [run|alerts|outbox] [all|daily|weekly] [--force] [--limit=N]\n"
        );
        exit(2);
    }

    $alerts = (new SavedSearchAlertService())->process('all', $limit, $force);
    $outbox = (new OutboxDeliveryService())->deliver($limit);

    fwrite(
        STDOUT,
        sprintf(
            "[PASS] Notifications: %d search(es), %d new match(es), %d digest(s); outbox %d processed, %d sent, %d failed.\n",
            $alerts['processed'],
            $alerts['matched'],
            $alerts['notified'],
            $outbox['processed'],
            $outbox['sent'],
            $outbox['failed']
        )
    );

    exit($outbox['failed'] > 0 ? 1 : 0);
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $release->execute(['lock_name' => $lockName]);
}
