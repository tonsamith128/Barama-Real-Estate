<?php
/** @var array<string,mixed> $mailSettings */
$mail = is_array($mailSettings ?? null) ? $mailSettings : [];
$driver = (string) ($mail['driver'] ?? 'mail');
?>
<section class="barama-mail-settings" aria-label="Mail Setup">
    <div class="barama-mail-settings__heading">
        <div>
            <h2><?= app_icon('mail') ?> Delivery configuration</h2>
            <p>Choose PHP Mail or SMTP for verification emails, notifications and system messages.</p>
        </div>
        <span class="barama-mail-settings__state"><?= !empty($mail['saved_override']) ? 'Custom configuration' : 'Installer defaults' ?></span>
    </div>

    <form class="barama-mail-settings__form" action="<?= e(url('/dashboard/settings/mail')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="mail_action" value="save">
        <div class="barama-mail-settings__grid">
            <label class="barama-settings-field">
                <span>Delivery method</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('mail') ?></i>
                    <select name="mail_driver" required>
                        <option value="mail" <?= $driver === 'mail' ? 'selected' : '' ?>>PHP Mail</option>
                        <option value="smtp" <?= $driver === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                    </select>
                </div>
                <small>SMTP is recommended for dependable delivery and authenticated transport.</small>
            </label>
            <label class="barama-settings-field">
                <span>From name</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('user') ?></i>
                    <input name="mail_from_name" value="<?= e((string) ($mail['from_name'] ?? '')) ?>" maxlength="120" required autocomplete="off">
                </div>
            </label>
            <label class="barama-settings-field">
                <span>From email</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('mail') ?></i>
                    <input name="mail_from_address" type="email" value="<?= e((string) ($mail['from_address'] ?? '')) ?>" maxlength="191" required autocomplete="off">
                </div>
            </label>
        </div>

        <div class="barama-mail-settings__divider">
            <h3>SMTP server</h3>
            <p>Used only when SMTP is selected. Changing to PHP Mail removes stored SMTP credentials.</p>
        </div>
        <div class="barama-mail-settings__grid">
            <label class="barama-settings-field">
                <span>SMTP host</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('globe') ?></i>
                    <input name="mail_host" value="<?= e((string) ($mail['host'] ?? '')) ?>" maxlength="255" placeholder="smtp.example.com" autocomplete="off">
                </div>
            </label>
            <label class="barama-settings-field">
                <span>Port</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('settings') ?></i>
                    <input name="mail_port" type="number" min="1" max="65535" value="<?= e((string) ($mail['port'] ?? 587)) ?>">
                </div>
            </label>
            <label class="barama-settings-field">
                <span>Encryption</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('lock') ?></i>
                    <select name="mail_encryption">
                        <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None (not recommended)'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($mail['encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </label>
            <label class="barama-settings-field">
                <span>Username</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('user') ?></i>
                    <input name="mail_username" value="<?= e((string) ($mail['username'] ?? '')) ?>" maxlength="191" autocomplete="off">
                </div>
            </label>
            <label class="barama-settings-field">
                <span>SMTP password</span>
                <div class="barama-settings-control">
                    <i><?= app_icon('key') ?></i>
                    <input name="mail_password" type="password" value="" maxlength="1024" autocomplete="new-password" placeholder="<?= !empty($mail['has_password']) ? 'Saved securely · leave blank to keep' : 'Enter SMTP password' ?>">
                </div>
                <small><?= !empty($mail['has_password']) ? 'A password is saved. It is never displayed. Leave blank to keep it when host and username remain unchanged.' : 'Only required for authenticated SMTP.' ?></small>
            </label>
        </div>
        <div class="barama-mail-settings__actions">
            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Mail Setup</button>
            <small>Settings are encrypted using this installation’s APP_KEY.</small>
        </div>
    </form>

    <div class="barama-mail-settings__test">
        <div>
            <h3><?= app_icon('check') ?> Test delivery</h3>
            <p>Send one test email to your signed-in administrator address. Test uses the currently saved configuration.</p>
        </div>
        <form action="<?= e(url('/dashboard/settings/mail')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="mail_action" value="test">
            <button class="btn btn-outline-secondary" type="submit" <?= empty($mail['ready']) ? 'disabled' : '' ?>><?= app_icon('mail') ?> Send test email</button>
        </form>
    </div>
</section>
