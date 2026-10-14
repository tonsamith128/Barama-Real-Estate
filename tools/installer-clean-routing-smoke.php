<?php

declare(strict_types=1);

define('BARAMA_CLI', true);
require dirname(__DIR__) . '/application/bootstrap/runtime.php';

use Barama\Services\InstallerService;

// Do not probe an external website: seed the private cached detection state
// and verify that the installer presents it as a mandatory preflight.
$installer = new InstallerService();
$property = (new ReflectionClass(InstallerService::class))->getProperty('detectedRoutingMode');

foreach ([
    'clean' => ['pass', true],
    'query' => ['fix', true],
] as $mode => [$expectedStatus, $expectedRequired]) {
    $property->setValue($installer, $mode);
    $items = array_values(array_filter(
        $installer->requirements(),
        static fn (array $check): bool =>
            ($check['label'] ?? '') === 'Application routing'
    ));

    if (count($items) !== 1) {
        throw new RuntimeException('Application routing must appear exactly once.');
    }

    $check = $items[0];
    if (
        ($check['status'] ?? null) !== $expectedStatus
        || ($check['required'] ?? null) !== $expectedRequired
    ) {
        throw new RuntimeException('Installer routing preflight failed for ' . $mode);
    }
}

// Only fresh installations are forced to clean mode. Keep the existing
// compatibility URL helper to avoid silently breaking legacy customers.
$source = file_get_contents(
    dirname(__DIR__) . '/application/app/Services/InstallerService.php'
);
if (
    !is_string($source)
    || !str_contains($source, "'APP_ROUTING_MODE' => 'clean'")
    || !str_contains($source, "if (\$this->routingMode() !== 'clean')")
    || str_contains($source, "'APP_ROUTING_MODE' => \$routingMode")
) {
    throw new RuntimeException('New installations must store only verified clean routing.');
}

$docs = dirname(__DIR__) . '/documentation/NGINX_CLEAN_URLS.md';
if (!is_file($docs) || !str_contains((string) file_get_contents($docs), 'try_files')) {
    throw new RuntimeException('Nginx clean URL customer setup guide is missing.');
}

echo "[PASS] New-install clean URL enforcement, legacy upgrade safety and Nginx guidance.\n";
