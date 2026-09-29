<?php
declare(strict_types=1);

$errorRequestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
?>
<link rel="stylesheet" href="<?= e(asset('css/error-page-v114.css')) ?>">

<section class="barama-error-page" aria-labelledby="barama-error-title">
    <div class="container app-container">
        <div class="barama-error-page__shell">
            <div class="barama-error-page__copy">
                <span class="eyebrow"><?= e(t('Page not found')) ?></span>
                <div class="barama-error-page__code" aria-hidden="true">404</div>

                <h1 id="barama-error-title"><?= e(t('This page could not be found.')) ?></h1>

                <p>
                    <?= e(t('The address may be outdated, the page may have moved, or the content is no longer available. You can return home or continue browsing the Barama marketplace.')) ?>
                </p>

                <span class="barama-error-page__path"><?= e($errorRequestPath) ?></span>

                <div class="barama-error-page__actions">
                    <a class="btn btn-primary" href="<?= e(url('/')) ?>">
                        <?= e(t('Return home')) ?>
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/properties')) ?>">
                        <?= e(t('Browse properties')) ?>
                    </a>
                </div>
            </div>

            <aside class="barama-error-page__panel" aria-label="<?= e(t('Popular destinations')) ?>">
                <div class="barama-error-page__panel-head">
                    <span class="barama-error-page__panel-icon" aria-hidden="true">
                        <?= app_icon('search') ?>
                    </span>
                    <div>
                        <strong><?= e(t('Continue exploring')) ?></strong>
                        <span><?= e(t('Popular Barama destinations')) ?></span>
                    </div>
                </div>

                <nav class="barama-error-page__links" aria-label="<?= e(t('404 page shortcuts')) ?>">
                    <a href="<?= e(url('/properties')) ?>">
                        <span><?= e(t('Properties')) ?></span>
                        <small aria-hidden="true">→</small>
                    </a>
                    <a href="<?= e(url('/projects')) ?>">
                        <span><?= e(t('Projects')) ?></span>
                        <small aria-hidden="true">→</small>
                    </a>
                    <a href="<?= e(url('/company')) ?>">
                        <span><?= e(t('Companies')) ?></span>
                        <small aria-hidden="true">→</small>
                    </a>
                    <a href="<?= e(url('/contact')) ?>">
                        <span><?= e(t('Contact')) ?></span>
                        <small aria-hidden="true">→</small>
                    </a>
                </nav>
            </aside>
        </div>
    </div>
</section>
