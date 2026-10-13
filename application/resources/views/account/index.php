<?php

declare(strict_types=1);

// BARAMA_A46R4_ACCOUNT_DEVELOPER_STANDARD_CARDS
$baramaA46r4Compact = static function (mixed $value): string {
    $number = is_numeric($value) ? max(0, (float) $value) : 0.0;
    foreach ([1000000000000 => 'T', 1000000000 => 'B', 1000000 => 'M', 1000 => 'K'] as $scale => $suffix) {
        if ($number >= $scale) {
            $text = number_format($number / $scale, 1, '.', '');
            return rtrim(rtrim($text, '0'), '.') . $suffix;
        }
    }
    return number_format((int) $number);
};

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
<section class="account-page<?= $activeSection === 'security'
    ? ' account-page--security-v1129'
    : ($activeSection === 'company' ? ' account-page--company-v114' : '') ?>">






    <div class="account-page__head">
        <div>
            <span class="eyebrow">Customer Account</span>
            <h1><?= e($sectionTitle) ?></h1>
            <p><?= e($sectionDescription) ?></p>
        </div>
        <?php if ($activeSection === 'apply'): ?>
            <?php if (auth_workspace_can('developer')): ?>
                <a class="barama-application-v116__header-action" href="<?= e(url('/developer')) ?>" aria-label="Open Business Workspace">
                    <?= app_icon('building') ?> <span>Open Business Workspace</span> <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
        <?php elseif ($activeSection === 'company' && auth_workspace_can('developer')): ?>
            <a class="barama-company-v114__header-action" href="<?= e(url('/developer')) ?>" aria-label="Open listing workspace">
                <?= app_icon('building') ?> <span>Open listing workspace</span> <span aria-hidden="true">→</span>
            </a>
        <?php elseif ($activeSection === 'company' && !auth_workspace_can('developer') && $emailVerified): ?>
            <a class="barama-company-v114__header-action" href="<?= e(url('/account/developer/apply')) ?>" aria-label="Apply for Developer or Company">+ <span>Apply for Developer / Company</span></a>
        <?php else: ?>
        <div class="account-page__identity<?= $activeSection === 'profile' ? ' account-page__identity--avatar-v110' : '' ?>">
            <?php if ($activeSection === 'profile'): ?>
                <?php $customerAvatarUuid = trim((string) ($profile['avatar_uuid'] ?? '')); ?>
                <div class="barama-customer-avatar-v110" data-avatar-v110>
                    <button type="button" class="barama-customer-avatar-v110__trigger" data-avatar-v110-toggle aria-expanded="false" aria-controls="customer-avatar-menu-v110" aria-label="Manage profile photo" title="Manage profile photo">
                        <?php if ($customerAvatarUuid !== ''): ?>
                            <img src="<?= e(url('/account/avatar/' . rawurlencode($customerAvatarUuid))) ?>" alt="" width="44" height="44" loading="eager">
                        <?php else: ?>
                            <?= app_icon('user') ?>
                        <?php endif; ?>
                        <span class="barama-customer-avatar-v110__edit" aria-hidden="true"><?= app_icon('image') ?></span>
                    </button>
                    <div id="customer-avatar-menu-v110" class="barama-customer-avatar-v110__menu" role="group" aria-label="Profile photo controls">
                        <strong class="barama-customer-avatar-v110__menu-title">Profile photo</strong>
                        <form action="<?= e(url('/account/profile')) ?>" method="post" enctype="multipart/form-data" data-avatar-v107-upload>
                            <?= csrf_field() ?>
                            <input type="hidden" name="profile_action" value="avatar_upload">
                            <input class="barama-avatar-v107__input" id="customer-avatar-input-v110" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-v107-input>
                            <label for="customer-avatar-input-v110" tabindex="0" class="barama-customer-avatar-v110__menu-action" title="<?= $customerAvatarUuid !== '' ? 'Replace profile photo' : 'Upload profile photo' ?>">
                                <?= app_icon('image') ?>
                                <?= $customerAvatarUuid !== '' ? 'Replace photo' : 'Upload photo' ?>
                            </label>
                        </form>
                        <?php if ($customerAvatarUuid !== ''): ?>
                            <form action="<?= e(url('/account/profile')) ?>" method="post" data-avatar-v107-remove>
                                <?= csrf_field() ?>
                                <input type="hidden" name="profile_action" value="avatar_remove">
                                <button class="barama-customer-avatar-v110__menu-action barama-customer-avatar-v110__menu-action--remove" type="submit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6"/></svg> Remove photo
                                </button>
                            </form>
                        <?php endif; ?>
                        <small>JPG, PNG or WebP · Up to 5 MB</small>
                    </div>
                </div>
            <?php else: ?>
                <span><?= app_icon('users') ?></span>
            <?php endif; ?>
            <div>
                <strong><?= e((string) $accountUser['name']) ?></strong>
                <small><?= e((string) $accountUser['email']) ?></small>
            </div>
        </div>
        <?php endif; ?>
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
        <div class="account-stat-grid account-stat-grid--a46r4" aria-label="Account summary">
            <a class="account-stat-grid__card" href="<?= e(url('/account/saved')) ?>" aria-label="Saved properties: <?= e(number_format((int) $summary['saved'])) ?>" title="Saved properties: <?= e(number_format((int) $summary['saved'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('heart') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['saved'])) ?></strong>
                <small>Saved properties</small>
            </a>
            <a class="account-stat-grid__card" href="<?= e(url('/account/compare')) ?>" aria-label="In comparison: <?= e(number_format((int) $summary['compare'])) ?>" title="In comparison: <?= e(number_format((int) $summary['compare'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('compare') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['compare'])) ?></strong>
                <small>In comparison</small>
            </a>
            <a class="account-stat-grid__card" href="<?= e(url('/account/inquiries')) ?>" aria-label="Inquiries: <?= e(number_format((int) $summary['inquiries'])) ?>" title="Inquiries: <?= e(number_format((int) $summary['inquiries'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('message') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['inquiries'])) ?></strong>
                <small>Inquiries</small>
            </a>
            <a class="account-stat-grid__card" href="<?= e(url('/account/viewings')) ?>" aria-label="Active viewings: <?= e(number_format((int) $summary['viewings'])) ?>" title="Active viewings: <?= e(number_format((int) $summary['viewings'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('calendar') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['viewings'])) ?></strong>
                <small>Active viewings</small>
            </a>
            <a class="account-stat-grid__card" href="<?= e(url('/account/alerts')) ?>" aria-label="Active alerts: <?= e(number_format((int) $summary['alerts'])) ?>" title="Active alerts: <?= e(number_format((int) $summary['alerts'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('bell') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['alerts'])) ?></strong>
                <small>Active alerts</small>
            </a>
            <a class="account-stat-grid__card" href="<?= e(url('/account/notifications')) ?>" aria-label="Unread notifications: <?= e(number_format((int) $summary['notifications'])) ?>" title="Unread notifications: <?= e(number_format((int) $summary['notifications'])) ?>">
                <span class="account-stat-grid__watermark" aria-hidden="true"><?= app_icon('bell') ?></span>
                <strong><?= e($baramaA46r4Compact($summary['notifications'])) ?></strong>
                <small>Unread notifications</small>
            </a>

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
                    <div><h2>My notifications</h2><span><?= e((string) $summary['notifications']) ?> unread notification<?= (int) $summary['notifications'] === 1 ? '' : 's' ?></span></div>
                    <?php if ($summary['notifications'] > 0): ?><form action="<?= e(url('/account/notifications/read-all')) ?>" method="post" data-account-action><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" type="submit">Mark all read</button></form><?php endif; ?>
                </div>

                <form method="get" class="account-personalization-filter account-personalization-filter--notifications">
                    <label class="account-personalization-search"><span><?= app_icon('search') ?></span><input name="q" maxlength="80" value="<?= e($personalizationSearch) ?>" placeholder="Search notifications"></label>
                    <label><span>State</span><select name="state"><option value="">Read & unread</option><option value="unread" <?= ($personalizationFilters['state'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= ($personalizationFilters['state'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option></select></label>
                    <label><span>Activity</span><select name="category"><option value="">All activity</option><option value="saved_search_alerts" <?= ($personalizationFilters['category'] ?? '') === 'saved_search_alerts' ? 'selected' : '' ?>>Property matches</option><option value="viewing_updates" <?= ($personalizationFilters['category'] ?? '') === 'viewing_updates' ? 'selected' : '' ?>>Viewings</option><option value="inquiry_updates" <?= ($personalizationFilters['category'] ?? '') === 'inquiry_updates' ? 'selected' : '' ?>>Inquiries</option></select></label>
                    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                    <?php if ($personalizationHasFilters): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/account/notifications')) ?>">Reset</a><?php endif; ?>
                </form>

                <?php if ($notifications === []): ?>
                    <div class="account-empty account-empty--compact"><?= app_icon('bell') ?><strong><?= $personalizationHasFilters ? 'No notifications match these filters.' : 'No notifications yet.' ?></strong><span><?= $personalizationHasFilters ? 'Adjust or reset the filters to see other marketplace activity.' : 'Matching listings, inquiry updates, and viewing activity will appear here.' ?></span></div>
                <?php else: ?>
                    <div class="barama-notification-table-v112__wrap">
                        <table class="barama-notification-table-v112" aria-label="Customer notifications">
                            <thead><tr><th>Notification</th><th>Category</th><th>Received</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php $unread = empty($notification['read_at']); ?>
                                    <tr class="<?= $unread ? 'is-unread' : '' ?>">
                                        <td data-label="Notification"><strong><?= e((string)$notification['title']) ?></strong><small><?= e((string)$notification['message']) ?></small></td>
                                        <td data-label="Category"><?= e($humanize($notification['category'])) ?></td>
                                        <td data-label="Received"><time><?= e((string)$notification['created_at']) ?></time></td>
                                        <td data-label="Status"><span class="barama-notification-table-v112__badge <?= $unread ? 'is-unread' : 'is-read' ?>"><?= $unread ? 'Unread' : 'Read' ?></span></td>
                                        <td data-label="Action"><form method="post" action="<?= e(url('/notifications/' . (int)$notification['id'] . '/open')) ?>"><?= csrf_field() ?><input type="hidden" name="source" value="account"><button class="barama-notification-table-v112__open" type="submit">Open <span aria-hidden="true">→</span></button></form></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php elseif ($activeSection === 'company'): ?>
        <link rel="stylesheet" href="<?= e(asset('css/account-company-access-v114.css') . '?v=117a3') ?>">
        <?php
        $canRequestCompany = true;
        foreach ($ownedCompanies as $ownedCompany) {
            if (!in_array((string) $ownedCompany['status'], ['rejected', 'archived'], true)) {
                $canRequestCompany = false;
                break;
            }
        }
        $hasWorkspaceAccess = auth_workspace_can('developer');
        ?>
        <div class="barama-company-v114">
            <section class="barama-company-v114__panel" aria-labelledby="company-access-title-v114">
                <header class="barama-company-v114__panel-head">
                    <div>
                        <h2 id="company-access-title-v114">Company access</h2>
                        <p>Manage your real-estate company access, review status, and listing workspace.</p>
                    </div>
                    <span class="barama-company-v114__count"><?= e((string) count($ownedCompanies)) ?> compan<?= count($ownedCompanies) === 1 ? 'y' : 'ies' ?></span>
                </header>
                <div class="barama-company-v114__scroll">
                    <table class="barama-company-v114__table" aria-label="Your company access records">
                        <thead><tr><th scope="col">Company / Workspace</th><th scope="col">Access type</th><th scope="col">Portfolio</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($ownedCompanies as $company): ?>
                            <?php $status = (string) ($company['status'] ?? 'draft'); ?>
                            <tr>
                                <td data-label="Company / Workspace">
                                    <strong><?= e((string) $company['name']) ?></strong>
                                    <small><?= e((string) ($company['location_name'] ?? 'Location not set')) ?><?php if (!empty($company['email'])): ?> · <?= e((string) $company['email']) ?><?php endif; ?></small>
                                </td>
                                <td data-label="Access type"><?= e($humanize((string) ($company['company_type'] ?? 'company'))) ?></td>
                                <td data-label="Portfolio"><div class="barama-company-v114__metrics"><span><b><?= e((string) (int) ($company['project_count'] ?? 0)) ?></b> Projects</span><span><b><?= e((string) (int) ($company['property_count'] ?? 0)) ?></b> Properties</span><span><b><?= e((string) (int) ($company['listing_count'] ?? 0)) ?></b> Listings</span></div></td>
                                <td data-label="Status"><span class="barama-company-v114__state barama-company-v114__state--<?= e(in_array($status, ['approved','pending','rejected','suspended','draft','archived'], true) ? $status : 'other') ?>"><?= e($humanize($status)) ?></span></td>
                                <td data-label="Action">
                                    <?php if ($status === 'approved' && $hasWorkspaceAccess): ?>
                                        <a class="barama-company-v114__row-action" href="<?= e(url('/developer')) ?>">Open workspace <span aria-hidden="true">→</span></a>
                                    <?php elseif ($status === 'pending'): ?><span class="barama-company-v114__muted">Awaiting review</span>
                                    <?php elseif ($status === 'rejected'): ?><span class="barama-company-v114__muted">Not approved</span>
                                    <?php elseif ($status === 'suspended'): ?><span class="barama-company-v114__muted">Access suspended</span>
                                    <?php else: ?><span class="barama-company-v114__muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($ownedCompanies === [] && $hasWorkspaceAccess): ?>
                            <tr>
                                <td data-label="Company / Workspace"><strong>Listing workspace</strong><small>Available to your signed-in account</small></td>
                                <td data-label="Access type">Developer access</td>
                                <td data-label="Portfolio"><span class="barama-company-v114__muted">—</span></td>
                                <td data-label="Status"><span class="barama-company-v114__state barama-company-v114__state--approved">Active</span></td>
                                <td data-label="Action"><a class="barama-company-v114__row-action" href="<?= e(url('/developer')) ?>">Open workspace <span aria-hidden="true">→</span></a></td>
                            </tr>
                        <?php elseif ($ownedCompanies === []): ?>
                            <tr class="barama-company-v114__empty"><td colspan="5"><span><?= app_icon('building') ?></span><strong>No company records yet</strong><small>Request company access below or accept an invitation to begin.</small></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($companyInvitations !== []): ?>
                <section class="barama-company-v114__panel" aria-labelledby="company-invitations-title-v114">
                    <header class="barama-company-v114__panel-head"><div><h2 id="company-invitations-title-v114">Pending invitations</h2><p>Company invitations waiting for your response.</p></div><span class="barama-company-v114__count"><?= e((string) count($companyInvitations)) ?> pending</span></header>
                    <div class="barama-company-v114__scroll">
                        <table class="barama-company-v114__table" aria-label="Pending company invitations">
                            <thead><tr><th scope="col">Company</th><th scope="col">Role</th><th scope="col">Expires</th><th scope="col">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($companyInvitations as $invitation): ?>
                                    <tr>
                                        <td data-label="Company"><strong><?= e((string) $invitation['company_name']) ?></strong><small>Invited by <?= e((string) $invitation['invited_by_name']) ?></small></td>
                                        <td data-label="Role"><?= e($humanize($invitation['member_role'])) ?></td>
                                        <td data-label="Expires"><?= e(date('M j, Y H:i', strtotime((string) $invitation['expires_at']))) ?></td>
                                        <td data-label="Action"><?php if ($emailVerified): ?><form action="<?= e(url('/account/company-invitations/' . (int) $invitation['id'] . '/accept')) ?>" method="post" data-account-action><?= csrf_field() ?><button class="barama-company-v114__row-action" type="submit">Accept invitation <span aria-hidden="true">→</span></button></form><?php else: ?><a class="barama-company-v114__row-action" href="<?= e(url('/verify-email')) ?>">Verify email first</a><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!$emailVerified): ?>
                <div class="barama-company-v114__notice"><?= app_icon('mail') ?> Verify your account email before submitting a company for review.</div>
            <?php elseif ($canRequestCompany && !$hasWorkspaceAccess): ?>
                <section class="barama-company-v117a3__upgrade" aria-labelledby="barama-company-v117a3-upgrade-title">
                    <div class="barama-company-v117a3__upgrade-main">
                        <div class="barama-company-v117a3__upgrade-copy">
                            <span class="barama-company-v117a3__eyebrow">Business upgrade</span>
                            <h2 id="barama-company-v117a3-upgrade-title">Become a Developer or Company</h2>
                            <p>Apply to list and manage real-estate inventory. Your account stays a customer account until administrator approval.</p>
                            <a class="barama-company-v117a3__apply" href="<?= e(url('/account/developer/apply')) ?>">
                                <span>Apply for Developer / Company</span>
                                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>
                        <div class="barama-company-v117a3__upgrade-art" aria-hidden="true">
                            <svg viewBox="0 0 500 294" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="company117a3Tower" x1="141" y1="70" x2="332" y2="236" gradientUnits="userSpaceOnUse"><stop stop-color="#EDF4FF"/><stop offset="1" stop-color="#73A8FF"/></linearGradient>
                                    <linearGradient id="company117a3TowerSide" x1="256" y1="125" x2="333" y2="231" gradientUnits="userSpaceOnUse"><stop stop-color="#91B8FF"/><stop offset="1" stop-color="#276FE9"/></linearGradient>
                                    <linearGradient id="company117a3Card" x1="280" y1="99" x2="424" y2="245" gradientUnits="userSpaceOnUse"><stop stop-color="#FFFFFF"/><stop offset="1" stop-color="#E4EDFF"/></linearGradient>
                                    <linearGradient id="company117a3Platform" x1="92" y1="211" x2="425" y2="280" gradientUnits="userSpaceOnUse"><stop stop-color="#F5F8FF"/><stop offset="1" stop-color="#CFDFFF"/></linearGradient>
                                    <filter id="company117a3Shadow" x="40" y="20" width="450" height="270" filterUnits="userSpaceOnUse"><feGaussianBlur stdDeviation="12"/></filter>
                                </defs>
                                <ellipse cx="272" cy="246" rx="191" ry="17" fill="#3877CC" fill-opacity=".12"/>
                                <path d="M85 209 230 145l197 79-143 57-199-72Z" fill="url(#company117a3Platform)" stroke="#D4E2FD" stroke-width="2"/>
                                <path d="m85 209 199 72v10L85 220v-11Z" fill="#B9D2F8"/>
                                <path d="m284 281 143-57v10l-143 57v-10Z" fill="#A8C8FA"/>
                                <path d="m135 134 59-22 63 24-57 23-65-25Z" fill="#F4F8FF" stroke="#BCD5FF" stroke-width="2"/>
                                <path d="m135 134 65 25v89l-65-25v-89Z" fill="url(#company117a3Tower)"/>
                                <path d="m200 159 57-23v89l-57 23v-89Z" fill="url(#company117a3TowerSide)"/>
                                <path d="m175 88 54-23 70 28-56 24-68-29Z" fill="#F6FAFF" stroke="#BCD5FF" stroke-width="2"/>
                                <path d="m175 88 68 29v116l-68-27V88Z" fill="url(#company117a3Tower)"/>
                                <path d="m243 117 56-24v115l-56 25V117Z" fill="url(#company117a3TowerSide)"/>
                                <path d="m208 75 16-7 43 17-17 8-42-18Z" fill="#D8E8FF"/>
                                <path d="M225 67V32" stroke="#4A85F2" stroke-width="4" stroke-linecap="round"/>
                                <path d="m225 31 11 5-11 6-11-6 11-5Z" fill="#1767F9"/>
                                <g fill="#DBE9FF" stroke="#9BC0FA" stroke-width="1.2">
                                    <path d="m187 111 15 6v17l-15-6v-17Zm26 10 15 6v17l-15-6v-17Zm-26 24 15 6v17l-15-6v-17Zm26 10 15 6v17l-15-6v-17Zm-26 24 15 6v17l-15-6v-17Z"/>
                                    <path d="m147 163 14 5v16l-14-5v-16Zm25 10 14 5v16l-14-5v-16Zm-25 13 14 5v16l-14-5v-16Z"/>
                                </g>
                                <g fill="#CBE0FF"><path d="m257 132 13-5v16l-13 5v-16Zm25-11 11-5v16l-11 5v-16Zm-25 35 13-5v16l-13 5v-16Zm25-11 11-5v16l-11 5v-16Zm-25 35 13-5v16l-13 5v-16Z"/></g>
                                <g transform="rotate(-8 351 166)">
                                    <rect x="279" y="90" width="151" height="150" rx="15" fill="url(#company117a3Card)" stroke="#D3E3FF" stroke-width="2"/>
                                    <rect x="294" y="106" width="121" height="18" rx="9" fill="#E1EBFC"/>
                                    <rect x="297" y="143" width="48" height="48" rx="11" fill="#D5E5FF"/>
                                    <path d="m307 165 14-12 15 12m-25-2v15h21v-15" stroke="#1767F9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M356 153h54M356 169h45M299 207h107" stroke="#A8C8F8" stroke-width="8" stroke-linecap="round"/>
                                    <path d="M299 224h71" stroke="#C3D8F8" stroke-width="7" stroke-linecap="round"/>
                                </g>
                                <circle cx="389" cy="223" r="27" fill="#165DFF" stroke="#D5E4FF" stroke-width="5"/>
                                <path d="m375 224 9 9 18-19" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="352" cy="71" r="30" fill="#E4EFFF" stroke="#B6D3FF" stroke-width="2"/>
                                <circle cx="344" cy="62" r="7" fill="#4385F7"/><circle cx="361" cy="66" r="6" fill="#7DA9FD"/>
                                <path d="M330 84c0-10 27-10 27 0v3h-27v-3Zm29-1c0-5 11-7 14 0v4h-14v-4Z" fill="#4385F7"/>
                                <path d="m116 228 6-24 6 24m-6-2v14" stroke="#7BB782" stroke-width="5" stroke-linecap="round"/>
                                <path d="m272 253 6-24 6 24m-6-2v13" stroke="#7BB782" stroke-width="5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <ul class="barama-company-v117a3__benefits" aria-label="Business workspace features">
                        <li>
                            <span class="barama-company-v117a3__benefit-icon"><?= app_icon('building') ?></span>
                            <span><strong>Publish listings</strong><small>Showcase approved properties</small></span>
                        </li>
                        <li>
                            <span class="barama-company-v117a3__benefit-icon"><?= app_icon('layers') ?></span>
                            <span><strong>Manage workspace</strong><small>Organize property inventory</small></span>
                        </li>
                        <li>
                            <span class="barama-company-v117a3__benefit-icon"><?= app_icon('shield') ?></span>
                            <span><strong>Admin approval required</strong><small>Access follows approval</small></span>
                        </li>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    <?php elseif ($activeSection === 'apply'): ?>
        <link rel="stylesheet" href="<?= e(asset('css/developer-application-v115.css') . '?v=117a5') ?>">
        <script src="<?= e(asset('js/developer-application-v115.js') . '?v=115') ?>" defer></script>
        <?php
        $pendingApplication = false;
        foreach ($ownedCompanies as $applicationCompany) {
            if (!in_array((string) $applicationCompany['status'], ['rejected', 'archived'], true)) { $pendingApplication = true; break; }
        }
        ?>
        <div class="barama-application-v115">
            <section class="barama-application-v115__intro barama-application-v117a4__intro" aria-labelledby="barama-apply-v117a4-title">
                <div class="barama-application-v117a4__intro-copy">
                    <span class="barama-application-v117a4__eyebrow">Business membership</span>
                    <h2 id="barama-apply-v117a4-title">Become a verified business member</h2>
                    <p>Apply to create a Developer or Company workspace for real-estate projects and listings. An administrator reviews your application before access is granted.</p>
                    <div class="barama-application-v117a4__assurances" aria-label="Application process">
                        <span><?= app_icon('shield') ?> Admin reviewed</span>
                        <span><?= app_icon('check') ?> Existing customer activity retained</span>
                    </div>
                </div>
                <div class="barama-application-v117a4__intro-art" aria-hidden="true">

                            <svg viewBox="0 0 500 294" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="apply117a4Tower" x1="141" y1="70" x2="332" y2="236" gradientUnits="userSpaceOnUse"><stop stop-color="#EDF4FF"/><stop offset="1" stop-color="#73A8FF"/></linearGradient>
                                    <linearGradient id="apply117a4TowerSide" x1="256" y1="125" x2="333" y2="231" gradientUnits="userSpaceOnUse"><stop stop-color="#91B8FF"/><stop offset="1" stop-color="#276FE9"/></linearGradient>
                                    <linearGradient id="apply117a4Card" x1="280" y1="99" x2="424" y2="245" gradientUnits="userSpaceOnUse"><stop stop-color="#FFFFFF"/><stop offset="1" stop-color="#E4EDFF"/></linearGradient>
                                    <linearGradient id="apply117a4Platform" x1="92" y1="211" x2="425" y2="280" gradientUnits="userSpaceOnUse"><stop stop-color="#F5F8FF"/><stop offset="1" stop-color="#CFDFFF"/></linearGradient>
                                    <filter id="apply117a4Shadow" x="40" y="20" width="450" height="270" filterUnits="userSpaceOnUse"><feGaussianBlur stdDeviation="12"/></filter>
                                </defs>
                                <ellipse cx="272" cy="246" rx="191" ry="17" fill="#3877CC" fill-opacity=".12"/>
                                <path d="M85 209 230 145l197 79-143 57-199-72Z" fill="url(#apply117a4Platform)" stroke="#D4E2FD" stroke-width="2"/>
                                <path d="m85 209 199 72v10L85 220v-11Z" fill="#B9D2F8"/>
                                <path d="m284 281 143-57v10l-143 57v-10Z" fill="#A8C8FA"/>
                                <path d="m135 134 59-22 63 24-57 23-65-25Z" fill="#F4F8FF" stroke="#BCD5FF" stroke-width="2"/>
                                <path d="m135 134 65 25v89l-65-25v-89Z" fill="url(#apply117a4Tower)"/>
                                <path d="m200 159 57-23v89l-57 23v-89Z" fill="url(#apply117a4TowerSide)"/>
                                <path d="m175 88 54-23 70 28-56 24-68-29Z" fill="#F6FAFF" stroke="#BCD5FF" stroke-width="2"/>
                                <path d="m175 88 68 29v116l-68-27V88Z" fill="url(#apply117a4Tower)"/>
                                <path d="m243 117 56-24v115l-56 25V117Z" fill="url(#apply117a4TowerSide)"/>
                                <path d="m208 75 16-7 43 17-17 8-42-18Z" fill="#D8E8FF"/>
                                <path d="M225 67V32" stroke="#4A85F2" stroke-width="4" stroke-linecap="round"/>
                                <path d="m225 31 11 5-11 6-11-6 11-5Z" fill="#1767F9"/>
                                <g fill="#DBE9FF" stroke="#9BC0FA" stroke-width="1.2">
                                    <path d="m187 111 15 6v17l-15-6v-17Zm26 10 15 6v17l-15-6v-17Zm-26 24 15 6v17l-15-6v-17Zm26 10 15 6v17l-15-6v-17Zm-26 24 15 6v17l-15-6v-17Z"/>
                                    <path d="m147 163 14 5v16l-14-5v-16Zm25 10 14 5v16l-14-5v-16Zm-25 13 14 5v16l-14-5v-16Z"/>
                                </g>
                                <g fill="#CBE0FF"><path d="m257 132 13-5v16l-13 5v-16Zm25-11 11-5v16l-11 5v-16Zm-25 35 13-5v16l-13 5v-16Zm25-11 11-5v16l-11 5v-16Zm-25 35 13-5v16l-13 5v-16Z"/></g>
                                <g transform="rotate(-8 351 166)">
                                    <rect x="279" y="90" width="151" height="150" rx="15" fill="url(#apply117a4Card)" stroke="#D3E3FF" stroke-width="2"/>
                                    <rect x="294" y="106" width="121" height="18" rx="9" fill="#E1EBFC"/>
                                    <rect x="297" y="143" width="48" height="48" rx="11" fill="#D5E5FF"/>
                                    <path d="m307 165 14-12 15 12m-25-2v15h21v-15" stroke="#1767F9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M356 153h54M356 169h45M299 207h107" stroke="#A8C8F8" stroke-width="8" stroke-linecap="round"/>
                                    <path d="M299 224h71" stroke="#C3D8F8" stroke-width="7" stroke-linecap="round"/>
                                </g>
                                <circle cx="389" cy="223" r="27" fill="#165DFF" stroke="#D5E4FF" stroke-width="5"/>
                                <path d="m375 224 9 9 18-19" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="352" cy="71" r="30" fill="#E4EFFF" stroke="#B6D3FF" stroke-width="2"/>
                                <circle cx="344" cy="62" r="7" fill="#4385F7"/><circle cx="361" cy="66" r="6" fill="#7DA9FD"/>
                                <path d="M330 84c0-10 27-10 27 0v3h-27v-3Zm29-1c0-5 11-7 14 0v4h-14v-4Z" fill="#4385F7"/>
                                <path d="m116 228 6-24 6 24m-6-2v14" stroke="#7BB782" stroke-width="5" stroke-linecap="round"/>
                                <path d="m272 253 6-24 6 24m-6-2v13" stroke="#7BB782" stroke-width="5" stroke-linecap="round"/>
                            </svg>                </div>
            </section>
            <?php if (auth_workspace_can('developer')): ?>
                <div class="barama-application-v115__notice">Your account already has business workspace access.</div>
            <?php elseif (!$emailVerified): ?>
                <div class="barama-application-v115__notice">Verify your email before submitting an application. <a href="<?= e(url('/verify-email')) ?>">Verify email</a></div>
            <?php elseif ($pendingApplication): ?>
                <div class="barama-application-v115__notice">You already have an existing company application. Check its status on <a href="<?= e(url('/account/company')) ?>">Company Access</a>.</div>
            <?php else: ?>
                <form action="<?= e(url('/account/company/onboard')) ?>" method="post" data-account-action class="account-form barama-application-v115__form">
                    <?= csrf_field() ?>
                    <fieldset class="barama-application-v115__kind barama-application-v117a4__chooser">
                        <legend>Choose account type</legend>
                        <p class="barama-application-v117a4__chooser-help">Select the business workspace you want to apply for.</p>
                        <div class="barama-application-v117a4__choices">
                            <label class="barama-application-v117a4__choice">
                                <input type="radio" name="application_kind" value="developer" checked required>
                                <span class="barama-application-v117a4__choice-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21h16M6 21V5l6-3 6 3v16M9 8h1m4 0h1M9 12h1m4 0h1M9 16h1m4 0h1M11 21v-3h2v3"/></svg>
                                </span>
                                <span class="barama-application-v117a4__choice-content"><strong>Developer</strong><small>Development projects, company portfolios, properties and listings.</small></span>
                                <span class="barama-application-v117a4__choice-check" aria-hidden="true"></span>
                            </label>
                            <label class="barama-application-v117a4__choice">
                                <input type="radio" name="application_kind" value="company" required>
                                <span class="barama-application-v117a4__choice-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 12h2m2 0h2M9 16h2m2 0h2M10 21v-3h4v3M2 9l10-6 10 6"/></svg>
                                </span>
                                <span class="barama-application-v117a4__choice-content"><strong>Company / Agency</strong><small>Property companies, agencies, brokers and approved listing operators.</small></span>
                                <span class="barama-application-v117a4__choice-check" aria-hidden="true"></span>
                            </label>
                        </div>
                    </fieldset>
                    <div class="account-form__grid">
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h1m4 0h1M9 14h1m4 0h1M10 21v-4h4v4"/></svg><span>Company / business name *</span></span><input class="form-control" name="name" required maxlength="180" autocomplete="organization"></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6M7 16h4"/></svg><span>Business type *</span></span><select class="form-select" name="company_type" required>
                            <option value="developer">Property developer</option><option value="agency">Real-estate agency</option><option value="property_company">Property company</option><option value="owner">Property owner</option>
                        </select></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l4 4v14H6zM15 3v5h5M9 12h7M9 16h7"/></svg><span>Legal / registered business name</span></span><input class="form-control" name="legal_name" maxlength="191"></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 9h14M4 15h14M10 3 7 21M17 3l-3 18"/></svg><span>Registration number</span></span><input class="form-control" name="registration_number" maxlength="120"></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span>Business email *</span></span><input class="form-control" name="email" type="email" required value="<?= e((string) $accountUser['email']) ?>" maxlength="191"></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h4l1 5-3 2c1.2 2.4 2.9 4 5.2 5.2l2-3 4.8 1.1v4c0 1.5-1.3 2.6-2.8 2.4C10.2 19 5 13.8 4.4 6.8 4.2 5.3 5.5 3 7 3z"/></svg><span>Business phone</span></span><input class="form-control" name="phone" maxlength="60"></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.4"/></svg><span>Primary operating location</span></span><select class="form-select" name="location_id"><option value="">Select location</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?> · <?= e($humanize($location['type'])) ?></option><?php endforeach; ?></select></label>
                        <label class="barama-application-v117a5__field"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-4 4-4 14 0 18M12 3c4 4 4 14 0 18"/></svg><span>Website</span></span><input class="form-control" name="website" type="url" maxlength="255" placeholder="https://"></label>
                        <label class="barama-application-v117a5__field account-form__span-2"><span class="barama-application-v117a5__field-label"><svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16M4 10h16M4 15h12M4 20h9"/></svg><span>Business description *</span></span><textarea class="form-control" name="description" required rows="5" maxlength="10000" placeholder="Describe your real-estate business and planned activities."></textarea></label>
                    </div>
                    <label class="barama-application-v115__agree"><input type="checkbox" name="accept_business_terms" value="1" required> I understand that approval moves my account to the appropriate business workspace. Only an administrator can downgrade it later.</label>
                    <footer class="barama-application-v115__actions"><button class="btn btn-primary" type="submit">Submit for administrator review</button><a class="btn btn-outline-secondary" href="<?= e(url('/account/company')) ?>">Cancel</a></footer>
                </form>
            <?php endif; ?>
        </div>
    <?php elseif ($activeSection === 'profile'): ?>
        <link rel="stylesheet" href="<?= e(asset('css/customer-profile-avatar-v110.css') . '?v=110') ?>">
        <script src="<?= e(asset('js/profile-avatar-unified-v107.js') . '?v=107') ?>" defer></script>
        <script src="<?= e(asset('js/customer-profile-avatar-v110.js') . '?v=110') ?>" defer></script>
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
