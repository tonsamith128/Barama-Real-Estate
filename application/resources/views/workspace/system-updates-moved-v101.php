<?php
/**
 * Deprecated System Updates admin page; point users to the canonical Auto Updater.
 * Never initiate installation, entitlement checks, or unguarded legacy upgrades here.
 */
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-system-center-v103.css') . '?v=110') ?>">
<section class="barama-sys-v103" aria-labelledby="barama-system-title">
  <nav class="bsys-breadcrumb" aria-label="Breadcrumb">Administration / System / Updates</nav>
  <div class="bsys-header"><div>
    <h1 id="barama-system-title">Updates are now managed in Auto Updater</h1>
    <p class="bsys-description">Use the unified Auto Updater to check eligibility, review publisher-signed releases, and manage supported installations securely.</p>
  </div></div>
  <section class="bsys-panel" aria-label="Auto Updater entry">
    <div class="bsys-section-body">
      <p>The previous System Updates page has been retired to avoid duplicate update interfaces. The separate protected recovery mechanism remains available to authorized administrators when needed.</p>
      <div class="bsys-actions"><a class="bsys-button bsys-button--primary" href="<?= e(url('/dashboard/system/auto-updater')) ?>">Open Auto Updater <span aria-hidden="true">&rarr;</span></a></div>
      <p class="bsys-note">License activation and verification are managed separately in License &amp; Activation.</p>
    </div>
  </section>
</section>
