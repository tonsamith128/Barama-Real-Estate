<?php

declare(strict_types=1);

use Barama\Core\Installation;
use Barama\Services\InstallerService;
use PDO;
use RuntimeException;
use ZipArchive;

$zipPath = $argv[1] ?? '';

if ($zipPath === '' || !is_file($zipPath) || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('Exact customer ZIP is unavailable for package QA.');
}

$temporaryRoot = sys_get_temp_dir() . '/barama-package-qa-' . bin2hex(random_bytes(6));
mkdir($temporaryRoot, 0775, true);

$zip = new ZipArchive();

if ($zip->open($zipPath) !== true) {
    throw new RuntimeException('Customer package could not be opened.');
}

$entries = [];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);

    if (is_string($name)) {
        $entries[] = str_replace('\\', '/', $name);
    }
}

foreach ([
    '.github/',
    '.env',
    '.env.example',
    'docs/',
    'tools/qa.php',
    'tools/migrate.php',
    'tools/schema-smoke.php',
    'tools/domain-write-smoke.php',
    'tools/notification-smoke.php',
] as $forbidden) {
    foreach ($entries as $entry) {
        if ($entry === $forbidden || str_starts_with($entry, $forbidden)) {
            throw new RuntimeException('Customer package contains internal source material: ' . $entry);
        }
    }
}

foreach ([
    'index.php',
    'installer/index.php',
    'application/bootstrap/runtime.php',
    'application/app/Services/InstallerService.php',
    'application/app/Services/LicenseManagerClient.php',
    'application/resources/views/installer/index.php',
    'assets/css/app.css',
    'assets/js/app.js',
    'tools/notifications.php',
    'LICENSE.md',
    'VERSION',
] as $required) {
    if (!in_array($required, $entries, true)) {
        throw new RuntimeException('Customer package is missing: ' . $required);
    }
}

foreach ($entries as $entry) {
    if (
        str_contains($entry, '/installed.php')
        || str_contains($entry, '/environment.php')
        || str_contains($entry, '/license.php')
        || str_contains($entry, '/installation-id.php')
        || preg_match('/\.(?:log|sql|bak|backup)$/i', $entry) === 1
    ) {
        throw new RuntimeException('Customer package contains runtime/private state: ' . $entry);
    }
}

if (!$zip->extractTo($temporaryRoot)) {
    throw new RuntimeException('Customer package could not be extracted.');
}

$zip->close();

$css = glob($temporaryRoot . '/assets/css/*.css') ?: [];
$js = glob($temporaryRoot . '/assets/js/*.js') ?: [];

if (
    count($css) !== 1
    || basename((string) $css[0]) !== 'app.css'
    || count($js) !== 1
    || basename((string) $js[0]) !== 'app.js'
) {
    throw new RuntimeException('Canonical customer assets are not clean.');
}

$viewSource = (string) file_get_contents(
    $temporaryRoot . '/application/resources/views/installer/index.php'
);

foreach ([
    'License & Terms',
    'Requirements',
    'Database',
    'Website',
    'System Health / Finish',
] as $visibleStep) {
    if (!str_contains($viewSource, $visibleStep)) {
        throw new RuntimeException('Installer visible step is missing: ' . $visibleStep);
    }
}

if (
    preg_match_all('/^\s*[1-5]\s*=>\s*\[/m', $viewSource) !== 5
    || !str_contains($viewSource, 'data-open-terms')
    || !str_contains($viewSource, 'data-accept-terms')
    || !str_contains($viewSource, 'data-license-validate')
    || !str_contains($viewSource, 'data-generate-password')
    || !str_contains($viewSource, 'name="license_method"')
    || !str_contains($viewSource, 'value="direct"')
    || !str_contains($viewSource, 'value="envato"')
    || !str_contains($viewSource, 'action="<?= e($installerUrl) ?>"')
    || !str_contains($viewSource, 'data-theme-toggle')
) {
    throw new RuntimeException('Five-step installer controls are incomplete.');
}

$stylesheetSource = (string) file_get_contents($temporaryRoot . '/assets/css/app.css');
$javascriptSource = (string) file_get_contents($temporaryRoot . '/assets/js/app.js');

if (
    !str_contains($stylesheetSource, '@media (max-width: 991.98px)')
    || !str_contains($stylesheetSource, '@media (max-width: 575.98px)')
    || !str_contains($javascriptSource, 'window.localStorage.setItem(storageKey, next)')
) {
    throw new RuntimeException('Responsive or Light/Dark installer behavior is incomplete.');
}

$rootIndex = (string) file_get_contents($temporaryRoot . '/index.php');
$controllerSource = (string) file_get_contents(
    $temporaryRoot . '/application/app/Http/Controllers/InstallerController.php'
);

if (
    !str_contains($rootIndex, '/installer/')
    || !str_contains($rootIndex, 'Cache-Control: no-store')
    || !str_contains($controllerSource, 'Csrf::validate')
    || !str_contains($controllerSource, 'agree_terms')
) {
    throw new RuntimeException('Installer redirect or server-side protection is incomplete.');
}

$_SERVER['SCRIPT_NAME'] = '/barama/installer/index.php';
$_SERVER['HTTP_HOST'] = 'barama.test';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

require $temporaryRoot . '/application/bootstrap/runtime.php';

$service = new InstallerService();

if (
    $service->basePath() !== '/barama'
    || $service->installerUrl() !== '/barama/installer/'
    || $service->detectedSiteUrl() !== 'https://barama.test/barama'
    || !$service->requirementsReady()
) {
    throw new RuntimeException('Extracted package URL or requirement checks failed.');
}

$dbHost = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
$dbPort = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
$dbName = (string) (getenv('PACKAGE_DB_DATABASE') ?: 'barama_package_test');
$dbUser = (string) (getenv('PACKAGE_DB_USERNAME') ?: 'root');
$dbPassword = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');

$badDatabaseRejected = false;

try {
    $service->testDatabase([
        'db_host' => $dbHost,
        'db_port' => $dbPort,
        'db_database' => $dbName,
        'db_username' => $dbUser,
        'db_password' => $dbPassword . '-incorrect',
        'create_database' => '1',
    ]);
} catch (RuntimeException) {
    $badDatabaseRejected = true;
}

if (!$badDatabaseRejected) {
    throw new RuntimeException('Invalid database credentials were not rejected.');
}

$database = $service->testDatabase([
    'db_host' => $dbHost,
    'db_port' => $dbPort,
    'db_database' => $dbName,
    'db_username' => $dbUser,
    'db_password' => $dbPassword,
    'create_database' => '1',
]);

$installationId = Installation::installationId();

$license = [
    'activation_token' => 'package-qa-activation',
    'license_id' => 'package-qa',
    'product_slug' => 'barama-real-estate',
    'license_type' => 'qa',
    'source' => 'package-qa',
    'is_test' => true,
    'status' => 'active',
    'domain' => 'barama.test',
    'installation_id' => $installationId,
    'verification_method' => 'envato',
    'validated_at' => gmdate('c'),
];

$health = $service->install(
    $database,
    [
        'site_name' => 'Barama Package QA',
        'site_url' => 'https://barama.test/barama',
        'timezone' => 'Asia/Phnom_Penh',
        'default_language' => 'en',
        'admin_name' => 'Package QA Administrator',
        'admin_email' => 'package-qa@example.test',
        'admin_password' => 'Barama!Package2026Strong',
        'admin_password_confirmation' => 'Barama!Package2026Strong',
    ],
    $license
);

if (empty($health['healthy']) || !Installation::isInstalled()) {
    throw new RuntimeException('Clean installation from the exact customer ZIP failed.');
}

$runtime = Installation::runtimeConfig();
$licenseState = Installation::licenseState();

if (
    ($runtime['APP_ENV'] ?? null) !== 'production'
    || ($runtime['APP_DEBUG'] ?? true) !== false
    || ($runtime['APP_URL'] ?? '') !== 'https://barama.test/barama'
    || ($licenseState['verification_method'] ?? '') !== 'envato'
    || isset($licenseState['purchase_code'], $licenseState['license_key'])
) {
    throw new RuntimeException('Production runtime or protected license state is invalid.');
}

$pdo = new PDO(
    'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

if ($service->runMigrations($pdo) !== []) {
    throw new RuntimeException('Migration replay after installation was not a no-op.');
}

$administratorCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM users
     INNER JOIN user_roles ON user_roles.user_id = users.id
     INNER JOIN roles ON roles.id = user_roles.role_id
     WHERE users.email = 'package-qa@example.test'
       AND users.status = 'active'
       AND roles.slug = 'administrator'"
)->fetchColumn();

if ($administratorCount !== 1) {
    throw new RuntimeException('First administrator was not provisioned correctly.');
}

$secondInstallBlocked = false;

try {
    $service->install($database, [], $license);
} catch (RuntimeException) {
    $secondInstallBlocked = true;
}

if (!$secondInstallBlocked) {
    throw new RuntimeException('Installation lock did not block a second installation.');
}

fwrite(
    STDOUT,
    '[PASS] Exact customer ZIP verified: structure, five steps, bad database recovery, '
    . 'subdirectory URLs, clean MySQL installation, production state, migration replay, '
    . 'administrator provisioning and installation lock.'
    . PHP_EOL
);
