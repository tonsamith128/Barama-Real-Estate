<?php

declare(strict_types=1);

namespace Barama\Repositories;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class PolicyCenterRepository
{
    private const STORE = '/storage/policy-center/data.json';

    private const COMPLIANCE_CHECKS = [
        'operator_identity',
        'jurisdiction',
        'legal_contact',
        'product_behavior',
        'privacy_data',
        'billing_refund',
        'third_parties',
        'accessibility_consumer',
    ];

    private const FEEDBACK_REASONS = [
        'hard_to_understand' => 'Hard to understand',
        'missing_information' => 'Missing information',
        'too_long' => 'Too long',
        'out_of_date' => 'Out of date',
        'other' => 'Other',
    ];

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->read()['policies'];

        usort($rows, static function (array $left, array $right): int {
            $updated = strcmp(
                (string) ($right['updated_at'] ?? ''),
                (string) ($left['updated_at'] ?? '')
            );

            if ($updated !== 0) {
                return $updated;
            }

            return strcasecmp(
                (string) ($left['title'] ?? ''),
                (string) ($right['title'] ?? '')
            );
        });

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function published(): array
    {
        $rows = array_values(array_filter(
            $this->read()['policies'],
            static fn (array $policy): bool =>
                (string) ($policy['status'] ?? 'draft') === 'published'
                && (string) ($policy['visibility'] ?? 'public') === 'public'
                // Fail closed if an imported template was never explicitly
                // approved, even when a stale/forged status says published.
                && (empty($policy['imported_demo_template']) || (
                    !empty($policy['demo_template_approved'])
                    && $this->isComplianceReady($policy)
                    && !self::hasUnresolvedDemoInstructions((string) ($policy['content_html'] ?? ''))
                ))
        ));

        usort($rows, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0))
                <=> ((int) ($right['sort_order'] ?? 0));

            return $order !== 0
                ? $order
                : strcasecmp(
                    (string) ($left['title'] ?? ''),
                    (string) ($right['title'] ?? '')
                );
        });

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        foreach ($this->read()['policies'] as $policy) {
            if ((string) ($policy['id'] ?? '') === $id) {
                return $policy;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        foreach ($this->published() as $policy) {
            if ((string) ($policy['slug'] ?? '') === $slug) {
                return $policy;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function complianceCheckKeys(): array
    {
        return self::COMPLIANCE_CHECKS;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(array $input, ?string $id = null): array
    {
        $store = $this->read();
        $existing = null;
        $existingIndex = null;

        if ($id !== null && $id !== '') {
            foreach ($store['policies'] as $index => $policy) {
                if ((string) ($policy['id'] ?? '') === $id) {
                    $existing = $policy;
                    $existingIndex = $index;
                    break;
                }
            }
        }

        $record = $this->normalize($input, $store, $existing);
        $publicationBlocked = (bool) ($record['_publication_blocked'] ?? false);
        unset($record['_publication_blocked']);

        if ($existingIndex === null) {
            $record['version_number'] = 1;
            $record['versions'] = [];
            $record['versions'][] = $this->snapshot(
                $record,
                trim((string) ($input['version_note'] ?? '')) ?: 'Initial version'
            );
            $store['policies'][] = $record;
        } else {
            $record['version_number'] = $this->nextVersionNumber($existing);
            $record['versions'] = is_array($existing['versions'] ?? null)
                ? array_values($existing['versions'])
                : [];

            $record['versions'][] = $this->snapshot(
                $record,
                trim((string) ($input['version_note'] ?? '')) ?: 'Policy updated'
            );

            $record['versions'] = array_slice($record['versions'], -50);
            $store['policies'][$existingIndex] = $record;
        }

        $this->write($store);

        $result = $record;
        $result['_publication_blocked'] = $publicationBlocked;

        return $result;
    }

    /**
     * Restore a previous immutable snapshot as a new current version.
     *
     * @return array<string, mixed>|null
     */
    public function restoreVersion(string $id, string $versionId): ?array
    {
        $store = $this->read();

        foreach ($store['policies'] as $index => $policy) {
            if ((string) ($policy['id'] ?? '') !== $id) {
                continue;
            }

            $versions = is_array($policy['versions'] ?? null)
                ? array_values($policy['versions'])
                : [];

            $target = null;

            foreach ($versions as $version) {
                if ((string) ($version['id'] ?? '') === $versionId) {
                    $target = $version;
                    break;
                }
            }

            if (!is_array($target) || !is_array($target['data'] ?? null)) {
                return null;
            }

            $data = $target['data'];
            $restored = $policy;

            foreach ($this->snapshotFields() as $field) {
                if (array_key_exists($field, $data)) {
                    $restored[$field] = $data[$field];
                }
            }

            $restored['id'] = (string) ($policy['id'] ?? $id);
            $restored['created_at'] = (string) ($policy['created_at'] ?? gmdate('c'));
            $restored['updated_at'] = gmdate('c');
            $restored['compliance_updated_at'] = gmdate('c');
            $restored['version_number'] = $this->nextVersionNumber($policy);
            $restored['versions'] = $versions;

            if (!empty($policy['imported_demo_template'])) {
                // Historical versions may contain obsolete legal promises.
                // A restore is ALWAYS a new private draft requiring approval.
                $restored['imported_demo_template'] = true;
                $restored['demo_template_approved'] = false;
                $restored['status'] = 'draft';
                $restored['visibility'] = 'private';
                $restored['compliance_gate_enabled'] = true;
                $restored['compliance_status'] = 'not_reviewed';
                $restored['compliance_progress'] = 0;
                $restored['compliance_checks'] = $this->blankComplianceChecks();
                $restored['reviewed_by'] = '';
                $restored['last_reviewed_at'] = '';
                $restored['next_review_date'] = '';
                $restored['effective_date'] = '';
            } elseif (
                !empty($restored['compliance_gate_enabled'])
                && (string) ($restored['status'] ?? 'draft') === 'published'
                && !$this->isComplianceReady($restored)
            ) {
                $restored['status'] = 'draft';
            }

            $fromNumber = (int) ($target['version_number'] ?? 0);
            $restored['versions'][] = $this->snapshot(
                $restored,
                $fromNumber > 0
                    ? 'Restored from v' . $fromNumber
                    : 'Restored historical version',
                (string) ($target['id'] ?? '')
            );
            $restored['versions'] = array_slice($restored['versions'], -50);

            $store['policies'][$index] = $restored;
            $this->write($store);

            return $restored;
        }

        return null;
    }

    public function delete(string $id): bool
    {
        $store = $this->read();
        $before = count($store['policies']);

        $store['policies'] = array_values(array_filter(
            $store['policies'],
            static fn (array $policy): bool =>
                (string) ($policy['id'] ?? '') !== $id
        ));

        if (count($store['policies']) === $before) {
            return false;
        }

        unset($store['feedback'][$id]);
        $this->write($store);

        return true;
    }

    /** @return array<string, mixed>|null */
    public function duplicate(string $id): ?array
    {
        $store = $this->read();
        $source = null;

        foreach ($store['policies'] as $policy) {
            if ((string) ($policy['id'] ?? '') === $id) {
                $source = $policy;
                break;
            }
        }

        if ($source === null) {
            return null;
        }

        $copy = $source;
        $copy['id'] = $this->id();
        $copy['title'] = trim((string) ($source['title'] ?? 'Policy')) . ' Copy';
        $copy['slug'] = $this->uniqueSlug(
            $this->slug((string) $copy['title']),
            $store,
            null
        );
        $copy['status'] = 'draft';
        if (!empty($source['imported_demo_template'])) {
            $copy['visibility'] = 'private';
            $copy['effective_date'] = '';
            $copy['demo_template_approved'] = false;
            $copy['compliance_gate_enabled'] = true;
        }
        $copy['compliance_status'] = 'not_reviewed';
        $copy['compliance_progress'] = 0;
        $copy['compliance_checks'] = $this->blankComplianceChecks();
        $copy['reviewed_by'] = '';
        $copy['last_reviewed_at'] = '';
        $copy['next_review_date'] = '';
        $copy['evidence_url'] = '';
        $copy['compliance_notes'] = '';
        $copy['compliance_updated_at'] = gmdate('c');
        $copy['version_number'] = 1;
        $copy['created_at'] = gmdate('c');
        $copy['updated_at'] = gmdate('c');
        $copy['versions'] = [];
        $copy['versions'][] = $this->snapshot($copy, 'Duplicated policy');

        $store['policies'][] = $copy;
        $this->write($store);

        return $copy;
    }

    /** @return array<int, array<string, mixed>> */
    public function categories(): array
    {
        $categories = $this->read()['categories'];

        usort($categories, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0))
                <=> ((int) ($right['sort_order'] ?? 0));

            return $order !== 0
                ? $order
                : strcasecmp(
                    (string) ($left['name'] ?? ''),
                    (string) ($right['name'] ?? '')
                );
        });

        return $categories;
    }

    /** @return array<string, mixed>|null */
    public function findCategoryById(string $id): ?array
    {
        foreach ($this->read()['categories'] as $category) {
            if ((string) ($category['id'] ?? '') === $id) {
                return $category;
            }
        }

        return null;
    }

    public function categoryUsageCount(string $id): int
    {
        $count = 0;

        foreach ($this->read()['policies'] as $policy) {
            if ((string) ($policy['category_id'] ?? '') === $id) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveCategory(array $input, ?string $id = null): array
    {
        $store = $this->read();
        $existing = null;
        $existingIndex = null;

        if ($id !== null && $id !== '') {
            foreach ($store['categories'] as $index => $category) {
                if ((string) ($category['id'] ?? '') === $id) {
                    $existing = $category;
                    $existingIndex = $index;
                    break;
                }
            }
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Category name is required.');
        }

        $name = mb_substr($name, 0, 80);
        $requestedSlug = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slug($requestedSlug !== '' ? $requestedSlug : $name);
        $slug = $this->uniqueCategorySlug(
            $slug !== '' ? $slug : 'category',
            $store,
            $id
        );

        $record = [
            'id' => (string) ($existing['id'] ?? ('pcat-' . bin2hex(random_bytes(7)))),
            'name' => $name,
            'slug' => mb_substr($slug, 0, 100),
            'description' => mb_substr(
                trim((string) ($input['description'] ?? '')),
                0,
                360
            ),
            'sort_order' => max(
                0,
                min(9999, (int) ($input['sort_order'] ?? 0))
            ),
            'created_at' => (string) ($existing['created_at'] ?? gmdate('c')),
            'updated_at' => gmdate('c'),
        ];

        $previousName = (string) ($existing['name'] ?? '');

        if ($existingIndex === null) {
            $store['categories'][] = $record;
        } else {
            $store['categories'][$existingIndex] = $record;

            if ($previousName !== '' && $previousName !== $record['name']) {
                foreach ($store['policies'] as &$policy) {
                    if ((string) ($policy['category_id'] ?? '') !== $record['id']) {
                        continue;
                    }

                    $policy['category'] = $record['name'];
                    $policy['updated_at'] = gmdate('c');
                }
                unset($policy);
            }
        }

        $this->write($store);

        return $record;
    }

    public function deleteCategory(string $id): bool
    {
        $store = $this->read();
        $usage = 0;

        foreach ($store['policies'] as $policy) {
            if ((string) ($policy['category_id'] ?? '') === $id) {
                $usage++;
            }
        }

        if ($usage > 0) {
            return false;
        }

        $before = count($store['categories']);

        $store['categories'] = array_values(array_filter(
            $store['categories'],
            static fn (array $category): bool =>
                (string) ($category['id'] ?? '') !== $id
        ));

        if (count($store['categories']) === $before) {
            return false;
        }

        $this->write($store);

        return true;
    }

    /**
     * Reassign every policy from one category to another, then remove source.
     */
    public function mergeCategory(string $sourceId, string $targetId): bool
    {
        if ($sourceId === '' || $targetId === '' || $sourceId === $targetId) {
            return false;
        }

        $store = $this->read();
        $source = null;
        $target = null;

        foreach ($store['categories'] as $category) {
            if ((string) ($category['id'] ?? '') === $sourceId) {
                $source = $category;
            }

            if ((string) ($category['id'] ?? '') === $targetId) {
                $target = $category;
            }
        }

        if ($source === null || $target === null) {
            return false;
        }

        foreach ($store['policies'] as &$policy) {
            if ((string) ($policy['category_id'] ?? '') !== $sourceId) {
                continue;
            }

            $policy['category_id'] = $targetId;
            $policy['category'] = (string) ($target['name'] ?? 'General');
            $policy['updated_at'] = gmdate('c');
        }
        unset($policy);

        $store['categories'] = array_values(array_filter(
            $store['categories'],
            static fn (array $category): bool =>
                (string) ($category['id'] ?? '') !== $sourceId
        ));

        $this->write($store);

        return true;
    }

    /** @param array<string, mixed> $store */
    private function uniqueCategorySlug(
        string $slug,
        array $store,
        ?string $ignoreId
    ): string {
        $base = $slug !== '' ? $slug : 'category';
        $candidate = $base;
        $suffix = 2;

        $exists = static function (string $value) use ($store, $ignoreId): bool {
            foreach ($store['categories'] as $category) {
                if (
                    $ignoreId !== null
                    && (string) ($category['id'] ?? '') === $ignoreId
                ) {
                    continue;
                }

                if ((string) ($category['slug'] ?? '') === $value) {
                    return true;
                }
            }

            return false;
        };

        while ($exists($candidate)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** @return array<string, string> */
    public static function feedbackReasonLabels(): array
    {
        return self::FEEDBACK_REASONS;
    }

    public function feedbackSummary(string $policyId): array
    {
        $store = $this->read();
        return $this->normalizeFeedbackSummary(
            is_array($store['feedback'][$policyId] ?? null) ? $store['feedback'][$policyId] : []
        );
    }

    public function feedbackSummaries(): array
    {
        $store = $this->read();
        $summaries = [];
        foreach ($store['policies'] as $policy) {
            $policyId = (string) ($policy['id'] ?? '');
            if ($policyId === '') { continue; }
            $summaries[$policyId] = $this->normalizeFeedbackSummary(
                is_array($store['feedback'][$policyId] ?? null) ? $store['feedback'][$policyId] : []
            );
        }
        return $summaries;
    }

    public function feedbackTotals(): array
    {
        $helpful = 0; $notHelpful = 0;
        foreach ($this->feedbackSummaries() as $summary) {
            $helpful += (int) ($summary['helpful'] ?? 0);
            $notHelpful += (int) ($summary['not_helpful'] ?? 0);
        }
        $total = $helpful + $notHelpful;
        return [
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'total' => $total,
            'helpful_rate' => $total > 0 ? (int) round(($helpful / $total) * 100) : 0,
        ];
    }

    /**
     * Aggregate-only feedback. No IP address, account ID, user-agent, email,
     * free-text comment, or individual feedback event is retained.
     */
    public function submitFeedback(string $policyId, string $value, string $reason = ''): array
    {
        $policyId = trim($policyId); $value = trim($value); $reason = trim($reason);
        if ($policyId === '') { throw new RuntimeException('Policy feedback requires a policy ID.'); }
        if (!in_array($value, ['helpful', 'not_helpful'], true)) {
            throw new RuntimeException('Unsupported policy feedback value.');
        }

        $store = $this->read();
        $policyExists = false;
        foreach ($store['policies'] as $policy) {
            if ((string) ($policy['id'] ?? '') === $policyId) { $policyExists = true; break; }
        }
        if (!$policyExists) { throw new RuntimeException('Policy feedback target was not found.'); }

        $summary = $this->normalizeFeedbackSummary(
            is_array($store['feedback'][$policyId] ?? null) ? $store['feedback'][$policyId] : []
        );

        if ($value === 'helpful') {
            $summary['helpful']++;
        } else {
            $summary['not_helpful']++;
            if ($reason !== '' && array_key_exists($reason, self::FEEDBACK_REASONS)) {
                $summary['reasons'][$reason]++;
            }
        }

        $summary['total'] = $summary['helpful'] + $summary['not_helpful'];
        $summary['helpful_rate'] = $summary['total'] > 0
            ? (int) round(($summary['helpful'] / $summary['total']) * 100)
            : 0;
        $summary['updated_at'] = gmdate('c');

        $store['feedback'][$policyId] = [
            'helpful' => $summary['helpful'],
            'not_helpful' => $summary['not_helpful'],
            'reasons' => $summary['reasons'],
            'updated_at' => $summary['updated_at'],
        ];
        $this->write($store);
        return $summary;
    }

    private function normalizeFeedbackSummary(array $input): array
    {
        $helpful = max(0, (int) ($input['helpful'] ?? 0));
        $notHelpful = max(0, (int) ($input['not_helpful'] ?? 0));
        $reasons = [];
        foreach (self::FEEDBACK_REASONS as $key => $_label) {
            $reasons[$key] = max(0, (int) (is_array($input['reasons'] ?? null) ? ($input['reasons'][$key] ?? 0) : 0));
        }
        $total = $helpful + $notHelpful;
        return [
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'total' => $total,
            'helpful_rate' => $total > 0 ? (int) round(($helpful / $total) * 100) : 0,
            'reasons' => $reasons,
            'updated_at' => trim((string) ($input['updated_at'] ?? '')),
        ];
    }

    /** @return array<int, array{question:string,answer:string}> */
    public function centerFaqs(): array
    {
        $faqs = $this->read()['faqs'];

        if ($faqs !== []) {
            return $faqs;
        }

        return [
            [
                'question' => 'How do I know which Barama policy is current?',
                'answer' => 'The Policy Center displays the currently published version of each public policy. The policy detail page also shows its version and last-updated information.',
            ],
            [
                'question' => 'Where can I see when a policy takes effect?',
                'answer' => 'Policies with an effective date show it on both the Policy Center card and the detailed policy page.',
            ],
            [
                'question' => 'Why does Barama publish different types of policies and terms?',
                'answer' => 'Different policies explain different platform responsibilities, including marketplace use, listings, account security, privacy, payments, communications, intellectual property, fraud prevention, and enforcement.',
            ],
            [
                'question' => 'Can Barama policies change after publication?',
                'answer' => 'Yes. Policies may be updated when platform features, marketplace practices, operational requirements, or applicable legal and regulatory expectations change.',
            ],
            [
                'question' => 'How can I find a specific policy quickly?',
                'answer' => 'Use the Policy Center search field to search published policies by their title, summary, or related policy information.',
            ],
            [
                'question' => 'Do policy rules apply to properties, projects, companies, and user accounts?',
                'answer' => 'Relevant Barama policies may apply to listings, projects, companies, account activity, inquiries, communications, payments, and other marketplace features. The detailed policy text explains the applicable scope.',
            ],
            [
                'question' => 'Where should I report suspected fraud, scams, abuse, or policy violations?',
                'answer' => 'Use Barama support or the relevant reporting channel available on the platform. Include enough information for the issue to be reviewed without sharing sensitive credentials.',
            ],
            [
                'question' => 'How do the Privacy Policy and Cookie Policy relate to the marketplace?',
                'answer' => 'Those policies explain how Barama handles relevant personal information, privacy choices, cookies, and related data practices. Read the published policy text for the current details.',
            ],
            [
                'question' => 'What happens if a user or listing violates a Barama policy?',
                'answer' => 'Depending on the applicable policy and circumstances, Barama may review, restrict, remove, suspend, or take other proportionate platform actions described in the relevant published policy.',
            ],
            [
                'question' => 'Are Policy Center FAQs a substitute for the policy text?',
                'answer' => 'No. FAQs are general guidance designed to help users navigate the Policy Center. The published policy text is the primary source for the specific rule, scope, or requirement.',
            ],
            [
                'question' => 'Who can I contact if I still have a policy question?',
                'answer' => 'Use Contact Support from the Barama website if you need help locating or understanding a published policy.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function emptyPolicy(): array
    {
        return [
            'id' => '',
            'title' => '',
            'slug' => '',
            'summary' => '',
            'content_html' => '',
            'category_id' => '',
            'category' => 'General',
            'status' => 'draft',
            'visibility' => 'public',
            'effective_date' => '',
            'sort_order' => 0,
            'featured' => false,
            'seo_title' => '',
            'meta_description' => '',
            'faqs' => [],
            'version_number' => 0,
            'versions' => [],
            'version_note' => '',
            'compliance_status' => 'not_reviewed',
            'risk_level' => 'unrated',
            'jurisdiction' => '',
            'legal_basis' => '',
            'compliance_owner' => '',
            'reviewed_by' => '',
            'last_reviewed_at' => '',
            'next_review_date' => '',
            'evidence_url' => '',
            'compliance_notes' => '',
            'compliance_checks' => array_fill_keys(self::COMPLIANCE_CHECKS, false),
            'compliance_progress' => 0,
            'compliance_gate_enabled' => true,
            'compliance_updated_at' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $store
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function normalize(array $input, array $store, ?array $existing): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $title = $title !== '' ? mb_substr($title, 0, 160) : 'Untitled Policy';

        $id = (string) ($existing['id'] ?? $this->id());
        $requestedSlug = trim((string) ($input['slug'] ?? ''));
        $slug = $this->slug($requestedSlug !== '' ? $requestedSlug : $title);
        $slug = $this->uniqueSlug($slug, $store, $id);

        $requestedCategoryId = trim((string) ($input['category_id'] ?? ''));
        $resolvedCategoryId = '';
        $resolvedCategoryName = '';

        if ($requestedCategoryId !== '') {
            foreach ($store['categories'] as $category) {
                if ((string) ($category['id'] ?? '') !== $requestedCategoryId) {
                    continue;
                }

                $resolvedCategoryId = (string) ($category['id'] ?? '');
                $resolvedCategoryName = (string) ($category['name'] ?? '');
                break;
            }
        }

        if ($resolvedCategoryName === '') {
            $fallbackCategory = trim((string) (
                $input['category']
                ?? $existing['category']
                ?? 'General'
            ));
            $fallbackCategory = $fallbackCategory !== ''
                ? mb_substr($fallbackCategory, 0, 80)
                : 'General';

            foreach ($store['categories'] as $category) {
                if (strcasecmp(
                    (string) ($category['name'] ?? ''),
                    $fallbackCategory
                ) !== 0) {
                    continue;
                }

                $resolvedCategoryId = (string) ($category['id'] ?? '');
                $resolvedCategoryName = (string) ($category['name'] ?? '');
                break;
            }

            if ($resolvedCategoryName === '') {
                $resolvedCategoryName = $fallbackCategory;
            }
        }

        $selectedChecks = is_array($input['compliance_check'] ?? null)
            ? array_map('strval', $input['compliance_check'])
            : [];

        $checks = [];

        foreach (self::COMPLIANCE_CHECKS as $key) {
            $checks[$key] = in_array($key, $selectedChecks, true);
        }

        $progress = (int) round(
            (count(array_filter($checks)) / max(1, count(self::COMPLIANCE_CHECKS))) * 100
        );

        $complianceStatus = (string) ($input['compliance_status'] ?? 'not_reviewed');
        if (!in_array(
            $complianceStatus,
            ['not_reviewed', 'in_review', 'compliant', 'action_required', 'not_applicable'],
            true
        )) {
            $complianceStatus = 'not_reviewed';
        }

        $riskLevel = (string) ($input['risk_level'] ?? 'unrated');
        if (!in_array($riskLevel, ['unrated', 'low', 'medium', 'high', 'critical'], true)) {
            $riskLevel = 'unrated';
        }

        $lastReviewedAt = $this->date((string) ($input['last_reviewed_at'] ?? ''));
        $nextReviewDate = $this->date((string) ($input['next_review_date'] ?? ''));
        $effectiveDate = $this->date((string) ($input['effective_date'] ?? ''));

        $evidenceUrl = trim((string) ($input['evidence_url'] ?? ''));
        if (
            $evidenceUrl !== ''
            && preg_match('#^(https://|/)#i', $evidenceUrl) !== 1
        ) {
            $evidenceUrl = '';
        }

        $gateEnabled = isset($input['compliance_gate_enabled'])
            && (string) $input['compliance_gate_enabled'] === '1';

        $saveMode = (string) ($input['save_mode'] ?? '');
        $requestedStatus = (string) ($input['status'] ?? 'draft');
        $status = $saveMode === 'publish'
            ? 'published'
            : (in_array($requestedStatus, ['draft', 'published'], true)
                ? $requestedStatus
                : 'draft');

        $requestedVisibility = (string) ($input['visibility'] ?? 'public');
        $visibility = in_array($requestedVisibility, ['public', 'private'], true)
            ? $requestedVisibility
            : 'public';

        $faqQuestions = is_array($input['faq_question'] ?? null)
            ? $input['faq_question']
            : [];
        $faqAnswers = is_array($input['faq_answer'] ?? null)
            ? $input['faq_answer']
            : [];

        $faqs = [];
        $faqCount = min(count($faqQuestions), count($faqAnswers), 30);

        for ($index = 0; $index < $faqCount; $index++) {
            $question = trim((string) $faqQuestions[$index]);
            $answer = trim((string) $faqAnswers[$index]);

            if ($question === '' && $answer === '') {
                continue;
            }

            $faqs[] = [
                'question' => mb_substr($question, 0, 240),
                'answer' => mb_substr($answer, 0, 1200),
            ];
        }

        $record = [
            'id' => $id,
            'title' => $title,
            'slug' => mb_substr($slug !== '' ? $slug : 'policy', 0, 180),
            'summary' => mb_substr(
                trim((string) ($input['summary'] ?? '')),
                0,
                420
            ),
            'content_html' => $this->sanitizeHtml(
                (string) ($input['content_html'] ?? '')
            ),
            'category_id' => $resolvedCategoryId,
            'category' => $resolvedCategoryName,
            'status' => $status,
            'visibility' => $visibility,
            'effective_date' => $effectiveDate,
            'sort_order' => max(
                0,
                min(9999, (int) ($input['sort_order'] ?? 0))
            ),
            'featured' => isset($input['featured'])
                && (string) $input['featured'] === '1',
            'seo_title' => mb_substr(
                trim((string) ($input['seo_title'] ?? '')),
                0,
                180
            ),
            'meta_description' => mb_substr(
                trim((string) ($input['meta_description'] ?? '')),
                0,
                320
            ),
            'faqs' => $faqs,
            'compliance_status' => $complianceStatus,
            'risk_level' => $riskLevel,
            'jurisdiction' => mb_substr(
                trim((string) ($input['jurisdiction'] ?? '')),
                0,
                180
            ),
            'legal_basis' => mb_substr(
                trim((string) ($input['legal_basis'] ?? '')),
                0,
                1000
            ),
            'compliance_owner' => mb_substr(
                trim((string) ($input['compliance_owner'] ?? '')),
                0,
                160
            ),
            'reviewed_by' => mb_substr(
                trim((string) ($input['reviewed_by'] ?? '')),
                0,
                160
            ),
            'last_reviewed_at' => $lastReviewedAt,
            'next_review_date' => $nextReviewDate,
            'evidence_url' => mb_substr($evidenceUrl, 0, 500),
            'compliance_notes' => mb_substr(
                trim((string) ($input['compliance_notes'] ?? '')),
                0,
                4000
            ),
            'compliance_checks' => $checks,
            'compliance_progress' => $progress,
            'compliance_gate_enabled' => $gateEnabled,
            'compliance_updated_at' => gmdate('c'),
            'created_at' => (string) ($existing['created_at'] ?? gmdate('c')),
            'updated_at' => gmdate('c'),
        ];

        $wasPublished = is_array($existing)
            && (string) ($existing['status'] ?? 'draft') === 'published';

        $publicationBlocked = false;
        $importedTemplate = !empty($existing['imported_demo_template']);
        if ($importedTemplate) {
            // Ignore untrusted form fields claiming this template is approved,
            // or attempting to disable the compliance gate. Publication must
            // be an explicit action with evidence and resolved template text.
            $record['imported_demo_template'] = true;
            $record['compliance_gate_enabled'] = true;
            $ready = $saveMode === 'publish'
                && $status === 'published'
                && $visibility === 'public'
                && $this->isComplianceReady($record)
                && !self::hasUnresolvedDemoInstructions((string) $record['content_html']);
            $record['demo_template_approved'] = $ready;
            if (!$ready) {
                $publicationBlocked = $saveMode === 'publish'
                    || $requestedStatus === 'published';
                $record['status'] = 'draft';
                $record['visibility'] = 'private';
            }
        } elseif (
            $saveMode === 'publish'
            && !$wasPublished
            && $gateEnabled
            && !$this->isComplianceReady($record)
        ) {
            $record['status'] = 'draft';
            $publicationBlocked = true;
        }

        $record['_publication_blocked'] = $publicationBlocked;

        return $record;
    }

    /** Editorial instructions must not become binding customer policies. */
    private static function hasUnresolvedDemoInstructions(string $html): bool
    {
        $text = mb_strtolower(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        foreach ([
            'administrator review required before publication',
            'confirm the operator legal name',
            'insert the operator',
            'before publication, insert',
            'configure actual periods before publication',
            'configure the official support',
            'configure a copyright',
            'configure an accessibility contact',
            'do not publish until',
            'limitation of liability should be finalized',
        ] as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $policy */
    private function isComplianceReady(array $policy): bool
    {
        if ((string) ($policy['compliance_status'] ?? '') !== 'compliant') {
            return false;
        }

        if ((int) ($policy['compliance_progress'] ?? 0) < 100) {
            return false;
        }

        if (trim((string) ($policy['reviewed_by'] ?? '')) === '') {
            return false;
        }

        if (trim((string) ($policy['last_reviewed_at'] ?? '')) === '') {
            return false;
        }

        $nextReview = trim((string) ($policy['next_review_date'] ?? ''));

        if (
            $nextReview !== ''
            && strtotime($nextReview . ' 23:59:59 UTC') !== false
            && strtotime($nextReview . ' 23:59:59 UTC') < time()
        ) {
            return false;
        }

        return true;
    }

    /** @return array<string, bool> */
    private function blankComplianceChecks(): array
    {
        return array_fill_keys(self::COMPLIANCE_CHECKS, false);
    }

    private function date(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? $value
            : '';
    }

    /** @param array<string, mixed> $policy */
    private function nextVersionNumber(array $policy): int
    {
        $max = max(0, (int) ($policy['version_number'] ?? 0));

        foreach ((array) ($policy['versions'] ?? []) as $version) {
            if (is_array($version)) {
                $max = max($max, (int) ($version['version_number'] ?? 0));
            }
        }

        return $max + 1;
    }

    /** @return list<string> */
    private function snapshotFields(): array
    {
        return [
            'title',
            'slug',
            'summary',
            'content_html',
            'category_id',
            'category',
            'status',
            'visibility',
            'effective_date',
            'sort_order',
            'featured',
            'seo_title',
            'meta_description',
            'faqs',
            'compliance_status',
            'risk_level',
            'jurisdiction',
            'legal_basis',
            'compliance_owner',
            'reviewed_by',
            'last_reviewed_at',
            'next_review_date',
            'evidence_url',
            'compliance_notes',
            'compliance_checks',
            'compliance_progress',
            'compliance_gate_enabled',
            'compliance_updated_at',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function snapshot(
        array $record,
        string $note,
        string $restoredFrom = ''
    ): array {
        $data = [];

        foreach ($this->snapshotFields() as $field) {
            if (array_key_exists($field, $record)) {
                $data[$field] = $record[$field];
            }
        }

        return [
            'id' => $this->id(),
            'version_number' => (int) ($record['version_number'] ?? 1),
            'saved_at' => gmdate('c'),
            'note' => mb_substr(trim($note), 0, 240),
            'restored_from' => $restoredFrom,
            'data' => $data,
        ];
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><h2><h3><h4><ul><ol><li><strong><b><em><i><a><blockquote><hr><br>';
        $html = strip_tags($html, $allowed);

        if (!class_exists(DOMDocument::class)) {
            $html = preg_replace(
                '/\son\w+\s*=\s*(["\']).*?\1/i',
                '',
                $html
            ) ?? $html;
            $html = preg_replace(
                '/\sstyle\s*=\s*(["\']).*?\1/i',
                '',
                $html
            ) ?? $html;
            $html = preg_replace(
                '/href\s*=\s*(["\'])\s*javascript:.*?\1/i',
                'href="#"',
                $html
            ) ?? $html;

            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="policy-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="policy-root"]//*');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $remove = [];

                foreach ($node->attributes ?? [] as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = trim($attribute->value);

                    if (
                        str_starts_with($name, 'on')
                        || in_array($name, ['style', 'src'], true)
                    ) {
                        $remove[] = $attribute->name;
                        continue;
                    }

                    if ($node->tagName === 'a' && $name === 'href') {
                        if (preg_match('#^(https://|/|mailto:)#i', $value) !== 1) {
                            $remove[] = $attribute->name;
                        }
                        continue;
                    }

                    if (
                        !(
                            $node->tagName === 'a'
                            && in_array($name, ['href', 'target', 'rel'], true)
                        )
                    ) {
                        $remove[] = $attribute->name;
                    }
                }

                foreach ($remove as $name) {
                    $node->removeAttribute($name);
                }

                if ($node->tagName === 'a' && $node->hasAttribute('target')) {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }

        $root = $dom->getElementById('policy-root');

        if ($root === null) {
            return $html;
        }

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $dom->saveHTML($child);
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $store
     */
    private function uniqueSlug(string $slug, array $store, ?string $ignoreId): string
    {
        $base = $slug !== '' ? $slug : 'policy';
        $candidate = $base;
        $suffix = 2;

        $exists = static function (string $value) use ($store, $ignoreId): bool {
            foreach ($store['policies'] as $policy) {
                if (
                    $ignoreId !== null
                    && (string) ($policy['id'] ?? '') === $ignoreId
                ) {
                    continue;
                }

                if ((string) ($policy['slug'] ?? '') === $value) {
                    return true;
                }
            }

            return false;
        };

        while ($exists($candidate)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->storePath();

        if (!is_file($path)) {
            return [
                'version' => 1,
                'policies' => [],
                'categories' => [],
                'faqs' => [],
                'feedback' => [],
            ];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [
                'version' => 1,
                'policies' => [],
                'categories' => [],
                'faqs' => [],
                'feedback' => [],
            ];
        }

        try {
            flock($handle, LOCK_SH);
            $json = stream_get_contents($handle) ?: '';
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [
                'version' => 1,
                'policies' => [],
                'categories' => [],
                'faqs' => [],
                'feedback' => [],
            ];
        }

        return [
            'version' => (int) ($decoded['version'] ?? 1),
            'policies' => is_array($decoded['policies'] ?? null)
                ? array_values($decoded['policies'])
                : [],
            'categories' => is_array($decoded['categories'] ?? null)
                ? array_values($decoded['categories'])
                : [],
            'faqs' => is_array($decoded['faqs'] ?? null)
                ? array_values($decoded['faqs'])
                : [],
            'feedback' => is_array($decoded['feedback'] ?? null)
                ? $decoded['feedback']
                : [],
        ];
    }

    /** @param array<string, mixed> $store */
    private function write(array $store): void
    {
        $path = $this->storePath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Policy Center storage directory could not be created.');
        }

        $json = json_encode(
            $store,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );

        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        $handle = fopen($temporary, 'xb');

        if ($handle === false) {
            throw new RuntimeException('Policy Center temporary store could not be created.');
        }

        try {
            flock($handle, LOCK_EX);
            fwrite($handle, $json . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        chmod($temporary, 0664);

        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Policy Center store could not be replaced atomically.');
        }
    }

    private function storePath(): string
    {
        return rtrim((string) BASE_PATH, '/\\') . self::STORE;
    }

    private function id(): string
    {
        return bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(4));
    }
}
