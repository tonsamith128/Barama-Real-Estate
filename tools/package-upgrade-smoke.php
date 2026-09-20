<?php

declare(strict_types=1);

$zipPath = $argv[1] ?? '';

if ($zipPath === '' || !is_file($zipPath) || !class_exists(ZipArchive::class)) {
    throw new RuntimeException(
        'Exact customer ZIP is unavailable for upgrade QA.'
    );
}

$temporaryRoot = sys_get_temp_dir()
    . '/barama-package-upgrade-qa-'
    . bin2hex(random_bytes(6));

if (!mkdir($temporaryRoot, 0775, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException(
        'Upgrade QA extraction directory could not be created.'
    );
}

$zip = new ZipArchive();

if ($zip->open($zipPath) !== true) {
    throw new RuntimeException('Customer package could not be opened.');
}

foreach ([
    'updater/index.php',
    'application/app/Http/Controllers/UpgradeController.php',
    'application/app/Services/UpgradeService.php',
    'application/resources/views/updater/index.php',
    'application/database/migrations/0010_release_security_hardening.php',
] as $required) {
    if ($zip->locateName($required) === false) {
        throw new RuntimeException(
            'Customer package upgrade surface is missing: ' . $required
        );
    }
}

if (!$zip->extractTo($temporaryRoot)) {
    throw new RuntimeException('Customer package could not be extracted.');
}

$zip->close();

require __DIR__ . '/upgrade-smoke-runner.php';

$result = run_barama_upgrade_smoke(
    $temporaryRoot,
    'barama_upgrade_package_test'
);

fwrite(
    STDOUT,
    '[PASS] Exact customer ZIP upgrade verified: '
    . $result['previous_version']
    . ' -> '
    . $result['version']
    . ', packaged updater, migration, protected state and clean replay.'
    . PHP_EOL
);
