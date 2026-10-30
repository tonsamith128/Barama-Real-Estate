<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/Installer/Setup.php';

use Barama\Installer\Setup;

function assertInstaller(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function failsInstaller(callable $callable): bool
{
    try {
        $callable();
    } catch (RuntimeException $e) {
        return true;
    }
    return false;
}

$db = ['host'=>'127.0.0.1','port'=>'3306','database'=>'barama_test','username'=>'barama','password'=>'P@ss:word#?='];
Setup::validateDatabase($db);
assertInstaller(failsInstaller(fn () => Setup::validateDatabase([...$db, 'host'=>'localhost;port=1'])), 'Host injection accepted');
assertInstaller(failsInstaller(fn () => Setup::validateDatabase([...$db, 'database'=>'legacy/database'])), 'Unsafe database name accepted');
assertInstaller(failsInstaller(fn () => Setup::validateApplication('https://user@example.com')), 'Credential-bearing URL accepted');
assertInstaller(failsInstaller(fn () => Setup::validateApplication('http://example.com')), 'Insecure production URL accepted');
assertInstaller(Setup::validateApplication('https://example.com') === 'https://example.com', 'Valid URL rejected');
assertInstaller(failsInstaller(fn () => Setup::validateAdministrator([
    'name'=>'User', 'email'=>'test@example.com', 'password'=>'weak', 'password_confirmation'=>'weak',
])), 'Weak administrator password accepted');

$admin = [
    'name'=>'Primary Administrator', 'email'=>'owner@example.com',
    'password'=>'a-long-passphrase-value', 'password_confirmation'=>'a-long-passphrase-value',
];
Setup::validateAdministrator($admin);
$env = Setup::environment($db, 'https://example.com', base64_encode(random_bytes(32)));
assertInstaller(str_contains($env, 'APP_DEBUG="false"'), 'Debug mode not disabled');
assertInstaller(str_contains($env, 'DB_PASSWORD='), 'Database password missing');
assertInstaller(!str_contains($env, 'APP_KEY=""'), 'Application key is empty');
assertInstaller(!str_contains($env, 'APP_ENV="local"'), 'Local environment configured');

if (is_file(dirname(__DIR__, 2).'/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2).'/vendor/autoload.php';
    $variables = \Dotenv\Dotenv::parse($env);
    assertInstaller(($variables['DB_PASSWORD'] ?? null) === $db['password'], 'Dotenv password roundtrip failed');
    $db['password'] = 'dollar$cash\backslash"quotation#hash';
    $variables = \Dotenv\Dotenv::parse(Setup::environment($db, 'https://example.com', base64_encode(random_bytes(32))));
    assertInstaller(($variables['DB_PASSWORD'] ?? null) === $db['password'], 'Special-character password roundtrip failed');
}
echo "INSTALLER_UNIT_PASS\n";
