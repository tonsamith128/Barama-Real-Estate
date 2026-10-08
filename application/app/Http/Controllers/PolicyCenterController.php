<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\PolicyCenterRepository;

final class PolicyCenterController
{
    public function index(Request $request): Response
    {
        $repository = new PolicyCenterRepository();
        $published = $repository->published();
        $slug = $this->requestedSlug($request);
        $selected = $slug !== '' ? $repository->findBySlug($slug) : null;
        $query = trim((string) $request->input('q', ''));

        $visible = $published;

        if ($selected === null && $query !== '') {
            $needle = mb_strtolower($query, 'UTF-8');

            $visible = array_values(array_filter(
                $published,
                static function (array $policy) use ($needle): bool {
                    $haystack = mb_strtolower(
                        (string) ($policy['title'] ?? '') . ' '
                        . (string) ($policy['summary'] ?? '') . ' '
                        . (string) ($policy['category'] ?? ''),
                        'UTF-8'
                    );

                    return str_contains($haystack, $needle);
                }
            ));
        }

        $pageTitle = $selected !== null
            ? (string) ($selected['seo_title'] ?: $selected['title'])
            : 'Policy Center';

        $metaDescription = $selected !== null
            ? (string) (
                $selected['meta_description']
                ?: $selected['summary']
                ?: 'Barama Real Estate policy information.'
            )
            : 'Search and review current Barama Real Estate policies, terms, and marketplace standards.';

        return Response::html(View::render('policy/index', [
            'pageTitle' => $pageTitle . ' · Barama Real Estate',
            'metaDescription' => $metaDescription,
            'activeArea' => 'policies',
            'policies' => $visible,
            'allPolicies' => $published,
            'selectedPolicy' => $selected,
            'query' => $query,
            'policyCenterFaqs' => $repository->centerFaqs(),
        ]));
    }

    public function feedback(Request $request): Response
    {
        $honeypot = trim((string) $request->input('company_website', ''));
        if ($honeypot !== '') { return Response::html('OK'); }

        $policyId = trim((string) $request->input('policy_id', ''));
        $value = trim((string) $request->input('value', ''));
        $reason = trim((string) $request->input('reason', ''));

        try {
            (new PolicyCenterRepository())->submitFeedback($policyId, $value, $reason);
        } catch (\RuntimeException $exception) {
            return Response::html('INVALID');
        } catch (\Throwable $exception) {
            error_log('[PolicyCenter] feedback aggregate failed: ' . get_class($exception) . ': ' . $exception->getMessage());
            return Response::html('ERROR');
        }

        return Response::html('OK');
    }

    public function adminIndex(Request $request): Response
    {
        $repository = new PolicyCenterRepository();

        return Response::html(View::render('workspace/policies', [
            'pageTitle' => 'Policy Center · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'policies' => $repository->all(),
            'policyFeedback' => $repository->feedbackSummaries(),
            'policyFeedbackTotals' => $repository->feedbackTotals(),
            'policyFeedbackReasonLabels' => PolicyCenterRepository::feedbackReasonLabels(),
        ]));
    }

    public function feedbackAnalytics(Request $request): Response
    {
        $repository = new PolicyCenterRepository();

        return Response::html(View::render('workspace/policy-feedback', [
            'pageTitle' => 'Policy Feedback · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'policies' => $repository->all(),
            'policyFeedback' => $repository->feedbackSummaries(),
            'policyFeedbackTotals' => $repository->feedbackTotals(),
            'policyFeedbackReasonLabels' => PolicyCenterRepository::feedbackReasonLabels(),
        ]));
    }

    public function feedbackAnalyticsDetail(Request $request): Response
    {
        $repository = new PolicyCenterRepository();
        $policyId = trim((string) $request->input('id', ''));

        if ($policyId === '') {
            return Response::redirect(url('/dashboard/policies/feedback?missing=1'));
        }

        $policy = $repository->findById($policyId);

        if ($policy === null) {
            return Response::redirect(url('/dashboard/policies/feedback?missing=1'));
        }

        return Response::html(View::render('workspace/policy-feedback-detail', [
            'pageTitle' => (string) ($policy['title'] ?? 'Policy') . ' Feedback · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'policy' => $policy,
            'feedbackSummary' => $repository->feedbackSummary($policyId),
            'policyFeedbackReasonLabels' => PolicyCenterRepository::feedbackReasonLabels(),
        ]));
    }

    public function feedbackReviewQueue(Request $request): Response
    {
        $repository = new PolicyCenterRepository();

        return Response::html(View::render('workspace/policy-feedback-review', [
            'pageTitle' => 'Policy Feedback Review · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'policies' => $repository->all(),
            'policyFeedback' => $repository->feedbackSummaries(),
            'policyFeedbackReasonLabels' => PolicyCenterRepository::feedbackReasonLabels(),
        ]));
    }

    public function categories(Request $request): Response
    {
        $repository = new PolicyCenterRepository();
        $categories = $repository->categories();
        $usage = [];

        foreach ($categories as $category) {
            $categoryId = (string) ($category['id'] ?? '');
            $usage[$categoryId] = $repository->categoryUsageCount($categoryId);
        }

        $editId = trim((string) $request->input('edit', ''));
        $editingCategory = $editId !== ''
            ? $repository->findCategoryById($editId)
            : null;

        return Response::html(View::render('workspace/policy-categories', [
            'pageTitle' => 'Policy Categories · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'categories' => $categories,
            'categoryUsage' => $usage,
            'editingCategory' => $editingCategory,
        ]));
    }

    public function categoryStore(Request $request): Response
    {
        try {
            (new PolicyCenterRepository())->saveCategory($request->all());
        } catch (\RuntimeException $exception) {
            return Response::redirect(
                url('/dashboard/policies/categories?error=' . rawurlencode($exception->getMessage()))
            );
        }

        return Response::redirect(url('/dashboard/policies/categories?created=1'));
    }

    public function categoryUpdate(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        $repository = new PolicyCenterRepository();

        if ($id === '' || $repository->findCategoryById($id) === null) {
            return Response::redirect(url('/dashboard/policies/categories'));
        }

        try {
            $repository->saveCategory($request->all(), $id);
        } catch (\RuntimeException $exception) {
            return Response::redirect(
                url(
                    '/dashboard/policies/categories?edit='
                    . rawurlencode($id)
                    . '&error='
                    . rawurlencode($exception->getMessage())
                )
            );
        }

        return Response::redirect(url('/dashboard/policies/categories?updated=1'));
    }

    public function categoryDelete(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        $deleted = $id !== ''
            ? (new PolicyCenterRepository())->deleteCategory($id)
            : false;

        return Response::redirect(
            url(
                '/dashboard/policies/categories?'
                . ($deleted ? 'deleted=1' : 'delete_blocked=1')
            )
        );
    }

    public function categoryMerge(Request $request): Response
    {
        $sourceId = trim((string) $request->input('source_id', ''));
        $targetId = trim((string) $request->input('target_id', ''));
        $merged = (new PolicyCenterRepository())->mergeCategory(
            $sourceId,
            $targetId
        );

        return Response::redirect(
            url(
                '/dashboard/policies/categories?'
                . ($merged ? 'merged=1' : 'merge_failed=1')
            )
        );
    }

    public function create(Request $request): Response
    {
        return $this->builder(PolicyCenterRepository::emptyPolicy(), false);
    }

    public function edit(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        $record = (new PolicyCenterRepository())->findById($id);

        if ($record === null) {
            return Response::redirect(url('/dashboard/policies'));
        }

        return $this->builder($record, true);
    }

    public function store(Request $request): Response
    {
        try {
            $record = (new PolicyCenterRepository())->save($request->all());
        } catch (\Throwable $exception) {
            error_log(
                '[PolicyCenter] create failed: '
                . get_class($exception)
                . ': '
                . $exception->getMessage()
            );

            return Response::redirect(
                url('/dashboard/policies/create?save_error=1')
            );
        }

        $blocked = !empty($record['_publication_blocked']);

        return Response::redirect(
            url(
                '/dashboard/policies/edit?id='
                . rawurlencode((string) $record['id'])
                . ($blocked ? '&compliance_blocked=1' : '&saved=1')
            )
        );
    }

    public function update(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        $repository = new PolicyCenterRepository();

        if ($id === '' || $repository->findById($id) === null) {
            return Response::redirect(url('/dashboard/policies'));
        }

        $restoreVersionId = trim((string) $request->input('restore_version_id', ''));

        if ($restoreVersionId !== '') {
            $restored = $repository->restoreVersion($id, $restoreVersionId);

            return Response::redirect(
                url(
                    '/dashboard/policies/edit?id='
                    . rawurlencode($id)
                    . ($restored !== null ? '&restored=1' : '&restore_failed=1')
                )
            );
        }

        try {
            $record = $repository->save($request->all(), $id);
        } catch (\Throwable $exception) {
            error_log(
                '[PolicyCenter] update failed for '
                . $id
                . ': '
                . get_class($exception)
                . ': '
                . $exception->getMessage()
            );

            return Response::redirect(
                url(
                    '/dashboard/policies/edit?id='
                    . rawurlencode($id)
                    . '&save_error=1'
                )
            );
        }

        $blocked = !empty($record['_publication_blocked']);

        return Response::redirect(
            url(
                '/dashboard/policies/edit?id='
                . rawurlencode($id)
                . ($blocked ? '&compliance_blocked=1' : '&saved=1')
            )
        );
    }

    public function delete(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));

        if ($id !== '') {
            (new PolicyCenterRepository())->delete($id);
        }

        return Response::redirect(url('/dashboard/policies?deleted=1'));
    }

    public function duplicate(Request $request): Response
    {
        $id = trim((string) $request->input('id', ''));
        $copy = $id !== ''
            ? (new PolicyCenterRepository())->duplicate($id)
            : null;

        if ($copy === null) {
            return Response::redirect(url('/dashboard/policies'));
        }

        return Response::redirect(
            url('/dashboard/policies/edit?id=' . rawurlencode((string) $copy['id']))
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function builder(array $record, bool $editing): Response
    {
        return Response::html(View::render('workspace/policy-builder', [
            'pageTitle' => ($editing ? 'Edit Policy' : 'Create Policy') . ' · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => (new DashboardController())->policyCenterNavigation(),
            'activeSection' => 'policies',
            'record' => $record,
            'editing' => $editing,
            'categories' => (new PolicyCenterRepository())->categories(),
            'complianceCheckKeys' => PolicyCenterRepository::complianceCheckKeys(),
        ]));
    }

    private function requestedSlug(Request $request): string
    {
        $compatibilitySlug = trim((string) $request->input('policy', ''));

        if ($compatibilitySlug !== '') {
            return $this->slug($compatibilitySlug);
        }

        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));

        if ($queryString === '') {
            return '';
        }

        if (str_starts_with($queryString, '=')) {
            return $this->slug(rawurldecode(substr($queryString, 1)));
        }

        if (!str_contains($queryString, '=')) {
            return $this->slug(rawurldecode($queryString));
        }

        return '';
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }
}
