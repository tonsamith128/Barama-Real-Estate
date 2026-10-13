<?php
/* BARAMA_GLOBAL_LOCATIONS_V110 */
$locationCountries = is_array($locationCountries ?? null) ? $locationCountries : [];
$locationSummary = is_array($locationSummary ?? null) ? $locationSummary : [];
$locationMode = in_array(($locationMode ?? 'selected'), ['selected', 'global'], true)
    ? (string) $locationMode
    : 'selected';
$defaultCountry = (string) ($locationSummary['default_country'] ?? '');
$hierarchyRecords = (int) ($locationSummary['hierarchy_records'] ?? 0);
$packCountries = (int) ($locationSummary['pack_countries'] ?? 0);
?>

<section class="barama-location-manager" data-global-location-manager>
    <header class="barama-location-manager__intro">
        <div>
            <span class="eyebrow">GLOBAL LOCATION MANAGEMENT</span>
            <h2>Countries & location hierarchy</h2>
            <p>Choose where this marketplace operates. Existing properties, projects, companies and leads keep their current location links even when a country is disabled.</p>
        </div>
        <a class="btn btn-secondary" href="<?= e(url('/dashboard/catalog')) ?>">
            <?= app_icon('map') ?> Manage hierarchy
        </a>
    </header>

    <div class="barama-location-stats" aria-label="Location catalog summary">
        <article><span><?= app_icon('globe') ?></span><strong><?= e((string) ($locationSummary['countries'] ?? count($locationCountries))) ?></strong><small>ISO countries</small></article>
        <article><span><?= app_icon('check') ?></span><strong><?= e((string) ($locationSummary['enabled_countries'] ?? 0)) ?></strong><small>Enabled countries</small></article>
        <article><span><?= app_icon('map') ?></span><strong><?= e(number_format($hierarchyRecords)) ?></strong><small>ISO subdivisions</small></article>
        <article><span><?= app_icon('home') ?></span><strong><?= e($defaultCountry !== '' ? $defaultCountry : '—') ?></strong><small>Default country</small></article>
    </div>

    <?php $providerState = is_array($locationProvider ?? null) ? $locationProvider : []; ?>
    <link rel="stylesheet" href="<?= e(asset('css/location-provider-v117a7.css') . '?v=117a7') ?>">
    <section class="barama-location-provider-v117a7" aria-labelledby="barama-location-provider-title">
        <div class="barama-location-provider-v117a7__head">
            <span class="eyebrow">ADMIN CONFIGURATION</span>
            <h3 id="barama-location-provider-title">Location Provider</h3>
            <p>Choose the search source used by location forms. This setting is visible only to authorized administrators.</p>
        </div>
        <form method="post" action="<?= e(url('/dashboard/settings/locations')) ?>" class="barama-location-provider-v117a7__options">
            <?= csrf_field() ?>
            <input type="hidden" name="location_action" value="provider_select">
            <label class="barama-location-provider-v117a7__choice">
                <input type="radio" name="location_provider" value="simple" <?= ($providerState['provider'] ?? 'simple') === 'simple' ? 'checked' : '' ?>>
                <span class="barama-location-provider-v117a7__icon"><?= app_icon('map') ?></span>
                <span><strong>Simple Location</strong><small>Use Barama's existing location hierarchy and database. No API required.</small></span>
            </label>
            <label class="barama-location-provider-v117a7__choice">
                <input type="radio" name="location_provider" value="mapbox" <?= ($providerState['provider'] ?? '') === 'mapbox' ? 'checked' : '' ?> <?= empty($providerState['tested']) ? 'disabled' : '' ?>>
                <span class="barama-location-provider-v117a7__icon"><?= app_icon('globe') ?></span>
                <span><strong>Mapbox Location</strong><small>Offer live address search through Mapbox Geocoding. Requires a tested API connection.</small></span>
            </label>
            <button type="submit" class="btn btn-primary"><?= app_icon('check') ?> Save Location Provider</button>
        </form>
        <div class="barama-location-provider-v117a7__credentials">
            <div><strong>Mapbox connection</strong><p>Token is encrypted using this installation's APP_KEY and is never displayed or included in page responses.</p>
                <small><?= !empty($providerState['has_token']) ? 'Token stored' : 'Token not configured' ?> · <?= !empty($providerState['tested']) ? 'Connection tested' : 'Connection not tested' ?></small>
            </div>
            <form action="<?= e(url('/dashboard/settings/locations')) ?>" method="post" class="barama-location-provider-v117a7__token">
                <?= csrf_field() ?>
                <input type="hidden" name="location_action" value="provider_token">
                <label><span>Mapbox API access token</span><input name="mapbox_token" type="password" minlength="12" maxlength="512" autocomplete="new-password" required placeholder="Enter a new Mapbox token"></label>
                <button class="btn btn-secondary" type="submit">Save Token</button>
            </form>
            <form action="<?= e(url('/dashboard/settings/locations')) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="location_action" value="provider_test">
                <button class="btn btn-secondary" type="submit" <?= empty($providerState['has_token']) ? 'disabled' : '' ?>><?= app_icon('check') ?> Test Connection</button>
            </form>
            <small>Mapbox suggestions are temporary and not saved to the canonical location database. Continue selecting a Barama administrative area when saving a property.</small>
        </div>
    </section>

    <section class="barama-location-scope-card">
        <div class="barama-location-scope-card__copy">
            <span class="barama-location-scope-card__icon"><?= app_icon('globe') ?></span>
            <div>
                <h3>Marketplace location scope</h3>
                <p>Selected Countries keeps forms focused on enabled markets. Global mode makes the complete country catalog available while preserving your selected-country preferences.</p>
            </div>
        </div>
        <form action="<?= e(url('/dashboard/settings/locations')) ?>" method="post" class="barama-location-mode-form">
            <?= csrf_field() ?>
            <input type="hidden" name="location_action" value="mode">
            <label class="barama-location-choice <?= $locationMode === 'selected' ? 'is-active' : '' ?>">
                <input type="radio" name="location_mode" value="selected" <?= $locationMode === 'selected' ? 'checked' : '' ?>>
                <span><?= app_icon('map') ?></span>
                <strong>Selected Countries</strong>
                <small>Only countries switched ON are available to the marketplace.</small>
            </label>
            <label class="barama-location-choice <?= $locationMode === 'global' ? 'is-active' : '' ?>">
                <input type="radio" name="location_mode" value="global" <?= $locationMode === 'global' ? 'checked' : '' ?>>
                <span><?= app_icon('globe') ?></span>
                <strong>Global</strong>
                <small>All countries are available. Country switches remain saved for local-mode use.</small>
            </label>
            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save location scope</button>
        </form>
    </section>


    <section class="barama-location-pack-card">
        <div class="barama-location-pack-card__icon"><?= app_icon('map') ?></div>
        <div class="barama-location-pack-card__copy">
            <span class="eyebrow">GLOBAL HIERARCHY PACK</span>
            <h3>ISO 3166-2 administrative areas</h3>
            <p><?= e(number_format($hierarchyRecords)) ?> governed subdivision records are indexed across <?= e(number_format($packCountries)) ?> country catalogs. Existing Barama locations were preserved and matched where names already existed.</p>
        </div>
        <div class="barama-location-pack-card__status"><strong>Installed</strong><small>v1.1.0</small></div>
        <a class="btn btn-secondary" href="<?= e(url('/dashboard/catalog')) ?>"><?= app_icon('map') ?> Add deeper places</a>
    </section>

    <section class="barama-location-countries">
        <div class="barama-location-countries__head">
            <div>
                <h3>Country availability</h3>
                <p>Switch countries ON or OFF without deleting their existing hierarchy or record relationships.</p>
            </div>
            <div class="barama-location-tools">
                <label class="barama-location-search">
                    <?= app_icon('search') ?>
                    <input type="search" placeholder="Search country or ISO code" autocomplete="off" data-country-search>
                </label>
                <select aria-label="Filter country status" data-country-status-filter>
                    <option value="all">All countries</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
        </div>

        <div class="barama-location-table-wrap">
            <table class="barama-location-table">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>ISO</th>
                        <th>Places</th>
                        <th>Catalog</th>
                        <th>Default</th>
                        <th>Availability</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locationCountries as $country): ?>
                        <?php
                        $code = strtoupper((string) ($country['country_code'] ?? ''));
                        $name = (string) ($country['name'] ?? $code);
                        $enabled = (int) ($country['enabled'] ?? 0) === 1;
                        $isDefault = (int) ($country['is_default'] ?? 0) === 1;
                        $places = (int) ($country['total_places'] ?? 0);
                        $activePlaces = (int) ($country['active_places'] ?? 0);
                        $dataStatus = (string) ($country['data_status'] ?? 'catalog_only');
                        ?>
                        <tr
                            data-country-row
                            data-country-name="<?= e(strtolower($name . ' ' . $code . ' ' . (string) ($country['iso3'] ?? ''))) ?>"
                            data-country-status="<?= $enabled ? 'enabled' : 'disabled' ?>"
                        >
                            <td>
                                <div class="barama-country-name">
                                    <span class="barama-country-code-mark"><?= e($code) ?></span>
                                    <span><strong><?= e($name) ?></strong><small><?= e((string) ($country['native_name'] ?? '')) ?></small></span>
                                </div>
                            </td>
                            <td><span class="barama-location-code"><?= e($code) ?></span> <small><?= e((string) ($country['iso3'] ?? '')) ?></small></td>
                            <td><strong><?= $places ?></strong><small><?= $activePlaces ?> active</small></td>
                            <td>
                                <span class="barama-location-data-status <?= $places > 0 ? 'has-tree' : '' ?>">
                                    <?= $dataStatus === 'iso_hierarchy' ? 'ISO hierarchy' : ($places > 0 ? 'Custom hierarchy' : 'Country only') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isDefault): ?>
                                    <span class="barama-location-default"><?= app_icon('check') ?> Default</span>
                                <?php else: ?>
                                    <form action="<?= e(url('/dashboard/settings/locations')) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="location_action" value="default_country">
                                        <input type="hidden" name="country_code" value="<?= e($code) ?>">
                                        <button class="barama-location-text-action" type="submit">Set default</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="<?= e(url('/dashboard/settings/locations')) ?>" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="location_action" value="country_status">
                                    <input type="hidden" name="country_code" value="<?= e($code) ?>">
                                    <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
                                    <button
                                        class="barama-country-toggle <?= $enabled ? 'is-on' : '' ?>"
                                        type="submit"
                                        role="switch"
                                        aria-checked="<?= $enabled ? 'true' : 'false' ?>"
                                        aria-label="<?= e(($enabled ? 'Disable ' : 'Enable ') . $name) ?>"
                                        <?= $isDefault && $enabled ? 'title="Choose another default country before disabling this country."' : '' ?>
                                    >
                                        <span></span><strong><?= $enabled ? 'ON' : 'OFF' ?></strong>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a class="barama-location-row-action" href="<?= e(url('/dashboard/catalog?country=' . rawurlencode($code))) ?>" title="Manage hierarchy for <?= e($name) ?>">
                                    <?= app_icon('map') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-country-empty hidden>
                        <td colspan="7" class="barama-location-empty">No countries match this search.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="barama-location-note">
            <?= app_icon('shield') ?>
            <span><strong>Safe availability control.</strong> Switching a country OFF never deletes locations or changes existing Property, Project, Company, Lead, or Saved Search relationships.</span>
        </footer>
    </section>
</section>
<script src="<?= e(asset('js/admin-global-locations-v110.js') . '?v=110') ?>" defer></script>
