<?php

declare(strict_types=1);

$steps = [
    1 => ['License & Terms', 'Verify your purchase and review the license terms.'],
    2 => ['Requirements', 'Confirm the server is ready for Barama Real Estate.'],
    3 => ['Database', 'Connect Barama Real Estate to your database.'],
    4 => ['Website', 'Set up the website and first administrator.'],
    5 => ['System Health / Finish', 'Confirm the installation is healthy and ready.'],
];

$current = $steps[$step] ?? $steps[1];
$assetBase = rtrim((string) $basePath, '/');
$termsReviewed = (bool) $termsReviewed;
$licenseValidated = (bool) $licenseValidated;
$healthChecks = is_array($health['checks'] ?? null)
    ? $health['checks']
    : [];
$siteUrlValue = (string) (
    $oldWebsite['site_url']
    ?? $siteUrl
);
$timezoneValue = (string) (
    $oldWebsite['timezone']
    ?? 'Asia/Phnom_Penh'
);
$languageValue = (string) (
    $oldWebsite['default_language']
    ?? 'en'
);
$mailDriverValue = in_array(
    (string) ($oldWebsite['mail_driver'] ?? 'smtp'),
    ['smtp', 'mail'],
    true
) ? (string) ($oldWebsite['mail_driver'] ?? 'smtp') : 'smtp';
$mailEncryptionValue = in_array(
    (string) ($oldWebsite['mail_encryption'] ?? 'tls'),
    ['tls', 'ssl', 'none'],
    true
) ? (string) ($oldWebsite['mail_encryption'] ?? 'tls') : 'tls';
$mailFromAddressValue = (string) (
    $oldWebsite['mail_from_address']
    ?? $oldWebsite['admin_email']
    ?? ''
);
$mailFromNameValue = (string) (
    $oldWebsite['mail_from_name']
    ?? $oldWebsite['site_name']
    ?? 'Barama Real Estate'
);
$languageFlags = [
    'en' => '🇬🇧',
    'km' => '🇰🇭',
    'zh' => '🇨🇳',
];
$databaseModeValue = in_array(
    (string) ($oldDatabase['db_mode'] ?? 'existing'),
    ['existing', 'create'],
    true
) ? (string) ($oldDatabase['db_mode'] ?? 'existing') : 'existing';
$cssAssetVersion = (string) (
    @filemtime(BASE_PATH . '/assets/css/app.css')
    ?: APP_VERSION
);
$jsAssetVersion = (string) (
    @filemtime(BASE_PATH . '/assets/js/app.js')
    ?: APP_VERSION
);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($pageTitle) ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="<?= e($assetBase . '/assets/css/app.css?v=' . rawurlencode($cssAssetVersion)) ?>"
        rel="stylesheet"
    >
</head>
<body class="installer-body">
<div
    class="installer-shell"
    data-installer
    data-installer-url="<?= e($installerUrl) ?>"
    data-terms-reviewed="<?= $termsReviewed ? '1' : '0' ?>"
    data-license-validated="<?= $licenseValidated ? '1' : '0' ?>"
>
    <aside class="installer-sidebar">
        <div class="installer-brand">
            <span class="brand__mark" aria-hidden="true">B</span>
            <div>
                <strong>Barama Real Estate</strong>
                <span>One-click Installer</span>
            </div>
        </div>

        <ol class="installer-progress" aria-label="Installation progress">
            <?php foreach ($steps as $number => [$label, $description]): ?>
                <?php
                $isActive = $number === $step;
                $isComplete = $number < $step
                    || ($step === 5 && $number < 5);
                ?>
                <li
                    class="<?= $isActive ? 'is-active' : '' ?> <?= $isComplete ? 'is-complete' : '' ?>"
                    data-installer-step-item="<?= e((string) $number) ?>"
                    <?= $isActive ? 'aria-current="step"' : '' ?>
                >
                    <span class="installer-progress__number">
                        <?php if ($isComplete): ?>
                            <?= app_icon('check') ?>
                        <?php else: ?>
                            <?= e((string) $number) ?>
                        <?php endif; ?>
                    </span>
                    <span>
                        <strong><?= e($label) ?></strong>
                        <small><?= e($description) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="installer-sidebar__footer">
            <span>Barama Real Estate</span>
            <small>Version <?= e(APP_VERSION) ?></small>
        </div>
    </aside>

    <section class="installer-workspace">
        <header class="installer-topbar">
            <span>Step <?= e((string) $step) ?> of 5</span>

            <div class="installer-utilities">
                <label class="installer-language">
                    <span class="installer-language__icon" aria-hidden="true">
                        <?= app_icon('language') ?>
                    </span>
                    <span class="visually-hidden">Installer language</span>
                    <select
                        aria-label="Installer language"
                        data-installer-language
                    >
                        <?php foreach ($languages as $code => $name): ?>
                            <option value="<?= e((string) $code) ?>">
                                <?= e(($languageFlags[$code] ?? '🌐') . ' ' . (string) $name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

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
        </header>

        <main class="installer-main">
            <header class="installer-heading">
                <span class="eyebrow">Installation</span>
                <h1><?= e($current[0]) ?></h1>
                <p><?= e($current[1]) ?></p>
            </header>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="installer-alert is-error" role="alert">
                    <?= app_icon('alert') ?>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <div
                class="installer-alert"
                data-installer-notice
                role="status"
                hidden
            ></div>

            <?php if ($step === 1): ?>
                <section class="installer-card">
                    <div class="installer-card__head">
                        <div>
                            <span><?= app_icon('key') ?></span>
                            <div>
                                <h2>Purchase verification</h2>
                                <p>Enter your purchase code to verify this Barama Real Estate installation.</p>
                            </div>
                        </div>
                    </div>

                    <form
                        action="<?= e($installerUrl) ?>"
                        method="post"
                        data-installer-license-form
                    >
                        <?= csrf_field() ?>

                        <label class="installer-field">
                            <span class="installer-field__label">Purchase code / license key</span>
                            <span class="installer-input installer-input--action">
                                <span class="installer-input__icon">
                                    <?= app_icon('key') ?>
                                </span>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="purchase_code"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    maxlength="120"
                                    placeholder="Enter your purchase code or license key"
                                    data-purchase-code
                                >
                                <button
                                    class="installer-input__action"
                                    type="submit"
                                    name="action"
                                    value="validate_license"
                                    data-license-validate
                                    disabled
                                >
                                    Validate
                                </button>
                            </span>
                        </label>

                        <div
                            class="installer-license-status <?= $licenseValidated ? 'is-valid' : '' ?>"
                            data-license-status
                            <?= $licenseValidated ? '' : 'hidden' ?>
                        >
                            <?= app_icon('check') ?>
                            <span>
                                <?= $licenseValidated
                                    ? 'License validated successfully.'
                                    : '' ?>
                            </span>
                        </div>

                        <div class="installer-terms-row">
                            <label class="installer-agreement">
                                <input
                                    type="checkbox"
                                    name="agree_terms"
                                    value="1"
                                    data-terms-agreement
                                >
                                <span>I agree to the Envato license terms, author disclaimer and support terms.</span>
                            </label>
                        </div>

                        <div class="installer-actions">
                            <span class="installer-actions__note">
                                Your code is used only to verify this licensed installation.
                            </span>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                name="action"
                                value="continue_license"
                                data-license-continue
                                <?= ($licenseValidated && $termsReviewed) ? '' : 'disabled' ?>
                            >
                                Verify &amp; Continue
                            </button>
                        </div>
                    </form>
                </section>

                <div
                    class="installer-modal"
                    data-installer-terms-modal
                    hidden
                    aria-hidden="true"
                >
                    <div
                        class="installer-modal__dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="installer-terms-title"
                    >
                        <div class="installer-modal__head">
                            <div>
                                <span class="eyebrow">License &amp; Terms</span>
                                <h2 id="installer-terms-title">Terms of Use</h2>
                            </div>
                            <button
                                type="button"
                                data-close-terms
                                aria-label="Close terms"
                            >×</button>
                        </div>

                        <div class="installer-modal__body">
                            <section>
                                <h3>Terms of Use</h3>
                                <p>Use Barama Real Estate only within the rights granted by your applicable purchase license. Do not redistribute, resell, publish, share, or sublicense the product source except where the applicable license expressly allows it.</p>
                            </section>
                            <section>
                                <h3>Envato Market License &amp; Terms</h3>
                                <p>CodeCanyon purchases are governed by the current Envato Market license and terms that apply to your purchase. You should read those terms in full on Envato before accepting this installer agreement. Purchase verification only confirms eligibility to activate this installation; it does not replace, amend, or override Envato's terms.</p>
                                <p><a href="https://codecanyon.net/licenses/standard" target="_blank" rel="noopener noreferrer">Read the Envato Standard License</a> and review the current Envato Market terms applicable to your account and purchase.</p>
                            </section>
                            <section>
                                <h3>Author Disclaimer</h3>
                                <p>The product is provided for use on a compatible hosting environment. To the extent permitted by applicable law, the author is not responsible for hosting outages, third-party service failures, customer content, data loss caused by unsupported server changes, or problems introduced by unauthorized modifications. Keep appropriate backups before installation, updates, or custom changes.</p>
                            </section>
                            <section>
                                <h3>Support from the Author</h3>
                                <p>Product documentation and eligible support are provided by the author through the official documentation and support channels listed with the product. Support is subject to the applicable Envato support terms and the scope of the purchased item.</p>
                            </section>
                        </div>

                        <div class="installer-modal__footer">
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                data-close-terms
                            >
                                Close
                            </button>
                            <button
                                class="btn btn-primary"
                                type="button"
                                data-accept-terms
                            >
                                Accept Terms
                            </button>
                        </div>
                    </div>
                </div>

            <?php elseif ($step === 2): ?>
                <section class="installer-card installer-card--plain">
                    <div class="installer-card__head">
                        <div>
                            <span><?= app_icon('server') ?></span>
                            <div>
                                <h2>Server requirements</h2>
                                <p>Barama checks your server automatically. Resolve only items marked Fix Required.</p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="installer-requirements-table"
                        role="table"
                        aria-label="Server requirements"
                    >
                        <div class="installer-requirements-table__head" role="row">
                            <span role="columnheader">Requirement</span>
                            <span role="columnheader">Detected / Details</span>
                            <span role="columnheader">Status</span>
                        </div>
                        <?php foreach ($requirements as $check): ?>
                            <?php
                            $status = (string) ($check['status'] ?? 'fix');
                            $label = match ($status) {
                                'pass' => 'Pass',
                                'warning' => 'Warning',
                                default => 'Fix Required',
                            };
                            ?>
                            <div class="installer-requirements-table__row" role="row">
                                <div role="cell">
                                    <span class="installer-requirement-icon installer-requirement-icon--<?= e($status) ?>">
                                        <?= app_icon(
                                            $status === 'pass'
                                                ? 'check'
                                                : 'alert'
                                        ) ?>
                                    </span>
                                    <strong><?= e((string) $check['label']) ?></strong>
                                </div>
                                <span role="cell"><?= e((string) $check['detail']) ?></span>
                                <span role="cell">
                                    <span class="installer-status installer-status--<?= e($status) ?>">
                                        <?= e($label) ?>
                                    </span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form action="<?= e($installerUrl) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="installer-actions">
                            <span class="installer-actions__note">
                                Warnings do not block installation.
                            </span>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                name="action"
                                value="continue_requirements"
                                <?= $requirementsReady ? '' : 'disabled' ?>
                            >
                                Continue
                            </button>
                        </div>
                    </form>
                </section>

            <?php elseif ($step === 3): ?>
                <section class="installer-card installer-card--plain">
                    <div class="installer-card__head">
                        <div>
                            <span><?= app_icon('database') ?></span>
                            <div>
                                <h2>Database setup</h2>
                                <p>Connect an existing database or let Barama create a dedicated database and user.</p>
                            </div>
                        </div>
                    </div>

                    <form
                        action="<?= e($installerUrl) ?>"
                        method="post"
                        class="installer-form"
                        data-installer-database-form
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="test_database">

                        <label class="installer-field installer-field--wide">
                            <span class="installer-field__label">Database setup option</span>
                            <span class="installer-input">
                                <span class="installer-input__icon">
                                    <?= app_icon('database') ?>
                                </span>
                                <select
                                    class="form-select"
                                    name="db_mode"
                                    data-database-mode
                                >
                                    <option
                                        value="existing"
                                        <?= $databaseModeValue === 'existing' ? 'selected' : '' ?>
                                    >
                                        Existing Database
                                    </option>
                                    <option
                                        value="create"
                                        <?= $databaseModeValue === 'create' ? 'selected' : '' ?>
                                    >
                                        Create Database
                                    </option>
                                </select>
                            </span>
                            <small>
                                Choose Existing Database when your hosting panel already provides database credentials. Choose Create Database only when you have MySQL/MariaDB administrative access.
                            </small>
                        </label>

                        <div
                            class="installer-database-mode"
                            data-database-mode-panel="existing"
                            <?= $databaseModeValue === 'existing' ? '' : 'hidden' ?>
                        >
                            <div class="installer-section-label">Existing Database</div>
                            <p class="installer-section-copy">
                                Enter the database credentials provided by your hosting provider.
                            </p>

                            <div class="installer-form__grid">
                                <label class="installer-field">
                                    <span class="installer-field__label">Database host</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('server') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_host"
                                            value="<?= e((string) ($oldDatabase['db_host'] ?? '127.0.0.1')) ?>"
                                            maxlength="255"
                                            autocomplete="off"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Port</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('server') ?></span>
                                        <input
                                            class="form-control"
                                            type="number"
                                            name="db_port"
                                            min="1"
                                            max="65535"
                                            value="<?= e((string) ($oldDatabase['db_port'] ?? 3306)) ?>"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Database name</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('database') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_database"
                                            value="<?= e((string) ($oldDatabase['db_database'] ?? '')) ?>"
                                            maxlength="64"
                                            autocomplete="off"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Database username</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('user') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_username"
                                            value="<?= e((string) ($oldDatabase['db_username'] ?? '')) ?>"
                                            maxlength="191"
                                            autocomplete="username"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field installer-field--wide">
                                    <span class="installer-field__label">Database password</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('lock') ?></span>
                                        <input
                                            class="form-control"
                                            type="password"
                                            name="db_password"
                                            autocomplete="new-password"
                                            data-password-field
                                        >
                                        <button
                                            class="installer-input__icon-button"
                                            type="button"
                                            data-password-toggle
                                            aria-label="Show password"
                                        >
                                            <?= app_icon('eye') ?>
                                        </button>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div
                            class="installer-database-mode"
                            data-database-mode-panel="create"
                            <?= $databaseModeValue === 'create' ? '' : 'hidden' ?>
                        >
                            <div class="installer-section-label">Database Server Admin Access</div>
                            <p class="installer-section-copy">
                                These privileged credentials are used only to create the database and dedicated database user. They are never stored in Barama.
                            </p>

                            <div class="installer-form__grid">
                                <label class="installer-field">
                                    <span class="installer-field__label">Admin host</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('server') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_admin_host"
                                            value="<?= e((string) ($oldDatabase['db_admin_host'] ?? '127.0.0.1')) ?>"
                                            maxlength="255"
                                            autocomplete="off"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Admin port</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('server') ?></span>
                                        <input
                                            class="form-control"
                                            type="number"
                                            name="db_admin_port"
                                            min="1"
                                            max="65535"
                                            value="<?= e((string) ($oldDatabase['db_admin_port'] ?? 3306)) ?>"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Admin username</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('user') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_admin_username"
                                            value="<?= e((string) ($oldDatabase['db_admin_username'] ?? '')) ?>"
                                            maxlength="191"
                                            autocomplete="username"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Admin password</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('lock') ?></span>
                                        <input
                                            class="form-control"
                                            type="password"
                                            name="db_admin_password"
                                            autocomplete="new-password"
                                            data-password-field
                                        >
                                        <button
                                            class="installer-input__icon-button"
                                            type="button"
                                            data-password-toggle
                                            aria-label="Show password"
                                        >
                                            <?= app_icon('eye') ?>
                                        </button>
                                    </span>
                                </label>
                            </div>

                            <div class="installer-section-label">New Database Details</div>
                            <p class="installer-section-copy">
                                Barama creates this database and a dedicated user with privileges limited to the new database.
                            </p>

                            <div class="installer-form__grid">
                                <label class="installer-field">
                                    <span class="installer-field__label">New database name</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('database') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_new_database"
                                            value="<?= e((string) ($oldDatabase['db_new_database'] ?? '')) ?>"
                                            maxlength="64"
                                            autocomplete="off"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">New database username</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('user') ?></span>
                                        <input
                                            class="form-control"
                                            name="db_new_username"
                                            value="<?= e((string) ($oldDatabase['db_new_username'] ?? '')) ?>"
                                            maxlength="32"
                                            autocomplete="off"
                                        >
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">New database password</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('lock') ?></span>
                                        <input
                                            class="form-control"
                                            type="password"
                                            name="db_new_password"
                                            minlength="12"
                                            autocomplete="new-password"
                                            data-password-field
                                        >
                                        <button
                                            class="installer-input__icon-button"
                                            type="button"
                                            data-password-toggle
                                            aria-label="Show password"
                                        >
                                            <?= app_icon('eye') ?>
                                        </button>
                                    </span>
                                </label>

                                <label class="installer-field">
                                    <span class="installer-field__label">Confirm database password</span>
                                    <span class="installer-input">
                                        <span class="installer-input__icon"><?= app_icon('lock') ?></span>
                                        <input
                                            class="form-control"
                                            type="password"
                                            name="db_new_password_confirmation"
                                            minlength="12"
                                            autocomplete="new-password"
                                            data-password-field
                                        >
                                        <button
                                            class="installer-input__icon-button"
                                            type="button"
                                            data-password-toggle
                                            aria-label="Show password"
                                        >
                                            <?= app_icon('eye') ?>
                                        </button>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="installer-actions">
                            <span class="installer-actions__note">
                                Passwords are never repopulated after validation errors.
                            </span>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                data-database-submit
                            >
                                <?= $databaseModeValue === 'create'
                                    ? 'Create Database & Continue'
                                    : 'Test Connection & Continue' ?>
                            </button>
                        </div>
                    </form>
                </section>

            <?php elseif ($step === 4): ?>
                <section class="installer-card">
                    <div class="installer-card__head">
                        <div>
                            <span><?= app_icon('globe') ?></span>
                            <div>
                                <h2>Website &amp; administrator</h2>
                                <p>These details create your website configuration and first administrator account.</p>
                            </div>
                        </div>
                    </div>

                    <form
                        action="<?= e($installerUrl) ?>"
                        method="post"
                        class="installer-form"
                        data-installer-final-form
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="install">

                        <div class="installer-section-label">Website</div>

                        <div class="installer-form__grid">
                            <label class="installer-field">
                                <span class="installer-field__label">Website name</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('building') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        name="site_name"
                                        value="<?= e((string) ($oldWebsite['site_name'] ?? 'Barama Real Estate')) ?>"
                                        required
                                        maxlength="120"
                                    >
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Website URL</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('globe') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="url"
                                        name="site_url"
                                        value="<?= e($siteUrlValue) ?>"
                                        required
                                    >
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Timezone</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('clock') ?>
                                    </span>
                                    <select
                                        class="form-select"
                                        name="timezone"
                                        required
                                    >
                                        <?php foreach ($timezones as $timezone): ?>
                                            <option
                                                value="<?= e($timezone) ?>"
                                                <?= $timezoneValue === $timezone ? 'selected' : '' ?>
                                            >
                                                <?= e($timezone) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Default language</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('language') ?>
                                    </span>
                                    <select
                                        class="form-select"
                                        name="default_language"
                                        required
                                    >
                                        <?php foreach ($languages as $code => $label): ?>
                                            <option
                                                value="<?= e($code) ?>"
                                                <?= $languageValue === $code ? 'selected' : '' ?>
                                            >
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            </label>
                        </div>

                        <div class="installer-section-label">First administrator</div>

                        <div class="installer-form__grid">
                            <label class="installer-field">
                                <span class="installer-field__label">Administrator name</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('user') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        name="admin_name"
                                        value="<?= e((string) ($oldWebsite['admin_name'] ?? '')) ?>"
                                        required
                                        maxlength="120"
                                        autocomplete="name"
                                    >
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Administrator email</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('mail') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="email"
                                        name="admin_email"
                                        value="<?= e((string) ($oldWebsite['admin_email'] ?? '')) ?>"
                                        required
                                        maxlength="191"
                                        autocomplete="email"
                                    >
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Administrator password</span>
                                <span class="installer-input installer-input--action">
                                    <span class="installer-input__icon">
                                        <?= app_icon('lock') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="password"
                                        name="admin_password"
                                        required
                                        minlength="12"
                                        autocomplete="new-password"
                                        data-admin-password
                                        data-password-field
                                    >
                                    <button
                                        class="installer-input__action"
                                        type="button"
                                        data-generate-password
                                    >
                                        Generate
                                    </button>
                                </span>
                                <small>12+ characters with uppercase, lowercase, number, and symbol.</small>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">Confirm password</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('lock') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="password"
                                        name="admin_password_confirmation"
                                        required
                                        minlength="12"
                                        autocomplete="new-password"
                                        data-admin-password-confirmation
                                        data-password-field
                                    >
                                    <button
                                        class="installer-input__icon-button"
                                        type="button"
                                        data-password-toggle
                                        aria-label="Show password"
                                    >
                                        <?= app_icon('eye') ?>
                                    </button>
                                </span>
                            </label>
                        </div>

                        <div class="installer-section-label">Email delivery</div>

                        <div class="installer-form__grid">
                            <label class="installer-field">
                                <span class="installer-field__label">Delivery method</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('mail') ?>
                                    </span>
                                    <select
                                        class="form-select"
                                        name="mail_driver"
                                        data-mail-driver
                                        data-mail-setting
                                        required
                                    >
                                        <option value="smtp" <?= $mailDriverValue === 'smtp' ? 'selected' : '' ?>>
                                            SMTP (recommended)
                                        </option>
                                        <option value="mail" <?= $mailDriverValue === 'mail' ? 'selected' : '' ?>>
                                            PHP mail
                                        </option>
                                    </select>
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">From email</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('mail') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="email"
                                        name="mail_from_address"
                                        value="<?= e($mailFromAddressValue) ?>"
                                        maxlength="191"
                                        autocomplete="email"
                                        data-mail-setting
                                        data-mail-from-address
                                        required
                                    >
                                </span>
                            </label>

                            <label class="installer-field">
                                <span class="installer-field__label">From name</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('building') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        name="mail_from_name"
                                        value="<?= e($mailFromNameValue) ?>"
                                        maxlength="120"
                                        data-mail-setting
                                        data-mail-from-name
                                        required
                                    >
                                </span>
                            </label>

                            <label class="installer-field" data-mail-smtp-field>
                                <span class="installer-field__label">SMTP host</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('server') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        name="mail_host"
                                        value="<?= e((string) ($oldWebsite['mail_host'] ?? '')) ?>"
                                        maxlength="255"
                                        autocomplete="off"
                                        placeholder="smtp.example.com"
                                        data-mail-setting
                                    >
                                </span>
                            </label>

                            <label class="installer-field" data-mail-smtp-field>
                                <span class="installer-field__label">SMTP port</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('server') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="number"
                                        name="mail_port"
                                        min="1"
                                        max="65535"
                                        value="<?= e((string) ($oldWebsite['mail_port'] ?? 587)) ?>"
                                        data-mail-setting
                                    >
                                </span>
                            </label>

                            <label class="installer-field" data-mail-smtp-field>
                                <span class="installer-field__label">Encryption</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('shield') ?>
                                    </span>
                                    <select
                                        class="form-select"
                                        name="mail_encryption"
                                        data-mail-setting
                                    >
                                        <option value="tls" <?= $mailEncryptionValue === 'tls' ? 'selected' : '' ?>>
                                            TLS / STARTTLS
                                        </option>
                                        <option value="ssl" <?= $mailEncryptionValue === 'ssl' ? 'selected' : '' ?>>
                                            SSL / TLS
                                        </option>
                                        <option value="none" <?= $mailEncryptionValue === 'none' ? 'selected' : '' ?>>
                                            None
                                        </option>
                                    </select>
                                </span>
                            </label>

                            <label class="installer-field" data-mail-smtp-field>
                                <span class="installer-field__label">SMTP username</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('user') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        name="mail_username"
                                        value="<?= e((string) ($oldWebsite['mail_username'] ?? '')) ?>"
                                        maxlength="191"
                                        autocomplete="username"
                                        data-mail-setting
                                    >
                                </span>
                            </label>

                            <label class="installer-field" data-mail-smtp-field>
                                <span class="installer-field__label">SMTP password</span>
                                <span class="installer-input">
                                    <span class="installer-input__icon">
                                        <?= app_icon('lock') ?>
                                    </span>
                                    <input
                                        class="form-control"
                                        type="password"
                                        name="mail_password"
                                        autocomplete="new-password"
                                        data-password-field
                                        data-mail-setting
                                    >
                                    <button
                                        class="installer-input__icon-button"
                                        type="button"
                                        data-password-toggle
                                        aria-label="Show password"
                                    >
                                        <?= app_icon('eye') ?>
                                    </button>
                                </span>
                                <small>The SMTP password is never restored after a validation error.</small>
                            </label>
                        </div>

                        <div
                            class="installer-alert"
                            data-mail-test-status
                            role="status"
                            hidden
                        ></div>

                        <div class="installer-actions">
                            <span class="installer-actions__note">
                                Send a test email to the administrator address before installation.
                            </span>
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                data-mail-test
                            >
                                <?= app_icon('mail') ?>
                                Send Test Email
                            </button>
                        </div>

                        <div class="installer-actions">
                            <span class="installer-actions__note">
                                Installation will run automatically when you continue.
                            </span>
                            <button
                                class="btn btn-primary"
                                type="submit"
                                data-install-submit
                            >
                                Install Barama Real Estate
                            </button>
                        </div>
                    </form>
                </section>

            <?php elseif ($step === 5): ?>
                <section class="installer-finish">
                    <div class="installer-finish__hero">
                        <span class="installer-finish__icon">
                            <?= app_icon(
                                !empty($health['healthy'])
                                    ? 'check'
                                    : 'alert'
                            ) ?>
                        </span>
                        <span class="eyebrow">Installation Complete</span>
                        <h2>
                            <?= !empty($health['healthy'])
                                ? 'Barama Real Estate is ready.'
                                : 'Installation finished with a health warning.' ?>
                        </h2>
                        <p>
                            <?= !empty($health['healthy'])
                                ? 'The website is installed, secured, and ready to use.'
                                : 'Review any Fix Required item before using the live website.' ?>
                        </p>
                    </div>

                    <div class="installer-health-grid">
                        <?php foreach ($healthChecks as $check): ?>
                            <?php
                            $status = (string) ($check['status'] ?? 'fix');
                            ?>
                            <article class="installer-health">
                                <span class="installer-check__icon installer-check__icon--<?= e($status) ?>">
                                    <?= app_icon(
                                        $status === 'pass'
                                            ? 'check'
                                            : 'alert'
                                    ) ?>
                                </span>
                                <div>
                                    <strong><?= e((string) $check['label']) ?></strong>
                                    <small><?= e((string) $check['detail']) ?></small>
                                </div>
                                <span class="installer-status installer-status--<?= e($status) ?>">
                                    <?= $status === 'pass' ? 'Pass' : 'Fix Required' ?>
                                </span>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="installer-finish__actions">
                        <a
                            class="btn btn-primary"
                            href="<?= e((string) ($health['site_url'] ?? $siteUrl)) ?>"
                        >
                            Homepage
                        </a>
                        <a
                            class="btn btn-outline-secondary"
                            href="<?= e(rtrim((string) ($health['site_url'] ?? $siteUrl), '/') . '/login') ?>"
                        >
                            Sign In
                        </a>
                        <a
                            class="btn btn-outline-secondary"
                            href="<?= e((string) config('app.documentation_url')) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Online Documentation
                        </a>
                        <a
                            class="btn btn-outline-secondary"
                            href="<?= e((string) config('app.support_url')) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Contact Support
                        </a>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </section>
</div>

<script
    src="<?= e($assetBase . '/assets/js/app.js?v=' . rawurlencode($jsAssetVersion)) ?>"
    defer
></script>
</body>
</html>
