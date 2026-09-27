<?php

declare(strict_types=1);

$companies = is_array($billingCompanies ?? null) ? $billingCompanies : [];

$formatLimit = static function (mixed $limit): string {
    return $limit === null ? 'Unlimited' : number_format((int) $limit);
};
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Company / Vendor Workspace') ?></span>
            <h1>Billing & Plan</h1>
            <p>Review subscription status and current resource usage for every company you can access.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Read-only company billing
        </span>
    </div>

    <?php if ($companies === []): ?>
        <div class="workspace-record-empty">
            No company billing records are available for this account.
        </div>
    <?php endif; ?>

    <?php foreach ($companies as $company): ?>
        <?php
        $subscription = is_array($company['subscription'] ?? null)
            ? $company['subscription']
            : null;
        $usage = is_array($company['usage'] ?? null)
            ? $company['usage']
            : [];

        $status = $subscription !== null
            ? (string) ($subscription['status'] ?? 'unknown')
            : 'legacy';
        ?>
        <section class="workspace-table-section">
            <div class="workspace-table-head">
                <div>
                    <h2><?= e((string) $company['name']) ?></h2>
                    <p>
                        Company status: <?= e(ucfirst((string) $company['company_status'])) ?>
                        · Your role: <?= e(ucfirst((string) $company['member_role'])) ?>
                    </p>
                </div>
                <span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'legacy' ? 'pending' : 'neutral')) ?>">
                    <?= e($status === 'legacy' ? 'Legacy unmetered' : ucfirst($status)) ?>
                </span>
            </div>

            <?php if ($subscription === null): ?>
                <div class="workspace-record-empty">
                    This company has never been enrolled in a subscription plan.
                    Existing behavior remains unmetered until an administrator assigns its first plan.
                </div>
            <?php else: ?>
                <div class="workspace-stat-grid">
                    <div class="workspace-stat">
                        <span><?= app_icon('layers') ?></span>
                        <strong><?= e((string) $subscription['plan_name']) ?></strong>
                        <small><?= e(ucfirst((string) $subscription['billing_interval'])) ?> plan</small>
                    </div>
                    <div class="workspace-stat">
                        <span><?= app_icon('edit') ?></span>
                        <strong><?= e((string) $subscription['currency']) ?> <?= e(number_format((float) $subscription['price'], 2)) ?></strong>
                        <small>Plan price</small>
                    </div>
                    <div class="workspace-stat">
                        <span><?= app_icon('calendar') ?></span>
                        <strong><?= e((string) ($subscription['renews_at'] ?? '—')) ?></strong>
                        <small>Renews</small>
                    </div>
                    <div class="workspace-stat">
                        <span><?= app_icon('calendar') ?></span>
                        <strong><?= e((string) ($subscription['expires_at'] ?? '—')) ?></strong>
                        <small>Expires</small>
                    </div>
                </div>

                <div class="workspace-table-wrap">
                    <table class="workspace-table">
                        <thead>
                            <tr>
                                <th>Entitlement</th>
                                <th>Used</th>
                                <th>Plan limit</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $entitlements = [
                                'Projects' => ['usage' => 'projects', 'limit' => 'project_limit'],
                                'Properties' => ['usage' => 'properties', 'limit' => 'property_limit'],
                                'Listings' => ['usage' => 'listings', 'limit' => 'listing_limit'],
                                'Team seats' => ['usage' => 'team_seats', 'limit' => 'team_member_limit'],
                            ];
                            ?>
                            <?php foreach ($entitlements as $label => $definition): ?>
                                <?php
                                $used = (int) ($usage[$definition['usage']] ?? 0);
                                $limit = $subscription[$definition['limit']];
                                $remaining = $limit === null
                                    ? 'Unlimited'
                                    : number_format(max(0, (int) $limit - $used));
                                ?>
                                <tr>
                                    <td><strong><?= e($label) ?></strong></td>
                                    <td><?= e(number_format($used)) ?></td>
                                    <td><?= e($formatLimit($limit)) ?></td>
                                    <td><?= e($remaining) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td>
                                    <strong>Featured listings</strong>
                                    <small>Reserved until featured-listing lifecycle is implemented.</small>
                                </td>
                                <td>—</td>
                                <td><?= e($formatLimit($subscription['featured_listing_limit'])) ?></td>
                                <td>—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="workspace-record-empty">
                    Active team seats: <?= e(number_format((int) ($usage['active_team_members'] ?? 0))) ?>
                    · Pending invitations reserving seats:
                    <?= e(number_format((int) ($usage['pending_team_invitations'] ?? 0))) ?>.
                    <?php if ($status !== 'active'): ?>
                        New projects, properties, listings, and team-seat allocations are blocked
                        until an active subscription is assigned.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</section>
