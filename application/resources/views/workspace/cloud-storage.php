<?php
$cloud = is_array($cloudStorage ?? null) ? $cloudStorage : [];
$providers = is_array($cloud['providers'] ?? null) ? $cloud['providers'] : [];
$s3 = is_array($providers['s3'] ?? null) ? $providers['s3'] : [];
$r2 = is_array($providers['r2'] ?? null) ? $providers['r2'] : [];
$activeDisk = (string) ($cloud['active_disk'] ?? 'private');
$base = '/dashboard/settings';
$settingsTabs = [
    ['key' => 'basic', 'label' => 'Basic', 'icon' => 'settings'],
    ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user'],
    ['key' => 'privacy', 'label' => 'Privacy', 'icon' => 'shield'],
    ['key' => 'security', 'label' => 'Security', 'icon' => 'lock'],
    ['key' => 'storage', 'label' => 'Storage', 'icon' => 'image'],
    ['key' => 'cloud-storage', 'label' => 'Cloud Storage', 'icon' => 'layers'],
];
?>
<link rel="stylesheet" href="<?= e(asset('css/admin-settings-v120.css') . '?v=120') ?>">

<section class="workspace-page barama-settings" data-admin-settings>
    <header class="barama-settings__hero">
        <div class="barama-settings__hero-copy">
            <span class="eyebrow">ADMIN SETTINGS</span>
            <h1>Cloud Storage</h1>
            <p>Configure Amazon S3 or Cloudflare R2 for managed media while keeping local storage available as a safe fallback.</p>
        </div>
        <div class="barama-settings__workspace-badge">
            <span class="barama-settings__workspace-icon"><?= app_icon('layers') ?></span>
            <span><strong>Admin Dashboard</strong><small>Cloud Storage configuration</small></span>
        </div>
    </header>

    <nav class="barama-settings-tabs" aria-label="Settings sections">
        <?php foreach ($settingsTabs as $tab): ?>
            <?php $isActive = $tab['key'] === 'cloud-storage'; ?>
            <a href="<?= e(url($base . '/' . $tab['key'])) ?>" class="<?= $isActive ? 'is-active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                <span><?= app_icon($tab['icon']) ?></span><strong><?= e($tab['label']) ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($saved) || !empty($error)): ?>
        <div class="barama-settings-toast <?= !empty($error) ? 'is-error' : 'is-success' ?>" role="status" aria-live="polite" data-settings-toast>
            <span class="barama-settings-toast__icon"><?= app_icon(!empty($error) ? 'alert' : 'check') ?></span>
            <span>
                <strong><?= !empty($error) ? 'Cloud storage was not changed' : 'Cloud storage updated' ?></strong>
                <small><?= e(!empty($error) ? (string) $error : ($savedMessage !== '' ? $savedMessage : 'The storage configuration is now active.')) ?></small>
            </span>
            <button type="button" aria-label="Dismiss notification" data-settings-toast-close>×</button>
        </div>
    <?php endif; ?>

    <div class="barama-settings-storage-overview">
        <article><span><?= app_icon('layers') ?></span><div><small>Active disk</small><strong><?= e($activeDisk === 'private' ? 'Local storage' : strtoupper($activeDisk)) ?></strong></div></article>
        <article><span><?= app_icon('check') ?></span><div><small>Amazon S3</small><strong class="<?= !empty($s3['tested']) ? 'is-good' : 'is-warn' ?>"><?= !empty($s3['tested']) ? 'Tested' : (!empty($s3['configured']) ? 'Needs test' : 'Not configured') ?></strong></div></article>
        <article><span><?= app_icon('check') ?></span><div><small>Cloudflare R2</small><strong class="<?= !empty($r2['tested']) ? 'is-good' : 'is-warn' ?>"><?= !empty($r2['tested']) ? 'Tested' : (!empty($r2['configured']) ? 'Needs test' : 'Not configured') ?></strong></div></article>
        <article><span><?= app_icon('shield') ?></span><div><small>Credential protection</small><strong class="<?= !empty($cloud['openssl_available']) && !empty($cloud['app_key_available']) ? 'is-good' : 'is-warn' ?>"><?= !empty($cloud['openssl_available']) && !empty($cloud['app_key_available']) ? 'Encrypted' : 'Needs attention' ?></strong></div></article>
    </div>

    <div class="barama-settings-grid barama-settings-grid--wide">
        <section class="barama-settings-card">
            <div class="barama-settings-card__head">
                <span class="barama-settings-card__icon"><?= app_icon('layers') ?></span>
                <div><h2>Active storage</h2><p>New uploads use this disk. Existing media keeps its original disk and is not rewritten when you switch providers.</p></div>
            </div>
            <form action="<?= e(url($base . '/cloud-storage')) ?>" method="post" class="barama-settings-form" data-settings-form>
                <?= csrf_field() ?><input type="hidden" name="cloud_action" value="activate">
                <label class="barama-settings-field">
                    <span>Storage disk</span><small>S3 or R2 must pass a real write/read/delete connection test before activation.</small>
                    <div class="barama-settings-control"><i><?= app_icon('layers') ?></i><select name="disk">
                        <option value="private" <?= $activeDisk === 'private' ? 'selected' : '' ?>>Local private storage</option>
                        <option value="s3" <?= $activeDisk === 's3' ? 'selected' : '' ?> <?= empty($s3['tested']) ? 'disabled' : '' ?>>Amazon S3<?= empty($s3['tested']) ? ' — test required' : '' ?></option>
                        <option value="r2" <?= $activeDisk === 'r2' ? 'selected' : '' ?> <?= empty($r2['tested']) ? 'disabled' : '' ?>>Cloudflare R2<?= empty($r2['tested']) ? ' — test required' : '' ?></option>
                    </select></div>
                </label>
                <div class="barama-settings-actions"><button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save active storage</button></div>
            </form>
        </section>

        <aside class="barama-settings-card">
            <div class="barama-settings-card__head"><span class="barama-settings-card__icon"><?= app_icon('shield') ?></span><div><h2>Safety rules</h2><p>Cloud storage is fail-closed and keeps existing media bound to its original disk.</p></div></div>
            <div class="barama-settings-summary">
                <div><span>Secrets at rest</span><strong>AES-256-GCM</strong></div>
                <div><span>Provider endpoints</span><strong>Derived HTTPS only</strong></div>
                <div><span>Cloud activation</span><strong>Connection test required</strong></div>
                <div><span>Existing media</span><strong>Disk preserved</strong></div>
            </div>
        </aside>
    </div>

    <div class="barama-settings-grid barama-settings-grid--equal">
        <section class="barama-settings-card">
            <div class="barama-settings-card__head"><span class="barama-settings-card__icon"><?= app_icon('layers') ?></span><div><h2>Amazon S3</h2><p>Use an IAM access key scoped to this bucket and object prefix.</p></div></div>
            <form action="<?= e(url($base . '/cloud-storage')) ?>" method="post" class="barama-settings-form" data-settings-form autocomplete="off">
                <?= csrf_field() ?><input type="hidden" name="provider" value="s3">
                <div class="barama-settings-fields">
                    <label class="barama-settings-field"><span>Bucket</span><small><?= !empty($s3['location_locked']) ? 'Locked because this provider already stores media.' : 'Example: barama-production-media' ?></small><div class="barama-settings-control <?= !empty($s3['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('layers') ?></i><input name="bucket" value="<?= e((string)($s3['bucket'] ?? '')) ?>" maxlength="63" required <?= !empty($s3['location_locked']) ? 'readonly' : '' ?>></div></label>
                    <label class="barama-settings-field"><span>AWS region</span><small><?= !empty($s3['location_locked']) ? 'Locked with the existing S3 media location.' : 'Example: ap-southeast-1' ?></small><div class="barama-settings-control <?= !empty($s3['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('globe') ?></i><input name="region" value="<?= e((string)($s3['region'] ?? 'ap-southeast-1')) ?>" maxlength="32" required <?= !empty($s3['location_locked']) ? 'readonly' : '' ?>></div></label>
                    <label class="barama-settings-field"><span>Access key ID</span><small>Credential rotation is allowed without moving existing objects.</small><div class="barama-settings-control"><i><?= app_icon('user') ?></i><input name="access_key_id" value="<?= e((string)($s3['access_key_id'] ?? '')) ?>" maxlength="128" required autocomplete="off"></div></label>
                    <label class="barama-settings-field"><span>Secret access key</span><small><?= !empty($s3['has_secret']) ? 'A secret is saved securely. Leave blank to keep it.' : 'Required for the first configuration.' ?></small><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="secret_access_key" value="" maxlength="256" <?= empty($s3['has_secret']) ? 'required' : '' ?> autocomplete="new-password"></div></label>
                    <label class="barama-settings-field"><span>Object prefix</span><small><?= !empty($s3['location_locked']) ? 'Locked while S3 owns existing media.' : 'Separates Barama objects inside a shared bucket.' ?></small><div class="barama-settings-control <?= !empty($s3['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('tag') ?></i><input name="prefix" value="<?= e((string)($s3['prefix'] ?? 'barama/')) ?>" maxlength="160" <?= !empty($s3['location_locked']) ? 'readonly' : '' ?>></div></label>
                </div>
                <div class="barama-settings-actions"><button class="btn btn-primary" type="submit" name="cloud_action" value="save_provider"><?= app_icon('check') ?> Save S3 configuration</button><?php if (!empty($s3['configured'])): ?><button class="btn btn-outline-secondary" type="submit" name="cloud_action" value="test"><?= app_icon('check') ?> Test S3 connection</button><?php endif; ?></div>
            </form>
        </section>

        <section class="barama-settings-card">
            <div class="barama-settings-card__head"><span class="barama-settings-card__icon"><?= app_icon('globe') ?></span><div><h2>Cloudflare R2</h2><p>Use an R2 API token credential with object read/write/delete access.</p></div></div>
            <form action="<?= e(url($base . '/cloud-storage')) ?>" method="post" class="barama-settings-form" data-settings-form autocomplete="off">
                <?= csrf_field() ?><input type="hidden" name="provider" value="r2">
                <div class="barama-settings-fields">
                    <label class="barama-settings-field"><span>Account ID</span><small><?= !empty($r2['location_locked']) ? 'Locked because this provider already stores media.' : '32-character Cloudflare account identifier.' ?></small><div class="barama-settings-control <?= !empty($r2['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('user') ?></i><input name="account_id" value="<?= e((string)($r2['account_id'] ?? '')) ?>" maxlength="32" required autocomplete="off" <?= !empty($r2['location_locked']) ? 'readonly' : '' ?>></div></label>
                    <label class="barama-settings-field"><span>Bucket</span><small><?= !empty($r2['location_locked']) ? 'Locked with the existing R2 media location.' : 'Your Cloudflare R2 bucket name.' ?></small><div class="barama-settings-control <?= !empty($r2['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('layers') ?></i><input name="bucket" value="<?= e((string)($r2['bucket'] ?? '')) ?>" maxlength="63" required <?= !empty($r2['location_locked']) ? 'readonly' : '' ?>></div></label>
                    <label class="barama-settings-field"><span>Access key ID</span><small>Generated from an R2 API token; safe credential rotation is supported.</small><div class="barama-settings-control"><i><?= app_icon('user') ?></i><input name="access_key_id" value="<?= e((string)($r2['access_key_id'] ?? '')) ?>" maxlength="128" required autocomplete="off"></div></label>
                    <label class="barama-settings-field"><span>Secret access key</span><small><?= !empty($r2['has_secret']) ? 'A secret is saved securely. Leave blank to keep it.' : 'Required for the first configuration.' ?></small><div class="barama-settings-control"><i><?= app_icon('lock') ?></i><input type="password" name="secret_access_key" value="" maxlength="256" <?= empty($r2['has_secret']) ? 'required' : '' ?> autocomplete="new-password"></div></label>
                    <label class="barama-settings-field"><span>Object prefix</span><small><?= !empty($r2['location_locked']) ? 'Locked while R2 owns existing media.' : 'Separates Barama objects inside a shared bucket.' ?></small><div class="barama-settings-control <?= !empty($r2['location_locked']) ? 'is-readonly' : '' ?>"><i><?= app_icon('tag') ?></i><input name="prefix" value="<?= e((string)($r2['prefix'] ?? 'barama/')) ?>" maxlength="160" <?= !empty($r2['location_locked']) ? 'readonly' : '' ?>></div></label>
                </div>
                <div class="barama-settings-actions"><button class="btn btn-primary" type="submit" name="cloud_action" value="save_provider"><?= app_icon('check') ?> Save R2 configuration</button><?php if (!empty($r2['configured'])): ?><button class="btn btn-outline-secondary" type="submit" name="cloud_action" value="test"><?= app_icon('check') ?> Test R2 connection</button><?php endif; ?></div>
            </form>
        </section>
    </div>
</section>

<script src="<?= e(asset('js/admin-settings-v120.js') . '?v=120') ?>" defer></script>
