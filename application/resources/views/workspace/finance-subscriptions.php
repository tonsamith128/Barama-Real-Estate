<?php

declare(strict_types=1);

$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$plans = is_array($plans ?? null) ? $plans : [];
$companies = is_array($companies ?? null) ? $companies : [];
$summary = is_array($financeSummary ?? null) ? $financeSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Subscriptions</h1>
            <p>Assign active plans to companies and control the manual subscription lifecycle before payment gateways are introduced.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Provider-neutral subscription lifecycle
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_subscriptions'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('alert') ?></span><strong><?= e(number_format((int) ($summary['paused_subscriptions'] ?? 0))) ?></strong><small>Paused</small></div>
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['ended_subscriptions'] ?? 0))) ?></strong><small>Ended</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Assign company subscription</span></summary>
        <form action="<?= e(url('/dashboard/subscriptions')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>
            <div class="workspace-form__grid">
                <label><span>Company</span>
                    <select class="form-select" name="company_id" required>
                        <option value="">Select company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) ($company['current_subscription_count'] ?? 0) > 0 ? 'disabled' : '' ?>>
                                <?= e((string) $company['name']) ?><?= (int) ($company['current_subscription_count'] ?? 0) > 0 ? ' · current subscription exists' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Active plan</span>
                    <select class="form-select" name="plan_id" required>
                        <option value="">Select plan</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= e((string) $plan['id']) ?>">
                                <?= e((string) $plan['name']) ?> · <?= e((string) $plan['currency']) ?> <?= e(number_format((float) $plan['price'], 2)) ?> / <?= e((string) $plan['billing_interval']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label><span>Starts at</span><input class="form-control" name="starts_at" type="datetime-local"></label>
                <label><span>Renews at</span><input class="form-control" name="renews_at" type="datetime-local"></label>
                <label><span>Expires at</span><input class="form-control" name="expires_at" type="datetime-local"></label>
                <label><span>External reference</span><input class="form-control" name="external_reference" maxlength="191" placeholder="Optional manual/provider reference"></label>
                <label class="workspace-form__span-2"><span>Internal notes</span><textarea class="form-control" name="notes" rows="3" maxlength="5000"></textarea></label>
            </div>
            <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Assign active subscription</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Company subscriptions</h2>
                <p>Cancelled and expired records are retained as immutable lifecycle history.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Plan</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Starts</th>
                        <th>Renews</th>
                        <th>Expires</th>
                        <th>Source</th>
                        <th>Reference</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($subscriptions === []): ?>
                        <tr><td colspan="11"><div class="workspace-record-empty">No company subscriptions exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($subscriptions as $subscription): ?>
                        <?php $status = (string) ($subscription['status'] ?? 'active'); ?>
                        <tr>
                            <td><strong><?= e((string) $subscription['company_name']) ?></strong><small><?= e((string) $subscription['company_status']) ?></small></td>
                            <td><?= e((string) $subscription['plan_name']) ?></td>
                            <td><?= e((string) $subscription['currency']) ?> <?= e(number_format((float) $subscription['price'], 2)) ?></td>
                            <td><span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'paused' ? 'pending' : 'neutral')) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $subscription['starts_at']) ?></td>
                            <td><?= e((string) ($subscription['renews_at'] ?? '—')) ?></td>
                            <td><?= e((string) ($subscription['expires_at'] ?? '—')) ?></td>
                            <td><?= e((string) $subscription['source']) ?></td>
                            <td><?= e((string) ($subscription['external_reference'] ?? '—')) ?></td>
                            <td><?= e((string) $subscription['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Subscription actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="paused">
                                                <button class="table-action" type="submit">Pause</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                <button class="table-action table-action--danger" type="submit">Cancel</button>
                                            </form>
                                        <?php elseif ($status === 'paused'): ?>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Resume</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/subscriptions/' . (int) $subscription['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                                                <button class="table-action table-action--danger" type="submit">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Lifecycle ended</span>
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
