<?php
/**
 * BARAMA_COMPLETED_UPDATE_RECEIPT_V101
 * Read-only previous-update presentation. Never starts a job or grants updates.
 * Version-neutral: do not pin customer installations to the v0.9.24 pilot.
 */
$bauVerifiedHistory = (static function () use ($uStaging, $uVersion, $uVersionAvailable): ?array {
    if ($uStaging || $uVersionAvailable || !defined('BASE_PATH')
        || !class_exists(\Barama\Services\Updates\PortableCustomerUpdateV1::class)) {
        return null;
    }
    try {
        $root = (string) BASE_PATH;
        if ($root === '' || !is_dir($root) || is_link($root)) return null;
        $state = \Barama\Services\Updates\PortableCustomerUpdateV1::status();
        if (($state['state'] ?? '') !== 'completed_file_only'
            || ($state['pending'] ?? true) !== false
            || (string) ($state['version'] ?? '') !== (string) $uVersion) return null;

        $vault = dirname($root) . '/.barama-private-updates-'
            . substr(hash('sha256', $root), 0, 18);
        if (!is_dir($vault) || is_link($vault)
            || (fileperms($vault) & 0077) !== 0
            || is_file($vault . '/current.json') || is_link($vault . '/current.json')
            || is_file($vault . '/active.json') || is_link($vault . '/active.json')) return null;

        $path = $vault . '/last.json';
        if (!is_file($path) || is_link($path)
            || filesize($path) < 1 || filesize($path) > 8192) return null;
        $receipt = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($receipt)) return null;

        $from = (string) ($receipt['installed_version'] ?? '');
        $to = (string) ($receipt['target_version'] ?? '');
        $sha = (string) ($receipt['archive_sha256'] ?? '');
        if (($receipt['schema'] ?? '') !== 'barama-portable-customer-current-v1'
            || ($receipt['state'] ?? '') !== 'completed_file_only'
            || !preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $from)
            || !preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $to)
            || !preg_match('/^[a-f0-9]{64}$/D', $sha)
            || !hash_equals((string) $uVersion, $to)
            || version_compare($to, $from, '<=')) return null;

        $installed = $root . '/VERSION';
        if (!is_file($installed) || is_link($installed)
            || filesize($installed) > 128
            || trim((string) file_get_contents($installed)) !== $to) return null;

        return ['previous' => $from, 'installed' => $to];
    } catch (\Throwable) {
        return null; // Never turn missing/untrusted history into a success badge.
    }
})();
if ($bauVerifiedHistory !== null):
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-updater-completed-receipt-v101.css') . '?v=101') ?>">
<section class="bau-completion-history" data-bau-verified-completion="<?= e($bauVerifiedHistory['installed']) ?>" aria-label="Completed update receipt" role="status">
  <div class="bau-completion-head">
    <div>
      <span class="bau-completion-eyebrow">Verified previous transaction</span>
      <h2>Update installed successfully</h2>
      <p>File-only update recorded from <strong>v<?= e($bauVerifiedHistory['previous']) ?></strong> to <strong>v<?= e($bauVerifiedHistory['installed']) ?></strong>. The private completion receipt matches the current installed version.</p>
    </div>
    <span class="bau-completion-status">&#10003; Completed</span>
  </div>
  <ol class="bau-completion-stages" aria-label="Completed update stages">
    <li><span>&#10003;</span> Entitlement confirmed</li>
    <li><span>&#10003;</span> Signed release discovered</li>
    <li><span>&#10003;</span> Release verified</li>
    <li><span>&#10003;</span> ZIP downloaded</li>
    <li><span>&#10003;</span> Files inspected</li>
    <li><span>&#10003;</span> Installation recorded</li>
    <li><span>&#10003;</span> Completion recorded</li>
  </ol>
  <p class="bau-completion-foot">This is a previously completed <strong>file-only</strong> transaction, not a newly discovered update. Backup restoration has not been independently retested. Check for updates to find future releases.</p>
</section>
<script defer src="<?= e(asset('js/barama-updater-completed-receipt-v101.js') . '?v=101') ?>"></script>
<?php endif; ?>
