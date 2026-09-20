<section class="auth-shell">
    <div class="container app-container">
        <div class="auth-grid">
            <div class="auth-intro">
                <span class="eyebrow">Secure account access</span>
                <h1>Welcome back to Barama Real Estate.</h1>
                <p>
                    Sign in to manage saved properties, inquiries and account activity.
                    Developer and administrator workspaces use the same secure identity system with role-based access.
                </p>
            </div>

            <div class="auth-card">
                <h2>Sign in</h2>
                <p class="auth-card__copy">Use the email address and password associated with your account.</p>

                <?php if (!empty($success)): ?>
                    <div class="alert-barama alert-barama--success" role="status"><?= e($success) ?></div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/login')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-field">
                        <label for="login-email">Email address</label>
                        <input
                            class="form-control"
                            id="login-email"
                            name="email"
                            type="email"
                            value="<?= e($email ?? '') ?>"
                            autocomplete="email"
                            maxlength="191"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="login-password">Password</label>
                        <input
                            class="form-control"
                            id="login-password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <div class="auth-actions">
                        <a class="auth-link" href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
                        <button class="btn btn-primary" type="submit">Sign in</button>
                    </div>
                </form>

                <div class="auth-meta">
                    New to Barama Real Estate?
                    <a class="auth-link" href="<?= e(url('/register')) ?>">Create an account</a>.
                </div>
            </div>
        </div>
    </div>
</section>
