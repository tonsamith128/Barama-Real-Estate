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
                    <span class="auth-panel__icon"><?= app_icon('key') ?></span>
                    <div><h2>Forgot password</h2><p>Enter the email used for your account.</p></div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert-barama alert-barama--success" role="status"><?= e($success) ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/forgot-password')) ?>" method="post" class="auth-form">
                    <?= csrf_field() ?>

                    <label class="auth-field" for="forgot-email">
                        <span>Email address</span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('mail') ?></span>
                            <input class="form-control" id="forgot-email" name="email" type="email" value="<?= e($email ?? '') ?>" autocomplete="email" maxlength="191" placeholder="you@example.com" required autofocus>
                        </span>
                    </label>

                    <button class="btn btn-primary auth-submit" type="submit">
                        <?= app_icon('mail') ?>
                        <span>Send reset link</span>
                    </button>
                </form>

                <div class="auth-panel__footer auth-panel__footer--split">
                    <a class="auth-panel__home" href="<?= e(url('/')) ?>"><?= e(t('Back to home')) ?></a>
                    <span class="auth-panel__secondary">
                        <a href="<?= e(url('/login')) ?>"><?= e(t('Back to sign in')) ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
