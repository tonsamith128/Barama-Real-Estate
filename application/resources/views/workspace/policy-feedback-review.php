<?php

declare(strict_types=1);

$policies = is_array($policies ?? null) ? $policies : [];
$policyFeedback = is_array($policyFeedback ?? null) ? $policyFeedback : [];
$policyFeedbackReasonLabels = is_array($policyFeedbackReasonLabels ?? null)
    ? $policyFeedbackReasonLabels
    : [];

$thresholds = [
    'critical' => [
        'label' => 'Critical',
        'min_responses' => 10,
        'max_helpful_rate' => 39,
        'weight' => 300,
    ],
    'high' => [
        'label' => 'High',
        'min_responses' => 5,
        'max_helpful_rate' => 49,
        'weight' => 200,
    ],
    'medium' => [
        'label' => 'Medium',
        'min_responses' => 3,
        'max_helpful_rate' => 69,
        'weight' => 100,
    ],
];

$queue = [];
$severityCounts = [
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
];
$categories = [];

$recommendationMap = [
    'hard_to_understand' => [
        'label' => 'Clarify wording',
        'summary' => 'Simplify complex sentences, define important terms, and add a short example where useful.',
    ],
    'missing_information' => [
        'label' => 'Add missing information',
        'summary' => 'Fill the most likely information gaps, including scope, responsibilities, exceptions, or next steps.',
    ],
    'too_long' => [
        'label' => 'Shorten content',
        'summary' => 'Remove repetition, split long sections, and move secondary detail out of the main reading flow.',
    ],
    'out_of_date' => [
        'label' => 'Review outdated sections',
        'summary' => 'Revalidate dates, contacts, third-party references, process steps, and current policy behavior.',
    ],
    'other' => [
        'label' => 'Review reader friction',
        'summary' => 'Inspect structure and recent changes for issues not captured by the standard reason choices.',
    ],
];

foreach ($policies as $policy) {
    $policyId = (string) ($policy['id'] ?? '');
    $title = trim((string) ($policy['title'] ?? 'Untitled'));
    $category = trim((string) ($policy['category'] ?? 'General'));
    $category = $category !== '' ? $category : 'General';
    $slug = trim((string) ($policy['slug'] ?? ''));
    $status = trim((string) ($policy['status'] ?? 'draft'));

    $feedback = is_array($policyFeedback[$policyId] ?? null)
        ? $policyFeedback[$policyId]
        : [
            'helpful' => 0,
            'not_helpful' => 0,
            'total' => 0,
            'helpful_rate' => 0,
            'reasons' => [],
            'updated_at' => '',
        ];

    $helpful = max(0, (int) ($feedback['helpful'] ?? 0));
    $notHelpful = max(0, (int) ($feedback['not_helpful'] ?? 0));
    $total = max(0, (int) ($feedback['total'] ?? ($helpful + $notHelpful)));
    $helpfulRate = max(0, min(100, (int) ($feedback['helpful_rate'] ?? 0)));

    $severity = '';
    $severityLabel = '';
    $severityWeight = 0;

    foreach ($thresholds as $key => $threshold) {
        if (
            $total >= (int) $threshold['min_responses']
            && $helpfulRate <= (int) $threshold['max_helpful_rate']
        ) {
            $severity = $key;
            $severityLabel = (string) $threshold['label'];
            $severityWeight = (int) $threshold['weight'];
            break;
        }
    }

    if ($severity === '') {
        continue;
    }

    $topReasonKey = '';
    $topReasonCount = 0;

    foreach ((array) ($feedback['reasons'] ?? []) as $reasonKey => $reasonCount) {
        $reasonCount = max(0, (int) $reasonCount);

        if ($reasonCount > $topReasonCount) {
            $topReasonKey = (string) $reasonKey;
            $topReasonCount = $reasonCount;
        }
    }

    $topReasonLabel = $topReasonKey !== ''
        ? (string) ($policyFeedbackReasonLabels[$topReasonKey] ?? $topReasonKey)
        : '—';

    $reasonTotal = array_sum(array_map(
        static fn ($value): int => max(0, (int) $value),
        (array) ($feedback['reasons'] ?? [])
    ));

    $reasonShare = $reasonTotal > 0 && $topReasonCount > 0
        ? (int) round(($topReasonCount / $reasonTotal) * 100)
        : 0;

    $recommendation = $topReasonKey !== ''
        && isset($recommendationMap[$topReasonKey])
            ? $recommendationMap[$topReasonKey]
            : [
                'label' => 'Review overall clarity',
                'summary' => 'No dominant reason was selected. Review structure, wording, completeness, and recent changes together.',
            ];

    if ($total >= 20) {
        $confidence = 'high';
        $confidenceLabel = 'High';
    } elseif ($total >= 10) {
        $confidence = 'medium';
        $confidenceLabel = 'Medium';
    } elseif ($total >= 5) {
        $confidence = 'emerging';
        $confidenceLabel = 'Emerging';
    } else {
        $confidence = 'limited';
        $confidenceLabel = 'Limited';
    }

    $feedbackUpdatedAt = trim((string) ($feedback['updated_at'] ?? ''));
    $feedbackUpdatedTimestamp = $feedbackUpdatedAt !== ''
        ? strtotime($feedbackUpdatedAt)
        : false;

    $priorityScore = $severityWeight
        + min(99, $notHelpful * 3)
        + min(99, $total);

    $targetRate = match ($severity) {
        'critical' => 40,
        'high' => 50,
        'medium' => 70,
        default => 0,
    };

    $additionalHelpfulNeeded = 0;

    if ($targetRate > 0 && $helpfulRate < $targetRate) {
        $numerator = ($targetRate * $total) - (100 * $helpful);
        $denominator = 100 - $targetRate;

        $additionalHelpfulNeeded = max(
            0,
            (int) ceil($numerator / $denominator)
        );
    }

    $projectedTotal = $total + $additionalHelpfulNeeded;
    $projectedHelpful = $helpful + $additionalHelpfulNeeded;
    $projectedRate = $projectedTotal > 0
        ? (int) floor(($projectedHelpful / $projectedTotal) * 100)
        : 0;

    $queue[] = [
        'id' => $policyId,
        'title' => $title,
        'category' => $category,
        'slug' => $slug,
        'status' => $status,
        'severity' => $severity,
        'severity_label' => $severityLabel,
        'priority_score' => $priorityScore,
        'helpful' => $helpful,
        'not_helpful' => $notHelpful,
        'total' => $total,
        'helpful_rate' => $helpfulRate,
        'top_reason' => $topReasonLabel,
        'top_reason_count' => $topReasonCount,
        'reason_share' => $reasonShare,
        'recommendation' => (string) $recommendation['label'],
        'recommendation_summary' => (string) $recommendation['summary'],
        'confidence' => $confidence,
        'confidence_label' => $confidenceLabel,
        'target_rate' => $targetRate,
        'additional_helpful_needed' => $additionalHelpfulNeeded,
        'projected_rate' => $projectedRate,
        'feedback_updated_timestamp' => $feedbackUpdatedTimestamp ?: 0,
    ];

    $severityCounts[$severity]++;
    $categories[$category] = true;
}

usort(
    $queue,
    static function (array $a, array $b): int {
        $priorityCompare = ((int) $b['priority_score']) <=> ((int) $a['priority_score']);

        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }

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

$categoryOptions = array_keys($categories);
natcasesort($categoryOptions);
$categoryOptions = array_values($categoryOptions);

$queueTotal = count($queue);
$criticalCount = (int) ($severityCounts['critical'] ?? 0);
$highCount = (int) ($severityCounts['high'] ?? 0);
$mediumCount = (int) ($severityCounts['medium'] ?? 0);
?>
<link rel="stylesheet" href="<?= e(asset('css/policy-center.css') . '?v=1.0.0-css-clean1') ?>">

<main class="pc-admin-main pc-feedback-review" data-pc-feedback-review>
    <header class="pc-admin-head pc-feedback-review__head">
        <div>
            <span class="pc-kicker">Feedback Analytics</span>
            <h1>Needs Review</h1>
            <p>
                Prioritized policy review queue derived only from aggregate
                helpfulness signals and minimum response thresholds.
            </p>
        </div>

        <div class="pc-admin-head__actions">
            <a
                class="pc-btn pc-category-button"
                href="<?= e(url('/dashboard/policies/feedback')) ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19V9"></path>
                    <path d="M10 19V5"></path>
                    <path d="M16 19v-7"></path>
                    <path d="M22 19V3"></path>
                </svg>
                <span>Analytics</span>
            </a>

            <a class="pc-btn pc-primary" href="<?= e(url('/dashboard/policies')) ?>">
                ← Policy Center
            </a>
        </div>
    </header>

    <section class="pc-review-kpis" aria-label="Review queue summary">
        <article>
            <span>Needs review</span>
            <strong><?= number_format($queueTotal) ?></strong>
            <small>Policies meeting a review threshold</small>
        </article>

        <article class="is-critical">
            <span>Critical</span>
            <strong><?= number_format($criticalCount) ?></strong>
            <small>≥10 responses · below 40% helpful</small>
        </article>

        <article class="is-high">
            <span>High</span>
            <strong><?= number_format($highCount) ?></strong>
            <small>≥5 responses · below 50% helpful</small>
        </article>

        <article class="is-medium">
            <span>Medium</span>
            <strong><?= number_format($mediumCount) ?></strong>
            <small>≥3 responses · below 70% helpful</small>
        </article>
    </section>

    <section class="pc-review-thresholds" aria-labelledby="pc-review-thresholds-title">
        <div>
            <span class="pc-kicker">How priority works</span>
            <h2 id="pc-review-thresholds-title">Review thresholds</h2>
        </div>

        <div class="pc-review-threshold-list">
            <span class="pc-review-threshold is-critical">
                <strong>Critical</strong>
                ≥10 responses and &lt;40% helpful
            </span>

            <span class="pc-review-threshold is-high">
                <strong>High</strong>
                ≥5 responses and &lt;50% helpful
            </span>

            <span class="pc-review-threshold is-medium">
                <strong>Medium</strong>
                ≥3 responses and &lt;70% helpful
            </span>
        </div>

        <p>
            Small samples below three responses are intentionally excluded.
            Priority then considers severity, not-helpful volume, and total responses.
            Recommendation confidence is based only on sample size:
            High 20+, Medium 10–19, Emerging 5–9, Limited 3–4.
        </p>
    </section>

    <section class="pc-review-toolbar">
        <label class="pc-search">
            <span>Search</span>
            <input
                type="search"
                placeholder="Search policy, category or issue..."
                data-pc-review-search
            >
        </label>

        <div class="pc-review-toolbar__controls">
            <label>
                <span class="visually-hidden">Severity</span>
                <select data-pc-review-severity aria-label="Filter by severity">
                    <option value="all">All severities</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                </select>
            </label>

            <label>
                <span class="visually-hidden">Category</span>
                <select data-pc-review-category aria-label="Filter by category">
                    <option value="all">All categories</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= e(mb_strtolower($category, 'UTF-8')) ?>">
                            <?= e($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="visually-hidden">Sort review queue</span>
                <select data-pc-review-sort aria-label="Sort review queue">
                    <option value="priority_desc">Priority</option>
                    <option value="rate_asc">Lowest helpful rate</option>
                    <option value="responses_desc">Most responses</option>
                    <option value="not_helpful_desc">Most not-helpful</option>
                    <option value="title_asc">Policy: A–Z</option>
                </select>
            </label>
        </div>
    </section>

    <section class="pc-table-wrap pc-review-table-wrap">
        <table class="pc-table pc-review-table">
            <thead>
                <tr>
                    <th>Policy</th>
                    <th>Severity</th>
                    <th>Helpful rate</th>
                    <th>Responses</th>
                    <th>Not helpful</th>
                    <th>Top issue</th>
                    <th>Decision support</th>
                    <th>Impact preview</th>
                    <th>Last feedback</th>
                    <th class="pc-actions-col">Actions</th>
                </tr>
            </thead>

            <tbody data-pc-review-rows>
                <?php foreach ($queue as $row): ?>
                    <?php
                    $searchValue = mb_strtolower(
                        (string) $row['title'] . ' '
                        . (string) $row['category'] . ' '
                        . (string) $row['top_reason'] . ' '
                        . (string) $row['recommendation'],
                        'UTF-8'
                    );

                    $detailUrl = url(
                        '/dashboard/policies/feedback/detail?id='
                        . rawurlencode((string) $row['id'])
                    );
                    $editUrl = url(
                        '/dashboard/policies/edit?id='
                        . rawurlencode((string) $row['id'])
                    );
                    $publicUrl = (string) $row['slug'] !== ''
                        ? url('/policies') . '?=' . rawurlencode((string) $row['slug'])
                        : url('/policies');
                    ?>
                    <tr
                        data-pc-review-row
                        data-title="<?= e(mb_strtolower((string) $row['title'], 'UTF-8')) ?>"
                        data-search="<?= e($searchValue) ?>"
                        data-category="<?= e(mb_strtolower((string) $row['category'], 'UTF-8')) ?>"
                        data-severity="<?= e((string) $row['severity']) ?>"
                        data-priority="<?= e((string) $row['priority_score']) ?>"
                        data-rate="<?= e((string) $row['helpful_rate']) ?>"
                        data-total="<?= e((string) $row['total']) ?>"
                        data-not-helpful="<?= e((string) $row['not_helpful']) ?>"
                    >
                        <td>
                            <a class="pc-policy-title-link" href="<?= e($detailUrl) ?>">
                                <?= e((string) $row['title']) ?>
                            </a>
                            <small><?= e((string) $row['category']) ?></small>
                        </td>

                        <td>
                            <span class="pc-review-severity is-<?= e((string) $row['severity']) ?>">
                                <?= e((string) $row['severity_label']) ?>
                            </span>
                        </td>

                        <td>
                            <div class="pc-review-rate">
                                <strong><?= number_format((int) $row['helpful_rate']) ?>%</strong>
                                <span>
                                    <i data-barama-width="<?= e((string) $row['helpful_rate']) ?>"></i>
                                </span>
                            </div>
                        </td>

                        <td><?= number_format((int) $row['total']) ?></td>
                        <td><?= number_format((int) $row['not_helpful']) ?></td>

                        <td>
                            <span class="pc-review-issue">
                                <?= e((string) $row['top_reason']) ?>
                                <?php if ((int) $row['top_reason_count'] > 0): ?>
                                    <small><?= number_format((int) $row['top_reason_count']) ?></small>
                                <?php endif; ?>
                            </span>
                        </td>

                        <td>
                            <div class="pc-review-decision">
                                <div class="pc-review-decision__head">
                                    <strong><?= e((string) $row['recommendation']) ?></strong>
                                    <span class="pc-review-confidence is-<?= e((string) $row['confidence']) ?>">
                                        <?= e((string) $row['confidence_label']) ?>
                                    </span>
                                </div>

                                <p><?= e((string) $row['recommendation_summary']) ?></p>

                                <small>
                                    <?= number_format((int) $row['total']) ?> responses
                                    <?php if ((int) $row['reason_share'] > 0): ?>
                                        · dominant reason <?= number_format((int) $row['reason_share']) ?>%
                                    <?php endif; ?>
                                </small>
                            </div>
                        </td>

                        <td>
                            <div class="pc-review-impact">
                                <div class="pc-review-impact__head">
                                    <span>Current</span>
                                    <strong><?= number_format((int) $row['helpful_rate']) ?>%</strong>
                                    <span>→</span>
                                    <span>Target</span>
                                    <strong><?= number_format((int) $row['target_rate']) ?>%</strong>
                                </div>

                                <div class="pc-review-impact__bar" aria-hidden="true">
                                    <span
                                        class="is-current"
                                        data-barama-width="<?= e((string) min(100, (int) $row['helpful_rate'])) ?>"
                                    ></span>
                                    <i
                                        data-barama-left="<?= e((string) min(100, (int) $row['target_rate'])) ?>"
                                    ></i>
                                </div>

                                <p>
                                    <?php if ((int) $row['additional_helpful_needed'] > 0): ?>
                                        <strong>
                                            +<?= number_format((int) $row['additional_helpful_needed']) ?>
                                            helpful
                                        </strong>
                                        needed to reach the next safe threshold.
                                    <?php else: ?>
                                        Threshold already reached.
                                    <?php endif; ?>
                                </p>

                                <small>
                                    Projected rate after those responses:
                                    <?= number_format((int) $row['projected_rate']) ?>%
                                </small>
                            </div>
                        </td>

                        <td>
                            <?php if ((int) $row['feedback_updated_timestamp'] > 0): ?>
                                <?= e(date('M j, Y', (int) $row['feedback_updated_timestamp'])) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td class="pc-review-actions">
                            <a href="<?= e($detailUrl) ?>">Review</a>
                            <a href="<?= e($editUrl) ?>">Edit</a>
                            <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr data-pc-review-empty <?= $queueTotal > 0 ? 'hidden' : '' ?>>
                    <td colspan="10" class="pc-empty">
                        <strong>No policies currently require review.</strong>
                        <span>
                            The queue will populate automatically when aggregate
                            feedback meets one of the defined thresholds.
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <footer class="pc-review-footer">
        <span>
            Showing <strong data-pc-review-visible><?= number_format($queueTotal) ?></strong>
            of <?= number_format($queueTotal) ?> queued policies
        </span>

        <span>Derived from aggregate feedback only · no review state is stored</span>
    </footer>
</main>
<script defer src="<?= e(asset('js/policy-center.js') . '?v=stable-20261029') ?>"></script>
