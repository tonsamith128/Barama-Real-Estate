<?php
/* BARAMA_PUBLIC_LOCATION_PICKER_V115 */
$pickerName = trim((string) ($name ?? 'location'));
$pickerName = $pickerName !== '' ? $pickerName : 'location';
$pickerValue = trim((string) ($value ?? ''));
$pickerLabel = trim((string) ($label ?? 'All locations'));
$pickerLabel = $pickerLabel !== '' ? $pickerLabel : 'All locations';
$pickerId = 'barama-location-' . substr(hash('sha256', $pickerName . '|' . $pickerValue . '|' . uniqid('', true)), 0, 12);
?>
<?= barama_css_once('frontend') ?>
<div class="barama-public-location-picker" data-public-location-picker data-location-endpoint="<?= e(url('/location-options')) ?>" data-selected-location="<?= e($pickerValue) ?>" data-empty-label="<?= e($pickerLabel) ?>">
    <input type="hidden" name="<?= e($pickerName) ?>" value="<?= e($pickerValue) ?>" data-public-location-value>
    <button class="barama-public-location-picker__trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?= e($pickerId) ?>-quick" data-public-location-trigger>
        <span class="barama-public-location-picker__icon"><?= app_icon('map') ?></span>
        <span class="barama-public-location-picker__label" data-public-location-label><?= e($pickerLabel) ?></span>
        <span class="barama-public-location-picker__chevron" aria-hidden="true">⌄</span>
    </button>

    <div class="barama-public-location-picker__quick" id="<?= e($pickerId) ?>-quick" role="dialog" aria-modal="false" aria-label="Choose country" hidden data-public-location-quick>
        <label class="barama-public-location-picker__search">
            <span><?= app_icon('search') ?></span>
            <input type="search" placeholder="Search countries…" autocomplete="off" data-public-location-country-search>
        </label>
        <div class="barama-public-location-picker__quick-list" role="listbox" aria-label="Enabled countries" data-public-location-country-list></div>
    </div>

    <div class="barama-public-location-picker__advanced" role="dialog" aria-modal="false" aria-label="Advanced location search" hidden data-public-location-advanced>
        <div class="barama-public-location-picker__head">
            <div>
                <strong>Advanced location</strong>
                <small>Choose a country, then refine the administrative area.</small>
            </div>
            <button type="button" class="barama-public-location-picker__close" aria-label="Close advanced location search" data-public-location-close>×</button>
        </div>
        <div class="barama-public-location-picker__advanced-body">
            <label class="barama-public-location-picker__field">
                <span>Country</span>
                <select data-public-location-country-select></select>
            </label>
            <div data-public-location-levels></div>
            <div class="barama-public-location-picker__status" data-public-location-status aria-live="polite"></div>
        </div>
        <div class="barama-public-location-picker__actions">
            <button type="button" class="barama-public-location-picker__clear" data-public-location-clear>Clear</button>
            <button type="button" class="barama-public-location-picker__done" data-public-location-done>Apply</button>
        </div>
    </div>
</div>
<?= barama_js_once('frontend') ?><script type="application/x-barama-module" data-barama-module="public-location-picker"></script>
