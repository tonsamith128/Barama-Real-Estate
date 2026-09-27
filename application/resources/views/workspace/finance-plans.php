<?php

declare(strict_types=1);

$plans = is_array($plans ?? null) ? $plans : [];
$summary = is_array($financeSummary ?? null) ? $financeSummary : [];

$money = static function (mixed $amount, mixed $currency): string {
    return strtoupper((string) $currency) . ' ' . number_format((float) $amount, 2);
};

$limit = static fn (mixed $value): string =>
    $value === null ? 'Unlimited' : number_format((int) $value);
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Plans & Packages</h1>
            <p>Define commercial entitlements independently from payment providers. New plans start inactive and must be activated before assignment.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Administrator billing controls
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['plans'] ?? 0))) ?></strong><small>Plans</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_plans'] ?? 0))) ?></strong><small>Active plans</small></div>
        <div class="workspace-stat"><span><?= app_icon('building') ?></span><strong><?= e(number_format((int) ($summary['active_subscriptions'] ?? 0))) ?></strong><small>Active subscriptions</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Create plan</span></summary>
        <form action="<?= e(url('/dashboard/plans')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>
            <div class="workspace-form__grid">
                <label><span>Name</span><input class="form-control" name="name" maxlength="160" required></label>
                <label><span>Slug</span><input class="form-control" name="slug" maxlength="160" placeholder="auto-from-name"></label>
                <label><span>Billing interval</span>
                    <select class="form-select" name="billing_interval" required>
                        <?php foreach (['monthly','quarterly','yearly','lifetime','manual'] as $interval): ?>
                            <option value="<?= e($interval) ?>"><?= e(ucwords(str_replace('_', ' ', $interval))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Price</span><input class="form-control" name="price" type="number" min="0" step="0.01" value="0.00" required></label>
                <label><span>Currency</span><input class="form-control" name="currency" maxlength="3" value="USD" required></label>
                <label><span>Sort order</span><input class="form-control" name="sort_order" type="number" value="0"></label>
                <label><span>Listing limit</span><input class="form-control" name="listing_limit" type="number" min="0" placeholder="Unlimited"></label>
                <label><span>Property limit</span><input class="form-control" name="property_limit" type="number" min="0" placeholder="Unlimited"></label>
                <label><span>Project limit</span><input class="form-control" name="project_limit" type="number" min="0" placeholder="Unlimited"></label>
                <label><span>Team member limit</span><input class="form-control" name="team_member_limit" type="number" min="0" placeholder="Unlimited"></label>
                <label><span>Featured listing limit</span><input class="form-control" name="featured_listing_limit" type="number" min="0" placeholder="Unlimited"></label>
                <label class="workspace-form__span-2"><span>Description</span><textarea class="form-control" name="description" rows="4" maxlength="5000"></textarea></label>
            </div>
            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create inactive plan</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Configured plans</h2>
                <p>Commercial definitions only. Payments and invoices are intentionally not part of this foundation.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Price</th>
                        <th>Interval</th>
                        <th>Listings</th>
                        <th>Properties</th>
                        <th>Projects</th>
                        <th>Team</th>
                        <th>Featured</th>
                        <th>Current subscriptions</th>
                        <th>Status</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($plans === []): ?>
                        <tr><td colspan="11"><div class="workspace-record-empty">No plans exist yet. Create the first commercial plan when your pricing model is ready.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($plans as $plan): ?>
                        <?php $status = (string) ($plan['status'] ?? 'inactive'); ?>
                        <tr>
                            <td><strong><?= e((string) $plan['name']) ?></strong><small><?= e((string) $plan['slug']) ?></small></td>
                            <td><?= e($money($plan['price'] ?? 0, $plan['currency'] ?? 'USD')) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', (string) $plan['billing_interval']))) ?></td>
                            <td><?= e($limit($plan['listing_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['property_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['project_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['team_member_limit'] ?? null)) ?></td>
                            <td><?= e($limit($plan['featured_listing_limit'] ?? null)) ?></td>
                            <td><?= e(number_format((int) ($plan['current_subscriptions'] ?? 0))) ?></td>
                            <td><span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'archived' ? 'neutral' : 'pending')) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Plan actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                                                <button class="table-action table-action--danger" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/plans/' . (int) $plan['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="inactive">
                                                <button class="table-action" type="submit">Deactivate</button>
                                            </form>
                                        <?php elseif ($status === 'archived'): ?>
                                            <span class="table-action-placeholder">Archived</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
