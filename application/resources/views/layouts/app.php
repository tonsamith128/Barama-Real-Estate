<?php
declare(strict_types=1);

$headerUser = auth_user();
$isAuthenticated = $headerUser !== null;
$isAdministrator = $isAuthenticated && auth()->hasRole('administrator');
$currentLocale = app_locale();
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$localeReturn = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
$appBasePath = (string) (parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: '');
$appBasePath = '/' . trim($appBasePath, '/');

if ($appBasePath !== '/' && str_starts_with($localeReturn, $appBasePath)) {
    $localeReturn = substr($localeReturn, strlen($appBasePath)) ?: '/';
}

$queryRoutePath = trim((string) ($_GET['_route'] ?? ''), '/');
$authRoutePath = $queryRoutePath !== ''
    ? '/' . $queryRoutePath
    : '/' . trim($localeReturn, '/');
$authRoutePath = (string) (
    parse_url($authRoutePath, PHP_URL_PATH) ?: '/'
);

if ($authRoutePath === '') {
    $authRoutePath = '/';
}

$isStandaloneAuthPage = in_array(
    $authRoutePath,
    ['/login', '/register', '/forgot-password', '/verify-email', '/mfa-challenge'],
    true
) || str_starts_with($authRoutePath, '/reset-password/')
    || str_starts_with($authRoutePath, '/verify-email/');

$queryString = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');

if ($queryString !== '') {
    $localeReturn .= '?' . $queryString;
}
/* BARAMA_WORKSPACE_HEADER_V100 */
/* BARAMA_WORKSPACE_BOUNDARY_HEADER_V100 */
$isWorkspacePage = isset($workspaceNavigation) && is_array($workspaceNavigation);

$publicContentProtection = [
    'enabled' => false,
    'block_context_menu' => false,
    'block_image_drag' => false,
    'block_text_selection' => false,
];
if (!$isWorkspacePage && !$isStandaloneAuthPage) {
    try {
        $publicContentProtection = (new \Barama\Services\SecurityProtectionService())
            ->publicContentProtection();
    } catch (\Throwable) {
        $publicContentProtection = [
            'enabled' => false,
            'block_context_menu' => false,
            'block_image_drag' => false,
            'block_text_selection' => false,
        ];
    }
}

$changeConfirmationRequired = false;
if ($isWorkspacePage && $isAuthenticated) {
    try {
        $changeConfirmationRequired = (new \Barama\Services\SecurityFactorService())
            ->passcodeEnabled((int) $headerUser['id']);
    } catch (\Throwable) {
        $changeConfirmationRequired = false;
    }
}

$frontendAppearance = null;
$frontendAppearanceVersion = '';
$frontendAppearanceSkin = 'modern';

if (!$isWorkspacePage) {
    try {
        $appearanceRepository = new \Barama\Repositories\AppearanceRepository();
        $frontendAppearance = $appearanceRepository->get();
        $frontendAppearanceVersion = \Barama\Repositories\AppearanceRepository::version(
            $frontendAppearance
        );
        $frontendAppearanceSkin = (new \Barama\Services\AppearanceManagementService())
            ->skinName($frontendAppearance);
    } catch (\Throwable) {
        $frontendAppearance = \Barama\Repositories\AppearanceRepository::defaults();
        $frontendAppearanceVersion = \Barama\Repositories\AppearanceRepository::version(
            $frontendAppearance
        );
    }
}

$publicNavigation = (!$isStandaloneAuthPage && !$isWorkspacePage)
    ? (new \Barama\Repositories\SiteContentRepository())->navigation()
    : [];

// Content-only footer settings remain separate from Appearance's footer style.
$footerContent = \Barama\Repositories\FooterContentRepository::defaults();
if (!$isWorkspacePage && !$isStandaloneAuthPage) {
    try {
        $footerContent = (new \Barama\Repositories\FooterContentRepository())->get();
    } catch (\Throwable $exception) {
        error_log('[Barama Footer] Unable to read footer configuration.');
    }
}
/* BARAMA_SHARED_PROFILE_AVATAR_V107: one owner-scoped avatar source for every signed-in header. */
$headerAvatarUuid = '';
if ($isAuthenticated && isset($headerUser['id'])) {
    try {
        $headerAvatarProfile = (new \Barama\Repositories\AccountSettingsRepository())->profile((int) $headerUser['id']);
        $headerAvatarUuid = trim((string) ($headerAvatarProfile['avatar_uuid'] ?? ''));
    } catch (\Throwable $exception) {
        error_log('[Barama Avatar] Header avatar lookup failed.');
    }
}
$workspaceHeaderUserName = trim((string) ($headerUser['name'] ?? ''));
$workspaceHeaderUserName = $workspaceHeaderUserName !== '' ? $workspaceHeaderUserName : t('My account');
$workspaceHeaderUserEmail = trim((string) ($headerUser['email'] ?? ''));
$workspaceHeaderArea = (string) ($activeArea ?? '');
$workspaceHeaderRole = match ($workspaceHeaderArea) {
    'dashboard' => t('Administrator'),
    'developer' => t('Company / Vendor'),
    default => t('Customer'),
};
$workspaceHeaderLabel = (string) ($workspaceLabel ?? match ($workspaceHeaderArea) {
    'dashboard' => t('Admin Control Center'),
    'developer' => t('Company / Vendor Workspace'),
    default => t('Customer Account'),
});

/* BARAMA_SHARED_NOTIFICATION_BELL_V111: current user's unread records only. */
$headerUnreadNotifications = 0;
if ($isAuthenticated) {
    try {
        $headerUnreadNotifications = max(0, (new \Barama\Repositories\NotificationRepository())
            ->unreadCount((int) $headerUser['id']));
    } catch (\Throwable $exception) {
        // A missing/temporarily unavailable notification store must not break navigation.
        error_log('[Barama Notifications] Header count unavailable.');
    }
}
$headerNotificationUrl = match ($workspaceHeaderArea) {
    'dashboard' => '/dashboard/notifications',
    'developer' => '/developer/notifications',
    default => '/account/notifications',
};
$headerNotificationsBadge = $headerUnreadNotifications > 99 ? '99+' : (string) $headerUnreadNotifications;
$headerUnreadItems = [];
if ($isAuthenticated && $headerUnreadNotifications > 0) {
    try {
        $headerUnreadItems = (new \Barama\Repositories\NotificationRepository())
            ->unreadForUser((int)$headerUser['id'], 7);
    } catch (\Throwable) {
        error_log('[Barama Notifications] Header inbox unavailable.');
    }
}
$headerNotificationSource = $isWorkspacePage ? $workspaceHeaderArea : 'account';


/* BARAMA_ADMIN_DEFAULT_THEME_SETTINGS_V101 */
$workspaceDefaultTheme = '';
if ($isWorkspacePage && $workspaceHeaderArea === 'dashboard' && is_array($headerUser) && isset($headerUser['id'])) {
    try {
        $themePreferences = (new \Barama\Repositories\WorkspaceSettingsRepository())->get((int) $headerUser['id'], 'dashboard');
        $themeCandidate = strtolower(trim((string) ($themePreferences['default_theme'] ?? '')));
        if (in_array($themeCandidate, ['light', 'dark'], true)) $workspaceDefaultTheme = $themeCandidate;
    } catch (\Throwable) {
        $workspaceDefaultTheme = '';
    }
}

/* BARAMA_GLOBAL_DEFAULT_THEME_V103 */
if ($workspaceDefaultTheme === '') {
    try {
        $workspaceDefaultTheme = (new \Barama\Repositories\GlobalThemeRepository())->get();
    } catch (\Throwable) {
        $workspaceDefaultTheme = 'light';
    }
}

$websiteIdentity = [];
try { $websiteIdentity = (new \Barama\Repositories\WebsiteIdentityRepository())->get(); } catch (\Throwable) { $websiteIdentity = []; }
$websiteName = (string)($websiteIdentity['name'] ?? config('app.name'));
$websiteLightLogo = (string)($websiteIdentity['light_logo'] ?? 'images/branding/barama-logo-header.png');
$websiteDarkLogo = (string)($websiteIdentity['dark_logo'] ?? $websiteLightLogo);
$seoTitle = trim((string) ($pageTitle ?? $websiteName));
$seoDescription = trim(strip_tags((string) ($metaDescription ?? ($websiteIdentity['description'] ?? 'Barama Real Estate marketplace and property management platform.'))));
$seoDescription = (string) preg_replace('/\s+/u', ' ', $seoDescription);
$seoDescription = mb_substr($seoDescription, 0, 300);
$seoCanonical = trim((string) ($canonicalUrl ?? ''));

if ($seoCanonical === '') {
    $seoCanonical = url($authRoutePath);
}

$seoRobots = trim((string) ($metaRobots ?? (
    $isStandaloneAuthPage || $isWorkspacePage
        ? 'noindex,nofollow'
        : 'index,follow,max-image-preview:large'
)));
$seoImage = trim((string) ($metaImage ?? ''));
$seoType = trim((string) ($metaType ?? 'website'));

$appCssFile = BASE_PATH . '/assets/css/app.css';
$appJsFile = BASE_PATH . '/assets/js/app.js';
$appCssVersion = is_file($appCssFile)
    ? (string) (filemtime($appCssFile) ?: APP_VERSION)
    : APP_VERSION;
$appJsVersion = is_file($appJsFile)
    ? (string) (filemtime($appJsFile) ?: APP_VERSION)
    : APP_VERSION;
?>
<!doctype html>
<html lang="<?= e(app_locale()) ?>" data-bs-theme="light"
    <?= $workspaceDefaultTheme !== '' ? 'data-barama-default-theme="' . e($workspaceDefaultTheme) . '"' : '' ?><?= !$isWorkspacePage ? ' class="barama-public-root appearance-skin-' . e($frontendAppearanceSkin) . '"' : '' ?><?= !empty($publicContentProtection['enabled']) ? ' data-right-click-protection="1"' : '' ?><?= !empty($publicContentProtection['block_context_menu']) ? ' data-right-click-context-menu="1"' : '' ?><?= !empty($publicContentProtection['block_image_drag']) ? ' data-right-click-image-drag="1"' : '' ?><?= !empty($publicContentProtection['block_text_selection']) ? ' data-right-click-selection="1"' : '' ?>>
<head>
    <script data-barama-theme-prepaint>
        (() => {
            const root = document.documentElement;
            const serverDefault = root.getAttribute('data-barama-default-theme');
            let theme = 'light';

            try {
                const stored = window.localStorage.getItem('barama-theme');
                if (stored === 'light' || stored === 'dark') theme = stored;
                else if (serverDefault === 'light' || serverDefault === 'dark') theme = serverDefault;
                else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) theme = 'dark';
            } catch (error) {
                if (serverDefault === 'light' || serverDefault === 'dark') theme = serverDefault;
                else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) theme = 'dark';
            }
            root.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="<?= e(asset((string)($websiteIdentity['favicon_32'] ?? 'images/branding/favicon-32x32.png'))) ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= e(asset((string)($websiteIdentity['favicon_16'] ?? 'images/branding/favicon-16x16.png'))) ?>" sizes="16x16" type="image/png">
    <link rel="shortcut icon" href="<?= e(asset((string)($websiteIdentity['favicon_32'] ?? 'images/branding/favicon-32x32.png'))) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset((string)($websiteIdentity['apple_icon'] ?? 'images/branding/apple-touch-icon.png'))) ?>">
    <title><?= e($seoTitle) ?></title>
    <meta name="description" content="<?= e($seoDescription) ?>">
    <meta name="robots" content="<?= e($seoRobots) ?>">
    <link rel="canonical" href="<?= e($seoCanonical) ?>">
    <meta property="og:type" content="<?= e($seoType) ?>">
    <meta property="og:site_name" content="<?= e($websiteName) ?>">
    <meta property="og:title" content="<?= e($seoTitle) ?>">
    <meta property="og:description" content="<?= e($seoDescription) ?>">
    <meta property="og:url" content="<?= e($seoCanonical) ?>">
    <?php if ($seoImage !== ''): ?><meta property="og:image" content="<?= e($seoImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $seoImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($seoTitle) ?>">
    <meta name="twitter:description" content="<?= e($seoDescription) ?>">
    <?php if ($seoImage !== ''): ?><meta name="twitter:image" content="<?= e($seoImage) ?>"><?php endif; ?>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="<?= e(asset('css/app.css') . '?v=' . rawurlencode($appCssVersion)) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/footer-management-v100.css') . '?v=100') ?>" rel="stylesheet">
    <link href="<?= e(asset('css/profile-avatar-unified-v107.css') . '?v=107') ?>" rel="stylesheet">
    <link href="<?= e(asset('css/shared-notifications-v111.css') . '?v=111') ?>" rel="stylesheet">
    <link href="<?= e(asset('css/notification-interaction-v112.css') . '?v=112') ?>" rel="stylesheet">
    <script src="<?= e(asset('js/notification-tray-v112.js') . '?v=112') ?>" defer></script>
    <link href="<?= e(asset('css/footer-social-inline-v105.css') . '?v=105') ?>" rel="stylesheet">
    <?php if ($isWorkspacePage): ?>
        <link href="<?= e(asset('css/workspace-sidebar-scroll-v115.css') . '?v=115') ?>" rel="stylesheet">
        <!-- BARAMA_WORKSPACE_ICON_RAIL_V117A15: Admin / Account / Developer desktop-only icon rail -->
        <link href="<?= e(asset('css/workspace-sidebar-icon-rail-v117a15.css') . '?v=117a15') ?>" rel="stylesheet">
        <?php if ($changeConfirmationRequired): ?>
            <link href="<?= e(asset('css/change-confirmation-v133.css') . '?v=133') ?>" rel="stylesheet">
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!$isWorkspacePage): ?>
        <!-- BARAMA_PUBLIC_MOBILE_V1114 -->
        <link href="<?= e(asset('css/public-mobile-v1114.css') . '?v=1114') ?>" rel="stylesheet">
        <!-- BARAMA_HERO_SEARCH_V1117 -->
        <link href="<?= e(asset('css/hero-search-v1117.css') . '?v=1117') ?>" rel="stylesheet">
        <!-- BARAMA_HERO_SEARCH_V1118 -->
        <link href="<?= e(asset('css/hero-search-v1118.css') . '?v=1118') ?>" rel="stylesheet">
        <!-- BARAMA_HERO_SEARCH_V1122 -->
        <link href="<?= e(asset('css/hero-search-v1122.css') . '?v=1122') ?>" rel="stylesheet">
        <!-- BARAMA_HERO_SEARCH_V1123 -->
        <link href="<?= e(asset('css/hero-search-v1123.css') . '?v=1123') ?>" rel="stylesheet">
        <!-- BARAMA_POPULAR_LOCATION_MARQUEE_V1125 -->
        <link href="<?= e(asset('css/popular-location-marquee-v1125.css') . '?v=1125') ?>" rel="stylesheet">
        <!-- BARAMA_PUBLIC_HEADER_INLINE_V1119 -->
        <link href="<?= e(asset('css/public-header-inline-v1119.css') . '?v=1119') ?>" rel="stylesheet">
        <!-- BARAMA_PUBLIC_HEADER_INLINE_V1120 -->
        <link href="<?= e(asset('css/public-header-inline-v1120.css') . '?v=1120') ?>" rel="stylesheet">
        <!-- BARAMA_PUBLIC_HEADER_INLINE_V1121 -->
        <link href="<?= e(asset('css/public-header-inline-v1121.css') . '?v=1121') ?>" rel="stylesheet">
        <script src="<?= e(asset('js/public-header-inline-v1121.js') . '?v=1121') ?>" defer></script>
                <?php if (!empty($publicContentProtection['enabled'])): ?>
            <link href="<?= e(asset('css/right-click-protection-v134.css') . '?v=134') ?>" rel="stylesheet">
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!$isWorkspacePage): ?>
        <link href="<?= e(asset('css/frontend-back-to-top-v102.css') . '?v=102') ?>" rel="stylesheet">
        <link href="<?= e(asset('css/footer-back-to-top-clearance-v106.css') . '?v=106') ?>" rel="stylesheet">
    <?php endif; ?>
<script>document.addEventListener('DOMContentLoaded',()=>{const apply=()=>{const dark=document.documentElement.getAttribute('data-bs-theme')==='dark';document.querySelectorAll('img[data-barama-logo-light]').forEach(i=>{const src=i.getAttribute(dark?'data-barama-logo-dark':'data-barama-logo-light');if(src&&i.getAttribute('src')!==src)i.setAttribute('src',src)})};apply();new MutationObserver(apply).observe(document.documentElement,{attributes:true,attributeFilter:['data-bs-theme']})});</script>
    <?php if (!$isWorkspacePage && !$isStandaloneAuthPage && ($activeArea ?? '') === 'contact'): ?>
        <!-- BARAMA_CONTACT_HERO_V117A17 -->
        <link href="<?= e(asset('css/contact-hero-v117a17.css') . '?v=117a17') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (!$isWorkspacePage): ?>
        <!-- BARAMA_APPEARANCE_LAST_PUBLIC_STYLESHEET_V117A10 -->
        <link href="<?= e(url('/appearance.css?v=' . rawurlencode($frontendAppearanceVersion))) ?>" rel="stylesheet">
    <?php endif; ?>
    <!-- BARAMA_AUTH_PREMIUM_V117A33_BEGIN -->
    <?php if ($isStandaloneAuthPage && in_array($authRoutePath, ['/login', '/register', '/forgot-password'], true)): ?>
        <link rel="stylesheet" href="<?= e(asset('css/auth-premium-v117a33.css') . '?v=117a33-r2') ?>">
        <script src="<?= e(asset('js/auth-premium-v117a33.js') . '?v=117a33-r2') ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_AUTH_PREMIUM_V117A33_END -->
    <!-- BARAMA_AUTH_COPY_V117A34_BEGIN -->
    <?php if ($isStandaloneAuthPage && in_array($authRoutePath, ['/login', '/register', '/forgot-password'], true)): ?>
        <link rel="stylesheet" href="<?= e(asset('css/auth-copy-v117a34.css') . '?v=117a34-r2') ?>">
        <script src="<?= e(asset('js/auth-copy-v117a34.js') . '?v=117a34-r2') ?>" data-barama-website-name="<?= e(trim((string) ($websiteName ?? '')) !== '' ? trim((string) $websiteName) : 'Barama Real Estate') ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_AUTH_COPY_V117A34_END -->
    <!-- BARAMA_AUTH_CENTERED_V117A35_BEGIN -->
    <?php if ($isStandaloneAuthPage && in_array($authRoutePath, ['/login', '/register', '/forgot-password'], true)): ?>
        <link rel="stylesheet" href="<?= e(asset('css/auth-centered-v117a35.css') . '?v=117a35-r2') ?>">
        <script src="<?= e(asset('js/auth-centered-v117a35.js') . '?v=117a35-r2') ?>" data-site-logo-light="<?= e(asset($websiteLightLogo)) ?>" data-site-logo-dark="<?= e(asset($websiteDarkLogo)) ?>" data-site-name="<?= e(trim((string) $websiteName) !== '' ? trim((string) $websiteName) : 'Barama Real Estate') ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_AUTH_CENTERED_V117A35_END -->
    <!-- BARAMA_AUTH_TITLE_CENTERING_V117A36_BEGIN -->
    <?php if ($isStandaloneAuthPage && in_array($authRoutePath, ['/login', '/register', '/forgot-password'], true)): ?>
        <link rel="stylesheet" href="<?= e(asset('css/auth-title-centering-v117a36.css') . '?v=117a36-r2') ?>">
        <script src="<?= e(asset('js/auth-title-centering-v117a36.js') . '?v=117a36-r2') ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_AUTH_TITLE_CENTERING_V117A36_END -->
    <!-- BARAMA_AUTH_POLICY_LINKS_V117A37_BEGIN -->
    <?php if ($isStandaloneAuthPage && $authRoutePath === '/register'): ?>
        <script src="<?= e(asset('js/auth-policy-links-v117a37.js') . '?v=117a37-r2') ?>" data-terms-url="<?= e(url('/policies?=terms-of-service')) ?>" data-privacy-url="<?= e(url('/policies?=privacy-policy')) ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_AUTH_POLICY_LINKS_V117A37_END -->
    <!-- BARAMA_VERIFY_EMAIL_V117A38_BEGIN -->
    <?php if (rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/') === '/verify-email'): ?>
        <link rel="stylesheet" href="<?= e(asset('css/verify-email-auth-v117a38.css') . '?v=117a38') ?>">
        <script src="<?= e(asset('js/verify-email-auth-v117a38.js') . '?v=117a38') ?>" data-site-logo-light="<?= e(asset($websiteLightLogo)) ?>" data-site-logo-dark="<?= e(asset($websiteDarkLogo)) ?>" data-site-name="<?= e(trim((string) $websiteName) !== '' ? trim((string) $websiteName) : 'Website') ?>" defer></script>
    <?php endif; ?>
    <!-- BARAMA_VERIFY_EMAIL_V117A38_END -->
    <!-- CodeCanyon compliant external UI styling; no inline CSS. -->
    <link href="<?= e(asset('css/codecanyon-ui-compliance-v1.css') . '?v=1') ?>" rel="stylesheet">
    <?php if ($isWorkspacePage): ?>
        <script src="<?= e(asset('js/branding-file-name-v1.js') . '?v=1') ?>" defer></script>
        <script src="<?= e(asset('js/workspace-confirm-v1.js') . '?v=1') ?>" defer></script>
    <?php endif; ?>
</head>
<body class="<?= e(trim(
    ($isStandaloneAuthPage ? 'auth-standalone-body ' : '')
    . (!$isWorkspacePage ? 'barama-public-surface appearance-skin-' . $frontendAppearanceSkin : '')
)) ?>"<?= $changeConfirmationRequired
    ? ' data-change-confirmation-required="1" data-change-confirmation-endpoint="' . e(url('/security/change-confirmation')) . '"'
    : '' ?>>
    <?php if (!$isStandaloneAuthPage && !$isWorkspacePage): ?>
    <header class="site-header" data-site-header>
        <div class="container app-container">
            <div class="site-header__inner">
                <a class="brand brand--image" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama Real Estate home')) ?>">
                    <img
                        class="brand__logo"
                        src="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-light="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-dark="<?= e(asset($websiteDarkLogo)) ?>"
                        alt="<?= e(t('Barama Real Estate')) ?>"
                        width="960"
                        height="220"
                    >
                </a>

                <button
                    class="site-menu-toggle"
                    type="button"
                    data-site-menu-toggle
                    aria-controls="site-navigation"
                    aria-expanded="false"
                    aria-label="<?= e(t('Open navigation')) ?>"
                >
                    <?= app_icon('menu') ?>
                </button>

                <div class="site-navigation" id="site-navigation" data-site-navigation>
                    <nav class="main-nav" aria-label="<?= e(t('Primary navigation')) ?>">
                        <?php foreach ($publicNavigation as $navigationItem): ?>
                            <?php
                            $navigationArea = (string) ($navigationItem['active_area'] ?? '');
                            $navigationPath = (string) ($navigationItem['path'] ?? '/');
                            ?>
                            <a
                                class="<?= $navigationArea !== '' && ($activeArea ?? '') === $navigationArea ? 'is-active' : '' ?>"
                                href="<?= e(url($navigationPath)) ?>"
                            ><?= e(t((string) ($navigationItem['label'] ?? 'Link'))) ?></a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="header-auth">
                        <form class="header-locale" action="<?= e(url('/locale')) ?>" method="post" data-locale-switch>
                            <?= csrf_field() ?>
                            <input type="hidden" name="return" value="<?= e($localeReturn) ?>">
                            <span class="header-locale__icon"><?= app_icon('language') ?></span>
                            <label class="visually-hidden" for="header-locale-select"><?= e(t('Language')) ?></label>
                            <select id="header-locale-select" name="locale" data-locale-select aria-label="<?= e(t('Language')) ?>">
                                <?php foreach (app_locales() as $localeCode => $localeLabel): ?>
                                    <option value="<?= e($localeCode) ?>" <?= $currentLocale === $localeCode ? 'selected' : '' ?>><?= e($localeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <div
                            class="barama-user-menu-wrap<?= $isAuthenticated ? ' barama-user-menu-wrap--authenticated' : '' ?>"
                            data-public-user-menu
                        >
                            <button
                                class="barama-user-trigger"
                                type="button"
                                data-public-user-trigger
                                aria-label="<?= e(t('Account menu')) ?>"
                                aria-haspopup="menu"
                                aria-expanded="false"
                            >
                                <?php if ($headerAvatarUuid !== ''): ?>
                                    <img class="barama-avatar-v107__header-img" src="<?= e(url('/account/avatar/' . rawurlencode($headerAvatarUuid))) ?>" alt="" width="32" height="32" decoding="async">
                                <?php else: ?>
                                    <?= app_icon('user') ?>
                                <?php endif; ?>
                            </button>

                            <div class="barama-user-menu" data-public-user-panel role="menu" hidden>
                                <?php if ($isAuthenticated): ?>
                                    <div class="barama-user-menu__head barama-user-menu__head--profile">
                                        <span class="barama-user-menu__profile-avatar" aria-hidden="true"><?php if ($headerAvatarUuid !== ''): ?><img class="barama-avatar-v107__header-img" src="<?= e(url('/account/avatar/' . rawurlencode($headerAvatarUuid))) ?>" alt="" width="40" height="40"><?php else: ?><?= app_icon('user') ?><?php endif; ?></span>
                                        <span class="barama-user-menu__profile-copy">
                                            <strong><?= e((string) ($headerUser['name'] ?? t('Profile'))) ?></strong>
                                            <small><?= e(t('Profile')) ?></small>
                                        </span>
                                    </div>

                                    <div class="barama-user-menu__actions">
                                        <?php if ($isAdministrator): ?>
                                            <a class="barama-user-action" href="<?= e(url('/account')) ?>" role="menuitem">
                                                <span class="barama-user-action__icon"><?= app_icon('user') ?></span>
                                                <span>
                                                    <strong><?= e(t('Account')) ?></strong>
                                                    <small><?= e(t('Manage account settings and profile details.')) ?></small>
                                                </span>
                                                <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                            </a>

                                            <a class="barama-user-action" href="<?= e(url('/developer')) ?>" role="menuitem">
                                                <span class="barama-user-action__icon"><?= app_icon('building') ?></span>
                                                <span>
                                                    <strong><?= e(t('Company')) ?></strong>
                                                    <small><?= e(t('Open the company and vendor workspace.')) ?></small>
                                                </span>
                                                <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                            </a>

                                            <a class="barama-user-action" href="<?= e(url('/dashboard')) ?>" role="menuitem">
                                                <span class="barama-user-action__icon"><?= app_icon('grid') ?></span>
                                                <span>
                                                    <strong><?= e(t('Dashboard')) ?></strong>
                                                    <small><?= e(t('Open the Admin Control Center.')) ?></small>
                                                </span>
                                                <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endif; ?>

                                        <form class="barama-user-menu__signout" action="<?= e(url('/logout')) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button class="barama-user-action barama-user-action--button" type="submit" role="menuitem">
                                                <span class="barama-user-action__icon"><?= app_icon('lock') ?></span>
                                                <span>
                                                    <strong><?= e(t('Sign Out')) ?></strong>
                                                    <small><?= e(t('End this signed-in session.')) ?></small>
                                                </span>
                                                <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="barama-user-menu__head">
                                        <strong><?= e(t('Account')) ?></strong>
                                        <small><?= e(t('Sign in or create an account to manage your Barama activity.')) ?></small>
                                    </div>

                                    <div class="barama-user-menu__actions">
                                        <a class="barama-user-action" href="<?= e(url('/login')) ?>" role="menuitem">
                                            <span class="barama-user-action__icon"><?= app_icon('key') ?></span>
                                            <span>
                                                <strong><?= e(t('Sign in')) ?></strong>
                                                <small><?= e(t('Access saved properties and your account activity.')) ?></small>
                                            </span>
                                            <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                        </a>

                                        <a class="barama-user-action" href="<?= e(url('/register')) ?>" role="menuitem">
                                            <span class="barama-user-action__icon"><?= app_icon('user') ?></span>
                                            <span>
                                                <strong><?= e(t('Create account')) ?></strong>
                                                <small><?= e(t('Create an account to save and manage your activity.')) ?></small>
                                            </span>
                                            <span class="barama-user-action__arrow" aria-hidden="true">→</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>

        <?php if ($isWorkspacePage): ?>
    <header class="workspace-topbar" data-workspace-header>
        <div class="workspace-topbar__grid">
            <div class="workspace-topbar__brand-zone">
                                <!-- BARAMA_WORKSPACE_HEADER_ALIGNMENT_FIX_V100 -->
<a class="workspace-topbar__brand" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama home')) ?>">
                    <img src="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-light="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-dark="<?= e(asset($websiteDarkLogo)) ?>" alt="Barama" width="300" height="100">
                </a>
<button class="workspace-menu-button workspace-topbar__menu workspace-topbar__sidebar-toggle" type="button" data-workspace-open data-workspace-sidebar-toggle aria-controls="workspace-navigation" aria-expanded="true" aria-label="<?= e(t('Toggle sidebar')) ?>" title="<?= e(t('Toggle sidebar')) ?>">
                    <?= app_icon('menu') ?>
                    <span><?= e(t('Menu')) ?></span>
                </button>
            </div>

            <div class="workspace-topbar__middle">
                <!-- BARAMA_WORKSPACE_NAV_TOGGLE_V100 -->
                <!-- BARAMA_WORKSPACE_HEADER_SEARCH_JOIN_FIX_V100 -->
                <form
                    class="workspace-topbar__search"
                    role="search"
                    method="get"
                    action="<?= e(url(match ($workspaceHeaderArea) { 'dashboard' => '/dashboard', 'developer' => '/developer', default => '/account' })) ?>"
                    aria-label="<?= e(match ($workspaceHeaderArea) { 'dashboard' => t('Search Admin Control Center'), 'developer' => t('Search Vendor/Developer'), default => t('Search Customer Account') }) ?>"
                >
                    <span class="workspace-topbar__search-icon" aria-hidden="true"><?= app_icon('search') ?></span>
                    <label class="visually-hidden" for="workspace-header-search"><?= e(t('Search')) ?></label>
                    <input
                        id="workspace-header-search"
                        type="search"
                        name="q"
                        value="<?= e((string) ($_GET['q'] ?? '')) ?>"
                        placeholder="<?= e(match ($workspaceHeaderArea) { 'dashboard' => t('Search Admin Control Center'), 'developer' => t('Search Vendor/Developer'), default => t('Search Customer Account') }) ?>"
                        autocomplete="off"
                    >
                </form>

                
            </div>

            <div class="workspace-topbar__actions">
                <!-- BARAMA_WORKSPACE_HEADER_UTILITY_ICON_FIX_V100 -->
                <a class="workspace-topbar__site-link" href="<?= e(url('/')) ?>" aria-label="<?= e(t('View public website')) ?>" title="<?= e(t('View public website')) ?>">
                    <span aria-hidden="true"><?= app_icon('home') ?></span>
                </a>

                <?php if ($isAuthenticated): ?>
                    <div class="barama-notification-tray-v112" data-notification-tray>
    <button type="button" class="barama-notification-bell barama-notification-bell--workspace" data-notification-toggle aria-expanded="false" aria-controls="notification-tray-workspace" aria-label="<?= e(t('Notifications') . ($headerUnreadNotifications > 0 ? ' (' . $headerUnreadNotifications . ' unread)' : '')) ?>" title="<?= e(t('Notifications')) ?>">
        <?= app_icon('bell') ?>
        <?php if ($headerUnreadNotifications > 0): ?><span class="barama-notification-bell__count" aria-hidden="true"><?= e($headerNotificationsBadge) ?></span><?php endif; ?>
    </button>
    <div class="barama-notification-tray-v112__popup" id="notification-tray-workspace" data-notification-panel hidden>
        <div class="barama-notification-tray-v112__heading"><div><strong>Notifications</strong><small><?= (int)$headerUnreadNotifications ?> unread</small></div><a href="<?= e(url($headerNotificationUrl)) ?>">View all</a></div>
        <?php if ($headerUnreadItems === []): ?>
            <div class="barama-notification-tray-v112__empty"><?= app_icon('bell') ?><span>You're all caught up.</span></div>
        <?php else: ?>
            <div class="barama-notification-tray-v112__scroll" role="list" aria-label="Unread notifications">
                <?php foreach ($headerUnreadItems as $headerNotification): ?>
                    <form method="post" action="<?= e(url('/notifications/' . (int)$headerNotification['id'] . '/open')) ?>" role="listitem" class="barama-notification-tray-v112__item">
                        <?= csrf_field() ?>
                        <input type="hidden" name="source" value="<?= e($headerNotificationSource) ?>">
                        <button type="submit" title="Open notification and mark as read"><strong><?= e((string)$headerNotification['title']) ?></strong><span><?= e((string)$headerNotification['message']) ?></span><small><?= e((string)$headerNotification['created_at']) ?></small></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="barama-notification-tray-v112__foot"><a href="<?= e(url($headerNotificationUrl)) ?>">All notifications <span aria-hidden="true">→</span></a></div>
    </div>
</div>
                <?php endif; ?>

                <form class="workspace-topbar__locale" action="<?= e(url('/locale')) ?>" method="post" data-locale-switch>
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="<?= e($localeReturn) ?>">
                    <span class="workspace-topbar__locale-icon" aria-hidden="true"><?= app_icon('language') ?></span>
                    <label class="visually-hidden" for="workspace-locale-select"><?= e(t('Language')) ?></label>
                    <select id="workspace-locale-select" name="locale" data-locale-select aria-label="<?= e(t('Language')) ?>" title="<?= e(t('Language')) ?>">
                        <?php foreach (app_locales() as $localeCode => $localeLabel): ?>
                            <option value="<?= e($localeCode) ?>" <?= $currentLocale === $localeCode ? 'selected' : '' ?>><?= e($localeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <button class="theme-toggle workspace-topbar__theme" type="button" data-theme-toggle aria-label="<?= e(t('Toggle light and dark mode')) ?>" title="<?= e(t('Toggle theme')) ?>">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm9-7a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5 11a1 1 0 1 1 0 2H4a1 1 0 1 1 0-2h1Zm13.36-5.36a1 1 0 0 1 0 1.42l-.7.7a1 1 0 1 1-1.42-1.42l.7-.7a1 1 0 0 1 1.42 0ZM7.76 16.24a1 1 0 0 1 0 1.42l-.7.7a1 1 0 1 1-1.42-1.42l.7-.7a1 1 0 0 1 1.42 0Zm10.6.7a1 1 0 0 1-1.42 1.42l-.7-.7a1 1 0 1 1 1.42-1.42l.7.7ZM7.76 7.76a1 1 0 0 1-1.42 0l-.7-.7a1 1 0 0 1 1.42-1.42l.7.7a1 1 0 0 1 0 1.42ZM12 19a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1Z"/></svg></span>
                </button>

                <!-- BARAMA_WORKSPACE_PROFILE_AVATAR_ONLY_V100 -->
                <details class="workspace-user-menu workspace-user-menu--avatar-only">
                    <summary class="workspace-user-menu__trigger" aria-label="<?= e(t('Account menu')) ?>" title="<?= e(t('Account menu')) ?>">
                        <span class="workspace-user-menu__avatar" aria-hidden="true"><?php if ($headerAvatarUuid !== ''): ?><img class="barama-avatar-v107__header-img" src="<?= e(url('/account/avatar/' . rawurlencode($headerAvatarUuid))) ?>" alt="" width="32" height="32"><?php else: ?><?= app_icon('user') ?><?php endif; ?></span>
                    </summary>
                    <div class="workspace-user-menu__panel">
                        <div class="workspace-user-menu__head">
                            <span class="workspace-user-menu__avatar"><?php if ($headerAvatarUuid !== ''): ?><img class="barama-avatar-v107__header-img" src="<?= e(url('/account/avatar/' . rawurlencode($headerAvatarUuid))) ?>" alt="" width="32" height="32"><?php else: ?><?= app_icon('user') ?><?php endif; ?></span>
                            <div><strong><?= e($workspaceHeaderUserName) ?></strong><?php if ($workspaceHeaderUserEmail !== ''): ?><small><?= e($workspaceHeaderUserEmail) ?></small><?php endif; ?></div>
                        </div>
                        <?php if (auth_workspace_can('account')): ?>
                        <nav aria-label="<?= e(t('Account shortcuts')) ?>">
                            <a href="<?= e(url('/account')) ?>"><?= app_icon('user') ?><span><?= e(t('My Account')) ?></span></a>
                            <a href="<?= e(url('/account/profile')) ?>"><?= app_icon('users') ?><span><?= e(t('Profile')) ?></span></a>
                            <a href="<?= e(url('/account/security')) ?>"><?= app_icon('lock') ?><span><?= e(t('Security')) ?></span></a>
                        </nav>
                        <?php endif; ?>
                        <form action="<?= e(url('/logout')) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit"><?= app_icon('lock') ?><span><?= e(t('Sign out')) ?></span></button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <?php if ($isWorkspacePage): ?>
        <div class="workspace-shell">
            <aside class="workspace-sidebar" id="workspace-navigation" data-workspace-sidebar>
                <!-- BARAMA_WORKSPACE_SIDEBAR_HEADING_REMOVAL_V100 -->
                <div class="workspace-sidebar__top workspace-sidebar__top--controls-only">
                    <button
                        type="button"
                        class="workspace-sidebar__close"
                        data-workspace-close
                        aria-label="<?= e(t('Close workspace navigation')) ?>"
                    >
                        ×
                    </button>
                </div>

                <nav class="workspace-nav" aria-label="<?= e(($workspaceLabel ?? t('Workspace')) . ' navigation') ?>">
                    <?php foreach ($workspaceNavigation as $item): ?>
                        <?php if (isset($item['heading'])): ?>
                            <div class="workspace-nav__section-label"><?= e((string) $item['heading']) ?></div>
                            <?php continue; ?>
                        <?php endif; ?>
                        <?php
                        $itemPath = (string) ($item['path'] ?? '/');
                        $itemLabel = (string) ($item['label'] ?? t('Section'));
                        $itemIcon = (string) ($item['icon'] ?? 'grid');
                        $sectionSlug = trim((string) parse_url($itemPath, PHP_URL_PATH), '/');
                        $sectionSlug = str_contains($sectionSlug, '/')
                            ? substr($sectionSlug, strrpos($sectionSlug, '/') + 1)
                            : 'overview';
                        $isActive = ($activeSection ?? 'overview') === $sectionSlug
                            || (($activeSection ?? 'overview') === 'overview'
                                && in_array($itemPath, ['/account', '/developer', '/dashboard'], true))
                            || (($activeSection ?? '') === 'basic'
                                && in_array($itemPath, ['/account/settings', '/developer/settings', '/dashboard/settings'], true));
                        ?>
                        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(url($itemPath)) ?>" aria-label="<?= e($itemLabel) ?>" title="<?= e($itemLabel) ?>">
                            <span class="workspace-nav__icon"><?= app_icon($itemIcon) ?></span>
                            <span><?= e($itemLabel) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php /* BARAMA_SYSTEM_CENTER_V100_SIDEBAR */ ?>
                    <?php if (($workspaceType ?? '') === 'admin' && auth_workspace_can('dashboard')): ?>
                    <div class="workspace-nav__section-label" data-barama-system-center-nav>System</div>
                    <?php foreach ([
                        ['About', '/dashboard/system/about', 'server'],
                        ['Auto Updater', '/dashboard/system/auto-updater', 'clock'], // barama-system-auto-updater-v110
                        ['System Health', '/dashboard/system/health', 'check'],
                    ] as $sysItem): ?>
                    <?php $sysActive = rtrim((string) $authRoutePath, '/') === $sysItem[1]; ?>
                    <a class="<?= $sysActive ? 'is-active' : '' ?>" href="<?= e(url($sysItem[1])) ?>" <?= $sysActive ? 'aria-current="page"' : '' ?>>
                        <span class="workspace-nav__icon"><?= app_icon($sysItem[2]) ?></span>
                        <span><?= e($sysItem[0]) ?></span>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </nav>

                <div class="workspace-sidebar__footer">
                    <span>Barama</span>
                    <small>v<?= e(APP_VERSION) ?></small>
                </div>
            </aside>

            <div class="workspace-main">
                
                <main>
                    <?= $content ?>
                </main>
            </div>
            <button class="workspace-backdrop" type="button" data-workspace-backdrop aria-label="<?= e(t('Close workspace navigation')) ?>"></button>
        </div>
    <?php else: ?>
        <main>
            <?= $content ?>
        </main>
    <?php endif; ?>

    <?php if (!$isStandaloneAuthPage && !$isWorkspacePage): ?>
    <footer class="site-footer">
        <div class="container app-container">
            <?php
            $editableFooterLinks = is_array($footerContent['links'] ?? null) ? $footerContent['links'] : [];
            $footerHeadings = [
                'marketplace' => (string) ($footerContent['marketplace_heading'] ?? 'Marketplace'),
                'compliance' => (string) ($footerContent['compliance_heading'] ?? 'Compliance'),
                'help' => (string) ($footerContent['help_heading'] ?? 'Help'),
            ];
            $footerItemHref = static function (string $href): string {
                if (!\Barama\Services\FooterContentService::validLink($href)) {
                    return '';
                }
                return str_starts_with($href, '/') ? url($href) : $href;
            };
            ?>
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a class="brand brand--image" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama Real Estate home')) ?>">
                        <img
                            class="brand__logo"
                            src="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-light="<?= e(asset($websiteLightLogo)) ?>" data-barama-logo-dark="<?= e(asset($websiteDarkLogo)) ?>"
                            alt="<?= e(t('Barama Real Estate')) ?>"
                            width="960"
                            height="220"
                        >
                    </a>
                    <strong class="site-footer__brand-heading"><?= e(t((string) ($footerContent['brand_heading'] ?? 'Barama Real Estate'))) ?></strong>
                    <p><?= e(t((string) ($footerContent['brand_description'] ?? ''))) ?></p>
                    <?php $brandLinks = (array) ($editableFooterLinks['brand'] ?? []); ?>
                    <?php if ($brandLinks !== []): ?>
                        <nav class="site-footer__brand-links" aria-label="<?= e(t('Brand links')) ?>">
                            <?php foreach ($brandLinks as $link): ?>
                                <?php
                                $href = $footerItemHref((string) ($link['url'] ?? ''));
                                if ($href === '') { continue; }
                                $external = str_starts_with($href, 'https://');
                                ?>
                                <a href="<?= e($href) ?>" <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e(t((string) ($link['label'] ?? 'Link'))) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </div>

                <?php foreach ($footerHeadings as $columnId => $heading): ?>
                    <nav aria-label="<?= e(t($heading . ' links')) ?>">
                        <strong><?= e(t($heading)) ?></strong>
                        <?php foreach ((array) ($editableFooterLinks[$columnId] ?? []) as $link): ?>
                            <?php
                            $href = $footerItemHref((string) ($link['url'] ?? ''));
                            if ($href === '') { continue; }
                            $external = str_starts_with($href, 'https://');
                            ?>
                            <a href="<?= e($href) ?>" <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e(t((string) ($link['label'] ?? 'Link'))) ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endforeach; ?>
            </div>

            <div class="site-footer__bottom">
                <div class="site-footer__meta">
                    <span><?= e(str_replace('{year}', date('Y'), (string) ($footerContent['copyright'] ?? 'Copyright {year} | Barama Real Estate'))) ?></span>
                </div>

                <div class="site-footer__utility">
                    <?php
                    // Trusted, bundled SVG icon paths. No icon data comes from admin input.
                    $footerSocialNetworks = [
                        'facebook' => [
                            'label' => 'Facebook',
                            'svg' => '<path d="M14.2 21v-8h2.7l.4-3.3h-3.1V7.6c0-1 .3-1.6 1.7-1.6h1.6V3.1C17.2 3 16.2 3 15.1 3c-2.9 0-4.8 1.8-4.8 5v1.7H7.5V13h2.8v8z"/>',
                        ],
                        'instagram' => [
                            'label' => 'Instagram',
                            'svg' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.6" cy="6.5" r="1.3"/>',
                        ],
                        'youtube' => [
                            'label' => 'YouTube',
                            'svg' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 8.5l6 3.5-6 3.5z" fill="var(--barama-social-play, #fff)"/>',
                        ],
                        'tiktok' => [
                            'label' => 'TikTok',
                            'svg' => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.25 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.59 3.17-5.91 3.21-1.42.08-2.84-.31-4.05-1.03-2-1.19-3.4-3.35-3.61-5.67-.02-.5-.03-1-.01-1.49.18-1.89 1.11-3.7 2.55-4.94 1.64-1.42 3.94-2.1 6.09-1.7.02 1.48-.04 2.96-.04 4.44-.98-.31-2.07-.22-2.93.36-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.1-.14 1.64.24 1.64 1.82 3.02 3.5 2.87 1.11-.01 2.16-.66 2.74-1.59.19-.33.4-.67.41-1.05.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
                        ],
                        'x' => [
                            'label' => 'X',
                            'svg' => '<path d="M18.901 1.153h3.68L14.54 10.35 24 22.847h-7.406l-5.8-7.584-6.63 7.584H.48l8.6-9.83L0 1.153h7.594l5.243 6.932zm-1.29 19.49h2.038L6.486 3.24H4.298z"/>',
                        ],
                        'linkedin' => [
                            'label' => 'LinkedIn',
                            'svg' => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.94v5.666H9.35V9h3.414v1.56h.049c.476-.9 1.637-1.85 3.37-1.85 3.6 0 4.264 2.37 4.264 5.455v6.287zM5.337 7.433a2.062 2.062 0 1 1 0-4.123 2.062 2.062 0 0 1 0 4.123zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
                        ],
                    ];
                    $footerSocialLinks = is_array($footerContent['social'] ?? null) ? $footerContent['social'] : [];
                    ?>
                    <nav class="site-footer__social" aria-label="<?= e(t((string) ($footerContent['social_heading'] ?? 'Follow us'))) ?>">
                        <?php foreach ($footerSocialNetworks as $platform => $details): ?>
                            <?php $socialHref = trim((string) ($footerSocialLinks[$platform] ?? '')); ?>
                            <?php if ($socialHref === '' || preg_match('~^https://~i', $socialHref) !== 1) { continue; } ?>
                            <a href="<?= e($socialHref) ?>" target="_blank" rel="noopener noreferrer" class="site-footer__social-link" data-social-platform="<?= e($platform) ?>" aria-label="<?= e($details['label']) ?>" title="<?= e($details['label']) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><?= $details['svg'] ?></svg>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <button
                        class="theme-toggle site-footer__theme"
                        type="button"
                        data-theme-toggle
                        aria-label="<?= e(t('Toggle light and dark mode')) ?>"
                        title="<?= e(t('Toggle theme')) ?>"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1Zm0 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm9-7a1 1 0 1 1 0 2h-1a1 1 0 1 1 0-2h1ZM5 11a1 1 0 1 1 0 2H4a1 1 0 1 1 0-2h1Zm13.36-5.36a1 1 0 0 1 0 1.42l-.7.7a1 1 0 1 1-1.42-1.42l.7-.7a1 1 0 0 1 1.42 0ZM7.76 16.24a1 1 0 0 1 0 1.42l-.7.7a1 1 0 1 1-1.42-1.42l.7-.7a1 1 0 0 1 1.42 0Zm10.6.7a1 1 0 0 1-1.42 1.42l-.7-.7a1 1 0 1 1 1.42-1.42l.7.7ZM7.76 7.76a1 1 0 0 1-1.42 0l-.7-.7a1 1 0 0 1 1.42-1.42l.7.7a1 1 0 0 1 0 1.42ZM12 19a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0v-1a1 1 0 0 1 1-1Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <?php if ($changeConfirmationRequired): ?>
    <dialog class="change-confirmation-dialog" data-change-confirmation-dialog aria-labelledby="change-confirmation-title">
        <form method="dialog" class="change-confirmation-dialog__shell" data-change-confirmation-shell>
            <header class="change-confirmation-dialog__head">
                <span class="change-confirmation-dialog__icon"><?= app_icon('key') ?></span>
                <div>
                    <h2 id="change-confirmation-title">Confirm this change</h2>
                    <p>Enter your 6-digit change passcode. The approval is single-use and expires quickly.</p>
                </div>
                <button type="button" class="change-confirmation-dialog__close" data-change-confirmation-cancel aria-label="Cancel change">×</button>
            </header>
            <div class="change-confirmation-dialog__body">
                <label>Enter your 6-digit passcode</label>
                <div class="change-confirmation-code" data-change-confirmation-code aria-label="6-digit change passcode">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="password" inputmode="numeric" maxlength="1" autocomplete="off" data-change-confirmation-digit aria-label="Digit <?= $i ?>">
                    <?php endfor; ?>
                    <input type="hidden" data-change-confirmation-passcode>
                </div>
                <small class="change-confirmation-dialog__hint">Each block accepts one digit. You can paste all six digits at once.</small>
                <p class="change-confirmation-dialog__error" data-change-confirmation-error hidden></p>
            </div>
            <footer class="change-confirmation-dialog__actions">
                <button class="btn btn-outline-secondary" type="button" data-change-confirmation-cancel>Cancel</button>
                <button class="btn btn-primary" type="button" data-change-confirmation-approve><?= app_icon('check') ?> Confirm change</button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <?php if (!$isWorkspacePage): ?>
        <button
            class="barama-back-to-top"
            type="button"
            data-back-to-top
            data-visible="false"
            aria-label="<?= e(t('Back to top')) ?>"
            title="<?= e(t('Back to top')) ?>"
            aria-hidden="true"
            tabindex="-1"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 19V5"></path>
                <path d="m6 11 6-6 6 6"></path>
            </svg>
        </button>
    <?php endif; ?>

    <script src="<?= e(asset('js/app.js') . '?v=' . rawurlencode($appJsVersion)) ?>" defer></script>
    <?php if (!$isWorkspacePage): ?>
        <script src="<?= e(asset('js/frontend-back-to-top-v102.js') . '?v=102') ?>" defer></script>
        <script src="<?= e(asset('js/footer-back-to-top-clearance-v106.js') . '?v=106') ?>" defer></script>
    <?php endif; ?>
    <?php if ($isWorkspacePage): ?>
        <script src="<?= e(asset('js/workspace-sidebar-scroll-v115.js') . '?v=115') ?>" defer></script>
        <script src="<?= e(asset('js/workspace-sidebar-tooltips-v117a15.js') . '?v=117a15') ?>" defer></script>
        <?php if ($changeConfirmationRequired): ?>
            <script src="<?= e(asset('js/change-confirmation-v133.js') . '?v=117a13') ?>" defer></script>
        <?php endif; ?>
    <?php elseif (!empty($publicContentProtection['enabled'])): ?>
        <script src="<?= e(asset('js/right-click-protection-v134.js') . '?v=134') ?>" defer></script>
    <?php endif; ?>

    <!-- BARAMA_TURNSTILE_REAL_V110_LAYOUT -->
    <?php
    try { $baramaTurnstile = \Barama\Security\TurnstileGuard::publicConfig(); }
    catch (\Throwable) { $baramaTurnstile = ['site' => '', 'forms' => []]; }
    if ($baramaTurnstile['site'] !== '' && $baramaTurnstile['forms'] !== []): ?>
        <script src="<?= e(asset('js/barama-turnstile.js') . '?v=113') ?>"
                data-sitekey="<?= e($baramaTurnstile['site']) ?>"
                data-forms="<?= e(implode(',', $baramaTurnstile['forms'])) ?>"
                data-appearance="<?= e($baramaTurnstile['appearance'] ?? 'always') ?>" defer></script>
    <?php endif; ?>
</body>
</html>
