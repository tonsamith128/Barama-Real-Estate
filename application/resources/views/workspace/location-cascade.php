<?php
/* BARAMA_GLOBAL_LOCATION_CASCADE_V110 */
$fieldName = trim((string) ($fieldName ?? 'location_id')) ?: 'location_id';
$currentLocationId = (int) ($currentLocationId ?? 0);
$label = trim((string) ($label ?? 'Location')) ?: 'Location';
$emptyLabel = trim((string) ($emptyLabel ?? 'No location')) ?: 'No location';
$required = !empty($required);
$spanClass = !isset($spanTwo) || !empty($spanTwo) ? ' workspace-form__span-2' : '';
$mapboxEnabled = (new \Barama\Repositories\LocationProviderRepository())->activeProvider() === 'mapbox';
?>
<div
    class="barama-location-cascade<?= e($spanClass) ?>"
    data-location-cascade
    data-location-endpoint="<?= e(url('/location-options')) ?>"
    data-selected-location="<?= e($currentLocationId > 0 ? (string) $currentLocationId : '') ?>"
>
    <input type="hidden" name="<?= e($fieldName) ?>" value="<?= e($currentLocationId > 0 ? (string) $currentLocationId : '') ?>" data-location-value>
    <div class="barama-location-cascade__head">
        <span><?= app_icon('map') ?></span>
        <div><strong><?= e($label) ?></strong><small>Country → administrative area → locality</small></div>
    </div>
    <div class="barama-location-cascade__grid" data-location-selects>
        <label>
            <span>Country</span>
            <select class="form-select" data-location-country <?= $required ? 'required' : '' ?>>
                <option value=""><?= e($emptyLabel) ?></option>
            </select>
        </label>
    </div>
    <?php if ($mapboxEnabled): ?>
        <div class="barama-location-provider-v117a7__search" data-location-mapbox data-search-endpoint="<?= e(url('/location-provider/suggest')) ?>">
            <label><span>Mapbox address search</span><input class="form-control" type="search" maxlength="120" minlength="3" placeholder="Search a place or street address" data-mapbox-q></label>
            <button class="btn btn-secondary" type="button" data-mapbox-search><?= app_icon('search') ?> Search Mapbox</button>
            <div data-mapbox-results class="barama-location-provider-v117a7__results" aria-live="polite"></div>
            <small>Suggestions are temporary. Choose the matching Barama administrative area below before saving.</small>
        </div>
    <?php endif; ?>
    <small class="barama-location-cascade__hint" data-location-hint>Select a country, then choose the deepest available area. The final area is saved as the canonical location.</small>
</div>

<?php if ($mapboxEnabled): ?>
<link rel="stylesheet" href="<?= e(asset('css/location-provider-v117a7.css') . '?v=117a7') ?>">
<script src="<?= e(asset('js/location-provider-v117a7.js') . '?v=117a7') ?>" defer></script>
<?php endif; ?>
