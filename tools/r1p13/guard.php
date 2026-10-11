<?php

declare(strict_types=1);

/** R1p13: repository-source sandbox gate. NEVER use with archived production files. */
if (PHP_SAPI !== 'cli' ||
    getenv('BARAMA_R1P13_ISOLATED') !== '1' ||
    getenv('PACKAGE_DB_HOST') === false ||
    preg_match('/^barama-r1p13-db-[a-z0-9-]+$/D', (string) getenv('PACKAGE_DB_HOST')) !== 1 ||
    getenv('PACKAGE_DB_PORT') !== '3306' ||
    getenv('PACKAGE_DB_USERNAME') !== 'root' ||
    getenv('PACKAGE_DB_PASSWORD') === false ||
    strlen((string) getenv('PACKAGE_DB_PASSWORD')) < 16 ||
    getenv('MAIL_DRIVER') !== 'log' ||
    getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "STOP=R1P13_SANDBOX_ENVIRONMENT_UNSAFE\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
if (!is_file($root . '/VERSION') ||
    !is_file($root . '/application/bootstrap/runtime.php') ||
    !is_file($root . '/tools/browser-package-prepare.php')) {
    fwrite(STDERR, "STOP=R1P13_APPLICATION_SOURCE_MISSING\n");
    exit(1);
}

foreach ([
    'application/storage/app/installation/environment.php',
    'application/storage/app/installation/installed.php',
    'application/storage/app/license/license.php',
    'application/storage/app/license/installation-id.php',
] as $relative) {
    if (file_exists($root . '/' . $relative) || is_link($root . '/' . $relative)) {
        fwrite(STDERR, "STOP=R1P13_PREEXISTING_RUNTIME_STATE\n");
        exit(1);
    }
}

echo "R1P13_REPO_SOURCE_CONFIG_GUARD=PASS\n";
echo "PRODUCTION_ENVIRONMENT_LOADED=NO\n";
echo "EXISTING_CUSTOMER_STATE_IMPORTED=NO\n";
