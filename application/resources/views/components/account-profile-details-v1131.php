<?php
/* BARAMA_ACCOUNT_PROFILE_DETAILS_V1131 */
$avatarUuid = trim((string) ($profile['avatar_uuid'] ?? ''));
$profileName = trim((string) ($profile['name'] ?? ''));
$profileEmail = trim((string) ($profile['email'] ?? ''));
$profilePhone = trim((string) ($profile['phone'] ?? ''));
$profileDob = trim((string) ($profile['date_of_birth'] ?? ''));
$profileAddress = trim((string) ($profile['address'] ?? ''));
?>
<div class="barama-settings-grid barama-settings-grid--wide barama-profile-v1131">
    <section class="barama-settings-card barama-profile-v1131__details">
        <div class="barama-settings-card__head">
            <span class="barama-settings-card__icon"><?= app_icon('user') ?></span>
            <div>
                <h2>Profile identity</h2>
                <p>Keep your personal identity and contact information accurate.</p>
            </div>
        </div>

        <form
            action="<?= e(url($base . '/profile')) ?>"
            method="post"
            class="barama-settings-form"
            data-settings-form
            data-profile-details-v1131
        >
            <?= csrf_field() ?>
            <input type="hidden" name="profile_action" value="details">

            <div class="barama-settings-fields barama-profile-v1131__fields">
                <label class="barama-settings-field">
                    <span>Full Name</span>
                    <small>Displayed in your account identity and signed-in activity.</small>
                    <div class="barama-settings-control">
                        <i><?= app_icon('user') ?></i>
                        <input name="name" value="<?= e($profileName) ?>" required maxlength="120" autocomplete="name">
                    </div>
                </label>

                <label class="barama-settings-field">
                    <span>Phone</span>
                    <small>Optional contact number for account operations.</small>
                    <div class="barama-settings-control">
                        <i><?= app_icon('phone') ?></i>
                        <input name="phone" value="<?= e($profilePhone) ?>" maxlength="60" autocomplete="tel">
                    </div>
                </label>

                <label class="barama-settings-field">
                    <span>Email</span>
                    <small>Email changes are protected by Security settings.</small>
                    <div class="barama-settings-control is-readonly">
                        <i><?= app_icon('mail') ?></i>
                        <input value="<?= e($profileEmail) ?>" disabled autocomplete="email">
                    </div>
                </label>

                <label class="barama-settings-field">
                    <span>Date of Birth</span>
                    <small>Used only as part of your private customer profile.</small>
                    <div class="barama-settings-control">
                        <i><?= app_icon('calendar') ?></i>
                        <input type="date" name="date_of_birth" value="<?= e($profileDob) ?>"
                            min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" autocomplete="bday">
                    </div>
                </label>

                <label class="barama-settings-field barama-profile-v1131__address">
                    <span>Address</span>
                    <small>Your private contact address. Maximum 500 characters.</small>
                    <div class="barama-settings-control barama-profile-v1131__textarea-control">
                        <i><?= app_icon('map') ?></i>
                        <textarea name="address" maxlength="500" rows="4"
                            autocomplete="street-address"><?= e($profileAddress) ?></textarea>
                    </div>
                </label>
            </div>

            <div class="barama-settings-actions barama-account-v1130-actions">
                <button class="btn btn-primary" type="submit">
                    <?= app_icon('check') ?> Save profile
                </button>
                <button class="btn btn-outline-secondary" type="reset" data-account-discard-profile>
                    Discard changes
                </button>
                <a class="barama-settings-secondary-link" href="<?= e(url($base . '/security')) ?>">
                    Manage email & password
                </a>
            </div>
        </form>
    </section>

    <aside class="barama-settings-card barama-settings-card--identity barama-profile-v1131__avatar-card">
        <div class="barama-profile-v1131__avatar-head">
            <div class="barama-profile-v1131__avatar" data-profile-avatar-preview aria-label="Profile avatar">
                <?php if ($avatarUuid !== ''): ?>
                    <img
                        src="<?= e(url('/account/avatar/' . rawurlencode($avatarUuid))) ?>"
                        alt="<?= e($profileName !== '' ? $profileName . ' profile avatar' : 'Profile avatar') ?>"
                        loading="lazy"
                    >
                <?php else: ?>
                    <span><?= app_icon('user') ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h2><?= e($profileName !== '' ? $profileName : 'Account') ?></h2>
                <p><?= e($profileEmail) ?></p>
            </div>
        </div>

        <form
            action="<?= e(url($base . '/profile')) ?>"
            method="post"
            enctype="multipart/form-data"
            class="barama-profile-v1131__avatar-form"
            data-profile-avatar-form
        >
            <?= csrf_field() ?>
            <input type="hidden" name="profile_action" value="avatar_upload">

            <label class="barama-profile-v1131__dropzone" data-profile-avatar-dropzone tabindex="0">
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-profile-avatar-input>
                <span class="barama-profile-v1131__dropzone-icon"><?= app_icon('image') ?></span>
                <strong>Drop your profile photo here</strong>
                <small>or click to choose a JPG, PNG, or WebP image · max 5 MB</small>
                <span class="barama-profile-v1131__file-name" data-profile-avatar-name>No file selected</span>
            </label>

            <button class="btn btn-primary" type="submit" data-profile-avatar-submit disabled>
                <?= app_icon('check') ?> Save avatar
            </button>
        </form>

        <?php if ($avatarUuid !== ''): ?>
            <form action="<?= e(url($base . '/profile')) ?>" method="post"
                class="barama-profile-v1131__remove"
                data-settings-confirm="Remove your current profile avatar?">
                <?= csrf_field() ?>
                <input type="hidden" name="profile_action" value="avatar_remove">
                <button class="btn btn-outline-secondary" type="submit">Remove avatar</button>
            </form>
        <?php endif; ?>

        <div class="barama-profile-v1131__status">
            <div class="barama-settings-status-row">
                <span>Email</span>
                <strong class="<?= $emailVerified ? 'is-good' : 'is-warn' ?>">
                    <?= $emailVerified ? 'Verified' : 'Pending' ?>
                </strong>
            </div>
            <div class="barama-settings-status-row">
                <span>Account</span>
                <strong><?= e(ucfirst($accountStatus)) ?></strong>
            </div>
        </div>
    </aside>
</div>
