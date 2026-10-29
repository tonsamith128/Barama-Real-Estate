<?php

declare(strict_types=1);

$record = is_array($record ?? null) ? $record : [];
$editing = (bool) ($editing ?? false);
$publicationBlocked = (bool) ($publicationBlocked ?? false);
$publicationIssues = is_array($publicationIssues ?? null)
    ? $publicationIssues : [];
$faqs = is_array($record['faqs'] ?? null) ? $record['faqs'] : [];
$versions = is_array($record['versions'] ?? null)
    ? array_reverse(array_values($record['versions']))
    : [];
$action = $editing ? '/dashboard/policies/update' : '/dashboard/policies/create';

$checkLabels = [
    'operator_identity' => 'Operator/company identity confirmed',
    'jurisdiction' => 'Governing jurisdiction confirmed',
    'legal_contact' => 'Legal and support contacts confirmed',
    'product_behavior' => 'Policy matches actual product behavior',
    'privacy_data' => 'Privacy and data practices verified',
    'billing_refund' => 'Billing and refund rules verified',
    'third_parties' => 'Third-party processors/vendors reviewed',
    'accessibility_consumer' => 'Accessibility and consumer obligations reviewed',
];

$checks = is_array($record['compliance_checks'] ?? null)
    ? $record['compliance_checks']
    : [];
$progress = max(0, min(100, (int) ($record['compliance_progress'] ?? 0)));
$versionNumber = max(0, (int) ($record['version_number'] ?? 0));
$nextReview = (string) ($record['next_review_date'] ?? '');
$reviewDue = $nextReview !== '' && $nextReview < date('Y-m-d');
$categories = is_array($categories ?? null) ? $categories : [];
$selectedCategoryId = (string) ($record['category_id'] ?? '');

if ($selectedCategoryId === '' && trim((string) ($record['category'] ?? '')) !== '') {
    foreach ($categories as $candidate) {
        if (strcasecmp(
            (string) ($candidate['name'] ?? ''),
            (string) ($record['category'] ?? '')
        ) === 0) {
            $selectedCategoryId = (string) ($candidate['id'] ?? '');
            break;
        }
    }
}
?>
<link rel="stylesheet" href="<?= e(asset('css/policy-center.css') . '?v=1.0.0-css-clean1') ?>">

<main class="pc-builder">
    <form method="post" action="<?= e(url($action)) ?>" data-pc-builder>
        <?= csrf_field() ?>

        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= e((string) ($record['id'] ?? '')) ?>">
        <?php endif; ?>

        <header class="pc-builder-head">
            <div>
                <a href="<?= e(url('/dashboard/policies')) ?>">← Policy Center</a>
                <h1><?= $editing ? 'Edit Policy' : 'Create New Policy' ?></h1>
                <p>
                    Build policy content, maintain compliance evidence, and
                    preserve every approved revision.
                </p>
                <button type="button" class="pc-guide-reopen"
                    aria-haspopup="dialog" aria-controls="pc-policy-publishing-guide"
                    data-pc-guide-open>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                    Publishing guide
                </button>
            </div>

            <div>
                <button class="pc-btn" type="submit" name="save_mode" value="draft">
                    Save Draft
                </button>
                <button class="pc-btn pc-primary" type="submit" name="save_mode" value="publish">
                    <?= $editing && (string) ($record['status'] ?? '') === 'published'
                        ? 'Update'
                        : 'Publish' ?>
                </button>
            </div>
        </header>

        <?php if (isset($_GET['saved'])): ?>
            <div class="pc-success">
                Policy saved as version v<?= number_format(max(1, $versionNumber)) ?>.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['restored'])): ?>
            <div class="pc-success">
                Historical version restored as a new current version.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['restore_failed'])): ?>
            <div class="pc-error">
                The selected policy version could not be restored.
            </div>
        <?php endif; ?>

        <?php if ($publicationBlocked): ?>
            <section id="pc-publish-feedback" class="pc-publish-feedback" role="alert"
                aria-labelledby="pc-publish-feedback-title" tabindex="-1" data-pc-publish-feedback>
                <strong id="pc-publish-feedback-title">
                    Publication was not completed — no new version was created.
                </strong>
                <p>Your changes are still in this form but have <b>not been saved</b>.
                   Correct the items below, then choose Publish again.
                   Alternatively, choose Save Draft to preserve your edits as a draft.</p>
                <?php if ($publicationIssues !== []): ?>
                    <ol>
                        <?php foreach ($publicationIssues as $issue): ?>
                            <?php if (is_array($issue)): ?>
                                <li><a href="#<?= e((string) ($issue['field'] ?? 'pc-legal-compliance')) ?>">
                                    <?= e((string) ($issue['message'] ?? 'Complete the legal review.')) ?>
                                </a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p>Add a policy title and text, then select Publish.</p>
                <?php endif; ?>
            </section>
        <?php elseif (isset($_GET['compliance_blocked'])): ?>
            <div class="pc-warning" role="alert">
                The previous publication attempt was not approved.
                Add a policy title and text, then Publish. Imported template
                instructions before trying again.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['save_error'])): ?>
            <div class="pc-error">
                The policy could not be saved. No policy data was intentionally
                changed. Please retry after the storage write check passes.
            </div>
        <?php endif; ?>

        <div class="pc-builder-grid">
            <section class="pc-builder-content">
                <div class="pc-field">
                    <label for="pc-policy-title">Policy title</label>
                    <input
                        id="pc-policy-title"
                        name="title"
                        required
                        maxlength="160"
                        value="<?= e((string) ($record['title'] ?? '')) ?>"
                        placeholder="e.g. Privacy Policy"
                        data-pc-title
                    >
                </div>

                <div class="pc-field">
                    <label for="pc-policy-slug">Dynamic URL title</label>
                    <div class="pc-slug pc-slug--copy">
                        <span>/policies?=</span>
                        <input
                            id="pc-policy-slug"
                            name="slug"
                            maxlength="180"
                            value="<?= e((string) ($record['slug'] ?? '')) ?>"
                            placeholder="privacy-policy"
                            data-pc-slug
                        >
                        <button
                            type="button"
                            class="pc-slug-copy"
                            aria-label="Copy dynamic public URL"
                            title="Copy dynamic public URL"
                            data-pc-copy-dynamic-url
                        >
                            <svg
                                class="pc-slug-copy__copy"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect x="8" y="8" width="11" height="11" rx="2"></rect>
                                <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
                            </svg>
                            <svg
                                class="pc-slug-copy__check"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="m5 12 4 4L19 6"></path>
                            </svg>
                            <span class="pc-sr-only" data-pc-copy-status>Copy dynamic public URL</span>
                        </button>
                    </div>
                    <small>
                        Public URL: <code>/policies?={policy-title}</code>.
                    </small>
                </div>

                <div class="pc-field">
                    <label for="pc-summary">Summary</label>
                    <textarea
                        id="pc-summary"
                        name="summary"
                        rows="3"
                        maxlength="420"
                        placeholder="Short description shown on the Policy Center card."
                    ><?= e((string) ($record['summary'] ?? '')) ?></textarea>
                </div>

                <div class="pc-field">
                    <label>Policy content</label>
                    <div
                        class="pc-toolbar pc-toolbar--icons"
                        role="toolbar"
                        aria-label="Policy content formatting"
                        data-pc-editor-toolbar
                    >
                        <div class="pc-toolbar-group" role="group" aria-label="History">
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="undo"
                                aria-label="Undo"
                                title="Undo"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 7 5 11l4 4"></path>
                                    <path d="M5 11h8a6 6 0 0 1 6 6"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="redo"
                                aria-label="Redo"
                                title="Redo"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m15 7 4 4-4 4"></path>
                                    <path d="M19 11h-8a6 6 0 0 0-6 6"></path>
                                </svg>
                            </button>
                        </div>

                        <span class="pc-toolbar-separator" aria-hidden="true"></span>

                        <div class="pc-toolbar-group" role="group" aria-label="Text structure">
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="formatBlock:p"
                                data-pc-format="p"
                                aria-label="Paragraph"
                                title="Paragraph"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M8 5h6a4 4 0 0 1 0 8H8z"></path>
                                    <path d="M12 5v14"></path>
                                    <path d="M16 5v14"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="formatBlock:h2"
                                data-pc-format="h2"
                                aria-label="Heading"
                                title="Heading"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M5 6v12"></path>
                                    <path d="M13 6v12"></path>
                                    <path d="M5 12h8"></path>
                                    <path d="M16 11.5c.4-1 1.2-1.5 2.3-1.5 1.3 0 2.2.8 2.2 1.9 0 1.1-.6 1.7-1.7 2.5L16 17h4.7"></path>
                                </svg>
                            </button>
                        </div>

                        <span class="pc-toolbar-separator" aria-hidden="true"></span>

                        <div class="pc-toolbar-group" role="group" aria-label="Text style">
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="bold"
                                data-pc-state="bold"
                                aria-label="Bold"
                                title="Bold"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M7 5h6a4 4 0 0 1 0 8H7z"></path>
                                    <path d="M7 13h7a3.5 3.5 0 0 1 0 7H7z"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="italic"
                                data-pc-state="italic"
                                aria-label="Italic"
                                title="Italic"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10 5h7"></path>
                                    <path d="M7 19h7"></path>
                                    <path d="m14 5-4 14"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="removeFormat"
                                aria-label="Clear formatting"
                                title="Clear formatting"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="m5 5 14 14"></path>
                                    <path d="M8 7h9"></path>
                                    <path d="m13 7-4 10"></path>
                                    <path d="M6 19h8"></path>
                                </svg>
                            </button>
                        </div>

                        <span class="pc-toolbar-separator" aria-hidden="true"></span>

                        <div class="pc-toolbar-group" role="group" aria-label="Lists and quotation">
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="insertUnorderedList"
                                data-pc-state="insertUnorderedList"
                                aria-label="Bulleted list"
                                title="Bulleted list"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="5" cy="7" r="1"></circle>
                                    <circle cx="5" cy="12" r="1"></circle>
                                    <circle cx="5" cy="17" r="1"></circle>
                                    <path d="M9 7h10"></path>
                                    <path d="M9 12h10"></path>
                                    <path d="M9 17h10"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="insertOrderedList"
                                data-pc-state="insertOrderedList"
                                aria-label="Numbered list"
                                title="Numbered list"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 6h1v3"></path>
                                    <path d="M3.5 9H6"></path>
                                    <path d="M3.5 13c.2-.8.8-1.2 1.5-1.2.9 0 1.5.5 1.5 1.2 0 .6-.3 1-1.1 1.6L3.5 16H6.5"></path>
                                    <path d="M10 7h10"></path>
                                    <path d="M10 12h10"></path>
                                    <path d="M10 17h10"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="formatBlock:blockquote"
                                data-pc-format="blockquote"
                                aria-label="Quote"
                                title="Quote"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M5 9h5v5H6c0 2-1 3-2 4"></path>
                                    <path d="M14 9h5v5h-4c0 2-1 3-2 4"></path>
                                </svg>
                            </button>
                        </div>

                        <span class="pc-toolbar-separator" aria-hidden="true"></span>

                        <div class="pc-toolbar-group" role="group" aria-label="Insert">
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="createLink"
                                aria-label="Insert link"
                                title="Insert link"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.1 1.1"></path>
                                    <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.1-1.1"></path>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="pc-toolbar-tool"
                                data-pc-cmd="insertHorizontalRule"
                                aria-label="Insert divider"
                                title="Insert divider"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 12h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div
                        id="pc-policy-content"
                        class="pc-editor"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        data-pc-editor
                    ><?= (string) ($record['content_html'] ?? '') ?></div>

                    <input type="hidden" name="content_html" value="" data-pc-content>
                </div>

                <section>
                    <div class="pc-section-head">
                        <div>
                            <h2>Policy FAQs</h2>
                            <p>
                                Optional questions displayed after this policy's
                                detailed content.
                            </p>
                        </div>

                        <button type="button" class="pc-btn" data-pc-add-faq>
                            + Add FAQ
                        </button>
                    </div>

                    <div data-pc-faq-list>
                        <?php foreach ($faqs as $faq): ?>
                            <div class="pc-faq-row" data-pc-faq-row>
                                <div class="pc-field">
                                    <label>Question</label>
                                    <input
                                        name="faq_question[]"
                                        maxlength="240"
                                        value="<?= e((string) ($faq['question'] ?? '')) ?>"
                                    >
                                </div>

                                <div class="pc-field">
                                    <label>Answer</label>
                                    <textarea
                                        name="faq_answer[]"
                                        rows="3"
                                        maxlength="1200"
                                    ><?= e((string) ($faq['answer'] ?? '')) ?></textarea>
                                </div>

                                <button type="button" data-pc-remove-faq>
                                    Remove
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>

            <aside class="pc-builder-side">
                <section>
                    <h2>Publish</h2>

                    <div class="pc-version-current">
                        <span>Current version</span>
                        <strong>v<?= number_format($versionNumber) ?></strong>
                    </div>

                    <div class="pc-field">
                        <label>Version note</label>
                        <input
                            name="version_note"
                            maxlength="240"
                            value="<?= e((string) ($record['version_note'] ?? '')) ?>"
                            placeholder="e.g. Updated retention section"
                        >
                        <small>
                            Saved with the next immutable version snapshot.
                        </small>
                    </div>

                    <div class="pc-field">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['draft' => 'Draft', 'published' => 'Published'] as $value => $label): ?>
                                <option
                                    value="<?= e($value) ?>"
                                    <?= (string) ($record['status'] ?? 'draft') === $value ? 'selected' : '' ?>
                                ><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label>Visibility</label>
                        <select name="visibility">
                            <?php foreach (['public' => 'Public', 'private' => 'Private'] as $value => $label): ?>
                                <option
                                    value="<?= e($value) ?>"
                                    <?= (string) ($record['visibility'] ?? 'public') === $value ? 'selected' : '' ?>
                                ><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label for="pc-effective-date">Effective date</label>
                        <div class="pc-date-input">
                            <input
                                id="pc-effective-date"
                                type="date"
                                name="effective_date"
                                value="<?= e((string) ($record['effective_date'] ?? '')) ?>"
                                data-pc-date-input
                            >
                            <button
                                type="button"
                                class="pc-date-trigger"
                                aria-label="Open Effective date calendar"
                                title="Open Effective date calendar"
                                data-pc-date-trigger
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="3.5" y="5.5" width="17" height="15" rx="2.5"></rect>
                                    <path d="M8 3.5v4"></path>
                                    <path d="M16 3.5v4"></path>
                                    <path d="M3.5 9.5h17"></path>
                                    <path d="M8 13h.01"></path>
                                    <path d="M12 13h.01"></path>
                                    <path d="M16 13h.01"></path>
                                    <path d="M8 17h.01"></path>
                                    <path d="M12 17h.01"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="pc-check pc-gate-check">
                        <input
                            type="checkbox"
                            name="compliance_gate_enabled"
                            value="1"
                            <?= !array_key_exists('compliance_gate_enabled', $record) || !empty($record['compliance_gate_enabled']) ? 'checked' : '' ?>
                        >
                        <span>
                            Track legal compliance (optional; does not block publishing)
                        </span>
                    </label>
                </section>

                <section class="pc-compliance-panel" id="pc-legal-compliance">
                    <div class="pc-side-heading">
                        <div>
                            <h2>Legal Compliance</h2>
                            <small data-pc-compliance-progress-text>
                                <?= number_format($progress) ?>% complete
                            </small>
                        </div>

                        <span class="pc-readiness <?= $reviewDue ? 'is-due' : '' ?>">
                            <?= $reviewDue ? 'Review due' : 'Governance' ?>
                        </span>
                    </div>

                    <div class="pc-compliance-progress" aria-hidden="true">
                        <span
                            data-barama-width="<?= e((string) $progress) ?>"
                            data-pc-compliance-progress
                        ></span>
                    </div>

                    <div class="pc-field">
                        <label>Compliance status</label>
                        <select name="compliance_status" id="pc-compliance-status">
                            <?php
                            $statuses = [
                                'not_reviewed' => 'Not reviewed',
                                'in_review' => 'In review',
                                'compliant' => 'Compliant',
                                'action_required' => 'Action required',
                                'not_applicable' => 'Not applicable',
                            ];
                            ?>
                            <?php foreach ($statuses as $value => $label): ?>
                                <option
                                    value="<?= e($value) ?>"
                                    <?= (string) ($record['compliance_status'] ?? 'not_reviewed') === $value ? 'selected' : '' ?>
                                ><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label>Risk level</label>
                        <select name="risk_level">
                            <?php foreach ([
                                'unrated' => 'Unrated',
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'critical' => 'Critical',
                            ] as $value => $label): ?>
                                <option
                                    value="<?= e($value) ?>"
                                    <?= (string) ($record['risk_level'] ?? 'unrated') === $value ? 'selected' : '' ?>
                                ><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label>Jurisdiction</label>
                        <input
                            name="jurisdiction"
                            maxlength="180"
                            value="<?= e((string) ($record['jurisdiction'] ?? '')) ?>"
                            placeholder="e.g. Cambodia"
                        >
                    </div>

                    <div class="pc-field">
                        <label>Legal basis / regulation</label>
                        <textarea
                            name="legal_basis"
                            rows="3"
                            maxlength="1000"
                            placeholder="Applicable law, regulation, contract, standard, or internal requirement."
                        ><?= e((string) ($record['legal_basis'] ?? '')) ?></textarea>
                    </div>

                    <div class="pc-field">
                        <label>Compliance owner</label>
                        <input
                            name="compliance_owner"
                            maxlength="160"
                            value="<?= e((string) ($record['compliance_owner'] ?? '')) ?>"
                            placeholder="Responsible person or team"
                        >
                    </div>

                    <div class="pc-field">
                        <label>Reviewed by</label>
                        <input
                            name="reviewed_by"
                            id="pc-reviewed-by"
                            maxlength="160"
                            value="<?= e((string) ($record['reviewed_by'] ?? '')) ?>"
                            placeholder="Reviewer name / role"
                        >
                    </div>

                    <div class="pc-two-fields">
                        <div class="pc-field">
                            <label for="pc-last-reviewed-at">Last Review</label>
                            <div class="pc-date-input">
                                <input
                                    id="pc-last-reviewed-at"
                                    type="date"
                                    name="last_reviewed_at"
                                    value="<?= e((string) ($record['last_reviewed_at'] ?? '')) ?>"
                                    data-pc-date-input
                                >
                                <button
                                    type="button"
                                    class="pc-date-trigger"
                                    aria-label="Open Last Review calendar"
                                    title="Open Last Review calendar"
                                    data-pc-date-trigger
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="3.5" y="5.5" width="17" height="15" rx="2.5"></rect>
                                        <path d="M8 3.5v4"></path>
                                        <path d="M16 3.5v4"></path>
                                        <path d="M3.5 9.5h17"></path>
                                        <path d="M8 13h.01"></path>
                                        <path d="M12 13h.01"></path>
                                        <path d="M16 13h.01"></path>
                                        <path d="M8 17h.01"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="pc-field">
                            <label for="pc-next-review-date">Next Review</label>
                            <div class="pc-date-input">
                                <input
                                    id="pc-next-review-date"
                                    type="date"
                                    name="next_review_date"
                                    value="<?= e($nextReview) ?>"
                                    data-pc-date-input
                                >
                                <button
                                    type="button"
                                    class="pc-date-trigger"
                                    aria-label="Open Next Review calendar"
                                    title="Open Next Review calendar"
                                    data-pc-date-trigger
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="3.5" y="5.5" width="17" height="15" rx="2.5"></rect>
                                        <path d="M8 3.5v4"></path>
                                        <path d="M16 3.5v4"></path>
                                        <path d="M3.5 9.5h17"></path>
                                        <path d="M8 13h.01"></path>
                                        <path d="M12 13h.01"></path>
                                        <path d="M16 13h.01"></path>
                                        <path d="M8 17h.01"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pc-field">
                        <label>Evidence URL</label>
                        <input
                            name="evidence_url"
                            maxlength="500"
                            value="<?= e((string) ($record['evidence_url'] ?? '')) ?>"
                            placeholder="/internal-evidence or https://..."
                        >
                    </div>

                    <div class="pc-compliance-checklist" id="pc-review-checklist">
                        <strong>Review checklist</strong>

                        <?php foreach ($checkLabels as $key => $label): ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="compliance_check[]"
                                    value="<?= e($key) ?>"
                                    data-pc-compliance-check
                                    <?= !empty($checks[$key]) ? 'checked' : '' ?>
                                >
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="pc-field">
                        <label>Compliance notes</label>
                        <textarea
                            name="compliance_notes"
                            rows="4"
                            maxlength="4000"
                            placeholder="Open issues, legal review notes, exceptions, remediation, or evidence context."
                        ><?= e((string) ($record['compliance_notes'] ?? '')) ?></textarea>
                    </div>
                </section>

                <?php if ($editing): ?>
                    <section>
                        <div class="pc-side-heading">
                            <div>
                                <h2>Version History</h2>
                                <small>Latest 50 immutable snapshots</small>
                            </div>

                            <span class="pc-version-badge">
                                v<?= number_format($versionNumber) ?>
                            </span>
                        </div>

                        <?php if ($versions === []): ?>
                            <p class="pc-side-empty">
                                Version history starts after the first save.
                            </p>
                        <?php else: ?>
                            <div class="pc-version-list">
                                <?php foreach (array_slice($versions, 0, 12) as $version): ?>
                                    <?php
                                    $number = (int) ($version['version_number'] ?? 0);
                                    $savedAt = strtotime((string) ($version['saved_at'] ?? ''));
                                    $current = $number === $versionNumber;
                                    ?>
                                    <article class="pc-version-item <?= $current ? 'is-current' : '' ?>">
                                        <div>
                                            <strong>v<?= number_format($number) ?></strong>
                                            <span>
                                                <?= e($savedAt ? date('M j, Y H:i', $savedAt) : '—') ?>
                                            </span>
                                        </div>

                                        <p>
                                            <?= e((string) ($version['note'] ?? 'Policy version')) ?>
                                        </p>

                                        <?php if ($current): ?>
                                            <small>Current</small>
                                        <?php else: ?>
                                            <button
                                                type="submit"
                                                name="restore_version_id"
                                                value="<?= e((string) ($version['id'] ?? '')) ?>"
                                                class="pc-version-restore"
                                                data-pc-restore-version="v<?= e((string) $number) ?>"
                                            >
                                                Restore
                                            </button>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section>
                    <h2>Organization</h2>

                    <div class="pc-field">
                        <div class="pc-field-label-row">
                            <label for="pc-policy-category">Category</label>
                            <a
                                class="pc-field-link"
                                href="<?= e(url('/dashboard/policies/categories')) ?>"
                            >Manage categories</a>
                        </div>

                        <div class="pc-select-premium">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h4.3a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.8l-4.8 4.8a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 4 10.8V6.5z"></path>
                                <circle cx="8.2" cy="8.2" r="1.2"></circle>
                            </svg>

                            <select
                                id="pc-policy-category"
                                name="category_id"
                                required
                                data-pc-category-select
                            >
                                <?php if ($categories === []): ?>
                                    <option value="">General</option>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <?php $categoryId = (string) ($category['id'] ?? ''); ?>
                                        <option
                                            value="<?= e($categoryId) ?>"
                                            <?= $selectedCategoryId === $categoryId ? 'selected' : '' ?>
                                        >
                                            <?= e((string) ($category['name'] ?? 'General')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <?php if ($categories === []): ?>
                            <input type="hidden" name="category" value="General">
                            <small>
                                No category registry exists yet. Create a category
                                from Policy Center → Category.
                            </small>
                        <?php else: ?>
                            <small>
                                Categories are centrally managed and reusable
                                across all policies.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="pc-field">
                        <label>Display order</label>
                        <input
                            type="number"
                            name="sort_order"
                            min="0"
                            max="9999"
                            value="<?= e((string) ($record['sort_order'] ?? 0)) ?>"
                        >
                    </div>

                    <label class="pc-check">
                        <input
                            type="checkbox"
                            name="featured"
                            value="1"
                            <?= !empty($record['featured']) ? 'checked' : '' ?>
                        >
                        <span>Featured policy</span>
                    </label>
                </section>

                <section>
                    <h2>SEO</h2>

                    <div class="pc-field">
                        <label>SEO title</label>
                        <input
                            name="seo_title"
                            maxlength="180"
                            value="<?= e((string) ($record['seo_title'] ?? '')) ?>"
                        >
                    </div>

                    <div class="pc-field">
                        <label>Meta description</label>
                        <textarea
                            name="meta_description"
                            rows="4"
                            maxlength="320"
                        ><?= e((string) ($record['meta_description'] ?? '')) ?></textarea>
                    </div>
                </section>

                <?php if ($editing && trim((string) ($record['slug'] ?? '')) !== ''): ?>
                    <section>
                        <h2>Public link</h2>

                        <a
                            class="pc-public-link"
                            target="_blank"
                            rel="noopener"
                            href="<?= e(url('/policies') . '?=' . rawurlencode((string) $record['slug'])) ?>"
                        ><?= e(url('/policies') . '?=' . rawurlencode((string) $record['slug'])) ?></a>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </form>
</main>

<dialog id="pc-policy-publishing-guide"
    class="pc-guide-dialog"
    data-pc-guide-dialog
    data-pc-guide-kind="<?= !empty($record['imported_demo_template']) ? 'demo' : 'general' ?>"
    aria-labelledby="pc-guide-title"
    aria-describedby="pc-guide-description">
    <div class="pc-guide-frame">
        <div class="pc-guide-banner" aria-hidden="true">
            <span class="pc-guide-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
            </span>
            <span class="pc-guide-eyebrow">BARAMA · POLICY CENTER</span>
        </div>
        <button type="button" class="pc-guide-close"
            data-pc-guide-dismiss aria-label="Close publishing guide">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.9" stroke-linecap="round">
                <path d="M5 5 19 19M19 5 5 19"></path>
            </svg>
        </button>
        <div class="pc-guide-content">
            <h2 id="pc-guide-title">Before you publish</h2>
            <p id="pc-guide-description" class="pc-guide-description">
                A quick overview of how Barama protects your legal policies and revisions.
            </p>
            <div class="pc-guide-steps">
                <section class="pc-guide-step">
                    <span class="pc-guide-number" aria-hidden="true">01</span>
                    <div>
                        <h3>Safe version history</h3>
                        <p>A new version is created only after a successful save.
                            If publication is blocked, no version is created and
                            the editor explains what to correct.</p>
                    </div>
                </section>
                <?php if (!empty($record['imported_demo_template'])): ?>
                    <section class="pc-guide-step">
                        <span class="pc-guide-number" aria-hidden="true">02</span>
                        <div>
                            <h3>Review your imported legal template</h3>
                            <p>Demo content is a private draft, not finalized legal advice.
                                Replace administrator placeholders with your own reviewed
                                legal text. Review the wording and select Publish. The optional
                                compliance checklist and reviewer fields can be completed later.</p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="pc-guide-step">
                        <span class="pc-guide-number" aria-hidden="true">02</span>
                        <div>
                            <h3>Complete legal governance</h3>
                            <p>Review your policy wording, then select Publish.
                                Legal compliance tracking is available but optional.</p>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
            <div class="pc-guide-footer">
                <span>Shown once in this browser. Reopen from <strong>Publishing guide</strong>.</span>
                <button type="button" class="pc-guide-confirm" data-pc-guide-dismiss>
                    Got it
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="m5 12 14 0m-5-5 5 5-5 5"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</dialog>

<script defer src="<?= e(asset('js/policy-center-v1.0.10.js')) ?>"></script>
<script defer src="<?= e(asset('js/policy-center-guidance-v100.js') . '?v=100') ?>"></script>
