<section class="auth-premium auth-premium--standalone auth-premium--a43r1">
    <!-- BARAMA_RESET_PASSWORD_A43R1 -->
    <?= barama_css_once('account') ?>
    <div class="container app-container auth-standalone__container">
        <div class="auth-premium__grid auth-premium__grid--standalone">
<div class="auth-panel">
                <div class="a43r1-brand">
                    <a href="<?= e(url('/')) ?>" aria-label="<?= e(t('Back to home')) ?>">
                        <img src="<?= e(asset('images/branding/barama-logo-header.png')) ?>" alt="<?= e(t('Barama Real Estate')) ?>" width="2172" height="724">
                    </a>
                </div>
                <div class="auth-panel__head">
                    
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
                            <span class="a43r1-sr-only">New password</span>
                            <span class="auth-input auth-input--action">
                                <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                                <input class="form-control" id="reset-password" name="password" type="password" placeholder="New password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-primary>
                                <button class="auth-input__generate" type="button" data-auth-generate-password>Generate</button>
                            </span>
                        </label>

                        <label class="auth-field" for="reset-confirmation">
                            <span class="a43r1-sr-only">Confirm new password</span>
                            <span class="auth-input">
                                <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                                <input class="form-control" id="reset-confirmation" name="password_confirmation" type="password" placeholder="Confirm new password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-confirmation>
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
