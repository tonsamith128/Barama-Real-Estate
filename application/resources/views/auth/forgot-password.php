<section class="auth-premium">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow">Password recovery</span>
                <h1>Recover your account safely.</h1>
                <p>Barama keeps recovery private: the same confirmation is shown whether or not an email belongs to an active account.</p>

                <div class="auth-story__features">
                    <article><span><?= app_icon('shield') ?></span><div><strong>Private response</strong><small>Account existence is never disclosed.</small></div></article>
                    <article><span><?= app_icon('clock') ?></span><div><strong>Time-limited link</strong><small>Reset links expire automatically.</small></div></article>
                    <article><span><?= app_icon('key') ?></span><div><strong>Single-use reset</strong><small>A successful reset invalidates the token.</small></div></article>
                </div>
            </aside>

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

                <div class="auth-panel__footer">
                    <a href="<?= e(url('/login')) ?>">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>
</section>
