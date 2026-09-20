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
    <header class="site-header">
        <div class="container app-container">
            <div class="site-header__inner">
                <a class="brand" href="<?= e(url('/')) ?>" aria-label="Barama Real Estate home">
                    <span class="brand__mark" aria-hidden="true">B</span>
                    <span>
                        <strong>Barama</strong>
                        <small>Real Estate</small>
                    </span>
                </a>

                <nav class="main-nav" aria-label="Primary navigation">
                    <a class="<?= ($activeArea ?? '') === 'home' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>">Home</a>
                    <a class="<?= ($activeArea ?? '') === 'properties' ? 'is-active' : '' ?>" href="<?= e(url('/properties')) ?>">Properties</a>
                    <a class="<?= ($activeArea ?? '') === 'projects' ? 'is-active' : '' ?>" href="<?= e(url('/projects')) ?>">Projects</a>
                    <a class="<?= ($activeArea ?? '') === 'company' ? 'is-active' : '' ?>" href="<?= e(url('/company')) ?>">Developers</a>

                    <?php if (auth_check()): ?>
                        <a class="<?= ($activeArea ?? '') === 'account' ? 'is-active' : '' ?>" href="<?= e(url('/account')) ?>">Account</a>

                        <?php if (auth_can('developer.access')): ?>
                            <a class="<?= ($activeArea ?? '') === 'developer' ? 'is-active' : '' ?>" href="<?= e(url('/developer')) ?>">Developer</a>
                        <?php endif; ?>

                        <?php if (auth_can('dashboard.access')): ?>
                            <a class="<?= ($activeArea ?? '') === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>">Admin</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>

                <div class="header-auth">
                    <?php if (auth_check()): ?>
                        <form action="<?= e(url('/logout')) ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="header-auth__button" type="submit">Sign out</button>
                        </form>
                    <?php else: ?>
                        <a class="main-nav__auth auth-link" href="<?= e(url('/login')) ?>">Sign in</a>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>">Create account</a>
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
    </header>

    <?php if (isset($workspaceNavigation) && is_array($workspaceNavigation)): ?>
        <div class="workspace-shell">
            <aside class="workspace-sidebar" data-workspace-sidebar>
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
                                && in_array($itemPath, ['/developer', '/dashboard'], true));
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
        <div class="container app-container site-footer__inner">
            <span>© <span data-current-year></span> Barama Real Estate · Version <?= e(APP_VERSION) ?></span>
            <span class="site-footer__links">
                <a href="<?= e((string) config('app.documentation_url')) ?>" target="_blank" rel="noopener noreferrer">Documentation</a>
                <a href="<?= e((string) config('app.support_url')) ?>" target="_blank" rel="noopener noreferrer">Support</a>
            </span>
        </div>
    </footer>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
