<section class="auth-premium">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow">Email verification</span>
                <h1>Secure the account before company access.</h1>
                <p>Barama verifies the registered email before a customer can create a company workspace or accept a company invitation.</p>
                <div class="auth-story__features">
                    <article><span><?= app_icon('mail') ?></span><div><strong>Address ownership</strong><small>Verification protects invitations and company access from unverified registrations.</small></div></article>
                    <article><span><?= app_icon('shield') ?></span><div><strong>Security boundary</strong><small>Your customer account remains separate from developer and administrator privileges.</small></div></article>
                </div>
            </aside>

            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('mail') ?></span>
                    <div><h2>Verify email</h2><p><?= e($email) ?></p></div>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert-barama alert-barama--error" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <?php if (!empty($sent)): ?>
                    <div class="alert-barama alert-barama--success" role="status">A fresh verification link has been queued for delivery.</div>
                <?php endif; ?>

                <?php if ($verified): ?>
                    <div class="auth-expired">
                        <?= app_icon('check') ?>
                        <strong>Your email address is verified.</strong>
                        <span>You can continue to your account and request company access when needed.</span>
                        <a class="btn btn-primary" href="<?= e(url('/account')) ?>">Continue to account</a>
                    </div>
                <?php elseif (is_string($token) && $token !== '' && $valid): ?>
                    <p class="auth-panel__copy">Confirm that <strong><?= e($email) ?></strong> belongs to this signed-in account.</p>
                    <form action="<?= e(url('/verify-email/' . rawurlencode($token))) ?>" method="post" class="auth-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary auth-submit" type="submit"><?= app_icon('check') ?><span>Verify email address</span></button>
                    </form>
                <?php else: ?>
                    <p class="auth-panel__copy">Open the verification link sent to your email. If the previous link expired, request a new one below.</p>
                    <form action="<?= e(url('/verify-email/resend')) ?>" method="post" class="auth-form">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary auth-submit" type="submit"><?= app_icon('mail') ?><span>Send verification email</span></button>
                    </form>
                <?php endif; ?>

                <div class="auth-panel__footer">
                    <form action="<?= e(url('/logout')) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
