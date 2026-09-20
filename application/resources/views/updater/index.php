<?php

declare(strict_types=1);

$currentVersion = (string) ($state['current_version'] ?? 'unknown');
$targetVersion = (string) ($state['target_version'] ?? APP_VERSION);
$pending = is_array($state['pending_migrations'] ?? null)
    ? $state['pending_migrations']
    : [];
$issues = is_array($state['issues'] ?? null) ? $state['issues'] : [];
$upToDate = !empty($state['up_to_date']);
$ready = !empty($state['ready']);
$assetBase = rtrim((string) $basePath, '/');
$completed = is_array($result) && !empty($result['upgraded']);
$alreadyCurrent = is_array($result) && !empty($result['already_current']);
$siteUrl = rtrim((string) ($state['site_url'] ?? ''), '/');
$homepage = $siteUrl !== '' ? $siteUrl : ($assetBase !== '' ? $assetBase : '/');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($assetBase . '/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="installer-body">
<div class="installer-shell">
    <aside class="installer-sidebar">
        <div class="installer-brand">
            <span class="brand__mark" aria-hidden="true">B</span>
            <div>
                <strong>Barama Real Estate</strong>
                <span>Secure Updater</span>
            </div>
        </div>

        <ol class="installer-progress" aria-label="Upgrade progress">
            <li class="is-complete">
                <span class="installer-progress__number"><?= app_icon('check') ?></span>
                <span><strong>Existing installation</strong><small><?= e($currentVersion) ?></small></span>
            </li>
            <li class="<?= $completed || $alreadyCurrent || $upToDate ? 'is-complete' : 'is-active' ?>">
                <span class="installer-progress__number">
                    <?= $completed || $alreadyCurrent || $upToDate ? app_icon('check') : '2' ?>
                </span>
                <span><strong>Authenticate &amp; migrate</strong><small>Administrator approval and pending migrations.</small></span>
            </li>
            <li class="<?= $completed || $alreadyCurrent || $upToDate ? 'is-active' : '' ?>">
                <span class="installer-progress__number">3</span>
                <span><strong>Finish</strong><small>Confirm the recorded application version.</small></span>
            </li>
        </ol>

        <div class="installer-sidebar__footer">
            <span>Target version</span>
            <small><?= e($targetVersion) ?></small>
        </div>
    </aside>

    <section class="installer-workspace">
        <header class="installer-topbar">
            <div>
                <span class="eyebrow">Release upgrade</span>
                <h1>Upgrade Barama Real Estate</h1>
                <p>Apply only the database migrations required by this package.</p>
            </div>
            <button class="installer-theme" type="button" data-theme-toggle aria-label="Toggle theme">
                <?= app_icon('eye') ?>
                <span>Theme</span>
            </button>
        </header>

        <main class="installer-content">
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="installer-notice is-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($completed): ?>
                <section class="installer-finish">
                    <div class="installer-finish__hero">
                        <span class="installer-finish__icon"><?= app_icon('check') ?></span>
                        <span class="eyebrow">Upgrade Complete</span>
                        <h2>Barama Real Estate is now <?= e((string) $result['version']) ?>.</h2>
                        <p><?= e((string) count((array) ($result['migrations'] ?? []))) ?> pending migration(s) were applied successfully.</p>
                    </div>
                    <div class="installer-finish__actions">
                        <a class="btn btn-primary" href="<?= e($homepage) ?>">Open website</a>
                        <a class="btn btn-outline-secondary" href="<?= e(rtrim($homepage, '/') . '/login') ?>">Sign in</a>
                    </div>
                </section>
            <?php elseif ($upToDate || $alreadyCurrent): ?>
                <section class="installer-finish">
                    <div class="installer-finish__hero">
                        <span class="installer-finish__icon"><?= app_icon('check') ?></span>
                        <span class="eyebrow">Up to Date</span>
                        <h2>No upgrade is required.</h2>
                        <p>The installed version and database schema already match <?= e($targetVersion) ?>.</p>
                    </div>
                    <div class="installer-finish__actions">
                        <a class="btn btn-primary" href="<?= e($homepage) ?>">Return to website</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="installer-card">
                    <div class="installer-card__head">
                        <div>
                            <span><?= app_icon('shield') ?></span>
                            <div>
                                <h2>Upgrade authorization</h2>
                                <p>Authenticate an active administrator and confirm your backup before database changes begin.</p>
                            </div>
                        </div>
                    </div>

                    <div class="installer-health-grid">
                        <article class="installer-health">
                            <span class="installer-check__icon installer-check__icon--pass"><?= app_icon('check') ?></span>
                            <div><strong>Installed version</strong><small><?= e($currentVersion) ?></small></div>
                            <span class="installer-status installer-status--pass">Detected</span>
                        </article>
                        <article class="installer-health">
                            <span class="installer-check__icon installer-check__icon--<?= $ready ? 'pass' : 'fix' ?>"><?= app_icon($ready ? 'check' : 'alert') ?></span>
                            <div><strong>Upgrade preflight</strong><small><?= $ready ? 'Runtime, database, license state, and storage are ready.' : 'Resolve the required checks before upgrading.' ?></small></div>
                            <span class="installer-status installer-status--<?= $ready ? 'pass' : 'fix' ?>"><?= $ready ? 'Pass' : 'Fix Required' ?></span>
                        </article>
                        <article class="installer-health">
                            <span class="installer-check__icon installer-check__icon--pass"><?= app_icon('layers') ?></span>
                            <div><strong>Pending migrations</strong><small><?= e((string) count($pending)) ?> migration(s) will be applied.</small></div>
                            <span class="installer-status installer-status--pass"><?= e((string) count($pending)) ?></span>
                        </article>
                    </div>

                    <?php if ($issues !== []): ?>
                        <div class="installer-requirements">
                            <?php foreach ($issues as $issue): ?>
                                <article class="installer-check">
                                    <span class="installer-check__icon installer-check__icon--fix"><?= app_icon('alert') ?></span>
                                    <div><strong>Fix Required</strong><small><?= e((string) $issue) ?></small></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($pending !== []): ?>
                        <div class="installer-section-label">Pending database changes</div>
                        <div class="installer-requirements">
                            <?php foreach ($pending as $migration): ?>
                                <article class="installer-check">
                                    <span class="installer-check__icon installer-check__icon--pass"><?= app_icon('layers') ?></span>
                                    <div><strong><?= e((string) $migration) ?></strong><small>Ordered migration</small></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= e($updaterUrl) ?>" method="post" class="installer-form">
                        <?= csrf_field() ?>
                        <div class="installer-section-label">Administrator confirmation</div>
                        <div class="installer-form__grid">
                            <label class="installer-field">
                                <span class="installer-field__label">Administrator email</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon"><?= app_icon('mail') ?></span>
                                    <input class="form-control" type="email" name="admin_email" maxlength="191" autocomplete="username" required>
                                </span>
                            </label>
                            <label class="installer-field">
                                <span class="installer-field__label">Administrator password</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon"><?= app_icon('lock') ?></span>
                                    <input class="form-control" type="password" name="admin_password" autocomplete="current-password" required>
                                </span>
                            </label>
                        </div>

                        <label class="installer-option">
                            <input type="checkbox" name="backup_confirmed" value="1" required>
                            <span>
                                <strong>I created a current database and files backup.</strong>
                                <small>Keep the backup until the upgraded website has been validated.</small>
                            </span>
                        </label>

                        <div class="installer-actions">
                            <span class="installer-actions__note">The updater validates the installed activation state and never asks for your original purchase code.</span>
                            <button class="btn btn-primary" type="submit" <?= !$ready ? 'disabled' : '' ?>>Authenticate &amp; Upgrade</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </section>
</div>
<script src="<?= e($assetBase . '/assets/js/app.js') ?>" defer></script>
</body>
</html>
