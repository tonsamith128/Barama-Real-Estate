<?php
$profile = is_array($profile ?? null) ? $profile : [];
$preferences = is_array($preferences ?? null) ? $preferences : [];
$section = (string) ($settingsSection ?? 'basic');
$workspace = (string) ($settingsWorkspace ?? 'account');
$base = (string) ($settingsBasePath ?? '/account/settings');
$emailVerified = !empty($profile['email_verified_at']);
$accountStatus = trim((string) ($profile['status'] ?? 'active')) ?: 'active';
$lastLogin = trim((string) ($profile['last_login_at'] ?? ''));
$createdAt = trim((string) ($profile['created_at'] ?? ''));
$savedFilters = is_array($preferences['last_filters'] ?? null) ? $preferences['last_filters'] : [];
$workspaceName = $workspace === 'dashboard' ? 'Admin Dashboard' : ($workspace === 'developer' ? 'Vendor / Developer' : 'Customer Account');
$workspaceIcon = $workspace === 'dashboard' ? 'shield' : ($workspace === 'developer' ? 'building' : 'user');
?>
<section class="workspace-page workspace-settings-page">
    <header class="workspace-page__header workspace-settings-hero">
        <div>
            <span class="eyebrow"><?= e(strtoupper($workspace === 'dashboard' ? 'Admin Settings' : ($workspace === 'developer' ? 'Vendor / Developer Settings' : 'Customer Settings'))) ?></span>
            <h1><?= e($settingsTitle) ?></h1>
            <p><?= e($settingsDescription) ?></p>
        </div>
        <div class="workspace-settings-hero__badge">
            <span><?= app_icon($workspaceIcon) ?></span>
            <div><strong><?= e($workspaceName) ?></strong><small><?= e(ucfirst($section)) ?> configuration</small></div>
        </div>
    </header>

    <?php if (!empty($saved)): ?>
        <div class="workspace-settings-notice is-success">
            <span><?= app_icon('check') ?></span>
            <div><strong>Saved successfully</strong><small><?= e($savedMessage !== '' ? $savedMessage : 'Your settings are now active.') ?></small></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="workspace-settings-notice is-error">
            <span><?= app_icon('alert') ?></span>
            <div><strong>Settings were not changed</strong><small><?= e($error) ?></small></div>
        </div>
    <?php endif; ?>

    <?php if ($section === 'basic'): ?>
        <div class="workspace-settings-layout">
            <section class="workspace-settings-card workspace-settings-card--primary">
                <div class="workspace-settings-card__head">
                    <span class="workspace-settings-card__icon"><?= app_icon('grid') ?></span>
                    <div><h2>Workspace defaults</h2><p>These preferences change how this workspace behaves when you use it.</p></div>
                </div>
                <form action="<?= e(url($base . '/basic')) ?>" method="post" class="workspace-form workspace-settings-form">
                    <?= csrf_field() ?>
                    <?php if ($workspace === 'account'): ?>
                        <div class="workspace-form__grid">
                            <label><span>Preferred language</span><small>Used by your signed-in customer experience.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('language') ?></span><select class="form-select" name="preferred_language"><option value="en" <?= ($profile['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option><option value="km" <?= ($profile['preferred_language'] ?? '') === 'km' ? 'selected' : '' ?>>Khmer</option><option value="zh" <?= ($profile['preferred_language'] ?? '') === 'zh' ? 'selected' : '' ?>>Chinese</option></select></div></label>
                            <label><span>Timezone</span><small>Used for account dates and scheduled activity.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('clock') ?></span><select class="form-select" name="timezone"><?php foreach ($timezones as $tz): ?><option value="<?= e($tz) ?>" <?= ($profile['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></div></label>
                        </div>
                    <?php else: ?>
                        <div class="workspace-form__grid">
                            <label><span>Records per page</span><small>Controls table density across operational lists.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('grid') ?></span><select class="form-select" name="page_size"><?php foreach ([10,20,50] as $size): ?><option value="<?= $size ?>" <?= (int)($preferences['page_size'] ?? 20) === $size ? 'selected' : '' ?>><?= $size ?> records</option><?php endforeach; ?></select></div></label>
                            <label><span>Default sort</span><small>Applied when a page has no explicit sort query.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('layers') ?></span><select class="form-select" name="default_sort"><option value="updated_desc" <?= ($preferences['default_sort'] ?? 'updated_desc') === 'updated_desc' ? 'selected' : '' ?>>Newest updated</option><option value="updated_asc" <?= ($preferences['default_sort'] ?? '') === 'updated_asc' ? 'selected' : '' ?>>Oldest updated</option><option value="name_asc" <?= ($preferences['default_sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option></select></div></label>
                            <?php if ($workspace === 'developer'): ?><label><span>Default listing currency</span><small>Pre-fills the currency on new listing forms.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('tag') ?></span><select class="form-select" name="default_currency"><?php foreach (['USD','KHR','THB'] as $currency): ?><option value="<?= $currency ?>" <?= ($preferences['default_currency'] ?? 'USD') === $currency ? 'selected' : '' ?>><?= $currency ?></option><?php endforeach; ?></select></div></label><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="workspace-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save defaults</button></div>
                </form>
            </section>

            <aside class="workspace-settings-card workspace-settings-card--summary">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('globe') ?></span><div><h2>Active configuration</h2><p>Current values that are already in effect.</p></div></div>
                <div class="workspace-settings-summary-list">
                    <?php if ($workspace === 'account'): ?>
                        <div><span>Language</span><strong><?= e(strtoupper((string)($profile['preferred_language'] ?? 'en'))) ?></strong></div>
                        <div><span>Timezone</span><strong><?= e((string)($profile['timezone'] ?? 'UTC')) ?></strong></div>
                    <?php else: ?>
                        <div><span>Page size</span><strong><?= e((string)($preferences['page_size'] ?? 20)) ?></strong></div>
                        <div><span>Default sort</span><strong><?= e(ucwords(str_replace('_', ' ', (string)($preferences['default_sort'] ?? 'updated_desc')))) ?></strong></div>
                        <?php if ($workspace === 'developer'): ?><div><span>Listing currency</span><strong><?= e((string)($preferences['default_currency'] ?? 'USD')) ?></strong></div><?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

    <?php elseif ($section === 'profile'): ?>
        <div class="workspace-settings-layout">
            <section class="workspace-settings-card workspace-settings-card--primary">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('user') ?></span><div><h2>Personal details</h2><p><?= $workspace === 'account' ? 'Keep your customer contact information current.' : ($workspace === 'developer' ? 'Identify the operator working inside this company workspace.' : 'Identify the administrator performing operational work.') ?></p></div></div>
                <form action="<?= e(url($base . '/profile')) ?>" method="post" class="workspace-form workspace-settings-form">
                    <?= csrf_field() ?>
                    <div class="workspace-form__grid">
                        <label><span>Name</span><small>Displayed in your account identity.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('user') ?></span><input class="form-control" name="name" value="<?= e((string)($profile['name'] ?? '')) ?>" required maxlength="120" autocomplete="name"></div></label>
                        <label><span>Phone</span><small>Optional contact number for account operations.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('message') ?></span><input class="form-control" name="phone" value="<?= e((string)($profile['phone'] ?? '')) ?>" maxlength="60" autocomplete="tel"></div></label>
                        <?php if ($workspace !== 'account'): ?><label><span><?= $workspace === 'dashboard' ? 'Administrator title' : 'Workspace role title' ?></span><small>Internal role label for this workspace.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('shield') ?></span><input class="form-control" name="role_title" value="<?= e((string)($preferences['role_title'] ?? '')) ?>" maxlength="100"></div></label><?php endif; ?>
                        <label><span>Email</span><small>Change this securely from Security settings.</small><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('mail') ?></span><input class="form-control" value="<?= e((string)($profile['email'] ?? '')) ?>" disabled></div></label>
                    </div>
                    <div class="workspace-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save profile</button><a class="workspace-settings-link" href="<?= e(url($base . '/security')) ?>">Manage email & password</a></div>
                </form>
            </section>

            <aside class="workspace-settings-card workspace-settings-card--identity">
                <div class="workspace-settings-avatar"><?= app_icon('user') ?></div>
                <h2><?= e((string)($profile['name'] ?? 'Account')) ?></h2>
                <p><?= e((string)($profile['email'] ?? '')) ?></p>
                <?php if ($workspace !== 'account' && !empty($preferences['role_title'])): ?><span class="workspace-settings-role"><?= e((string)$preferences['role_title']) ?></span><?php endif; ?>
                <div class="workspace-settings-status-row"><span>Email</span><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending verification' ?></strong></div>
                <div class="workspace-settings-status-row"><span>Status</span><strong><?= e(ucfirst($accountStatus)) ?></strong></div>
            </aside>
        </div>

    <?php elseif ($section === 'privacy'): ?>
        <div class="workspace-settings-layout">
            <section class="workspace-settings-card workspace-settings-card--primary">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('shield') ?></span><div><h2><?= $workspace === 'account' ? 'Activity history' : 'Filter memory' ?></h2><p><?= $workspace === 'account' ? 'Control whether Barama remembers properties you recently opened.' : 'Control whether search and filter choices return when you revisit workspace lists.' ?></p></div></div>
                <form action="<?= e(url($base . '/privacy')) ?>" method="post" class="workspace-form workspace-settings-form">
                    <?= csrf_field() ?><input type="hidden" name="privacy_action" value="save">
                    <?php if ($workspace === 'account'): ?>
                        <label class="workspace-setting-toggle"><span class="workspace-setting-toggle__icon"><?= app_icon('clock') ?></span><span class="workspace-setting-toggle__copy"><strong>Remember recently viewed properties</strong><small>When disabled, existing recent-view history is cleared and new views are not retained.</small></span><span class="workspace-setting-toggle__control"><input type="checkbox" name="remember_recent_views" value="1" <?= !isset($profile['remember_recent_views']) || !empty($profile['remember_recent_views']) ? 'checked' : '' ?>><i></i></span></label>
                    <?php else: ?>
                        <label class="workspace-setting-toggle"><span class="workspace-setting-toggle__icon"><?= app_icon('search') ?></span><span class="workspace-setting-toggle__copy"><strong>Remember <?= $workspace === 'dashboard' ? 'Admin Dashboard' : 'Vendor / Developer' ?> filters</strong><small>Search, status, type, location, and sort choices are restored on your next visit.</small></span><span class="workspace-setting-toggle__control"><input type="checkbox" name="remember_filters" value="1" <?= !empty($preferences['remember_filters']) ? 'checked' : '' ?>><i></i></span></label>
                    <?php endif; ?>
                    <div class="workspace-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save privacy preference</button></div>
                </form>
            </section>

            <aside class="workspace-settings-card workspace-settings-card--summary">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('clock') ?></span><div><h2>Stored activity</h2><p>Remove retained workspace activity without changing your account.</p></div></div>
                <?php if ($workspace === 'account'): ?>
                    <p class="workspace-settings-muted">Clearing history removes your stored recently viewed properties immediately.</p>
                    <form action="<?= e(url($base . '/privacy')) ?>" method="post" class="workspace-settings-inline-form" data-confirm="Clear all recently viewed property history?">
                        <?= csrf_field() ?><input type="hidden" name="privacy_action" value="clear_history"><button class="btn btn-outline-secondary" type="submit"><?= app_icon('clock') ?> Clear recent history</button>
                    </form>
                <?php else: ?>
                    <div class="workspace-settings-status-row"><span>Remembered values</span><strong><?= e((string)count(array_filter($savedFilters, static fn($v): bool => trim((string)$v) !== ''))) ?></strong></div>
                    <p class="workspace-settings-muted">Clear remembered filters if you want the next workspace visit to start from defaults.</p>
                    <form action="<?= e(url($base . '/privacy')) ?>" method="post" class="workspace-settings-inline-form" data-confirm="Clear remembered workspace filters?">
                        <?= csrf_field() ?><input type="hidden" name="privacy_action" value="clear_filters"><button class="btn btn-outline-secondary" type="submit"><?= app_icon('search') ?> Clear remembered filters</button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>

    <?php else: ?>
        <div class="workspace-security-status">
            <div><span class="workspace-settings-card__icon"><?= app_icon('shield') ?></span><span><small>Account status</small><strong><?= e(ucfirst($accountStatus)) ?></strong></span></div>
            <div><span class="workspace-settings-card__icon"><?= app_icon('mail') ?></span><span><small>Email security</small><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Verification pending' ?></strong></span></div>
            <div><span class="workspace-settings-card__icon"><?= app_icon('clock') ?></span><span><small>Last sign-in</small><strong><?= e($lastLogin !== '' ? $lastLogin : 'Not recorded') ?></strong></span></div>
        </div>

        <div class="workspace-settings-grid workspace-settings-grid--security">
            <section class="workspace-settings-card">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('lock') ?></span><div><h2>Change password</h2><p>Updating your password invalidates other authenticated sessions.</p></div></div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="workspace-form workspace-settings-form">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="password">
                    <label><span>Current password</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('lock') ?></span><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div></label>
                    <div class="workspace-form__grid">
                        <label><span>New password</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('key') ?></span><input class="form-control" type="password" name="password" required autocomplete="new-password"></div></label>
                        <label><span>Confirm new password</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('check') ?></span><input class="form-control" type="password" name="password_confirmation" required autocomplete="new-password"></div></label>
                    </div>
                    <div class="workspace-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('lock') ?> Update password</button></div>
                </form>
            </section>

            <section class="workspace-settings-card">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('mail') ?></span><div><h2>Change email</h2><p>A confirmation link is sent to the new address before it becomes active.</p></div></div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="workspace-form workspace-settings-form">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="email">
                    <label><span>New email</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('mail') ?></span><input class="form-control" type="email" name="new_email" required autocomplete="email"></div></label>
                    <label><span>Current password</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('lock') ?></span><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div></label>
                    <div class="workspace-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('mail') ?> Send confirmation</button></div>
                </form>
            </section>
        </div>

        <?php if ($workspace === 'account'): ?>
            <section class="workspace-settings-card workspace-settings-card--danger">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('alert') ?></span><div><h2>Deactivate account</h2><p>This signs you out and disables the account. Company owners must transfer or archive ownership first.</p></div></div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="workspace-form workspace-settings-form" data-confirm="Deactivate this Barama account?">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="deactivate">
                    <div class="workspace-form__grid">
                        <label><span>Current password</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('lock') ?></span><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div></label>
                        <label><span>Type DEACTIVATE</span><div class="workspace-settings-field-control"><span class="workspace-settings-field-control__icon"><?= app_icon('alert') ?></span><input class="form-control" name="confirm_phrase" required autocomplete="off" placeholder="DEACTIVATE"></div></label>
                    </div>
                    <div class="workspace-settings-actions"><button class="btn workspace-settings-danger-button" type="submit"><?= app_icon('alert') ?> Deactivate account</button></div>
                </form>
            </section>
        <?php else: ?>
            <section class="workspace-settings-card workspace-settings-card--info">
                <div class="workspace-settings-card__head"><span class="workspace-settings-card__icon"><?= app_icon('shield') ?></span><div><h2>Workspace access boundary</h2><p><?= $workspace === 'dashboard' ? 'Administrator access remains protected by the dashboard.access permission and the canonical role system.' : 'Vendor / Developer access remains protected by developer.access and company-scoped permissions.' ?></p></div></div>
                <div class="workspace-settings-status-row"><span>Member since</span><strong><?= e($createdAt !== '' ? $createdAt : 'Not recorded') ?></strong></div>
                <div class="workspace-settings-status-row"><span>Verified email</span><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Yes' : 'No' ?></strong></div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
