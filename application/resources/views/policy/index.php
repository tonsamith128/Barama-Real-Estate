<?php

declare(strict_types=1);

$policies = is_array($policies ?? null) ? $policies : [];
$allPolicies = is_array($allPolicies ?? null) ? $allPolicies : [];
$selectedPolicy = is_array($selectedPolicy ?? null) ? $selectedPolicy : null;
$query = trim((string) ($query ?? ''));
$policyCenterFaqs = is_array($policyCenterFaqs ?? null) ? $policyCenterFaqs : [];

$policyUrl = static fn (array $policy): string =>
    url('/policies') . '?=' . rawurlencode((string) ($policy['slug'] ?? ''));

$previousPolicy = null;
$nextPolicy = null;
$relatedPolicies = [];

if ($selectedPolicy !== null) {
    $selectedId = (string) ($selectedPolicy['id'] ?? '');
    $selectedCategory = trim((string) ($selectedPolicy['category'] ?? ''));

    foreach ($allPolicies as $index => $policy) {
        if ((string) ($policy['id'] ?? '') !== $selectedId) {
            continue;
        }

        $previousPolicy = $index > 0
            ? ($allPolicies[$index - 1] ?? null)
            : null;

        $nextPolicy = $index < count($allPolicies) - 1
            ? ($allPolicies[$index + 1] ?? null)
            : null;

        break;
    }

    if ($selectedCategory !== '') {
        foreach ($allPolicies as $policy) {
            if ((string) ($policy['id'] ?? '') === $selectedId) {
                continue;
            }

            if (
                strcasecmp(
                    trim((string) ($policy['category'] ?? '')),
                    $selectedCategory
                ) !== 0
            ) {
                continue;
            }

            $relatedPolicies[] = $policy;

            if (count($relatedPolicies) >= 3) {
                break;
            }
        }
    }
}
?>
<link rel="stylesheet" href="<?= e(asset('css/policy-center-public.css') . '?v=1.0.0-css-clean1') ?>">

<main class="pc-public">
    <?php if ($selectedPolicy === null): ?>
    <section class="pc-hero pc-policy-hero">
        <div class="container app-container pc-policy-hero__inner">
            <div class="pc-policy-hero__content">
                <span class="pc-kicker">Trust &amp; Transparency</span>
                <h1>Policy Center</h1>
                <p>
                    Find the policies, terms, and standards that explain how Barama
                    operates, protects its marketplace community, and maintains
                    transparent platform rules.
                </p>

                <?php if ($selectedPolicy === null): ?>
                    <form class="pc-public-search" method="get" action="<?= e(url('/policies')) ?>">
                        <span aria-hidden="true"><?= app_icon('search') ?></span>
                        <input
                            type="search"
                            name="q"
                            value="<?= e($query) ?>"
                            placeholder="Search policies..."
                            aria-label="Search policies"
                        >
                        <button type="submit">Search</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="pc-policy-hero__visual" aria-hidden="true">
                <img
                    src="<?= e(asset('img/policy-center-trust-v1.0.11.svg')) ?>"
                    alt=""
                    loading="eager"
                    decoding="async"
                >
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="container app-container pc-public-body<?= $selectedPolicy !== null ? ' pc-public-body--detail' : '' ?>">
        <?php if ($selectedPolicy !== null): ?>
            <div class="pc-detail-layout">
                <aside class="pc-detail-side" data-policy-detail-nav>
                    <a class="pc-detail-back" href="<?= e(url('/policies')) ?>">← All policies</a>

                    <section class="pc-detail-toc" data-policy-toc hidden>
                        <div class="pc-detail-side__label">On this page</div>
                        <nav aria-label="On this page" data-policy-toc-list></nav>
                    </section>

                    <section class="pc-detail-policy-list">
                        <div class="pc-detail-side__label">All policies</div>
                        <nav aria-label="Policy navigation">
                            <?php foreach ($allPolicies as $policy): ?>
                                <?php $active = (string) ($policy['id'] ?? '') === (string) ($selectedPolicy['id'] ?? ''); ?>
                                <a
                                    href="<?= e($policyUrl($policy)) ?>"
                                    class="<?= $active ? 'is-active' : '' ?>"
                                    <?= $active ? 'aria-current="page"' : '' ?>
                                >
                                    <span><?= e((string) ($policy['title'] ?? 'Policy')) ?></span>
                                    <?php if ($active): ?>
                                        <svg class="pc-detail-policy-current" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="m7 12 3 3 7-7"></path>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </section>
                </aside>

                <article class="pc-detail">
                    <header class="pc-detail-header">
                        <div class="pc-detail-heading">
                            <div class="pc-detail-heading__content">
                                <span class="pc-kicker">Policy</span>
                                <h1><?= e((string) ($selectedPolicy['title'] ?? 'Policy')) ?></h1>

                                <?php if (trim((string) ($selectedPolicy['summary'] ?? '')) !== ''): ?>
                                    <p class="pc-detail-summary"><?= e((string) $selectedPolicy['summary']) ?></p>
                                <?php endif; ?>
                            </div>

                            <button
                                type="button"
                                class="pc-detail-copy"
                                data-policy-copy-url
                                aria-label="Copy policy link"
                                title="Copy policy link"
                            >
                                <svg class="pc-detail-copy__copy" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="8" y="8" width="11" height="11" rx="2"></rect>
                                    <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
                                </svg>
                                <svg class="pc-detail-copy__check" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m5 12 4 4L19 6"></path>
                                </svg>
                            </button>
                        </div>

                        <?php
                        $effectiveTimestamp = strtotime((string) ($selectedPolicy['effective_date'] ?? ''));
                        $reviewTimestamp = strtotime((string) ($selectedPolicy['last_reviewed_at'] ?? ''));
                        ?>
                        <div class="pc-detail-meta" aria-label="Policy metadata">
                            <div class="pc-detail-meta__item">
                                <span>Category</span>
                                <strong><?= e((string) ($selectedPolicy['category'] ?? 'General')) ?></strong>
                            </div>

                            <div class="pc-detail-meta__item">
                                <span>Version</span>
                                <strong>v<?= number_format(max(1, (int) ($selectedPolicy['version_number'] ?? 1))) ?></strong>
                            </div>

                            <div class="pc-detail-meta__item">
                                <span>Effective</span>
                                <strong><?= e($effectiveTimestamp ? date('M j, Y', $effectiveTimestamp) : 'Not set') ?></strong>
                            </div>

                            <div class="pc-detail-meta__item">
                                <span>Last review</span>
                                <strong><?= e($reviewTimestamp ? date('M j, Y', $reviewTimestamp) : 'Not reviewed') ?></strong>
                            </div>
                        </div>
                    </header>

                    <div class="pc-prose">
                        <?= (string) ($selectedPolicy['content_html'] ?? '') ?>
                    </div>

                    <?php if (!empty($selectedPolicy['faqs'])): ?>
                        <section class="pc-inline-faq">
                            <h2>Frequently asked questions</h2>
                            <?php foreach ((array) $selectedPolicy['faqs'] as $faq): ?>
                                <details>
                                    <summary><?= e((string) ($faq['question'] ?? '')) ?></summary>
                                    <div><?= nl2br(e((string) ($faq['answer'] ?? ''))) ?></div>
                                </details>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                    <footer class="pc-detail-bottom">
                        <?php if ($relatedPolicies !== []): ?>
                            <section class="pc-related" aria-labelledby="pc-related-title">
                                <div class="pc-detail-bottom__heading">
                                    <div>
                                        <span class="pc-kicker">Keep reading</span>
                                        <h2 id="pc-related-title">Related policies</h2>
                                    </div>
                                    <span><?= e((string) ($selectedPolicy['category'] ?? 'General')) ?></span>
                                </div>

                                <div class="pc-related-grid">
                                    <?php foreach ($relatedPolicies as $relatedPolicy): ?>
                                        <a class="pc-related-card" href="<?= e($policyUrl($relatedPolicy)) ?>">
                                            <span class="pc-related-card__category">
                                                <?= e((string) ($relatedPolicy['category'] ?? 'General')) ?>
                                            </span>

                                            <strong>
                                                <?= e((string) ($relatedPolicy['title'] ?? 'Policy')) ?>
                                            </strong>

                                            <span class="pc-related-card__meta">
                                                v<?= number_format(max(1, (int) ($relatedPolicy['version_number'] ?? 1))) ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <section class="pc-feedback" aria-labelledby="pc-feedback-title" data-policy-feedback>
                            <form method="post" action="<?= e(url('/policies/feedback')) ?>" data-policy-feedback-form data-policy-id="<?= e((string) ($selectedPolicy['id'] ?? '')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="policy_id" value="<?= e((string) ($selectedPolicy['id'] ?? '')) ?>">
                                <input type="hidden" name="value" value="" data-policy-feedback-input>
                                <input type="hidden" name="reason" value="" data-policy-feedback-reason-input>
                                <label class="pc-feedback__honeypot" aria-hidden="true">Company website<input type="text" name="company_website" value="" tabindex="-1" autocomplete="off"></label>

                                <div class="pc-feedback__intro">
                                    <span class="pc-kicker">Feedback</span>
                                    <h2 id="pc-feedback-title">Was this policy helpful?</h2>
                                    <p>Anonymous aggregate feedback helps Barama improve policy clarity without storing personal identifiers.</p>
                                </div>

                                <div class="pc-feedback__actions" role="group" aria-label="Policy helpfulness">
                                    <button type="button" data-policy-feedback-value="helpful" aria-pressed="false">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10v10H4V10z"></path><path d="M7 18h9.5a2 2 0 0 0 1.9-1.4l1.4-5A2 2 0 0 0 17.9 9H14l.6-3.2A2.3 2.3 0 0 0 12.3 3L7 10z"></path></svg>
                                        Yes
                                    </button>
                                    <button type="button" data-policy-feedback-value="not_helpful" aria-pressed="false">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 14V4H4v10z"></path><path d="M7 6h9.5a2 2 0 0 1 1.9 1.4l1.4 5a2 2 0 0 1-1.9 2.6H14l.6 3.2a2.3 2.3 0 0 1-2.3 2.8L7 14z"></path></svg>
                                        Not yet
                                    </button>
                                </div>

                                <div class="pc-feedback__reasons" data-policy-feedback-reasons hidden>
                                    <span>Optional: what could be improved?</span>
                                    <div class="pc-feedback__reason-list" role="group" aria-label="Feedback reason">
                                        <?php foreach ([
                                            'hard_to_understand' => 'Hard to understand',
                                            'missing_information' => 'Missing information',
                                            'too_long' => 'Too long',
                                            'out_of_date' => 'Out of date',
                                            'other' => 'Other',
                                        ] as $reasonValue => $reasonLabel): ?>
                                            <button type="button" data-policy-feedback-reason="<?= e($reasonValue) ?>" aria-pressed="false"><?= e($reasonLabel) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="pc-feedback__send" data-policy-feedback-send>Send feedback</button>
                                </div>

                                <p class="pc-feedback__note" data-policy-feedback-note aria-live="polite">Only aggregate counts are stored. No IP address, account ID, email, user-agent, or free-text comment is recorded.</p>
                            </form>
                        </section>

                        <nav class="pc-detail-pager" aria-label="Policy pagination">
                            <?php if (is_array($previousPolicy)): ?>
                                <a class="pc-detail-pager__item pc-detail-pager__item--previous" href="<?= e($policyUrl($previousPolicy)) ?>">
                                    <span>Previous policy</span>
                                    <strong><?= e((string) ($previousPolicy['title'] ?? 'Policy')) ?></strong>
                                </a>
                            <?php else: ?>
                                <span class="pc-detail-pager__item pc-detail-pager__item--disabled" aria-hidden="true">
                                    <span>Previous policy</span>
                                    <strong>Beginning of list</strong>
                                </span>
                            <?php endif; ?>

                            <a class="pc-detail-pager__center" href="<?= e(url('/policies')) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="4" y="4" width="6" height="6" rx="1"></rect>
                                    <rect x="14" y="4" width="6" height="6" rx="1"></rect>
                                    <rect x="4" y="14" width="6" height="6" rx="1"></rect>
                                    <rect x="14" y="14" width="6" height="6" rx="1"></rect>
                                </svg>
                                <span>Policy Center</span>
                            </a>

                            <?php if (is_array($nextPolicy)): ?>
                                <a class="pc-detail-pager__item pc-detail-pager__item--next" href="<?= e($policyUrl($nextPolicy)) ?>">
                                    <span>Next policy</span>
                                    <strong><?= e((string) ($nextPolicy['title'] ?? 'Policy')) ?></strong>
                                </a>
                            <?php else: ?>
                                <span class="pc-detail-pager__item pc-detail-pager__item--disabled pc-detail-pager__item--next" aria-hidden="true">
                                    <span>Next policy</span>
                                    <strong>End of list</strong>
                                </span>
                            <?php endif; ?>
                        </nav>
                    </footer>
                </article>
            </div>
        <?php else: ?>
            <section>
                <header class="pc-list-head">
                    <div>
                        <h2><?= $query !== '' ? 'Search results' : 'Policies' ?></h2>
                        <p>
                            <?= $query !== ''
                                ? 'Policies matching “' . e($query) . '”.'
                                : 'Browse current Barama policies and operating standards.' ?>
                        </p>
                    </div>
                    <span>
                        <?= number_format(count($policies)) ?>
                        <?= count($policies) === 1 ? 'policy' : 'policies' ?>
                    </span>
                </header>

                <div class="pc-cards pc-policy-list">
                    <?php if ($policies === []): ?>
                        <div class="pc-public-empty">
                            <strong><?= $query !== '' ? 'No policies found.' : 'No published policies yet.' ?></strong>
                            <p>
                                <?= $query !== ''
                                    ? 'Try another search or return to the complete Policy Center.'
                                    : 'Published policies created by the administrator will appear here.' ?>
                            </p>
                            <?php if ($query !== ''): ?>
                                <a href="<?= e(url('/policies')) ?>">View all policies</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($policies as $index => $policy): ?>
                            <?php $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?>
                            <a
                                class="pc-card pc-policy-card"
                                href="<?= e($policyUrl($policy)) ?>"
                                aria-label="Open <?= e((string) ($policy['title'] ?? 'Policy')) ?>"
                            >
                                <span class="pc-policy-card__number" aria-hidden="true"><?= e($number) ?></span>

                                <span class="pc-policy-card__mark" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 3 5 6v5c0 4.5 2.7 7.5 7 10 4.3-2.5 7-5.5 7-10V6z"></path>
                                        <path d="m9.5 12 1.7 1.7 3.5-4"></path>
                                    </svg>
                                </span>

                                <div class="pc-policy-card__content">
                                    <span class="pc-policy-card__category">
                                        <?= e((string) ($policy['category'] ?? 'General')) ?>
                                    </span>

                                    <h3 class="pc-policy-card__title">
                                        <?= e((string) ($policy['title'] ?? 'Policy')) ?>
                                    </h3>

                                    <div class="pc-policy-card__meta">
                                        <span>
                                            <?= !empty($policy['effective_date'])
                                                ? 'Effective ' . e((string) $policy['effective_date'])
                                                : 'Current policy' ?>
                                        </span>
                                        <span>
                                            v<?= number_format(max(1, (int) ($policy['version_number'] ?? 1))) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="pc-faq">
                <header>
                    <span class="pc-kicker">Help</span>
                    <h2>Policy Center FAQs</h2>
                    <p>
                        Practical guidance for understanding publication, effective dates,
                        platform rules, updates, privacy, enforcement, and support.
                    </p>
                </header>
                <div>
                    <?php foreach ($policyCenterFaqs as $faq): ?>
                        <details>
                            <summary><?= e((string) ($faq['question'] ?? '')) ?></summary>
                            <p><?= nl2br(e((string) ($faq['answer'] ?? ''))) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
<script defer src="<?= e(asset('js/policy-center.js') . '?v=stable-20261029') ?>"></script>
