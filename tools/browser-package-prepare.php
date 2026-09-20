<?php

declare(strict_types=1);

use Barama\Core\Installation;
use Barama\Services\InstallerService;
use Barama\Support\IdGenerator;

$root = rtrim((string) ($argv[1] ?? ''), '/');
$database = trim((string) ($argv[2] ?? ''));
$siteUrl = rtrim((string) ($argv[3] ?? ''), '/');

if (
    $root === ''
    || !is_file($root . '/application/bootstrap/runtime.php')
    || preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1
    || !str_starts_with($siteUrl, 'http://127.0.0.1:')
) {
    throw new RuntimeException('Browser QA package preparation arguments are invalid.');
}

define('BARAMA_CLI', true);

require $root . '/application/bootstrap/runtime.php';

$host = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
$username = (string) (getenv('PACKAGE_DB_USERNAME') ?: 'root');
$password = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$tick = chr(96);
$server->exec(
    "CREATE DATABASE {$tick}{$database}{$tick} "
    . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$migrations = (new InstallerService())->runMigrations($pdo);

if ($migrations === []) {
    throw new RuntimeException('Browser QA package migrations did not run.');
}

$passwordHash = password_hash(
    'Barama-Browser-QA-123!',
    PASSWORD_DEFAULT
);

if (!is_string($passwordHash)) {
    throw new RuntimeException('Browser QA password hash could not be created.');
}

$user = $pdo->prepare(
    'INSERT INTO users
        (uuid, name, email, password_hash, status, email_verified_at)
     VALUES
        (:uuid, :name, :email, :password_hash, "active", CURRENT_TIMESTAMP)'
);
$user->execute([
    'uuid' => IdGenerator::uuid(),
    'name' => 'Browser QA Administrator',
    'email' => 'browser-admin@example.test',
    'password_hash' => $passwordHash,
]);
$userId = (int) $pdo->lastInsertId();

$roles = $pdo->query(
    "SELECT slug, id
     FROM roles
     WHERE slug IN ('user', 'developer', 'administrator')"
)->fetchAll(PDO::FETCH_KEY_PAIR);

foreach (['user', 'developer', 'administrator'] as $slug) {
    if (!isset($roles[$slug])) {
        throw new RuntimeException(
            'Browser QA required role is missing: ' . $slug
        );
    }

    $assign = $pdo->prepare(
        'INSERT INTO user_roles (user_id, role_id)
         VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $userId,
        'role_id' => (int) $roles[$slug],
    ]);
}

$settings = [
    'site.name' => 'Barama Browser QA',
    'site.url' => $siteUrl,
    'site.timezone' => 'Asia/Phnom_Penh',
    'site.default_language' => 'en',
    'installation.version' => APP_VERSION,
];

$setting = $pdo->prepare(
    'INSERT INTO application_settings
        (setting_key, setting_value, value_type)
     VALUES
        (:setting_key, :setting_value, "string")
     ON DUPLICATE KEY UPDATE
        setting_value = VALUES(setting_value),
        value_type = VALUES(value_type)'
);

foreach ($settings as $key => $value) {
    $setting->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

Installation::removeIncompleteState();

$idPath = Installation::installationIdPath();

if (is_file($idPath)) {
    @unlink($idPath);
}

$installationId = Installation::installationId();

Installation::writeRuntimeConfig([
    'APP_NAME' => 'Barama Browser QA',
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,
    'APP_URL' => $siteUrl,
    'APP_TIMEZONE' => 'Asia/Phnom_Penh',
    'APP_LOCALE' => 'en',
    'APP_KEY' => base64_encode(random_bytes(32)),
    'SESSION_NAME' => 'barama_browser_qa',
    'DB_HOST' => $host,
    'DB_PORT' => $port,
    'DB_DATABASE' => $database,
    'DB_USERNAME' => $username,
    'DB_PASSWORD' => $password,
    'MAIL_DRIVER' => 'log',
    'MAIL_FROM_ADDRESS' => 'browser@example.test',
    'MAIL_FROM_NAME' => 'Barama Browser QA',
]);

Installation::writeLicenseState([
    'activation_token' => 'browser-qa-activation-token',
    'license_id' => 'browser-qa',
    'product_slug' => 'barama-real-estate',
    'license_type' => 'qa',
    'source' => 'qa',
    'is_test' => true,
    'status' => 'active',
    'domain' => '127.0.0.1',
    'installation_id' => $installationId,
    'verification_method' => 'direct',
    'validated_at' => gmdate('c'),
    'last_validated_at' => gmdate('c'),
]);

Installation::markInstalled([
    'version' => APP_VERSION,
    'installation_id' => $installationId,
    'administrator_user_id' => $userId,
    'database' => $database,
    'migrations_applied' => count($migrations),
    'product_slug' => 'barama-real-estate',
]);

fwrite(
    STDOUT,
    '[PASS] Browser QA installed package prepared at '
    . $siteUrl
    . ' with administrator user '
    . $userId
    . '.'
    . PHP_EOL
);
