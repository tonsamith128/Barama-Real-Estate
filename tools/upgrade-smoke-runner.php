<?php

declare(strict_types=1);

/**
 * Execute the canonical upgrade scenario against a supplied Barama package root.
 *
 * @return array<string,mixed>
 */
function run_barama_upgrade_smoke(
    string $root,
    string $databasePrefix
): array {
    if (!defined('BARAMA_CLI')) {
        define('BARAMA_CLI', true);
    }

    require $root . '/application/bootstrap/runtime.php';

    $host = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
    $username = (string) (getenv('PACKAGE_DB_USERNAME') ?: 'root');
    $password = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');
    $databaseName = $databasePrefix . '_' . bin2hex(random_bytes(4));

    $server = new \PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $username,
        $password,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $tick = chr(96);
    $server->exec(
        "CREATE DATABASE {$tick}{$databaseName}{$tick} "
        . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pdo = new \PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec(
        'CREATE TABLE schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(191) NOT NULL,
            batch INT UNSIGNED NOT NULL,
            ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY schema_migrations_unique (migration),
            KEY schema_migrations_batch_index (batch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationFiles = glob(
        $root . '/application/database/migrations/*.php'
    ) ?: [];
    sort($migrationFiles, SORT_STRING);
    $applied = [];

    foreach ($migrationFiles as $file) {
        $name = basename($file);

        if (strcmp($name, '0010_release_security_hardening.php') >= 0) {
            continue;
        }

        $migration = require $file;

        if (!is_callable($migration)) {
            throw new \RuntimeException(
                'Pre-upgrade migration is invalid: ' . $name
            );
        }

        $migration($pdo);

        $statement = $pdo->prepare(
            'INSERT INTO schema_migrations (migration, batch)
             VALUES (:migration, 1)'
        );
        $statement->execute(['migration' => $name]);
        $applied[] = $name;
    }

    if (
        count($applied) !== 9
        || $applied[0] !== '0001_create_auth_core.php'
        || $applied[8] !== '0009_create_account_onboarding.php'
    ) {
        throw new \RuntimeException(
            'The reconstructed previous release schema is incomplete.'
        );
    }

    $expectedPendingMigrations = [
        '0010_release_security_hardening.php',
        '0011_create_workspace_preferences.php',
        '0012_create_cms_pages.php',
        '0013_create_billing_foundation.php',
        '0014_create_invoice_transaction_foundation.php',
        '0015_create_coupon_discount_foundation.php',
        '0016_create_tax_rule_foundation.php',
        '0017_create_payment_webhook_foundation.php',
        '0018_create_communication_permission.php',
        '0019_create_email_template_foundation.php',
        '0021_create_newsletter_subscribers.php',
        '0022_create_contact_messages.php',
    ];

    $adminPassword = 'Barama-Upgrade-QA-123!';
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);

    if (!is_string($hash)) {
        throw new \RuntimeException(
            'Unable to create upgrade QA administrator password.'
        );
    }

    $userInsert = $pdo->prepare(
        'INSERT INTO users
            (uuid, name, email, password_hash, status, email_verified_at)
         VALUES
            (:uuid, "Upgrade QA Administrator", "upgrade-admin@example.test",
             :password_hash, "active", CURRENT_TIMESTAMP)'
    );
    $userInsert->execute([
        'uuid' => \Barama\Support\IdGenerator::uuid(),
        'password_hash' => $hash,
    ]);
    $adminId = (int) $pdo->lastInsertId();

    $roleIds = $pdo->query(
        "SELECT slug, id FROM roles WHERE slug IN ('user','administrator')"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);

    foreach (['user', 'administrator'] as $slug) {
        if (!isset($roleIds[$slug])) {
            throw new \RuntimeException(
                'Required administrator role seed is unavailable.'
            );
        }

        $statement = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        );
        $statement->execute([
            'user_id' => $adminId,
            'role_id' => (int) $roleIds[$slug],
        ]);
    }

    $pdo->prepare(
        'INSERT INTO application_settings
            (setting_key, setting_value, value_type)
         VALUES ("installation.version", "0.9.3-dev", "string")'
    )->execute();

    \Barama\Core\Installation::removeIncompleteState();
    $installationIdPath = \Barama\Core\Installation::installationIdPath();

    if (is_file($installationIdPath)) {
        @unlink($installationIdPath);
    }

    $installationId = \Barama\Core\Installation::installationId();

    \Barama\Core\Installation::writeRuntimeConfig([
        'APP_NAME' => 'Barama Upgrade QA',
        'APP_ENV' => 'production',
        'APP_DEBUG' => false,
        'APP_URL' => 'https://upgrade.example.test',
        'APP_TIMEZONE' => 'Asia/Phnom_Penh',
        'APP_LOCALE' => 'en',
        'APP_KEY' => base64_encode(random_bytes(32)),
        'SESSION_NAME' => 'barama_upgrade_qa',
        'DB_HOST' => $host,
        'DB_PORT' => $port,
        'DB_DATABASE' => $databaseName,
        'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password,
    ]);

    \Barama\Core\Installation::writeLicenseState([
        'activation_token' => 'upgrade-qa-activation-token',
        'license_id' => 'upgrade-qa',
        'product_slug' => 'barama-real-estate',
        'license_type' => 'qa',
        'source' => 'qa',
        'is_test' => true,
        'status' => 'active',
        'domain' => 'upgrade.example.test',
        'installation_id' => $installationId,
        'verification_method' => 'envato',
        'validated_at' => gmdate('c'),
        'last_validated_at' => gmdate('c'),
    ]);

    \Barama\Core\Installation::markInstalled([
        'version' => '0.9.3-dev',
        'installation_id' => $installationId,
        'administrator_user_id' => $adminId,
        'database' => $databaseName,
        'migrations_applied' => 9,
        'product_slug' => 'barama-real-estate',
    ]);

    $_SERVER['SCRIPT_NAME'] = '/updater/index.php';
    $_SERVER['HTTP_HOST'] = 'upgrade.example.test';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';

    $upgrade = new \Barama\Services\UpgradeService($pdo);
    $status = $upgrade->status();

    if (
        ($status['current_version'] ?? '') !== '0.9.3-dev'
        || ($status['target_version'] ?? '') !== APP_VERSION
        || (array) ($status['pending_migrations'] ?? [])
            !== $expectedPendingMigrations
        || empty($status['ready'])
    ) {
        throw new \RuntimeException(
            'Pre-upgrade status did not identify the expected migrations.'
        );
    }

    $backupBlocked = false;

    try {
        $upgrade->upgrade([
            'admin_email' => 'upgrade-admin@example.test',
            'admin_password' => $adminPassword,
            'backup_confirmed' => '0',
        ]);
    } catch (\RuntimeException) {
        $backupBlocked = true;
    }

    $credentialBlocked = false;

    try {
        $upgrade->upgrade([
            'admin_email' => 'upgrade-admin@example.test',
            'admin_password' => 'incorrect-password',
            'backup_confirmed' => '1',
        ]);
    } catch (\RuntimeException) {
        $credentialBlocked = true;
    }

    if (!$backupBlocked || !$credentialBlocked) {
        throw new \RuntimeException(
            'Upgrade backup or administrator authentication safeguards were bypassed.'
        );
    }

    $revokedClient = new \Barama\Services\LicenseManagerClient(
        static fn (string $path, array $payload): array => [
            'success' => true,
            'license' => [
                'product_slug' => 'barama-real-estate',
                'status' => 'revoked',
                'domain' => (string) ($payload['domain'] ?? ''),
                'installation_id' => (string) ($payload['installation_id'] ?? ''),
            ],
        ]
    );
    $revokedUpgrade = new \Barama\Services\UpgradeService(
        $pdo,
        $revokedClient
    );
    $revokedBlocked = false;

    try {
        $revokedUpgrade->upgrade([
            'admin_email' => 'upgrade-admin@example.test',
            'admin_password' => $adminPassword,
            'backup_confirmed' => '1',
        ]);
    } catch (\RuntimeException $exception) {
        $revokedBlocked =
            !\Barama\Services\LicenseManagerClient::isTemporaryFailure(
                $exception
            )
            && str_contains(
                strtolower($exception->getMessage()),
                'not active'
            );
    }

    if (!$revokedBlocked) {
        throw new \RuntimeException(
            'A revoked license incorrectly received updater offline grace.'
        );
    }

    $temporaryClient = new \Barama\Services\LicenseManagerClient(
        static function (string $path, array $payload): array {
            throw new \RuntimeException(
                'License service is temporarily unavailable. Please try again.',
                \Barama\Services\LicenseManagerClient::ERROR_TEMPORARY_UNAVAILABLE
            );
        }
    );
    $graceUpgrade = new \Barama\Services\UpgradeService(
        $pdo,
        $temporaryClient
    );

    $result = $graceUpgrade->upgrade([
        'admin_email' => 'upgrade-admin@example.test',
        'admin_password' => $adminPassword,
        'backup_confirmed' => '1',
        'ip' => '127.0.0.99',
        'user_agent' => 'Barama Upgrade QA',
    ]);

    if (
        empty($result['upgraded'])
        || ($result['previous_version'] ?? '') !== '0.9.3-dev'
        || ($result['version'] ?? '') !== APP_VERSION
        || (array) ($result['migrations'] ?? [])
            !== $expectedPendingMigrations
    ) {
        throw new \RuntimeException(
            'Upgrade did not apply the expected migrations and version.'
        );
    }

    $authVersion = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'auth_version'"
    )->fetchColumn();

    $recordedVersion = (string) $pdo->query(
        "SELECT setting_value
         FROM application_settings
         WHERE setting_key = 'installation.version'"
    )->fetchColumn();

    $audit = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM security_audit_events
         WHERE event_type = 'system.upgrade_completed'
           AND actor_user_id = {$adminId}"
    )->fetchColumn();

    $metadata = \Barama\Core\Installation::metadata();

    if (
        $authVersion !== 1
        || $recordedVersion !== APP_VERSION
        || ($metadata['version'] ?? '') !== APP_VERSION
        || ($metadata['previous_version'] ?? '') !== '0.9.3-dev'
        || $audit !== 1
    ) {
        throw new \RuntimeException(
            'Post-upgrade schema, metadata, or audit state is incomplete.'
        );
    }

    $second = $graceUpgrade->upgrade([
        'admin_email' => 'upgrade-admin@example.test',
        'admin_password' => $adminPassword,
        'backup_confirmed' => '1',
    ]);

    if (empty($second['already_current']) || !empty($second['migrations'])) {
        throw new \RuntimeException(
            'A second upgrade run was not a clean no-op.'
        );
    }

    \Barama\Core\Installation::removeIncompleteState();

    if (is_file($installationIdPath)) {
        @unlink($installationIdPath);
    }

    return [
        'previous_version' => '0.9.3-dev',
        'version' => APP_VERSION,
        'migrations' => $expectedPendingMigrations,
        'database' => $databaseName,
    ];
}
