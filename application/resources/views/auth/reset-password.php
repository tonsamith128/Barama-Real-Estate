<section class="auth-premium">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow">Set a new password</span>
                <h1>Secure your account with a fresh password.</h1>
                <p>Reset tokens are one-time, time-limited credentials. Your new password replaces the previous credential immediately after a successful reset.</p>

                <div class="auth-story__features">
                    <article><span><?= app_icon('shield') ?></span><div><strong>Protected token</strong><small>The reset token is stored only as a one-way hash.</small></div></article>
                    <article><span><?= app_icon('lock') ?></span><div><strong>Strong password</strong><small>Use at least 12 characters or generate one securely.</small></div></article>
                    <article><span><?= app_icon('check') ?></span><div><strong>Immediate update</strong><small>Return to sign in after the reset succeeds.</small></div></article>
                </div>
            </aside>

            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('lock') ?></span>
                    <div><h2>Reset password</h2><p>Choose a new password for your account.</p></div>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <?php if ($valid): ?>
                    <form action="<?= e(url('/reset-password/' . rawurlencode($token))) ?>" method="post" class="auth-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <label class="auth-field" for="reset-password">
                            <span>New password</span>
                            <span class="auth-input auth-input--action">
                                <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                                <input class="form-control" id="reset-password" name="password" type="password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-primary>
                                <button class="auth-input__generate" type="button" data-auth-generate-password>Generate</button>
                            </span>
                        </label>

                        <label class="auth-field" for="reset-confirmation">
                            <span>Confirm new password</span>
                            <span class="auth-input">
                                <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                                <input class="form-control" id="reset-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-confirmation>
                                <button class="auth-input__action" type="button" data-auth-password-toggle aria-label="Show password"><?= app_icon('eye') ?></button>
                            </span>
                        </label>

                        <div class="auth-password-strength" data-auth-password-strength aria-live="polite"></div>

                        <button class="btn btn-primary auth-submit" type="submit">
                            <?= app_icon('check') ?>
                            <span>Update password</span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="auth-expired">
                        <?= app_icon('alert') ?>
                        <strong>This reset link is no longer available.</strong>
                        <span>Request a fresh password reset link to continue.</span>
                        <a class="btn btn-primary" href="<?= e(url('/forgot-password')) ?>">Request new link</a>
                    </div>
                <?php endif; ?>

                <div class="auth-panel__footer">
                    <a href="<?= e(url('/login')) ?>">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>
</section>
