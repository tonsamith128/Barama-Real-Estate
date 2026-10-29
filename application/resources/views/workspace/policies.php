<?php

declare(strict_types=1);

$policies = is_array($policies ?? null) ? $policies : [];
$policyFeedback = is_array($policyFeedback ?? null) ? $policyFeedback : [];
$policyFeedbackTotals = is_array($policyFeedbackTotals ?? null) ? $policyFeedbackTotals : ['helpful' => 0, 'not_helpful' => 0, 'total' => 0, 'helpful_rate' => 0];
$policyFeedbackReasonLabels = is_array($policyFeedbackReasonLabels ?? null) ? $policyFeedbackReasonLabels : [];
$publishedCount = count(array_filter(
    $policies,
    static fn (array $policy): bool =>
        (string) ($policy['status'] ?? 'draft') === 'published'
));
$draftCount = count($policies) - $publishedCount;
$today = date('Y-m-d');

$compliantCount = 0;
$actionCount = 0;
$reviewDueCount = 0;

foreach ($policies as $policy) {
    $complianceStatus = (string) ($policy['compliance_status'] ?? 'not_reviewed');
    $progress = (int) ($policy['compliance_progress'] ?? 0);
    $nextReview = (string) ($policy['next_review_date'] ?? '');
    $reviewDue = $nextReview !== '' && $nextReview < $today;

    if ($reviewDue) {
        $reviewDueCount++;
    }

    if ($complianceStatus === 'compliant' && $progress >= 100 && !$reviewDue) {
        $compliantCount++;
    }

    if ($complianceStatus === 'action_required' || $reviewDue) {
        $actionCount++;
    }
}
?>
<link rel="stylesheet" href="<?= e(asset('css/policy-center.css') . '?v=1.0.0-css-clean1') ?>">

<main class="pc-admin-main">
    <header class="pc-admin-head">
        <div>
            <span class="pc-kicker">Content &amp; Discovery</span>
            <h1>Policy Center</h1>
            <p>Manage legal policies, compliance reviews, publication state, version history, and public URLs.</p>

            <div class="pc-admin-summary" aria-label="Policy summary">
                <span><strong><?= number_format(count($policies)) ?></strong> Total</span>
                <span><strong><?= number_format($publishedCount) ?></strong> Published</span>
                <span><strong><?= number_format($draftCount) ?></strong> Draft</span>
                <span><strong><?= number_format($compliantCount) ?></strong> Compliant</span>
                <span><strong><?= number_format($actionCount) ?></strong> Needs action</span>
                <span><strong><?= number_format($reviewDueCount) ?></strong> Review due</span>
                <span><strong><?= number_format((int) ($policyFeedbackTotals['total'] ?? 0)) ?></strong> Feedback</span>
                <span><strong><?= number_format((int) ($policyFeedbackTotals['helpful_rate'] ?? 0)) ?>%</strong> Helpful</span>
            </div>
        </div>

        <div class="pc-admin-head__actions">
            <a class="pc-btn pc-category-button" href="<?= e(url('/dashboard/policies/feedback')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19V9"></path>
                    <path d="M10 19V5"></path>
                    <path d="M16 19v-7"></path>
                    <path d="M22 19V3"></path>
                </svg>
                <span>Feedback Analytics</span>
            </a>

            <a class="pc-btn pc-category-button" href="<?= e(url('/dashboard/policies/categories')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h4.3a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.8l-4.8 4.8a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 4 10.8V6.5z"></path>
                    <circle cx="8.2" cy="8.2" r="1.2"></circle>
                </svg>
                <span>Category</span>
            </a>

            <a class="pc-btn pc-primary" href="<?= e(url('/dashboard/policies/create')) ?>">
                + Create New
            </a>
        </div>
    </header>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="pc-success">Policy deleted successfully.</div>
    <?php endif; ?>

    <section class="pc-admin-toolbar">
        <label class="pc-search">
            <span>Search</span>
            <input type="search" placeholder="Search policies..." data-pc-search>
        </label>

        <div class="pc-admin-toolbar__right">
            <label class="pc-compliance-filter">
                <span class="visually-hidden">Compliance</span>
                <select data-pc-compliance-filter aria-label="Filter by legal compliance">
                    <option value="all">All compliance</option>
                    <option value="compliant">Compliant</option>
                    <option value="in_review">In review</option>
                    <option value="action_required">Action required</option>
                    <option value="not_reviewed">Not reviewed</option>
                    <option value="review_due">Review due</option>
                    <option value="not_applicable">Not applicable</option>
                </select>
            </label>

            <div class="pc-filters">
                <button type="button" class="is-active" data-pc-filter="all">All</button>
                <button type="button" data-pc-filter="published">Published</button>
                <button type="button" data-pc-filter="draft">Draft</button>
            </div>
        </div>
    </section>

    <section class="pc-table-wrap">
        <table class="pc-table pc-policy-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Policy</th>
                    <th>Version</th>
                    <th>Compliance</th>
                    <th>Status</th>
                    <th>Visibility</th>
                    <th>Effective</th>
                    <th>Updated</th>
                    <th>Feedback</th>
                    <th class="pc-actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($policies === []): ?>
                    <tr>
                        <td colspan="10" class="pc-empty">
                            <strong>No policies yet.</strong>
                            <span>Create the first policy to populate the public Policy Center.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($policies as $index => $policy): ?>
                        <?php
                        $status = (string) ($policy['status'] ?? 'draft');
                        $id = (string) ($policy['id'] ?? '');
                        $slug = (string) ($policy['slug'] ?? '');
                        $updated = strtotime((string) ($policy['updated_at'] ?? ''));
                        $version = max(1, (int) ($policy['version_number'] ?? 1));
                        $complianceStatus = (string) ($policy['compliance_status'] ?? 'not_reviewed');
                        $progress = max(0, min(100, (int) ($policy['compliance_progress'] ?? 0)));
                        $nextReview = (string) ($policy['next_review_date'] ?? '');
                        $reviewDue = $nextReview !== '' && $nextReview < $today;

                        $feedback = is_array($policyFeedback[$id] ?? null) ? $policyFeedback[$id] : ['helpful' => 0, 'not_helpful' => 0, 'total' => 0, 'helpful_rate' => 0, 'reasons' => []];
                        $feedbackTotal = max(0, (int) ($feedback['total'] ?? 0));
                        $helpfulRate = max(0, min(100, (int) ($feedback['helpful_rate'] ?? 0)));
                        $topReasonKey = ''; $topReasonCount = 0;
                        foreach ((array) ($feedback['reasons'] ?? []) as $reasonKey => $reasonCount) {
                            $reasonCount = max(0, (int) $reasonCount);
                            if ($reasonCount > $topReasonCount) { $topReasonKey = (string) $reasonKey; $topReasonCount = $reasonCount; }
                        }
                        $topReasonLabel = $topReasonKey !== '' ? (string) ($policyFeedbackReasonLabels[$topReasonKey] ?? $topReasonKey) : '';

                        $complianceFilter = $reviewDue
                            ? 'review_due'
                            : $complianceStatus;

                        $complianceLabel = [
                            'not_reviewed' => 'Not reviewed',
                            'in_review' => 'In review',
                            'compliant' => 'Compliant',
                            'action_required' => 'Action required',
                            'not_applicable' => 'N/A',
                        ][$complianceStatus] ?? 'Not reviewed';

                        if ($reviewDue) {
                            $complianceLabel = 'Review due';
                        }

                        $search = mb_strtolower(
                            (string) ($policy['title'] ?? '') . ' '
                            . $slug . ' '
                            . $status . ' '
                            . $complianceLabel . ' '
                            . (string) ($policy['category'] ?? ''),
                            'UTF-8'
                        );
                        ?>
                        <tr
                            data-pc-row
                            data-status="<?= e($status) ?>"
                            data-compliance="<?= e($complianceFilter) ?>"
                            data-search="<?= e($search) ?>"
                        >
                            <td><?= number_format($index + 1) ?></td>

                            <td>
                                <a
                                    class="pc-policy-title-link"
                                    href="<?= e(url('/dashboard/policies/edit?id=' . rawurlencode($id))) ?>"
                                >
                                    <?= e((string) ($policy['title'] ?? 'Untitled')) ?>
                                </a>
                                <small><?= e((string) ($policy['category'] ?? 'General')) ?></small>
                            </td>

                            <td>
                                <span class="pc-version-badge">v<?= number_format($version) ?></span>
                            </td>

                            <td>
                                <div class="pc-compliance-cell">
                                    <span class="pc-compliance-badge pc-compliance-<?= e($complianceFilter) ?>">
                                        <?= e($complianceLabel) ?>
                                    </span>
                                    <div
                                        class="pc-compliance-meter"
                                        title="<?= e((string) $progress) ?>% checklist complete"
                                        aria-label="<?= e((string) $progress) ?>% compliance checklist complete"
                                    >
                                        <span data-barama-width="<?= e((string) $progress) ?>"></span>
                                    </div>
                                    <small><?= number_format($progress) ?>%</small>
                                </div>
                            </td>

                            <td>
                                <span class="pc-status <?= $status === 'published' ? 'pc-published' : 'pc-draft' ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>

                            <td><?= e(ucfirst((string) ($policy['visibility'] ?? 'public'))) ?></td>
                            <td><?= e((string) ($policy['effective_date'] ?: '—')) ?></td>
                            <td><?= e($updated ? date('M j, Y', $updated) : '—') ?></td>
                            <td><div class="pc-policy-feedback-admin"><?php if ($feedbackTotal > 0): ?><strong><?= number_format($helpfulRate) ?>% helpful</strong><span><?= number_format($feedbackTotal) ?> responses</span><?php if ($topReasonLabel !== '' && $topReasonCount > 0): ?><small>Top issue: <?= e($topReasonLabel) ?> (<?= number_format($topReasonCount) ?>)</small><?php endif; ?><?php else: ?><strong>No feedback</strong><span>0 responses</span><?php endif; ?></div></td>

                            <td class="pc-actions-cell">
                                <div class="pc-kebab" data-pc-menu>
                                    <button
                                        type="button"
                                        class="pc-kebab__trigger"
                                        aria-label="Actions for <?= e((string) ($policy['title'] ?? 'policy')) ?>"
                                        aria-expanded="false"
                                        data-pc-menu-trigger
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="5" r="1.8"></circle>
                                            <circle cx="12" cy="12" r="1.8"></circle>
                                            <circle cx="12" cy="19" r="1.8"></circle>
                                        </svg>
                                    </button>

                                    <div class="pc-kebab__menu" role="menu" data-pc-menu-panel>
                                        <button
                                            type="button"
                                            role="menuitem"
                                            data-pc-copy-slug="<?= e($slug) ?>"
                                        >
                                            <svg class="pc-menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                <rect x="8" y="8" width="10" height="10" rx="2"></rect>
                                                <path d="M15 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h2"></path>
                                            </svg>
                                            <span>Copy slug</span>
                                        </button>

                                        <a
                                            role="menuitem"
                                            href="<?= e(url('/dashboard/policies/edit?id=' . rawurlencode($id))) ?>"
                                        >
                                            <svg class="pc-menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M4 20h4l10-10-4-4L4 16v4z"></path>
                                                <path d="M12.5 7.5l4 4"></path>
                                            </svg>
                                            <span>Edit</span>
                                        </a>

                                        <form method="post" action="<?= e(url('/dashboard/policies/duplicate')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($id) ?>">
                                            <button type="submit" role="menuitem">
                                                <svg class="pc-menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                    <rect x="8" y="8" width="10" height="10" rx="2"></rect>
                                                    <rect x="4" y="4" width="10" height="10" rx="2"></rect>
                                                </svg>
                                                <span>Duplicate</span>
                                            </button>
                                        </form>

                                        <form
                                            method="post"
                                            action="<?= e(url('/dashboard/policies/delete')) ?>"
                                            data-pc-delete
                                        >
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e($id) ?>">
                                            <button type="submit" class="danger" role="menuitem">
                                                <svg class="pc-menu-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M5 7h14"></path>
                                                    <path d="M9 7V5h6v2"></path>
                                                    <path d="M8 10v7"></path>
                                                    <path d="M12 10v7"></path>
                                                    <path d="M16 10v7"></path>
                                                    <path d="M7 7l1 13h8l1-13"></path>
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <tr class="pc-no-results" data-pc-no-results hidden>
                    <td colspan="10">
                        <strong>No matching policies.</strong>
                        <span>Adjust the search or filters to see more results.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <nav
        class="pc-pagination"
        aria-label="Policy table pagination"
        data-pc-pagination
        data-page-size="10"
    >
        <div class="pc-pagination__summary" aria-live="polite">
            Showing
            <strong data-pc-page-start>0</strong>–<strong data-pc-page-end>0</strong>
            of <strong data-pc-page-total><?= number_format(count($policies)) ?></strong>
        </div>

        <div class="pc-pagination__pages">
            <button
                type="button"
                class="pc-page-btn pc-page-nav"
                data-pc-page-prev
                aria-label="Previous page"
            >
                Prev
            </button>

            <div class="pc-page-numbers" data-pc-page-numbers></div>

            <button
                type="button"
                class="pc-page-btn pc-page-nav"
                data-pc-page-next
                aria-label="Next page"
            >
                Next
            </button>
        </div>

        <div class="pc-pagination__goto">
            <label for="pc-policy-page-number">Go To</label>
            <input
                id="pc-policy-page-number"
                type="number"
                min="1"
                step="1"
                inputmode="numeric"
                value="1"
                aria-label="Go to policy page"
                data-pc-page-input
            >
            <button type="button" class="pc-page-btn" data-pc-page-go>Go</button>
        </div>
    </nav>

    <div class="pc-copy-toast" role="status" aria-live="polite" data-pc-copy-toast>
        Slug copied
    </div>

    <section class="pc-admin-note pc-admin-note--legal">
        <div>
            <strong>Legal compliance governance</strong>
            <p>
                Each policy now has a review status, risk level, jurisdiction,
                legal basis, owner, evidence, review dates, checklist,
                automatic version snapshots, and restoreable history.
            </p>
        </div>

        <div>
            <strong>Dynamic public URL</strong>
            <code><?= e(url('/policies') . '?={policy-title}') ?></code>
            <p>
                Slugs stay available from the 3-dot menu without using a
                dedicated table column.
            </p>
        </div>
        <div>
            <strong>Privacy-minimized feedback</strong>
            <p>Feedback stores aggregate helpful/not-helpful counts and controlled reason totals only. No IP address, account ID, email, user-agent, free-text comment, or event history is retained.</p>
        </div>
    </section>
</main>

<script defer src="<?= e(asset('js/policy-center-v1.0.6.js') . '?v=policy-fix-20261029') ?>"></script>
