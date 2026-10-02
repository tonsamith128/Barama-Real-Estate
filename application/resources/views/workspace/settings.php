<?php
/* BARAMA_ADMIN_SETTINGS_WORKSPACE_V120 */
$profile = is_array($profile ?? null) ? $profile : [];
$preferences = is_array($preferences ?? null) ? $preferences : [];
$storage = is_array($storageDiagnostics ?? null) ? $storageDiagnostics : [];
$section = (string) ($settingsSection ?? 'basic');
$workspace = (string) ($settingsWorkspace ?? 'account');
$base = (string) ($settingsBasePath ?? '/account/settings');
$emailVerified = !empty($profile['email_verified_at']);
$accountStatus = trim((string) ($profile['status'] ?? 'active')) ?: 'active';
$lastLogin = trim((string) ($profile['last_login_at'] ?? ''));
$savedFilters = is_array($preferences['last_filters'] ?? null)
    ? $preferences['last_filters']
    : [];
$workspaceName = $workspace === 'dashboard'
    ? 'Admin Dashboard'
    : ($workspace === 'developer' ? 'Vendor / Developer' : 'Customer Account');
$workspaceIcon = $workspace === 'dashboard'
    ? 'shield'
    : ($workspace === 'developer' ? 'building' : 'user');

$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    foreach ($units as $unit) {
        $value /= 1024;
        if ($value < 1024 || $unit === 'TB') {
            return number_format($value, $value >= 100 ? 0 : 1) . ' ' . $unit;
        }
    }

    return $bytes . ' B';
};

$settingsTabs = [
    ['key' => 'basic', 'label' => 'Basic', 'icon' => 'settings'],
    ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user'],
    ['key' => 'privacy', 'label' => 'Privacy', 'icon' => 'shield'],
    ['key' => 'security', 'label' => 'Security', 'icon' => 'lock'],
];

if ($workspace === 'dashboard') {
    $settingsTabs[] = ['key' => 'storage', 'label' => 'Storage', 'icon' => 'image'];
}
?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-v120.css') . '?v=120') ?>">

<section class="workspace-page barama-settings" data-admin-settings>
    <header class="barama-settings__hero">
        <div class="barama-settings__hero-copy">
            <span class="eyebrow"><?= e(strtoupper(
                $workspace === 'dashboard'
                    ? 'Admin Settings'
                    : ($workspace === 'developer' ? 'Vendor / Developer Settings' : 'Customer Settings')
            )) ?></span>
            <h1><?= e($settingsTitle) ?></h1>
            <p><?= e($settingsDescription) ?></p>
        </div>
        <div class="barama-settings__workspace-badge">
            <span class="barama-settings__workspace-icon"><?= app_icon($workspaceIcon) ?></span>
            <span>
                <strong><?= e($workspaceName) ?></strong>
                <small><?= e(ucfirst($section)) ?> configuration</small>
            </span>
        </div>
    </header>

    <nav class="barama-settings-tabs" aria-label="Settings sections">
        <?php foreach ($settingsTabs as $tab): ?>
            <?php
            $tabPath = $base . '/' . $tab['key'];
            $isTabActive = $section === $tab['key'];
            ?>
            <a
                href="<?= e(url($tabPath)) ?>"
                class="<?= $isTabActive ? 'is-active' : '' ?>"
                <?= $isTabActive ? 'aria-current="page"' : '' ?>
            >
                <span><?= app_icon($tab['icon']) ?></span>
                <strong><?= e($tab['label']) ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($saved) || !empty($error)): ?>
        <div
            class="barama-settings-toast <?= !empty($error) ? 'is-error' : 'is-success' ?>"
            role="status"
            aria-live="polite"
            data-settings-toast
        >
            <span class="barama-settings-toast__icon">
                <?= app_icon(!empty($error) ? 'alert' : 'check') ?>
            </span>
            <span>
                <strong><?= !empty($error) ? 'Settings were not changed' : 'Saved successfully' ?></strong>
                <small><?= e(!empty($error)
                    ? (string) $error
                    : ($savedMessage !== '' ? $savedMessage : 'Your settings are now active.')) ?></small>
            </span>
            <button type="button" aria-label="Dismiss notification" data-settings-toast-close>×</button>
        </div>
    <?php endif; ?>

    <?php if ($section === 'basic'): ?>
        <div class="barama-settings-grid barama-settings-grid--wide">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('grid') ?></span>
                    <div>
                        <h2>Workspace defaults</h2>
                        <p>Set the values the workspace uses when a page has no explicit preference.</p>
                    </div>
                </div>

                <form
                    action="<?= e(url($base . '/basic')) ?>"
                    method="post"
                    class="barama-settings-form"
                    data-settings-form
                >
                    <?= csrf_field() ?>

                    <?php if ($workspace === 'account'): ?>
                        <div class="barama-settings-fields">
                            <label class="barama-settings-field">
                                <span>Preferred language</span>
                                <small>Used by the signed-in customer experience.</small>
                                <div class="barama-settings-control">
                                    <i><?= app_icon('language') ?></i>
                                    <select name="preferred_language">
                                        <option value="en" <?= ($profile['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                        <option value="km" <?= ($profile['preferred_language'] ?? '') === 'km' ? 'selected' : '' ?>>Khmer</option>
                                        <option value="zh" <?= ($profile['preferred_language'] ?? '') === 'zh' ? 'selected' : '' ?>>Chinese</option>
                                    </select>
                                </div>
                            </label>

                            <label class="barama-settings-field">
                                <span>Timezone</span>
                                <small>Used for account dates and scheduled activity.</small>
                                <div class="barama-settings-control">
                                    <i><?= app_icon('clock') ?></i>
                                    <select name="timezone">
                                        <?php foreach ($timezones as $tz): ?>
                                            <option value="<?= e($tz) ?>" <?= ($profile['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="barama-settings-fields">
                            <label class="barama-settings-field">
                                <span>Records per page</span>
                                <small>Controls record density across operational tables.</small>
                                <div class="barama-settings-control">
                                    <i><?= app_icon('grid') ?></i>
                                    <select name="page_size">
                                        <?php foreach ([10, 20, 50] as $size): ?>
                                            <option value="<?= $size ?>" <?= (int) ($preferences['page_size'] ?? 20) === $size ? 'selected' : '' ?>><?= $size ?> records</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </label>

                            <label class="barama-settings-field">
                                <span>Default sort</span>
                                <small>Applied when a page does not define its own sort query.</small>
                                <div class="barama-settings-control">
                                    <i><?= app_icon('layers') ?></i>
                                    <select name="default_sort">
                                        <option value="updated_desc" <?= ($preferences['default_sort'] ?? 'updated_desc') === 'updated_desc' ? 'selected' : '' ?>>Newest updated</option>
                                        <option value="updated_asc" <?= ($preferences['default_sort'] ?? '') === 'updated_asc' ? 'selected' : '' ?>>Oldest updated</option>
                                        <option value="name_asc" <?= ($preferences['default_sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
                                    </select>
                                </div>
                            </label>

                            <?php if ($workspace === 'developer'): ?>
                                <label class="barama-settings-field">
                                    <span>Default listing currency</span>
                                    <small>Pre-fills the currency for new listing forms.</small>
                                    <div class="barama-settings-control">
                                        <i><?= app_icon('tag') ?></i>
                                        <select name="default_currency">
                                            <?php foreach (['USD', 'KHR', 'THB'] as $currency): ?>
                                                <option value="<?= $currency ?>" <?= ($preferences['default_currency'] ?? 'USD') === $currency ? 'selected' : '' ?>><?= $currency ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </label>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit">
                            <?= app_icon('check') ?> Save defaults
                        </button>
                    </div>
                </form>
            </section>

            <aside class="barama-settings-card barama-settings-card--summary">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('globe') ?></span>
                    <div>
                        <h2>Active configuration</h2>
                        <p>Current values already in effect.</p>
                    </div>
                </div>
                <div class="barama-settings-summary">
                    <?php if ($workspace === 'account'): ?>
                        <div><span>Language</span><strong><?= e(strtoupper((string) ($profile['preferred_language'] ?? 'en'))) ?></strong></div>
                        <div><span>Timezone</span><strong><?= e((string) ($profile['timezone'] ?? 'UTC')) ?></strong></div>
                    <?php else: ?>
                        <div><span>Page size</span><strong><?= e((string) ($preferences['page_size'] ?? 20)) ?></strong></div>
                        <div><span>Default sort</span><strong><?= e(ucwords(str_replace('_', ' ', (string) ($preferences['default_sort'] ?? 'updated_desc')))) ?></strong></div>
                        <?php if ($workspace === 'developer'): ?>
                            <div><span>Currency</span><strong><?= e((string) ($preferences['default_currency'] ?? 'USD')) ?></strong></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

    <?php elseif ($section === 'profile'): ?>
        <div class="barama-settings-grid barama-settings-grid--wide">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('user') ?></span>
                    <div>
                        <h2>Profile identity</h2>
                        <p>Maintain the identity used for signed-in operational activity.</p>
                    </div>
                </div>

                <form action="<?= e(url($base . '/profile')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <div class="barama-settings-fields">
                        <label class="barama-settings-field">
                            <span>Name</span>
                            <small>Displayed in your account identity and operational activity.</small>
                            <div class="barama-settings-control">
                                <i><?= app_icon('user') ?></i>
                                <input name="name" value="<?= e((string) ($profile['name'] ?? '')) ?>" required maxlength="120" autocomplete="name">
                            </div>
                        </label>

                        <label class="barama-settings-field">
                            <span>Phone</span>
                            <small>Optional contact number for account operations.</small>
                            <div class="barama-settings-control">
                                <i><?= app_icon('message') ?></i>
                                <input name="phone" value="<?= e((string) ($profile['phone'] ?? '')) ?>" maxlength="60" autocomplete="tel">
                            </div>
                        </label>

                        <?php if ($workspace !== 'account'): ?>
                            <label class="barama-settings-field">
                                <span><?= $workspace === 'dashboard' ? 'Administrator title' : 'Workspace role title' ?></span>
                                <small>Internal role label for this workspace.</small>
                                <div class="barama-settings-control">
                                    <i><?= app_icon('shield') ?></i>
                                    <input name="role_title" value="<?= e((string) ($preferences['role_title'] ?? '')) ?>" maxlength="100">
                                </div>
                            </label>
                        <?php endif; ?>

                        <label class="barama-settings-field">
                            <span>Email</span>
                            <small>Change the address securely from Security settings.</small>
                            <div class="barama-settings-control is-readonly">
                                <i><?= app_icon('mail') ?></i>
                                <input value="<?= e((string) ($profile['email'] ?? '')) ?>" disabled>
                            </div>
                        </label>
                    </div>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit">
                            <?= app_icon('check') ?> Save profile
                        </button>
                        <a class="barama-settings-secondary-link" href="<?= e(url($base . '/security')) ?>">Manage email & password</a>
                    </div>
                </form>
            </section>

            <aside class="barama-settings-card barama-settings-card--identity">
                <div class="barama-settings-avatar"><?= app_icon('user') ?></div>
                <h2><?= e((string) ($profile['name'] ?? 'Account')) ?></h2>
                <p><?= e((string) ($profile['email'] ?? '')) ?></p>
                <?php if ($workspace !== 'account' && !empty($preferences['role_title'])): ?>
                    <span class="barama-settings-role"><?= e((string) $preferences['role_title']) ?></span>
                <?php endif; ?>
                <div class="barama-settings-status-row">
                    <span>Email</span>
                    <strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></strong>
                </div>
                <div class="barama-settings-status-row">
                    <span>Account</span>
                    <strong><?= e(ucfirst($accountStatus)) ?></strong>
                </div>
            </aside>
        </div>

    <?php elseif ($section === 'privacy'): ?>
        <div class="barama-settings-grid barama-settings-grid--wide">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('shield') ?></span>
                    <div>
                        <h2><?= $workspace === 'account' ? 'Activity history' : 'Workspace memory' ?></h2>
                        <p><?= $workspace === 'account'
                            ? 'Control whether recently viewed properties are retained.'
                            : 'Choose whether search and filter selections return when you revisit operational lists.' ?></p>
                    </div>
                </div>

                <form action="<?= e(url($base . '/privacy')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="privacy_action" value="save">

                    <?php if ($workspace === 'account'): ?>
                        <label class="barama-settings-toggle">
                            <span class="barama-settings-card__icon"><?= app_icon('clock') ?></span>
                            <span class="barama-settings-toggle__copy">
                                <strong>Remember recently viewed properties</strong>
                                <small>When disabled, stored recent-view history is cleared and future views are not retained.</small>
                            </span>
                            <span class="barama-settings-switch">
                                <input type="checkbox" name="remember_recent_views" value="1" <?= !isset($profile['remember_recent_views']) || !empty($profile['remember_recent_views']) ? 'checked' : '' ?>>
                                <i></i>
                            </span>
                        </label>
                    <?php else: ?>
                        <label class="barama-settings-toggle">
                            <span class="barama-settings-card__icon"><?= app_icon('search') ?></span>
                            <span class="barama-settings-toggle__copy">
                                <strong>Remember <?= $workspace === 'dashboard' ? 'Admin Dashboard' : 'Vendor / Developer' ?> filters</strong>
                                <small>Search, status, type, location, and sort choices are restored on your next visit.</small>
                            </span>
                            <span class="barama-settings-switch">
                                <input type="checkbox" name="remember_filters" value="1" <?= !empty($preferences['remember_filters']) ? 'checked' : '' ?>>
                                <i></i>
                            </span>
                        </label>
                    <?php endif; ?>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit">
                            <?= app_icon('check') ?> Save privacy
                        </button>
                    </div>
                </form>
            </section>

            <aside class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('clock') ?></span>
                    <div>
                        <h2>Stored activity</h2>
                        <p>Clear retained workspace activity without changing your account.</p>
                    </div>
                </div>

                <?php if ($workspace === 'account'): ?>
                    <p class="barama-settings-muted">Clearing history removes all stored recently viewed properties immediately.</p>
                    <form action="<?= e(url($base . '/privacy')) ?>" method="post" data-settings-confirm="Clear all recently viewed property history?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="privacy_action" value="clear_history">
                        <button class="btn btn-outline-secondary" type="submit"><?= app_icon('clock') ?> Clear recent history</button>
                    </form>
                <?php else: ?>
                    <div class="barama-settings-status-row">
                        <span>Remembered values</span>
                        <strong><?= e((string) count(array_filter($savedFilters, static fn($v): bool => trim((string) $v) !== ''))) ?></strong>
                    </div>
                    <p class="barama-settings-muted">Clear remembered filters to return operational lists to their defaults.</p>
                    <form action="<?= e(url($base . '/privacy')) ?>" method="post" data-settings-confirm="Clear remembered workspace filters?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="privacy_action" value="clear_filters">
                        <button class="btn btn-outline-secondary" type="submit"><?= app_icon('search') ?> Clear remembered filters</button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>

    <?php elseif ($section === 'security'): ?>
        <div class="barama-settings-security-status">
            <div>
                <span class="barama-settings-card__icon"><?= app_icon('shield') ?></span>
                <span><small>Account status</small><strong><?= e(ucfirst($accountStatus)) ?></strong></span>
            </div>
            <div>
                <span class="barama-settings-card__icon"><?= app_icon('mail') ?></span>
                <span><small>Email security</small><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></strong></span>
            </div>
            <div>
                <span class="barama-settings-card__icon"><?= app_icon('clock') ?></span>
                <span><small>Last sign-in</small><strong><?= e($lastLogin !== '' ? $lastLogin : 'Not recorded') ?></strong></span>
            </div>
        </div>

        <div class="barama-settings-grid barama-settings-grid--equal">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('lock') ?></span>
                    <div>
                        <h2>Change password</h2>
                        <p>Changing your password invalidates other authenticated sessions.</p>
                    </div>
                </div>

                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="security_action" value="password">

                    <label class="barama-settings-field">
                        <span>Current password</span>
                        <div class="barama-settings-control">
                            <i><?= app_icon('lock') ?></i>
                            <input type="password" name="current_password" required autocomplete="current-password" data-password-field>
                            <button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button>
                        </div>
                    </label>

                    <label class="barama-settings-field">
                        <span>New password</span>
                        <small>Use a strong, unique password that satisfies the Barama password policy.</small>
                        <div class="barama-settings-control">
                            <i><?= app_icon('key') ?></i>
                            <input type="password" name="password" required autocomplete="new-password" data-password-field>
                            <button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button>
                        </div>
                    </label>

                    <label class="barama-settings-field">
                        <span>Confirm new password</span>
                        <div class="barama-settings-control">
                            <i><?= app_icon('check') ?></i>
                            <input type="password" name="password_confirmation" required autocomplete="new-password" data-password-field>
                            <button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button>
                        </div>
                    </label>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Update password</button>
                    </div>
                </form>
            </section>

            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('mail') ?></span>
                    <div>
                        <h2>Change email address</h2>
                        <p>A secure confirmation link is sent to the new address before the account email changes.</p>
                    </div>
                </div>

                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="security_action" value="email">

                    <label class="barama-settings-field">
                        <span>Current email</span>
                        <div class="barama-settings-control is-readonly">
                            <i><?= app_icon('mail') ?></i>
                            <input value="<?= e((string) ($profile['email'] ?? '')) ?>" disabled>
                        </div>
                    </label>

                    <label class="barama-settings-field">
                        <span>New email</span>
                        <div class="barama-settings-control">
                            <i><?= app_icon('mail') ?></i>
                            <input type="email" name="new_email" required autocomplete="email">
                        </div>
                    </label>

                    <label class="barama-settings-field">
                        <span>Confirm with current password</span>
                        <div class="barama-settings-control">
                            <i><?= app_icon('lock') ?></i>
                            <input type="password" name="current_password" required autocomplete="current-password" data-password-field>
                            <button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button>
                        </div>
                    </label>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit"><?= app_icon('mail') ?> Send confirmation</button>
                    </div>
                </form>
            </section>
        </div>

    <?php elseif ($section === 'storage' && $workspace === 'dashboard'): ?>
        <div class="barama-settings-storage-overview">
            <article>
                <span><?= app_icon('image') ?></span>
                <div>
                    <small>Application storage</small>
                    <strong><?= !empty($storage['storage_writable']) ? 'Writable' : 'Needs attention' ?></strong>
                </div>
            </article>
            <article>
                <span><?= app_icon('layers') ?></span>
                <div>
                    <small>Disk used</small>
                    <strong><?= e((string) ($storage['used_percent'] ?? 0)) ?>%</strong>
                </div>
            </article>
            <article>
                <span><?= app_icon('grid') ?></span>
                <div>
                    <small>Free space</small>
                    <strong><?= e($formatBytes((int) ($storage['free_bytes'] ?? 0))) ?></strong>
                </div>
            </article>
            <article>
                <span><?= app_icon('clock') ?></span>
                <div>
                    <small>Temporary files</small>
                    <strong><?= e((string) ($storage['temp_files'] ?? 0)) ?></strong>
                </div>
            </article>
        </div>

        <div class="barama-settings-grid barama-settings-grid--wide">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('settings') ?></span>
                    <div>
                        <h2>Storage policy</h2>
                        <p>Control Admin-managed Hero/site media limits and temporary-file retention.</p>
                    </div>
                </div>

                <form action="<?= e(url($base . '/storage')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="storage_action" value="save">

                    <div class="barama-settings-fields">
                        <label class="barama-settings-field">
                            <span>Hero & site-media upload cap</span>
                            <small>The application also respects the lower server/configuration limit when one exists.</small>
                            <div class="barama-settings-control">
                                <i><?= app_icon('image') ?></i>
                                <select name="site_media_upload_limit_mb">
                                    <?php foreach ([5, 10, 25, 50] as $limit): ?>
                                        <option value="<?= $limit ?>" <?= (int) ($storage['site_media_upload_limit_mb'] ?? 50) === $limit ? 'selected' : '' ?>><?= $limit ?> MB</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>

                        <label class="barama-settings-field">
                            <span>Temporary-file retention</span>
                            <small>Cleanup removes only files older than this age from application/storage/tmp.</small>
                            <div class="barama-settings-control">
                                <i><?= app_icon('clock') ?></i>
                                <select name="temp_retention_hours">
                                    <?php foreach ([1, 6, 12, 24, 72] as $hours): ?>
                                        <option value="<?= $hours ?>" <?= (int) ($storage['temp_retention_hours'] ?? 24) === $hours ? 'selected' : '' ?>><?= $hours ?> hour<?= $hours === 1 ? '' : 's' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>
                    </div>

                    <div class="barama-settings-actions">
                        <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save storage policy</button>
                    </div>
                </form>
            </section>

            <aside class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('shield') ?></span>
                    <div>
                        <h2>Runtime diagnostics</h2>
                        <p>Live storage state reported by the application process.</p>
                    </div>
                </div>

                <div class="barama-settings-summary">
                    <div><span>Storage root</span><strong class="<?= !empty($storage['storage_writable']) ? 'is-good' : 'is-bad' ?>"><?= !empty($storage['storage_writable']) ? 'Writable' : 'Not writable' ?></strong></div>
                    <div><span>Private uploads</span><strong class="<?= !empty($storage['uploads_writable']) ? 'is-good' : 'is-bad' ?>"><?= !empty($storage['uploads_writable']) ? 'Writable' : 'Not writable' ?></strong></div>
                    <div><span>Temporary storage</span><strong class="<?= !empty($storage['temp_writable']) ? 'is-good' : 'is-bad' ?>"><?= !empty($storage['temp_writable']) ? 'Writable' : 'Not writable' ?></strong></div>
                    <div><span>Storage configuration</span><strong class="<?= !empty($storage['config_writable']) ? 'is-good' : 'is-bad' ?>"><?= !empty($storage['config_writable']) ? 'Writable' : 'Not writable' ?></strong></div>
                    <div><span>Effective site-media cap</span><strong><?= e($formatBytes((int) ($storage['effective_site_media_limit_bytes'] ?? 0))) ?></strong></div>
                    <div><span>PHP upload_max_filesize</span><strong><?= e((string) ($storage['php_upload_max'] ?? 'Unknown')) ?></strong></div>
                    <div><span>PHP post_max_size</span><strong><?= e((string) ($storage['php_post_max'] ?? 'Unknown')) ?></strong></div>
                </div>
            </aside>
        </div>

        <div class="barama-settings-grid barama-settings-grid--equal">
            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('check') ?></span>
                    <div>
                        <h2>Storage write test</h2>
                        <p>Create, verify, and immediately remove a tiny temporary test file.</p>
                    </div>
                </div>
                <form action="<?= e(url($base . '/storage')) ?>" method="post" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="storage_action" value="test_write">
                    <button class="btn btn-outline-secondary" type="submit"><?= app_icon('check') ?> Run write test</button>
                </form>
            </section>

            <section class="barama-settings-card">
                <div class="barama-settings-card__head">
                    <span class="barama-settings-card__icon"><?= app_icon('clock') ?></span>
                    <div>
                        <h2>Temporary cleanup</h2>
                        <p><?= e((string) ($storage['temp_files'] ?? 0)) ?> files currently use <?= e($formatBytes((int) ($storage['temp_bytes'] ?? 0))) ?>.</p>
                    </div>
                </div>
                <form
                    action="<?= e(url($base . '/storage')) ?>"
                    method="post"
                    data-settings-confirm="Delete temporary files older than the configured retention period?"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="storage_action" value="clear_temp">
                    <button class="btn btn-outline-secondary" type="submit"><?= app_icon('clock') ?> Clean temporary files</button>
                </form>
            </section>
        </div>

        <section class="barama-settings-paths">
            <div>
                <span><?= app_icon('grid') ?></span>
                <span><small>Storage root</small><code><?= e((string) ($storage['storage_root'] ?? '')) ?></code></span>
            </div>
            <div>
                <span><?= app_icon('image') ?></span>
                <span><small>Private uploads</small><code><?= e((string) ($storage['uploads_root'] ?? '')) ?></code></span>
            </div>
            <div>
                <span><?= app_icon('clock') ?></span>
                <span><small>Temporary files</small><code><?= e((string) ($storage['temp_root'] ?? '')) ?></code></span>
            </div>
            <div>
                <span><?= app_icon('settings') ?></span>
                <span><small>Storage policy</small><code><?= e((string) ($storage['config_path'] ?? '')) ?></code></span>
            </div>
        </section>
    <?php endif; ?>
</section>

<script src="<?= e(asset('js/admin-settings-v120.js') . '?v=120') ?>" defer></script>
