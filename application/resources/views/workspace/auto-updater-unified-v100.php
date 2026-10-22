<?php
/** Barama unified Auto Updater UI v1.0.0. Display-only; existing privileged POST actions remain authoritative. */
$uReady = is_array($systemFeedReadiness ?? null) ? $systemFeedReadiness : [];
$uFeed = is_array($systemFeedResult ?? null) ? $systemFeedResult : [];
$uInspect = is_array($systemReleaseInspection ?? null) ? $systemReleaseInspection : [];
$uRelease = is_array($systemReleaseReadiness ?? null) ? $systemReleaseReadiness : [];
$uLicense = is_array($systemPublisherCheck ?? null) ? $systemPublisherCheck : [];
$uBinding = is_array($systemLicenseBinding ?? null) ? $systemLicenseBinding : [];
$uSystem = is_array($systemAutoUpdater ?? null) ? $systemAutoUpdater : [];
$uVersion = (string)($systemVersion ?? (defined('APP_VERSION') ? APP_VERSION : 'Unknown'));
$uFeedStatus = (string)($uFeed['status'] ?? 'not_checked');
$uEligible = $uFeedStatus === 'available' || $uFeedStatus === 'eligible';
$uEligible = $uEligible && ($uFeed['entitlement'] ?? '') === 'Eligible';
$uSigned = $uFeedStatus === 'available' && $uEligible && ($uFeed['feed'] ?? '') === 'Signed release verified';
$uFresh = isset($uFeed['at']) && is_int($uFeed['at']) && $uFeed['at'] <= time()+30 && $uFeed['at'] > time()-600;
$uSigned = $uSigned && $uFresh;
$uVersionAvailable = $uSigned && preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', (string)($uFeed['version'] ?? '')) === 1;
$uCanCheck = !empty($uReady['can_check']);
$uLicenseActive = in_array((string)($uLicense['state'] ?? ''), ['Active license verified', 'Installed license recognized'], true);
$uDomain = (string)($uBinding['recorded_domain'] ?? '');
$uCurrentDomain = (string)($uBinding['current_domain'] ?? 'Not recorded');
$uDomainOk = $uDomain !== '' && $uDomain !== 'Not recorded' && $uCurrentDomain !== 'Not recorded'
    && hash_equals($uCurrentDomain, $uDomain);
$uDomainTitle = $uDomainOk ? 'Installed website: ' . $uCurrentDomain
    : 'Configured website: ' . $uCurrentDomain . ' | Saved license domain: ' . $uDomain;
$uStaged = !empty($uRelease['staged_release']);
$uStagedVerified = ($uInspect['status'] ?? '') === 'Signed package verified (not installed)';
$uStorage = !empty($uSystem['storage_writable']);
$uStaging = class_exists(\Barama\Services\Updates\PublisherTrustPaths::class) && \Barama\Services\Updates\PublisherTrustPaths::isProtectedStaging();
$uPortable = false;
try { $uPortable = class_exists(\Barama\Services\Updates\PortableCustomerUpdateV1::class) && \Barama\Services\Updates\PortableCustomerUpdateV1::ready(); } catch (\Throwable) { /* Unknown => disabled */ }
$uReadyInstall = !$uStaging && $uPortable && $uVersionAvailable;
// A staging action is visible only when the separately reviewed one-click
// controller, queue, and root-worker status contract have all been supplied.
// This view never grants update authorization or creates update jobs.
$uStagingQueueSupported = $uStaging
    && class_exists(\Barama\Services\Updates\OneClickUpdateQueueV1::class)
    && isset($systemOneClickWorkerReady, $systemOneClickPending, $systemOneClickStatus)
    && is_bool($systemOneClickWorkerReady)
    && is_bool($systemOneClickPending)
    && is_array($systemOneClickStatus);
$uStagingAdministrator = false;
if ($uStagingQueueSupported) {
    try {
        $uAuth = new \Barama\Services\AuthService();
        $uStagingAdministrator = $uAuth->hasRole('administrator')
            || $uAuth->hasRole('super-administrator');
    } catch (\Throwable) { /* Fail closed. */ }
}
$uCanRequestStagingUpdate = $uStagingQueueSupported && $uStagingAdministrator
    && $uVersionAvailable && $systemOneClickWorkerReady && !$systemOneClickPending
    && is_string($uFeed['archive_sha256'] ?? null)
    && preg_match('/^[a-f0-9]{64}$/D', $uFeed['archive_sha256']) === 1;
$uFlash = is_string($systemFeedFlash ?? null) ? $systemFeedFlash : '';
$uInstallFlash = $_SESSION['_barama_portable_update_flash_v102'] ?? null;
if (isset($_SESSION['_barama_portable_update_flash_v102'])) unset($_SESSION['_barama_portable_update_flash_v102']);
$uSteps = [
 ['Entitlement', $uEligible && $uFresh ? 'Confirmed' : ($uFeedStatus === 'ineligible' ? 'Not eligible' : 'Not checked'), $uEligible && $uFresh ? 'done' : ($uFeedStatus === 'ineligible' ? 'warn' : 'pending')],
 ['Signed feed', $uCanCheck ? 'Configured' : 'Unavailable', $uCanCheck ? 'done' : 'warn'],
 ['Release signature', $uVersionAvailable ? 'Verified manifest' : 'Not verified', $uVersionAvailable ? 'done' : 'pending'],
 ['Package staging', $uStaged ? 'Package detected' : 'Not staged', $uStaged ? 'done' : 'pending'],
 ['Package inspection', $uStagedVerified ? 'Verified ZIP' : 'Not inspected', $uStagedVerified ? 'done' : 'pending'],
 ['Backup & installation', 'Not executed', 'pending'],
 ['Final verification', 'Not executed', 'pending'],
];
$uCompleted = ($uEligible && $uFresh ? 1 : 0) + ($uCanCheck ? 1 : 0) + ($uVersionAvailable ? 1 : 0) + ($uStaged ? 1 : 0) + ($uStagedVerified ? 1 : 0);
$uPhase = $uVersionAvailable ? 'Signed release found' : ($uFeedStatus === 'eligible' ? 'No newer release' : ($uFeedStatus === 'ineligible' ? 'Update not authorized' : ($uCanCheck ? 'Ready to check' : 'Setup required')));
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-auto-updater-unified-v100.css') . '?v=100') ?>">
<link rel="stylesheet" href="<?= e(asset('css/barama-auto-updater-theme-v101.css') . '?v=101') ?>">
<main class="bau" id="barama-unified-auto-updater" aria-labelledby="bau-title">
 <nav class="bau-breadcrumb" aria-label="Breadcrumb">Administration / System / Auto Updater</nav>
 <header class="bau-heading"><div><h1 id="bau-title">Auto Updater</h1><p>Discover and verify publisher-signed Barama releases in one place.</p></div>
  <form method="post" action="<?= e(url('/dashboard/system/auto-updater/check-updates')) ?>" data-bau-check-form>
   <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
   <button class="bau-button bau-primary" type="submit" <?= $uCanCheck ? '' : 'disabled' ?> data-bau-check>↻ &nbsp; Check for updates</button>
  </form></header>
 <?php if ($uFlash !== ''): ?><div class="bau-flash" role="status"><?= e($uFlash) ?></div><?php endif; ?>
 <?php if (is_string($uInstallFlash) && $uInstallFlash !== ''): ?><div class="bau-flash" role="status"><?= e($uInstallFlash) ?></div><?php endif; ?>
 <section class="bau-versions" aria-label="Software versions">
  <div><span class="bau-kicker">Current installed version</span><strong>v<?= e($uVersion) ?></strong></div>
  <span class="bau-versus" aria-hidden="true">→</span>
  <div><span class="bau-kicker">Latest verified signed release</span><strong><?= $uVersionAvailable ? 'v'.e((string)$uFeed['version']) : (($uFeedStatus === 'eligible' && $uFresh) ? 'No newer release' : 'Not confirmed') ?></strong><small><?= $uVersionAvailable ? 'Fresh publisher signature verified' : (($uFeedStatus === 'eligible' && $uFresh) ? 'Publisher confirmed no newer signed release' : 'Select Check for updates to request signed release details') ?></small></div>
  <span class="bau-pill <?= $uVersionAvailable ? 'bau-pill--good' : '' ?>"><?= $uVersionAvailable ? 'Update discovered' : (($uFeedStatus === 'eligible' && $uFresh) ? 'No update required' : 'Discovery pending') ?></span>
 </section>
 <div class="bau-readiness" aria-label="Update prerequisites">
  <span><b class="<?= $uLicenseActive ? 'bau-icon-good' : '' ?>">✓</b> License <?= $uLicenseActive ? 'recognized' : 'not verified' ?></span>
  <span title="<?= e($uDomainTitle) ?>"><b class="<?= $uDomainOk ? 'bau-icon-good' : '' ?>">⌁</b> Domain <?= $uDomainOk ? 'matched' : 'not confirmed' ?></span>
  <span><b class="<?= $uCanCheck ? 'bau-icon-good' : '' ?>">◉</b> Signed feed <?= $uCanCheck ? 'ready' : 'not ready' ?></span>
  <span><b class="<?= $uStorage ? 'bau-icon-good' : '' ?>">▣</b> Storage <?= $uStorage ? 'writable' : 'not ready' ?></span>
  <a href="<?= e(url('/dashboard/license')) ?>">Manage license ↗</a>
 </div>
 <div class="bau-columns">
  <section class="bau-panel bau-mainpanel" aria-labelledby="bau-workflow-title">
   <div class="bau-panel-heading"><div><h2 id="bau-workflow-title">Update lifecycle</h2><p>Progress is based on confirmed server results. No installation starts during discovery.</p></div><span class="bau-pill">Step <?= (int)$uCompleted ?> of 7 confirmed</span></div>
   <div class="bau-progress-layout"><div class="bau-ring-wrap"><div class="bau-ring" data-bau-ring role="status" aria-live="polite" aria-label="<?= e($uPhase) ?>"><div class="bau-ring-inner"><span class="bau-ring-symbol">↻</span><strong data-bau-ring-title><?= e($uPhase) ?></strong><small data-bau-ring-desc><?= $uVersionAvailable ? 'Release metadata signed by the trusted publisher' : 'Awaiting verified update discovery' ?></small></div></div><p class="bau-ring-note" data-bau-live-message>No download, backup, or installation has been started on this page.</p></div>
    <ol class="bau-steps"><?php foreach ($uSteps as $uIndex=>$uStep): ?><li class="bau-step bau-step--<?= e($uStep[2]) ?>"><span class="bau-step-num"><?= $uStep[2] === 'done' ? '✓' : (string)($uIndex+1) ?></span><div><strong><?= e($uStep[0]) ?></strong><small><?= e($uStep[1]) ?></small></div></li><?php endforeach; ?></ol>
   </div>
  </section>
  <aside class="bau-panel bau-aside" aria-labelledby="bau-release-title"><div class="bau-panel-heading"><div><h2 id="bau-release-title">Release &amp; installation</h2><p>Only verified facts can unlock the next action.</p></div></div>
   <div class="bau-mini"><span>Installed</span><strong>v<?= e($uVersion) ?></strong></div>
   <div class="bau-mini"><span>Signed candidate</span><strong><?= $uVersionAvailable ? 'v'.e((string)$uFeed['version']) : 'None verified' ?></strong></div>
   <div class="bau-mini"><span>Update entitlement</span><strong><?= $uEligible && $uFresh ? 'Eligible' : ($uFeedStatus === 'ineligible' ? (($uFeed['entitlement'] ?? '') === 'Not configured' ? 'Not configured' : 'Not entitled') : 'Not confirmed') ?></strong></div>
   <div class="bau-mini"><span>Staged release ZIP</span><strong><?= $uStagedVerified ? 'Verified' : ($uStaged ? 'Present · unverified' : 'Not staged') ?></strong></div>
   <div class="bau-mini"><span>Backup and rollback</span><strong>Not verified in this session</strong></div>
   <div class="bau-aside-actions">
    <?php if ($uStaging): ?>
      <div class="bau-safe-note">Staging installations require a separate, protected, root-owned worker. Discovery alone never starts installation.</div>
      <?php if ($uCanRequestStagingUpdate): ?>
        <form method="post" action="<?= e(url('/dashboard/system/auto-updater/update-now')) ?>" data-bau-install-form>
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <button type="submit" class="bau-button bau-install">Update Now</button>
        </form>
        <small>Administrator request only. The independent trusted worker must reverify authorization, ZIP integrity, backup, and recovery before any update is installed.</small>
      <?php else: ?>
        <button type="button" class="bau-button bau-install" disabled>Update Now unavailable</button>
        <small><?php if (!$uStagingQueueSupported): ?>The protected staging update queue is not installed in this application build.
          <?php elseif (!$uStagingAdministrator): ?>Only an administrator can request an update.
          <?php elseif (!empty($systemOneClickPending)): ?>A previous update request is pending; review its status before resubmitting.
          <?php elseif (empty($systemOneClickWorkerReady)): ?>The trusted staging worker is offline; Update Now remains disabled.
          <?php else: ?>Use Check for updates to verify a fresh, eligible, newer signed release.
          <?php endif; ?></small>
      <?php endif; ?>
      <?php if ($uStagingQueueSupported): ?>
      <?php if (!empty($systemOneClickFlash)): ?><p class="bau-flash" role="status"><?= e((string)$systemOneClickFlash) ?></p><?php endif; ?>
      <div class="bau-safe-note" role="status" aria-live="polite"
           data-oneclick-status
           data-oneclick-endpoint="<?= e(url('/dashboard/system/auto-updater/status')) ?>"
           data-oneclick-initial-worker-ready="<?= $systemOneClickWorkerReady ? '1' : '0' ?>"
           data-oneclick-initial-pending="<?= $systemOneClickPending ? '1' : '0' ?>"
           data-oneclick-initial-state="<?= e((string)($systemOneClickStatus['state'] ?? 'not_started')) ?>">
        Update job: <strong data-oneclick-state><?= e(str_replace('_', ' ', (string)($systemOneClickStatus['state'] ?? 'not_started'))) ?></strong>
        <span data-oneclick-worker><?= $systemOneClickWorkerReady ? ' · Trusted worker online' : ' · Trusted worker offline' ?></span>
        <p data-oneclick-stage-message>Awaiting trusted job status.</p>
      </div>
      <?php endif; ?>
    <?php elseif ($uReadyInstall): ?>
      <form method="post" action="<?= e(url('/dashboard/system/auto-updater/portable-update-now')) ?>" data-bau-install-form>
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <button type="submit" class="bau-button bau-install">Install verified update</button>
      </form><small>Installation requires administrator confirmation, fresh signed authorization, and the updater's independent verification.</small>
    <?php else: ?><button class="bau-button bau-install" disabled>Install update unavailable</button><small><?= !$uCanCheck ? 'Publisher trust must be available to the website runtime.' : 'A fresh, eligible, newer signed release is required.' ?></small><?php endif; ?>
    <p class="bau-separate">License activation and verification are managed separately in <a href="<?= e(url('/dashboard/license')) ?>">License &amp; Activation</a>.</p>
   </div>
  </aside>
 </div>
 <section class="bau-panel bau-bottom" aria-labelledby="bau-overview-title"><div class="bau-panel-heading"><div><h2 id="bau-overview-title">Updater overview</h2><p>Transparent status of each required step. Pending steps are not simulated.</p></div><a class="bau-button bau-refresh" href="<?= e(url('/dashboard/system/auto-updater')) ?>">↻ Refresh</a></div>
  <div class="bau-tablewrap"><table class="bau-table"><thead><tr><th>Check</th><th>Status</th><th>Explanation</th></tr></thead><tbody>
  <?php foreach ($uSteps as $uStep): ?><tr><td><?= e($uStep[0]) ?></td><td><span class="bau-pill <?= $uStep[2] === 'done' ? 'bau-pill--good' : ($uStep[2] === 'warn' ? 'bau-pill--warn' : '') ?>"><?= e($uStep[1]) ?></span></td><td><?= $uStep[2] === 'done' ? 'Confirmed by the current website state' : 'Awaiting an authenticated result or safe update execution' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
</main>
<script defer src="<?= e(asset('js/barama-auto-updater-unified-v100.js') . '?v=100') ?>"></script>
<?php if ($uStagingQueueSupported): ?><script defer src="<?= e(asset('js/barama-oneclick-status-v100.js') . '?v=100') ?>"></script><?php endif; ?>