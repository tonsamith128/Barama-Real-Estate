<?php
/** Scoped admin System Center view. Values are supplied by read-only controller. */
$systemSection = (string) ($systemSection ?? 'about');
$systemTitle = (string) ($systemTitle ?? 'System');
$systemVersion = (string) ($systemVersion ?? 'Unknown');
$systemInstalledVersion = (string) ($systemInstalledVersion ?? 'Unknown');
$systemSnapshot = is_array($systemSnapshot ?? null) ? $systemSnapshot : [];
$systemLicense = is_array($systemLicense ?? null) ? $systemLicense : [];
$systemEnvironment = is_array($systemEnvironment ?? null) ? $systemEnvironment : [];
$systemRequirements = is_array($systemRequirements ?? null) ? $systemRequirements : [];
$systemUpdateSummary = is_array($systemUpdateSummary ?? null) ? $systemUpdateSummary : [];
$systemAutoUpdater = is_array($systemAutoUpdater ?? null) ? $systemAutoUpdater : [];
$systemReleaseReadiness = is_array($systemReleaseReadiness ?? null) ? $systemReleaseReadiness : [];
$systemReleaseInspection = is_array($systemReleaseInspection ?? null) ? $systemReleaseInspection : ['status'=>'Not inspected','checked_at'=>'Never'];
$systemPublisherCheck = is_array($systemPublisherCheck ?? null) ? $systemPublisherCheck : ['state' => 'Not checked', 'checked_at' => 'Never'];
$systemCheckedAt = (string) ($systemCheckedAt ?? 'Not checked');
$systemPublisherFlash = !empty($systemPublisherFlash);
$systemLicenseBinding = is_array($systemLicenseBinding ?? null) ? $systemLicenseBinding : [];
$publisherState = (string) ($systemPublisherCheck['state'] ?? 'Not checked');
$publisherVerified = $publisherState === 'Active license verified';
$publisherRecognized = $publisherState === 'Installed license recognized';
$publisherPositive = $publisherVerified || $publisherRecognized;
$versionMatches = $systemVersion !== '' && $systemVersion === $systemInstalledVersion;
$systemServices = is_array($systemSnapshot['services'] ?? null) ? $systemSnapshot['services'] : [];
$licenseLabel = (string) ($systemLicense['status'] ?? 'Not recorded');
$supportUntil = (string) ($systemLicense['support_until'] ?? 'Not recorded');
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-system-center-v103.css') . '?v=110') ?>">
<?php if ($systemSection === 'auto-updater'): ?><link rel="stylesheet" href="<?= e(asset('css/barama-publisher-license-ux-v112.css') . '?v=113') ?>"><link rel="stylesheet" href="<?= e(asset('css/barama-auto-updater-premium-v120.css') . '?v=121') ?>"><link rel="stylesheet" href="<?= e(asset('css/barama-auto-updater-actions-v122.css') . '?v=122') ?>"><?php endif; ?>
<section class="barama-sys-v103 <?= $systemSection === 'auto-updater' ? 'bsys-auto-premium' : '' ?>" aria-labelledby="barama-system-title">
  <nav class="bsys-breadcrumb" aria-label="Breadcrumb">Administration / System / <?= e($systemTitle) ?></nav>
  <div class="bsys-header">
    <div>
      <h1 id="barama-system-title"><?= e($systemTitle) ?></h1>
      <?php if ($systemSection !== 'auto-updater'): ?><p class="bsys-description"><?php if ($systemSection === 'about'): ?>Product identity, installation, license records, support resources and runtime requirements.<?php elseif ($systemSection === 'updates'): ?>Review version compatibility and the existing guarded updater. Signed automatic updates are not yet enabled.<?php elseif ($systemSection === 'auto-updater'): ?>Secure product delivery from Ton Samith. Only verified local readiness indicators are shown until the update service is connected.<?php else: ?>Read-only service signals and infrastructure readiness. Only the checks expressly shown as tested have been measured.<?php endif; ?></p><?php endif; ?>
    </div>
    <?php if ($systemSection === 'auto-updater'): ?>
    <div class="bsys-update-header-right">
      <div class="bsys-update-header-actions" aria-label="Update management actions">
        <form method="post" action="<?= e(url('/dashboard/system/auto-updater/check-updates')) ?>" class="bsys-inline-action">
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <button class="bsys-button bsys-button--primary" type="submit" <?= !empty($systemFeedReadiness['can_check']) ? '' : 'disabled title="Author must configure the trusted publisher public key and release-feed contract"' ?>>Check for updates</button>
        </form>
        <?php if (!empty($systemUpdaterPresent)): ?><a class="bsys-button bsys-button--outline" href="<?= e(url('/updater/')) ?>">Open guarded updater <span aria-hidden="true">↗</span></a><?php endif; ?>
      </div>
      <small class="bsys-update-discovery-note">Discovery only · no automatic download or installation</small>
      <?php if (!empty($systemFeedFlash)): ?><p class="bsys-note bsys-note--feed-status bsys-update-header-feedback" role="status" aria-live="polite"><?= e((string)$systemFeedFlash) ?></p><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bsys-chip-row"><span class="bsys-chip">Barama Real Estate</span><span class="bsys-state bsys-state--neutral">v<?= e($systemVersion) ?></span></div>
    <?php endif; ?>
  </div>


  <?php if ($systemSection === 'auto-updater'): ?>
  <?php
  // Read-only presentation. No remote entitlement/update claims are inferred from a local license.
  $boundDomain = (string) ($systemLicenseBinding['recorded_domain'] ?? 'Not recorded');
  $currentDomain = (string) ($systemLicenseBinding['current_domain'] ?? 'Not recorded');
  $boundOk = $boundDomain !== 'Not recorded' && $currentDomain !== 'Not recorded'
      && $boundDomain !== '' && $currentDomain !== '' && hash_equals($currentDomain, $boundDomain);
  $updaterFound = !empty($systemUpdaterPresent);
  $storageOk = !empty($systemAutoUpdater['storage_writable']);
  $versionOk = !empty($systemAutoUpdater['version_match']);
  // Priorities are strictly presentation order; they are not new diagnostic test results.
  $feedResult = is_array($systemFeedResult ?? null) ? $systemFeedResult : [];
  $feedReady = is_array($systemFeedReadiness ?? null) ? $systemFeedReadiness : [];
  $feedEligible = ($feedResult['entitlement'] ?? '') === 'Eligible';
  $feedSigned = ($feedResult['feed'] ?? '') === 'Signed release verified';
  $feedStatus = (string)($feedResult['status'] ?? 'not_checked');
  $feedVersion = (string)($feedResult['version'] ?? '');
  $feedConfigured = !empty($feedReady['can_check']);
  $premiumRows = [
    ['id'=>'license','icon'=>'shield','label'=>'Publisher verification record',
      'state'=>$publisherVerified ? 'Verified' : ($publisherRecognized ? 'Activated' : $publisherState),
      'tone'=>$publisherPositive ? 'good' : 'needs',
      'summary'=>$publisherVerified ? 'Last successful publisher check securely saved for this installation' : ($publisherRecognized ? 'Existing installed activation recognized; optional publisher check' : 'Publisher confirmation not recorded for this installation'),
      'detail'=>'The last trusted publisher result stays recorded across sessions until the installation binding changes or a new trusted verification replaces it. Verify again is optional; update entitlement is checked separately.',
      'priority'=>$publisherPositive ? 20 : 200],
    ['id'=>'domain','icon'=>'globe','label'=>'Bound domain authorization',
      'state'=>$boundOk ? 'Matched' : 'Review needed', 'tone'=>$boundOk ? 'good' : 'needs',
      'summary'=>$boundOk ? 'Matches the current website domain' : 'Review the recorded domain binding',
      'detail'=>'Current website: '.$currentDomain.' | License binding: '.$boundDomain.'. Changing an activated domain requires the authorized publisher workflow.',
      'priority'=>$boundOk ? 15 : 190],
    ['id'=>'entitlement','icon'=>'key','label'=>'Update entitlement',
      'state'=>(string)($feedResult['entitlement']??'Not checked'),
      'tone'=>$feedEligible ? 'good' : ($feedStatus==='ineligible' ? 'needs' : 'neutral'),
      'summary'=>$feedEligible ? 'Publisher confirmed update eligibility for this installation' : ($feedStatus==='ineligible' ? (($feedResult['reason'] ?? '') === 'not_configured' ? 'Publisher has not configured update eligibility for this installation' : 'Publisher denied update entitlement') : ($feedConfigured ? 'Use Check for updates to request entitlement' : 'Publisher update contract not configured')),
      'detail'=>'Authoritative entitlement requires a POST to the trusted publisher with the existing installation-bound activation. Last check: '.(string)($feedResult['checked_at']??'Never').'. A valid license by itself does not grant update entitlement.',
      'priority'=>110],
    ['id'=>'feed','icon'=>'signal','label'=>'Signed release feed',
      'state'=>(string)($feedResult['feed']??'Not connected'),
      'tone'=>$feedSigned ? 'good' : ($feedStatus==='eligible' ? 'info' : 'neutral'),
      'summary'=>$feedSigned ? 'Publisher-signed release manifest verified: v'.$feedVersion : ($feedStatus==='eligible' ? 'No newer signed release currently published' : ($feedConfigured ? 'Feed is configured but has not returned a verified release' : 'Trusted release feed not connected')),
      'detail'=>'Feed metadata must arrive from the pinned HTTPS publisher; an Ed25519 signature over the canonical V2 manifest is verified with the separately provisioned trusted public key. This step does not download or authenticate a release ZIP. Last check: '.(string)($feedResult['checked_at']??'Never').'.',
      'priority'=>105],
    ['id'=>'authenticity','icon'=>'shield','label'=>'Release authenticity',
      'state'=>($systemReleaseInspection['status']??'')==='Signed package verified (not installed)' ? 'Verified package' : 'Not verified',
      'tone'=>($systemReleaseInspection['status']??'')==='Signed package verified (not installed)' ? 'good' : 'needs',
      'summary'=>($systemReleaseInspection['status']??'')==='Signed package verified (not installed)' ? 'Signed staged release passed offline inspection (not installed)' : 'No signed staged release has passed verification',
      'detail'=>'The offline verifier checks the pinned publisher Ed25519 V2 catalog, canonical signature, product, version and PHP compatibility, archive digest, and ZIP traversal/symlinks/entry limits. It never installs or extracts. Last result: '.(string)($systemReleaseInspection['status']??'Not inspected').'.',
      'priority'=>100],
    ['id'=>'staging','icon'=>'layers','label'=>'Secure staging & compatibility',
      'state'=>!empty($systemReleaseReadiness['staged_release']) ? 'Package staged' : 'Not staged',
      'tone'=>!empty($systemReleaseReadiness['staged_release']) ? 'info' : 'needs',
      'summary'=>!empty($systemReleaseReadiness['staged_release']) ? 'Private staging files detected' : 'No release package staged privately',
      'detail'=>'Readiness: trusted public key '.(!empty($systemReleaseReadiness['trusted_key']) ? 'present' : 'not configured').'; private package '.(!empty($systemReleaseReadiness['staged_release']) ? 'present' : 'not staged').'; PHP ZIP extension '.(!empty($systemReleaseReadiness['zip_available']) ? 'available' : 'missing').'. Backup, database migrations and automatic installation are separately blocked until verified.',
      'priority'=>95],
    ['id'=>'backup','icon'=>'database','label'=>'Backup & rollback', 'state'=>'Not checked', 'tone'=>'neutral',
      'summary'=>'Verified restore rehearsal not recorded',
      'detail'=>'Automatic updates require private file and database backups with integrity verification, migration rollback planning and a tested restore path. Prior manual upgrade backups do not certify an unattended restore rehearsal.',
      'priority'=>90],
    ['id'=>'updater','icon'=>'settings','label'=>'Existing guarded updater',
      'state'=>$updaterFound ? 'Available' : 'Unavailable', 'tone'=>$updaterFound ? 'info' : 'needs',
      'summary'=>$updaterFound ? 'Manual guarded updater entry is present' : 'Manual updater entry was not found',
      'detail'=>'The existing guarded updater is separate from the disabled automatic release workflow. Use its own administrator and backup checks.',
      'priority'=>$updaterFound ? 10 : 180],
  ];
  usort($premiumRows, static fn(array $a,array $b): int => $b['priority'] <=> $a['priority']);
  $premiumSvg = static function (string $name): string {
      $paths = [
        'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'globe'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'key'=>'<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9 2 2-2 2 1 1-3 3-2-2-4 4"/>',
        'signal'=>'<path d="M3 16a14 14 0 0 1 18 0M6 19a9 9 0 0 1 12 0M10 22a3 3 0 0 1 4 0"/><path d="M12 3v9"/>',
        'layers'=>'<path d="m12 2 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'database'=>'<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1m0-14.2L17 7M7 17l-2.1 2.1"/>',
      ];
      return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name] ?? $paths['settings']).'</svg>';
  };
  ?>
  <section class="bsys-license-focus bsys-license-center bsys-premium-hero" aria-label="License verification center" data-publisher-control data-publisher-flash="<?= $systemPublisherFlash ? '1' : '0' ?>" data-verified="<?= $publisherPositive ? '1' : '0' ?>">
    <div class="bsys-premium-main">
      <div class="bsys-license-emblem" data-publisher-emblem aria-hidden="true">
        <span class="bsys-license-emblem-halo"></span>
        <svg class="bsys-license-shield" viewBox="0 0 96 112" fill="none" aria-hidden="true"><defs><linearGradient id="bsys-shield-gradient-v121" x1="8" y1="5" x2="88" y2="108" gradientUnits="userSpaceOnUse"><stop class="bsys-shield-start" offset="0"/><stop class="bsys-shield-end" offset="1"/></linearGradient></defs><path d="M48 5C61 16 76 17 89 20V55C89 79 75 95 48 107C21 95 7 79 7 55V20C20 17 35 16 48 5Z" stroke="url(#bsys-shield-gradient-v121)" stroke-width="5.5"/><path d="m26 55 15 15 29-31" stroke="url(#bsys-shield-gradient-v121)" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="bsys-license-emblem-spinner" aria-hidden="true"></span>
      </div>
      <div class="bsys-premium-hero-copy">
        <span class="bsys-license-eyebrow">License verification</span>
        <h2 data-publisher-hero-title><?= $publisherVerified ? 'License verified' : ($publisherRecognized ? 'License activated' : 'Verify your Barama license') ?></h2>
        <p class="bsys-license-hero-detail" data-publisher-hero-detail><?= $publisherVerified ? 'Publisher verification saved for this installation. No repeat check is required to view the recorded result.' : ($publisherRecognized ? 'Your installed license is already recognized. A new publisher check is optional.' : 'Check your license securely with Ton Samith when you choose.') ?></p>
        <div class="bsys-license-last" role="status" aria-live="polite" aria-atomic="true">
          <span class="bsys-state <?= $publisherPositive ? 'bsys-state--good' : 'bsys-state--neutral' ?>" data-publisher-state><?= e($publisherState) ?></span>
          <small data-publisher-checked><?= $publisherRecognized ? 'Activation recorded: ' : 'Last checked: ' ?><?= e((string) ($systemPublisherCheck['checked_at'] ?? 'Never')) ?></small>
        </div>
        <div class="bsys-license-action-row" aria-label="License verification and support">
          <form method="post" action="<?= e(url('/dashboard/system/auto-updater/check-license')) ?>" data-publisher-check-form>
            <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
            <button type="submit" class="bsys-button bsys-button--primary bsys-license-check-button" data-publisher-check-button><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.5 6"/><path d="M20 4v7h-7"/></svg><span data-publisher-button-text><?= $publisherVerified ? 'Verify again' : ($publisherRecognized ? 'Verify publisher (optional)' : 'Check license') ?></span></button>
          </form>
          <a class="bsys-button bsys-button--outline" href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">Request domain replacement <span aria-hidden="true">↗</span></a>
          <a class="bsys-button bsys-button--outline" href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">License assistance <span aria-hidden="true">↗</span></a>
        </div>
      </div>
    </div>
    <div class="bsys-premium-hero-meta"><div><span>Bound domain</span><strong><?= e($boundDomain) ?></strong></div><div><span>Installed version</span><strong>v<?= e($systemVersion) ?></strong></div></div>
    <div class="bsys-license-progress" data-publisher-progress role="status" aria-live="polite" aria-atomic="true" hidden><div class="bsys-license-progress-copy"><strong data-publisher-phase>Preparing secure request</strong><span data-publisher-detail>Confirming your administrator session.</span></div></div>
    <div class="bsys-license-lifecycle" data-publisher-lifecycle hidden aria-label="Verification request lifecycle"><span data-lifecycle-stage="prepare">Preparing</span><span data-lifecycle-stage="publisher">Contacting publisher</span><span data-lifecycle-stage="response">Reviewing response</span><span data-lifecycle-stage="complete">Result</span></div>
  </section>
  <div class="bsys-toast-host" data-publisher-toast-host aria-live="polite" aria-atomic="true"><div class="bsys-toast" data-publisher-toast hidden><span class="bsys-toast-symbol" data-publisher-toast-symbol aria-hidden="true"></span><div class="bsys-toast-copy"><strong data-publisher-toast-title>License verification</strong><p data-publisher-toast-description></p></div><button type="button" class="bsys-toast-close" data-publisher-toast-close aria-label="Dismiss notification">×</button></div></div>
  <?php if ($systemPublisherFlash): ?><noscript><p class="bsys-license-nojs" role="status">Last license check: <?= e($publisherState) ?>.</p></noscript><?php endif; ?>
  <?php endif; ?>

  <?php if ($systemSection === 'about'): ?>
    <div class="bsys-metrics" aria-label="Product overview">
      <div class="bsys-metric"><span class="bsys-label">Product</span><strong class="bsys-metric-value">Barama Real Estate</strong><span class="bsys-metric-hint">Property marketplace</span></div>
      <div class="bsys-metric"><span class="bsys-label">Installed version</span><strong class="bsys-metric-value">v<?= e($systemInstalledVersion !== '' ? $systemInstalledVersion : 'Unknown') ?></strong><span class="bsys-metric-hint"><?= $versionMatches ? 'Matches application version' : 'Version record needs review' ?></span></div>
      <div class="bsys-metric"><span class="bsys-label">License state</span><strong class="bsys-metric-value"><?= e($licenseLabel) ?></strong><span class="bsys-metric-hint">Stored record · not revalidated online</span></div>
      <div class="bsys-metric"><span class="bsys-label">Support period</span><strong class="bsys-metric-value"><?= e($supportUntil) ?></strong><span class="bsys-metric-hint">From local license record, if present</span></div>
    </div>
    <div class="bsys-layout">
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Product & installation</h2><p>Information from this installation and product configuration</p></div><span class="bsys-chip">Read-only</span></div>
        <div class="bsys-table-wrap"><table><tbody>
          <tr><td>Product name</td><td>Barama Real Estate</td></tr>
          <tr><td>Product category</td><td>Real estate marketplace and management</td></tr>
          <tr><td>Application version</td><td>v<?= e($systemVersion) ?></td></tr>
          <tr><td>Installation record</td><td>v<?= e($systemInstalledVersion !== '' ? $systemInstalledVersion : 'Unknown') ?></td></tr>
          <tr><td>Version consistency</td><td><span class="bsys-state <?= $versionMatches ? 'bsys-state--good' : 'bsys-state--warn' ?>"><span class="bsys-indicator"></span><?= $versionMatches ? 'Records match' : 'Needs review' ?></span></td></tr>
          <tr><td>Release channel</td><td><?= e((string) ($systemEnvironment['release_channel'] ?? 'Not determined')) ?></td></tr>
          <tr><td>Author</td><td>Ton Samith</td></tr>
          <tr><td>Deployment</td><td>Self-hosted installation</td></tr>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>License information</h2><p>Safe summary without exposing keys, tokens or purchase codes</p></div><span class="bsys-state bsys-state--neutral">Locally recorded</span></div>
        <div class="bsys-table-wrap"><table><tbody>
          <tr><td>License status</td><td><?= e($licenseLabel) ?></td></tr>
          <tr><td>License type</td><td><?= e((string) ($systemLicense['type'] ?? 'Not recorded')) ?></td></tr>
          <tr><td>Purchase source</td><td><?= e((string) ($systemLicense['source'] ?? 'Not recorded')) ?></td></tr>
          <tr><td>Purchase code / activation token</td><td>Protected · never displayed</td></tr>
          <tr><td>Activated product</td><td><?= e((string) ($systemLicense['product'] ?? 'Not recorded')) ?></td></tr>
          <tr><td>Last license validation</td><td><?= e((string) ($systemLicense['validated_at'] ?? 'Not recorded')) ?></td></tr>
          <tr><td>Support period ends</td><td><?= e($supportUntil) ?></td></tr>
          <tr><td>Live entitlement check</td><td><span class="bsys-state bsys-state--neutral">Not performed on this page</span></td></tr>
        </tbody></table></div>
      </section>
      <section class="bsys-panel"><div class="bsys-section-title"><div><h2>System requirements & compatibility</h2><p>Baseline compatibility and current PHP runtime module availability</p></div><span class="bsys-chip">Measured at page load</span></div>
        <div class="bsys-table-wrap"><table><thead><tr><th>Component</th><th>Requirement / purpose</th><th>Detected</th><th>State</th></tr></thead><tbody>
          <?php foreach ($systemRequirements as $item): if (!is_array($item)) continue; ?>
          <tr><td><?= e((string) ($item['name'] ?? 'Runtime')) ?></td><td><?= e((string) ($item['requirement'] ?? 'Not specified')) ?></td><td><?= e((string) ($item['detected'] ?? 'Not inspected')) ?></td><td><span class="bsys-state <?= ($item['state'] ?? '') === 'Available' ? 'bsys-state--good' : (($item['state'] ?? '') === 'Unavailable' ? 'bsys-state--warn' : 'bsys-state--neutral') ?>"><?= e((string) ($item['state'] ?? 'Not assessed')) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Environment summary</h2><p>Limited read-only runtime details</p></div></div>
        <div class="bsys-table-wrap"><table><tbody>
          <tr><td>PHP runtime</td><td><?= e((string) ($systemEnvironment['php_version'] ?? 'Unknown')) ?></td></tr>
          <tr><td>PHP interface</td><td><?= e((string) ($systemEnvironment['php_sapi'] ?? 'Unknown')) ?></td></tr>
          <tr><td>Storage writable</td><td><?= e((string) ($systemEnvironment['storage'] ?? 'Not assessed')) ?></td></tr>
          <tr><td>HTTP server</td><td>Not inspected by this page</td></tr>
          <tr><td>Database engine/version</td><td>Not inspected by this page</td></tr>
          <tr><td>External integrations</td><td>Not tested by this page</td></tr>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Support & documentation</h2><p>Help, licensing and maintenance resources</p></div></div>
        <div class="bsys-section-body"><div class="bsys-overview-grid">
          <div class="bsys-overview-item"><span class="bsys-label">Support period</span><strong><?= e($supportUntil) ?></strong><small>No unverified entitlement is implied</small></div>
          <div class="bsys-overview-item"><span class="bsys-label">Maintained by</span><strong>Ton Samith</strong><small>Product author and publisher</small></div>
        </div><div class="bsys-actions"><a class="bsys-button bsys-button--primary" href="<?= e((string) config('app.support_url', 'https://tonsamith.com/contact')) ?>" target="_blank" rel="noopener noreferrer">Contact support ↗</a><a class="bsys-button" href="<?= e((string) config('app.documentation_url', 'https://docs.tonsamith.com')) ?>" target="_blank" rel="noopener noreferrer">Documentation ↗</a><a class="bsys-button" href="https://tonsamith.com" target="_blank" rel="noopener noreferrer">Author website ↗</a></div></div>
      </section>
    </div>

  <?php elseif ($systemSection === 'updates'): ?>
    <div class="bsys-metrics" aria-label="Update overview">
      <div class="bsys-metric"><span class="bsys-label">Application version</span><strong class="bsys-metric-value">v<?= e($systemVersion) ?></strong><span class="bsys-metric-hint">Installed application source</span></div>
      <div class="bsys-metric"><span class="bsys-label">Installed record</span><strong class="bsys-metric-value">v<?= e($systemInstalledVersion !== '' ? $systemInstalledVersion : 'Unknown') ?></strong><span class="bsys-metric-hint"><?= $versionMatches ? 'Matches current version' : 'Version mismatch' ?></span></div>
      <div class="bsys-metric"><span class="bsys-label">Latest available release</span><strong class="bsys-metric-value">Not checked</strong><span class="bsys-metric-hint">No connected release feed</span></div>
      <div class="bsys-metric"><span class="bsys-label">Automatic installation</span><strong class="bsys-metric-value">Not enabled</strong><span class="bsys-metric-hint">Manual guarded updater available</span></div>
    </div>
    <div class="bsys-layout">
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Version overview</h2><p>Actual local version information</p></div></div>
        <div class="bsys-table-wrap"><table><tbody>
          <tr><td>Installed source</td><td>v<?= e($systemVersion) ?></td></tr>
          <tr><td>Installation record</td><td>v<?= e($systemInstalledVersion !== '' ? $systemInstalledVersion : 'Unknown') ?></td></tr>
          <tr><td>Version records</td><td><span class="bsys-state <?= $versionMatches ? 'bsys-state--good' : 'bsys-state--warn' ?>"><?= $versionMatches ? 'Match' : 'Review required' ?></span></td></tr>
          <tr><td>Release channel</td><td><?= e((string) ($systemEnvironment['release_channel'] ?? 'Not determined')) ?></td></tr>
          <tr><td>Available version</td><td>Not checked</td></tr>
          <tr><td>Release entitlement</td><td>Not checked</td></tr>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Existing guarded updater</h2><p>The established update flow is unchanged</p></div><span class="bsys-state <?= !empty($systemUpdaterPresent) ? 'bsys-state--good' : 'bsys-state--warn' ?>"><?= !empty($systemUpdaterPresent) ? 'Entry point found' : 'Unavailable' ?></span></div>
        <div class="bsys-section-body"><p class="bsys-note">The existing updater requires administrator credentials, backup confirmation and package validation. It is not an automatic version-check service, and opening it does not install an update.</p>
        <?php if (!empty($systemUpdaterPresent)): ?><div class="bsys-actions"><a class="bsys-button bsys-button--primary" href="<?= e(url('/updater/')) ?>">Open guarded updater ↗</a><a class="bsys-button" href="<?= e(url('/dashboard/system/auto-updater')) ?>">Auto Updater overview ↗</a></div><?php endif; ?></div>
      </section>
      <section class="bsys-panel"><div class="bsys-section-title"><div><h2>Readiness & compatibility</h2><p>Indicators checked without executing an update or migration</p></div><span class="bsys-chip">Preliminary only</span></div>
        <div class="bsys-table-wrap"><table><thead><tr><th>Check</th><th>Current observation</th><th>Status</th></tr></thead><tbody>
          <?php foreach ($systemUpdateSummary as $item): if (!is_array($item)) continue; ?>
          <tr><td><?= e((string) ($item['name'] ?? 'Check')) ?></td><td><?= e((string) ($item['description'] ?? '')) ?></td><td><span class="bsys-state <?= ($item['status'] ?? '') === 'Available' ? 'bsys-state--good' : (($item['status'] ?? '') === 'Attention' ? 'bsys-state--warn' : 'bsys-state--neutral') ?>"><?= e((string) ($item['status'] ?? 'Not checked')) ?></span></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Update history</h2><p>A verified historical release log is not connected</p></div></div><div class="bsys-table-wrap"><table><thead><tr><th>Installed</th><th>Version</th><th>Method</th><th>Result</th></tr></thead><tbody><tr><td colspan="4"><span class="bsys-state bsys-state--neutral">Not available</span> <span class="bsys-note"> No authoritative update history source is connected.</span></td></tr></tbody></table></div></section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Secure auto-update roadmap</h2><p>Planned safeguards, not yet active controls</p></div><span class="bsys-state bsys-state--warn">Not configured</span></div><div class="bsys-section-body"><div class="bsys-overview-grid">
        <?php foreach (['Signed release metadata','Verified private downloads','Backup & safe staging','Migration and recovery checks'] as $future): ?><div class="bsys-overview-item"><span class="bsys-label">Planned</span><strong><?= e($future) ?></strong><small>Implementation / QA pending</small></div><?php endforeach; ?>
      </div></div></section>
    </div>


  <?php elseif ($systemSection === 'auto-updater'): ?>
    <section class="bsys-panel" aria-label="One-click update installation">
      <div class="bsys-section-title"><div><h2>One-click Update</h2>
        <p>Administrator-initiated signed updates with independent worker verification.</p></div>
        <span class="bsys-state bsys-state--neutral"><?= !empty($systemOneClickPending) ? 'Request pending' : 'Ready to check' ?></span>
      </div>
      <div class="bsys-section-body">
        <?php if (!empty($systemOneClickFlash)): ?>
        <p class="bsys-note" role="status" aria-live="polite"><?= e((string)$systemOneClickFlash) ?></p>
        <?php endif; ?>
        <p class="bsys-note">Installed version: <strong><?= e((string)$systemVersion) ?></strong>.
          <?= !empty($systemOneClickPending) ? 'A request already exists and must be resolved before resubmission.' : 'The publisher must have an eligible published release, a matching signed package, and a trusted worker before an update can complete.' ?>
        </p>
        <form method="post" action="<?= e(url('/dashboard/system/auto-updater/update-now')) ?>" class="bsys-inline-action">
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <button type="submit" class="bsys-button bsys-button--primary" <?= !empty($systemOneClickPending) || empty($systemOneClickWorkerReady) || ($systemFeedResult['status'] ?? '') !== 'available' ? 'disabled' : '' ?>>Update Now</button>
          <a class="bsys-button" href="<?= e(url('/dashboard/system/auto-updater')) ?>">Refresh status</a>
        </form>
        <?php if (empty($systemOneClickWorkerReady)): ?><p class="bsys-note" role="status">Trusted worker offline. Update Now is disabled until a signed update is available and the trusted worker is ready.</p><?php endif; ?>
        <div class="bsys-note" role="status" aria-live="polite"
          data-oneclick-status
          data-oneclick-initial-worker-ready="<?= !empty($systemOneClickWorkerReady) ? '1' : '0' ?>"
          data-oneclick-initial-pending="<?= !empty($systemOneClickPending) ? '1' : '0' ?>"
          data-oneclick-initial-state="<?= e((string)($systemOneClickStatus['state'] ?? 'not_started')) ?>"
          data-oneclick-endpoint="<?= e(url('/dashboard/system/auto-updater/status')) ?>">
          Update job status: <strong data-oneclick-state><?= e(str_replace('_',' ',(string)($systemOneClickStatus['state']??'not_started'))) ?></strong>
          <span data-oneclick-worker><?= empty($systemOneClickWorkerReady) ? ' · Trusted worker offline' : ' · Trusted worker online' ?></span>
        </div>
        <p class="bsys-note">Submitting a request does not mean installation succeeded. A separate trusted worker must confirm completion.</p>
      </div>
    </section>
    <section class="bsys-premium-overview" aria-labelledby="bsys-premium-overview-title">
      <div class="bsys-premium-table-header"><div><h2 id="bsys-premium-overview-title">Updater overview</h2><p>Items requiring setup or review appear first.</p></div><a class="bsys-premium-refresh" href="<?= e(url('/dashboard/system/auto-updater')) ?>" aria-label="Refresh updater readiness"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2 6"/><path d="M20 4v7h-7"/></svg>Refresh</a></div>
      <div class="bsys-premium-table-scroll"><table class="bsys-premium-table" data-premium-table>
        <thead><tr><th scope="col">Item</th><th scope="col">Status</th><th scope="col">Summary</th><th scope="col">Detail</th></tr></thead>
        <tbody>
        <?php foreach ($premiumRows as $row): ?>
          <tr class="bsys-premium-item" data-premium-item="<?= e($row['id']) ?>" data-priority="<?= (int) $row['priority'] ?>">
            <th scope="row"><span class="bsys-premium-item-name"><span class="bsys-premium-icon"><?= $premiumSvg($row['icon']) ?></span><span><?= e($row['label']) ?></span></span></th>
            <td><span class="bsys-premium-tag bsys-premium-tag--<?= e($row['tone']) ?>" <?= $row['id'] === 'license' ? 'data-premium-license-tag' : '' ?>><?php if ($row['id'] === 'license'): ?><span data-publisher-summary><?= e($row['state']) ?></span><?php else: ?><?= e($row['state']) ?><?php endif; ?></span></td>
            <td class="bsys-premium-summary" <?= $row['id'] === 'license' ? 'data-premium-license-summary' : '' ?>><?= e($row['summary']) ?><?php if ($row['id'] === 'license'): ?><span class="bsys-premium-sr" data-publisher-readiness-state><?= e($publisherState) ?></span><?php endif; ?></td>
            <td><button class="bsys-premium-detail-trigger" type="button" data-detail-target="detail-<?= e($row['id']) ?>" aria-controls="detail-<?= e($row['id']) ?>" aria-expanded="false">Detail <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button></td>
          </tr>
          <tr id="detail-<?= e($row['id']) ?>" class="bsys-premium-detail-row" hidden><td colspan="4"><div class="bsys-premium-detail-content"><strong><?= e($row['label']) ?></strong><p><?= e($row['detail']) ?></p><?php if ($row['id'] === 'updater' && $updaterFound): ?><a href="<?= e(url('/updater/')) ?>">Open guarded updater ↗</a><?php elseif ($row['id'] === 'domain'): ?><a href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">Request domain replacement ↗</a><?php endif; ?></div></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="bsys-premium-footer-actions" aria-label="Staged release inspection">
        <form method="post" action="<?= e(url('/dashboard/system/auto-updater/inspect-staged-release')) ?>" class="bsys-inline-action">
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <button class="bsys-button" type="submit" <?= !empty($systemReleaseReadiness['inspect_enabled']) ? '' : 'disabled title="Stage a signed release and provision the trusted public key first"' ?>>Inspect signed staged release</button>
        </form>
        <?php if (($systemReleaseInspection['status']??'')==='Signed package verified (not installed)'): ?><span class="bsys-state bsys-state--good" role="status">Signed package verified · not installed</span><?php endif; ?>
        <span class="bsys-note">Staged inspection only · never installs a package</span>
      </div>
    </section>

  <?php elseif ($systemSection === 'health'): ?>
    <?php $overallGood = ($systemSnapshot['overall'] ?? '') === 'operational'; ?>
    <div class="bsys-metrics" aria-label="Health status overview">
      <div class="bsys-metric"><span class="bsys-label">Customer-facing service status</span><strong class="bsys-metric-value"><?= e((string) ($systemSnapshot['label'] ?? 'Unavailable')) ?></strong><span class="bsys-metric-hint">Existing coarse service probe</span></div>
      <div class="bsys-metric"><span class="bsys-label">Services reported</span><strong class="bsys-metric-value"><?= count($systemServices) ?></strong><span class="bsys-metric-hint">From existing health service</span></div>
      <div class="bsys-metric"><span class="bsys-label">PHP runtime</span><strong class="bsys-metric-value"><?= e((string) ($systemEnvironment['php_version'] ?? 'Unknown')) ?></strong><span class="bsys-metric-hint">Current server runtime</span></div>
      <div class="bsys-metric"><span class="bsys-label">Storage access</span><strong class="bsys-metric-value"><?= e((string) ($systemEnvironment['storage'] ?? 'Not assessed')) ?></strong><span class="bsys-metric-hint">Directory writability check only</span></div>
    </div>
    <div class="bsys-layout">
      <section class="bsys-panel"><div class="bsys-section-title"><div><h2>Service health</h2><p><?= e((string) ($systemSnapshot['message'] ?? 'No service snapshot is available.')) ?></p></div><span class="bsys-state <?= $overallGood ? 'bsys-state--good' : 'bsys-state--warn' ?>"><span class="bsys-indicator"></span><?= $overallGood ? 'Operational' : 'Attention' ?></span></div>
        <div class="bsys-table-wrap"><table><thead><tr><th>Service</th><th>Coverage</th><th>Status</th><th>Checked</th></tr></thead><tbody>
          <?php foreach ($systemServices as $service): if (!is_array($service)) continue; ?><tr><td><span class="bsys-strong"><?= e((string) ($service['name'] ?? 'Service')) ?></span></td><td><?= e((string) ($service['description'] ?? 'Customer-facing service')) ?></td><td><span class="bsys-state <?= ($service['status'] ?? '') === 'operational' ? 'bsys-state--good' : 'bsys-state--warn' ?>"><span class="bsys-indicator"></span><?= e((string) ($service['label'] ?? 'Unknown')) ?></span></td><td><?= e($systemCheckedAt) ?></td></tr><?php endforeach; ?>
          <?php if (!$systemServices): ?><tr><td colspan="4">No measured service details are available.</td></tr><?php endif; ?>
        </tbody></table></div>
      </section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Runtime & storage</h2><p>Non-invasive current-process checks</p></div></div><div class="bsys-table-wrap"><table><tbody>
        <tr><td>PHP version</td><td><?= e((string) ($systemEnvironment['php_version'] ?? 'Unknown')) ?></td></tr>
        <tr><td>PHP interface</td><td><?= e((string) ($systemEnvironment['php_sapi'] ?? 'Unknown')) ?></td></tr>
        <tr><td>Application storage writable</td><td><?= e((string) ($systemEnvironment['storage'] ?? 'Not assessed')) ?></td></tr>
        <tr><td>Database connectivity</td><td>Included in existing data-service probe</td></tr>
        <tr><td>Cloud storage operations</td><td>Not tested</td></tr>
      </tbody></table></div></section>
      <section class="bsys-panel bsys-half"><div class="bsys-section-title"><div><h2>Additional integrations</h2><p>Checks that are not performed on this page</p></div></div><div class="bsys-overview-grid bsys-section-body">
        <?php foreach (['SMTP / email delivery','Payment gateways','Queues & schedulers','External API availability'] as $external): ?><div class="bsys-overview-item"><span class="bsys-label"><?= e($external) ?></span><strong>Not tested</strong><small>No outbound operation performed</small></div><?php endforeach; ?>
      </div></section>
      <section class="bsys-panel"><div class="bsys-section-title"><div><h2>Public service status</h2><p>Deliberately limited status without private infrastructure or credentials</p></div></div><div class="bsys-section-body"><div class="bsys-meter-status"><p class="bsys-note">The public health page exposes only coarse customer-facing service availability. It does not certify end-to-end login, mail, payments or backup restoration.</p><a class="bsys-button" href="<?= e(url('/health')) ?>" rel="noopener noreferrer" target="_blank">View public health ↗</a></div></div></section>
    </div>
  <?php endif; ?>
  <?php if ($systemSection !== 'auto-updater'): ?><aside class="bsys-readonly" role="note">Read-only administrative overview. License and support fields are displayed from existing local records when available; no private credentials are shown. No update, migration, outbound email or payment action is triggered by these pages.</aside><?php endif; ?>
</section>

<?php if ($systemSection === 'auto-updater'): ?><script defer src="<?= e(asset('js/barama-publisher-license-ux-v112.js') . '?v=113') ?>"></script><script defer src="<?= e(asset('js/barama-auto-updater-premium-v120.js') . '?v=121') ?>"></script><script defer src="<?= e(asset('js/barama-oneclick-status-v100.js') . '?v=101') ?>"></script><?php endif; ?>
