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
        <?php if ($savedListings === []): ?>
            <div class="account-empty"><?= app_icon('heart') ?><strong>No saved properties yet.</strong><span>Save a published property to build your shortlist.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">Browse properties</a></div>
        <?php else: ?>
            <div class="account-listing-grid">
                <?php foreach ($savedListings as $item): ?>
                    <div class="account-listing-item">
                        <?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?>
                        <div class="account-listing-item__actions">
                            <form action="<?= e(url('/account/saved/' . (int) $item['id'] . '/remove')) ?>" method="post" data-account-action>
                                <?= csrf_field() ?>
                                <button type="submit"><?= app_icon('heart') ?> Remove</button>
                            </form>
                            <form action="<?= e(url('/account/compare/' . (int) $item['id'])) ?>" method="post" data-account-action>
                                <?= csrf_field() ?>
                                <button type="submit"><?= app_icon('compare') ?> Compare</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'compare'): ?>
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
        <?php if ($inquiries === []): ?>
            <div class="account-empty"><?= app_icon('message') ?><strong>No signed-in inquiries yet.</strong><span>Inquiries sent while you are signed in will be available here.</span></div>
        <?php else: ?>
            <div class="account-record-list">
                <?php foreach ($inquiries as $inquiry): ?>
                    <article>
                        <div><span class="status-badge status-badge--<?= e((string) $inquiry['status']) ?>"><?= e($humanize($inquiry['status'])) ?></span><small><?= e((string) $inquiry['created_at']) ?></small></div>
                        <h2><?= e((string) ($inquiry['property_title'] ?? $inquiry['company_name'] ?? 'Marketplace inquiry')) ?></h2>
                        <p><?= e((string) $inquiry['message']) ?></p>
                        <footer><span><?= e((string) ($inquiry['company_name'] ?? '')) ?></span><?php if (!empty($inquiry['listing_slug'])): ?><a href="<?= e(url('/properties/' . rawurlencode((string) $inquiry['listing_slug']))) ?>">View property</a><?php endif; ?></footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'viewings'): ?>
        <?php if ($viewingRequests === []): ?>
            <div class="account-empty"><?= app_icon('calendar') ?><strong>No viewing requests yet.</strong><span>Open a published property and request a preferred date and time window.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/properties')) ?>">Browse properties</a></div>
        <?php else: ?>
            <div class="account-record-list">
                <?php foreach ($viewingRequests as $viewing): ?>
                    <article>
                        <div><span class="status-badge status-badge--<?= e((string) $viewing['status']) ?>"><?= e($humanize($viewing['status'])) ?></span><small>Requested <?= e((string) $viewing['created_at']) ?></small></div>
                        <h2><?= e((string) $viewing['property_title']) ?></h2>
                        <dl class="account-inline-facts">
                            <div><dt>Company</dt><dd><?= e((string) $viewing['company_name']) ?></dd></div>
                            <div><dt>Preferred</dt><dd><?= e((string) $viewing['preferred_date']) ?> · <?= e($humanize($viewing['preferred_time_window'])) ?></dd></div>
                            <?php if (!empty($viewing['scheduled_for'])): ?><div><dt>Scheduled</dt><dd><?= e((string) $viewing['scheduled_for']) ?></dd></div><?php endif; ?>
                        </dl>
                        <?php if (!empty($viewing['company_note'])): ?><p><?= e((string) $viewing['company_note']) ?></p><?php endif; ?>
                        <footer>
                            <a href="<?= e(url('/properties/' . rawurlencode((string) $viewing['listing_slug']))) ?>">View property</a>
                            <?php if (in_array((string) $viewing['status'], ['pending','confirmed','rescheduled'], true)): ?>
                                <form action="<?= e(url('/account/viewings/' . (int) $viewing['id'] . '/cancel')) ?>" method="post" data-account-action data-confirm="Cancel this viewing request?"><?= csrf_field() ?><button class="is-danger" type="submit">Cancel request</button></form>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($activeSection === 'alerts'): ?>
        <div class="account-alert-layout">
            <section class="account-panel">
                <div class="account-panel__head"><h2>Create saved search</h2><span>Daily and weekly matches use Barama's configured notification delivery job and your notification preferences.</span></div>
                <form action="<?= e(url('/account/alerts')) ?>" method="post" data-account-action class="account-form">
                    <?= csrf_field() ?>
                    <label><span>Name</span><input class="form-control" name="name" required maxlength="120" placeholder="e.g. Phnom Penh 2-bedroom condos"></label>
                    <label><span>Keywords</span><input class="form-control" name="query_text" maxlength="100"></label>
                    <div class="account-form__grid">
                        <label><span>Offer</span><select class="form-select" name="transaction_type"><option value="">Sale & rent</option><option value="sale">For sale</option><option value="rent">For rent</option></select></label>
                        <label><span>Property type</span><select class="form-select" name="property_type"><option value="">All types</option><?php foreach ($propertyTypes as $type): ?><option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                        <label><span>Location</span><select class="form-select" name="location_id"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></label>
                        <label><span>Bedrooms</span><input class="form-control" type="number" min="0" step="0.5" name="bedrooms_min"></label>
                        <label><span>Min price</span><input class="form-control" type="number" min="0" step="100" name="price_min"></label>
                        <label><span>Max price</span><input class="form-control" type="number" min="0" step="100" name="price_max"></label>
                        <label><span>Currency</span><input class="form-control" name="currency" value="USD" maxlength="3"></label>
                        <label><span>Frequency</span><select class="form-select" name="alert_frequency"><option value="daily">Daily</option><option value="weekly">Weekly</option></select></label>
                    </div>
                    <label class="workspace-check"><input type="checkbox" name="alerts_enabled" value="1" checked><span>Enable matching-property alerts</span></label>
                    <button class="btn btn-primary" type="submit">Save search</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head"><h2>Saved searches</h2><span><?= e((string) count($savedSearches)) ?> saved</span></div>
                <?php if ($savedSearches === []): ?><div class="account-record-empty">No saved searches yet.</div><?php endif; ?>
                <?php foreach ($savedSearches as $search): ?>
                    <article class="saved-search-row">
                        <div>
                            <strong><?= e((string) $search['name']) ?></strong>
                            <span><?= e(implode(' · ', array_filter([
                                $search['transaction_type'] ? $humanize($search['transaction_type']) : null,
                                $search['property_type'] ? $humanize($search['property_type']) : null,
                                $search['location_name'] ?? null,
                                $search['price_max'] ? 'Up to ' . $search['currency'] . ' ' . number_format((float) $search['price_max'], 0) : null,
                            ]))) ?></span>
                        </div>
                        <div class="saved-search-row__actions">
                            <a href="<?= e($propertySearchUrl($search)) ?>">View matches</a>
                            <form action="<?= e(url('/account/alerts/' . (int) $search['id'])) ?>" method="post" data-account-action>
                                <?= csrf_field() ?>
                                <select name="alert_frequency"><option value="daily" <?= $search['alert_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option><option value="weekly" <?= $search['alert_frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option></select>
                                <label><input type="checkbox" name="alerts_enabled" value="1" <?= !empty($search['alerts_enabled']) ? 'checked' : '' ?>> Alerts</label>
                                <button type="submit">Update</button>
                            </form>
                            <form action="<?= e(url('/account/alerts/' . (int) $search['id'] . '/delete')) ?>" method="post" data-account-action data-confirm="Delete this saved search?"><?= csrf_field() ?><button class="is-danger" type="submit">Delete</button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    <?php elseif ($activeSection === 'notifications'): ?>
        <div class="account-notification-layout">
            <section class="account-panel">
                <div class="account-panel__head">
                    <h2>Notification preferences</h2>
                    <span>Choose which marketplace events can reach you and which delivery channels Barama may use.</span>
                </div>
                <form action="<?= e(url('/account/notification-preferences')) ?>" method="post" data-account-action class="account-form account-notification-preferences">
                    <?= csrf_field() ?>
                    <label class="workspace-check">
                        <input type="checkbox" name="in_app_enabled" value="1" <?= !empty($notificationPreferences['in_app_enabled']) ? 'checked' : '' ?>>
                        <span>In-app notifications</span>
                    </label>
                    <label class="workspace-check">
                        <input type="checkbox" name="email_enabled" value="1" <?= !empty($notificationPreferences['email_enabled']) ? 'checked' : '' ?>>
                        <span>Email notifications</span>
                    </label>
                    <div class="account-preference-divider"></div>
                    <label class="workspace-check">
                        <input type="checkbox" name="saved_search_alerts" value="1" <?= !empty($notificationPreferences['saved_search_alerts']) ? 'checked' : '' ?>>
                        <span>Saved-search property matches</span>
                    </label>
                    <label class="workspace-check">
                        <input type="checkbox" name="viewing_updates" value="1" <?= !empty($notificationPreferences['viewing_updates']) ? 'checked' : '' ?>>
                        <span>Viewing request updates</span>
                    </label>
                    <label class="workspace-check">
                        <input type="checkbox" name="inquiry_updates" value="1" <?= !empty($notificationPreferences['inquiry_updates']) ? 'checked' : '' ?>>
                        <span>Inquiry updates</span>
                    </label>
                    <button class="btn btn-primary" type="submit">Save preferences</button>
                </form>
            </section>

            <section class="account-panel">
                <div class="account-panel__head account-panel__head--actions">
                    <div>
                        <h2>Notification inbox</h2>
                        <span><?= e((string) $summary['notifications']) ?> unread</span>
                    </div>
                    <?php if ($summary['notifications'] > 0): ?>
                        <form action="<?= e(url('/account/notifications/read-all')) ?>" method="post" data-account-action>
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-secondary btn-sm" type="submit">Mark all read</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($notifications === []): ?>
                    <div class="account-empty account-empty--compact">
                        <?= app_icon('bell') ?>
                        <strong>No notifications yet.</strong>
                        <span>Saved-search matches, inquiry updates, and viewing activity will appear here.</span>
                    </div>
                <?php else: ?>
                    <div class="account-notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="account-notification-item <?= empty($notification['read_at']) ? 'is-unread' : '' ?>">
                                <div class="account-notification-item__icon">
                                    <?= app_icon(match ((string) $notification['category']) {
                                        'viewing_updates' => 'calendar',
                                        'inquiry_updates' => 'message',
                                        default => 'bell',
                                    }) ?>
                                </div>
                                <div class="account-notification-item__body">
                                    <div class="account-notification-item__meta">
                                        <span><?= e($humanize($notification['category'])) ?></span>
                                        <time><?= e((string) $notification['created_at']) ?></time>
                                    </div>
                                    <h3><?= e((string) $notification['title']) ?></h3>
                                    <p><?= e((string) $notification['message']) ?></p>
                                    <div class="account-notification-item__actions">
                                        <?php if (!empty($notification['action_url'])): ?>
                                            <a href="<?= e(url((string) $notification['action_url'])) ?>">Open</a>
                                        <?php endif; ?>
                                        <?php if (empty($notification['read_at'])): ?>
                                            <form action="<?= e(url('/account/notifications/' . (int) $notification['id'] . '/read')) ?>" method="post" data-account-action>
                                                <?= csrf_field() ?>
                                                <button type="submit">Mark read</button>
                                            </form>
                                        <?php endif; ?>
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
                <div class="account-panel__head">
                    <h2>Company access</h2>
                    <span>Developer permissions activate only after administrator approval or invitation acceptance.</span>
                </div>

                <?php if ($companyInvitations !== []): ?>
                    <div class="account-invitation-list">
                        <?php foreach ($companyInvitations as $invitation): ?>
                            <article>
                                <span class="account-invitation-list__icon"><?= app_icon('building') ?></span>
                                <div>
                                    <strong><?= e((string) $invitation['company_name']) ?></strong>
                                    <small>Invited as <?= e($humanize($invitation['member_role'])) ?> by <?= e((string) $invitation['invited_by_name']) ?></small>
                                    <small>Expires <?= e((string) $invitation['expires_at']) ?></small>
                                </div>
                                <?php if ($emailVerified): ?>
                                    <form action="<?= e(url('/account/company-invitations/' . (int) $invitation['id'] . '/accept')) ?>" method="post" data-account-action>
                                        <?= csrf_field() ?>
                                        <button class="btn btn-primary btn-sm" type="submit">Accept invitation</button>
                                    </form>
                                <?php else: ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/verify-email')) ?>">Verify email first</a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ownedCompanies !== []): ?>
                    <div class="account-company-status">
                        <?php foreach ($ownedCompanies as $company): ?>
                            <article>
                                <div>
                                    <span class="status-badge status-badge--<?= e((string) $company['status']) ?>"><?= e($humanize($company['status'])) ?></span>
                                    <h3><?= e((string) $company['name']) ?></h3>
                                    <p><?= e($humanize($company['company_type'])) ?><?= !empty($company['email']) ? ' · ' . e((string) $company['email']) : '' ?></p>
                                </div>
                                <?php if ((string) $company['status'] === 'approved' && auth_can('developer.access')): ?>
                                    <a class="btn btn-primary btn-sm" href="<?= e(url('/developer')) ?>">Open workspace</a>
                                <?php elseif ((string) $company['status'] === 'pending'): ?>
                                    <span class="account-review-note"><?= app_icon('clock') ?> Administrator review pending</span>
                                <?php elseif ((string) $company['status'] === 'rejected'): ?>
                                    <span class="account-review-note is-danger"><?= app_icon('alert') ?> Request not approved</span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$emailVerified): ?>
                    <div class="account-review-note">
                        <?= app_icon('mail') ?>
                        Verify your account email before submitting a company for review.
                    </div>
                <?php elseif ($canRequestCompany && !auth_can('developer.access')): ?>
                    <form action="<?= e(url('/account/company/onboard')) ?>" method="post" data-account-action class="account-form account-company-form">
                        <?= csrf_field() ?>
                        <div class="account-form__grid">
                            <label><span>Company name</span><input class="form-control" name="name" required maxlength="180"></label>
                            <label><span>Company type</span>
                                <select class="form-select" name="company_type" required>
                                    <?php foreach ($companyTypes as $type): ?>
                                        <option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><span>Legal name</span><input class="form-control" name="legal_name" maxlength="191"></label>
                            <label><span>Registration number</span><input class="form-control" name="registration_number" maxlength="120"></label>
                            <label><span>Company email</span><input class="form-control" name="email" type="email" value="<?= e((string) $accountUser['email']) ?>" maxlength="191"></label>
                            <label><span>Phone</span><input class="form-control" name="phone" maxlength="60"></label>
                            <label class="account-form__span-2"><span>Location</span>
                                <select class="form-select" name="location_id">
                                    <option value="">Select location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?> · <?= e($humanize($location['type'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="account-form__span-2"><span>Website</span><input class="form-control" name="website" type="url" maxlength="255" placeholder="https://"></label>
                            <label class="account-form__span-2"><span>Company description</span><textarea class="form-control" name="description" rows="4" maxlength="10000"></textarea></label>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= app_icon('building') ?> Submit company for review</button>
                    </form>
                <?php elseif (auth_can('developer.access')): ?>
                    <div class="account-access-ready">
                        <?= app_icon('check') ?>
                        <div><strong>Company workspace access is active.</strong><span>Manage approved company records, team invitations, projects, properties, listings, inquiries, and leads.</span></div>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('/developer')) ?>">Open workspace</a>
                    </div>
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
                                <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="km" <?= $language === 'km' ? 'selected' : '' ?>>Khmer</option>
                                <option value="zh" <?= $language === 'zh' ? 'selected' : '' ?>>Chinese</option>
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
        <?php if ($recentViews === []): ?>
            <div class="account-empty"><?= app_icon('clock') ?><strong>No recent property views yet.</strong><span>Properties you open while signed in will appear here.</span></div>
        <?php else: ?>
            <div class="inventory-grid">
                <?php foreach ($recentViews as $item): ?><?= \Barama\Core\View::partial('components/inventory-card', ['kind' => 'listing', 'item' => $item]) ?><?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
