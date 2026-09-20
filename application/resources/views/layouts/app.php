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
                    <a class="<?= ($activeArea ?? '') === 'company' ? 'is-active' : '' ?>" href="<?= e(url('/company')) ?>">Developers</a>
                    <a class="<?= ($activeArea ?? '') === 'account' ? 'is-active' : '' ?>" href="<?= e(url('/account')) ?>">Account</a>
                    <a class="<?= ($activeArea ?? '') === 'developer' ? 'is-active' : '' ?>" href="<?= e(url('/developer')) ?>">Developer</a>
                    <a class="<?= ($activeArea ?? '') === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('/dashboard')) ?>">Admin</a>
                </nav>

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
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="container app-container site-footer__inner">
            <span>© <span data-current-year></span> Barama Real Estate</span>
            <span>Version <?= e(APP_VERSION) ?> · Ton Samith</span>
        </div>
    </footer>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
