<?php
/** @var array<string,mixed> $location */
?>
<link rel="stylesheet" href="<?= e(asset('css/catalog-location-edit-v117a9.css') . '?v=117a9') ?>">
<section class="barama-location-edit-v117a9">
  <header class="barama-location-edit-v117a9__header">
    <div>
      <span class="eyebrow">ADMIN CONTROL CENTER</span>
      <h1>Edit Location</h1>
      <p>Update details for this existing location without changing its hierarchy or linked records.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/catalog')) ?>">Back to Locations</a>
  </header>
  <div data-workspace-notice hidden></div>
  <div class="barama-location-edit-v117a9__card">
    <div class="barama-location-edit-v117a9__title">
      <span class="barama-location-edit-v117a9__icon"><?= app_icon('map') ?></span>
      <div><strong><?= e((string)$location['name']) ?></strong><small>Record #<?= e((string)$location['id']) ?> · <?= e(ucwords(str_replace('_',' ',(string)$location['type']))) ?></small></div>
      <span class="barama-location-edit-v117a9__pill"><?= e(ucfirst((string)$location['status'])) ?></span>
    </div>
    <form action="<?= e(url('/dashboard/locations/' . (int)$location['id'])) ?>" method="post" data-workspace-form class="barama-location-edit-v117a9__form">
      <?= csrf_field() ?>
      <input type="hidden" name="revision" value="<?= e((string)$location['revision']) ?>">
      <div class="barama-location-edit-v117a9__grid">
        <label><span><?= app_icon('map-pin') ?> Location name *</span><input class="form-control" name="name" required minlength="2" maxlength="160" value="<?= e((string)$location['name']) ?>"></label>
        <div class="barama-location-edit-v117a9__readonly"><span>Country</span><strong><?= e((string)($location['country_code'] ?? '') ?: 'Not set') ?></strong></div>
        <div class="barama-location-edit-v117a9__readonly"><span>Location type</span><strong><?= e(ucwords(str_replace('_',' ',(string)$location['type']))) ?></strong></div>
        <div class="barama-location-edit-v117a9__readonly"><span>Parent location</span><strong><?= e((string)($location['parent_name'] ?? '') ?: 'No parent') ?></strong></div>
        <label><span><?= app_icon('map-pin') ?> Latitude</span><input class="form-control" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="<?= e((string)($location['latitude'] ?? '')) ?>" placeholder="Optional"></label>
        <label><span><?= app_icon('map-pin') ?> Longitude</span><input class="form-control" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="<?= e((string)($location['longitude'] ?? '')) ?>" placeholder="Optional"></label>
        <label><span><?= app_icon('list-ordered') ?> Sort order</span><input class="form-control" name="sort_order" type="number" step="1" required value="<?= e((string)$location['sort_order']) ?>"></label>
      </div>
      <p class="barama-location-edit-v117a9__note"><?= app_icon('shield') ?> Country, parent, type, and slug are protected. Changing them requires a separate hierarchy reconciliation to avoid breaking property and ISO reference relationships.</p>
      <div class="barama-location-edit-v117a9__footer">
        <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/catalog')) ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save Changes</button>
      </div>
    </form>
  </div>
</section>
