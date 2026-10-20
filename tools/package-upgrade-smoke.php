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
    'application/app/Services/Updates/OneClickDashboardGateV1.php',
    'application/app/Services/Updates/OneClickInspectionFreshnessV1.php',
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

// Prevent a submission build that ships the new dashboard without the actual
// fail-closed install gate or signed-inspection freshness protection.
$contracts = [
    'application/resources/views/workspace/system-center.php' => [
        'data-oneclick-install-disabled',
        'One-click Update workflow',
        'Inspection expired or package changed',
    ],
    'application/app/Http/Controllers/SystemCenterController.php' => [
        'OneClickDashboardGateV1::assess',
        'OneClickInspectionFreshnessV1::display',
    ],
    'application/resources/views/updater/index.php' => [
        'Return to Update Center',
        '/dashboard/system/auto-updater',
    ],
];
foreach ($contracts as $relative => $markers) {
    $contents = file_get_contents($temporaryRoot . '/' . $relative);
    if (!is_string($contents)) {
        throw new RuntimeException('Packaged updater security contract unavailable: ' . $relative);
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            throw new RuntimeException('Packaged updater contract missing: ' . $relative . ':' . $marker);
        }
    }
}
fwrite(STDOUT, '[PASS] Packaged One-click dashboard remains fail-closed; signed inspection is freshness-bound.' . PHP_EOL);

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
