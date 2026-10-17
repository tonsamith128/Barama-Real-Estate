<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root . '/application/resources/views/workspace/finance-plans.php');
$css = file_get_contents($root . '/assets/css/finance-plans-modal-theme-v182.css');
if (!is_string($view) || !is_string($css)) {
    fwrite(STDERR, "FAIL modal assets absent\n");
    exit(1);
}

$checks = [
    'New CSS loaded by Plans view once' =>
        substr_count($view, "asset('css/finance-plans-modal-theme-v182.css')") === 1,
    'Existing PR76 styles preserved' =>
        str_contains($view, "asset('css/finance-plans-premium-white-v181.css')"),
    'Create/Edit plan controls unmodified' =>
        str_contains($view, 'data-plan-modal') &&
        str_contains($view, 'data-plan-form') &&
        str_contains($view, 'data-plan-edit=') &&
        str_contains($view, 'data-plan-open'),
    'Submission authorization remains in original form' =>
        str_contains($view, 'csrf_field()') &&
        str_contains($view, 'data-workspace-form'),
    'Modal stylesheet scoped to modal selectors' =>
        !preg_match('/(^|\})\s*(body|\.workspace-main|\.finance-plans__table)\s*\{/', $css),
    'Light modal background and text' =>
        str_contains($css, '--pm-surface: #ffffff') &&
        str_contains($css, '--pm-text: #17243b'),
    'Dark modal background and text' =>
        str_contains($css, 'html[data-bs-theme="dark"] .finance-plan-modal') &&
        str_contains($css, '--pm-text: #f0f5fd'),
    'Header and subtitle legible' =>
        str_contains($css, '.finance-plan-modal__dialog h2') &&
        str_contains($css, '.finance-plan-modal__dialog header p'),
    'Presets and labels readable in both themes' =>
        str_contains($css, '.finance-plan-modal__presets button') &&
        str_contains($css, '.finance-plan-form label > span'),
    'Inputs and dropdown options themed' =>
        str_contains($css, 'select.form-select option') &&
        str_contains($css, 'color-scheme: dark') &&
        str_contains($css, 'input.form-control'),
    'Notice, buttons and focus rings visible' =>
        str_contains($css, '.finance-plan-form__notice') &&
        str_contains($css, '.finance-plan-form footer .btn') &&
        str_contains($css, ':focus-visible'),
    'Mobile dialog scrolling supported' =>
        str_contains($css, '100dvh') &&
        str_contains($css, 'overflow-y: auto'),
    'No global or inline stylesheet changes' =>
        !preg_match('/<style\b/', $view),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, 'FAIL ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS ' . $label . PHP_EOL;
}
echo 'FINANCE_PLANS_MODAL_THEME=PASS CHECKS=' . count($checks) . PHP_EOL;
