<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/Installer/Setup.php';

use Barama\Installer\Setup;

$root = Setup::root();
$public = realpath($root.'/public');
if ($public === false) {
    throw new RuntimeException('public/ directory is missing');
}
$assert = static function (bool $condition, string $description): void {
    if (!$condition) {
        throw new RuntimeException('Root route failure: '.$description);
    }
};

// Preferred Laravel document root requires no rewrite marker.
$assert(Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => $public,
    'REQUEST_URI' => '/installer/',
]), 'Correct public/ document root was rejected');

// Fail closed if the cPanel root bridge is missing or has no rewrite env.
$assert(!Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => $root,
    'REQUEST_URI' => '/installer/',
]), 'Project root without bridge proof was accepted');

// Reject attempts to access the installer by exposing /public as a URL.
$assert(!Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => $root,
    'REQUEST_URI' => '/public/installer/',
    'REDIRECT_BARAMA_PROTECTED_ROOT_BRIDGE' => '1',
]), 'Direct public/ URL was accepted');

// A verified root bridge can present /installer without bypassing the guard.
$assert(Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => $root,
    'REQUEST_URI' => '/installer/',
    'REDIRECT_BARAMA_PROTECTED_ROOT_BRIDGE' => '1',
]), 'Protected root bridge was rejected');

$assert(Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => $root,
    'REQUEST_URI' => '/installer?step=1',
    'BARAMA_PROTECTED_ROOT_BRIDGE' => '1',
]), 'Root bridge without trailing slash was rejected');

$assert(!Setup::documentRootAllowed([
    'DOCUMENT_ROOT' => '/tmp',
    'REQUEST_URI' => '/installer/',
    'BARAMA_PROTECTED_ROOT_BRIDGE' => '1',
]), 'Unrelated webroot accepted');

$htaccess = file_get_contents($root.'/.htaccess');
foreach ([
    'Options -Indexes',
    'RewriteRule ^(?:app|bootstrap|config|database|docs|Installer|resources|routes|scripts|storage|tests|vendor)',
    'RewriteRule ^$ public/index.php',
    'RewriteRule ^installer/?$ public/installer/index.php',
] as $mustExist) {
    $assert(str_contains((string) $htaccess, $mustExist),
        'Missing safe root routing directive: '.$mustExist);
}
echo "ROOT_INSTALLER_ROUTING_PASS\n";
