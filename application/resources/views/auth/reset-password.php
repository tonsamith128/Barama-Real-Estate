<section class="auth-shell">
    <div class="container app-container">
        <div class="auth-grid">
            <div class="auth-intro">
                <span class="eyebrow">Set a new password</span>
                <h1>Create a new secure password for your account.</h1>
                <p>Password reset tokens are stored as one-way hashes and expire automatically.</p>
            </div>

            <div class="auth-card">
                <h2>Reset password</h2>
                <p class="auth-card__copy">Choose a new password with at least 12 characters.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <?php if ($valid): ?>
                    <form action="<?= e(url('/reset-password/' . rawurlencode($token))) ?>" method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <div class="form-field">
                            <label for="reset-password">New password</label>
                            <input
                                class="form-control"
                                id="reset-password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                minlength="12"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="reset-confirmation">Confirm new password</label>
                            <input
                                class="form-control"
                                id="reset-confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                minlength="12"
                                required
                            >
                        </div>

                        <div class="auth-actions">
                            <a class="auth-link" href="<?= e(url('/login')) ?>">Back to sign in</a>
                            <button class="btn btn-primary" type="submit">Update password</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="auth-meta">
                        Request a new reset link from the
                        <a class="auth-link" href="<?= e(url('/forgot-password')) ?>">forgot password page</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
