<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = (string) file_get_contents($root . '/application/resources/views/layouts/app.php');
$script = (string) file_get_contents($root . '/assets/js/frontend-back-to-top-v100.js');

$checks = [
    'frontend loader present' => str_contains($layout, "frontend-back-to-top-v100.js"),
    'workspace loader excluded' => str_contains($layout, "if (!\$isWorkspacePage)"),
    'frontend root guard' => str_contains($script, "barama-public-root"),
    'button hook' => str_contains($script, "data-back-to-top"),
    'scroll threshold' => str_contains($script, "const threshold = 420"),
    'smooth scroll' => str_contains($script, "window.scrollTo"),
    'reduced motion' => str_contains($script, "prefers-reduced-motion"),
    'dark mode styling' => str_contains($script, 'html[data-bs-theme="dark"] .barama-back-to-top'),
    'mobile safe area' => str_contains($script, 'env(safe-area-inset-bottom)'),
    'accessible label' => str_contains($script, "aria-label', 'Back to top"),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo $label . ': ' . ($passed ? 'PASS' : 'FAIL') . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'FAILED: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "FRONTEND_BACK_TO_TOP_CONTRACT: PASS\n";
