<?php

declare(strict_types=1);

$humanize = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', trim((string) $value)));

$emailVerified = !empty($accountUser['email_verified_at']);

$formatPrice = static function (array $item): string {
    if (($item['price'] ?? null) === null) {
        return 'Price on request';
    }

    $price = strtoupper((string) ($item['currency'] ?? 'USD'))
        . ' ' . number_format((float) $item['price'], 0);

    if (($item['transaction_type'] ?? '') === 'rent' && !empty($item['pricing_period'])) {
        $price .= ' / ' . ucwords(str_replace('_', ' ', (string) $item['pricing_period']));
    }

    return $price;
};

// BARAMA_CUSTOMER_ACTIVITY_VIEW_V150
$activitySearch = (string) ($activitySearch ?? '');
$activityFilters = is_array($activityFilters ?? null) ? $activityFilters : [];
$activitySort = (string) ($activitySort ?? '');
$activitySortOptions = is_array($activitySortOptions ?? null) ? $activitySortOptions : [];
$activityStatusOptions = is_array($activityStatusOptions ?? null) ? $activityStatusOptions : [];
$activityHasFilters = $activitySearch !== ''
    || (string) ($activityFilters['offer'] ?? '') !== ''
    || (string) ($activityFilters['type'] ?? '') !== ''
    || (string) ($activityFilters['status'] ?? '') !== '';

// BARAMA_CUSTOMER_PERSONALIZATION_VIEW_V160
$personalizationSearch = (string) ($personalizationSearch ?? '');
$personalizationFilters = is_array($personalizationFilters ?? null) ? $personalizationFilters : [];
$personalizationSort = (string) ($personalizationSort ?? '');
$personalizationHasFilters = $personalizationSearch !== ''
    || (string) ($personalizationFilters['state'] ?? '') !== ''
    || (string) ($personalizationFilters['category'] ?? '') !== ''
    || (string) ($personalizationFilters['frequency'] ?? '') !== '';

$propertySearchUrl = static function (array $search): string {
    $query = array_filter([
        'q' => $search['query_text'] ?? '',
        'offer' => $search['transaction_type'] ?? '',
        'type' => $search['property_type'] ?? '',
        'location' => $search['location_id'] ?? '',
        'bedrooms' => $search['bedrooms_min'] ?? '',
        'min_price' => $search['price_min'] ?? '',
        'max_price' => $search['price_max'] ?? '',
        'currency' => $search['currency'] ?? '',
    ], static fn (mixed $value): bool => $value !== null && $value !== '');

    return url('/properties' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>
<section class="account-page">
    <div class="account-page__head">
        <div>
            <span class="eyebrow">Customer Account</span>
            <h1><?= e($sectionTitle) ?></h1>
            <p><?= e($sectionDescription) ?></p>
        </div>
        <div class="account-page__identity">
            <span><?= app_icon('users') ?></span>
            <div>
                <strong><?= e((string) $accountUser['name']) ?></strong>
                <small><?= e((string) $accountUser['email']) ?></small>
            </div>
        </div>
    </div>

    <div data-account-notice hidden></div>

    <?php if (!$emailVerified): ?>
        <div class="account-access-ready account-access-ready--verification">
            <?= app_icon('mail') ?>
            <div>
                <strong>Verify your email address.</strong>
                <span>Verification is required before company onboarding or accepting a company invitation.</span>
            </div>
            <a class="btn btn-primary btn-sm" href="<?= e(url('/verify-email')) ?>">Verify email</a>
        </div>
    <?php endif; ?>

    <?php if ($activeSection === 'overview'): ?>
        <div class="account-stat-grid">
            <a href="<?= e(url('/account/saved')) ?>"><span><?= app_icon('heart') ?></span><strong><?= e((string) $summary['saved']) ?></strong><small>Saved properties</small></a>
            <a href="<?= e(url('/account/compare')) ?>"><span><?= app_icon('compare') ?></span><strong><?= e((string) $summary['compare']) ?></strong><small>In comparison</small></a>
            <a href="<?= e(url('/account/inquiries')) ?>"><span><?= app_icon('message') ?></span><strong><?= e((string) $summary['inquiries']) ?></strong><small>Inquiries</small></a>
            <a href="<?= e(url('/account/viewings')) ?>"><span><?= app_icon('calendar') ?></span><strong><?= e((string) $summary['viewings']) ?></strong><small>Active viewings</small></a>
            <a href="<?= e(url('/account/alerts')) ?>"><span><?= app_icon('bell') ?></span><strong><?= e((string) $summary['alerts']) ?></strong><small>Active alerts</small></a>
            <a href="<?= e(url('/account/notifications')) ?>"><span><?= app_icon('bell') ?></span><strong><?= e((string) $summary['notifications']) ?></strong><small>Unread notifications</small></a>
        </div>

        <div class="account-quick-actions" aria-label="Account shortcuts">
            <a href="<?= e(url('/properties')) ?>">
                <span><?= app_icon('search') ?></span>
                <span><strong>Find properties</strong><small>Browse approved sale and rental listings.</small></span>
            </a>
            <a href="<?= e(url('/account/saved')) ?>">
                <span><?= app_icon('heart') ?></span>
                <span><strong>Open shortlist</strong><small>Return to properties you saved.</small></span>
            </a>
            <a href="<?= e(url('/account/alerts')) ?>">
                <span><?= app_icon('bell') ?></span>
                <span><strong>Create an alert</strong><small>Save criteria and receive matching updates.</small></span>
            </a>
        </div>

        <?php if ($savedListings !== []): ?>
            <section class="account-section">
                <div class="section-heading-row">
                    <div><span class="eyebrow">Shortlist</span><h2>Saved properties</h2></div>
                    <a href="<?= e(url('/account/saved')) ?>">View all</a>
                </div>
                <div class="inventory-grid">
                    <?php foreach ($savedListings as $item): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($recentViews !== []): ?>
            <section class="account-section">
                <div class="section-heading-row">
                    <div><span class="eyebrow">History</span><h2>Recently viewed</h2></div>
                    <a href="<?= e(url('/account/recent')) ?>">View all</a>
                </div>
                <div class="inventory-grid">
                    <?php foreach ($recentViews as $item): ?>
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($savedListings === [] && $recentViews === [] && $viewingRequests === []): ?>
            <div class="account-empty">
                <?= app_icon('home') ?>
                <strong>Your marketplace activity will appear here.</strong>
                <span>Explore published properties to save, compare, inquire, or request a viewing.</span>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">Browse properties</a>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'saved'): ?>
        <section class="account-activity-toolbar" aria-label="Saved property filters">
            <div class="account-activity-toolbar__intro">
                <span class="account-activity-toolbar__icon"><?= app_icon('heart') ?></span>
                <div><strong><?= e(number_format(count($savedListings))) ?> matching saved propert<?= count($savedListings) === 1 ? 'y' : 'ies' ?></strong><span>Search and refine your personal shortlist.</span></div>
            </div>
            <form method="get" class="account-activity-filter">
                <label class="account-activity-search"><span><?= app_icon('search') ?></span><input name="q" value="<?= e($activitySearch) ?>" maxlength="80" placeholder="Search saved properties" aria-label="Search saved properties"></label>
                <label><span>Offer</span><select name="offer"><option value="">Sale & rent</option><option value="sale" <?= ($activityFilters['offer'] ?? '') === 'sale' ? 'selected' : '' ?>>For sale</option><option value="rent" <?= ($activityFilters['offer'] ?? '') === 'rent' ? 'selected' : '' ?>>For rent</option></select></label>
                <label><span>Property type</span><select name="type"><option value="">All property types</option><?php foreach ($propertyTypes as $type): ?><option value="<?= e((string) $type) ?>" <?= ($activityFilters['type'] ?? '') === $type ? 'selected' : '' ?>><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                <label><span>Sort</span><select name="sort"><?php foreach ($activitySortOptions as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $activitySort === $value ? 'selected' : '' ?>><?= e((string) $label) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <?php if ($activityHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/saved')) ?>">Reset</a><?php endif; ?>
            </form>
        </section>
        <?php if ($savedListings === []): ?>
            <div class="account-empty"><?= app_icon('heart') ?><strong><?= $activityHasFilters ? 'No saved properties match these filters.' : 'No saved properties yet.' ?></strong><span><?= $activityHasFilters ? 'Adjust or reset the filters to see your shortlist.' : 'Save a published property to build your shortlist.' ?></span><a class="btn btn-primary btn-sm" href="<?= e($activityHasFilters ? url('/account/saved') : url('/properties')) ?>"><?= $activityHasFilters ? 'Reset filters' : 'Browse properties' ?></a></div>
        <?php else: ?>
            <div class="account-listing-grid account-listing-grid--activity">
                <?php foreach ($savedListings as $item): ?>
                    <div class="account-listing-item">
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                        <div class="account-listing-item__meta"><span><?= app_icon('clock') ?> Saved <?= e(!empty($item['saved_at']) ? date('M j, Y', strtotime((string) $item['saved_at'])) : 'recently') ?></span><span><?= app_icon('building') ?> <?= e((string) ($item['company_name'] ?? '')) ?></span></div>
                        <div class="account-listing-item__actions">
                            <a href="<?= e(url('/properties/' . rawurlencode((string) $item['slug']))) ?>"><?= app_icon('eye') ?> View</a>
                            <form action="<?= e(url('/account/compare/' . (int) $item['id'])) ?>" method="post" data-account-action><?= csrf_field() ?><button type="submit"><?= app_icon('compare') ?> Compare</button></form>
                            <form action="<?= e(url('/account/saved/' . (int) $item['id'] . '/remove')) ?>" method="post" data-account-action data-confirm="Remove this property from your saved list?"><?= csrf_field() ?><button class="is-danger" type="submit"><?= app_icon('heart') ?> Remove</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'compare'): ?>
        <section class="account-compare-summary" aria-label="Comparison capacity"><span class="account-activity-toolbar__icon"><?= app_icon('compare') ?></span><div><strong><?= e((string) count($comparisonItems)) ?> of 4 properties selected</strong><span>Review price, location and core specifications side by side.</span></div><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/saved')) ?>">Add from Saved</a></section>
        <?php if ($comparisonItems === []): ?>
            <div class="account-empty"><?= app_icon('compare') ?><strong>No properties in comparison.</strong><span>Add up to four published properties from a property page or your saved list.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">Find properties</a></div>
        <?php else: ?>
            <div class="account-compare-wrap">
                <table class="account-compare-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <?php foreach ($comparisonItems as $item): ?>
                                <th>
                                    <a href="<?= e(url('/properties/' . rawurlencode((string) $item['slug']))) ?>"><?= e((string) $item['title']) ?></a>
                                    <form action="<?= e(url('/account/compare/' . (int) $item['id'] . '/remove')) ?>" method="post" data-account-action>
                                        <?= csrf_field() ?><button type="submit">Remove</button>
                                    </form>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $compareRows = [
                            'Price' => static fn (array $item): string => $formatPrice($item),
                            'Offer' => static fn (array $item): string => $humanize($item['transaction_type'] ?? ''),
                            'Property type' => static fn (array $item): string => $humanize($item['property_type'] ?? ''),
                            'Location' => static fn (array $item): string => (string) ($item['location_name'] ?? '—'),
                            'Bedrooms' => static fn (array $item): string => (string) ($item['bedrooms'] ?? '—'),
                            'Bathrooms' => static fn (array $item): string => (string) ($item['bathrooms'] ?? '—'),
                            'Area' => static fn (array $item): string => trim((string) ($item['area_value'] ?? '') . ' ' . (string) ($item['area_unit'] ?? '')) ?: '—',
                            'Company' => static fn (array $item): string => (string) ($item['company_name'] ?? '—'),
                        ];
                        ?>
                        <?php foreach ($compareRows as $label => $formatter): ?>
                            <tr><th><?= e($label) ?></th><?php foreach ($comparisonItems as $item): ?><td><?= e($formatter($item)) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'inquiries'): ?>
        <section class="account-activity-toolbar" aria-label="Inquiry filters">
            <div class="account-activity-toolbar__intro"><span class="account-activity-toolbar__icon"><?= app_icon('message') ?></span><div><strong><?= e(number_format(count($inquiries))) ?> matching inquir<?= count($inquiries) === 1 ? 'y' : 'ies' ?></strong><span>Track messages submitted from this signed-in customer account.</span></div></div>
            <form method="get" class="account-activity-filter account-activity-filter--compact">
                <label class="account-activity-search"><span><?= app_icon('search') ?></span><input name="q" value="<?= e($activitySearch) ?>" maxlength="80" placeholder="Search inquiries" aria-label="Search inquiries"></label>
                <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach ($activityStatusOptions as $statusOption): ?><option value="<?= e((string) $statusOption) ?>" <?= ($activityFilters['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= e($humanize($statusOption)) ?></option><?php endforeach; ?></select></label>
                <label><span>Sort</span><select name="sort"><?php foreach ($activitySortOptions as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $activitySort === $value ? 'selected' : '' ?>><?= e((string) $label) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary btn-sm" type="submit">Filter</button><?php if ($activityHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/inquiries')) ?>">Reset</a><?php endif; ?>
            </form>
        </section>
        <?php if ($inquiries === []): ?>
            <div class="account-empty"><?= app_icon('message') ?><strong><?= $activityHasFilters ? 'No inquiries match these filters.' : 'No signed-in inquiries yet.' ?></strong><span><?= $activityHasFilters ? 'Adjust the search or status filter and try again.' : 'Inquiries sent while you are signed in will be available here.' ?></span></div>
        <?php else: ?>
            <div class="account-record-list account-record-list--activity">
                <?php foreach ($inquiries as $inquiry): ?>
                    <article><div class="account-record-list__top"><span class="status-badge status-badge--<?= e((string) $inquiry['status']) ?>"><?= e($humanize($inquiry['status'])) ?></span><small><?= e(date('M j, Y H:i', strtotime((string) $inquiry['created_at']))) ?></small></div><h2><?= e((string) ($inquiry['property_title'] ?? $inquiry['company_name'] ?? $inquiry['subject'] ?? 'Marketplace inquiry')) ?></h2><?php if (!empty($inquiry['subject'])): ?><strong class="account-record-subject"><?= e((string) $inquiry['subject']) ?></strong><?php endif; ?><p><?= e((string) $inquiry['message']) ?></p><dl class="account-inline-facts"><div><dt>Company</dt><dd><?= e((string) ($inquiry['company_name'] ?? '—')) ?></dd></div><div><dt>Response</dt><dd><?= !empty($inquiry['responded_at']) ? e(date('M j, Y H:i', strtotime((string) $inquiry['responded_at']))) : 'Awaiting response' ?></dd></div></dl><footer><span>Inquiry #<?= e((string) $inquiry['id']) ?></span><?php if (!empty($inquiry['listing_slug'])): ?><a href="<?= e(url('/properties/' . rawurlencode((string) $inquiry['listing_slug']))) ?>">View property</a><?php endif; ?></footer></article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'viewings'): ?>
        <section class="account-activity-toolbar" aria-label="Viewing request filters">
            <div class="account-activity-toolbar__intro"><span class="account-activity-toolbar__icon"><?= app_icon('calendar') ?></span><div><strong><?= e(number_format(count($viewingRequests))) ?> matching viewing request<?= count($viewingRequests) === 1 ? '' : 's' ?></strong><span>Track preferred dates, company scheduling and completion status.</span></div></div>
            <form method="get" class="account-activity-filter account-activity-filter--compact">
                <label class="account-activity-search"><span><?= app_icon('search') ?></span><input name="q" value="<?= e($activitySearch) ?>" maxlength="80" placeholder="Search viewings" aria-label="Search viewing requests"></label>
                <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach ($activityStatusOptions as $statusOption): ?><option value="<?= e((string) $statusOption) ?>" <?= ($activityFilters['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= e($humanize($statusOption)) ?></option><?php endforeach; ?></select></label>
                <label><span>Sort</span><select name="sort"><?php foreach ($activitySortOptions as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $activitySort === $value ? 'selected' : '' ?>><?= e((string) $label) ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary btn-sm" type="submit">Filter</button><?php if ($activityHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/viewings')) ?>">Reset</a><?php endif; ?>
            </form>
        </section>
        <?php if ($viewingRequests === []): ?>
            <div class="account-empty"><?= app_icon('calendar') ?><strong><?= $activityHasFilters ? 'No viewing requests match these filters.' : 'No viewing requests yet.' ?></strong><span><?= $activityHasFilters ? 'Adjust the filters or reset them to see your full viewing history.' : 'Open a published property and request a preferred date and time window.' ?></span><a class="btn btn-primary btn-sm" href="<?= e($activityHasFilters ? url('/account/viewings') : url('/properties')) ?>"><?= $activityHasFilters ? 'Reset filters' : 'Browse properties' ?></a></div>
        <?php else: ?>
            <div class="account-record-list account-record-list--activity">
                <?php foreach ($viewingRequests as $viewing): ?>
                    <article><div class="account-record-list__top"><span class="status-badge status-badge--<?= e((string) $viewing['status']) ?>"><?= e($humanize($viewing['status'])) ?></span><small>Requested <?= e(date('M j, Y H:i', strtotime((string) $viewing['created_at']))) ?></small></div><h2><?= e((string) $viewing['property_title']) ?></h2><dl class="account-inline-facts account-inline-facts--activity"><div><dt>Company</dt><dd><?= e((string) $viewing['company_name']) ?></dd></div><div><dt>Preferred</dt><dd><?= e((string) $viewing['preferred_date']) ?> · <?= e($humanize($viewing['preferred_time_window'])) ?></dd></div><?php if (!empty($viewing['alternate_date'])): ?><div><dt>Alternate</dt><dd><?= e((string) $viewing['alternate_date']) ?> · <?= e($humanize($viewing['alternate_time_window'] ?? '')) ?></dd></div><?php endif; ?><div><dt>Scheduled</dt><dd><?= !empty($viewing['scheduled_for']) ? e(date('M j, Y H:i', strtotime((string) $viewing['scheduled_for']))) : 'Pending confirmation' ?></dd></div></dl><?php if (!empty($viewing['company_note'])): ?><p class="account-company-note"><strong>Company note:</strong> <?= e((string) $viewing['company_note']) ?></p><?php endif; ?><footer><a href="<?= e(url('/properties/' . rawurlencode((string) $viewing['listing_slug']))) ?>">View property</a><?php if (in_array((string) $viewing['status'], ['pending','confirmed','rescheduled'], true)): ?><form action="<?= e(url('/account/viewings/' . (int) $viewing['id'] . '/cancel')) ?>" method="post" data-account-action data-confirm="Cancel this viewing request?"><?= csrf_field() ?><button class="is-danger" type="submit">Cancel request</button></form><?php endif; ?></footer></article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'alerts'): ?>
        <div class="account-alert-layout">
            <section class="account-panel account-panel--featured">
                <div class="account-panel__head">
                    <h2>Create property alert</h2>
                    <span>Save real-estate listing criteria and receive matching-property updates through your enabled Barama notification channels.</span>
                </div>
                <form action="<?= e(url('/account/alerts')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Alert name</span><input class="form-control" name="name" required maxlength="120" placeholder="e.g. Phnom Penh 2-bedroom condos"></label>
                    <label><span>Listing keywords</span><input class="form-control" name="query_text" maxlength="100" placeholder="Project, area or listing keywords"></label>
                    <div class="account-form__grid">
                        <label><span>Offer</span><select class="form-select" name="transaction_type"><option value="">Sale & rent</option><option value="sale">For sale</option><option value="rent">For rent</option></select></label>
                        <label><span>Property type</span><select class="form-select" name="property_type"><option value="">All types</option><?php foreach ($propertyTypes as $type): ?><option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                        <label><span>Location</span><select class="form-select" name="location_id"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
                        <label><span>Minimum bedrooms</span><input class="form-control" type="number" min="0" step="0.5" name="bedrooms_min"></label>
                        <label><span>Minimum price</span><input class="form-control" type="number" min="0" step="100" name="price_min"></label>
                        <label><span>Maximum price</span><input class="form-control" type="number" min="0" step="100" name="price_max"></label>
                        <label><span>Currency</span><input class="form-control" name="currency" value="USD" maxlength="3"></label>
                        <label><span>Alert frequency</span><select class="form-select" name="alert_frequency"><option value="daily">Daily</option><option value="weekly">Weekly</option></select></label>
                    </div>
                    <label class="workspace-check"><input type="checkbox" name="alerts_enabled" value="1" checked><span>Enable matching-property alerts</span></label>
                    <button class="btn btn-primary" type="submit"><?= app_icon('bell') ?> Save property alert</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head"><h2>Saved property alerts</h2><span><?= e((string) count($savedSearches)) ?> matching saved search<?= count($savedSearches) === 1 ? '' : 'es' ?></span></div>

                <form method="get" class="account-personalization-filter">
                    <label class="account-personalization-search"><span><?= app_icon('search') ?></span><input name="q" maxlength="80" value="<?= e($personalizationSearch) ?>" placeholder="Search property alerts"></label>
                    <label><span>State</span><select name="state"><option value="">Enabled & paused</option><option value="enabled" <?= ($personalizationFilters['state'] ?? '') === 'enabled' ? 'selected' : '' ?>>Enabled</option><option value="paused" <?= ($personalizationFilters['state'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option></select></label>
                    <label><span>Frequency</span><select name="frequency"><option value="">Daily & weekly</option><option value="daily" <?= ($personalizationFilters['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option><option value="weekly" <?= ($personalizationFilters['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option></select></label>
                    <label><span>Sort</span><select name="sort"><option value="updated_desc" <?= $personalizationSort === 'updated_desc' ? 'selected' : '' ?>>Recently updated</option><option value="name_asc" <?= $personalizationSort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option></select></label>
                    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                    <?php if ($personalizationHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/alerts')) ?>">Reset</a><?php endif; ?>
                </form>

                <?php if ($savedSearches === []): ?>
                    <div class="account-empty account-empty--compact"><?= app_icon('bell') ?><strong><?= $personalizationHasFilters ? 'No property alerts match these filters.' : 'No saved property alerts yet.' ?></strong><span><?= $personalizationHasFilters ? 'Adjust or reset the filters to see your other saved searches.' : 'Create a saved search above to monitor matching real-estate listings.' ?></span></div>
                <?php else: ?>
                    <div class="saved-search-list">
                        <?php foreach ($savedSearches as $search): ?>
                            <article class="saved-search-row saved-search-row--premium">
                                <span class="saved-search-row__icon"><?= app_icon('home') ?></span>
                                <div class="saved-search-row__body">
                                    <div class="saved-search-row__title"><strong><?= e((string) $search['name']) ?></strong><span class="status-badge status-badge--<?= !empty($search['alerts_enabled']) ? 'approved' : 'neutral' ?>"><?= !empty($search['alerts_enabled']) ? 'Enabled' : 'Paused' ?></span></div>
                                    <span><?= e(implode(' · ', array_filter([
                                        $search['transaction_type'] ? $humanize($search['transaction_type']) : 'Sale & rent',
                                        $search['property_type'] ? $humanize($search['property_type']) : 'All property types',
                                        $search['location_name'] ?? 'All locations',
                                        $search['bedrooms_min'] ? 'Min ' . $search['bedrooms_min'] . ' bed' : null,
                                        $search['price_min'] ? 'From ' . $search['currency'] . ' ' . number_format((float) $search['price_min'], 0) : null,
                                        $search['price_max'] ? 'Up to ' . $search['currency'] . ' ' . number_format((float) $search['price_max'], 0) : null,
                                    ]))) ?></span>
                                    <small><?= e($humanize($search['alert_frequency'])) ?> matching-property checks</small>
                                </div>
                                <div class="saved-search-row__actions">
                                    <a href="<?= e($propertySearchUrl($search)) ?>">View matching listings</a>
                                    <form action="<?= e(url('/account/alerts/' . (int) $search['id'])) ?>" method="post" data-account-action><?= csrf_field() ?><select name="alert_frequency"><option value="daily" <?= $search['alert_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option><option value="weekly" <?= $search['alert_frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option></select><label><input type="checkbox" name="alerts_enabled" value="1" <?= !empty($search['alerts_enabled']) ? 'checked' : '' ?>> Alerts on</label><button type="submit">Update</button></form>
                                    <form action="<?= e(url('/account/alerts/' . (int) $search['id'] . '/delete')) ?>" method="post" data-account-action data-confirm="Delete this saved property alert?"><?= csrf_field() ?><button class="is-danger" type="submit">Delete</button></form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php elseif ($activeSection === 'notifications'): ?>
        <div class="account-notification-layout">
            <section class="account-panel">
                <div class="account-panel__head"><h2>Real-estate notification preferences</h2><span>Control matching property listings, viewing requests, and property inquiry updates only.</span></div>
                <form action="<?= e(url('/account/notification-preferences')) ?>" method="post" data-account-action class="account-form account-notification-preferences">
                    <?= csrf_field() ?>
                    <label class="workspace-check"><input type="checkbox" name="in_app_enabled" value="1" <?= !empty($notificationPreferences['in_app_enabled']) ? 'checked' : '' ?>><span>In-app property notifications</span></label>
                    <label class="workspace-check"><input type="checkbox" name="email_enabled" value="1" <?= !empty($notificationPreferences['email_enabled']) ? 'checked' : '' ?>><span>Email property notifications</span></label>
                    <div class="account-preference-divider"></div>
                    <label class="workspace-check"><input type="checkbox" name="saved_search_alerts" value="1" <?= !empty($notificationPreferences['saved_search_alerts']) ? 'checked' : '' ?>><span>Matching property listing alerts</span></label>
                    <label class="workspace-check"><input type="checkbox" name="viewing_updates" value="1" <?= !empty($notificationPreferences['viewing_updates']) ? 'checked' : '' ?>><span>Property viewing updates</span></label>
                    <label class="workspace-check"><input type="checkbox" name="inquiry_updates" value="1" <?= !empty($notificationPreferences['inquiry_updates']) ? 'checked' : '' ?>><span>Property inquiry updates</span></label>
                    <button class="btn btn-primary" type="submit">Save notification preferences</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head account-panel__head--actions">
                    <div><h2>Property activity inbox</h2><span><?= e((string) $summary['notifications']) ?> unread marketplace notification<?= (int) $summary['notifications'] === 1 ? '' : 's' ?></span></div>
                    <?php if ($summary['notifications'] > 0): ?><form action="<?= e(url('/account/notifications/read-all')) ?>" method="post" data-account-action><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Mark all read</button></form><?php endif; ?>
                </div>

                <form method="get" class="account-personalization-filter account-personalization-filter--notifications">
                    <label class="account-personalization-search"><span><?= app_icon('search') ?></span><input name="q" maxlength="80" value="<?= e($personalizationSearch) ?>" placeholder="Search property notifications"></label>
                    <label><span>State</span><select name="state"><option value="">Read & unread</option><option value="unread" <?= ($personalizationFilters['state'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= ($personalizationFilters['state'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option></select></label>
                    <label><span>Activity</span><select name="category"><option value="">All property activity</option><option value="saved_search_alerts" <?= ($personalizationFilters['category'] ?? '') === 'saved_search_alerts' ? 'selected' : '' ?>>Property matches</option><option value="viewing_updates" <?= ($personalizationFilters['category'] ?? '') === 'viewing_updates' ? 'selected' : '' ?>>Viewings</option><option value="inquiry_updates" <?= ($personalizationFilters['category'] ?? '') === 'inquiry_updates' ? 'selected' : '' ?>>Inquiries</option></select></label>
                    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                    <?php if ($personalizationHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/notifications')) ?>">Reset</a><?php endif; ?>
                </form>

                <?php if ($notifications === []): ?>
                    <div class="account-empty account-empty--compact"><?= app_icon('bell') ?><strong><?= $personalizationHasFilters ? 'No property notifications match these filters.' : 'No property notifications yet.' ?></strong><span><?= $personalizationHasFilters ? 'Adjust or reset the filters to see other marketplace activity.' : 'Matching listings, inquiry updates, and viewing activity will appear here.' ?></span></div>
                <?php else: ?>
                    <div class="account-notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="account-notification-item <?= empty($notification['read_at']) ? 'is-unread' : '' ?>">
                                <div class="account-notification-item__icon"><?= app_icon(match ((string) $notification['category']) {'viewing_updates' => 'calendar','inquiry_updates' => 'message','saved_search_alerts' => 'home',default => 'bell',}) ?></div>
                                <div class="account-notification-item__body">
                                    <div class="account-notification-item__meta"><span><?= e(match ((string) $notification['category']) {'saved_search_alerts' => 'Property match','viewing_updates' => 'Viewing update','inquiry_updates' => 'Inquiry update',default => $humanize($notification['category']),}) ?></span><time><?= e(date('M j, Y H:i', strtotime((string) $notification['created_at']))) ?></time></div>
                                    <h3><?= e((string) $notification['title']) ?></h3>
                                    <p><?= e((string) $notification['message']) ?></p>
                                    <div class="account-notification-item__actions">
                                        <?php if (!empty($notification['action_url'])): ?><a href="<?= e(url((string) $notification['action_url'])) ?>">Open property activity</a><?php endif; ?>
                                        <?php if (empty($notification['read_at'])): ?><form action="<?= e(url('/account/notifications/' . (int) $notification['id'] . '/read')) ?>" method="post" data-account-action><?= csrf_field() ?><button type="submit">Mark read</button></form><?php else: ?><span class="account-read-state"><?= app_icon('check') ?> Read</span><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php elseif ($activeSection === 'company'): ?>
        <?php
        $canRequestCompany = true;
        foreach ($ownedCompanies as $ownedCompany) {
            if (!in_array((string) $ownedCompany['status'], ['rejected', 'archived'], true)) {
                $canRequestCompany = false;
                break;
            }
        }
        ?>
        <div class="account-settings-grid">
            <section class="account-panel account-panel--featured">
                <div class="account-panel__head"><h2>Real-estate company access</h2><span>Company access is only for managing projects, properties, listings, inquiries, viewings, leads, team members, and listing media.</span></div>

                <?php if ($companyInvitations !== []): ?>
                    <div class="account-company-section-title"><strong>Pending company invitations</strong><span><?= e((string) count($companyInvitations)) ?> awaiting your action</span></div>
                    <div class="account-invitation-list">
                        <?php foreach ($companyInvitations as $invitation): ?>
                            <article>
                                <span class="account-invitation-list__icon"><?= app_icon('building') ?></span>
                                <div><strong><?= e((string) $invitation['company_name']) ?></strong><small>Real-estate workspace role: <?= e($humanize($invitation['member_role'])) ?></small><small>Invited by <?= e((string) $invitation['invited_by_name']) ?> · Expires <?= e(date('M j, Y H:i', strtotime((string) $invitation['expires_at']))) ?></small></div>
                                <?php if ($emailVerified): ?><form action="<?= e(url('/account/company-invitations/' . (int) $invitation['id'] . '/accept')) ?>" method="post" data-account-action><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Accept company access</button></form><?php else: ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/verify-email')) ?>">Verify email first</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ownedCompanies !== []): ?>
                    <div class="account-company-section-title"><strong>Your real-estate companies</strong><span>Portfolio access and review state</span></div>
                    <div class="account-company-status account-company-status--portfolio">
                        <?php foreach ($ownedCompanies as $company): ?>
                            <article>
                                <div class="account-company-status__main">
                                    <div class="account-company-status__heading"><span class="status-badge status-badge--<?= e((string) $company['status']) ?>"><?= e($humanize($company['status'])) ?></span><span><?= e($humanize($company['company_type'])) ?></span></div>
                                    <h3><?= e((string) $company['name']) ?></h3>
                                    <p><?= e((string) ($company['location_name'] ?? 'Location not set')) ?><?= !empty($company['email']) ? ' · ' . e((string) $company['email']) : '' ?></p>
                                    <dl class="account-company-portfolio"><div><dt>Projects</dt><dd><?= e((string) ($company['project_count'] ?? 0)) ?></dd></div><div><dt>Properties</dt><dd><?= e((string) ($company['property_count'] ?? 0)) ?></dd></div><div><dt>Listings</dt><dd><?= e((string) ($company['listing_count'] ?? 0)) ?></dd></div></dl>
                                </div>
                                <div class="account-company-status__action">
                                    <?php if ((string) $company['status'] === 'approved' && auth_can('developer.access')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/developer')) ?>">Manage listings</a>
                                    <?php elseif ((string) $company['status'] === 'pending'): ?><span class="account-review-note"><?= app_icon('clock') ?> Administrator review pending</span>
                                    <?php elseif ((string) $company['status'] === 'rejected'): ?><span class="account-review-note is-danger"><?= app_icon('alert') ?> Company request not approved</span>
                                    <?php elseif ((string) $company['status'] === 'suspended'): ?><span class="account-review-note is-danger"><?= app_icon('alert') ?> Company access suspended</span><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$emailVerified): ?>
                    <div class="account-review-note"><?= app_icon('mail') ?> Verify your account email before submitting a real-estate company for review.</div>
                <?php elseif ($canRequestCompany && !auth_can('developer.access')): ?>
                    <div class="account-company-section-title"><strong>Request a real-estate company workspace</strong><span>For developers, agencies, property companies, and property owners listing inventory on Barama.</span></div>
                    <form action="<?= e(url('/account/company/onboard')) ?>" method="post" data-account-action class="account-form account-company-form">
                        <?= csrf_field() ?>
                        <div class="account-form__grid">
                            <label><span>Company name</span><input class="form-control" name="name" required maxlength="180"></label>
                            <label><span>Company type</span><select class="form-select" name="company_type" required><?php foreach ($companyTypes as $type): ?><option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                            <label><span>Legal name</span><input class="form-control" name="legal_name" maxlength="191"></label>
                            <label><span>Registration number</span><input class="form-control" name="registration_number" maxlength="120"></label>
                            <label><span>Company email</span><input class="form-control" name="email" type="email" value="<?= e((string) $accountUser['email']) ?>" maxlength="191"></label>
                            <label><span>Phone</span><input class="form-control" name="phone" maxlength="60"></label>
                            <label class="account-form__span-2"><span>Primary property market location</span><select class="form-select" name="location_id"><option value="">Select location</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?> · <?= e($humanize($location['type'])) ?></option><?php endforeach; ?></select></label>
                            <label class="account-form__span-2"><span>Company website</span><input class="form-control" name="website" type="url" maxlength="255" placeholder="https://"></label>
                            <label class="account-form__span-2"><span>Real-estate business description</span><textarea class="form-control" name="description" rows="4" maxlength="10000" placeholder="Describe your real-estate company, development projects, or property listing activity."></textarea></label>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= app_icon('building') ?> Submit company for review</button>
                    </form>
                <?php elseif (auth_can('developer.access')): ?>
                    <div class="account-access-ready"><?= app_icon('check') ?><div><strong>Real-estate company workspace access is active.</strong><span>Manage company profile, projects, properties, listings, customer inquiries, viewing requests, leads, team access, and listing media.</span></div><a class="btn btn-primary btn-sm" href="<?= e(url('/developer')) ?>">Open listing workspace</a></div>
                <?php endif; ?>
            </section>
        </div>
    <?php elseif ($activeSection === 'profile'): ?>
        <div class="account-settings-grid">
            <section class="account-panel">
                <div class="account-panel__head"><h2>Personal profile</h2><span>Your login email is managed separately under Security.</span></div>
                <form action="<?= e(url('/account/profile')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Full name</span><input class="form-control" name="name" value="<?= e((string) ($profile['name'] ?? $accountUser['name'])) ?>" required maxlength="120"></label>
                    <label><span>Email</span><input class="form-control" value="<?= e((string) $accountUser['email']) ?>" type="email" readonly></label>
                    <label><span>Phone</span><input class="form-control" name="phone" value="<?= e((string) ($profile['phone'] ?? '')) ?>" maxlength="60"></label>
                    <div class="account-form__grid">
                        <label><span>Timezone</span>
                            <select class="form-select" name="timezone">
                                <?php $selectedTimezone = (string) ($profile['timezone'] ?? 'UTC'); ?>
                                <?php foreach ($timezones as $timezone): ?>
                                    <option value="<?= e($timezone) ?>" <?= $selectedTimezone === $timezone ? 'selected' : '' ?>><?= e($timezone) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>Preferred language</span>
                            <?php $language = (string) ($profile['preferred_language'] ?? 'en'); ?>
                            <select class="form-select" name="preferred_language">
                                <?php foreach (app_locales() as $localeCode => $localeLabel): ?>
                                    <option value="<?= e($localeCode) ?>" <?= $language === $localeCode ? 'selected' : '' ?>><?= e($localeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save profile</button>
                </form>
            </section>
        </div>
    <?php elseif ($activeSection === 'privacy'): ?>
        <div class="account-settings-grid">
            <section class="account-panel">
                <div class="account-panel__head"><h2>Marketplace activity</h2><span>Control whether signed-in property views are kept in your Recently Viewed history.</span></div>
                <form action="<?= e(url('/account/privacy')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label class="workspace-check">
                        <input type="checkbox" name="remember_recent_views" value="1" <?= !isset($profile['remember_recent_views']) || !empty($profile['remember_recent_views']) ? 'checked' : '' ?>>
                        <span>Remember recently viewed properties on this account</span>
                    </label>
                    <button class="btn btn-primary" type="submit">Save privacy settings</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head"><h2>Clear browsing activity</h2><span>This removes your signed-in Recently Viewed history. Saved properties, comparisons, inquiries, and viewing requests are not deleted.</span></div>
                <form action="<?= e(url('/account/privacy/clear-history')) ?>" method="post" data-account-action data-confirm="Clear your recently viewed property history?">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary" type="submit">Clear recently viewed</button>
                </form>
            </section>
        </div>
    <?php elseif ($activeSection === 'security'): ?>
        <div class="account-settings-grid account-settings-grid--security">
            <section class="account-panel">
                <div class="account-panel__head"><h2>Change password</h2><span>Current-password verification is required.</span></div>
                <form action="<?= e(url('/account/security/password')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Current password</span><input class="form-control" name="current_password" type="password" autocomplete="current-password" required></label>
                    <label><span>New password</span><input class="form-control" name="password" type="password" minlength="12" autocomplete="new-password" required></label>
                    <label><span>Confirm new password</span><input class="form-control" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
                    <button class="btn btn-primary" type="submit"><?= app_icon('lock') ?> Update password</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head"><h2>Change email address</h2><span>A verification link is sent to the new address and expires after 60 minutes.</span></div>
                <form action="<?= e(url('/account/security/email')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Current email</span><input class="form-control" value="<?= e((string) $accountUser['email']) ?>" readonly></label>
                    <label><span>New email</span><input class="form-control" name="new_email" type="email" required maxlength="191"></label>
                    <label><span>Current password</span><input class="form-control" name="current_password" type="password" autocomplete="current-password" required></label>
                    <button class="btn btn-primary" type="submit"><?= app_icon('mail') ?> Send confirmation</button>
                </form>
            </section>

            <section class="account-panel account-danger-zone">
                <div class="account-panel__head"><h2>Deactivate account</h2><span>Sign-in access stops immediately. Company owners must transfer or archive ownership first.</span></div>
                <form action="<?= e(url('/account/security/deactivate')) ?>" method="post" data-account-action data-confirm="Deactivate your Barama account? You will be signed out." class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Current password</span><input class="form-control" name="current_password" type="password" autocomplete="current-password" required></label>
                    <button class="btn btn-outline-danger" type="submit">Deactivate account</button>
                </form>
            </section>
        </div>

    <?php elseif ($activeSection === 'recent'): ?>
        <section class="account-recent-toolbar">
            <div class="account-activity-toolbar__intro"><span class="account-activity-toolbar__icon"><?= app_icon('clock') ?></span><div><strong><?= e((string) count($recentViews)) ?> matching recently viewed propert<?= count($recentViews) === 1 ? 'y' : 'ies' ?></strong><span>Only published real-estate listings viewed while signed in are shown here.</span></div></div>
            <form method="get" class="account-personalization-filter account-personalization-filter--recent">
                <label class="account-personalization-search"><span><?= app_icon('search') ?></span><input name="q" maxlength="80" value="<?= e($personalizationSearch) ?>" placeholder="Search recently viewed properties"></label>
                <label><span>Sort</span><select name="sort"><option value="recent_desc" <?= $personalizationSort === 'recent_desc' ? 'selected' : '' ?>>Recently viewed</option><option value="most_viewed" <?= $personalizationSort === 'most_viewed' ? 'selected' : '' ?>>Most viewed</option><option value="title_asc" <?= $personalizationSort === 'title_asc' ? 'selected' : '' ?>>Property A-Z</option></select></label>
                <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                <?php if ($personalizationHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/recent')) ?>">Reset</a><?php endif; ?>
                <?php if ($recentViews !== []): ?><form action="<?= e(url('/account/privacy/clear-history')) ?>" method="post" data-account-action data-confirm="Clear your recently viewed property history?"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Clear history</button></form><?php endif; ?>
            </form>
        </section>

        <?php if ($recentViews === []): ?>
            <div class="account-empty"><?= app_icon('clock') ?><strong><?= $personalizationHasFilters ? 'No recently viewed properties match this search.' : 'No recent property views yet.' ?></strong><span><?= $personalizationHasFilters ? 'Reset the search to see the rest of your property history.' : 'Published properties you open while signed in will appear here.' ?></span><a class="btn btn-primary btn-sm" href="<?= e($personalizationHasFilters ? url('/account/recent') : url('/properties')) ?>"><?= $personalizationHasFilters ? 'Reset search' : 'Browse properties' ?></a></div>
        <?php else: ?>
            <div class="account-listing-grid account-listing-grid--recent">
                <?php foreach ($recentViews as $item): ?>
                    <div class="account-listing-item">
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                        <div class="account-listing-item__meta"><span><?= app_icon('clock') ?> Viewed <?= e(!empty($item['last_viewed_at']) ? date('M j, Y H:i', strtotime((string) $item['last_viewed_at'])) : 'recently') ?></span><span><?= app_icon('eye') ?> <?= e((string) max(1, (int) ($item['view_count'] ?? 1))) ?> view<?= (int) ($item['view_count'] ?? 1) === 1 ? '' : 's' ?></span></div>
                        <div class="account-listing-item__actions"><a href="<?= e(url('/properties/' . rawurlencode((string) $item['slug']))) ?>"><?= app_icon('eye') ?> View property</a><form action="<?= e(url('/account/saved/' . (int) $item['id'])) ?>" method="post" data-account-action><?= csrf_field() ?><button type="submit"><?= app_icon('heart') ?> Save</button></form><form action="<?= e(url('/account/compare/' . (int) $item['id'])) ?>" method="post" data-account-action><?= csrf_field() ?><button type="submit"><?= app_icon('compare') ?> Compare</button></form></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
