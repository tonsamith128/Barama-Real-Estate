<?php

declare(strict_types=1);

use Barama\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(191) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY schema_migrations_unique (migration),
    KEY schema_migrations_batch_index (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$applied = $pdo
    ->query('SELECT migration FROM schema_migrations ORDER BY id')
    ->fetchAll(PDO::FETCH_COLUMN);

$appliedLookup = array_fill_keys(
    array_map(static fn (mixed $item): string => (string) $item, $applied),
    true
);

$migrations = glob(APP_PATH . '/database/migrations/*.php') ?: [];
sort($migrations, SORT_STRING);

if ($migrations === []) {
    fwrite(STDOUT, "No migrations found.\n");
    exit(0);
}

$batch = (int) $pdo->query(
    'SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations'
)->fetchColumn();

$ran = 0;

foreach ($migrations as $file) {
    $name = basename($file);

    if (isset($appliedLookup[$name])) {
        fwrite(STDOUT, "[SKIP] {$name}\n");
        continue;
    }

    $migration = require $file;

    if (!is_callable($migration)) {
        throw new RuntimeException("Migration [{$name}] must return a callable.");
    }

    try {
        $pdo->beginTransaction();
        $migration($pdo);

        $statement = $pdo->prepare(
            'INSERT INTO schema_migrations (migration, batch) VALUES (:migration, :batch)'
        );
        $statement->execute([
            'migration' => $name,
            'batch' => $batch,
        ]);

        $pdo->commit();
        $ran++;

        fwrite(STDOUT, "[MIGRATED] {$name}\n");
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "[FAILED] {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Migration complete. {$ran} migration(s) applied.\n");
