<?php

declare(strict_types=1);

/**
 * Reject retired partial QA overlays and misleading production UI copy.
 * This lightweight source guard complements the Laravel feature tests.
 */
$root = dirname(__DIR__);
$errors = [];

$retired = [
    'build-appearance-upgrade.sh',
    'build-catalog-moderation-upgrade.sh',
    'build-cpanel-postinstall-upgrade.sh',
    'build-customer-dashboard-upgrade.sh',
    'build-dashboard-viewing-upgrade.sh',
    'build-installer-routing-hotfix.sh',
    'build-media-library-upgrade.sh',
    'build-policy-center-upgrade.sh',
    'verify-cpanel-upgrade-assets.py',
];

foreach ($retired as $name) {
    if (is_file($root.'/scripts/'.$name)) {
        $errors[] = 'Retired partial release script: '.$name;
    }
}

$workflowFile = $root.'/.github/workflows/release-acceptance.yml';
if (is_file($workflowFile)) {
    // Source checkout: enforce the historical manual release-workflow guard.
    $workflow = (string) file_get_contents($workflowFile);
    if (!str_contains($workflow, 'bash scripts/build-qa-package.sh')
        || str_contains($workflow, 'scripts/build-installer-routing-hotfix.sh')
        || preg_match('~dist/Barama-Laravel-[^\\s]*-Upgrade\\.zip~', $workflow)) {
        $errors[] = 'Manual release workflow must build only the integrated QA archive.';
    }
} elseif (is_file($root.'/QA_BUILD_INFO.txt') && !is_dir($root.'/.github')) {
    // Integrated QA archives intentionally omit .github and include an
    // explicit package marker. Keep every other UI guard active.
    fwrite(STDOUT, "RELEASE_WORKFLOW_GUARD=SOURCE_ONLY_QA_PACKAGE_MARKED\\n");
} else {
    // Never allow an unmarked source tree to bypass the workflow policy.
    $errors[] = 'Missing release workflow outside an identified QA package.';
}

$templates = [
    'resources/views/admin/dashboard/index.blade.php',
    'resources/views/components/layouts/admin.blade.php',
    'resources/views/frontend/home/index.blade.php',
    'resources/views/admin/appearance/index.blade.php',
    'resources/views/admin/policies/index.blade.php',
];
$forbidden = [
    'demo content importer', 'demo importer',
    'this is a laravel development build',
    'remain under migration', 'still require migration',
    'not included in this module', 'lorem ipsum', 'coming soon',
];
foreach ($templates as $path) {
    $content = @file_get_contents($root.'/'.$path);
    if (!is_string($content)) {
        $errors[] = 'Missing production template: '.$path;
        continue;
    }
    foreach ($forbidden as $phrase) {
        if (stripos($content, $phrase) !== false) {
            $errors[] = 'Misleading production UI copy ('.$phrase.'): '.$path;
        }
    }
}
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
fwrite(STDOUT, "LARAVEL_RELEASE_UI_HYGIENE_PASS\n");
