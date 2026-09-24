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
                    <span class="auth-panel__icon"><?= app_icon('user') ?></span>
                    <div><h2><?= e(t('Sign in')) ?></h2><p><?= e(t('Continue to your Barama workspace.')) ?></p></div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert-barama alert-barama--success" role="status"><?= e($success) ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/login')) ?>" method="post" class="auth-form">
                    <?= csrf_field() ?>

                    <label class="auth-field" for="login-email">
                        <span><?= e(t('Email address')) ?></span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('mail') ?></span>
                            <input
                                class="form-control"
                                id="login-email"
                                name="email"
                                type="email"
                                value="<?= e($email ?? '') ?>"
                                autocomplete="email"
                                maxlength="191"
                                placeholder="you@example.com"
                                required
                                autofocus
                            >
                        </span>
                    </label>

                    <label class="auth-field" for="login-password">
                        <span><?= e(t('Password')) ?></span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('lock') ?></span>
                            <input
                                class="form-control"
                                id="login-password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                data-auth-password
                            >
                            <button class="auth-input__action" type="button" data-auth-password-toggle aria-label="<?= e(t('Show password')) ?>">
                                <?= app_icon('eye') ?>
                            </button>
                        </span>
                    </label>

                    <div class="auth-form__meta">
                        <a href="<?= e(url('/forgot-password')) ?>"><?= e(t('Forgot password?')) ?></a>
                    </div>

                    <button class="btn btn-primary auth-submit" type="submit">
                        <?= app_icon('key') ?>
                        <span><?= e(t('Sign in securely')) ?></span>
                    </button>
                </form>

                <div class="auth-panel__footer auth-panel__footer--split">
                    <a class="auth-panel__home" href="<?= e(url('/')) ?>"><?= e(t('Back to home')) ?></a>
                    <span class="auth-panel__secondary">
                        <span><?= e(t('New to Barama?')) ?></span>
                        <a href="<?= e(url('/register')) ?>"><?= e(t('Create an account')) ?></a>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
