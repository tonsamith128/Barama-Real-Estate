<?php
declare(strict_types=1);

$headerUser = auth_user();
$isAuthenticated = $headerUser !== null;
$currentLocale = app_locale();
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$localeReturn = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
$appBasePath = (string) (parse_url((string) config('app.url', ''), PHP_URL_PATH) ?: '');
$appBasePath = '/' . trim($appBasePath, '/');

if ($appBasePath !== '/' && str_starts_with($localeReturn, $appBasePath)) {
    $localeReturn = substr($localeReturn, strlen($appBasePath)) ?: '/';
}

$authRoutePath = '/' . trim($localeReturn, '/');

if ($authRoutePath === '') {
    $authRoutePath = '/';
}

$isStandaloneAuthPage = in_array(
    $authRoutePath,
    ['/login', '/register', '/forgot-password'],
    true
);

$queryString = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');

if ($queryString !== '') {
    $localeReturn .= '?' . $queryString;
}
/* BARAMA_WORKSPACE_HEADER_V100 */
/* BARAMA_WORKSPACE_BOUNDARY_HEADER_V100 */
$isWorkspacePage = isset($workspaceNavigation) && is_array($workspaceNavigation);
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
    'dashboard' => t('Admin Dashboard'),
    'developer' => t('Company / Vendor Workspace'),
    default => t('Customer Account'),
});
?>
<!doctype html>
<html lang="<?= e(app_locale()) ?>" data-bs-theme="light">
<head>
    <script data-barama-theme-prepaint>
        (() => {
            const root = document.documentElement;
            let theme = 'light';

            try {
                const stored = window.localStorage.getItem('barama-theme');

                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                } else if (
                    window.matchMedia
                    && window.matchMedia('(prefers-color-scheme: dark)').matches
                ) {
                    theme = 'dark';
                }
            } catch (error) {
                if (
                    window.matchMedia
                    && window.matchMedia('(prefers-color-scheme: dark)').matches
                ) {
                    theme = 'dark';
                }
            }

            root.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="<?= e(asset('images/branding/favicon-32x32.png')) ?>" sizes="32x32" type="image/png">
    <link rel="icon" href="<?= e(asset('images/branding/favicon-16x16.png')) ?>" sizes="16x16" type="image/png">
    <link rel="shortcut icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('images/branding/apple-touch-icon.png')) ?>">
    <title><?= e($pageTitle ?? config('app.name')) ?></title>
    <meta name="description" content="Barama Real Estate marketplace and property management platform.">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= $isStandaloneAuthPage ? 'auth-standalone-body' : '' ?>">
    <?php if (!$isStandaloneAuthPage && !$isWorkspacePage): ?>
    <header class="site-header" data-site-header>
        <div class="container app-container">
            <div class="site-header__inner">
                <a class="brand brand--image" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama Real Estate home')) ?>">
                    <img
                        class="brand__logo"
                        src="<?= e(asset('images/branding/barama-logo-header.png')) ?>"
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
                        <a class="<?= ($activeArea ?? '') === 'home' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>"><?= e(t('Home')) ?></a>
                        <a class="<?= ($activeArea ?? '') === 'properties' ? 'is-active' : '' ?>" href="<?= e(url('/properties')) ?>"><?= e(t('Properties')) ?></a>
                        <a class="<?= ($activeArea ?? '') === 'projects' ? 'is-active' : '' ?>" href="<?= e(url('/projects')) ?>"><?= e(t('Projects')) ?></a>
                        <a class="<?= ($activeArea ?? '') === 'company' ? 'is-active' : '' ?>" href="<?= e(url('/company')) ?>"><?= e(t('Companies')) ?></a>
                    </nav>

                    <div class="header-auth">
                        <?php if ($isAuthenticated): ?>
                            <div class="account-switcher">
                                <?php if (auth_workspace_can('account')): ?>
                                <a class="account-switcher__identity <?= ($activeArea ?? '') === 'account' ? 'is-active' : '' ?>" href="<?= e(url('/account')) ?>">
                                    <span class="account-switcher__avatar"><?= app_icon('user') ?></span>
                                    <span>
                                        <strong><?= e((string) ($headerUser['name'] ?? t('My account'))) ?></strong>
                                        <small><?= e(t('My Account')) ?></small>
                                    </span>
                                </a>
                                <?php endif; ?>

                                <?php if (auth_workspace_can('developer')): ?>
                                    <a class="account-switcher__shortcut <?= ($activeArea ?? '') === 'developer' ? 'is-active' : '' ?>" href="<?= e(url('/developer')) ?>" title="<?= e(t('Company / Vendor')) ?>">
                                        <?= app_icon('building') ?>
                                        <span><?= e(t('Company / Vendor')) ?></span>
                                    </a>
                                <?php endif; ?>

                                <?php if (auth_workspace_can('dashboard')): ?>
                                    <a class="account-switcher__shortcut <?= ($activeArea ?? '') === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>" title="<?= e(t('Dashboard')) ?>">
                                        <?= app_icon('grid') ?>
                                        <span><?= e(t('Dashboard')) ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <form class="header-signout" action="<?= e(url('/logout')) ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" aria-label="<?= e(t('Sign out')) ?>" title="<?= e(t('Sign out')) ?>">
                                    <?= app_icon('lock') ?>
                                    <span><?= e(t('Sign out')) ?></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <a class="header-auth__signin" href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
                            <a class="btn btn-primary btn-sm header-auth__register" href="<?= e(url('/register')) ?>"><?= e(t('Create account')) ?></a>
                        <?php endif; ?>

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

                        <button
                            class="theme-toggle"
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
        </div>
    </header>
    <?php endif; ?>

        <?php if ($isWorkspacePage): ?>
    <header class="workspace-topbar" data-workspace-header>
        <div class="workspace-topbar__grid">
            <div class="workspace-topbar__brand-zone">
                                <!-- BARAMA_WORKSPACE_HEADER_ALIGNMENT_FIX_V100 -->
<a class="workspace-topbar__brand" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama home')) ?>">
                    <img src="<?= e(asset('images/branding/barama-logo-header.png')) ?>" alt="Barama" width="300" height="100">
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
                    aria-label="<?= e(match ($workspaceHeaderArea) { 'dashboard' => t('Search Admin Dashboard'), 'developer' => t('Search Vendor/Developer'), default => t('Search Customer Account') }) ?>"
                >
                    <span class="workspace-topbar__search-icon" aria-hidden="true"><?= app_icon('search') ?></span>
                    <label class="visually-hidden" for="workspace-header-search"><?= e(t('Search')) ?></label>
                    <input
                        id="workspace-header-search"
                        type="search"
                        name="q"
                        value="<?= e((string) ($_GET['q'] ?? '')) ?>"
                        placeholder="<?= e(match ($workspaceHeaderArea) { 'dashboard' => t('Search Admin Dashboard'), 'developer' => t('Search Vendor/Developer'), default => t('Search Customer Account') }) ?>"
                        autocomplete="off"
                    >
                </form>

                
            </div>

            <div class="workspace-topbar__actions">
                <!-- BARAMA_WORKSPACE_HEADER_UTILITY_ICON_FIX_V100 -->
                <a class="workspace-topbar__site-link" href="<?= e(url('/')) ?>" aria-label="<?= e(t('View public website')) ?>" title="<?= e(t('View public website')) ?>">
                    <span aria-hidden="true"><?= app_icon('home') ?></span>
                </a>

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
                        <span class="workspace-user-menu__avatar" aria-hidden="true"><?= app_icon('user') ?></span>
                    </summary>
                    <div class="workspace-user-menu__panel">
                        <div class="workspace-user-menu__head">
                            <span class="workspace-user-menu__avatar"><?= app_icon('user') ?></span>
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
                                && in_array($itemPath, ['/account', '/developer', '/dashboard'], true));
                        ?>
                        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e(url($itemPath)) ?>">
                            <span class="workspace-nav__icon"><?= app_icon($itemIcon) ?></span>
                            <span><?= e($itemLabel) ?></span>
                        </a>
                    <?php endforeach; ?>
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
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a class="brand brand--image" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Barama Real Estate home')) ?>">
                        <img
                            class="brand__logo"
                            src="<?= e(asset('images/branding/barama-logo-header.png')) ?>"
                            alt="<?= e(t('Barama Real Estate')) ?>"
                            width="960"
                            height="220"
                        >
                    </a>
                    <p><?= e(t('Property marketplace, developer discovery, and secure real estate management in one focused platform.')) ?></p>
                </div>

                <nav aria-label="<?= e(t('Marketplace links')) ?>">
                    <strong><?= e(t('Marketplace')) ?></strong>
                    <a href="<?= e(url('/properties')) ?>"><?= e(t('Properties')) ?></a>
                    <a href="<?= e(url('/projects')) ?>"><?= e(t('Projects')) ?></a>
                    <a href="<?= e(url('/company')) ?>"><?= e(t('Companies')) ?></a>
                </nav>

                <nav aria-label="<?= e(t('Account links')) ?>">
                    <strong><?= e(t('Accounts')) ?></strong>
                    <?php if ($isAuthenticated): ?>
                        <?php if (auth_workspace_can('account')): ?><a href="<?= e(url('/account')) ?>"><?= e(t('My Account')) ?></a><?php endif; ?>
                        <?php if (auth_workspace_can('developer')): ?><a href="<?= e(url('/developer')) ?>"><?= e(t('Company / Vendor')) ?></a><?php endif; ?>
                        <?php if (auth_workspace_can('dashboard')): ?><a href="<?= e(url('/dashboard')) ?>"><?= e(t('Dashboard')) ?></a><?php endif; ?>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
                        <a href="<?= e(url('/register')) ?>"><?= e(t('Create account')) ?></a>
                        <a href="<?= e(url('/forgot-password')) ?>"><?= e(t('Password recovery')) ?></a>
                    <?php endif; ?>
                </nav>

                <nav aria-label="<?= e(t('Help links')) ?>">
                    <strong><?= e(t('Help')) ?></strong>
                    <a href="<?= e((string) config('app.documentation_url')) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('Documentation')) ?></a>
                    <a href="<?= e((string) config('app.support_url')) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('Contact Support')) ?></a>
                    <a href="<?= e(url('/health')) ?>"><?= e(t('System Health')) ?></a>
                </nav>
            </div>

            <div class="site-footer__bottom">
                <span>© <span data-current-year></span> Barama Real Estate · Version <?= e(APP_VERSION) ?></span>
                <span><?= e(t('Built by Ton Samith')) ?></span>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
