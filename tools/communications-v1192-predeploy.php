<?php

declare(strict_types=1);

use Barama\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

define('BARAMA_CLI', true);

$root = dirname(__DIR__);
$manifestPath = '';
$allowNonLive = in_array('--allow-non-live', $argv, true);
$jsonOnly = in_array('--json', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $candidateRoot = rtrim(substr($argument, 7), '/');

        if ($candidateRoot !== '') {
            $root = $candidateRoot;
        }

        continue;
    }

    if (str_starts_with($argument, '--manifest=')) {
        $manifestPath = substr($argument, 11);
    }
}

if ($manifestPath === '') {
    $manifestPath = $root . '/docs/releases/admin-communications-v1.19.2.json';
}

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

    $verifiedBaseFiles = 0;

    foreach ((array) ($manifest['accepted_base_files'] ?? []) as $entry) {
        if (!is_array($entry)) {
            $record(false, 'Release manifest contains an invalid base file entry.');
            continue;
        }

        $relative = (string) ($entry['path'] ?? '');
        $expectedPresent = (bool) ($entry['present'] ?? false);
        $path = $root . '/' . $relative;

        if (!$expectedPresent) {
            $record(
                !file_exists($path),
                'New v1.19.2 file is absent on accepted v1.19.1 baseline: '
                    . $relative
            );

            if (!file_exists($path)) {
                $verifiedBaseFiles++;
            }

            continue;
        }

        $expectedBlob = strtolower(
            trim((string) ($entry['git_blob_sha'] ?? ''))
        );

        if (
            $relative === ''
            || preg_match('/^[a-f0-9]{40}$/', $expectedBlob) !== 1
        ) {
            $record(
                false,
                'Release manifest contains an invalid accepted-base file binding.'
            );
            continue;
        }

        if (!is_file($path) || !is_readable($path)) {
            $record(false, 'Accepted-base file is missing: ' . $relative);
            continue;
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            $record(false, 'Accepted-base file is unreadable: ' . $relative);
            continue;
        }

        $actualBlob = $gitBlobSha($contents);
        $ok = hash_equals($expectedBlob, $actualBlob);
        $record(
            $ok,
            'Accepted v1.19.1 Git blob matches: ' . $relative
        );

        if ($ok) {
            $verifiedBaseFiles++;
        }
    }

    $details['verified_base_files'] = $verifiedBaseFiles;
    $details['expected_base_files'] = count(
        (array) ($manifest['accepted_base_files'] ?? [])
    );

    require $root . '/application/bootstrap/app.php';

    $pdo = Database::connection();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();

    try {
        $contract = (array) (
            $manifest['predeploy_database_contract'] ?? []
        );

        $migrationStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM schema_migrations
             WHERE migration = :migration'
        );

        foreach (
            (array) ($contract['required_applied_migrations'] ?? [])
            as $migration
        ) {
            $migrationStatement->execute(['migration' => $migration]);
            $record(
                (int) $migrationStatement->fetchColumn() === 1,
                'Accepted-base migration is applied exactly once: '
                    . $migration
            );
        }

        foreach (
            (array) ($contract['forbidden_applied_migrations'] ?? [])
            as $migration
        ) {
            $migrationStatement->execute(['migration' => $migration]);
            $record(
                (int) $migrationStatement->fetchColumn() === 0,
                'v1.19.2 migration is not yet recorded: ' . $migration
            );
        }

        $tableStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );

        foreach (
            (array) ($contract['tables_expected_absent'] ?? [])
            as $table
        ) {
            $tableStatement->execute(['table_name' => $table]);
            $record(
                (int) $tableStatement->fetchColumn() === 0,
                'v1.19.2 table is absent before deployment: ' . $table
            );
        }

        $tableStatement->execute(['table_name' => 'email_templates']);
        $record(
            (int) $tableStatement->fetchColumn() === 1,
            'Accepted v1.19.1 email template foundation exists.'
        );

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
            'Accepted communication.manage permission remains administrator-only.'
        );

        $details['database_baseline'] = [
            'users' => (int) $pdo->query(
                'SELECT COUNT(*) FROM users'
            )->fetchColumn(),
            'notifications' => (int) $pdo->query(
                'SELECT COUNT(*) FROM notifications'
            )->fetchColumn(),
            'email_outbox' => (int) $pdo->query(
                'SELECT COUNT(*) FROM email_outbox'
            )->fetchColumn(),
            'email_templates' => (int) $pdo->query(
                'SELECT COUNT(*) FROM email_templates'
            )->fetchColumn(),
        ];
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
} catch (Throwable $exception) {
    $failures[] = 'Pre-deploy checker exception: ' . $exception->getMessage();
}

$result = [
    'ok' => $failures === [],
    'release' => 'admin-communications-phase7-v1.19.2',
    'phase' => 'predeploy',
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
            "\nBarama Communications v1.19.2 pre-deploy acceptance: %d pass(es), %d failure(s).\n",
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
