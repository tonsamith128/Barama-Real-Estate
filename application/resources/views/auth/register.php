<section class="auth-premium auth-premium--standalone">
    <div class="container app-container auth-standalone__container">
                <a
            class="auth-standalone__brand"
            href="<?= e(url('/')) ?>"
            aria-label="<?= e(t('Back to home')) ?>"
        >
            <img
                src="<?= e(asset('images/branding/barama-logo-header.png')) ?>"
                alt="<?= e(t('Barama')) ?>"
                width="2172"
                height="724"
            >
        </a>

        <div class="auth-premium__grid auth-premium__grid--standalone">

            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('plus') ?></span>
                    <div><h2><?= e(t('Create account')) ?></h2><p><?= e(t('Set up your secure customer profile.')) ?></p></div>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/register')) ?>" method="post" class="auth-form">
                    <?= csrf_field() ?>

                    <label class="auth-field" for="register-name">
                        <span><?= e(t('Full name')) ?></span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('user') ?></span>
                            <input class="form-control" id="register-name" name="name" type="text" value="<?= e($name ?? '') ?>" autocomplete="name" maxlength="120" required autofocus>
                        </span>
                    </label>

                    <label class="auth-field" for="register-email">
                        <span><?= e(t('Email address')) ?></span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('mail') ?></span>
                            <input class="form-control" id="register-email" name="email" type="email" value="<?= e($email ?? '') ?>" autocomplete="email" maxlength="191" required>
                        </span>
                    </label>

                    <label class="auth-field" for="register-password">
                        <span><?= e(t('Password')) ?></span>
                        <span class="auth-input auth-input--action">
                            <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                            <input class="form-control" id="register-password" name="password" type="password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-primary>
                            <button class="auth-input__generate" type="button" data-auth-generate-password><?= e(t('Generate')) ?></button>
                        </span>
                        <small><?= e(t('Use at least 12 characters with uppercase, lowercase, number, and symbol. The generator creates a strong password and confirms it automatically.')) ?></small>
                    </label>

                    <label class="auth-field" for="register-confirmation">
                        <span><?= e(t('Confirm password')) ?></span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                            <input class="form-control" id="register-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required data-auth-password data-auth-password-confirmation>
                            <button class="auth-input__action" type="button" data-auth-password-toggle aria-label="<?= e(t('Show password')) ?>"><?= app_icon('eye') ?></button>
                        </span>
                    </label>

                    <div class="auth-password-strength" data-auth-password-strength aria-live="polite"></div>

                    <button class="btn btn-primary auth-submit" type="submit">
                        <?= app_icon('plus') ?>
                        <span><?= e(t('Create account')) ?></span>
                    </button>
                </form>

                <div class="auth-panel__footer auth-panel__footer--split">
                    <a class="auth-panel__home" href="<?= e(url('/')) ?>"><?= e(t('Back to home')) ?></a>
                    <span class="auth-panel__secondary">
                        <span><?= e(t('Already have an account?')) ?></span>
                        <a href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
