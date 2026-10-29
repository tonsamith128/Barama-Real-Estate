<?= barama_css_once('account') ?>
<section class="barama-verify-v117">
  <div class="barama-verify-v117__card">
    <span class="barama-verify-v117__icon" aria-hidden="true"><?= app_icon($verified ? 'check' : 'mail') ?></span>
    <span class="eyebrow">Account security</span>
    <h1>Verify your email</h1>
    <p class="barama-verify-v117__intro">Confirm your registered email address before applying for Developer or Company access.</p>
    <div class="barama-verify-v117__email"><span>Registered email</span><strong><?= e((string) $email) ?></strong></div>
    <?php if (!$verified && (string) ($emailChangeNotice ?? '') === 'queued'): ?>
      <div class="alert-barama alert-barama--success" role="status">A confirmation link for your new email address has been queued. Your current email stays unchanged until you confirm the new address.</div>
    <?php endif; ?>
    <?php foreach ((array) ($errors ?? []) as $error): ?>
      <div class="alert-barama alert-barama--error" role="alert"><?= e((string) $error) ?></div>
    <?php endforeach; ?>
    <?php $verificationNotice = (string) ($verificationNotice ?? ''); ?>
    <?php if (!$verified && $verificationNotice === 'queued'): ?>
      <div class="alert-barama alert-barama--success" role="status">A verification link has been queued for sending. Check your email, including the spam folder.</div>
    <?php elseif (!$verified && $verificationNotice === 'wait'): ?>
      <div class="alert-barama" role="status">A link was requested recently. Please wait before requesting another one.</div>
    <?php elseif (!$verified && $verificationNotice === 'error'): ?>
      <div class="alert-barama alert-barama--error" role="alert">Unable to queue a new link at the moment. Please try again later.</div>
    <?php endif; ?>
    <?php if ($verified): ?>
      <p class="barama-verify-v117__message">Your email address is verified. Your customer account is ready.</p>
      <a class="btn btn-primary barama-verify-v117__action" href="<?= e(url('/account')) ?>">Continue to account</a>
    <?php elseif (is_string($token) && $token !== '' && $valid): ?>
      <p class="barama-verify-v117__message">This link is ready to confirm the registered email address.</p>
      <form action="<?= e(url('/verify-email/' . rawurlencode($token))) ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary barama-verify-v117__action" type="submit"><?= app_icon('check') ?> Verify email address</button>
      </form>
    <?php else: ?>
      <p class="barama-verify-v117__message">Use the link sent to your inbox. If it has expired, you can request another one.</p>
      <form action="<?= e(url('/verify-email/resend')) ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-primary barama-verify-v117__action" type="submit"><?= app_icon('mail') ?> Send verification email</button>
      </form>
    <?php endif; ?>
    <?php if (!$verified): ?>
      <details class="barama-verify-v117__change" <?= (array) ($errors ?? []) !== [] ? 'open' : '' ?>>
        <summary>Wrong email address? Change it <?= app_icon('chevron-down') ?></summary>
        <p class="barama-verify-v117__change-copy">Enter the correct address and your current password. A confirmation link will be sent to your new email. Your account's current address will not change until you confirm it.</p>
        <form action="<?= e(url('/verify-email/change')) ?>" method="post" class="barama-verify-v117__change-form">
          <?= csrf_field() ?>
          <label for="verification-new-email">New email address</label>
          <input id="verification-new-email" class="form-control" type="email" name="new_email" required maxlength="191" autocomplete="email" placeholder="name@example.com">
          <label for="verification-current-password">Current account password</label>
          <input id="verification-current-password" class="form-control" type="password" name="current_password" required autocomplete="current-password" placeholder="Enter your password">
          <button type="submit" class="btn btn-outline-primary barama-verify-v117__change-submit">Send confirmation to new email</button>
        </form>
      </details>
    <?php endif; ?>
    <div class="barama-verify-v117__footer">
      <form action="<?= e(url('/logout')) ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary btn-sm" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</section>
