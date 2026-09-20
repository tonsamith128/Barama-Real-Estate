<section class="auth-premium">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow"><?= e(t('Create your account')) ?></span>
                <h1><?= e(t('One account for your property journey.')) ?></h1>
                <p><?= e(t('Public registration creates a secure customer account. Verify your email after registration; company/vendor and administrator access remain permission-controlled.')) ?></p>

                <div class="auth-story__features">
                    <article><span><?= app_icon('home') ?></span><div><strong><?= e(t('Discover')) ?></strong><small><?= e(t('Save, compare, and revisit published properties.')) ?></small></div></article>
                    <article><span><?= app_icon('message') ?></span><div><strong><?= e(t('Connect')) ?></strong><small><?= e(t('Keep inquiries and viewing requests organized.')) ?></small></div></article>
                    <article><span><?= app_icon('bell') ?></span><div><strong><?= e(t('Stay updated')) ?></strong><small><?= e(t('Create saved searches and control notifications.')) ?></small></div></article>
                </div>
            </aside>

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

                <div class="auth-panel__footer">
                    <span><?= e(t('Already have an account?')) ?></span>
                    <a href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
