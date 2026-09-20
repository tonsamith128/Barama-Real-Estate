<section class="auth-premium account-confirmation">
    <div class="container app-container">
        <div class="auth-premium__grid">
            <aside class="auth-story">
                <span class="eyebrow">Account security</span>
                <h1>Confirm your new email address.</h1>
                <p>The address changes only after this signed-in confirmation succeeds. Existing sessions and account ownership remain attached to the same user account.</p>
            </aside>

            <div class="auth-panel">
                <div class="auth-panel__head">
                    <span class="auth-panel__icon"><?= app_icon('mail') ?></span>
                    <div><h2>Email confirmation</h2><p>Secure account update</p></div>
                </div>

                <?php if ($newEmail !== null): ?>
                    <div class="account-confirm-email">
                        <span>New email address</span>
                        <strong><?= e($newEmail) ?></strong>
                    </div>
                    <form action="<?= e(url('/account/email-change/' . rawurlencode($token))) ?>" method="post" data-account-action>
                        <?= csrf_field() ?>
                        <button class="btn btn-primary auth-submit" type="submit"><?= app_icon('check') ?><span>Confirm email change</span></button>
                    </form>
                <?php else: ?>
                    <div class="auth-expired">
                        <?= app_icon('alert') ?>
                        <strong>This confirmation link is unavailable.</strong>
                        <span>It may have expired, already been used, or belong to another signed-in account.</span>
                        <a class="btn btn-primary" href="<?= e(url('/account/security')) ?>">Return to security</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
