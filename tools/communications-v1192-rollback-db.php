<?php

declare(strict_types=1);

use Barama\Core\Database;
use RuntimeException;
use Throwable;

define('BARAMA_CLI', true);

$root = '';
$allowNonLive = in_array('--allow-non-live', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $root = rtrim(substr($argument, 7), '/');
    }
}

if ($root === '') {
    fwrite(STDERR, "[FAIL] Usage: php communications-v1192-rollback-db.php --root=/www/wwwroot/baramarealestate.tonsamith.com\n");
    exit(2);
}

$expectedRoot = '/www/wwwroot/baramarealestate.tonsamith.com';
$actualRoot = realpath($root) ?: $root;

if (!$allowNonLive && $actualRoot !== $expectedRoot) {
    fwrite(STDERR, "[FAIL] Refusing database rollback outside the Barama production root.\n");
    exit(2);
}

try {
    require $root . '/application/bootstrap/app.php';

    $pdo = Database::connection();

    $tableExists = static function (string $table) use ($pdo): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $statement->execute(['table_name' => $table]);

        return (int) $statement->fetchColumn() === 1;
    };

    $announcementRows = $tableExists('communication_announcements')
        ? (int) $pdo->query(
            'SELECT COUNT(*) FROM communication_announcements'
        )->fetchColumn()
        : 0;
    $deliveryRows = $tableExists('communication_announcement_deliveries')
        ? (int) $pdo->query(
            'SELECT COUNT(*) FROM communication_announcement_deliveries'
        )->fetchColumn()
        : 0;

    if ($announcementRows > 0 || $deliveryRows > 0) {
        fwrite(
            STDERR,
            sprintf(
                "[FAIL] Automatic database rollback refused: %d announcement row(s), %d delivery row(s) exist. Preserve/resolve this data manually before downgrading.\n",
                $announcementRows,
                $deliveryRows
            )
        );
        exit(3);
    }

    if ($tableExists('communication_announcement_deliveries')) {
        $pdo->exec(
            'DROP TABLE communication_announcement_deliveries'
        );
        fwrite(
            STDOUT,
            "[ROLLBACK] Dropped empty communication_announcement_deliveries.\n"
        );
    }

    if ($tableExists('communication_announcements')) {
        $pdo->exec('DROP TABLE communication_announcements');
        fwrite(
            STDOUT,
            "[ROLLBACK] Dropped empty communication_announcements.\n"
        );
    }

    $statement = $pdo->prepare(
        'DELETE FROM schema_migrations
         WHERE migration = :migration'
    );
    $statement->execute([
        'migration' => '0020_create_announcement_foundation.php',
    ]);

    fwrite(
        STDOUT,
        "[PASS] Communications v1.19.2 additive database migration rolled back safely.\n"
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        '[FAIL] Communications database rollback failed: '
            . $exception->getMessage()
            . PHP_EOL
    );
    exit(1);
}
