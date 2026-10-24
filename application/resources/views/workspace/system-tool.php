<?php
/** BARAMA_SYSTEM_TOOL_V100 — all mutations handled by authenticated, confirmed routes. */
$toolTab = in_array((string)($toolTab ?? ''), ['importer','exporter','backup','restore'], true) ? $toolTab : 'importer';
$toolDatasets = is_array($toolDatasets ?? null) ? $toolDatasets : [];
$toolBackups = is_array($toolBackups ?? null) ? $toolBackups : [];
$toolStorageReady = !empty($toolStorageReady);
$toolFlash = is_array($toolFlash ?? null) ? $toolFlash : null;
$toolBase = '/dashboard/system/tool';
?>
<link rel="stylesheet" href="<?= e(asset('css/barama-system-tool-v100.css') . '?v=100') ?>">
<section class="btool" id="barama-system-tool" data-passcode-enabled="<?= !empty($toolPasscodeEnabled) ? '1' : '0' ?>" aria-labelledby="btool-heading">
  <div class="btool-breadcrumb">Administration <span aria-hidden="true">/</span> System <span aria-hidden="true">/</span> Tool</div>
  <header class="btool-header">
    <div>
      <span class="btool-eyebrow">System utilities</span>
      <h1 id="btool-heading">Tool</h1>
      <p>Import and export marketplace data. Protect, verify, and restore your database with controlled snapshots.</p>
    </div>
    <span class="btool-status"><span aria-hidden="true">●</span> Administrator tools</span>
  </header>

  <?php if ($toolFlash !== null): ?>
    <div class="btool-flash btool-flash--<?= ($toolFlash['tone'] ?? '') === 'success' ? 'success' : 'error' ?>" role="status">
      <?= e((string) ($toolFlash['message'] ?? 'Operation completed.')) ?>
    </div>
  <?php endif; ?>
  <?php if ((string)($toolError ?? '') !== ''): ?>
    <div class="btool-flash btool-flash--error" role="alert"><?= e((string)$toolError) ?></div>
  <?php endif; ?>

  <div class="btool-shell">
    <nav class="btool-tabs" aria-label="Tool functions">
      <?php foreach ([
          'importer' => ['↧', 'Importer'],
          'exporter' => ['↥', 'Exporter'],
          'backup' => ['▤', 'Backup'],
          'restore' => ['↺', 'Restore'],
      ] as $key => $item): ?>
        <a href="<?= e(url($toolBase . '?tab=' . $key)) ?>"
           class="btool-tab <?= $toolTab === $key ? 'is-active' : '' ?>"
           <?= $toolTab === $key ? 'aria-current="page"' : '' ?>>
          <span class="btool-tab-icon" aria-hidden="true"><?= e($item[0]) ?></span>
          <?= e($item[1]) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="btool-content">
      <?php if ($toolTab === 'importer'): ?>
        <div class="btool-content-head">
          <div><span class="btool-eyebrow">Data migration</span><h2>Importer</h2>
            <p>Validate a CSV file and insert new rows into a selected marketplace table. Existing records are never silently overwritten.</p></div>
          <span class="btool-chip">Insert only</span>
        </div>
        <div class="btool-two">
          <section class="btool-card">
            <h3>Import CSV data</h3>
            <p>Match the database column names in the first CSV row. Preview checks the same database rules without committing changes.</p>
            <form action="<?= e(url($toolBase . '/import')) ?>" method="post" enctype="multipart/form-data" data-btool-protected>
              <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
              <label for="btool-import-table">Destination dataset</label>
              <select name="dataset" id="btool-import-table" required>
                <?php foreach ($toolDatasets as $table): ?>
                  <option value="<?= e($table) ?>"><?= e(ucwords(str_replace('_',' ',$table))) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="btool-import-file">CSV file</label>
              <input type="file" name="file" id="btool-import-file" accept=".csv,text/csv" required>
              <p class="btool-help">UTF-8 CSV · up to 5 MB · up to 5,000 rows · all rows validate or none are imported.</p>
              <div class="btool-actions">
                <button class="btool-btn btool-btn-secondary" type="submit" name="mode" value="preview" <?= !$toolDatasets ? 'disabled' : '' ?>>Validate / Preview</button>
                <button class="btool-btn btool-btn-primary" type="submit" name="mode" value="import" <?= !$toolDatasets ? 'disabled' : '' ?>>Import records</button>
              </div>
            </form>
          </section>
          <aside class="btool-guide">
            <div class="btool-step">01 <strong>Choose your data</strong><span>Locations, amenities, companies, projects or properties available in this installation</span></div>
            <div class="btool-step">02 <strong>Validate first</strong><span>Schema, row widths, unique keys and foreign keys are checked transactionally</span></div>
            <div class="btool-step">03 <strong>Commit explicitly</strong><span>Change passcode required when enabled; no silent overwrite or partial import</span></div>
          </aside>
        </div>
      <?php elseif ($toolTab === 'exporter'): ?>
        <div class="btool-content-head"><div><span class="btool-eyebrow">Portable data</span><h2>Exporter</h2>
          <p>Download a CSV copy of a marketplace dataset for reporting, review, or migration to another installation.</p></div><span class="btool-chip">CSV</span></div>
        <section class="btool-card">
          <h3>Export marketplace records</h3>
          <p>Choose a dataset. Exported files contain the table's data columns; treat them as private business data.</p>
          <form action="<?= e(url($toolBase . '/export')) ?>" method="get">
            <label for="btool-export-table">Dataset</label>
            <select name="dataset" id="btool-export-table" required>
              <?php foreach ($toolDatasets as $table): ?>
                <option value="<?= e($table) ?>"><?= e(ucwords(str_replace('_',' ',$table))) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="btool-actions"><button class="btool-btn btool-btn-primary" type="submit" <?= !$toolDatasets ? 'disabled' : '' ?>>Download CSV</button></div>
          </form>
        </section>
      <?php elseif ($toolTab === 'backup'): ?>
        <div class="btool-content-head"><div><span class="btool-eyebrow">Recovery readiness</span><h2>Backup</h2>
          <p>Create a private logical database snapshot, then download an integrity-checked ZIP for safe off-server storage.</p></div>
          <span class="btool-chip"><?= $toolStorageReady ? 'Private storage ready' : 'Private storage not provisioned' ?></span></div>
        <section class="btool-card">
          <h3>Create database backup</h3>
          <p>The backup captures all supported InnoDB database tables. It does <strong>not</strong> include site code, media uploads, or cloud storage.</p>
          <form method="post" action="<?= e(url($toolBase . '/backup')) ?>" data-btool-protected>
            <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
            <div class="btool-actions"><button class="btool-btn btool-btn-primary" type="submit">Create verified snapshot</button></div>
          </form>
        </section>
        <section class="btool-card btool-card-list">
          <h3>Available database snapshots</h3>
          <?php if (!$toolBackups): ?><p class="btool-empty">No verified snapshots found in private storage.</p>
          <?php else: ?>
          <div class="btool-table-wrap"><table><thead><tr><th>Snapshot ID</th><th>Created</th><th>ZIP size</th><th></th></tr></thead><tbody>
          <?php foreach ($toolBackups as $snapshot): ?>
            <tr><td><code><?= e((string)$snapshot['id']) ?></code></td><td><?= e((string)$snapshot['created_at']) ?></td>
              <td><?= e(number_format(((int)$snapshot['size'])/1024/1024, 2)) ?> MB</td>
              <td><a class="btool-link" href="<?= e(url($toolBase . '/download?id=' . rawurlencode((string)$snapshot['id']))) ?>">Download ZIP</a></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <div class="btool-content-head"><div><span class="btool-eyebrow">Controlled recovery</span><h2>Restore</h2>
          <p>Restore from an existing verified database snapshot created on this installation. This replaces current database rows.</p></div>
          <span class="btool-chip btool-chip-danger">High impact</span></div>
        <div class="btool-warning">
          <strong>Database restoration only.</strong> Media and website files are not restored. All database data may be replaced. An exact-schema check and a fresh safety snapshot are required before any changes.
        </div>
        <section class="btool-card">
          <h3>Restore a verified snapshot</h3>
          <form method="post" action="<?= e(url($toolBase . '/restore')) ?>" data-btool-protected data-btool-restore>
            <input type="hidden" name="_token" value="<?= e(\Barama\Core\Csrf::token()) ?>">
            <label for="btool-restore-snapshot">Restore point</label>
            <select name="backup_id" id="btool-restore-snapshot" required>
              <option value="">Select a verified snapshot</option>
              <?php foreach ($toolBackups as $snapshot): ?>
                <option value="<?= e((string)$snapshot['id']) ?>"><?= e((string)$snapshot['id']) ?> — <?= e((string)$snapshot['created_at']) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="btool-confirmation">Type <strong>RESTORE DATABASE</strong> to confirm</label>
            <input type="text" id="btool-confirmation" name="confirmation" autocomplete="off" spellcheck="false" placeholder="RESTORE DATABASE" required>
            <p class="btool-help">Restore only during a maintenance window with an independent off-server copy. There is no automatic file or media rollback.</p>
            <div class="btool-actions"><button class="btool-btn btool-btn-danger" type="submit" <?= !$toolBackups ? 'disabled' : '' ?>>Verify and restore database</button></div>
          </form>
        </section>
      <?php endif; ?>
    </div>
  </div>
  <footer class="btool-footnote">Private, administrator-only tools · CSRF and Change Confirmation enforced for modifications · database operations fail closed on validation errors.</footer>
</section>

<dialog class="btool-dialog" id="btool-passcode-dialog" aria-labelledby="btool-passcode-title">
  <div class="btool-dialog-inner">
    <div class="btool-dialog-symbol" aria-hidden="true">◇</div>
    <h2 id="btool-passcode-title">Confirm this change</h2>
    <p>Enter your existing six-digit Change Confirmation passcode if enabled. It is checked securely and never saved on this page.</p>
    <label for="btool-passcode">Change Confirmation passcode</label>
    <input id="btool-passcode" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" placeholder="6-digit passcode">
    <p id="btool-passcode-feedback" class="btool-dialog-feedback" role="alert"></p>
    <div class="btool-actions"><button class="btool-btn btool-btn-secondary" id="btool-passcode-cancel" type="button">Cancel</button>
      <button class="btool-btn btool-btn-primary" id="btool-passcode-submit" type="button">Confirm action</button></div>
  </div>
</dialog>
<noscript><p class="btool-help">Actions protected by Change Confirmation require JavaScript to submit the passcode grant.</p></noscript>
<script defer src="<?= e(asset('js/barama-system-tool-v100.js') . '?v=100') ?>"></script>
