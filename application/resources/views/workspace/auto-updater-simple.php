<?php
/**
 * Customer-facing Auto Updater.
 *
 * The publisher handles license and source eligibility. The UI intentionally
 * exposes only a safe signed-release check until an independently verified,
 * rollback-capable one-click installer is available.
 */
declare(strict_types=1);

$feed = is_array($systemFeedResult ?? null) ? $systemFeedResult : [];
$ready = is_array($systemFeedReadiness ?? null) ? $systemFeedReadiness : [];
$licenseCheck = is_array($systemPublisherCheck ?? null) ? $systemPublisherCheck : [];
$status = (string) ($feed['status'] ?? 'not_checked');
if (!in_array($status, ['not_checked', 'available', 'eligible', 'ineligible', 'unavailable'], true)) {
    $status = 'not_checked';
}
$canCheck = !empty($ready['can_check']);
$installedVersion = (string) ($systemVersion ?? 'Unknown');
$newVersion = $status === 'available' ? (string) ($feed['version'] ?? '') : '';
$lastChecked = (string) ($feed['checked_at'] ?? 'Never');
$licenseRecorded = in_array((string) ($licenseCheck['state'] ?? ''), [
    'Active license verified', 'Installed license recognized',
], true);
$messages = [
    'not_checked' => [
        'title' => 'Keep your website up to date',
        'description' => 'Check securely for the latest Barama release. Your website stays unchanged until you approve an update.',
        'tag' => 'Ready to check',
    ],
    'eligible' => [
        'title' => 'Your website is up to date',
        'description' => 'The publisher confirmed your update eligibility. No newer signed release is available.',
        'tag' => 'Up to date',
    ],
    'available' => [
        'title' => 'A new version is available',
        'description' => 'A newer signed release has been verified. Automatic installation is not enabled yet; no files were downloaded or installed.',
        'tag' => 'Update found',
    ],
    'ineligible' => [
        'title' => 'Updates are not available',
        'description' => 'This installation does not currently have publisher update access. Your existing license and website are unchanged.',
        'tag' => 'Contact support',
    ],
    'unavailable' => [
        'title' => 'Unable to check right now',
        'description' => 'The update service could not be confirmed. Please try again later; no website files were changed.',
        'tag' => 'Try again',
    ],
];
$state = $messages[$status];
if (!$canCheck) {
    $state = [
        'title' => 'Update service is not ready',
        'description' => 'The publisher must finish configuring secure release checks. Your installed website continues working normally.',
        'tag' => 'Not connected',
    ];
}
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-auto-updater-simple-v130.css') . '?v=130') ?>">
<section class="barama-update-simple" aria-labelledby="barama-update-title">
  <header class="bau-heading">
    <div>
      <p class="bau-eyebrow">System / Auto Updater</p>
      <h1 id="barama-update-title">Auto Updater</h1>
      <p>One place to check for Barama updates and upgrades.</p>
    </div>
    <span class="bau-version">Installed v<?= e($installedVersion) ?></span>
  </header>

  <section class="bau-main" aria-labelledby="bau-state-title">
    <div class="bau-status-icon bau-status-icon--<?= e($canCheck ? $status : 'unavailable') ?>" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 11a8 8 0 1 1-2.4-5.7"/><path d="M20 4v6h-6"/><path d="m9 13 2 2 4-4"/>
      </svg>
    </div>
    <div class="bau-state-copy">
      <span class="bau-status bau-status--<?= e($canCheck ? $status : 'unavailable') ?>"><?= e($state['tag']) ?></span>
      <h2 id="bau-state-title"><?= e($state['title']) ?></h2>
      <p><?= e($state['description']) ?></p>
      <?php if ($status === 'available' && $newVersion !== '' && $canCheck): ?>
        <div class="bau-version-comparison" aria-label="Available software version">
          <div><span>Installed</span><strong>v<?= e($installedVersion) ?></strong></div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          <div><span>Available</span><strong>v<?= e($newVersion) ?></strong></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($systemFeedFlash)): ?>
        <p class="bau-feedback" role="status" aria-live="polite"><?= e((string) $systemFeedFlash) ?></p>
      <?php endif; ?>
      <div class="bau-actions">
        <form method="post" action="<?= e(url('/dashboard/system/auto-updater/check-updates')) ?>">
          <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
          <button class="bau-button bau-button--primary" type="submit" <?= $canCheck ? '' : 'disabled' ?>>Check for updates</button>
        </form>
        <?php if ($status === 'available' && $canCheck): ?>
          <button class="bau-button bau-button--disabled" type="button" disabled title="Automatic installation is not enabled">Update now</button>
        <?php endif; ?>
      </div>
      <p class="bau-footnote">Last checked: <?= e($lastChecked) ?> · Checking never downloads or installs a package.</p>
    </div>
  </section>

  <div class="bau-bottom">
    <div class="bau-info">
      <span class="bau-info-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
      </span>
      <div>
        <strong><?= $licenseRecorded ? 'License recognized' : 'License confirmation pending' ?></strong>
        <p>Signed updates are checked through Ton Samith License Manager. Eligible CodeCanyon purchases use the publisher's license verification.</p>
      </div>
    </div>
    <div class="bau-links">
      <a href="<?= e(url('/updater/')) ?>">Manual upgrade</a>
      <a href="https://tonsamith.com/contact" target="_blank" rel="noopener noreferrer">Get support ↗</a>
    </div>
  </div>
</section>
