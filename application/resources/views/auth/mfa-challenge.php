<section class="auth-premium auth-premium--standalone">
    <div class="container app-container auth-standalone__container">
        <a class="auth-standalone__brand" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Back to home')) ?>">
            <img src="<?= e(asset('images/branding/barama-logo-header.png')) ?>" alt="<?= e(t('Barama')) ?>" width="2172" height="724">
        </a>

        <div class="auth-premium__grid auth-premium__grid--standalone">
            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('shield') ?></span>
                    <div>
                        <h2>Security verification</h2>
                        <p>Confirm this sign-in with your authenticator app or a recovery code.</p>
                    </div>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/mfa-challenge')) ?>" method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <label class="auth-field" for="mfa-code">
                        <span>Authenticator or recovery code</span>
                        <span class="auth-input">
                            <span class="auth-input__icon"><?= app_icon('key') ?></span>
                            <input
                                class="form-control"
                                id="mfa-code"
                                name="code"
                                type="text"
                                inputmode="text"
                                autocomplete="one-time-code"
                                maxlength="24"
                                placeholder="123456 or XXXX-XXXX-XXXX-XXXX"
                                required
                                autofocus
                            >
                        </span>
                    </label>

                    <button class="btn btn-primary auth-submit" type="submit">
                        <?= app_icon('check') ?>
                        <span>Verify and continue</span>
                    </button>
                </form>

                <div class="auth-panel__footer auth-panel__footer--split">
                    <a class="auth-panel__home" href="<?= e(url('/login')) ?>">Back to sign in</a>
                    <span class="auth-panel__secondary">Recovery codes work once and are removed after use.</span>
                </div>
            </div>
        </div>
    </div>
</section>
