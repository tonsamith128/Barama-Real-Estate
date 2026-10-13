<?php
/**
 * Barama Real Estate customer license center — visual UI only.
 * Every displayed value comes from the existing authenticated controller.
 * No remote verification, token reads or database writes occur in this view.
 */
$escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$status = is_array($licenseStatus ?? null) ? $licenseStatus : [];
$context = is_array($licenseContext ?? null) ? $licenseContext : [];
$configured = ($status['configured'] ?? false) === true;
$hasError = !empty($status['error']);
$state = $hasError ? 'error' : (in_array($status['state'] ?? '', ['verified', 'pending', 'error'], true) ? $status['state'] : 'pending');
if (!$configured && !$hasError) { $state = 'pending'; }
$stateTitle = match ($state) {
    'verified' => 'License verified',
    'error' => 'Attention required',
    default => $configured ? 'Verification pending' : 'Activation required',
};
if ($state === 'error' && is_string($status['status_title'] ?? null)) {
    $stateTitle = $status['status_title'];
}
$stateHint = match ($state) {
    'verified' => 'Recently verified with Publisher',
    'error' => $hasError ? 'Private license state unavailable' : 'Check license status before proceeding',
    default => $configured ? 'Activation stored · verification needed' : 'Purchase code or license key to get started',
};
$installerMode = ($status['source'] ?? null) === 'installer_v1';
$activationSource = (string) ($status['activation_entrypoint'] ?? 'One-click Installer / Auto Updater');
$installerRecognized = $installerMode && ($status['installer_recognized'] ?? false) === true;
if (is_string($status['status_title'] ?? null) && $status['status_title'] !== '') {
    $stateTitle = $status['status_title'];
}
if (is_string($status['status_hint'] ?? null) && $status['status_hint'] !== '') {
    $stateHint = $status['status_hint'];
}
$publisherConfigured = ($context['publisher_configured'] ?? false) === true;
$product = (string) ($context['product_name'] ?? 'Barama Real Estate');
$domain = (string) ($status['domain'] ?? $context['domain'] ?? '—');
$maskedId = (string) ($context['installation_id_masked'] ?? 'Protected');
$version = (string) ($context['version'] ?? '—');
$publisherHost = $publisherConfigured ? (string) ($context['publisher_host'] ?? 'Publisher configured') : 'Not configured';
$lastVerified = (string) ($status['last_verified_at'] ?? 'Never');
$expires = (string) ($status['expires_at'] ?? 'Not supplied');
$actionUrl = (string) ($licenseActionUrl ?? '/dashboard/license/actions');
$csrf = (string) ($csrfToken ?? '');
$publisherState = $publisherConfigured ? 'neutral' : 'pending'; // Configured is not a successful API handshake.
$rows = [
    ['id' => 'status', 'item' => 'License status', 'state' => $state, 'status' => $stateTitle, 'value' => $stateHint, 'updated' => $lastVerified, 'details' => 'Green means Publisher verification is within the configured verification interval. Blue means activation or re-verification is needed. Red means the current license record needs attention.'],
    ['id' => 'entrypoint', 'item' => 'Activation source', 'state' => 'neutral', 'status' => $installerMode ? 'Unified' : 'Not recorded', 'value' => $installerMode ? $activationSource : '—', 'updated' => '—', 'details' => 'A valid license activated through the one-click installer or License Center is stored in the same private installation record that Auto Updater verifies. No second activation is required.'],
    ['id' => 'product', 'item' => 'Registered product', 'state' => 'neutral', 'status' => 'Installed', 'value' => $product, 'updated' => '—', 'details' => 'The one-click installer, Auto Updater and License Center use the same Barama Real Estate product identity. A license for another product cannot activate it.'],
    ['id' => 'version', 'item' => 'Application version', 'state' => 'neutral', 'status' => 'Installed', 'value' => $version, 'updated' => '—', 'details' => 'Version is read from the installed application. License activation does not automatically update or upgrade the application.'],
    ['id' => 'domain', 'item' => 'Authorized domain', 'state' => 'neutral', 'status' => 'Identity', 'value' => $domain, 'updated' => '—', 'details' => 'Publisher activation binds the license to the canonical HTTPS domain and existing installation identity. A different domain requires Publisher authorization.'],
    ['id' => 'identity', 'item' => 'Installation ID', 'state' => 'neutral', 'status' => 'Protected', 'value' => $maskedId, 'updated' => '—', 'details' => 'The installed system maintains one private installation identity. Only a masked fragment is shown on this page.'],
    ['id' => 'publisher', 'item' => $installerMode ? 'Installer licensing endpoint' : 'Publisher endpoint', 'state' => $publisherState, 'status' => $publisherConfigured ? 'Configured' : 'Not configured', 'value' => $publisherHost, 'updated' => '—', 'details' => $installerMode ? 'All three licensing screens use the same stored token and trusted Ton Samith License Manager endpoint. Server configuration alone is not proof of connectivity.' : 'The configured HTTPS Publisher origin is not proof that its activation, verification or deactivation API is reachable. Configure the existing trusted runtime setting on the server.'],
    ['id' => 'verification', 'item' => 'Last verification', 'state' => $state === 'verified' ? 'verified' : ($state === 'error' ? 'error' : 'pending'), 'status' => $state === 'verified' ? 'Current' : ($state === 'error' ? 'Review' : 'Pending'), 'value' => $lastVerified, 'updated' => $lastVerified, 'details' => 'Timestamp comes from the saved installer verification or the newer Publisher verification. A historical timestamp is not a live connectivity claim.'],
    ['id' => 'expiry', 'item' => 'License expiration', 'state' => $state === 'error' && !$hasError ? 'error' : 'neutral', 'status' => $expires === 'Not supplied' ? 'Not supplied' : ($state === 'error' ? 'Review' : 'Recorded'), 'value' => $expires, 'updated' => '—', 'details' => 'An expiration value is displayed only when supplied by the Publisher. No renewal date is invented locally.'],
    ['id' => 'storage', 'item' => 'Activation storage', 'state' => $hasError ? 'error' : 'neutral', 'status' => $hasError ? 'Unavailable' : 'Private', 'value' => $hasError ? 'Recovery required' : ($installerMode ? 'Shared private activation receipt' : ($configured ? 'Encrypted activation state' : 'Ready for activation')), 'updated' => '—', 'details' => $installerMode ? 'The installer, Auto Updater and License Center reuse the original private installation receipt. The purchase code is not stored or displayed.' : 'Publisher V2 stores an encrypted activation record in the private installation state. No token is displayed in the browser.'],
    ['id' => 'channel', 'item' => 'Verification channel', 'state' => 'neutral', 'status' => 'HTTPS', 'value' => 'Authenticated installation request', 'updated' => '—', 'details' => 'Verification and deactivation are sent over HTTPS using the installed application domain and installation identifier. No request is made merely by viewing this page.'],
];
?>
<link rel="stylesheet" href="<?= $escape(asset('css/license-settings.css')) ?>?v=1440">
<section class="bl-settings" data-bl-settings data-bl-license-state="<?= $escape($state) ?>" aria-labelledby="bl-page-title">
  <div class="bl-breadcrumb" aria-label="Breadcrumb"><span>System</span><span class="bl-breadcrumb-sep" aria-hidden="true">/</span><span>License &amp; Activation</span></div>

  <header class="bl-hero">
    <div class="bl-state-emblem" aria-hidden="true">
      <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="60" cy="60" r="54" class="bl-emblem-halo"/>
        <circle cx="60" cy="60" r="43" class="bl-emblem-disc"/>
        <path d="M60 28 85 38v20c0 18-10 30-25 37-15-7-25-19-25-37V38l25-10Z" class="bl-emblem-shield" stroke-width="3.5" stroke-linejoin="round"/>
        <g class="bl-symbol-verified" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m48 60 8 8 17-18"/></g>
        <g class="bl-symbol-pending" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="57" cy="60" r="7"/><path d="M64 60h13m-5 0v6m-6-6v5"/></g>
        <g class="bl-symbol-error" stroke-width="4" stroke-linecap="round"><path d="m51 51 18 18m0-18L51 69"/></g>
      </svg>
    </div>
    <p class="bl-eyebrow">BARAMA LICENSE CENTER</p>
    <h1 id="bl-page-title">License &amp; Activation</h1>
    <p class="bl-lead">One place to activate, verify, and manage your product license.</p>
    <div class="bl-state-pill" data-bl-status data-state="<?= $escape($state) ?>" role="status">
      <span class="bl-pill-status-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path class="bl-pill-check" d="m5 12 4.5 4.5L19 7"/><path class="bl-pill-pending" d="M12 7v5l3.5 2"/><path class="bl-pill-error" d="m7 7 10 10M17 7 7 17"/></svg>
      </span>
      <span data-bl-status-label><?= $escape($stateTitle) ?></span>
    </div>
    <?php if ($state === 'verified' && $lastVerified !== '' && $lastVerified !== 'Never'): ?>
      <p class="bl-status-evidence" data-bl-status-evidence>Publisher verification confirmed <span aria-hidden="true">·</span> <strong><?= $escape($lastVerified) ?></strong></p>
    <?php endif; ?>
  </header>

  <section class="bl-license-action" aria-labelledby="bl-action-title">
    <div class="bl-action-head">
      <span class="bl-action-heading-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M9 13a5 5 0 1 1 5 5h-3l-2 2v-3H7" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="16" cy="7" r="1" fill="currentColor"/></svg></span>
      <div><p class="bl-section-caption">YOUR LICENSE</p><h2 id="bl-action-title">License action</h2></div>
    </div>
    <?php if (($status['source'] ?? null) === 'conflict'): ?>
      <div class="bl-alert bl-alert-error" role="alert"><strong>Conflicting activation records detected.</strong><span>Contact support to reconcile the older independent V2 record. No license will be overwritten.</span></div>
    <?php elseif ($hasError): ?>
      <div class="bl-alert bl-alert-error" role="alert"><strong>Private activation state is unavailable.</strong><span>Restore the private backup before changing activation.</span></div>
    <?php elseif ($installerMode): ?>
      <div class="bl-active-binding"><span><?= $installerRecognized ? 'Recognized from ' . $escape($activationSource) . ' ·' : 'Existing license needs review ·' ?></span><strong><?= $escape($domain) ?></strong></div>
      <?php if ($installerRecognized): ?>
        <div class="bl-action-buttons bl-action-stack" aria-label="License actions">
          <form action="<?= $escape($actionUrl) ?>?action=verify" method="post" data-bl-form>
            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
            <button type="submit" class="bl-button bl-button-primary" <?= $publisherConfigured ? '' : 'disabled title="Original licensing endpoint is not configured"' ?>>Verify again <span aria-hidden="true">↗</span></button>
          </form>
          <form action="<?= $escape($actionUrl) ?>?action=deactivate" method="post" data-bl-form data-bl-confirm="Deactivate this installation with the Publisher? All three licensing screens will require activation again.">
            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
            <button type="submit" class="bl-button bl-button-secondary bl-button-deactivate" <?= $publisherConfigured ? '' : 'disabled' ?>>Deactivate this installation</button>
          </form>
        </div>
        <p class="bl-action-hint">Your license is shared by the installer and Auto Updater. No purchase code needed again.</p>
        <?php if (!$publisherConfigured): ?><p class="bl-config-note" role="note">The saved activation is recognized, but the original license-server URL must be configured before verification.</p><?php endif; ?>
      <?php else: ?>
        <div class="bl-alert bl-alert-error" role="alert"><strong>Previously installed license needs attention.</strong><span>Its saved product, domain, installation or status no longer matches. Contact support; do not enter a new key here.</span></div>
      <?php endif; ?>
    <?php elseif (!$configured): ?>
      <form action="<?= $escape($actionUrl) ?>?action=activate" method="post" data-bl-form class="bl-activation-form">
        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
        <label for="bl-license-key">Purchase code or license key</label>
        <div class="bl-field-wrap">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="8.5" cy="15" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="m12 12 8-8 1.5 1.5-2 2 1.5 1.5-2.5 2.5-1.5-1.5-3 3" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
          <input id="bl-license-key" type="password" name="license_key" minlength="8" maxlength="120" pattern="[A-Za-z0-9-]{8,120}" title="Enter a CodeCanyon purchase code or Ton Samith License Manager key" required autocomplete="off" spellcheck="false" placeholder="CodeCanyon purchase code or license key">
          <button class="bl-reveal" type="button" data-bl-reveal aria-controls="bl-license-key" aria-label="Show license key">Show</button>
        </div>
        <button type="submit" class="bl-button bl-button-primary" <?= $publisherConfigured ? '' : 'disabled title="Configure the trusted Publisher origin before activating"' ?>>Activate license <span aria-hidden="true">→</span></button>
        <p class="bl-action-hint">Your key is transmitted only during activation and is never saved in browser storage.</p>
      </form>
      <?php if (!$publisherConfigured): ?><p class="bl-config-note" role="note">Publisher is not configured yet. The trusted Ton Samith License Manager HTTPS endpoint must be configured.</p><?php endif; ?>
    <?php else: ?>
      <div class="bl-active-binding"><span>Bound to</span><strong><?= $escape($domain) ?></strong></div>
      <div class="bl-action-buttons bl-action-stack" aria-label="License actions">
        <form action="<?= $escape($actionUrl) ?>?action=verify" method="post" data-bl-form><input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button type="submit" class="bl-button bl-button-primary" <?= $publisherConfigured ? '' : 'disabled title="Publisher origin is not configured"' ?>>Verify license <span aria-hidden="true">↗</span></button></form>
        <form action="<?= $escape($actionUrl) ?>?action=deactivate" method="post" data-bl-form data-bl-confirm="Deactivate this installation? You will need the license key to reactivate."><input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button type="submit" class="bl-button bl-button-secondary bl-button-deactivate" <?= $publisherConfigured ? '' : 'disabled title="Publisher origin is not configured"' ?>>Deactivate this installation</button></form>
      </div>
      <p class="bl-action-hint">Deactivation only clears local state after the Publisher confirms success.</p>
    <?php endif; ?>
    <div class="bl-feedback" data-bl-feedback role="status" aria-live="polite" hidden></div>
  </section>

  <div class="bl-stats" aria-label="License at a glance">
    <div class="bl-stat"><span>LICENSE STATUS</span><strong data-bl-table-status><?= $escape($stateTitle) ?></strong></div>
    <div class="bl-stat"><span>PRODUCT</span><strong><?= $escape($product) ?></strong></div>
    <div class="bl-stat"><span>PUBLISHER</span><strong><?= $publisherConfigured ? ($installerMode ? 'Installer endpoint set' : 'Endpoint configured') : 'Not connected' ?></strong></div>
    <div class="bl-stat"><span>LAST VERIFIED</span><strong><?= $escape($lastVerified) ?></strong></div>
  </div>

  <section class="bl-details-section" aria-labelledby="bl-details-title">
    <div class="bl-table-heading"><div><p class="bl-section-caption">SYSTEM OVERVIEW</p><h2 id="bl-details-title">License details</h2></div><p>Select <strong>Details</strong> to explore each item.</p></div>
    <div class="bl-table-scroll">
      <table class="bl-details-table">
        <thead><tr><th scope="col">Item</th><th scope="col">Status</th><th scope="col">Value</th><th scope="col">Updated</th><th scope="col" class="bl-column-action">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr class="bl-data-row">
            <th scope="row"><span class="bl-item-dot" data-state="<?= $escape($row['state']) ?>" aria-hidden="true"></span><?= $escape($row['item']) ?></th>
            <td><span class="bl-table-state" data-state="<?= $escape($row['state']) ?>"<?= $row['id'] === 'status' ? ' data-bl-table-state' : '' ?>><?= $escape($row['status']) ?></span></td>
            <td class="bl-value-cell"<?= $row['id'] === 'identity' ? ' title="Installation identifier is masked"' : '' ?>><?= $escape($row['value']) ?></td>
            <td class="bl-date-cell"><?= $escape($row['updated']) ?></td>
            <td class="bl-column-action"><button type="button" class="bl-details-toggle" data-bl-details-toggle aria-expanded="false" aria-controls="bl-details-<?= $escape($row['id']) ?>">Details <span aria-hidden="true">⌄</span></button></td>
          </tr>
          <tr id="bl-details-<?= $escape($row['id']) ?>" class="bl-expanded-row" hidden><td colspan="5"><div class="bl-expanded-content"><span class="bl-expanded-label"><?= $escape($row['item']) ?></span><p><?= $escape($row['details']) ?></p></div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <footer class="bl-page-footer"><a href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">Product support <span aria-hidden="true">↗</span></a><a href="<?= $escape(url('/dashboard/system/auto-updater')) ?>">Auto Updater ↗</a><span>Protected installation · Barama License Center</span></footer>
</section>
<script defer src="<?= $escape(asset('js/license-settings.js')) ?>?v=1440"></script>
