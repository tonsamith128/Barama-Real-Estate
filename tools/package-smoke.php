<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Installation;
use Barama\Core\RuntimeEnvironment;
use Barama\Services\InstallerService;
use Barama\Services\LicenseManagerClient;
use Barama\Support\MigrationCatalog;

$packagePath = $argv[1] ?? '';
if ($packagePath === '' || !is_file($packagePath)) {
    throw new RuntimeException('A valid customer package ZIP is required.');
}

$temporaryRoot = sys_get_temp_dir()
    . '/barama-package-smoke-'
    . bin2hex(random_bytes(6));

if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException('Unable to create package smoke directory.');
}

$cleanup = static function (string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
};

try {
    $archive = new ZipArchive();
    if ($archive->open($packagePath) !== true) {
        throw new RuntimeException('Unable to open customer package ZIP.');
    }

    if (!$archive->extractTo($temporaryRoot)) {
        $archive->close();
        throw new RuntimeException('Unable to extract customer package ZIP.');
    }
    $archive->close();

    $requiredFiles = [
        '.htaccess',
        'index.php',
        'VERSION',
        'LICENSE.md',
        'THIRD_PARTY_NOTICES.md',
        'CUSTOMER_PACKAGE.md',
        'CHANGELOG.md',
        'documentation/index.html',
        'installer/index.php',
        'documentation/NGINX_CLEAN_URLS.md',
        'updater/index.php',
        'application/bootstrap/app.php',
        'application/bootstrap/runtime.php',
        'application/routes/web.php',
        'application/config/app.php',
        'application/config/auth.php',
        'application/config/database.php',
        'application/config/domain.php',
        'application/config/license.php',
        'application/config/mail.php',
        'application/app/Core/Installation.php',
        'application/app/Core/RuntimeEnvironment.php',
        'application/app/Services/InstallerService.php',
        'application/app/Services/UpgradeService.php',
        'application/app/Services/LicenseManagerClient.php',
        'application/app/Support/MigrationCatalog.php',
        'application/resources/views/installer/index.php',
        'application/resources/views/updater/index.php',
        'assets/css/app.css',
        'assets/js/app.js',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($temporaryRoot . '/' . $relative)) {
            throw new RuntimeException(
                'Package missing required file: ' . $relative
            );
        }
    }

    $migrationFiles = glob(
        $temporaryRoot . '/application/database/migrations/*.php'
    ) ?: [];
    $migrationNames = array_map('basename', $migrationFiles);
    sort($migrationNames, SORT_STRING);

    $expectedMigrations = require __DIR__ . '/pinned-migration-names.php';

    if ($migrationNames !== $expectedMigrations) {
        throw new RuntimeException(
            'Package migration inventory does not match the canonical catalog.'
        );
    }

    $cssFiles = glob($temporaryRoot . '/assets/css/*.css') ?: [];
    $jsFiles = glob($temporaryRoot . '/assets/js/*.js') ?: [];
    $cssNames = array_map(
        static fn (string $path): string => 'assets/css/' . basename($path),
        $cssFiles
    );
    $jsNames = array_map(
        static fn (string $path): string => 'assets/js/' . basename($path),
        $jsFiles
    );
    sort($cssNames, SORT_STRING);
    sort($jsNames, SORT_STRING);

    // The repository source tree, not a retired hand-maintained shortlist,
    // is the canonical baseline for the *exact* customer installation ZIP.
    $sourceRoot = dirname(__DIR__);
    $approvedCssFiles = array_map(
        static fn (string $file): string => 'assets/css/' . basename($file),
        glob($sourceRoot . '/assets/css/*.css') ?: []
    );
    $approvedJsFiles = array_map(
        static fn (string $file): string => 'assets/js/' . basename($file),
        glob($sourceRoot . '/assets/js/*.js') ?: []
    );
    sort($approvedCssFiles, SORT_STRING);
    sort($approvedJsFiles, SORT_STRING);

    $unapprovedCssFiles = array_values(array_diff($cssNames, $approvedCssFiles));
    $unapprovedJsFiles = array_values(array_diff($jsNames, $approvedJsFiles));
    $missingCssFiles = array_values(array_diff($approvedCssFiles, $cssNames));
    $missingJsFiles = array_values(array_diff($approvedJsFiles, $jsNames));

    if (
        !in_array('assets/css/app.css', $cssNames, true)
        || $unapprovedCssFiles !== []
        || $missingCssFiles !== []
    ) {
        throw new RuntimeException(
            'Package CSS inventory contains missing core or unapproved modular assets.'
        );
    }

    if (
        !in_array('assets/js/app.js', $jsNames, true)
        || $unapprovedJsFiles !== []
        || $missingJsFiles !== []
    ) {
        throw new RuntimeException(
            'Package JavaScript inventory contains missing core or unapproved modular assets.'
        );
    }

    $database = (string) (getenv('PACKAGE_DB_DATABASE') ?: 'barama_package_test');
    $host = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
    $username = (string) (getenv('PACKAGE_DB_USERNAME') ?: 'root');
    $password = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
    $pdo->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    $pdo->exec(
        'CREATE DATABASE '
        . $quotedDatabase
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $envPath = $temporaryRoot . '/.env';
    $installedPath = $temporaryRoot . '/storage/installed.json';
    $runtimePath = $temporaryRoot . '/storage/runtime';
    $logsPath = $temporaryRoot . '/storage/logs';
    $backupsPath = $temporaryRoot . '/storage/backups';
    $updatesPath = $temporaryRoot . '/storage/updates';
    $bootstrapCache = $temporaryRoot . '/application/bootstrap/cache';

    foreach (
        [
            dirname($installedPath),
            $runtimePath,
            $logsPath,
            $backupsPath,
            $updatesPath,
            $bootstrapCache,
        ] as $directory
    ) {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $temporaryRoot);
    }

    require $temporaryRoot . '/application/bootstrap/app.php';

    $viewSource = (string) file_get_contents(
        $temporaryRoot . '/application/resources/views/installer/index.php'
    );

    if (
        preg_match_all('/^\s*[1-5]\s*=>\s*\[/m', $viewSource) !== 5
        || str_contains($viewSource, 'data-open-terms')
        || !str_contains($viewSource, 'data-terms-agreement')
        || !str_contains($viewSource, 'data-accept-terms')
        || !str_contains($viewSource, 'data-license-validate')
        || !str_contains($viewSource, 'data-generate-password')
        || str_contains($viewSource, 'data-mail-test')
        || str_contains($viewSource, 'name="mail_driver"')
        || str_contains($viewSource, 'name="mail_host"')
        || str_contains($viewSource, 'name="mail_password"')
        || !str_contains(
            $viewSource,
            'Email delivery defaults to PHP mail and can be configured later from the application settings.'
        )
        || str_contains($viewSource, 'name="license_method"')
        || str_contains($viewSource, 'data-license-method')
        || str_contains($viewSource, 'Ton Samith License')
        || !str_contains($viewSource, 'Purchase code / license key')
        || !str_contains($viewSource, 'Envato Market License &amp; Terms')
        || !str_contains($viewSource, 'Support from the Author')
        || !str_contains(
            $viewSource,
            'I agree to the Envato license terms, author disclaimer and support terms.'
        )
        || str_contains($viewSource, "<?= \$termsReviewed ? 'checked' : '' ?>")
        || !str_contains($viewSource, 'action="<?= e($installerUrl) ?>"')
        || !str_contains($viewSource, 'data-theme-toggle')
        || !str_contains($viewSource, 'data-installer-language')
        || !str_contains($viewSource, '🇰🇭')
        || !str_contains($viewSource, 'installer-requirements-table')
        || !str_contains($viewSource, 'name="db_mode"')
        || !str_contains($viewSource, 'Existing Database')
        || !str_contains($viewSource, 'Create Database')
        || !str_contains($viewSource, 'Database Server Admin Access')
        || !str_contains($viewSource, 'name="db_admin_username"')
        || !str_contains($viewSource, 'name="db_new_database"')
        || !str_contains($viewSource, 'name="db_new_password_confirmation"')
        || !str_contains($viewSource, 'installer-form__grid--pair')
        || !str_contains($viewSource, 'data-1p-ignore')
        || str_contains($viewSource, 'name="create_database"')
    ) {
        throw new RuntimeException('Five-step installer controls are incomplete.');
    }

    $stylesheetSource = (string) file_get_contents($temporaryRoot . '/assets/css/app.css');
    $javascriptSource = (string) file_get_contents($temporaryRoot . '/assets/js/app.js');

    if (
        !str_contains($stylesheetSource, '@media (max-width: 991.98px)')
        || !str_contains($stylesheetSource, '@media (max-width: 575.98px)')
        || !str_contains($stylesheetSource, ':focus-visible')
        || !str_contains($javascriptSource, 'window.localStorage.setItem(storageKey, next)')
        || !str_contains($javascriptSource, "workspaceOpen?.setAttribute('aria-expanded', 'false')")
        || !str_contains($javascriptSource, "workspaceOpen.setAttribute('aria-expanded', 'true')")
        || !str_contains($stylesheetSource, '.installer-requirements-table')
        || !str_contains($stylesheetSource, '.installer-database-mode')
        || !str_contains($stylesheetSource, '.installer-form__grid--pair')
        || !str_contains($stylesheetSource, 'background: transparent')
        || !str_contains($javascriptSource, '[data-database-mode]')
        || !str_contains($javascriptSource, 'Create Database & Continue')
        || !str_contains($javascriptSource, 'barama-installer-language')
        || str_contains($javascriptSource, 'Send a successful test email before installation.')
        || str_contains($javascriptSource, '[data-mail-test]')
    ) {
        throw new RuntimeException('Responsive or Light/Dark installer behavior is incomplete.');
    }

    $layoutSource = (string) file_get_contents(
        $temporaryRoot . '/application/resources/views/layouts/app.php'
    );
    if (
        !str_contains($layoutSource, 'data-bs-theme="light"')
        || str_contains($layoutSource, 'data-bs-theme="dark"')
    ) {
        throw new RuntimeException('Default application theme must be Light.');
    }

    $licenseClient = new LicenseManagerClient();
    $licenseConfig = $licenseClient->configuration();
    if (
        ($licenseConfig['host'] ?? '') !== 'https://tonsamith.com'
        || ($licenseConfig['product'] ?? '') !== 'barama-real-estate'
        || ($licenseConfig['api_key'] ?? null) !== null
    ) {
        throw new RuntimeException(
            'Customer package License Manager configuration is unsafe.'
        );
    }

    $requirements = (new InstallerService())->requirements();
    $requiredLabels = array_column($requirements, 'label');
    foreach (
        [
            'PHP 8.2 or newer',
            'PDO MySQL',
            'Writable runtime',
            'Core CSS asset',
            'Core JavaScript asset',
        ] as $requiredLabel
    ) {
        if (!in_array($requiredLabel, $requiredLabels, true)) {
            throw new RuntimeException(
                'Package installer requirement missing: ' . $requiredLabel
            );
        }
    }

    $databaseConfig = [
        'driver' => 'mysql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8mb4',
    ];

    $installer = new InstallerService();
    $installer->writeEnvironment([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'http://localhost',
        'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
        'DB_HOST' => $host,
        'DB_PORT' => (string) $port,
        'DB_DATABASE' => $database,
        'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password,
        'MAIL_DRIVER' => 'mail',
        'MAIL_HOST' => '',
        'MAIL_PORT' => '587',
        'MAIL_USERNAME' => '',
        'MAIL_PASSWORD' => '',
        'MAIL_ENCRYPTION' => 'tls',
        'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
        'MAIL_FROM_NAME' => 'Barama Real Estate',
    ]);

    $environment = RuntimeEnvironment::read($envPath);
    if (
        ($environment['APP_ENV'] ?? '') !== 'production'
        || ($environment['APP_DEBUG'] ?? '') !== 'false'
        || ($environment['MAIL_DRIVER'] ?? '') !== 'mail'
    ) {
        throw new RuntimeException('Package environment write failed.');
    }

    Database::reset();
    $installer->testDatabase($databaseConfig);
    $installer->migrate($databaseConfig);

    $pdo = Database::connection();
    $migrationRows = $pdo
        ->query('SELECT migration FROM schema_migrations ORDER BY migration')
        ->fetchAll(PDO::FETCH_COLUMN);
    if ($migrationRows !== $expectedMigrations) {
        throw new RuntimeException(
            'Package migrations did not apply the canonical catalog.'
        );
    }

    $health = $installer->health();
    if (empty($health['healthy'])) {
        throw new RuntimeException('Package installer health check failed.');
    }

    fwrite(
        STDOUT,
        "[PASS] Exact customer package installer, migrations and health verified.\n"
    );
} finally {
    $cleanup($temporaryRoot);
}
