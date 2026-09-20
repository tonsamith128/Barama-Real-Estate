<section class="auth-shell">
    <div class="container app-container">
        <div class="auth-grid">
            <div class="auth-intro">
                <span class="eyebrow">Password recovery</span>
                <h1>Recover access without exposing account information.</h1>
                <p>
                    Enter your email address. For privacy, Barama Real Estate uses the same confirmation response
                    whether or not an account exists.
                </p>
            </div>

            <div class="auth-card">
                <h2>Forgot password</h2>
                <p class="auth-card__copy">A time-limited reset link will be sent when an active account matches the email.</p>

                <?php if (!empty($success)): ?>
                    <div class="alert-barama alert-barama--success" role="status"><?= e($success) ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/forgot-password')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-field">
                        <label for="forgot-email">Email address</label>
                        <input
                            class="form-control"
                            id="forgot-email"
                            name="email"
                            type="email"
                            value="<?= e($email ?? '') ?>"
                            autocomplete="email"
                            maxlength="191"
                            required
                        >
                    </div>

                    <div class="auth-actions">
                        <a class="auth-link" href="<?= e(url('/login')) ?>">Back to sign in</a>
                        <button class="btn btn-primary" type="submit">Send reset link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
