<?php
/* BARAMA_GLOBAL_LOCATIONS_V101 */
/* BARAMA_ADMIN_SETTINGS_WORKSPACE_V120 */
/* BARAMA_ADMIN_DEFAULT_THEME_SETTINGS_V101 */
/* BARAMA_GLOBAL_DEFAULT_THEME_V103 */
$profile = is_array($profile ?? null) ? $profile : [];
$preferences = is_array($preferences ?? null) ? $preferences : [];
$storage = is_array($storageDiagnostics ?? null) ? $storageDiagnostics : [];
$section = (string) ($settingsSection ?? 'basic');
$workspace = (string) ($settingsWorkspace ?? 'account');
$base = (string) ($settingsBasePath ?? '/account/settings');
$emailVerified = !empty($profile['email_verified_at']);
$accountStatus = trim((string) ($profile['status'] ?? 'active')) ?: 'active';
$lastLogin = trim((string) ($profile['last_login_at'] ?? ''));
$securityFactors = is_array($securityFactors ?? null) ? $securityFactors : [];
$changePasscodeEnabled = !empty($securityFactors['passcode_enabled']);
$mfaEnabled = !empty($securityFactors['mfa_enabled']);
$mfaPending = !empty($securityFactors['mfa_pending']);
$mfaSetupAuthorized = !empty($securityFactors['mfa_setup_authorized']);
$mfaSetup = is_array($securityFactors['mfa_setup'] ?? null) ? $securityFactors['mfa_setup'] : null;
$mfaConfirmedAt = trim((string) ($securityFactors['mfa_confirmed_at'] ?? ''));
$mfaRecoveryRemaining = (int) ($securityFactors['mfa_recovery_remaining'] ?? 0);
$mfaRecoveryCodes = is_array($mfaRecoveryCodes ?? null) ? $mfaRecoveryCodes : [];
$securityProtection = is_array($securityProtection ?? null) ? $securityProtection : [];
$securityProtectionConfig = is_array($securityProtection['config'] ?? null) ? $securityProtection['config'] : [];
$securityProtectionStats = is_array($securityProtection['stats'] ?? null) ? $securityProtection['stats'] : [];
$spamProtection = is_array($securityProtectionConfig['spam'] ?? null) ? $securityProtectionConfig['spam'] : [];
$hackProtection = is_array($securityProtectionConfig['hack'] ?? null) ? $securityProtectionConfig['hack'] : [];
$rightClickProtection = is_array($securityProtectionConfig['right_click'] ?? null) ? $securityProtectionConfig['right_click'] : [];
$spamProtectionEnabled = !empty($spamProtection['enabled']);
$hackProtectionEnabled = !empty($hackProtection['enabled']);
$hackAlertsEnabled = !empty($hackProtection['alerts_enabled']);
$rightClickProtectionEnabled = !empty($rightClickProtection['enabled']);
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

?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-v120.css') . '?v=120') ?>">
<?php if ($workspace === 'account' && in_array($section, ['basic', 'profile', 'privacy'], true)): ?>
<link rel="stylesheet" href="<?= e(asset('css/account-settings-full-v1130.css') . '?v=1130r3') ?>">
<script src="<?= e(asset('js/account-settings-full-v1130.js') . '?v=1130r3') ?>" defer></script>
<?php if ($workspace === 'account' && $section === 'profile'): ?>
<link rel="stylesheet" href="<?= e(asset('css/account-profile-v1131.css') . '?v=1131r1') ?>">
<script src="<?= e(asset('js/account-profile.js') . '?v=1.0.0-clean1') ?>" defer></script>
<?php endif; ?>
<?php endif; ?>
<?php if ($section === 'mail' && $workspace === 'dashboard'): ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-mail-settings-v100.css') . '?v=102') ?>">
<?php endif; ?>
<?php if ($section === 'locations' && $workspace === 'dashboard'): ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-global-locations.css') . '?v=1.0.0-clean1') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-default-theme-v101.css') . '?v=101') ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-flat-v130.css') . '?v=130') ?>">
<?php if ($section === 'profile'): ?>
<link rel="stylesheet" href="<?= e(asset('css/profile-avatar-unified-v107.css') . '?v=107') ?>">
<script src="<?= e(asset('js/profile-avatar-unified-v107.js') . '?v=107') ?>" defer></script>
<?php endif; ?>
<?php if ($section === 'basic' && $workspace === 'dashboard'): ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-branding-identity-v104.css') . '?v=104') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-security-v131.css') . '?v=131') ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-security-factors.css') . '?v=1.0.0-clean1') ?>">
<?php if ($section === 'security' && $workspace === 'dashboard'): ?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-security-protection-v134.css') . '?v=134') ?>">
<?php endif; ?>

<section class="workspace-page barama-settings<?= $workspace === 'dashboard' ? ' barama-settings--flat' : '' ?>" data-admin-settings>
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
        <?php if ($workspace !== 'dashboard'): ?>
        <div class="barama-settings__workspace-badge">
            <span class="barama-settings__workspace-icon"><?= app_icon($workspaceIcon) ?></span>
            <span>
                <strong><?= e($workspaceName) ?></strong>
                <small><?= e(ucfirst($section)) ?> configuration</small>
            </span>
        </div>
        <?php endif; ?>
    </header>

    <?php /* BARAMA_ADMIN_SETTINGS_NO_DUPLICATE_TABS_V117A16: Admin uses its main sidebar. */ ?>
    <?php if ($workspace !== 'dashboard'): ?>
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
    <?php endif; ?>

<?php if ($workspace === 'dashboard'): ?>
<?php
$baramaGovernance = [
    'basic' => [
        ['Appearance', '/dashboard/appearance', 'settings', 'Branding, theme and public presentation'],
        ['Homepage', '/dashboard/homepage', 'home', 'Hero and homepage composition'],
        ['Navigation', '/dashboard/navigation', 'menu', 'Header and footer navigation'],
        ['Page Manager', '/dashboard/pages', 'edit', 'CMS pages and publishing'],
        ['SEO Center', '/dashboard/seo', 'search', 'Metadata and discovery controls'],
        ['Users', '/dashboard/users', 'users', 'Accounts, roles and access'],
        ['Plans & Packages', '/dashboard/plans', 'layers', 'Commercial plan governance'],
        ['Payment Providers', '/dashboard/payment-providers', 'settings', 'Payment gateway configuration'],
        ['Email Templates', '/dashboard/email-templates', 'mail', 'Transactional communication content'],
    ],
    'profile' => [
        ['Users', '/dashboard/users', 'users', 'Manage administrator and member accounts'],
        ['Verification Center', '/dashboard/verification', 'check', 'Review identity and email verification'],
        ['Companies', '/dashboard/companies', 'building', 'Company records and public profiles'],
        ['Security Audit', '/dashboard/audit', 'shield', 'Review administrator activity'],
    ],
    'privacy' => [
        ['Users', '/dashboard/users', 'users', 'Account visibility and lifecycle controls'],
        ['Security Audit', '/dashboard/audit', 'shield', 'Review retained operational activity'],
        ['Media Library', '/dashboard/media', 'image', 'Stored media and asset metadata'],
        ['Contact', '/dashboard/contact', 'mail', 'Public contact and inquiry settings'],
    ],
    'security' => [
        ['Security Audit', '/dashboard/audit', 'shield', 'Security events and operational trail'],
        ['Verification Center', '/dashboard/verification', 'check', 'Verification operations'],
        ['Users', '/dashboard/users', 'users', 'Roles, status and account access'],
        ['Mail Queue & Logs', '/dashboard/mail-queue', 'pipeline', 'Security and verification email delivery'],
    ],
    'storage' => [
        ['Media Library', '/dashboard/media', 'image', 'Manage uploaded platform media'],
        ['Cloud Storage', '/dashboard/settings/cloud-storage', 'layers', 'Amazon S3 and Cloudflare R2'],
        ['Appearance', '/dashboard/appearance', 'settings', 'Brand and site-media consumers'],
        ['Homepage', '/dashboard/homepage', 'home', 'Hero and homepage media'],
    ],
];
$baramaGovernanceLinks = $baramaGovernance[$section] ?? [];
?>
<?php if ($baramaGovernanceLinks !== []): ?>
<section class="barama-settings-governance" aria-label="Platform governance">
    <header class="barama-settings-governance__head">
        <div>
            <h2>Platform governance</h2>
            <p>Open the full management modules connected to this settings area.</p>
        </div>
        <span>Live controls</span>
    </header>
    <div class="barama-settings-governance__grid">
        <?php foreach ($baramaGovernanceLinks as [$govLabel, $govPath, $govIcon, $govHelp]): ?>
            <a class="barama-settings-governance__link" href="<?= e(url($govPath)) ?>">
                <span class="barama-settings-governance__icon"><?= app_icon($govIcon) ?></span>
                <span class="barama-settings-governance__copy">
                    <strong><?= e($govLabel) ?></strong>
                    <small><?= e($govHelp) ?></small>
                </span>
                <span class="barama-settings-governance__arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>
<!-- /BARAMA_ADMIN_SETTINGS_GOVERNANCE_V130 -->

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
                <strong><?= !empty($error) ? 'Settings were not changed' : ($section === 'security' ? 'Security updated' : 'Saved successfully') ?></strong>
                <small><?= e(!empty($error)
                    ? (string) $error
                    : ($savedMessage !== '' ? $savedMessage : 'Your settings are now active.')) ?></small>
            </span>
            <button type="button" aria-label="Dismiss notification" data-settings-toast-close>×</button>
        </div>
    <?php endif; ?>

    <?php if ($section === 'basic' && $workspace === 'dashboard'): ?>
        <?php $identity = is_array($websiteIdentity ?? null) ? $websiteIdentity : []; ?>
        <section id="barama-branding-identity-panel" class="barama-settings-card barama-settings-card--branding-identity">
            <div class="barama-settings-card__head"><span class="barama-settings-card__icon"><?= app_icon('globe') ?></span><div><h2>Website Identity &amp; Branding</h2><p>Global website information, light/dark logos and browser icon. Existing branding is shown until replaced.</p></div></div>
            <form method="post" action="<?= e(url('/dashboard/settings/basic')) ?>" enctype="multipart/form-data" class="barama-settings-form">
                <?= csrf_field() ?>
                <input type="hidden" name="basic_action" value="website_identity">
                <div class="barama-settings-fields">
                    <?php foreach (['name'=>['Website name','Required; browser and social metadata'], 'tagline'=>['Website tagline','Short description of the platform'], 'description'=>['Website description','Default SEO description'], 'keywords'=>['SEO keywords','Comma-separated terms'], 'contact_email'=>['Website contact email','Public contact email'], 'contact_phone'=>['Website contact phone','Public contact phone']] as $key=>$details): ?>
                    <label class="barama-settings-field"><span><?= e($details[0]) ?></span><small><?= e($details[1]) ?></small><div class="barama-settings-control"><input type="text" name="<?= e($key) ?>" value="<?= e((string)($identity[$key] ?? '')) ?>" <?= $key === 'name' ? 'required' : '' ?> maxlength="<?= $key==='description'?320:($key==='keywords'?500:($key==='contact_email'?254:180)) ?>"></div></label>
                    <?php endforeach; ?>
                    <label class="barama-settings-field"><span>About the website</span><small>Longer introduction to your business or marketplace.</small><div class="barama-settings-control barama-identity-about-control-v104"><textarea class="barama-identity-about-v104" name="about" rows="4" maxlength="3000"><?= e((string)($identity['about'] ?? '')) ?></textarea></div></label>
                </div>
                <div class="barama-branding-assets-v104">
                    <?php foreach (['light_logo'=>['Light logo','Used in Light Mode'], 'dark_logo'=>['Dark logo','Used in Dark Mode'], 'favicon_32'=>['Favicon','PNG · generates browser icons']] as $key=>$details): ?>
                    <?php $field = $key==='favicon_32' ? 'favicon' : $key; $isFavicon = $key==='favicon_32'; ?>
                    <div class="barama-branding-card-v104">
                        <strong><?= e($details[0]) ?></strong>
                        <small><?= e($details[1]) ?></small>
                        <div class="barama-branding-preview-v104<?= $key==='dark_logo' ? ' barama-branding-preview-v104--dark' : '' ?>">
                            <img class="barama-branding-img-v104<?= $isFavicon ? ' barama-branding-img-v104--favicon' : '' ?>" src="<?= e(asset((string)($identity[$key] ?? 'images/branding/barama-logo-header.png'))) ?>" alt="<?= e($details[0]) ?> preview" loading="lazy" width="<?= $isFavicon ? '44' : '140' ?>" height="<?= $isFavicon ? '44' : '60' ?>" >
                        </div>
                        <label class="barama-branding-pick-v104" for="barama-branding-upload-<?= e($field) ?>">Replace image
                            <input id="barama-branding-upload-<?= e($field) ?>" type="file" name="<?= e($field) ?>" accept="<?= $field==='favicon'?'image/png':'image/png,image/jpeg,image/webp' ?>" aria-label="Choose <?= e($details[0]) ?> image" data-barama-branding-file>
                        </label>
                        <small class="barama-branding-file-name-v104" aria-live="polite">Current image retained</small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="barama-settings-actions"><button type="submit" class="btn btn-primary"><?= app_icon('check') ?> Save website identity</button></div>
            </form>
        </section>
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

                            <?php if ($workspace === 'dashboard'): ?>
                                <?php $defaultTheme = in_array((string) ($preferences['default_theme'] ?? 'light'), ['light', 'dark'], true)
                                    ? (string) ($preferences['default_theme'] ?? 'light')
                                    : 'light'; ?>
                                <div class="barama-settings-field barama-settings-theme-choice">
                                    <span>Default appearance</span>
                                    <small>Sets the Admin Dashboard default on browsers with no manual theme override. The header theme button can still override it on this browser.</small>
                                    <div class="barama-settings-theme-options" role="radiogroup" aria-label="Default Admin Dashboard appearance">
                                        <label class="barama-settings-theme-option">
                                            <input type="radio" name="default_theme" value="light" <?= $defaultTheme === 'light' ? 'checked' : '' ?>>
                                            <span class="barama-settings-theme-option__surface">
                                                <span class="barama-settings-theme-option__preview is-light" aria-hidden="true"></span>
                                                <span class="barama-settings-theme-option__copy"><strong>Light</strong><small>Bright workspace surfaces with dark text.</small></span>
                                                <span class="barama-settings-theme-option__check" aria-hidden="true"></span>
                                            </span>
                                        </label>
                                        <label class="barama-settings-theme-option">
                                            <input type="radio" name="default_theme" value="dark" <?= $defaultTheme === 'dark' ? 'checked' : '' ?>>
                                            <span class="barama-settings-theme-option__surface">
                                                <span class="barama-settings-theme-option__preview is-dark" aria-hidden="true"></span>
                                                <span class="barama-settings-theme-option__copy"><strong>Dark</strong><small>Dark workspace surfaces with high-contrast text.</small></span>
                                                <span class="barama-settings-theme-option__check" aria-hidden="true"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>


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
                        <?php if ($workspace === 'dashboard'): ?>
                            <?php $summaryTheme = in_array((string) ($preferences['default_theme'] ?? 'light'), ['light', 'dark'], true) ? (string) ($preferences['default_theme'] ?? 'light') : 'light'; ?>
                            <div><span>Global default mode</span><strong><?= e(ucfirst($summaryTheme)) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($workspace === 'developer'): ?>
                            <div><span>Currency</span><strong><?= e((string) ($preferences['default_currency'] ?? 'USD')) ?></strong></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

    <?php elseif ($section === 'locations' && $workspace === 'dashboard'): ?>
        <?= \Barama\Core\View::partial('workspace/settings-locations', [
            'locationCountries' => $locationCountries ?? [],
            'locationSummary' => $locationSummary ?? [],
            'locationMode' => $locationMode ?? 'selected',
            'locationProvider' => $locationProvider ?? [],
        ]) ?>

    <?php elseif ($section === 'profile'): ?>
        <?php if ($workspace === 'account'): ?>
            <?php require APP_PATH . '/resources/views/components/account-profile-details-v1131.php'; ?>
        <?php else: ?>

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
                                <i><?= app_icon('phone') ?></i>
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
                <div class="barama-avatar-v107" data-avatar-v107>
    <div class="barama-avatar-v107__portrait" aria-label="Current profile avatar">
        <?php if (trim((string) ($profile['avatar_uuid'] ?? '')) !== ''): ?>
            <img class="barama-avatar-v107__photo" src="<?= e(url('/account/avatar/' . rawurlencode(trim((string) ($profile['avatar_uuid'] ?? ''))))) ?>" alt="<?= e(((string) ($profile['name'] ?? 'Account')) . ' profile image') ?>" width="112" height="112" loading="eager">
        <?php else: ?>
            <?= app_icon('user') ?>
        <?php endif; ?>
    </div>
    <div class="barama-avatar-v107__actions">
        <form method="post" action="<?= e(url($base . '/profile')) ?>" enctype="multipart/form-data" data-avatar-v107-upload>
            <?= csrf_field() ?>
            <input type="hidden" name="profile_action" value="avatar_upload">
            <input class="barama-avatar-v107__input" type="file" name="avatar" id="avatar-v107-workspace" accept="image/jpeg,image/png,image/webp" data-avatar-v107-input>
            <label class="barama-avatar-v107__action" for="avatar-v107-workspace" tabindex="0" title="<?= trim((string) ($profile['avatar_uuid'] ?? '')) !== '' ? 'Replace profile image' : 'Upload profile image' ?>">
                <?= app_icon('image') ?> <span><?= trim((string) ($profile['avatar_uuid'] ?? '')) !== '' ? 'Replace' : 'Upload' ?></span>
            </label>
        </form>
        <?php if (trim((string) ($profile['avatar_uuid'] ?? '')) !== ''): ?>
        <form method="post" action="<?= e(url($base . '/profile')) ?>" data-avatar-v107-remove>
            <?= csrf_field() ?>
            <input type="hidden" name="profile_action" value="avatar_remove">
            <button class="barama-avatar-v107__action barama-avatar-v107__action--remove" type="submit" title="Remove profile image" aria-label="Remove profile image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/></svg> <span>Remove</span>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
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

    
        <?php endif; ?>

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

        <!-- BARAMA_ADMIN_SETTINGS_SECURITY_TABLE_V131 -->
        <link rel="stylesheet" href="<?= e(asset('css/account-security-table-v1129r5.css') . '?v=1129r5') ?>">
        <section class="barama-security-features" aria-label="Security features">
            <header class="barama-security-features__head">
                <div>
                    <h2>Security features</h2>
                    <p>Review each protection area and open its complete configuration only when needed.</p>
                </div>
                <span>Live controls</span>
            </header>

            <div class="barama-security-table-wrap">
                <table class="barama-security-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Status</th>
                            <th>Protection</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php if ($workspace === 'dashboard'): ?>
<?php include __DIR__ . '/turnstile-table-row.php'; ?>
<?php endif; ?>

                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('lock') ?></span>
                                    <span>
                                        <strong>Password & sessions</strong>
                                        <small>Credential and authenticated-session protection</small>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill is-good">Protected</span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Change the administrator password and invalidate other authenticated sessions in one secured workflow.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-password">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('mail') ?></span>
                                    <span>
                                        <strong>Email & verification</strong>
                                        <small>Primary sign-in identity and verification</small>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Change the primary email only after password confirmation and a secure verification workflow.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-email">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('users') ?></span>
                                    <span>
                                        <strong>Account access</strong>
                                        <small>Administrator status, roles and verification</small>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= strtolower($accountStatus) === 'active' ? 'is-good' : 'is-warn' ?>"><?= e(ucfirst($accountStatus)) ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Review the current administrator state, last sign-in, account access and verification operations.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-access">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('shield') ?></span>
                                    <span>
                                        <strong>Security monitoring</strong>
                                        <small>Audit trail and security mail operations</small>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill is-good">Available</span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Open the real audit trail and mail-delivery logs used for security and verification operations.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-monitoring">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('key') ?></span>
                                    <span><strong>Change passcode</strong><small>6-digit approval before workspace changes</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $changePasscodeEnabled ? 'is-good' : 'is-warn' ?>"><?= $changePasscodeEnabled ? 'Enabled' : 'Not configured' ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">When enabled, every authenticated Account, Vendor/Developer, or Admin change requires a single-use 6-digit approval.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-passcode">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('shield') ?></span>
                                    <span><strong>Multi-factor authentication</strong><small>Authenticator app and recovery-code protection</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $mfaEnabled ? 'is-good' : ($mfaPending ? 'is-warn' : 'is-warn') ?>"><?= $mfaEnabled ? 'Enabled' : ($mfaPending ? 'Setup pending' : 'Not configured') ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Require a rotating TOTP authenticator code after password sign-in, with single-use recovery codes for account recovery.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-mfa">Configure</button></td>
                        </tr>
                        <?php if ($workspace === 'dashboard'): ?>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('shield') ?></span>
                                    <span><strong>Spam Protection</strong><small>Public form rate limiting and repeat-submit defense</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $spamProtectionEnabled ? 'is-good' : 'is-warn' ?>"><?= $spamProtectionEnabled ? 'Enabled' : 'Disabled' ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Rate-limit public contact, inquiry and newsletter submissions, including repeated identical payloads from the same source.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-spam">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('shield') ?></span>
                                    <span><strong>Hack Protection</strong><small>High-confidence malicious-request blocking and alerts</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $hackProtectionEnabled ? 'is-good' : 'is-warn' ?>"><?= $hackProtectionEnabled ? ($hackAlertsEnabled ? 'Enabled + alerts' : 'Enabled') : 'Disabled' ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Block common scanner probes, traversal and injection signatures before the target controller runs, then write every block to Security Audit.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-hack">Configure</button></td>
                        </tr>
                        <tr>
                            <td data-label="Feature">
                                <div class="barama-security-feature">
                                    <span class="barama-security-feature__icon"><?= app_icon('lock') ?></span>
                                    <span><strong>Right Click Protection</strong><small>Optional public-site content deterrence</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><span class="barama-security-status-pill <?= $rightClickProtectionEnabled ? 'is-good' : 'is-warn' ?>"><?= $rightClickProtectionEnabled ? 'Enabled' : 'Disabled' ?></span></td>
                            <td data-label="Protection"><span class="barama-security-feature-detail">Discourage casual copying by blocking the public-site context menu and image dragging. This is a deterrent, not a security boundary.</span></td>
                            <td data-label="Actions" class="barama-security-actions"><button class="barama-security-configure" type="button" popovertarget="barama-security-right-click">Configure</button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
<?php if ($workspace === 'dashboard'): ?>
<link rel="stylesheet" href="<?= e(asset('css/turnstile-admin-modal.css') . '?v=113') ?>">
<?php include __DIR__ . '/turnstile-modal.php'; ?>
<script src="<?= e(asset('js/turnstile-admin-modal.js') . '?v=113') ?>" defer></script>
<?php endif; ?>

            </div>
        </section>

        <div class="barama-security-popover" id="barama-security-password" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title">
                    <span class="barama-settings-card__icon"><?= app_icon('lock') ?></span>
                    <div><h2>Password & sessions</h2><p>Update the administrator password and invalidate other authenticated sessions.</p></div>
                </div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-password" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="security_action" value="password">
                    <label class="barama-settings-field"><span>Current password</span><small>Required to authorize this security-sensitive change.</small><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="current_password" required autocomplete="current-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button></div></label>
                    <label class="barama-settings-field"><span>New password</span><small>Use a strong, unique password that satisfies the Barama password policy.</small><div class="barama-settings-control"><i><?= app_icon('key') ?></i><input type="password" name="password" required autocomplete="new-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button></div></label>
                    <label class="barama-settings-field"><span>Confirm new password</span><div class="barama-settings-control"><i><?= app_icon('check') ?></i><input type="password" name="password_confirmation" required autocomplete="new-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button></div></label>
                    <div class="barama-security-popover__actions"><button type="button" class="btn btn-outline-secondary" popovertarget="barama-security-password" popovertargetaction="hide">Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Update password</button></div>
                </form>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-email" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title">
                    <span class="barama-settings-card__icon"><?= app_icon('mail') ?></span>
                    <div><h2>Email & verification</h2><p>Change the primary email through Barama's secure confirmation workflow.</p></div>
                </div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-email" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-popover__summary">
                    <div><span>Current email</span><strong><?= e((string) ($profile['email'] ?? '')) ?></strong></div>
                    <div><span>Verification</span><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></strong></div>
                </div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="security_action" value="email">
                    <label class="barama-settings-field"><span>New email</span><small>A confirmation link will be sent before the primary email changes.</small><div class="barama-settings-control"><i><?= app_icon('mail') ?></i><input type="email" name="new_email" required autocomplete="email"></div></label>
                    <label class="barama-settings-field"><span>Confirm with current password</span><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="current_password" required autocomplete="current-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle aria-label="Show or hide password">View</button></div></label>
                    <div class="barama-security-popover__actions"><button type="button" class="btn btn-outline-secondary" popovertarget="barama-security-email" popovertargetaction="hide">Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('mail') ?> Send confirmation</button></div>
                </form>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-access" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title">
                    <span class="barama-settings-card__icon"><?= app_icon('users') ?></span>
                    <div><h2>Account access</h2><p>Review administrator state and open the full account-access management modules.</p></div>
                </div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-access" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-popover__summary">
                    <div><span>Account status</span><strong><?= e(ucfirst($accountStatus)) ?></strong></div>
                    <div><span>Email security</span><strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>"><?= $emailVerified ? 'Verified' : 'Pending' ?></strong></div>
                    <div><span>Last sign-in</span><strong><?= e($lastLogin !== '' ? $lastLogin : 'Not recorded') ?></strong></div>
                </div>
                <div class="barama-security-popover__module-grid">
                    <a class="barama-security-popover__module" href="<?= e(url('/dashboard/users')) ?>"><i><?= app_icon('users') ?></i><span><strong>Users</strong><small>Roles, status and account access</small></span><span>→</span></a>
                    <a class="barama-security-popover__module" href="<?= e(url('/dashboard/verification')) ?>"><i><?= app_icon('check') ?></i><span><strong>Verification Center</strong><small>Identity and email verification operations</small></span><span>→</span></a>
                </div>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-monitoring" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title">
                    <span class="barama-settings-card__icon"><?= app_icon('shield') ?></span>
                    <div><h2>Security monitoring</h2><p>Use the full audit and mail-operation modules for ongoing security review.</p></div>
                </div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-monitoring" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-popover__module-grid">
                    <a class="barama-security-popover__module" href="<?= e(url('/dashboard/audit')) ?>"><i><?= app_icon('shield') ?></i><span><strong>Security Audit</strong><small>Security events and operational trail</small></span><span>→</span></a>
                    <a class="barama-security-popover__module" href="<?= e(url('/dashboard/mail-queue')) ?>"><i><?= app_icon('pipeline') ?></i><span><strong>Mail Queue & Logs</strong><small>Security and verification email delivery</small></span><span>→</span></a>
                </div>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-passcode" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title"><span class="barama-settings-card__icon"><?= app_icon('key') ?></span><div><h2>Change passcode</h2><p>Use a memorable 6-digit approval code for authenticated workspace changes.</p></div></div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-passcode" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-factor-note"><strong><?= $changePasscodeEnabled ? 'Passcode protection is enabled' : 'Add a 6-digit change passcode' ?></strong><span>Each block accepts one digit. Weak sequences such as 123456, 000000, or repeated digits are rejected.</span></div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form data-security-code-form data-change-confirmation-exempt="1">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="passcode_set">
                    <label class="barama-settings-field"><span>Current password</span><small>Required before passcode protection can be changed.</small><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="current_password" required autocomplete="current-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle>View</button></div></label>
                    <?php if ($changePasscodeEnabled): ?>
                        <div class="barama-settings-field"><span>Current 6-digit passcode</span><small>Confirm the passcode currently protecting workspace changes.</small>
                            <div class="barama-security-code-group" data-security-code-group data-security-code-mask="1" aria-label="Current 6-digit passcode">
                                <?php for ($i = 1; $i <= 6; $i++): ?><input type="password" inputmode="numeric" maxlength="1" autocomplete="off" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?>
                                <input type="hidden" name="current_passcode" data-security-code-value>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="barama-settings-field"><span><?= $changePasscodeEnabled ? 'New 6-digit passcode' : '6-digit passcode' ?></span><small>Choose six digits that are difficult to guess.</small>
                        <div class="barama-security-code-group" data-security-code-group data-security-code-mask="1" aria-label="New 6-digit passcode">
                            <?php for ($i = 1; $i <= 6; $i++): ?><input type="password" inputmode="numeric" maxlength="1" autocomplete="off" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?>
                            <input type="hidden" name="new_passcode" data-security-code-value>
                        </div>
                    </div>
                    <div class="barama-settings-field"><span>Confirm passcode</span><small>Enter the same six digits again.</small>
                        <div class="barama-security-code-group" data-security-code-group data-security-code-mask="1" aria-label="Confirm 6-digit passcode">
                            <?php for ($i = 1; $i <= 6; $i++): ?><input type="password" inputmode="numeric" maxlength="1" autocomplete="off" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?>
                            <input type="hidden" name="new_passcode_confirmation" data-security-code-value>
                        </div>
                    </div>
                    <p class="barama-security-code-error" data-security-code-form-error hidden></p>
                    <div class="barama-security-popover__actions"><button type="button" class="btn btn-outline-secondary" popovertarget="barama-security-passcode" popovertargetaction="hide">Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> <?= $changePasscodeEnabled ? 'Replace passcode' : 'Enable passcode' ?></button></div>
                </form>
                <?php if ($changePasscodeEnabled): ?>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-security-danger-form barama-security-danger-form--stacked" data-settings-confirm="Disable change-passcode protection?" data-security-code-form data-change-confirmation-exempt="1">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="passcode_disable">
                    <input type="password" name="current_password" required placeholder="Current password" autocomplete="current-password">
                    <div class="barama-security-code-group barama-security-code-group--compact" data-security-code-group data-security-code-mask="1" aria-label="Current 6-digit passcode">
                        <?php for ($i = 1; $i <= 6; $i++): ?><input type="password" inputmode="numeric" maxlength="1" autocomplete="off" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?>
                        <input type="hidden" name="current_passcode" data-security-code-value>
                    </div>
                    <button class="btn btn-outline-danger" type="submit">Disable passcode</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-mfa" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title"><span class="barama-settings-card__icon"><?= app_icon('shield') ?></span><div><h2>Multi-factor authentication</h2><p>Protect sign-in with an authenticator app and single-use recovery codes.</p></div></div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-mfa" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body" data-security-mfa-body>
                <?php if ($mfaRecoveryCodes !== []): ?>
                    <div class="barama-security-recovery" data-security-recovery>
                        <div class="barama-security-recovery__head"><div><strong>Save these recovery codes now</strong><p>Each code works once. They will not be shown again after this page.</p></div><span><?= count($mfaRecoveryCodes) ?> codes</span></div>
                        <div data-security-recovery-list><?php foreach ($mfaRecoveryCodes as $recoveryCode): ?><code><?= e($recoveryCode) ?></code><?php endforeach; ?></div>
                        <div class="barama-security-recovery__actions"><button type="button" class="btn btn-outline-secondary" data-security-copy-recovery>Copy all</button><button type="button" class="btn btn-outline-secondary" data-security-download-recovery>Download .txt</button></div>
                    </div>
                <?php endif; ?>

                <?php if (!$mfaEnabled && (!$mfaPending || !$mfaSetupAuthorized || $mfaSetup === null)): ?>
                    <div class="barama-security-factor-note"><strong><?= $mfaPending ? 'Continue pending MFA setup' : 'Authenticator app setup' ?></strong><span>Confirm your identity once. Barama will then keep this Configure popup in the MFA setup flow and display a local QR code plus the manual key.</span></div>
                    <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form data-security-mfa-start>
                        <?= csrf_field() ?><input type="hidden" name="security_action" value="mfa_begin">
                        <label class="barama-settings-field"><span>Current password</span><small>Required to reveal or create the authenticator setup secret.</small><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="current_password" required autocomplete="current-password" data-password-field><button type="button" class="barama-settings-password-toggle" data-password-toggle>View</button></div></label>
                        <div class="barama-security-popover__actions"><button type="button" class="btn btn-outline-secondary" popovertarget="barama-security-mfa" popovertargetaction="hide">Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('key') ?> <?= $mfaPending ? 'Continue setup' : 'Continue to QR setup' ?></button></div>
                    </form>
                <?php elseif (!$mfaEnabled && $mfaPending && $mfaSetupAuthorized && $mfaSetup !== null): ?>
                    <div class="barama-security-mfa-steps" aria-label="MFA setup progress">
                        <span class="is-complete"><b>1</b> Identity confirmed</span><span class="is-active"><b>2</b> Add authenticator</span><span><b>3</b> Verify code</span>
                    </div>
                    <div class="barama-security-mfa-enroll">
                        <section class="barama-security-qr-card">
                            <div class="barama-security-qr" data-security-qr data-security-qr-uri="<?= e((string) $mfaSetup['otpauth_uri']) ?>" aria-live="polite"><span>Generating secure QR code…</span></div>
                            <strong>Scan with your authenticator app</strong>
                            <small>The QR is generated locally in your browser. Your MFA secret is not sent to a QR service.</small>
                        </section>
                        <section class="barama-security-manual-card">
                            <span class="barama-security-step-label">Manual setup</span>
                            <h3>Can’t scan the QR?</h3>
                            <p>Choose manual entry in Google Authenticator, Microsoft Authenticator, Authy, 1Password, or another TOTP app.</p>
                            <label class="barama-settings-field"><span>Setup key</span><small>Keep this key private.</small><div class="barama-security-copy-row"><code data-security-copy-source="mfa-secret"><?= e((string) $mfaSetup['secret']) ?></code><button type="button" class="btn btn-outline-secondary" data-security-copy="mfa-secret">Copy</button></div></label>
                            <div class="barama-security-mfa-meta"><span>Type</span><strong>Time based (TOTP)</strong><span>Digits</span><strong>6</strong><span>Period</span><strong>30 seconds</strong></div>
                        </section>
                    </div>
                    <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form barama-security-verify-form" data-settings-form data-security-code-form data-change-confirmation-exempt="1">
                        <?= csrf_field() ?><input type="hidden" name="security_action" value="mfa_enable">
                        <div class="barama-settings-field"><span>Verify authenticator code</span><small>Enter the current six digits shown by your authenticator app.</small>
                            <div class="barama-security-code-group" data-security-code-group aria-label="6-digit authenticator code">
                                <?php for ($i = 1; $i <= 6; $i++): ?><input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?>
                                <input type="hidden" name="mfa_code" data-security-code-value>
                            </div>
                        </div>
                        <p class="barama-security-code-error" data-security-code-form-error hidden></p>
                        <div class="barama-security-popover__actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Enable MFA</button></div>
                    </form>
                    <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-security-inline-cancel" data-settings-confirm="Cancel pending MFA setup?" data-change-confirmation-exempt="1">
                        <?= csrf_field() ?><input type="hidden" name="security_action" value="mfa_cancel"><input type="password" name="current_password" required placeholder="Current password" autocomplete="current-password"><button class="btn btn-outline-secondary" type="submit">Cancel setup</button>
                    </form>
                <?php else: ?>
                    <div class="barama-security-popover__summary barama-security-mfa-summary"><div><span>Status</span><strong class="is-good">Enabled</strong></div><div><span>Recovery codes remaining</span><strong><?= e((string) $mfaRecoveryRemaining) ?></strong></div><?php if ($mfaConfirmedAt !== ''): ?><div><span>Enabled</span><strong><?= e($mfaConfirmedAt) ?></strong></div><?php endif; ?></div>
                    <div class="barama-security-factor-note"><strong>MFA management</strong><span>Generate replacement recovery codes when your saved set is low, or disable MFA only after password and MFA verification.</span></div>
                    <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form data-security-code-form>
                        <?= csrf_field() ?><input type="hidden" name="security_action" value="mfa_recovery_regenerate">
                        <label class="barama-settings-field"><span>Current password</span><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="current_password" required autocomplete="current-password" data-password-field></div></label>
                        <div class="barama-settings-field"><span>Authenticator code</span><small>Required before all recovery codes are replaced.</small><div class="barama-security-code-group barama-security-code-group--compact" data-security-code-group aria-label="6-digit authenticator code"><?php for ($i = 1; $i <= 6; $i++): ?><input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" data-security-code-digit aria-label="Digit <?= $i ?>"><?php endfor; ?><input type="hidden" name="mfa_code" data-security-code-value></div></div>
                        <p class="barama-security-code-error" data-security-code-form-error hidden></p>
                        <div class="barama-security-popover__actions"><button class="btn btn-outline-secondary" type="submit">Generate new recovery codes</button></div>
                    </form>
                    <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-security-danger-form" data-settings-confirm="Disable multi-factor authentication?">
                        <?= csrf_field() ?><input type="hidden" name="security_action" value="mfa_disable"><input type="password" name="current_password" required placeholder="Current password" autocomplete="current-password"><input type="text" name="mfa_code" required maxlength="24" placeholder="Authenticator or recovery code" autocomplete="one-time-code"><button class="btn btn-outline-danger" type="submit">Disable MFA</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($workspace === 'dashboard'): ?>
        <div class="barama-security-popover" id="barama-security-spam" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title"><span class="barama-settings-card__icon"><?= app_icon('shield') ?></span><div><h2>Spam Protection</h2><p>Control server-side public-form throttling and repeated-submission defense.</p></div></div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-spam" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-protection-summary">
                    <div class="barama-security-protection-summary__item"><span>Status</span><strong><?= $spamProtectionEnabled ? 'Enabled' : 'Disabled' ?></strong></div>
                    <div class="barama-security-protection-summary__item"><span>Blocked · 24h</span><strong><?= e((string) ($securityProtectionStats['spam_24h'] ?? 0)) ?></strong></div>
                    <div class="barama-security-protection-summary__item"><span>Counter storage</span><strong><?= !empty($securityProtection['rate_state_writable']) ? 'Writable' : 'Needs attention' ?></strong></div>
                </div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="spam_protection_save">
                    <label class="barama-security-protection-check"><input type="checkbox" name="enabled" value="1" <?= $spamProtectionEnabled ? 'checked' : '' ?>><span><strong>Enable Spam Protection</strong><small>Protect contact, inquiry and newsletter submission endpoints.</small></span></label>
                    <div class="barama-security-protection-grid">
                        <label class="barama-settings-field"><span>Rate window</span><small>Count submissions from the same source inside this window.</small><div class="barama-settings-control"><i><?= app_icon('clock') ?></i><select name="window_seconds"><?php foreach ([300=>'5 minutes',600=>'10 minutes',900=>'15 minutes',1800=>'30 minutes'] as $value=>$label): ?><option value="<?= $value ?>" <?= (int) ($spamProtection['window_seconds'] ?? 600) === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></label>
                        <label class="barama-settings-field"><span>Maximum submissions</span><small>Requests allowed per source and endpoint before a temporary block.</small><div class="barama-settings-control"><i><?= app_icon('shield') ?></i><select name="max_requests"><?php foreach ([3,5,8,10,15] as $value): ?><option value="<?= $value ?>" <?= (int) ($spamProtection['max_requests'] ?? 8) === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></div></label>
                        <label class="barama-settings-field"><span>Repeated identical submissions</span><small>Lower threshold for the same normalized payload.</small><div class="barama-settings-control"><i><?= app_icon('layers') ?></i><select name="duplicate_max"><?php foreach ([2,3,5] as $value): ?><option value="<?= $value ?>" <?= (int) ($spamProtection['duplicate_max'] ?? 3) === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></div></label>
                        <label class="barama-settings-field"><span>Temporary block</span><small>How long a source waits after exceeding the threshold.</small><div class="barama-settings-control"><i><?= app_icon('clock') ?></i><select name="block_seconds"><?php foreach ([300=>'5 minutes',900=>'15 minutes',1800=>'30 minutes',3600=>'1 hour'] as $value=>$label): ?><option value="<?= $value ?>" <?= (int) ($spamProtection['block_seconds'] ?? 900) === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></label>
                    </div>
                    <div class="barama-security-popover__actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Spam Protection</button></div>
                </form>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-security-danger-form" data-settings-confirm="Clear current Spam Protection rate counters?">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="spam_counters_clear"><button class="btn btn-outline-secondary" type="submit">Clear rate counters</button>
                </form>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-hack" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title"><span class="barama-settings-card__icon"><?= app_icon('shield') ?></span><div><h2>Hack Protection</h2><p>Block high-confidence malicious requests and optionally send throttled administrator alerts.</p></div></div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-hack" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-protection-summary">
                    <div class="barama-security-protection-summary__item"><span>Status</span><strong><?= $hackProtectionEnabled ? 'Enabled' : 'Disabled' ?></strong></div>
                    <div class="barama-security-protection-summary__item"><span>Blocked · 24h</span><strong><?= e((string) ($securityProtectionStats['hack_24h'] ?? 0)) ?></strong></div>
                    <div class="barama-security-protection-summary__item"><span>Hacking alerts</span><strong><?= $hackAlertsEnabled ? 'Enabled' : 'Disabled' ?></strong></div>
                </div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="hack_protection_save">
                    <label class="barama-security-protection-check"><input type="checkbox" name="enabled" value="1" <?= $hackProtectionEnabled ? 'checked' : '' ?>><span><strong>Enable Hack Protection</strong><small>Block high-confidence malicious requests before the target controller executes.</small></span></label>
                    <div class="barama-security-protection-grid">
                        <label class="barama-security-protection-check"><input type="checkbox" name="detect_path_probes" value="1" <?= !array_key_exists('detect_path_probes',$hackProtection) || !empty($hackProtection['detect_path_probes']) ? 'checked' : '' ?>><span><strong>Scanner & secret-file probes</strong><small>Blocks probes for .env, .git, wp-admin, phpMyAdmin, private keys and similar targets.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="detect_traversal" value="1" <?= !array_key_exists('detect_traversal',$hackProtection) || !empty($hackProtection['detect_traversal']) ? 'checked' : '' ?>><span><strong>Traversal & wrapper patterns</strong><small>Blocks ../ traversal, php://, file:// and sensitive filesystem targets.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="detect_injection" value="1" <?= !array_key_exists('detect_injection',$hackProtection) || !empty($hackProtection['detect_injection']) ? 'checked' : '' ?>><span><strong>Public injection signatures</strong><small>Blocks high-confidence script and SQL injection signatures on unauthenticated input.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="alerts_enabled" value="1" <?= $hackAlertsEnabled ? 'checked' : '' ?>><span><strong>Email hacking alerts</strong><small>Queue a throttled email after a suspicious request is blocked. Every event is still written to Security Audit.</small></span></label>
                    </div>
                    <div class="barama-security-protection-grid">
                        <label class="barama-settings-field"><span>Alert email</span><small>Used only for security alerts. Raw malicious payloads are never included.</small><div class="barama-settings-control"><i><?= app_icon('mail') ?></i><input type="email" name="alert_email" value="<?= e((string) ($hackProtection['alert_email'] ?? '')) ?>" placeholder="<?= e((string) ($profile['email'] ?? 'admin@example.com')) ?>" maxlength="191"></div></label>
                        <label class="barama-settings-field"><span>Alert cooldown</span><small>Maximum alert-email frequency. Audit events continue during cooldown.</small><div class="barama-settings-control"><i><?= app_icon('clock') ?></i><select name="alert_cooldown_seconds"><?php foreach ([900=>'15 minutes',1800=>'30 minutes',3600=>'1 hour',21600=>'6 hours'] as $value=>$label): ?><option value="<?= $value ?>" <?= (int) ($hackProtection['alert_cooldown_seconds'] ?? 1800) === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div></label>
                    </div>
                    <div class="barama-security-popover__actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Hack Protection</button></div>
                </form>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-security-danger-form">
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="hack_alert_test"><button class="btn btn-outline-secondary" type="submit">Send test hacking alert</button>
                </form>
            </div>
        </div>

        <div class="barama-security-popover" id="barama-security-right-click" popover="auto">
            <header class="barama-security-popover__head">
                <div class="barama-security-popover__title"><span class="barama-settings-card__icon"><?= app_icon('lock') ?></span><div><h2>Right Click Protection</h2><p>Optional public-site content deterrence for casual copying.</p></div></div>
                <button type="button" class="barama-security-popover__close" popovertarget="barama-security-right-click" popovertargetaction="hide" aria-label="Close">×</button>
            </header>
            <div class="barama-security-popover__body">
                <div class="barama-security-protection-note"><strong>Important:</strong> browser-side right-click controls cannot prevent screenshots, developer tools, direct downloads, or determined copying. Use this only as a content deterrent.</div>
                <form action="<?= e(url($base . '/security')) ?>" method="post" class="barama-settings-form" data-settings-form>
                    <?= csrf_field() ?><input type="hidden" name="security_action" value="right_click_protection_save">
                    <div class="barama-security-protection-grid barama-security-protection-grid--single">
                        <label class="barama-security-protection-check"><input type="checkbox" name="enabled" value="1" <?= $rightClickProtectionEnabled ? 'checked' : '' ?>><span><strong>Enable on the public website</strong><small>Does not apply to Admin, Vendor/Developer, Customer settings, or authentication forms.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="block_context_menu" value="1" <?= !array_key_exists('block_context_menu',$rightClickProtection) || !empty($rightClickProtection['block_context_menu']) ? 'checked' : '' ?>><span><strong>Block context menu</strong><small>Prevents normal right-click context menus outside editable fields.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="block_image_drag" value="1" <?= !array_key_exists('block_image_drag',$rightClickProtection) || !empty($rightClickProtection['block_image_drag']) ? 'checked' : '' ?>><span><strong>Block image dragging</strong><small>Discourages dragging public images directly to the desktop.</small></span></label>
                        <label class="barama-security-protection-check"><input type="checkbox" name="block_text_selection" value="1" <?= !empty($rightClickProtection['block_text_selection']) ? 'checked' : '' ?>><span><strong>Block general text selection</strong><small>Optional and disabled by default because it can reduce accessibility and usability. Form fields and code remain selectable.</small></span></label>
                    </div>
                    <div class="barama-security-popover__actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Right Click Protection</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($section === 'mail' && $workspace === 'dashboard'): ?>
        <?= \Barama\Core\View::partial('workspace/settings-mail', [
            'mailSettings' => $mailSettings ?? [],
        ]) ?>
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

<?php if ($section === 'security'): ?>
<script src="<?= e(asset('js/vendor/qrcode-v133.js') . '?v=133') ?>" defer></script>
<script src="<?= e(asset('js/admin-settings-security-factors.js') . '?v=1.0.0-clean1') ?>" defer></script>
<?php if ($workspace === 'dashboard'): ?><script src="<?= e(asset('js/admin-settings-security-protection-v134.js') . '?v=134') ?>" defer></script><?php endif; ?>
<?php endif; ?>
<script src="<?= e(asset('js/admin-settings-v120.js') . '?v=120') ?>" defer></script>

<?php if ($workspace === 'dashboard' && !empty($saved)): ?>
<script data-barama-default-theme-save-sync>
(() => {
    const selected = document.querySelector('input[name=\"default_theme\"]:checked');
    const theme = selected instanceof HTMLInputElement ? selected.value : '';
    if (theme !== 'light' && theme !== 'dark') return;
    try { window.localStorage.setItem('barama-theme', theme); } catch (error) {}
    document.documentElement.setAttribute('data-bs-theme', theme);
})();
</script>
<?php endif; ?>
