<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$layout = file_get_contents($root . '/application/resources/views/workspace/system-center.php');
$view = file_get_contents($root . '/application/resources/views/workspace/auto-updater-simple.php');
$css = file_get_contents($root . '/assets/css/barama-auto-updater-simple-v130.css');
$controller = file_get_contents($root . '/application/app/Http/Controllers/SystemCenterController.php');
$feed = file_get_contents($root . '/application/app/Services/Updates/TrustedReleaseFeed.php');
if (!is_string($layout) || !is_string($view) || !is_string($css)
    || !is_string($controller) || !is_string($feed)) {
    fwrite(STDERR, "FAIL Missing updater contract source\n");
    exit(1);
}
$assert = static function (bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS ' . $label . PHP_EOL;
};
$assert(str_contains($layout, "if (\$systemSection === 'auto-updater') {\n    require __DIR__ . '/auto-updater-simple.php';\n    return;"),
    'Dedicated Auto Updater view is the effective branch');
$assert(str_contains($view, 'barama-auto-updater-simple-v130.css'),
    'Auto Updater has one dedicated external stylesheet');
$assert(substr_count($view, '/dashboard/system/auto-updater/check-updates') === 1
    && substr_count($view, '<form method="post"') === 1,
    'One primary update check form');
$assert(str_contains($view, 'Csrf::token()')
    && str_contains($controller, 'TrustedReleaseFeed::check('),
    'Existing CSRF and signed feed check preserved');
$assert(str_contains($view, 'Update now</button>')
    && str_contains($view, 'type="button" disabled'),
    'Unimplemented installation remains disabled');
$assert(!str_contains($view, '/license/v2/update/artifact')
    && !str_contains($view, 'PrivateArtifactHttpClientV2')
    && !str_contains($view, 'download('),
    'No customer UI bypass into CLI-only artifact downloader');
$assert(str_contains($view, 'https://tonsamith.com/contact')
    && str_contains($view, '/updater/'),
    'Help and existing guarded manual upgrade available');
$assert(str_contains($view, 'ineligible')
    && str_contains($view, 'eligible')
    && str_contains($view, 'available')
    && str_contains($view, 'unavailable'),
    'Safe publisher result states handled');
$assert(str_contains($view, 'never downloads or installs')
    && str_contains($view, 'Automatic installation is not enabled yet'),
    'No misleading one-click installation claims');
$assert(!str_contains($view, '<style') && !preg_match('/\sstyle\s*=/', $view),
    'No inline styles');
$assert(str_contains($css, 'var(--barama-surface')
    && str_contains($css, 'var(--barama-text')
    && str_contains($css, '@media (max-width:650px)'),
    'Theme-aware responsive stylesheet');
$assert(str_contains($css, ':focus-visible')
    && str_contains($view, 'aria-live="polite"'),
    'Keyboard and status accessibility preserved');
$assert(str_contains($controller, "'available' =>")
    && str_contains($controller, "'eligible' =>")
    && str_contains($controller, "'ineligible' =>"),
    'Customer status stays server-authoritative');
$assert(str_contains($feed, "['entitlement_denied', 'entitlement_not_configured']"),
    'Publisher entitlement rejection still fail-closed');
echo 'AUTO_UPDATER_SIMPLE_THEME_CONTRACT=PASS' . PHP_EOL;
