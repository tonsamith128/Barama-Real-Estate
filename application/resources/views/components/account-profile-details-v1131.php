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
            <div class="barama-avatar-v107" data-avatar-v107>
                <div class="barama-avatar-v107__portrait" aria-label="Current profile avatar">
                    <?php if ($avatarUuid !== ''): ?>
                        <img class="barama-avatar-v107__photo" src="<?= e(url('/account/avatar/' . rawurlencode($avatarUuid))) ?>" alt="<?= e(($profileName !== '' ? $profileName : 'Account') . ' profile image') ?>" width="112" height="112" loading="eager">
                    <?php else: ?>
                        <?= app_icon('user') ?>
                    <?php endif; ?>
                </div>
                <div class="barama-avatar-v107__actions">
                    <form method="post" action="<?= e(url($base . '/profile')) ?>" enctype="multipart/form-data" data-avatar-v107-upload>
                        <?= csrf_field() ?>
                        <input type="hidden" name="profile_action" value="avatar_upload">
                        <input class="barama-avatar-v107__input" type="file" name="avatar" id="avatar-v107-account" accept="image/jpeg,image/png,image/webp" data-avatar-v107-input>
                        <label class="barama-avatar-v107__action" for="avatar-v107-account" tabindex="0" title="<?= $avatarUuid !== '' ? 'Replace profile image' : 'Upload profile image' ?>">
                            <?= app_icon('image') ?> <span><?= $avatarUuid !== '' ? 'Replace' : 'Upload' ?></span>
                        </label>
                    </form>
                    <?php if ($avatarUuid !== ''): ?>
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
            <div>
                <h2><?= e($profileName !== '' ? $profileName : 'Account') ?></h2>
                <p><?= e($profileEmail) ?></p>
            </div>
        </div>

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
