<?php

declare(strict_types=1);

$policy = is_array($policy ?? null) ? $policy : [];
$feedbackSummary = is_array($feedbackSummary ?? null)
    ? $feedbackSummary
    : [
        'helpful' => 0,
        'not_helpful' => 0,
        'total' => 0,
        'helpful_rate' => 0,
        'reasons' => [],
        'updated_at' => '',
    ];
$policyFeedbackReasonLabels = is_array($policyFeedbackReasonLabels ?? null)
    ? $policyFeedbackReasonLabels
    : [];

$policyId = (string) ($policy['id'] ?? '');
$title = trim((string) ($policy['title'] ?? 'Policy'));
$category = trim((string) ($policy['category'] ?? 'General'));
$slug = trim((string) ($policy['slug'] ?? ''));
$status = trim((string) ($policy['status'] ?? 'draft'));
$visibility = trim((string) ($policy['visibility'] ?? 'public'));
$version = max(1, (int) ($policy['version_number'] ?? 1));

$helpful = max(0, (int) ($feedbackSummary['helpful'] ?? 0));
$notHelpful = max(0, (int) ($feedbackSummary['not_helpful'] ?? 0));
$total = max(0, (int) ($feedbackSummary['total'] ?? ($helpful + $notHelpful)));
$helpfulRate = max(0, min(100, (int) ($feedbackSummary['helpful_rate'] ?? 0)));
$notHelpfulRate = $total > 0 ? max(0, 100 - $helpfulRate) : 0;

$effectiveTimestamp = strtotime((string) ($policy['effective_date'] ?? ''));
$reviewTimestamp = strtotime((string) ($policy['last_reviewed_at'] ?? ''));
$updatedTimestamp = strtotime((string) ($policy['updated_at'] ?? ''));
$feedbackUpdatedTimestamp = strtotime((string) ($feedbackSummary['updated_at'] ?? ''));

$reasonRows = [];
$reasonTotal = 0;

foreach ($policyFeedbackReasonLabels as $reasonKey => $reasonLabel) {
    $count = max(
        0,
        (int) (
            is_array($feedbackSummary['reasons'] ?? null)
                ? ($feedbackSummary['reasons'][(string) $reasonKey] ?? 0)
                : 0
        )
    );

    $reasonTotal += $count;

    $reasonRows[] = [
        'key' => (string) $reasonKey,
        'label' => (string) $reasonLabel,
        'count' => $count,
    ];
}

usort(
    $reasonRows,
    static function (array $a, array $b): int {
        $countCompare = ((int) $b['count']) <=> ((int) $a['count']);

        return $countCompare !== 0
            ? $countCompare
            : strcasecmp((string) $a['label'], (string) $b['label']);
    }
);

$maxReasonCount = $reasonRows !== []
    ? max(array_map(static fn (array $row): int => (int) $row['count'], $reasonRows))
    : 0;

$dominantReasonKey = '';
$dominantReasonLabel = 'No dominant reason';
$dominantReasonCount = 0;

if ($reasonRows !== [] && (int) ($reasonRows[0]['count'] ?? 0) > 0) {
    $dominantReasonKey = (string) ($reasonRows[0]['key'] ?? '');
    $dominantReasonLabel = (string) ($reasonRows[0]['label'] ?? 'Other');
    $dominantReasonCount = (int) ($reasonRows[0]['count'] ?? 0);
}

$dominantReasonShare = $reasonTotal > 0 && $dominantReasonCount > 0
    ? (int) round(($dominantReasonCount / $reasonTotal) * 100)
    : 0;

$recommendationMap = [
    'hard_to_understand' => [
        'label' => 'Clarify wording',
        'summary' => 'Simplify complex sentences, define important terms, and add a short example where useful.',
        'checklist' => [
            'Shorten dense sentences and paragraphs.',
            'Define technical or legal terms at first use.',
            'Add one practical example for the hardest section.',
        ],
    ],
    'missing_information' => [
        'label' => 'Add missing information',
        'summary' => 'Fill the most likely information gaps, especially scope, responsibilities, exceptions, or next steps.',
        'checklist' => [
            'Check whether scope and affected users are explicit.',
            'Add missing responsibilities, exceptions, or timelines.',
            'Make contact/escalation or next-step instructions easy to find.',
        ],
    ],
    'too_long' => [
        'label' => 'Shorten content',
        'summary' => 'Remove repetition, split long sections, and move secondary detail out of the primary reading path.',
        'checklist' => [
            'Remove repeated explanations and duplicated rules.',
            'Break long sections into shorter headed blocks.',
            'Move secondary examples or reference material to supporting sections.',
        ],
    ],
    'out_of_date' => [
        'label' => 'Review outdated sections',
        'summary' => 'Revalidate dates, contacts, third-party references, process steps, and current policy behavior.',
        'checklist' => [
            'Recheck dates, contact details, and linked resources.',
            'Validate third-party names, product behavior, and operational steps.',
            'Confirm the effective/review dates reflect the latest approved version.',
        ],
    ],
    'other' => [
        'label' => 'Review reader friction',
        'summary' => 'Inspect structure and recent changes for issues not represented by the standard reason choices.',
        'checklist' => [
            'Compare recent edits with the current feedback pattern.',
            'Review navigation, heading order, and content discoverability.',
            'Check for ambiguous rules that do not fit the preset reasons.',
        ],
    ],
];

if ($total < 3) {
    $recommendation = [
        'label' => 'Collect more feedback',
        'summary' => 'The sample is too small for a reliable review recommendation.',
        'checklist' => [
            'Keep monitoring aggregate responses.',
            'Avoid major changes based on one or two votes alone.',
            'Reassess after at least three responses are available.',
        ],
    ];
} elseif ($dominantReasonKey !== '' && isset($recommendationMap[$dominantReasonKey])) {
    $recommendation = $recommendationMap[$dominantReasonKey];
} else {
    $recommendation = [
        'label' => 'Review overall clarity',
        'summary' => 'No dominant reason was selected. Review wording, structure, completeness, and recent changes together.',
        'checklist' => [
            'Read the policy from a first-time reader perspective.',
            'Check heading order, clarity, completeness, and repetition.',
            'Use the helpful rate and response volume as the primary signal.',
        ],
    ];
}

if ($total >= 20) {
    $decisionConfidence = 'high';
    $decisionConfidenceLabel = 'High confidence';
    $decisionConfidenceText = '20+ aggregate responses provide the strongest sample in this model.';
} elseif ($total >= 10) {
    $decisionConfidence = 'medium';
    $decisionConfidenceLabel = 'Medium confidence';
    $decisionConfidenceText = '10–19 responses provide a useful but still developing signal.';
} elseif ($total >= 5) {
    $decisionConfidence = 'emerging';
    $decisionConfidenceLabel = 'Emerging signal';
    $decisionConfidenceText = '5–9 responses suggest a direction, but changes should remain proportionate.';
} elseif ($total >= 3) {
    $decisionConfidence = 'limited';
    $decisionConfidenceLabel = 'Limited confidence';
    $decisionConfidenceText = '3–4 responses meet the review minimum but remain a small sample.';
} else {
    $decisionConfidence = 'insufficient';
    $decisionConfidenceLabel = 'Insufficient sample';
    $decisionConfidenceText = 'Fewer than three responses are not enough for a review recommendation.';
}

$reviewSeverity = 'monitor';
$reviewSeverityLabel = 'Monitor';

if ($total >= 10 && $helpfulRate < 40) {
    $reviewSeverity = 'critical';
    $reviewSeverityLabel = 'Critical';
} elseif ($total >= 5 && $helpfulRate < 50) {
    $reviewSeverity = 'high';
    $reviewSeverityLabel = 'High';
} elseif ($total >= 3 && $helpfulRate < 70) {
    $reviewSeverity = 'medium';
    $reviewSeverityLabel = 'Medium';
}

$impactTargetRate = match ($reviewSeverity) {
    'critical' => 40,
    'high' => 50,
    'medium' => 70,
    default => 0,
};

$impactAdditionalHelpful = 0;

if ($impactTargetRate > 0 && $helpfulRate < $impactTargetRate) {
    $impactNumerator = ($impactTargetRate * $total) - (100 * $helpful);
    $impactDenominator = 100 - $impactTargetRate;

    $impactAdditionalHelpful = max(
        0,
        (int) ceil($impactNumerator / $impactDenominator)
    );
}

$impactProjectedTotal = $total + $impactAdditionalHelpful;
$impactProjectedHelpful = $helpful + $impactAdditionalHelpful;
$impactProjectedRate = $impactProjectedTotal > 0
    ? (int) floor(($impactProjectedHelpful / $impactProjectedTotal) * 100)
    : 0;

$impactGap = $impactTargetRate > 0
    ? max(0, $impactTargetRate - $helpfulRate)
    : 0;

$publicUrl = $slug !== ''
    ? url('/policies') . '?=' . rawurlencode($slug)
    : url('/policies');

$editUrl = url('/dashboard/policies/edit?id=' . rawurlencode($policyId));
?>
<?= barama_css_once('policy-center') ?>

<main class="pc-admin-main pc-feedback-detail" data-pc-feedback-detail>
    <header class="pc-admin-head pc-feedback-detail__head">
        <div>
            <span class="pc-kicker">Feedback Analytics</span>
            <h1><?= e($title) ?></h1>
            <p>
                Aggregate feedback detail for this policy. No individual feedback
                events or personal identifiers are stored.
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
                <span>Review Queue</span>
            </a>

            <a
                class="pc-btn pc-category-button"
                href="<?= e($publicUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 5h5v5"></path>
                    <path d="m10 14 9-9"></path>
                    <path d="M19 13v6H5V5h6"></path>
                </svg>
                <span>View</span>
            </a>

            <a class="pc-btn pc-category-button" href="<?= e($editUrl) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 20h4l11-11-4-4L4 16z"></path>
                    <path d="m13 7 4 4"></path>
                </svg>
                <span>Edit</span>
            </a>

            <a class="pc-btn pc-primary" href="<?= e(url('/dashboard/policies/feedback')) ?>">
                ← Back to Analytics
            </a>
        </div>
    </header>

    <section class="pc-feedback-detail-meta" aria-label="Policy context">
        <article>
            <span>Category</span>
            <strong><?= e($category !== '' ? $category : 'General') ?></strong>
        </article>

        <article>
            <span>Version</span>
            <strong>v<?= number_format($version) ?></strong>
        </article>

        <article>
            <span>Effective</span>
            <strong><?= e($effectiveTimestamp ? date('M j, Y', $effectiveTimestamp) : 'Not set') ?></strong>
        </article>

        <article>
            <span>Last review</span>
            <strong><?= e($reviewTimestamp ? date('M j, Y', $reviewTimestamp) : 'Not reviewed') ?></strong>
        </article>

        <article>
            <span>Status</span>
            <strong><?= e(ucfirst($status !== '' ? $status : 'draft')) ?></strong>
        </article>

        <article>
            <span>Visibility</span>
            <strong><?= e(ucfirst($visibility !== '' ? $visibility : 'public')) ?></strong>
        </article>
    </section>

    <section class="pc-feedback-detail-kpis" aria-label="Policy feedback metrics">
        <article>
            <span>Total responses</span>
            <strong><?= number_format($total) ?></strong>
            <small>All aggregate votes</small>
        </article>

        <article>
            <span>Helpful rate</span>
            <strong><?= number_format($helpfulRate) ?>%</strong>
            <small><?= number_format($helpful) ?> helpful</small>
        </article>

        <article>
            <span>Not helpful</span>
            <strong><?= number_format($notHelpful) ?></strong>
            <small><?= number_format($notHelpfulRate) ?>% of responses</small>
        </article>

        <article>
            <span>Reason selections</span>
            <strong><?= number_format($reasonTotal) ?></strong>
            <small>Optional reason choices</small>
        </article>
    </section>

    <section class="pc-decision-support" aria-labelledby="pc-decision-support-title">
        <div class="pc-decision-support__primary">
            <div class="pc-decision-support__eyebrow">
                <span class="pc-kicker">Decision Support</span>
                <span class="pc-review-severity is-<?= e($reviewSeverity) ?>">
                    <?= e($reviewSeverityLabel) ?>
                </span>
            </div>

            <h2 id="pc-decision-support-title"><?= e((string) $recommendation['label']) ?></h2>
            <p><?= e((string) $recommendation['summary']) ?></p>

            <ul>
                <?php foreach ((array) ($recommendation['checklist'] ?? []) as $item): ?>
                    <li><?= e((string) $item) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <aside class="pc-decision-support__signal">
            <div class="pc-decision-confidence is-<?= e($decisionConfidence) ?>">
                <span><?= e($decisionConfidenceLabel) ?></span>
                <strong><?= number_format($total) ?> responses</strong>
            </div>

            <p><?= e($decisionConfidenceText) ?></p>

            <dl>
                <div>
                    <dt>Dominant reason</dt>
                    <dd><?= e($dominantReasonLabel) ?></dd>
                </div>

                <div>
                    <dt>Reason share</dt>
                    <dd><?= $dominantReasonShare > 0 ? number_format($dominantReasonShare) . '%' : '—' ?></dd>
                </div>

                <div>
                    <dt>Helpful rate</dt>
                    <dd><?= number_format($helpfulRate) ?>%</dd>
                </div>
            </dl>

            <small>
                Guidance is calculated at render time from aggregate counters only
                and is never saved as workflow state.
            </small>
        </aside>
    </section>

    <section class="pc-impact-preview" aria-labelledby="pc-impact-preview-title">
        <div class="pc-impact-preview__main">
            <div class="pc-impact-preview__eyebrow">
                <span class="pc-kicker">Review Impact Preview</span>
                <span class="pc-review-severity is-<?= e($reviewSeverity) ?>">
                    <?= e($reviewSeverityLabel) ?>
                </span>
            </div>

            <h2 id="pc-impact-preview-title">
                <?php if ($impactTargetRate > 0): ?>
                    What would move this policy out of <?= e($reviewSeverityLabel) ?>?
                <?php else: ?>
                    No active review threshold
                <?php endif; ?>
            </h2>

            <?php if ($impactTargetRate > 0): ?>
                <p>
                    The current helpful rate is
                    <strong><?= number_format($helpfulRate) ?>%</strong>.
                    Reaching the next safe boundary requires at least
                    <strong><?= number_format($impactTargetRate) ?>%</strong>.
                </p>

                <div class="pc-impact-preview__scale">
                    <div class="pc-impact-preview__labels">
                        <span>0%</span>
                        <span>Current <?= number_format($helpfulRate) ?>%</span>
                        <span>Target <?= number_format($impactTargetRate) ?>%</span>
                        <span>100%</span>
                    </div>

                    <div class="pc-impact-preview__track" aria-hidden="true">
                        <span
                            class="is-current"
                            data-barama-width="<?= e((string) min(100, $helpfulRate)) ?>"
                        ></span>
                        <i
                            class="is-target"
                            data-barama-left="<?= e((string) min(100, $impactTargetRate)) ?>"
                        ></i>
                    </div>
                </div>
            <?php else: ?>
                <p>
                    This policy does not currently meet the Critical, High, or
                    Medium review thresholds. Continue monitoring aggregate feedback.
                </p>
            <?php endif; ?>
        </div>

        <aside class="pc-impact-preview__numbers">
            <?php if ($impactTargetRate > 0): ?>
                <article>
                    <span>Rate gap</span>
                    <strong><?= number_format($impactGap) ?> pts</strong>
                </article>

                <article>
                    <span>Helpful responses needed</span>
                    <strong>+<?= number_format($impactAdditionalHelpful) ?></strong>
                </article>

                <article>
                    <span>Projected helpful rate</span>
                    <strong><?= number_format($impactProjectedRate) ?>%</strong>
                </article>

                <article>
                    <span>Projected sample</span>
                    <strong><?= number_format($impactProjectedTotal) ?></strong>
                </article>
            <?php else: ?>
                <article>
                    <span>Current helpful rate</span>
                    <strong><?= number_format($helpfulRate) ?>%</strong>
                </article>

                <article>
                    <span>Total responses</span>
                    <strong><?= number_format($total) ?></strong>
                </article>
            <?php endif; ?>
        </aside>

        <p class="pc-impact-preview__note">
            This is a mathematical scenario, not a prediction. It assumes every
            additional response in the scenario is Helpful and does not forecast
            future reader behavior.
        </p>
    </section>

    <section class="pc-feedback-detail-grid">
        <article class="pc-feedback-detail-panel">
            <header>
                <div>
                    <span class="pc-kicker">Balance</span>
                    <h2>Helpful vs not helpful</h2>
                </div>
                <small><?= number_format($total) ?> responses</small>
            </header>

            <?php if ($total > 0): ?>
                <div
                    class="pc-feedback-balance"
                    role="img"
                    aria-label="<?= e(number_format($helpfulRate) . '% helpful and ' . number_format($notHelpfulRate) . '% not helpful') ?>"
                >
                    <span class="pc-feedback-balance__helpful" data-barama-width="<?= e((string) $helpfulRate) ?>"></span>
                    <span class="pc-feedback-balance__not-helpful" data-barama-width="<?= e((string) $notHelpfulRate) ?>"></span>
                </div>

                <div class="pc-feedback-balance-legend">
                    <span>
                        <i class="is-helpful"></i>
                        Helpful
                        <strong><?= number_format($helpful) ?></strong>
                    </span>

                    <span>
                        <i class="is-not-helpful"></i>
                        Not helpful
                        <strong><?= number_format($notHelpful) ?></strong>
                    </span>
                </div>
            <?php else: ?>
                <div class="pc-feedback-detail-empty">
                    No feedback has been recorded for this policy yet.
                </div>
            <?php endif; ?>
        </article>

        <article class="pc-feedback-detail-panel">
            <header>
                <div>
                    <span class="pc-kicker">Context</span>
                    <h2>Policy lifecycle</h2>
                </div>
                <small>Read-only context</small>
            </header>

            <dl class="pc-feedback-context-list">
                <div>
                    <dt>Policy updated</dt>
                    <dd><?= e($updatedTimestamp ? date('M j, Y', $updatedTimestamp) : '—') ?></dd>
                </div>

                <div>
                    <dt>Feedback updated</dt>
                    <dd><?= e($feedbackUpdatedTimestamp ? date('M j, Y g:i A', $feedbackUpdatedTimestamp) : 'No feedback yet') ?></dd>
                </div>

                <div>
                    <dt>Public URL</dt>
                    <dd><?= e($slug !== '' ? '/policies?=' . $slug : '/policies') ?></dd>
                </div>

                <div>
                    <dt>Policy ID</dt>
                    <dd><?= e($policyId !== '' ? $policyId : '—') ?></dd>
                </div>
            </dl>
        </article>

        <article class="pc-feedback-detail-panel pc-feedback-detail-panel--reasons">
            <header>
                <div>
                    <span class="pc-kicker">Reasons</span>
                    <h2>Why readers selected “Not helpful”</h2>
                </div>
                <small><?= number_format($reasonTotal) ?> selections</small>
            </header>

            <?php if ($reasonTotal > 0): ?>
                <div class="pc-feedback-detail-reasons">
                    <?php foreach ($reasonRows as $row): ?>
                        <?php
                        $width = $maxReasonCount > 0
                            ? (int) round(((int) $row['count'] / $maxReasonCount) * 100)
                            : 0;
                        $share = $reasonTotal > 0
                            ? (int) round(((int) $row['count'] / $reasonTotal) * 100)
                            : 0;
                        ?>
                        <div class="pc-feedback-detail-reason">
                            <div class="pc-feedback-detail-reason__head">
                                <span><?= e((string) $row['label']) ?></span>
                                <strong>
                                    <?= number_format((int) $row['count']) ?>
                                    <small><?= number_format($share) ?>%</small>
                                </strong>
                            </div>

                            <div class="pc-feedback-detail-reason__track" aria-hidden="true">
                                <span data-barama-width="<?= e((string) $width) ?>"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="pc-feedback-detail-empty">
                    No not-helpful reason has been selected for this policy.
                </div>
            <?php endif; ?>

            <p class="pc-feedback-detail-note">
                Reason selection is optional. Percentages above describe reason
                selections, not all feedback responses.
            </p>
        </article>
    </section>

    <section class="pc-feedback-detail-privacy">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6z"></path>
            <path d="M9 12l2 2 4-4"></path>
        </svg>

        <div>
            <strong>Privacy-minimized analytics</strong>
            <p>
                This page is generated from aggregate counters only. The system
                does not retain IP addresses, user-agents, account IDs, emails,
                free-text comments, or individual feedback-event history.
            </p>
        </div>
    </section>
</main>
