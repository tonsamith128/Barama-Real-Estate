<section class="auth-shell">
    <div class="container app-container">
        <div class="auth-grid">
            <div class="auth-intro">
                <span class="eyebrow">Create your account</span>
                <h1>Start your property journey with one secure account.</h1>
                <p>
                    Customer registration creates a standard user account. Developer and administrator permissions
                    are assigned separately so privileged workspaces are never exposed through public registration.
                </p>
            </div>

            <div class="auth-card">
                <h2>Create account</h2>
                <p class="auth-card__copy">Use a valid email address and a strong password with at least 12 characters.</p>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form action="<?= e(url('/register')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-field">
                        <label for="register-name">Full name</label>
                        <input
                            class="form-control"
                            id="register-name"
                            name="name"
                            type="text"
                            value="<?= e($name ?? '') ?>"
                            autocomplete="name"
                            maxlength="120"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="register-email">Email address</label>
                        <input
                            class="form-control"
                            id="register-email"
                            name="email"
                            type="email"
                            value="<?= e($email ?? '') ?>"
                            autocomplete="email"
                            maxlength="191"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="register-password">Password</label>
                        <input
                            class="form-control"
                            id="register-password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >
                    </div>

                    <div class="form-field">
                        <label for="register-confirmation">Confirm password</label>
                        <input
                            class="form-control"
                            id="register-confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            required
                        >
                    </div>

                    <div class="auth-actions">
                        <a class="auth-link" href="<?= e(url('/login')) ?>">Already have an account?</a>
                        <button class="btn btn-primary" type="submit">Create account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
