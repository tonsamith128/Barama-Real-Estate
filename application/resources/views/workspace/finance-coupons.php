<?php

declare(strict_types=1);

$coupons = is_array($coupons ?? null) ? $coupons : [];
$summary = is_array($couponSummary ?? null) ? $couponSummary : [];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Coupons</h1>
            <p>Create controlled percentage or fixed discounts. New coupons start inactive and must be activated before invoice use.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('shield') ?>
            Invoice-time discount snapshots
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['coupons'] ?? 0))) ?></strong><small>Coupons</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active_coupons'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('building') ?></span><strong><?= e(number_format((int) ($summary['effective_redemptions'] ?? 0))) ?></strong><small>Effective redemptions</small></div>
    </div>

    <details class="workspace-create">
        <summary><?= app_icon('plus') ?><span>Create coupon</span></summary>
        <form action="<?= e(url('/dashboard/coupons')) ?>" method="post" data-workspace-form class="workspace-form">
            <?= csrf_field() ?>

            <div class="workspace-form__grid">
                <label><span>Code</span><input class="form-control" name="code" maxlength="64" required placeholder="WELCOME10"></label>
                <label><span>Name</span><input class="form-control" name="name" maxlength="160" required></label>

                <label><span>Discount type</span>
                    <select class="form-select" name="discount_type" required>
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed amount</option>
                    </select>
                </label>

                <label><span>Discount value</span><input class="form-control" name="discount_value" type="number" min="0.01" step="0.01" required></label>
                <label><span>Currency <small>Fixed discounts only</small></span><input class="form-control" name="currency" maxlength="3" placeholder="USD"></label>
                <label><span>Maximum redemptions</span><input class="form-control" name="max_redemptions" type="number" min="1" placeholder="Unlimited"></label>
                <label><span>Per-company limit</span><input class="form-control" name="per_company_limit" type="number" min="1" placeholder="Unlimited"></label>
                <label><span>Starts at</span><input class="form-control" name="starts_at" type="datetime-local"></label>
                <label><span>Ends at</span><input class="form-control" name="ends_at" type="datetime-local"></label>
                <label class="workspace-form__span-2"><span>Description</span><textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea></label>
            </div>

            <button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Create inactive coupon</button>
        </form>
    </details>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Configured coupons</h2>
                <p>Voided invoices remain in redemption history but do not consume effective coupon limits.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Coupon</th>
                        <th>Discount</th>
                        <th>Window</th>
                        <th>Global limit</th>
                        <th>Company limit</th>
                        <th>Effective uses</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($coupons === []): ?>
                        <tr><td colspan="9"><div class="workspace-record-empty">No coupons exist yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($coupons as $coupon): ?>
                        <?php
                        $status = (string) ($coupon['status'] ?? 'inactive');
                        $type = (string) ($coupon['discount_type'] ?? 'percent');
                        $discount = $type === 'percent'
                            ? number_format((float) $coupon['discount_value'], 2) . '%'
                            : (string) ($coupon['currency'] ?? '') . ' ' . number_format((float) $coupon['discount_value'], 2);
                        ?>
                        <tr>
                            <td><strong><?= e((string) $coupon['code']) ?></strong><small><?= e((string) $coupon['name']) ?></small></td>
                            <td><?= e($discount) ?></td>
                            <td>
                                <small>From: <?= e((string) ($coupon['starts_at'] ?? 'Any time')) ?></small>
                                <small>Until: <?= e((string) ($coupon['ends_at'] ?? 'No expiry')) ?></small>
                            </td>
                            <td><?= $coupon['max_redemptions'] === null ? 'Unlimited' : e(number_format((int) $coupon['max_redemptions'])) ?></td>
                            <td><?= $coupon['per_company_limit'] === null ? 'Unlimited' : e(number_format((int) $coupon['per_company_limit'])) ?></td>
                            <td><?= e(number_format((int) ($coupon['effective_redemptions'] ?? 0))) ?></td>
                            <td><span class="status-badge status-badge--<?= e($status === 'active' ? 'approved' : ($status === 'archived' ? 'neutral' : 'pending')) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $coupon['updated_at']) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Coupon actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="archived">
                                                <button class="table-action table-action--danger" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/coupons/' . (int) $coupon['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?><input type="hidden" name="status" value="inactive">
                                                <button class="table-action" type="submit">Deactivate</button>
                                            </form>
                                        <?php else: ?>
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
