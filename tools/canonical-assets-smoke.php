<?php
declare(strict_types=1);

/**
 * CODECANYON_CANONICAL_ASSET_CONTRACT_V100
 * Prevent obsolete historical CSS/JS copies from returning to the
 * v1.0.0 customer package. Layered asset families are intentionally
 * not in this first, reference-audited migration wave.
 */
$root = dirname(__DIR__);
$assets = [
    'assets/css/admin-email-template-builder.css',
    'assets/css/admin-email-templates.css',
    'assets/css/admin-global-locations.css',
    'assets/css/admin-settings-security-factors.css',
    'assets/css/homepage-agent.css',
    'assets/css/media-library-upload.css',
    'assets/css/popular-location-marquee.css',
    'assets/css/public-location-picker.css',
    'assets/js/account-profile.js',
    'assets/js/admin-email-template-builder.js',
    'assets/js/admin-email-templates.js',
    'assets/js/admin-global-locations.js',
    'assets/js/admin-settings-security-factors.js',
    'assets/js/page-builder.js',
    'assets/js/public-location-picker.js',
];
$views = [
    'application/resources/views/workspace/email-template-builder.php' => [
        'css/admin-email-template-builder.css','js/admin-email-template-builder.js'],
    'application/resources/views/workspace/communications-email-templates.php' => [
        'css/admin-email-templates.css','js/admin-email-templates.js'],
    'application/resources/views/workspace/settings.php' => [
        'js/account-profile.js','css/admin-global-locations.css',
        'css/admin-settings-security-factors.css','js/admin-settings-security-factors.js'],
    'application/resources/views/workspace/settings-locations.php' => [
        'js/admin-global-locations.js'],
    'application/resources/views/workspace/page-builder.php' => [
        'js/page-builder.js'],
    'application/resources/views/components/public-location-picker.php' => [
        'css/public-location-picker.css','js/public-location-picker.js'],
    'application/resources/views/workspace/media-upload.php' => [
        'css/media-library-upload.css'],
    'application/resources/views/layouts/app.php' => [
        'css/popular-location-marquee.css'],
    'application/resources/views/home.php' => [
        'css/homepage-agent.css'],
];
$fail = static function (string $reason): never {
    fwrite(STDERR, "CANONICAL_ASSET_CONTRACT_FAIL: {$reason}\n");
    exit(1);
};
$legacyPatterns = [];
foreach ($assets as $asset) {
    $path = $root . '/' . $asset;
    if (!is_file($path) || is_link($path)) {
        $fail('Missing or symlinked canonical asset ' . $asset);
    }
    $ext = pathinfo($asset, PATHINFO_EXTENSION);
    $stem = substr($asset, 0, -strlen($ext) - 1);
    $pattern = $stem . '-v*.' . $ext;
    $legacyPatterns[] = $pattern;
    if (glob($root . '/' . $pattern) !== []) {
        $fail('Historical duplicates remain: ' . $pattern);
    }
}
foreach ($views as $view => $references) {
    $path = $root . '/' . $view;
    if (!is_file($path)) {
        $fail('Missing asset-loading view: ' . $view);
    }
    $source = (string) file_get_contents($path);
    foreach ($references as $relativeAsset) {
        if (!str_contains($source, "asset('" . $relativeAsset . "')")) {
            $fail('View does not load canonical asset ' . $view . ': ' . $relativeAsset);
        }
    }
    foreach ($legacyPatterns as $pattern) {
        $legacyName = basename($pattern);
        $prefix = substr($legacyName, 0, strpos($legacyName, '-v*'));
        if (preg_match('/' . preg_quote($prefix, '/') . '-v[0-9][a-z0-9]*\\.(?:css|js)/i', $source)) {
            $fail('View references superseded asset ' . $view . ': ' . $prefix);
        }
    }
}
if (isset($argv[1])) {
    $zip = new ZipArchive();
    if ($zip->open((string)$argv[1]) !== true) {
        $fail('Cannot inspect customer ZIP');
    }
    $names = [];
    for ($i=0; $i<$zip->numFiles; ++$i) {
        $names[$zip->getNameIndex($i)] = true;
    }
    foreach ($assets as $asset) {
        if (!isset($names[$asset])) {
            $fail('Customer ZIP missing canonical asset: ' . $asset);
        }
    }
    foreach (array_keys($names) as $name) {
        foreach ($legacyPatterns as $pattern) {
            $prefix = substr($pattern, 0, strpos($pattern, '-v*'));
            $ext = pathinfo($pattern, PATHINFO_EXTENSION);
            if (preg_match('/^' . preg_quote($prefix, '/') . '-v[0-9][a-z0-9]*\\.' . preg_quote($ext, '/') . '$/i', $name)) {
                $fail('Customer ZIP includes superseded asset ' . $name);
            }
        }
    }
    $zip->close();
}
echo 'CANONICAL_ASSET_CONTRACT_V100=PASS assets=' . count($assets) .
     ' views=' . count($views) . PHP_EOL;
