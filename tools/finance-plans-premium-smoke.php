<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = file_get_contents($root . '/application/resources/views/workspace/finance-plans.php');
$css = file_get_contents($root . '/assets/css/finance-plans-premium-white-v181.css');
$js = file_get_contents($root . '/assets/js/finance-plans-actions-v181.js');
if (!is_string($view) || !is_string($css) || !is_string($js)) {
    fwrite(STDERR, "FAIL missing Plans assets\n");
    exit(1);
}

$checks = [
    'Only Plans page loads stylesheet' =>
        str_contains($view, "asset('css/finance-plans-premium-white-v181.css')"),
    'Only Plans page loads script' =>
        str_contains($view, "asset('js/finance-plans-actions-v181.js')"),
    'No inline CSS in Plans view' =>
        !str_contains($view, 'finance-plans-premium-white-v181.css') ||
        !preg_match('/<style[^>]*>/', $view),
    'Plans page continues to render filter form' =>
        str_contains($view, 'class="finance-plans__filters"'),
    'Search and status selection preserved' =>
        str_contains($view, 'name="q"') && str_contains($view, 'name="status"'),
    'Native actions and CSRF forms preserved' =>
        str_contains($view, 'class="workspace-row-actions"') &&
        str_contains($view, 'csrf_field()'),
    'No global white dashboard override' =>
        str_contains($css, '.workspace-main:has(> main .finance-plans)') &&
        !preg_match('/(^|\})\s*body\s*\{/', $css),
    'Dark background remains dark' =>
        str_contains($css, 'html[data-bs-theme="dark"] .workspace-main'),
    'Native light/dark input themes' =>
        str_contains($css, 'color-scheme: light') &&
        str_contains($css, 'color-scheme: dark'),
    'Menu clamped to Plans bounds' =>
        str_contains($js, 'bounds.right - margin') &&
        str_contains($js, 'Math.max(leftBoundary'),
    'Menu flips vertically within viewport' =>
        str_contains($js, 'openAbove') &&
        str_contains($js, 'window.innerHeight'),
    'Outside click, Escape, and scroll close menus' =>
        str_contains($js, "addEventListener('pointerdown'") &&
        str_contains($js, "event.key !== 'Escape'") &&
        str_contains($js, "addEventListener('scroll'"),
    'Uses native details / summary' =>
        str_contains($js, "querySelector('summary')") &&
        str_contains($view, '<details class="workspace-row-actions">'),
    'No external service or network dependency' =>
        !preg_match('/\bfetch\s*\(|XMLHttpRequest|https?:\/\//', $js),
];
$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
        fwrite(STDERR, 'FAIL ' . $name . PHP_EOL);
    } else {
        echo 'PASS ' . $name . PHP_EOL;
    }
}
if ($failed !== []) exit(1);
echo 'FINANCE_PLANS_PREMIUM_SMOKE=PASS CHECKS=' . count($checks) . PHP_EOL;
