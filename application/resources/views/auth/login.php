<section class="auth-premium">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow">Secure account access</span>
                <h1>Welcome back.</h1>
                <p>Use one account for property activity, company workspaces, and administration based on your assigned permissions.</p>

                <div class="auth-story__features">
                    <article>
                        <span><?= app_icon('heart') ?></span>
                        <div><strong>Save & compare</strong><small>Keep a focused shortlist of marketplace properties.</small></div>
                    </article>
                    <article>
                        <span><?= app_icon('calendar') ?></span>
                        <div><strong>Track activity</strong><small>Review inquiries, viewings, alerts, and notifications.</small></div>
                    </article>
                    <article>
                        <span><?= app_icon('building') ?></span>
                        <div><strong>Role-aware access</strong><small>Company and admin tools appear only when permission is granted.</small></div>
                    </article>
                </div>
            </aside>

            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('user') ?></span>
                    <div><h2>Sign in</h2><p>Continue to your Barama workspace.</p></div>
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
                        <span>Email address</span>
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
                        <span>Password</span>
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
                            <button class="auth-input__action" type="button" data-auth-password-toggle aria-label="Show password">
                                <?= app_icon('eye') ?>
                            </button>
                        </span>
                    </label>

                    <div class="auth-form__meta">
                        <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
                    </div>

                    <button class="btn btn-primary auth-submit" type="submit">
                        <?= app_icon('key') ?>
                        <span>Sign in securely</span>
                    </button>
                </form>

                <div class="auth-panel__footer">
                    <span>New to Barama?</span>
                    <a href="<?= e(url('/register')) ?>">Create an account</a>
                </div>
            </div>
        </div>
    </div>
</section>
