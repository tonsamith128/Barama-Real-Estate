<?php
/* BARAMA_GLOBAL_LOCATION_CASCADE_V110 */
$fieldName = trim((string) ($fieldName ?? 'location_id')) ?: 'location_id';
$currentLocationId = (int) ($currentLocationId ?? 0);
$label = trim((string) ($label ?? 'Location')) ?: 'Location';
$emptyLabel = trim((string) ($emptyLabel ?? 'No location')) ?: 'No location';
$required = !empty($required);
$spanClass = !isset($spanTwo) || !empty($spanTwo) ? ' workspace-form__span-2' : '';
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
    <small class="barama-location-cascade__hint" data-location-hint>Select a country, then choose the deepest available area. The final area is saved as the canonical location.</small>
</div>
