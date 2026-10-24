<?php
/** BARAMA_DEMO_IMPORT_VIEW_V100: verified packages only; never scrape remote HTML. */
$demoMode = in_array((string)($demoMode ?? ''), ['manual','auto'], true) ? $demoMode : 'manual';
$demoReview = is_array($demoReview ?? null) ? $demoReview : null;
$demoFlash = is_array($demoFlash ?? null) ? $demoFlash : null;
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-system-tool-v100.css') . '?v=100') ?>">
<link rel="stylesheet" href="<?= e(asset('css/barama-demo-importer-v100.css') . '?v=100') ?>">
<link rel="stylesheet" href="<?= e(asset('css/barama-demo-premium-v103.css') . '?v=103') ?>">
<section id="barama-system-tool" class="btool bdemo" data-passcode-enabled="<?= !empty($demoPasscodeEnabled) ? '1' : '0' ?>" aria-labelledby="bdemo-title">
 <div class="btool-breadcrumb">Administration <span>/</span> System <span>/</span> Demo Importer</div>
 <header class="btool-header">
   <div><span class="btool-eyebrow">Live demo delivery</span><h1 id="bdemo-title">Demo Importer</h1>
   <p>Bring the official Barama Real Estate Live Demo into your licensed installation using a verified publisher package.</p></div>
   <span class="btool-status"><span aria-hidden="true">●</span> <?= !empty($demoDownloadEnabled) ? 'Verified download available' : 'Integration preview' ?></span>
 </header>
 <?php if ($demoFlash !== null): ?>
 <div role="status" class="btool-flash btool-flash--<?= ($demoFlash['tone'] ?? '') === 'success' ? 'success' : 'error' ?>"><?= e((string)($demoFlash['message'] ?? '')) ?></div>
 <?php endif; ?>
 <?php if (empty($demoReleaseAccepted)): ?>
 <div class="btool-warning" role="status">The official Barama package is published. Signed package discovery, verification and private staging are enabled. Installing database records, media and frontend content remains disabled until a fresh staging import and rollback test passes.</div>
 <?php endif; ?>
 <div class="bdemo-summary">
   <div class="bdemo-summary-icon" aria-hidden="true">✦</div>
   <div class="bdemo-summary-info"><strong>Official Barama Live Demo</strong>
     <p>Design, navigation, footer, pages, marketplace records and images are delivered together in a signed snapshot.</p>
     <div class="bdemo-source"><span>Demo reference:</span> <a href="<?= e($demoSource) ?>" target="_blank" rel="noopener"><?= e($demoSource) ?></a></div>
   </div>
   <span class="btool-chip"><?= !empty($demoCommercialConfigured) ? 'License activated' : 'License not activated' ?></span>
 </div>
 <div class="btool-shell">
   <nav class="btool-tabs" aria-label="Demo import method">
      <a class="btool-tab <?= $demoMode === 'manual' ? 'is-active' : '' ?>"
         <?= $demoMode === 'manual' ? 'aria-current="page"' : '' ?>
         href="<?= e(url('/dashboard/system/demo-importer')) ?>?mode=manual">
        <span class="btool-tab-icon">⇩</span> Manual Import
      </a>
      <a class="btool-tab <?= $demoMode === 'auto' ? 'is-active' : '' ?>"
         <?= $demoMode === 'auto' ? 'aria-current="page"' : '' ?>
         href="<?= e(url('/dashboard/system/demo-importer')) ?>?mode=auto">
        <span class="btool-tab-icon">⟳</span> Auto Import
      </a>
   </nav>
   <div class="btool-content">
    <?php if ($demoMode === 'manual'): ?>
     <div class="btool-content-head">
       <div><span class="btool-eyebrow">Offline package</span><h2>Manually import Live Demo</h2>
       <p>Upload a publisher-signed ZIP downloaded from the official Demo Content service. The ZIP is verified before any installation.</p></div>
       <span class="btool-chip">Signed ZIP</span>
     </div>
     <div class="btool-two">
       <section class="btool-card">
        <h3>Upload official demo package</h3>
        <p>Packages from unknown sources, modified files, unsigned ZIPs and arbitrary database dumps are rejected.</p>
        <form method="post" action="<?= e(url('/dashboard/system/demo-importer/manual')) ?>" enctype="multipart/form-data">
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <label for="bdemo-package">Live Demo package</label>
          <input id="bdemo-package" type="file" name="package" accept=".zip,application/zip" required>
          <p class="btool-help">Maximum 64 MB. Uploading only verifies and stages the package; it does not import content.</p>
          <div class="btool-actions"><button type="submit" class="btool-btn btool-btn-primary">Verify package</button></div>
        </form>
       </section>
       <aside class="btool-guide">
        <div class="btool-step">01 <strong>Download from publisher</strong><span>Use the official licensed package provided in Demo Content</span></div>
        <div class="btool-step">02 <strong>Upload and verify</strong><span>Check Ed25519 signature, exact file digests, format, compatibility and media paths</span></div>
        <div class="btool-step">03 <strong>Review package</strong><span>Inspect the signed package while the database importer remains disabled until staging acceptance</span></div>
       </aside>
     </div>
    <?php else: ?>
      <div class="btool-content-head">
        <div><span class="btool-eyebrow">License-connected</span><h2>Automatically import Live Demo</h2>
        <p>Check your commercial license with the publisher, securely retrieve the official signed ZIP, then review the exact contents before installation.</p></div>
        <span class="btool-chip">Authenticated publisher</span>
      </div>
      <section class="btool-card bdemo-api-check" aria-label="Live publisher API connection">
        <div class="bdemo-review-title">
          <div>
            <span class="btool-eyebrow">Live publisher API</span>
            <h3>Publisher connection</h3>
          </div>
          <span class="btool-chip">Read-only diagnostics</span>
        </div>
        <p>Connect to the real TonSamith.com Demo Content API. The full public-key fingerprint is independently pinned; this panel checks the live key and currently published licensed packages.</p>
        <div class="btool-actions">
          <a class="btool-btn btool-btn-secondary" href="<?= e(url('/dashboard/system/demo-importer/connection')) ?>">Test HTTPS &amp; signing key</a>
          <a class="btool-btn btool-btn-secondary" href="<?= e(url('/dashboard/system/demo-importer/catalog')) ?>">Check licensed demo catalog</a>
        </div>
        <?php if (is_array($demoApiIdentity ?? null)): ?>
        <div class="bdemo-api-result">
          <strong>Publisher signing key verified</strong>
          <p>Key ID: <code><?= e((string)($demoApiIdentity['key_id'] ?? '')) ?></code></p>
          <p>Fingerprint: <code><?= e((string)($demoApiIdentity['fingerprint'] ?? '')) ?></code></p>
          <p>The public key must match the fingerprint independently verified on the publisher host. Any unapproved rotation is rejected.</p>
        </div>
        <?php endif; ?>
        <?php if (is_array($demoApiCatalog ?? null)): ?>
        <div class="bdemo-api-result">
          <strong>Published packages: <?= count((array)($demoApiCatalog['packages'] ?? [])) ?></strong>
          <?php if (empty($demoApiCatalog['packages'])): ?>
            <p>No published Barama package is available to this particular activated license.</p>
          <?php else: ?>
            <?php foreach ((array)$demoApiCatalog['packages'] as $demoItem): ?>
            <div class="bdemo-api-package">
              <span><?= e((string)($demoItem['name'] ?? 'Live Demo')) ?></span>
              <span>v<?= e((string)($demoItem['version'] ?? '?')) ?></span>
              <small><?= e((string)($demoItem['id'] ?? '')) ?></small>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </section>
      <section class="btool-card">
       <h3>Get latest official demo package</h3>
       <p>Auto Import contacts TonSamith.com directly over HTTPS with your installation-bound activation. Publisher entitlement and package signature must both pass.</p>
       <div class="bdemo-requirements">
         <span><b>01</b> Active bound license</span>
         <span><b>02</b> Official published demo</span>
         <span><b>03</b> Signed content ZIP</span>
         <span><b>04</b> Private staging; no installation</span>
       </div>
       <form method="post" action="<?= e(url('/dashboard/system/demo-importer/auto')) ?>" data-btool-protected>
         <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
         <div class="btool-actions"><button type="submit" class="btool-btn btool-btn-primary" <?= empty($demoDownloadEnabled) ? 'disabled' : '' ?>>Download &amp; verify official ZIP</button></div>
       </form>
       <p class="btool-help">The publisher issues a short-lived download ticket. The ZIP is verified and privately staged; it cannot modify this live website.</p>
      </section>
    <?php endif; ?>

    <?php if ($demoReview !== null): ?>
     <section class="btool-card bdemo-review" aria-label="Verified package details">
      <div class="bdemo-review-title">
       <div><span class="btool-eyebrow">Verified package</span><h3><?= e((string)$demoReview['package_id']) ?></h3></div>
       <span class="btool-chip">Ed25519 signature verified</span>
      </div>
      <div class="bdemo-stats">
       <div><strong><?= (int)$demoReview['records'] ?></strong><span>Content records</span></div>
       <div><strong><?= (int)$demoReview['assets'] ?></strong><span>Image files</span></div>
       <div><strong><?= (int)$demoReview['settings'] ?></strong><span>Design/config groups</span></div>
       <div><strong><?= e((string)$demoReview['expires_at']) ?></strong><span>Signature status</span></div>
      </div>
      <p class="btool-help">Packaged sections: <?= e(implode(' · ', array_slice(array_map('strval',(array)$demoReview['components']),0,15))) ?>.</p>
      <div class="btool-warning">
       Demo import is for a fresh content installation. Existing marketplace records are never deleted or silently replaced. Installation remains disabled until staging acceptance. Your live website is not changed by package staging.
      </div>
      <form method="post" action="<?= e(url('/dashboard/system/demo-importer/install')) ?>" data-btool-protected>
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <label for="bdemo-confirmation">Type <strong>IMPORT LIVE DEMO</strong> to confirm</label>
       <input id="bdemo-confirmation" type="text" name="confirmation" autocomplete="off" placeholder="IMPORT LIVE DEMO" required>
       <p class="btool-help">Installation will become available only after the native importer passes an isolated staging backup and rollback test.</p>
       <div class="btool-actions"><button class="btool-btn btool-btn-primary" type="submit" <?= empty($demoReleaseAccepted) ? 'disabled' : '' ?>>Install verified Live Demo</button></div>
      </form>
     </section>
    <?php else: ?>
     <div class="bdemo-pending">
       <span class="bdemo-pending-icon" aria-hidden="true">◇</span>
       <div><strong>No package staged</strong><p>Upload a signed ZIP or use Auto Import to request the official published package.</p></div>
     </div>
    <?php endif; ?>
   </div>
 </div>
 <footer class="btool-footnote">Publisher-signed packages only · Fresh-site protection · Database backup before writes · Manual and Auto use the same verified installer.</footer>
</section>
<dialog class="btool-dialog" id="btool-passcode-dialog" aria-labelledby="btool-passcode-title">
 <div class="btool-dialog-inner">
  <div class="btool-dialog-symbol" aria-hidden="true">◇</div>
  <h2 id="btool-passcode-title">Confirm this change</h2>
  <p>Enter your six-digit Change Confirmation passcode when enabled. The passcode is never saved.</p>
  <label for="btool-passcode">Passcode</label>
  <input id="btool-passcode" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off">
  <p id="btool-passcode-feedback" class="btool-dialog-feedback" role="alert"></p>
  <div class="btool-actions"><button class="btool-btn btool-btn-secondary" type="button" id="btool-passcode-cancel">Cancel</button>
  <button class="btool-btn btool-btn-primary" type="button" id="btool-passcode-submit">Confirm action</button></div>
 </div>
</dialog>
<script defer src="<?= e(asset('js/barama-system-tool-v100.js') . '?v=100') ?>"></script>
<script defer src="<?= e(asset('js/barama-demo-premium-v103.js') . '?v=103') ?>"></script>
