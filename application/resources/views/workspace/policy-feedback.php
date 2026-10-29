<?php

declare(strict_types=1);

$policies = is_array($policies ?? null) ? $policies : [];
$policyFeedback = is_array($policyFeedback ?? null) ? $policyFeedback : [];
$policyFeedbackTotals = is_array($policyFeedbackTotals ?? null)
    ? $policyFeedbackTotals
    : ['helpful' => 0, 'not_helpful' => 0, 'total' => 0, 'helpful_rate' => 0];
$policyFeedbackReasonLabels = is_array($policyFeedbackReasonLabels ?? null)
    ? $policyFeedbackReasonLabels
    : [];

$reasonTotals = [];
foreach ($policyFeedbackReasonLabels as $reasonKey => $reasonLabel) {
    $reasonTotals[(string) $reasonKey] = 0;
}

$rows = [];
$categories = [];
$policiesWithFeedback = 0;

foreach ($policies as $policy) {
    $id = (string) ($policy['id'] ?? '');
    $title = trim((string) ($policy['title'] ?? 'Untitled'));
    $category = trim((string) ($policy['category'] ?? 'General'));
    $category = $category !== '' ? $category : 'General';
    $status = (string) ($policy['status'] ?? 'draft');
    $slug = (string) ($policy['slug'] ?? '');

    $summary = is_array($policyFeedback[$id] ?? null)
        ? $policyFeedback[$id]
        : [
            'helpful' => 0,
            'not_helpful' => 0,
            'total' => 0,
            'helpful_rate' => 0,
            'reasons' => [],
            'updated_at' => '',
        ];

    $helpful = max(0, (int) ($summary['helpful'] ?? 0));
    $notHelpful = max(0, (int) ($summary['not_helpful'] ?? 0));
    $total = max(0, (int) ($summary['total'] ?? ($helpful + $notHelpful)));
    $helpfulRate = max(0, min(100, (int) ($summary['helpful_rate'] ?? 0)));
    $updatedAt = trim((string) ($summary['updated_at'] ?? ''));
    $updatedTimestamp = $updatedAt !== '' ? strtotime($updatedAt) : false;

    if ($total > 0) {
        $policiesWithFeedback++;
    }

    $topReasonKey = '';
    $topReasonCount = 0;

    foreach ((array) ($summary['reasons'] ?? []) as $reasonKey => $reasonCount) {
        $reasonKey = (string) $reasonKey;
        $reasonCount = max(0, (int) $reasonCount);

        if (!array_key_exists($reasonKey, $reasonTotals)) {
            $reasonTotals[$reasonKey] = 0;
        }

        $reasonTotals[$reasonKey] += $reasonCount;

        if ($reasonCount > $topReasonCount) {
            $topReasonKey = $reasonKey;
            $topReasonCount = $reasonCount;
        }
    }

    $topReasonLabel = $topReasonKey !== ''
        ? (string) ($policyFeedbackReasonLabels[$topReasonKey] ?? $topReasonKey)
        : '—';

    $rows[] = [
        'id' => $id,
        'title' => $title,
        'category' => $category,
        'status' => $status,
        'slug' => $slug,
        'helpful' => $helpful,
        'not_helpful' => $notHelpful,
        'total' => $total,
        'helpful_rate' => $helpfulRate,
        'top_reason' => $topReasonLabel,
        'top_reason_count' => $topReasonCount,
        'updated_at' => $updatedAt,
        'updated_timestamp' => $updatedTimestamp ?: 0,
    ];

    $categories[$category] = true;
}

$categoryOptions = array_keys($categories);
natcasesort($categoryOptions);
$categoryOptions = array_values($categoryOptions);

$rankable = array_values(array_filter(
    $rows,
    static fn (array $row): bool => (int) ($row['total'] ?? 0) > 0
));

$mostHelpful = $rankable;
usort(
    $mostHelpful,
    static function (array $a, array $b): int {
        $rateCompare = ((int) $b['helpful_rate']) <=> ((int) $a['helpful_rate']);
        if ($rateCompare !== 0) {
            return $rateCompare;
        }

        $responseCompare = ((int) $b['total']) <=> ((int) $a['total']);
        if ($responseCompare !== 0) {
            return $responseCompare;
        }

        return strcasecmp((string) $a['title'], (string) $b['title']);
    }
);
$mostHelpful = array_slice($mostHelpful, 0, 5);

$needsAttention = $rankable;
usort(
    $needsAttention,
    static function (array $a, array $b): int {
        $rateCompare = ((int) $a['helpful_rate']) <=> ((int) $b['helpful_rate']);
        if ($rateCompare !== 0) {
            return $rateCompare;
        }

        $responseCompare = ((int) $b['total']) <=> ((int) $a['total']);
        if ($responseCompare !== 0) {
            return $responseCompare;
        }

        return strcasecmp((string) $a['title'], (string) $b['title']);
    }
);
$needsAttention = array_slice($needsAttention, 0, 5);

$reasonSelections = array_sum($reasonTotals);
$maxReasonCount = $reasonTotals !== [] ? max($reasonTotals) : 0;
$totalPolicies = count($policies);
$coverageRate = $totalPolicies > 0
    ? (int) round(($policiesWithFeedback / $totalPolicies) * 100)
    : 0;
?>
<?= barama_css_once('policy-center') ?>

<main class="pc-admin-main pc-feedback-analytics" data-pc-feedback-analytics>
    <header class="pc-admin-head pc-feedback-analytics__head">
        <div>
            <span class="pc-kicker">Policy Center</span>
            <h1>Feedback Analytics</h1>
            <p>
                Review privacy-minimized policy feedback, response quality signals,
                and controlled reason trends.
            </p>
        </div>

        <div class="pc-admin-head__actions">
            <a
                class="pc-btn pc-category-button"
                href="<?= e(url('/dashboard/policies/feedback/review')) ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 3 20h18z"></path>
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                </svg>
                <span>Needs Review</span>
            </a>

            <button
                type="button"
                class="pc-btn pc-category-button"
                data-pc-feedback-export
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3v12"></path>
                    <path d="m7 10 5 5 5-5"></path>
                    <path d="M5 21h14"></path>
                </svg>
                <span>Export CSV</span>
            </button>

            <a class="pc-btn pc-primary" href="<?= e(url('/dashboard/policies')) ?>">
                ← Policy Center
            </a>
        </div>
    </header>

    <section class="pc-feedback-kpis" aria-label="Policy feedback summary">
        <article>
            <span>Total responses</span>
            <strong><?= number_format((int) ($policyFeedbackTotals['total'] ?? 0)) ?></strong>
            <small>Helpful + not helpful</small>
        </article>

        <article>
            <span>Helpful rate</span>
            <strong><?= number_format((int) ($policyFeedbackTotals['helpful_rate'] ?? 0)) ?>%</strong>
            <small>Across all recorded feedback</small>
        </article>

        <article>
            <span>Helpful</span>
            <strong><?= number_format((int) ($policyFeedbackTotals['helpful'] ?? 0)) ?></strong>
            <small>Positive responses</small>
        </article>

        <article>
            <span>Not helpful</span>
            <strong><?= number_format((int) ($policyFeedbackTotals['not_helpful'] ?? 0)) ?></strong>
            <small>Improvement signals</small>
        </article>

        <article>
            <span>Coverage</span>
            <strong><?= number_format($coverageRate) ?>%</strong>
            <small><?= number_format($policiesWithFeedback) ?> of <?= number_format($totalPolicies) ?> policies</small>
        </article>
    </section>

    <section class="pc-feedback-insights">
        <article class="pc-feedback-panel">
            <header>
                <div>
                    <span class="pc-kicker">Ranking</span>
                    <h2>Most helpful</h2>
                </div>
                <small>Rate, then response volume</small>
            </header>

            <?php if ($mostHelpful === []): ?>
                <div class="pc-feedback-empty">No feedback has been recorded yet.</div>
            <?php else: ?>
                <ol class="pc-feedback-ranking">
                    <?php foreach ($mostHelpful as $row): ?>
                        <li>
                            <a href="<?= e(url('/dashboard/policies/feedback/detail?id=' . rawurlencode((string) $row['id']))) ?>">
                                <span>
                                    <strong><?= e((string) $row['title']) ?></strong>
                                    <small><?= e((string) $row['category']) ?></small>
                                </span>
                                <span class="pc-feedback-ranking__metric">
                                    <strong><?= number_format((int) $row['helpful_rate']) ?>%</strong>
                                    <small><?= number_format((int) $row['total']) ?> responses</small>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <article class="pc-feedback-panel">
            <header>
                <div>
                    <span class="pc-kicker">Review</span>
                    <h2>Needs attention</h2>
                </div>
                <small>Lowest helpful rate first</small>
            </header>

            <?php if ($needsAttention === []): ?>
                <div class="pc-feedback-empty">No feedback has been recorded yet.</div>
            <?php else: ?>
                <ol class="pc-feedback-ranking pc-feedback-ranking--attention">
                    <?php foreach ($needsAttention as $row): ?>
                        <li>
                            <a href="<?= e(url('/dashboard/policies/feedback/detail?id=' . rawurlencode((string) $row['id']))) ?>">
                                <span>
                                    <strong><?= e((string) $row['title']) ?></strong>
                                    <small><?= e((string) $row['top_reason']) ?></small>
                                </span>
                                <span class="pc-feedback-ranking__metric">
                                    <strong><?= number_format((int) $row['helpful_rate']) ?>%</strong>
                                    <small><?= number_format((int) $row['total']) ?> responses</small>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <article class="pc-feedback-panel pc-feedback-panel--reasons">
            <header>
                <div>
                    <span class="pc-kicker">Reasons</span>
                    <h2>Not-helpful breakdown</h2>
                </div>
                <small><?= number_format($reasonSelections) ?> reason selections</small>
            </header>

            <div class="pc-feedback-reasons">
                <?php foreach ($policyFeedbackReasonLabels as $reasonKey => $reasonLabel): ?>
                    <?php
                    $count = max(0, (int) ($reasonTotals[(string) $reasonKey] ?? 0));
                    $width = $maxReasonCount > 0
                        ? (int) round(($count / $maxReasonCount) * 100)
                        : 0;
                    ?>
                    <div class="pc-feedback-reason">
                        <div>
                            <span><?= e((string) $reasonLabel) ?></span>
                            <strong><?= number_format($count) ?></strong>
                        </div>
                        <div class="pc-feedback-reason__track" aria-hidden="true">
                            <span data-barama-width="<?= e((string) $width) ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="pc-feedback-panel__note">
                Reason selection is optional, so reason totals can be lower than
                the number of not-helpful responses.
            </p>
        </article>
    </section>

    <div class="pc-feedback-drilldown-note">
        Select a policy title or ranking item to open its aggregate feedback detail.
    </div>

    <section class="pc-feedback-toolbar">
        <label class="pc-search">
            <span>Search</span>
            <input
                type="search"
                placeholder="Search policy or category..."
                data-pc-feedback-search
            >
        </label>

        <div class="pc-feedback-toolbar__controls">
            <label>
                <span class="visually-hidden">Category</span>
                <select data-pc-feedback-category aria-label="Filter by category">
                    <option value="all">All categories</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= e(mb_strtolower($category, 'UTF-8')) ?>">
                            <?= e($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="visually-hidden">Feedback signal</span>
                <select data-pc-feedback-signal aria-label="Filter by feedback signal">
                    <option value="all">All signals</option>
                    <option value="strong">80–100% helpful</option>
                    <option value="mixed">50–79% helpful</option>
                    <option value="attention">Below 50% helpful</option>
                    <option value="none">No feedback</option>
                </select>
            </label>

            <label>
                <span class="visually-hidden">Sort feedback</span>
                <select data-pc-feedback-sort aria-label="Sort policy feedback">
                    <option value="responses_desc">Most responses</option>
                    <option value="rate_desc">Helpful rate: high to low</option>
                    <option value="rate_asc">Helpful rate: low to high</option>
                    <option value="title_asc">Policy: A–Z</option>
                    <option value="updated_desc">Latest feedback</option>
                </select>
            </label>
        </div>
    </section>

    <section class="pc-table-wrap pc-feedback-table-wrap">
        <table class="pc-table pc-feedback-table">
            <thead>
                <tr>
                    <th>Policy</th>
                    <th>Helpful</th>
                    <th>Not helpful</th>
                    <th>Responses</th>
                    <th>Helpful rate</th>
                    <th>Top issue</th>
                    <th>Last feedback</th>
                </tr>
            </thead>

            <tbody data-pc-feedback-rows>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $searchValue = mb_strtolower(
                        (string) $row['title'] . ' '
                        . (string) $row['category'] . ' '
                        . (string) $row['slug'],
                        'UTF-8'
                    );
                    ?>
                    <tr
                        data-pc-feedback-row
                        data-title="<?= e(mb_strtolower((string) $row['title'], 'UTF-8')) ?>"
                        data-category="<?= e(mb_strtolower((string) $row['category'], 'UTF-8')) ?>"
                        data-search="<?= e($searchValue) ?>"
                        data-helpful="<?= e((string) $row['helpful']) ?>"
                        data-not-helpful="<?= e((string) $row['not_helpful']) ?>"
                        data-total="<?= e((string) $row['total']) ?>"
                        data-rate="<?= e((string) $row['helpful_rate']) ?>"
                        data-updated="<?= e((string) $row['updated_timestamp']) ?>"
                    >
                        <td>
                            <a
                                class="pc-policy-title-link"
                                href="<?= e(url('/dashboard/policies/feedback/detail?id=' . rawurlencode((string) $row['id']))) ?>"
                            >
                                <?= e((string) $row['title']) ?>
                            </a>
                            <small><?= e((string) $row['category']) ?></small>
                        </td>

                        <td><?= number_format((int) $row['helpful']) ?></td>
                        <td><?= number_format((int) $row['not_helpful']) ?></td>
                        <td><?= number_format((int) $row['total']) ?></td>

                        <td>
                            <div class="pc-feedback-rate">
                                <strong><?= number_format((int) $row['helpful_rate']) ?>%</strong>
                                <span>
                                    <i data-barama-width="<?= e((string) $row['helpful_rate']) ?>"></i>
                                </span>
                            </div>
                        </td>

                        <td>
                            <span class="pc-feedback-top-issue">
                                <?= e((string) $row['top_reason']) ?>
                                <?php if ((int) $row['top_reason_count'] > 0): ?>
                                    <small><?= number_format((int) $row['top_reason_count']) ?></small>
                                <?php endif; ?>
                            </span>
                        </td>

                        <td>
                            <?php if ((int) $row['updated_timestamp'] > 0): ?>
                                <?= e(date('M j, Y', (int) $row['updated_timestamp'])) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr data-pc-feedback-empty hidden>
                    <td colspan="7" class="pc-empty">
                        <strong>No matching policy feedback.</strong>
                        <span>Adjust the search or filter controls.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <footer class="pc-feedback-table-footer">
        <span>
            Showing <strong data-pc-feedback-visible><?= number_format(count($rows)) ?></strong>
            of <?= number_format(count($rows)) ?> policies
        </span>

        <span>Aggregate-only analytics · no personal identifiers</span>
    </footer>
</main>

<?= barama_js_once('policy-center') ?><script type="application/x-barama-module" data-barama-module="policy-center-feedback-analytics"></script>
