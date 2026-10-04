<?php

declare(strict_types=1);

$seoTitle = trim((string) ($pageTitle ?? 'System Status'));
$seoDescription = trim((string) ($metaDescription ?? 'Current service status.'));
$seoCanonical = trim((string) ($canonicalUrl ?? url('/health')));
$seoRobots = trim((string) ($metaRobots ?? 'noindex,follow'));

$appCssFile = BASE_PATH . '/assets/css/app.css';
$appJsFile = BASE_PATH . '/assets/js/app.js';
$statusCssFile = BASE_PATH . '/assets/css/system-health-v1126.css';
$statusThemeFile = BASE_PATH . '/assets/js/system-health-theme-v1126.js';

$appCssVersion = is_file($appCssFile)
    ? (string) (filemtime($appCssFile) ?: APP_VERSION)
    : APP_VERSION;
$appJsVersion = is_file($appJsFile)
    ? (string) (filemtime($appJsFile) ?: APP_VERSION)
    : APP_VERSION;
$statusCssVersion = is_file($statusCssFile)
    ? (string) (filemtime($statusCssFile) ?: 1126)
    : '1126';
$statusThemeVersion = is_file($statusThemeFile)
    ? (string) (filemtime($statusThemeFile) ?: 1126)
    : '1126';
?>
<!doctype html>
<html lang="<?= e(app_locale()) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <script src="<?= e(asset('js/system-health-theme-v1126.js') . '?v=' . rawurlencode($statusThemeVersion)) ?>"></script>
    <link rel="icon" href="<?= e(asset('images/branding/favicon-32x32.png')) ?>" sizes="32x32" type="image/png">
    <link rel="shortcut icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/branding/apple-touch-icon.png')) ?>">
    <title><?= e($seoTitle) ?></title>
    <meta name="description" content="<?= e($seoDescription) ?>">
    <meta name="robots" content="<?= e($seoRobots) ?>">
    <link rel="canonical" href="<?= e($seoCanonical) ?>">
    <link href="<?= e(asset('css/app.css') . '?v=' . rawurlencode($appCssVersion)) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/system-health-v1126.css') . '?v=' . rawurlencode($statusCssVersion)) ?>" rel="stylesheet">
</head>
<body class="system-status-body">
    <header class="system-status-header">
        <div class="system-status-container system-status-header__inner">
            <a class="system-status-brand" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama Real Estate home')) ?>">
                <img
                    src="<?= e(asset('images/branding/barama-logo-header.png')) ?>"
                    alt="<?= e(t('Barama Real Estate')) ?>"
                    width="960"
                    height="220"
                >
            </a>
            <div class="system-status-header__actions">
                <a class="system-status-home-link" href="<?= e(url('/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.2 12 4l9 7.2v8.3a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <span>Back to Barama</span>
                </a>
                <button class="system-status-theme-toggle" type="button" data-theme-toggle aria-label="Switch appearance">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.64 5.64l1.42 1.42m9.88 9.88 1.42 1.42m0-12.72-1.42 1.42M7.06 16.94l-1.42 1.42" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                </button>
            </div>
        </div>
    </header>

    <?= $content ?>

    <footer class="system-status-footer">
        <div class="system-status-container system-status-footer__inner">
            <span>&copy; <span data-current-year><?= e(date('Y')) ?></span> Barama Real Estate</span>
            <a href="<?= e(url('/contact')) ?>">Contact support</a>
        </div>
    </footer>

    <script src="<?= e(asset('js/app.js') . '?v=' . rawurlencode($appJsVersion)) ?>"></script>
</body>
</html>
