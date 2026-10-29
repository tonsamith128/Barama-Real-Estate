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
$uVersionAvailable = $uSigned && preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', (string)($uFeed['version'] ?? '')) === 1 && version_compare((string)($uFeed['version'] ?? ''),$uVersion,'>');
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
// STEP5_READONLY_INSPECTION_V100: recompute the exact private ZIP, never trust
// a stale session value or a mutable "verified" flag for lifecycle status.
$uStep5 = ['verified'=>false,'reason'=>'STEP5_NOT_INSPECTED'];
if (class_exists(\Barama\Services\Updates\PortableUpdateInspectionV1::class)) {
    try { $uStep5 = \Barama\Services\Updates\PortableUpdateInspectionV1::status(); }
    catch (\Throwable) { $uStep5 = ['verified'=>false,'reason'=>'STEP5_VERIFICATION_BLOCKED']; }
}
$uStagedVerified = !empty($uStep5['verified']);
$uStorage = !empty($uSystem['storage_writable']);
$uStaging = class_exists(\Barama\Services\Updates\PublisherTrustPaths::class) && \Barama\Services\Updates\PublisherTrustPaths::isProtectedStaging();
$uPortable = false;
try { $uPortable = class_exists(\Barama\Services\Updates\PortableCustomerUpdateV1::class) && \Barama\Services\Updates\PortableCustomerUpdateV1::ready(); } catch (\Throwable) { /* Unknown => disabled */ }
$uPortableState = is_array($systemPortableStatus ?? null) ? (string)($systemPortableStatus['state'] ?? 'not_started') : 'unavailable';
$uPortablePending = is_array($systemPortableStatus ?? null) && !empty($systemPortableStatus['pending']);
// STEP4_PRIVATE_DOWNLOAD_ONLY: allows verified staging without granting
// writable code; the install gate and JS-chained install action remain closed.
$uCanStage = false;
try { $uCanStage = !$uStaging && class_exists(\Barama\Services\Updates\PortableCustomerUpdateV1::class)
    && \Barama\Services\Updates\PortableCustomerUpdateV1::canStage(); }
catch (\Throwable) { /* fail closed */ }
$uStageOnlyAvailable = $uCanStage && $uVersionAvailable
    && $uPortableState === 'not_started' && !$uPortablePending;
$uReadyInstall = !$uStaging && $uPortable && $uVersionAvailable && !$uPortablePending;
// A verified download receipt is an installed-app state; it is not an
// archive inspection or a claim that any website files have been installed.
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

/* BARAMA_PORTABLE_VERIFIED_LIFECYCLE_V105: QA presentation with preserved PR job binding.
 * UI state is server-supplied and cannot authorize downloads/installs.
 * A completed job must refer to the currently installed version, never
 * just any stale completed receipt; no recoverability claims are inferred.
 */
$uPortableTarget = is_array($systemPortableStatus ?? null)
    ? (string)($systemPortableStatus['version'] ?? '') : '';
$uPortableTargetWellFormed = preg_match('/^\\d{1,4}\\.\\d{1,4}\\.\\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $uPortableTarget) === 1;
if (!$uStaging && $uPortableState === 'downloaded' && $uPortableTargetWellFormed) {
    $uStaged = true;
    $uSteps[3] = ['Package staging', 'Signed ZIP downloaded privately', 'done'];
    if ($uStagedVerified) {
        $uSteps[4] = ['Package inspection', 'Signature, ZIP CRC & paths verified', 'done'];
    } elseif (($uStep5['reason'] ?? 'STEP5_NOT_INSPECTED') !== 'STEP5_STAGED_JOB_NOT_READY') {
        $uSteps[4] = ['Package inspection', 'Inspection blocked · review package', 'warn'];
    }
    // No backup, file replacement, installation, or VERSION update was performed.
    $uCompleted = ($uEligible && $uFresh ? 1 : 0) + ($uCanCheck ? 1 : 0)
        + ($uVersionAvailable ? 1 : 0) + 1 + ($uStagedVerified ? 1 : 0);
    $uPhase = $uStagedVerified ? 'Signed ZIP inspected' : 'Signed ZIP staged';
}
$uPortableLifecycle = !$uStaging && $uPortable && $uPortableTargetWellFormed
    && in_array($uPortableState, ['downloaded', 'health_pending', 'completed_file_only'], true)
    && ($uPortableState === 'downloaded' || hash_equals($uVersion, $uPortableTarget))
    && ($uPortableState !== 'completed_file_only' || (!$uVersionAvailable && !$uPortablePending));
if ($uPortableLifecycle) {
    // health_pending occurs after backup creation and verified file installation.
    $uPortableCount = ['downloaded' => 1, 'health_pending' => 3, 'completed_file_only' => 4];
    $uCompleted = $uPortableCount[$uPortableState];
    $uSteps = [
        ['Signed download', 'Signed package verified', 'done'],
        ['Backup creation', $uCompleted >= 3 ? 'Installer checkpoint passed; backup not rechecked' : 'Not yet confirmed', $uCompleted >= 3 ? 'done' : 'pending'],
        ['File installation', $uCompleted === 4 ? 'Installed version verified' : ($uCompleted >= 3 ? 'Installed; final verification pending' : 'Not yet confirmed'), $uCompleted >= 3 ? 'done' : 'pending'],
        ['Final verification', $uCompleted === 4 ? 'Installed version verified' : 'Not yet confirmed', $uCompleted === 4 ? 'done' : 'pending'],
    ];
    $uPhase = match ($uPortableState) {
        'downloaded' => 'Signed package downloaded',
        'health_pending' => 'Final verification pending',
        'completed_file_only' => 'Update completed',
    };
}
$uPortableCompleted = $uPortableLifecycle && $uCompleted === 4;
$uStepTotal = count($uSteps);
$uProgressLabel = $uPortableCompleted
    ? 'Completed · 4 of 4 confirmed'
    : 'Step ' . $uCompleted . ' of ' . $uStepTotal . ' confirmed';
$uPortableRingDescription = $uPortableLifecycle
    ? ($uPortableCompleted ? 'Installed version v' . $uVersion . ' verified'
        : ($uCompleted === 3 ? 'Files installed; final verification pending' : 'Signed package acquired; installation not yet confirmed'))
    : ($uVersionAvailable ? 'Release metadata signed by the trusted publisher' : 'Awaiting verified update discovery');
$uPortableRingNote = $uPortableLifecycle
    ? ($uPortableCompleted ? 'File-only update completed and installed version verified. Rollback was not used.'
        : ($uCompleted === 3 ? 'Backup checkpoint and installation completed; final verification pending.' : 'Signed download verified; installation not yet confirmed.'))
    : ($uPortableState === 'downloaded' ? 'Private signed ZIP downloaded and ' . ($uStagedVerified ? 'independently inspected.' : 'awaiting independent inspection.') . ' No backup or installation started.' : 'No download, backup, or installation has been started on this page.');
$uBackupSummary = $uPortableLifecycle
    ? ($uCompleted >= 3 ? 'Checkpoint passed · artifact not rechecked' : 'Not yet confirmed')
    : 'Not verified in this session';
$uRecoverySummary = $uPortableLifecycle
    ? match ($uPortableState) {
        'downloaded' => 'Not yet applicable',
        'health_pending' => 'Recovery action offered · backup not audited',
        'completed_file_only' => 'Not used · availability not audited',
    }
    : 'Not verified in this session';

?>
<?= barama_css_once('admin') ?>
<?= barama_css_once('admin') ?>
<?= barama_css_once('admin') ?>
<?= barama_css_once('frontend') ?>
<main class="bau<?= $uPortableCompleted ? ' bau-portable-complete' : '' ?>" id="barama-unified-auto-updater" aria-labelledby="bau-title">
 <nav class="bau-breadcrumb" aria-label="Breadcrumb">Administration / System / Auto Updater</nav>
 <header class="bau-heading"><div><h1 id="bau-title">Auto Updater</h1><p>Discover and verify publisher-signed Barama releases in one place.</p></div>
  <form method="post" action="<?= e(url('/dashboard/system/auto-updater/check-updates')) ?>" data-bau-check-form>
   <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
   <button class="bau-button bau-primary" type="submit" <?= $uCanCheck ? '' : 'disabled' ?> data-bau-check>↻ &nbsp; Check for updates</button>
  </form></header>
 <div class="bau-feedback-stack bau-feedback-v103" aria-label="Updater notifications">
 <?php if ($uFlash !== ''): ?><div class="bau-flash" role="status"><?= e($uFlash) ?></div><?php endif; ?>
 <?php if (is_string($uInstallFlash) && $uInstallFlash !== ''): ?><div class="bau-flash" role="status"><?= e($uInstallFlash) ?></div><?php endif; ?>
  <?php if ($uStaging && !empty($systemOneClickFlash)): ?><div class="bau-flash" role="status"><?= e((string)$systemOneClickFlash) ?></div><?php endif; ?>
 </div>
 
 
 <?php require __DIR__ . '/barama-updater-completed-receipt-v101.php'; ?>
 <div class="bau-columns">
  <section class="bau-panel bau-mainpanel" aria-labelledby="bau-workflow-title">
   <div class="bau-panel-heading"><div><h2 id="bau-workflow-title">Update lifecycle</h2><p>Progress is based on confirmed server results. No installation starts during discovery.</p></div><span class="bau-pill<?= $uPortableCompleted ? ' bau-pill--good' : '' ?>"><?= e($uProgressLabel) ?></span></div>
   <div class="bau-progress-layout"><div class="bau-ring-wrap"><div class="bau-ring" data-bau-ring role="status" aria-live="polite" aria-label="<?= e($uPhase) ?>"><div class="bau-ring-inner"><span class="bau-ring-symbol"><?= $uPortableCompleted ? '✓' : '↻' ?></span><strong data-bau-ring-title><?= e($uPhase) ?></strong><small data-bau-ring-desc><?= e($uPortableRingDescription) ?></small></div></div><p class="bau-ring-note" data-bau-live-message><?= e($uPortableRingNote) ?></p></div>
    <ol class="bau-steps"><?php foreach ($uSteps as $uIndex=>$uStep): ?><li class="bau-step bau-step--<?= e($uStep[2]) ?>"><span class="bau-step-num"><?= $uStep[2] === 'done' ? '✓' : (string)($uIndex+1) ?></span><div><strong><?= e($uStep[0]) ?></strong><small><?= e($uStep[1]) ?></small></div></li><?php endforeach; ?></ol>
   </div>
  </section>
  <aside class="bau-panel bau-aside" aria-labelledby="bau-release-title"><div class="bau-panel-heading"><div><h2 id="bau-release-title">Release &amp; installation</h2><p>Only verified facts can unlock the next action.</p></div></div>
   <div class="bau-mini"><span>Installed</span><strong>v<?= e($uVersion) ?></strong></div>
   <div class="bau-mini"><span>Signed candidate</span><strong><?= $uVersionAvailable ? 'v'.e((string)$uFeed['version']) : 'None verified' ?></strong></div>
   <div class="bau-mini"><span>Update entitlement</span><strong><?= $uEligible && $uFresh ? 'Eligible' : ($uFeedStatus === 'ineligible' ? (($uFeed['entitlement'] ?? '') === 'Not configured' ? 'Not configured' : 'Not entitled') : 'Not confirmed') ?></strong></div>
   <div class="bau-mini"><span>Staged release ZIP</span><strong><?= $uStagedVerified ? 'Verified' : ($uStaged ? 'Present · unverified' : 'Not staged') ?></strong></div>
   <?php if ($uPortableLifecycle): ?>
   <div class="bau-mini"><span>Backup creation</span><strong><?= e($uBackupSummary) ?></strong></div>
   <div class="bau-mini"><span>Rollback &amp; recovery</span><strong><?= e($uRecoverySummary) ?></strong></div>
   <?php else: ?>
   <div class="bau-mini"><span>Backup and rollback</span><strong>Not verified in this session</strong></div>
   <?php endif; ?>
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
    <?php elseif (!$uStaging && $uPortableState === 'downloaded'): ?>
      <div class="bau-safe-note" role="status">Signed ZIP staged in private storage. No website files installed. Installation remains locked until separate backup and code-write readiness is approved.</div>
      <button type="button" class="bau-button bau-install" disabled>Installation locked — Step 6</button>
      <?php if ($uStagedVerified): ?>
        <div class="bau-safe-note" role="status">Step 5 complete: the signed ZIP, SHA-256, CRC, allowed paths, and current file baselines were verified independently. <?= e((string)($uStep5['file_count'] ?? '')) ?> file entries inspected; <?= e((string)($uStep5['overwrites'] ?? '')) ?> non-version file(s) differ. No installation performed.</div>
        <?php if (!empty($uStep5['version_only'])): ?>
        <small>Verified version-only package: the signed v0.9.23 ZIP changes VERSION, but its included PHP source already matches Live Demo. Step 6 remains locked; the normal installer still requires a separately reviewed install policy and backup.</small>
        <?php endif; ?>
        <?php if (!empty($uStep5['changed_paths'])): ?>
        <small>Proposed changed files: <?= e(implode(', ', (array)$uStep5['changed_paths'])) ?>. Review these changes before separately approving Step 6.</small>
        <?php endif; ?>
      <?php else: ?>
        <small>Step 5 inspection pending: <?= e((string)($uStep5['reason'] ?? 'STEP5_VERIFICATION_BLOCKED')) ?>. No installation permitted.</small>
      <?php endif; ?>
    <?php elseif ($uStageOnlyAvailable): ?>
      <form method="post" action="<?= e(url('/dashboard/system/auto-updater/portable-update-now')) ?>">
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <button type="submit" class="bau-button bau-install">Download signed ZIP only</button>
      </form>
      <small>Step 4 only: authorized private download and SHA-256 verification. No files installed, no backup started, no automatic continuation.</small>
    <?php elseif (in_array($uPortableState,['downloaded','health_pending'],true) && $uPortable): ?>
      <form method="post" action="<?= e(url('/dashboard/system/auto-updater/' . ($uPortableState === 'downloaded' ? 'portable-install' : 'portable-finish'))) ?>"
            data-bau-install-form data-bau-portable-flow data-portable-start="<?= e($uPortableState) ?>">
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <button type="submit" class="bau-button bau-install"><?= $uPortableState === 'downloaded' ? 'Continue verified installation' : 'Verify installed update' ?></button>
      </form><small>Existing verified job: <?= e(str_replace('_',' ',$uPortableState)) ?>.</small>
      <?php if ($uPortableState === 'health_pending'): ?>
      <form method="post" action="<?= e(url('/dashboard/system/auto-updater/portable-recover')) ?>" data-bau-portable-recovery>
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <button type="submit" class="bau-button bau-refresh">Restore previous files</button>
      </form><small>Manual rollback of this bound file-only job only. No database data will be restored.</small>
      <?php endif; ?>
    <?php elseif ($uPortableState === 'recovery_review_required'): ?>
      <button type="button" class="bau-button bau-install" disabled>Recovery review required</button>
      <small>Preserve the private backup. Do not submit a new update.</small>
    <?php elseif ($uReadyInstall): ?>
      <form method="post" action="<?= e(url('/dashboard/system/auto-updater/portable-update-now')) ?>"
            data-bau-install-form data-bau-portable-flow data-portable-start="not_started">
       <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
       <button type="submit" class="bau-button bau-install">Install verified update</button>
      </form><small>Administrator-only: signed download, private backup, file-only installation and independent verification. No DB migrations.</small>
    <?php elseif ($uPortableState === 'completed_file_only'): ?>
      <?php if ($uPortableCompleted): ?>
      <div class="bau-safe-note" role="status">Previous file-only update completed and installed files were verified.</div>
      <?php else: ?>
      <button type="button" class="bau-button bau-install" disabled>Update status requires review</button>
      <small>Completed receipt cannot be matched to the installed version; review the private job before updating.</small>
      <?php endif; ?>
    <?php else: ?><button class="bau-button bau-install" disabled>Install update unavailable</button>
      <small><?= !$uPortable ? 'Private vault, writable code and PHP extensions are required.' : (!$uCanCheck ? 'Publisher trust must be available.' : 'A fresh newer eligible signed release is required.') ?></small>
    <?php endif; ?>
    <p class="bau-separate">License activation and verification are managed separately in <a href="<?= e(url('/dashboard/license')) ?>">License &amp; Activation</a>.</p>
   </div>
  </aside>
 </div>
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
  <a class="bau-button bau-license-action" href="<?= e(url('/dashboard/license')) ?>">Manage license</a>
 </div>
 <section class="bau-panel bau-bottom" aria-labelledby="bau-overview-title"><div class="bau-panel-heading"><div><h2 id="bau-overview-title">Updater overview</h2><p>Transparent status of each required step. Pending steps are not simulated.</p></div><a class="bau-button bau-refresh" href="<?= e(url('/dashboard/system/auto-updater')) ?>">↻ Refresh</a></div>
  <div class="bau-tablewrap"><table class="bau-table"><thead><tr><th>Check</th><th>Status</th><th>Explanation</th></tr></thead><tbody>
  <?php foreach ($uSteps as $uStep): ?><tr><td><?= e($uStep[0]) ?></td><td><span class="bau-pill <?= $uStep[2] === 'done' ? 'bau-pill--good' : ($uStep[2] === 'warn' ? 'bau-pill--warn' : '') ?>"><?= e($uStep[1]) ?></span></td><td><?= $uStep[2] === 'done' ? 'Confirmed by the current website state' : 'Awaiting an authenticated result or safe update execution' ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
</main>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="barama-auto-updater-unified"></script>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="barama-auto-updater-experience"></script>
<?php if ($uStagingQueueSupported): ?><?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="barama-oneclick-status"></script><?php endif; ?>
<?php if (!$uStaging): ?><?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="barama-portable-auto-updater"></script><?php endif; ?>
