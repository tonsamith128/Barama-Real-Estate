<?php
declare(strict_types=1);

$headerUser = auth_user();
$isAuthenticated = $headerUser !== null;
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? config('app.name')) ?></title>
    <meta name="description" content="Barama Real Estate marketplace and property management platform.">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
    <header class="site-header" data-site-header>
        <div class="container app-container">
            <div class="site-header__inner">
                <a class="brand" href="<?= e(url('/')) ?>" aria-label="Barama Real Estate home">
                    <span class="brand__mark" aria-hidden="true">B</span>
                    <span>
                        <strong>Barama</strong>
                        <small>Real Estate</small>
                    </span>
                </a>

                <button
                    class="site-menu-toggle"
                    type="button"
                    data-site-menu-toggle
                    aria-controls="site-navigation"
                    aria-expanded="false"
                    aria-label="Open navigation"
                >
                    <?= app_icon('menu') ?>
                </button>

                <div class="site-navigation" id="site-navigation" data-site-navigation>
                    <nav class="main-nav" aria-label="Primary navigation">
                        <a class="<?= ($activeArea ?? '') === 'home' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>">Home</a>
                        <a class="<?= ($activeArea ?? '') === 'properties' ? 'is-active' : '' ?>" href="<?= e(url('/properties')) ?>">Properties</a>
                        <a class="<?= ($activeArea ?? '') === 'projects' ? 'is-active' : '' ?>" href="<?= e(url('/projects')) ?>">Projects</a>
                        <a class="<?= ($activeArea ?? '') === 'company' ? 'is-active' : '' ?>" href="<?= e(url('/company')) ?>">Companies</a>
                    </nav>

                    <div class="header-auth">
                        <?php if ($isAuthenticated): ?>
                            <div class="account-switcher">
                                <a class="account-switcher__identity <?= ($activeArea ?? '') === 'account' ? 'is-active' : '' ?>" href="<?= e(url('/account')) ?>">
                                    <span class="account-switcher__avatar"><?= app_icon('user') ?></span>
                                    <span>
                                        <strong><?= e((string) ($headerUser['name'] ?? 'My account')) ?></strong>
                                        <small>My Account</small>
                                    </span>
                                </a>

                                <?php if (auth_can('developer.access')): ?>
                                    <a class="account-switcher__shortcut <?= ($activeArea ?? '') === 'developer' ? 'is-active' : '' ?>" href="<?= e(url('/developer')) ?>" title="Company / Vendor Workspace">
                                        <?= app_icon('building') ?>
                                        <span>Company / Vendor</span>
                                    </a>
                                <?php endif; ?>

                                <?php if (auth_can('dashboard.access')): ?>
                                    <a class="account-switcher__shortcut <?= ($activeArea ?? '') === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>" title="Admin Dashboard">
                                        <?= app_icon('grid') ?>
                                        <span>Dashboard</span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <form class="header-signout" action="<?= e(url('/logout')) ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" aria-label="Sign out" title="Sign out">
                                    <?= app_icon('lock') ?>
                                    <span>Sign out</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <a class="header-auth__signin" href="<?= e(url('/login')) ?>">Sign in</a>
                            <a class="btn btn-primary btn-sm header-auth__register" href="<?= e(url('/register')) ?>">Create account</a>
                        <?php endif; ?>

                        <button
                            class="theme-toggle"
                            type="button"
                            data-theme-toggle
                            aria-label="Toggle light and dark mode"
                            title="Toggle theme"
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

    <?php if (isset($workspaceNavigation) && is_array($workspaceNavigation)): ?>
        <div class="workspace-shell">
            <aside class="workspace-sidebar" id="workspace-navigation" data-workspace-sidebar>
                <div class="workspace-sidebar__top">
                    <div>
                        <span>Workspace</span>
                        <strong><?= e($workspaceLabel ?? 'Management') ?></strong>
                    </div>
                    <button
                        type="button"
                        class="workspace-sidebar__close"
                        data-workspace-close
                        aria-label="Close workspace navigation"
                    >
                        ×
                    </button>
                </div>

                <nav class="workspace-nav" aria-label="<?= e(($workspaceLabel ?? 'Workspace') . ' navigation') ?>">
                    <?php foreach ($workspaceNavigation as $item): ?>
                        <?php
                        $itemPath = (string) ($item['path'] ?? '/');
                        $itemLabel = (string) ($item['label'] ?? 'Section');
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
                    <span>Barama Real Estate</span>
                    <small>v<?= e(APP_VERSION) ?></small>
                </div>
            </aside>

            <div class="workspace-main">
                <button
                    class="workspace-menu-button"
                    type="button"
                    data-workspace-open
                    aria-controls="workspace-navigation"
                    aria-expanded="false"
                    aria-label="Open workspace navigation"
                >
                    <?= app_icon('menu') ?>
                    <span>Menu</span>
                </button>
                <main>
                    <?= $content ?>
                </main>
            </div>
            <button class="workspace-backdrop" type="button" data-workspace-backdrop aria-label="Close workspace navigation"></button>
        </div>
    <?php else: ?>
        <main>
            <?= $content ?>
        </main>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container app-container">
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a class="brand" href="<?= e(url('/')) ?>">
                        <span class="brand__mark" aria-hidden="true">B</span>
                        <span><strong>Barama</strong><small>Real Estate</small></span>
                    </a>
                    <p>Property marketplace, developer discovery, and secure real estate management in one focused platform.</p>
                </div>

                <nav aria-label="Marketplace links">
                    <strong>Marketplace</strong>
                    <a href="<?= e(url('/properties')) ?>">Properties</a>
                    <a href="<?= e(url('/projects')) ?>">Projects</a>
                    <a href="<?= e(url('/company')) ?>">Companies</a>
                </nav>

                <nav aria-label="Account links">
                    <strong>Accounts</strong>
                    <?php if ($isAuthenticated): ?>
                        <a href="<?= e(url('/account')) ?>">My Account</a>
                        <?php if (auth_can('developer.access')): ?><a href="<?= e(url('/developer')) ?>">Company / Vendor</a><?php endif; ?>
                        <?php if (auth_can('dashboard.access')): ?><a href="<?= e(url('/dashboard')) ?>">Dashboard</a><?php endif; ?>
                    <?php else: ?>
                        <a href="<?= e(url('/login')) ?>">Sign in</a>
                        <a href="<?= e(url('/register')) ?>">Create account</a>
                        <a href="<?= e(url('/forgot-password')) ?>">Password recovery</a>
                    <?php endif; ?>
                </nav>

                <nav aria-label="Help links">
                    <strong>Help</strong>
                    <a href="<?= e((string) config('app.documentation_url')) ?>" target="_blank" rel="noopener noreferrer">Documentation</a>
                    <a href="<?= e((string) config('app.support_url')) ?>" target="_blank" rel="noopener noreferrer">Contact Support</a>
                    <a href="<?= e(url('/health')) ?>">System Health</a>
                </nav>
            </div>

            <div class="site-footer__bottom">
                <span>© <span data-current-year></span> Barama Real Estate · Version <?= e(APP_VERSION) ?></span>
                <span>Built by Ton Samith</span>
            </div>
        </div>
    </footer>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
