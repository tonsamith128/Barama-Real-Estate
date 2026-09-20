<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Installation;
use Barama\Support\IdGenerator;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class InstallerService
{
    private const SUPPORTED_LANGUAGES = [
        'en' => 'English',
    ];

    /** @return array<int,array{label:string,detail:string,status:string,required:bool}> */
    public function requirements(): array
    {
        $checks = [
            $this->requiredCheck(
                'PHP 8.2 or newer',
                version_compare(PHP_VERSION, '8.2.0', '>='),
                'Detected PHP ' . PHP_VERSION . '.'
            ),
        ];

        foreach ([
            'pdo' => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'json' => 'JSON',
            'mbstring' => 'Mbstring',
            'ctype' => 'Ctype',
            'session' => 'Session',
            'openssl' => 'OpenSSL',
            'fileinfo' => 'Fileinfo',
            'curl' => 'cURL',
        ] as $extension => $label) {
            $checks[] = $this->requiredCheck(
                $label . ' extension',
                extension_loaded($extension),
                'Required by Barama Real Estate.'
            );
        }

        foreach ([
            'index.php',
            'VERSION',
            '.htaccess',
            'application/bootstrap/app.php',
            'application/bootstrap/runtime.php',
            'application/routes/web.php',
            'assets/css/app.css',
            'assets/js/app.js',
        ] as $relative) {
            $checks[] = $this->requiredCheck(
                'Required file: ' . $relative,
                is_file(BASE_PATH . '/' . $relative)
                    && is_readable(BASE_PATH . '/' . $relative),
                $relative
            );
        }

        try {
            foreach (Installation::ensureRuntimeDirectories() as $directory) {
                $checks[] = $this->requiredCheck(
                    'Writable runtime: ' . $this->relative($directory),
                    is_writable($directory),
                    $this->relative($directory)
                );
            }
        } catch (Throwable) {
            $checks[] = $this->requiredCheck(
                'Writable runtime storage',
                false,
                'The application/storage directory must be writable by PHP.'
            );
        }

        $css = glob(BASE_PATH . '/assets/css/*.css') ?: [];
        $js = glob(BASE_PATH . '/assets/js/*.js') ?: [];

        $checks[] = $this->requiredCheck(
            'Canonical stylesheet',
            count($css) === 1 && basename((string) ($css[0] ?? '')) === 'app.css',
            'assets/css/app.css'
        );
        $checks[] = $this->requiredCheck(
            'Canonical JavaScript',
            count($js) === 1 && basename((string) ($js[0] ?? '')) === 'app.js',
            'assets/js/app.js'
        );

        $checks[] = [
            'label' => 'HTTPS connection',
            'detail' => is_https()
                ? 'Secure HTTPS connection detected.'
                : 'HTTPS is recommended before using the live website.',
            'status' => is_https() ? 'pass' : 'warning',
            'required' => false,
        ];

        return $checks;
    }

    public function requirementsReady(): bool
    {
        foreach ($this->requirements() as $check) {
            if ($check['required'] && $check['status'] !== 'pass') {
                return false;
            }
        }

        return true;
    }

    public function basePath(): string
    {
        $script = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '/installer/index.php')
        );
        $suffix = '/installer/index.php';

        if (str_ends_with($script, $suffix)) {
            $base = substr($script, 0, -strlen($suffix));
        } else {
            $base = dirname(dirname($script));
        }

        $base = '/' . trim((string) $base, '/');

        return $base === '/' ? '' : $base;
    }

    public function installerUrl(string $query = ''): string
    {
        $url = $this->basePath() . '/installer/';

        return $query !== '' ? $url . '?' . ltrim($query, '?') : $url;
    }

    public function detectedSiteUrl(): string
    {
        $scheme = is_https() ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return $scheme . '://' . $host . $this->basePath();
    }

    public function detectedDomain(): string
    {
        return LicenseManagerClient::canonicalDomain(
            (string) ($_SERVER['HTTP_HOST'] ?? '')
        );
    }

    /** @return array<string,string> */
    public function supportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /** @return array<int,string> */
    public function commonTimezones(): array
    {
        return [
            'Asia/Phnom_Penh',
            'Asia/Bangkok',
            'Asia/Ho_Chi_Minh',
            'Asia/Singapore',
            'Asia/Kuala_Lumpur',
            'Asia/Jakarta',
            'Asia/Manila',
            'Asia/Hong_Kong',
            'Asia/Shanghai',
            'Asia/Tokyo',
            'Asia/Seoul',
            'Asia/Kolkata',
            'Asia/Dubai',
            'Europe/London',
            'Europe/Paris',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'Australia/Sydney',
            'UTC',
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function testDatabase(array $input): array
    {
        $database = $this->validateDatabaseInput($input);

        if ($database['create_database']) {
            $server = $this->connect(
                $database['host'],
                $database['port'],
                null,
                $database['username'],
                $database['password']
            );
            $this->assertSupportedDatabase($server);

            $tick = chr(96);
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS '
                . $tick . $database['database'] . $tick
                . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        }

        $connection = $this->connect(
            $database['host'],
            $database['port'],
            $database['database'],
            $database['username'],
            $database['password']
        );

        $this->assertSupportedDatabase($connection);
        $this->assertDatabaseSuitable($connection);

        return $database;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function validateWebsite(array $input, string $licensedDomain): array
    {
        $siteName = trim((string) ($input['site_name'] ?? ''));
        $siteUrl = rtrim(trim((string) ($input['site_url'] ?? '')), '/');
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $language = trim((string) ($input['default_language'] ?? 'en'));
        $adminName = trim((string) ($input['admin_name'] ?? ''));
        $adminEmail = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $password = (string) ($input['admin_password'] ?? '');
        $confirmation = (string) ($input['admin_password_confirmation'] ?? '');

        if (mb_strlen($siteName) < 2 || mb_strlen($siteName) > 120) {
            throw new RuntimeException(
                'Enter a website name between 2 and 120 characters.'
            );
        }

        if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(
                'Enter a valid website URL beginning with http:// or https://.'
            );
        }

        $scheme = strtolower((string) parse_url($siteUrl, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Website URL must begin with http:// or https://.'
            );
        }

        if (
            parse_url($siteUrl, PHP_URL_QUERY) !== null
            || parse_url($siteUrl, PHP_URL_FRAGMENT) !== null
        ) {
            throw new RuntimeException(
                'Website URL must not contain a query string or fragment.'
            );
        }

        $siteDomain = LicenseManagerClient::canonicalDomain($siteUrl);

        if (
            $siteDomain === ''
            || $siteDomain
                !== LicenseManagerClient::canonicalDomain($licensedDomain)
        ) {
            throw new RuntimeException(
                'Website URL must use the domain validated in Step 1.'
            );
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new RuntimeException('Choose a valid timezone.');
        }

        if (!isset(self::SUPPORTED_LANGUAGES[$language])) {
            throw new RuntimeException('Choose a supported default language.');
        }

        if (mb_strlen($adminName) < 2 || mb_strlen($adminName) > 120) {
            throw new RuntimeException('Enter the administrator name.');
        }

        if (
            !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
            || mb_strlen($adminEmail) > 191
        ) {
            throw new RuntimeException(
                'Enter a valid administrator email address.'
            );
        }

        if (strlen($password) < 12 || strlen($password) > 255) {
            throw new RuntimeException(
                'Administrator password must contain at least 12 characters.'
            );
        }

        if (
            preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/\\d/', $password) !== 1
            || preg_match('/[^A-Za-z0-9]/', $password) !== 1
        ) {
            throw new RuntimeException(
                'Administrator password must include uppercase, lowercase, number, and symbol characters.'
            );
        }

        if (!hash_equals($password, $confirmation)) {
            throw new RuntimeException(
                'Administrator password confirmation does not match.'
            );
        }

        return [
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'timezone' => $timezone,
            'default_language' => $language,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $password,
        ];
    }

    /**
     * @param array<string,mixed> $database
     * @param array<string,mixed> $website
     * @param array<string,mixed> $license
     * @return array<string,mixed>
     */
    public function install(
        array $database,
        array $website,
        array $license
    ): array {
        if (Installation::isInstalled()) {
            throw new RuntimeException(
                'Barama Real Estate is already installed.'
            );
        }

        if (!$this->requirementsReady()) {
            throw new RuntimeException(
                'Resolve the required server checks before installation.'
            );
        }

        $this->assertLicenseState($license);
        $database = $this->testDatabase($database);
        $website = $this->validateWebsite(
            $website,
            (string) $license['domain']
        );

        $pdo = $this->connect(
            $database['host'],
            $database['port'],
            $database['database'],
            $database['username'],
            $database['password']
        );

        $migrations = $this->runMigrations($pdo);
        $installationId = Installation::installationId();
        $appKey = base64_encode(random_bytes(32));

        try {
            $pdo->beginTransaction();

            $adminId = $this->provisionAdministrator($pdo, $website);
            $this->saveSettings($pdo, $website);

            Installation::writeRuntimeConfig([
                'APP_NAME' => $website['site_name'],
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
                'APP_URL' => $website['site_url'],
                'APP_TIMEZONE' => $website['timezone'],
                'APP_LOCALE' => $website['default_language'],
                'APP_KEY' => $appKey,
                'SESSION_NAME' => 'barama_session',
                'DB_HOST' => $database['host'],
                'DB_PORT' => $database['port'],
                'DB_DATABASE' => $database['database'],
                'DB_USERNAME' => $database['username'],
                'DB_PASSWORD' => $database['password'],
                'MAIL_DRIVER' => 'mail',
                'MAIL_FROM_ADDRESS' => $website['admin_email'],
                'MAIL_FROM_NAME' => $website['site_name'],
                'MAIL_HOST' => '',
                'MAIL_PORT' => 587,
                'MAIL_ENCRYPTION' => 'tls',
                'MAIL_USERNAME' => '',
                'MAIL_PASSWORD' => '',
                'MAIL_TIMEOUT' => 15,
                'MAIL_MAX_ATTEMPTS' => 5,
                'MAIL_RETRY_MINUTES' => 10,
                'MAIL_PROCESSING_STALE_MINUTES' => 15,
            ]);

            Installation::writeLicenseState([
                'activation_token' => (string) $license['activation_token'],
                'license_id' => $license['license_id'] ?? null,
                'product_slug' => (string) $license['product_slug'],
                'envato_item_id' => $license['envato_item_id'] ?? null,
                'license_type' => (string) ($license['license_type'] ?? ''),
                'source' => (string) ($license['source'] ?? ''),
                'is_test' => (bool) ($license['is_test'] ?? false),
                'status' => (string) ($license['status'] ?? 'active'),
                'domain' => (string) $license['domain'],
                'installation_id' => $installationId,
                'verification_method' => (string) (
                    $license['verification_method'] ?? 'envato'
                ),
                'validated_at' => (string) (
                    $license['validated_at'] ?? gmdate('c')
                ),
                'last_validated_at' => gmdate('c'),
            ]);

            $this->assertFinalDatabaseState(
                $pdo,
                $adminId,
                $website
            );

            Installation::markInstalled([
                'installation_id' => $installationId,
                'administrator_user_id' => $adminId,
                'database' => $database['database'],
                'migrations_applied' => count($migrations),
                'product_slug' => (string) $license['product_slug'],
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Installation::removeIncompleteState();
            throw $exception;
        }

        return $this->health();
    }

    /** @return array<int,string> */
    public function runMigrations(PDO $pdo): array
    {
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

        $applied = $pdo->query(
            'SELECT migration FROM schema_migrations ORDER BY id'
        )->fetchAll(PDO::FETCH_COLUMN);

        $lookup = array_fill_keys(
            array_map('strval', $applied),
            true
        );

        $files = glob(APP_PATH . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);

        if ($files === []) {
            throw new RuntimeException('Database migrations are missing.');
        }

        $batch = (int) $pdo->query(
            'SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations'
        )->fetchColumn();

        $ran = [];

        foreach ($files as $file) {
            $name = basename($file);

            if (isset($lookup[$name])) {
                continue;
            }

            $migration = require $file;

            if (!is_callable($migration)) {
                throw new RuntimeException(
                    'A database migration is invalid.'
                );
            }

            $migration($pdo);

            $statement = $pdo->prepare(
                'INSERT INTO schema_migrations (migration, batch)
                 VALUES (:migration, :batch)'
            );
            $statement->execute([
                'migration' => $name,
                'batch' => $batch,
            ]);

            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * @return array{
     *   healthy:bool,
     *   checks:array<int,array{label:string,status:string,detail:string}>,
     *   site_url:string
     * }
     */
    public function health(): array
    {
        $checks = [];
        $runtime = Installation::runtimeConfig();
        $license = Installation::licenseState();
        $metadata = Installation::metadata();

        $checks[] = $this->healthCheck(
            'Runtime configuration',
            $runtime !== []
                && ($runtime['APP_ENV'] ?? null) === 'production'
                && ($runtime['APP_DEBUG'] ?? true) === false,
            'Production configuration is stored securely.'
        );

        $checks[] = $this->healthCheck(
            'License verification',
            trim((string) ($license['activation_token'] ?? '')) !== ''
                && ($license['product_slug'] ?? '')
                    === (string) config(
                        'license.product_slug',
                        'barama-real-estate'
                    )
                && in_array(
                    (string) ($license['verification_method'] ?? ''),
                    ['direct', 'envato'],
                    true
                ),
            'Purchase activation is bound to this installation.'
        );

        $checks[] = $this->healthCheck(
            'Installation lock',
            Installation::isInstalled()
                && ($metadata['installation_id'] ?? '')
                    === ($license['installation_id'] ?? ''),
            'The browser installer is sealed.'
        );

        $pdo = null;

        try {
            if ($runtime !== []) {
                $pdo = $this->connect(
                    (string) ($runtime['DB_HOST'] ?? ''),
                    (int) ($runtime['DB_PORT'] ?? 3306),
                    (string) ($runtime['DB_DATABASE'] ?? ''),
                    (string) ($runtime['DB_USERNAME'] ?? ''),
                    (string) ($runtime['DB_PASSWORD'] ?? '')
                );
            }
        } catch (Throwable) {
            $pdo = null;
        }

        $checks[] = $this->healthCheck(
            'Database connection',
            $pdo instanceof PDO,
            $pdo instanceof PDO
                ? 'Database connection is healthy.'
                : 'Database connection could not be verified.'
        );

        $schemaHealthy = false;
        $adminHealthy = false;
        $settingsHealthy = false;

        if ($pdo instanceof PDO) {
            $expected = glob(APP_PATH . '/database/migrations/*.php') ?: [];
            $applied = (int) $pdo->query(
                'SELECT COUNT(*) FROM schema_migrations'
            )->fetchColumn();

            $schemaHealthy = $applied === count($expected);

            $adminHealthy = (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM users
                 INNER JOIN user_roles
                    ON user_roles.user_id = users.id
                 INNER JOIN roles
                    ON roles.id = user_roles.role_id
                 WHERE roles.slug = 'administrator'
                   AND users.status = 'active'"
            )->fetchColumn() >= 1;

            $settingsHealthy = (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM application_settings
                 WHERE setting_key IN (
                    'site.name',
                    'site.url',
                    'site.timezone',
                    'site.default_language',
                    'installation.version'
                 )"
            )->fetchColumn() === 5;
        }

        $checks[] = $this->healthCheck(
            'Database schema',
            $schemaHealthy,
            'All ordered migrations are recorded.'
        );
        $checks[] = $this->healthCheck(
            'Administrator account',
            $adminHealthy,
            'The first administrator account is active.'
        );
        $checks[] = $this->healthCheck(
            'Website settings',
            $settingsHealthy,
            'Website settings are stored.'
        );

        $runtimeWritable = true;

        try {
            foreach (Installation::ensureRuntimeDirectories() as $directory) {
                if (!is_writable($directory)) {
                    $runtimeWritable = false;
                    break;
                }
            }
        } catch (Throwable) {
            $runtimeWritable = false;
        }

        $checks[] = $this->healthCheck(
            'Runtime folders',
            $runtimeWritable,
            'Required runtime folders are writable.'
        );

        $css = glob(BASE_PATH . '/assets/css/*.css') ?: [];
        $js = glob(BASE_PATH . '/assets/js/*.js') ?: [];

        $checks[] = $this->healthCheck(
            'Canonical stylesheet',
            count($css) === 1
                && basename((string) ($css[0] ?? '')) === 'app.css',
            'assets/css/app.css'
        );
        $checks[] = $this->healthCheck(
            'Canonical JavaScript',
            count($js) === 1
                && basename((string) ($js[0] ?? '')) === 'app.js',
            'assets/js/app.js'
        );

        $healthy = true;

        foreach ($checks as $check) {
            if ($check['status'] !== 'pass') {
                $healthy = false;
                break;
            }
        }

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'site_url' => rtrim(
                (string) (
                    $runtime['APP_URL']
                    ?? $this->detectedSiteUrl()
                ),
                '/'
            ),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validateDatabaseInput(array $input): array
    {
        $host = trim(
            (string) ($input['host'] ?? $input['db_host'] ?? '127.0.0.1')
        );
        $port = filter_var(
            $input['port'] ?? $input['db_port'] ?? 3306,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );
        $database = trim(
            (string) ($input['database'] ?? $input['db_database'] ?? '')
        );
        $username = trim(
            (string) ($input['username'] ?? $input['db_username'] ?? '')
        );
        $password = (string) (
            $input['password'] ?? $input['db_password'] ?? ''
        );

        if (
            $host === ''
            || mb_strlen($host) > 255
            || preg_match('/[\r\n]/', $host) === 1
        ) {
            throw new RuntimeException('Enter a valid database host.');
        }

        if ($port === false) {
            throw new RuntimeException('Enter a valid database port.');
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $database) !== 1) {
            throw new RuntimeException(
                'Database name may contain letters, numbers, underscores, and hyphens.'
            );
        }

        if (
            $username === ''
            || mb_strlen($username) > 191
            || preg_match('/[\r\n]/', $username) === 1
        ) {
            throw new RuntimeException(
                'Enter a valid database username.'
            );
        }

        return [
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'create_database' => !empty($input['create_database']),
        ];
    }

    private function connect(
        string $host,
        int $port,
        ?string $database,
        string $username,
        string $password
    ): PDO {
        $dsn = 'mysql:host=' . $host
            . ';port=' . $port
            . ';charset=utf8mb4';

        if ($database !== null && $database !== '') {
            $dsn .= ';dbname=' . $database;
        }

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Database connection failed. Check the host, port, database name, username, and password.',
                0,
                $exception
            );
        }
    }

    private function assertSupportedDatabase(PDO $pdo): void
    {
        $raw = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($raw, 'mariadb') !== false;
        $matches = [];

        if ($isMaria) {
            if (
                preg_match(
                    '/(\d+\.\d+(?:\.\d+)?)-MariaDB/i',
                    $raw,
                    $matches
                ) !== 1
            ) {
                throw new RuntimeException(
                    'The MariaDB server version could not be determined.'
                );
            }
        } elseif (
            preg_match(
                '/^(\d+\.\d+(?:\.\d+)?)/',
                $raw,
                $matches
            ) !== 1
        ) {
            throw new RuntimeException(
                'The MySQL server version could not be determined.'
            );
        }

        $minimum = $isMaria ? '10.6.0' : '8.0.0';
        $name = $isMaria ? 'MariaDB' : 'MySQL';

        if (!version_compare($matches[1], $minimum, '>=')) {
            throw new RuntimeException(
                $name . ' ' . $minimum . ' or newer is required.'
            );
        }
    }

    private function assertDatabaseSuitable(PDO $pdo): void
    {
        $tables = $pdo->query(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($tables === []) {
            return;
        }

        if (!in_array('schema_migrations', $tables, true)) {
            throw new RuntimeException(
                'The selected database is not empty. Choose an empty database for Barama Real Estate.'
            );
        }

        if (in_array('users', $tables, true)) {
            $users = (int) $pdo->query(
                'SELECT COUNT(*) FROM users'
            )->fetchColumn();

            if ($users > 0) {
                throw new RuntimeException(
                    'The selected database already contains user accounts. Choose a clean database.'
                );
            }
        }
    }

    /** @param array<string,mixed> $website */
    private function provisionAdministrator(
        PDO $pdo,
        array $website
    ): int {
        if (
            (int) $pdo->query(
                'SELECT COUNT(*) FROM users'
            )->fetchColumn() > 0
        ) {
            throw new RuntimeException(
                'The selected database already contains user accounts.'
            );
        }

        $passwordHash = password_hash(
            (string) $website['admin_password'],
            PASSWORD_DEFAULT
        );

        if (!is_string($passwordHash)) {
            throw new RuntimeException(
                'Administrator password could not be secured.'
            );
        }

        $statement = $pdo->prepare(
            'INSERT INTO users
                (uuid, name, email, password_hash, status, email_verified_at)
             VALUES
                (:uuid, :name, :email, :password_hash, "active", CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'name' => $website['admin_name'],
            'email' => $website['admin_email'],
            'password_hash' => $passwordHash,
        ]);

        $userId = (int) $pdo->lastInsertId();
        $roles = $pdo->query(
            "SELECT slug, id
             FROM roles
             WHERE slug IN ('user', 'administrator')"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!isset($roles['user'], $roles['administrator'])) {
            throw new RuntimeException(
                'Administrator roles are unavailable after database migration.'
            );
        }

        $assign = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        );

        foreach (['user', 'administrator'] as $slug) {
            $assign->execute([
                'user_id' => $userId,
                'role_id' => (int) $roles[$slug],
            ]);
        }

        return $userId;
    }

    /** @param array<string,mixed> $website */
    private function saveSettings(PDO $pdo, array $website): void
    {
        $settings = [
            'site.name' => $website['site_name'],
            'site.url' => $website['site_url'],
            'site.timezone' => $website['timezone'],
            'site.default_language' => $website['default_language'],
            'installation.version' => (
                defined('APP_VERSION')
                    ? APP_VERSION
                    : 'unknown'
            ),
        ];

        $statement = $pdo->prepare(
            'INSERT INTO application_settings
                (setting_key, setting_value, value_type)
             VALUES
                (:setting_key, :setting_value, "string")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type)'
        );

        foreach ($settings as $key => $value) {
            $statement->execute([
                'setting_key' => $key,
                'setting_value' => (string) $value,
            ]);
        }
    }

    /** @param array<string,mixed> $website */
    private function assertFinalDatabaseState(
        PDO $pdo,
        int $adminId,
        array $website
    ): void {
        $admin = $pdo->prepare(
            "SELECT COUNT(*)
             FROM user_roles
             INNER JOIN roles
                ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id
               AND roles.slug = 'administrator'"
        );
        $admin->execute(['user_id' => $adminId]);

        if ((int) $admin->fetchColumn() !== 1) {
            throw new RuntimeException(
                'Administrator account could not be finalized.'
            );
        }

        $setting = $pdo->query(
            'SELECT setting_value
             FROM application_settings
             WHERE setting_key = "site.url"
             LIMIT 1'
        );

        if (
            (string) $setting->fetchColumn()
            !== (string) $website['site_url']
        ) {
            throw new RuntimeException(
                'Website settings could not be finalized.'
            );
        }
    }

    /** @param array<string,mixed> $license */
    private function assertLicenseState(array $license): void
    {
        $expectedProduct = (string) config(
            'license.product_slug',
            'barama-real-estate'
        );

        if (
            trim((string) ($license['activation_token'] ?? '')) === ''
            || (string) ($license['product_slug'] ?? '')
                !== $expectedProduct
            || LicenseManagerClient::canonicalDomain(
                (string) ($license['domain'] ?? '')
            ) === ''
            || !in_array(
                (string) ($license['verification_method'] ?? ''),
                ['direct', 'envato'],
                true
            )
        ) {
            throw new RuntimeException(
                'Validate the purchase code before continuing.'
            );
        }

        if (
            strtolower((string) ($license['installation_id'] ?? ''))
            !== strtolower(Installation::installationId())
        ) {
            throw new RuntimeException(
                'The validated license does not belong to this installation.'
            );
        }
    }

    /** @return array{label:string,detail:string,status:string,required:bool} */
    private function requiredCheck(
        string $label,
        bool $passed,
        string $detail
    ): array {
        return [
            'label' => $label,
            'detail' => $detail,
            'status' => $passed ? 'pass' : 'fix',
            'required' => true,
        ];
    }

    /** @return array{label:string,status:string,detail:string} */
    private function healthCheck(
        string $label,
        bool $passed,
        string $detail
    ): array {
        return [
            'label' => $label,
            'status' => $passed ? 'pass' : 'fix',
            'detail' => $detail,
        ];
    }

    private function relative(string $path): string
    {
        $root = rtrim(
            str_replace('\\', '/', BASE_PATH),
            '/'
        );
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root . '/')
            ? substr($path, strlen($root) + 1)
            : $path;
    }
}
