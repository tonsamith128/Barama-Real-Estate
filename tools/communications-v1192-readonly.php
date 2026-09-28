<?php

declare(strict_types=1);

use Barama\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

define('BARAMA_CLI', true);

$root = dirname(__DIR__);
$manifestPath = $root . '/docs/releases/admin-communications-v1.19.2.json';
$allowNonLive = in_array('--allow-non-live', $argv, true);
$jsonOnly = in_array('--json', $argv, true);

$passes = [];
$failures = [];
$details = [];

$record = static function (
    bool $ok,
    string $message
) use (&$passes, &$failures): void {
    if ($ok) {
        $passes[] = $message;
        return;
    }

    $failures[] = $message;
};

$gitBlobSha = static function (string $contents): string {
    return sha1('blob ' . strlen($contents) . "\0" . $contents);
};

try {
    if (!is_file($manifestPath)) {
        throw new RuntimeException(
            'v1.19.2 release manifest is missing: ' . $manifestPath
        );
    }

    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($manifest)) {
        throw new RuntimeException('v1.19.2 release manifest is invalid.');
    }

    $expectedRoot = (string) ($manifest['target_root'] ?? '');
    $expectedPhp = (string) ($manifest['php_binary'] ?? '');
    $actualRoot = realpath($root) ?: $root;
    $actualPhp = realpath(PHP_BINARY) ?: PHP_BINARY;
    $expectedPhpReal = realpath($expectedPhp) ?: $expectedPhp;

    $record(
        $allowNonLive || $actualRoot === $expectedRoot,
        'Application root matches the hard-scoped Barama production root.'
    );
    $record(
        $allowNonLive || $actualPhp === $expectedPhpReal,
        'PHP CLI matches the expected aaPanel PHP 8.4 binary.'
    );

    $verifiedFiles = 0;

    foreach ((array) ($manifest['production_files'] ?? []) as $entry) {
        if (!is_array($entry)) {
            $record(false, 'Release manifest contains an invalid file entry.');
            continue;
        }

        $relative = (string) ($entry['path'] ?? '');
        $expectedBlob = strtolower(
            trim((string) ($entry['git_blob_sha'] ?? ''))
        );

        if (
            $relative === ''
            || preg_match('/^[a-f0-9]{40}$/', $expectedBlob) !== 1
        ) {
            $record(
                false,
                'Release manifest contains an invalid production file binding.'
            );
            continue;
        }

        $path = $root . '/' . $relative;

        if (!is_file($path) || !is_readable($path)) {
            $record(false, 'Required release file is missing: ' . $relative);
            continue;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $record(false, 'Required release file is unreadable: ' . $relative);
            continue;
        }

        $actualBlob = $gitBlobSha($contents);
        $record(
            hash_equals($expectedBlob, $actualBlob),
            'Git blob matches release manifest: ' . $relative
        );

        if (hash_equals($expectedBlob, $actualBlob)) {
            $verifiedFiles++;
        }
    }

    $details['verified_production_files'] = $verifiedFiles;
    $details['expected_production_files'] = count(
        (array) ($manifest['production_files'] ?? [])
    );

    require $root . '/application/bootstrap/app.php';

    foreach ((array) ($manifest['required_methods'] ?? []) as $class => $methods) {
        $classExists = class_exists((string) $class);
        $record(
            $classExists,
            'Required release class is autoloadable: ' . $class
        );

        if (!$classExists) {
            continue;
        }

        foreach ((array) $methods as $method) {
            $record(
                method_exists((string) $class, (string) $method),
                'Required release method is available: '
                    . $class
                    . '::'
                    . $method
            );
        }
    }

    $audiences = \Barama\Services\AnnouncementManagementService::audienceOptions();
    $priorities = \Barama\Services\AnnouncementManagementService::priorityOptions();

    $record(
        array_keys($audiences) === [
            'all',
            'customer',
            'developer',
            'administrator',
        ],
        'Announcement audience contract is exact.'
    );
    $record(
        array_keys($priorities) === ['normal', 'high', 'urgent'],
        'Announcement priority contract is exact.'
    );

    $routes = (string) file_get_contents(
        $root . '/application/routes/web.php'
    );

    foreach ((array) ($manifest['required_routes'] ?? []) as $route) {
        $record(
            str_contains($routes, "'" . (string) $route . "'"),
            'Announcement route is present: ' . $route
        );
    }

    $record(
        substr_count(
            $routes,
            "new PermissionMiddleware('communication.manage')"
        ) >= 5,
        'Announcement routes are protected by communication.manage.'
    );
    $record(
        substr_count(
            $routes,
            'new CsrfMiddleware()'
        ) >= 4,
        'Announcement mutation routes retain CSRF protection.'
    );

    $runnerPath = $root . '/' . (string) (
        $manifest['runner']['path'] ?? 'tools/notifications.php'
    );
    $runner = is_file($runnerPath)
        ? (string) file_get_contents($runnerPath)
        : '';

    $record(
        $runner !== ''
        && str_contains(
            $runner,
            (string) ($manifest['runner']['lock'] ?? '')
        ),
        'Notification runner retains the canonical advisory lock.'
    );
    $record(
        $runner !== ''
        && str_contains(
            $runner,
            'AnnouncementDeliveryService'
        )
        && str_contains(
            $runner,
            "if (\$command === 'announcements')"
        ),
        'Notification runner integrates scheduled announcement delivery.'
    );

    $pdo = Database::connection();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();

    try {
        $migration = (string) ($manifest['migration'] ?? '');

        $migrationStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM schema_migrations
             WHERE migration = :migration'
        );
        $migrationStatement->execute(['migration' => $migration]);

        $record(
            (int) $migrationStatement->fetchColumn() === 1,
            'Announcement migration is recorded exactly once: ' . $migration
        );

        foreach (
            (array) ($manifest['required_tables'] ?? [])
            as $table => $requiredColumns
        ) {
            $tableStatement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name'
            );
            $tableStatement->execute(['table_name' => $table]);

            $record(
                (int) $tableStatement->fetchColumn() === 1,
                'Required communications table exists: ' . $table
            );

            $columnStatement = $pdo->prepare(
                'SELECT column_name
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                 ORDER BY ordinal_position'
            );
            $columnStatement->execute(['table_name' => $table]);
            $columns = array_map(
                static fn (mixed $value): string => (string) $value,
                $columnStatement->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ((array) $requiredColumns as $column) {
                $record(
                    in_array((string) $column, $columns, true),
                    'Required column exists: ' . $table . '.' . $column
                );
            }
        }

        foreach (
            (array) ($manifest['required_indexes'] ?? [])
            as $table => $requiredIndexes
        ) {
            $indexStatement = $pdo->prepare(
                'SELECT DISTINCT index_name
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name'
            );
            $indexStatement->execute(['table_name' => $table]);
            $indexes = array_map(
                static fn (mixed $value): string => (string) $value,
                $indexStatement->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ((array) $requiredIndexes as $index) {
                $record(
                    in_array((string) $index, $indexes, true),
                    'Required index exists: ' . $table . '.' . $index
                );
            }
        }

        foreach (
            (array) ($manifest['required_foreign_keys'] ?? [])
            as $table => $requiredForeignKeys
        ) {
            $foreignKeyStatement = $pdo->prepare(
                'SELECT constraint_name
                 FROM information_schema.referential_constraints
                 WHERE constraint_schema = DATABASE()
                   AND table_name = :table_name'
            );
            $foreignKeyStatement->execute(['table_name' => $table]);
            $foreignKeys = array_map(
                static fn (mixed $value): string => (string) $value,
                $foreignKeyStatement->fetchAll(PDO::FETCH_COLUMN)
            );

            foreach ((array) $requiredForeignKeys as $foreignKey) {
                $record(
                    in_array((string) $foreignKey, $foreignKeys, true),
                    'Required foreign key exists: '
                        . $table
                        . '.'
                        . $foreignKey
                );
            }
        }

        $permissionSlug = (string) (
            $manifest['permission']['slug'] ?? 'communication.manage'
        );
        $permissionStatement = $pdo->prepare(
            'SELECT r.slug
             FROM roles r
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE p.slug = :permission_slug
             ORDER BY r.slug ASC'
        );
        $permissionStatement->execute([
            'permission_slug' => $permissionSlug,
        ]);
        $assignedRoles = array_map(
            static fn (mixed $value): string => (string) $value,
            $permissionStatement->fetchAll(PDO::FETCH_COLUMN)
        );
        $allowedRoles = array_values(
            array_map(
                static fn (mixed $value): string => (string) $value,
                (array) (
                    $manifest['permission']['allowed_role_slugs']
                    ?? ['administrator']
                )
            )
        );
        sort($assignedRoles, SORT_STRING);
        sort($allowedRoles, SORT_STRING);

        $record(
            $assignedRoles === $allowedRoles,
            'communication.manage is assigned only to the allowed administrator role.'
        );

        $summary = [];

        foreach ([
            'communication_announcements',
            'communication_announcement_deliveries',
            'notifications',
            'email_outbox',
        ] as $table) {
            $summary[$table] = (int) $pdo
                ->query('SELECT COUNT(*) FROM ' . $table)
                ->fetchColumn();
        }

        $summary['due_scheduled_announcements'] = (int) $pdo
            ->query(
                'SELECT COUNT(*)
                 FROM communication_announcements
                 WHERE status = "scheduled"
                   AND scheduled_at IS NOT NULL
                   AND scheduled_at <= CURRENT_TIMESTAMP'
            )
            ->fetchColumn();

        $templateStatement = $pdo->query(
            'SELECT COUNT(*)
             FROM email_templates
             WHERE template_key = "announcement_published"
               AND status = "active"'
        );
        $summary['active_announcement_email_templates'] = (int) (
            $templateStatement->fetchColumn()
        );

        $lockStatement = $pdo->query(
            'SELECT IS_FREE_LOCK("barama:notification-jobs")'
        );
        $summary['notification_runner_lock_free'] = (
            (int) $lockStatement->fetchColumn() === 1
        );

        $details['database_summary'] = $summary;
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
} catch (Throwable $exception) {
    $failures[] = 'Acceptance checker exception: ' . $exception->getMessage();
}

$result = [
    'ok' => $failures === [],
    'release' => 'admin-communications-phase7-v1.19.2',
    'mode' => 'read-only',
    'passes' => count($passes),
    'failures' => count($failures),
    'details' => $details,
    'failed_checks' => $failures,
];

if (!$jsonOnly) {
    foreach ($passes as $message) {
        fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
    }

    foreach ($failures as $message) {
        fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    }

    fwrite(
        STDOUT,
        sprintf(
            "\nBarama Communications v1.19.2 read-only acceptance: %d pass(es), %d failure(s).\n",
            count($passes),
            count($failures)
        )
    );
}

fwrite(
    STDOUT,
    json_encode(
        $result,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
    )
    . PHP_EOL
);

exit($failures === [] ? 0 : 1);
