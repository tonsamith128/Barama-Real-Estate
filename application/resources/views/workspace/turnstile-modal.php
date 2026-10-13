<?php
/** Scoped include inside the existing Security table view. Dashboard only. */
use Barama\Security\TurnstileGuard;

$brTurnstileReady = true;
try {
    $brTurnstile = TurnstileGuard::status();
} catch (\Throwable) {
    $brTurnstileReady = false;
    $brTurnstile = [
        'site' => '', 'forms' => [], 'enabled' => false,
        'verified' => 0, 'configured' => false,
    ];
}
$brTurnstileLabels = [
    'login' => 'Login',
    'register' => 'Registration',
    'forgot_password' => 'Forgot password',
    'contact' => 'Contact messages',
    'inquiry' => 'Property inquiries',
];
$brTurnstileState = !$brTurnstileReady ? 'Unavailable'
    : ($brTurnstile['enabled'] ? 'Enabled' : ($brTurnstile['configured'] ? 'Disabled' : 'Not configured'));
$brTurnstileFeedback = $_SESSION['barama_turnstile_feedback'] ?? null;
unset($_SESSION['barama_turnstile_feedback']);
if (!is_array($brTurnstileFeedback)) {
    $brTurnstileFeedback = null;
}
$brTurnstileStartOpen = ((string) ($_GET['turnstile'] ?? '') === '1');
$brTurnstilePost = url('/dashboard/settings/security/turnstile');
$brTsValidVerification = $brTurnstile['verified'] > 0
    && $brTurnstile['verified'] >= time() - 3600
    && $brTurnstile['verified'] <= time() + 60;
$brTsAppearance = $brTurnstile['appearance'] ?? 'always';

?>
<!-- BARAMA_TURNSTILE_TABLE_MODAL_V111 -->
<div class="br-ts-overlay" id="br-ts-overlay" hidden data-initial-open="<?= $brTurnstileStartOpen ? '1' : '0' ?>">
  <section class="br-ts-dialog" role="dialog" aria-modal="true" aria-labelledby="br-ts-heading" tabindex="-1">
    <header class="br-ts-dialog-head">
      <div class="br-ts-dialog-identity">
        <span class="br-ts-dialog-symbol" aria-hidden="true"><?= app_icon('shield') ?></span>
        <div>
          <span class="br-ts-kicker">SECURITY · BOT PROTECTION</span>
          <h2 id="br-ts-heading">Cloudflare Turnstile</h2>
          <p>Configure and manage bot protection without leaving Security settings.</p>
        </div>
      </div>
      <button type="button" class="br-ts-close" data-br-ts-close aria-label="Close Turnstile settings">&times;</button>
    </header>

    <div class="br-ts-body">
      <div class="br-ts-summary">
        <span>Protection <strong class="<?= $brTurnstile['enabled'] ? 'br-ts-green' : 'br-ts-neutral' ?>"><?= e($brTurnstileState) ?></strong></span>
        <span>Credentials <strong><?= $brTurnstile['configured'] ? 'Saved' : 'Not configured' ?></strong></span>
        <span>Verification <strong><?= $brTsValidVerification ? 'Verified' : ($brTurnstile['verified'] > 0 ? 'Expired' : 'Pending') ?></strong></span>
      </div>

      <?php if ($brTurnstileFeedback !== null): ?>
        <p class="br-ts-message <?= !empty($brTurnstileFeedback['error']) ? 'br-ts-message-error' : '' ?>" role="status">
          <?= e((string) ($brTurnstileFeedback['message'] ?? '')) ?>
        </p>
      <?php endif; ?>
      <?php if (!$brTurnstileReady): ?>
        <p class="br-ts-message br-ts-message-error" role="alert">Encrypted settings could not be loaded. Changes are unavailable until the configuration is repaired.</p>
      <?php endif; ?>

      <section class="br-ts-section" aria-labelledby="br-ts-credentials-title">
        <div class="br-ts-section-heading"><span class="br-ts-step">01</span>
          <div><h3 id="br-ts-credentials-title">Credentials & form coverage</h3><p>Use your real Cloudflare keys for this website. The secret is never displayed after saving.</p></div>
        </div>
        <form method="post" action="<?= e($brTurnstilePost) ?>" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="operation" value="save">
          <div class="br-ts-fields">
            <label>Public site key<input name="site_key" type="text" value="<?= e((string) $brTurnstile['site']) ?>" maxlength="128" required spellcheck="false" autocomplete="off"></label>
            <label>Secret key<input name="secret_key" type="password" maxlength="256" autocomplete="new-password" placeholder="<?= $brTurnstile['configured'] ? 'Leave empty to retain saved secret' : 'Paste secret key' ?>"></label>
          </div>
          <div class="br-ts-forms-heading">Widget appearance</div>
          <div class="br-ts-appearance-choices" role="group" aria-label="Cloudflare Turnstile widget appearance">
            <label class="br-ts-appearance-option"><input type="radio" name="appearance" value="always" <?= $brTsAppearance === 'always' ? 'checked' : '' ?>><span><strong>Always visible</strong><small><code>always</code> · Show the visible widget from page load.</small></span></label>
            <label class="br-ts-appearance-option"><input type="radio" name="appearance" value="execute" <?= $brTsAppearance === 'execute' ? 'checked' : '' ?>><span><strong>Visible when challenge begins</strong><small><code>execute</code> · Show the widget after challenge execution begins.</small></span></label>
            <label class="br-ts-appearance-option"><input type="radio" name="appearance" value="interaction-only" <?= $brTsAppearance === 'interaction-only' ? 'checked' : '' ?>><span><strong>Interaction only</strong><small><code>interaction-only</code> · Show only when visitor interaction is required.</small></span></label>
          </div>
          <p class="br-ts-help br-ts-widget-modes">Cloudflare widget types are <strong>Managed (recommended)</strong>, <strong>Non-Interactive</strong> and <strong>Invisible</strong>. Choose the widget type in your <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener noreferrer">Cloudflare Dashboard</a>; Barama's appearance control cannot change the type attached to your site key. Appearance settings do not affect Invisible widgets.</p>
          <div class="br-ts-forms-heading">Forms to protect</div>
          <div class="br-ts-forms">
            <?php foreach ($brTurnstileLabels as $name => $label): ?>
              <label class="br-ts-choice"><input type="checkbox" name="forms[]" value="<?= e($name) ?>" <?= in_array($name, $brTurnstile['forms'], true) ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="br-ts-toolbar"><button class="br-ts-btn br-ts-primary" type="submit" <?= !$brTurnstileReady ? 'disabled' : '' ?>>Save credentials & selection</button><span class="br-ts-help">Saving disables protection until re-verification.</span></div>
        </form>
      </section>

      <section class="br-ts-section" aria-labelledby="br-ts-verify-title">
        <div class="br-ts-section-heading"><span class="br-ts-step">02</span>
          <div><h3 id="br-ts-verify-title">Verify with Cloudflare</h3><p>Complete one live challenge before enabling the selected forms.</p></div>
        </div>
        <?php if ($brTurnstileReady && $brTurnstile['configured']): ?>
          <?php if ($brTsValidVerification): ?>
            <p class="br-ts-verified-notice" role="status"><strong>Cloudflare verification successful.</strong> Your credentials are verified. You can enable selected forms below; another challenge is not required.</p>
            <button type="button" class="br-ts-btn br-ts-secondary" data-br-ts-reverify>Verify credentials again</button>
          <?php endif; ?>
          <form method="post" action="<?= e($brTurnstilePost) ?>" data-br-ts-verify-form <?= $brTsValidVerification ? 'hidden' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="verify">
            <input type="hidden" name="cf-turnstile-response" value="" data-br-ts-token>
            <div class="br-ts-widget" data-br-ts-widget data-sitekey="<?= e((string) $brTurnstile['site']) ?>"></div>
            <div class="br-ts-toolbar"><button type="submit" class="br-ts-btn br-ts-primary" data-br-ts-verify-button disabled>Verify real credentials</button><span class="br-ts-help" data-br-ts-widget-hint aria-live="polite">Complete the Cloudflare challenge to activate this button.</span></div>
          </form>
        <?php else: ?>
          <p class="br-ts-muted">Save your valid site key and secret key to show the verification challenge.</p>
        <?php endif; ?>
      </section>

      <section class="br-ts-section" aria-labelledby="br-ts-activation-title">
        <div class="br-ts-section-heading"><span class="br-ts-step">03</span>
          <div><h3 id="br-ts-activation-title">Protection status</h3><p>Activate after your credentials are verified. Start with Contact messages and test real submissions before enabling other forms.</p></div>
        </div>
        <div class="br-ts-toolbar">
          <form method="post" action="<?= e($brTurnstilePost) ?>"><?= csrf_field() ?><input type="hidden" name="operation" value="enable"><button type="submit" class="br-ts-btn br-ts-primary" <?= (!$brTurnstileReady || !$brTurnstile['configured'] || !$brTsValidVerification || !$brTurnstile['forms']) ? 'disabled' : '' ?>>Enable selected forms</button></form>
          <form method="post" action="<?= e($brTurnstilePost) ?>"><?= csrf_field() ?><input type="hidden" name="operation" value="disable"><button type="submit" class="br-ts-btn br-ts-secondary" <?= (!$brTurnstileReady || !$brTurnstile['enabled']) ? 'disabled' : '' ?>>Disable protection</button></form>
        </div>
      </section>
    </div>
    <footer class="br-ts-dialog-footer"><span>Cloudflare Siteverify · Encrypted local credentials</span><button type="button" class="br-ts-btn br-ts-secondary" data-br-ts-close>Close</button></footer>
  </section>
</div>
