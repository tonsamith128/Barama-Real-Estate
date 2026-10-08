<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\FinanceRepository;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;
use Barama\Repositories\WorkspaceSettingsRepository;

final class DeveloperController
{
    private const SECTIONS = [
        'overview' => [
            'title' => 'Workspace Overview',
            'description' => 'Track company inventory, live marketplace activity, inquiries, and active leads.',
        ],
        'company' => [
            'title' => 'Company Profile',
            'description' => 'Manage the developer or agency records that own projects, properties, and team access.',
        ],
        'projects' => [
            'title' => 'Development Projects',
            'description' => 'Create and maintain project inventory before submitting it for marketplace review.',
        ],
        'properties' => [
            'title' => 'Properties',
            'description' => 'Manage canonical property records, specifications, and approval state.',
        ],
        'listings' => [
            'title' => 'Listings',
            'description' => 'Control sale and rental offers separately from the underlying property record.',
        ],
        'inquiries' => [
            'title' => 'Inquiries',
            'description' => 'Review marketplace messages, record responses, and convert qualified interest into leads.',
        ],
        'viewings' => [
            'title' => 'Viewing Requests',
            'description' => 'Confirm, reschedule, complete, or decline customer property viewing requests.',
        ],
        'leads' => [
            'title' => 'Lead Pipeline',
            'description' => 'Move qualified prospects through contact, viewing, negotiation, won, and lost stages.',
        ],
        'team' => [
            'title' => 'Team Members',
            'description' => 'Manage company representatives without weakening company ownership boundaries.',
        ],
        'media' => [
            'title' => 'Media Library',
            'description' => 'Upload validated private media and attach it to company-owned marketplace records.',
        ],
    ];

    public function index(Request $request): Response
    {
        return $this->renderSection($request, 'overview');
    }

    public function section(Request $request, string $section): Response
    {
        return $this->renderSection($request, strtolower(trim($section)));
    }

    public function billing(Request $request): Response
    {
        $user = auth_user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        return Response::html(View::render('workspace/developer-billing', [
            'pageTitle' => 'Billing & Plan · Company / Vendor',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Company / Vendor Workspace',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'billing',
            'billingCompanies' => (new FinanceRepository())
                ->developerBillingCompanies((int) $user['id']),
        ]));
    }

    public function record(Request $request, string $resource, string $id): Response
    {
        $resource = strtolower(trim($resource));

        if (!in_array($resource, ['company', 'project', 'property', 'listing', 'inquiry', 'viewing', 'lead'], true)
            || !ctype_digit($id)
            || (int) $id < 1
        ) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Record not found']),
                404
            );
        }

        $user = auth_user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $data = (new WorkspaceRecordRepository())->developerRecord(
            (int) $user['id'],
            $resource,
            (int) $id
        );

        if ($data === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Record not found']),
                404
            );
        }

        $record = $data['record'];
        $title = (string) (
            $record['name']
            ?? $record['title']
            ?? $record['property_title']
            ?? $record['contact_name']
            ?? ucfirst($resource)
        );

        return Response::html(View::render('workspace/record', [
            'pageTitle' => $title . ' · Company / Vendor',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Company / Vendor Workspace',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $this->sectionForResource($resource),
            'recordResource' => $resource,
            'record' => $record,
            'history' => $data['history'],
            'media' => $data['media'],
            'formOptions' => (new WorkspaceReadRepository())->developerOptions((int) $user['id']),
        ]));
    }

    private function renderSection(Request $request, string $section): Response
    {
        if (!isset(self::SECTIONS[$section])) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Page not found']),
                404
            );
        }

        $user = auth_user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }

        $settingsRepository = new WorkspaceSettingsRepository();
        $workspacePreferences = $settingsRepository->get((int) $user['id'], 'developer');
        $rememberFilters = !empty($workspacePreferences['remember_filters']);
        $savedFilters = is_array($workspacePreferences['last_filters'] ?? null) ? $workspacePreferences['last_filters'] : [];
        $hasFilterQuery = isset($_GET['q']) || isset($_GET['status']) || isset($_GET['type']) || isset($_GET['location']) || isset($_GET['readiness']) || isset($_GET['quality']) || isset($_GET['sort']);

        $search = mb_substr(trim((string) $request->input('q', $rememberFilters && !$hasFilterQuery ? ($savedFilters['q'] ?? '') : '')), 0, 80);
        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $page = $page === false ? 1 : (int) $page;

        $filters = [
            'status' => mb_substr(trim((string) $request->input('status', $rememberFilters && !$hasFilterQuery ? ($savedFilters['status'] ?? '') : '')), 0, 40),
            'type' => mb_substr(trim((string) $request->input('type', $rememberFilters && !$hasFilterQuery ? ($savedFilters['type'] ?? '') : '')), 0, 80),
            'location' => trim((string) $request->input('location', $rememberFilters && !$hasFilterQuery ? ($savedFilters['location'] ?? '') : '')),
            'readiness' => mb_substr(trim((string) $request->input('readiness', $rememberFilters && !$hasFilterQuery ? ($savedFilters['readiness'] ?? '') : '')), 0, 20),
            'quality' => mb_substr(trim((string) $request->input('quality', $rememberFilters && !$hasFilterQuery ? ($savedFilters['quality'] ?? '') : '')), 0, 40),
        ];
        $sortDefault = (string) ($workspacePreferences['default_sort'] ?? 'updated_desc');
        $sort = mb_substr(trim((string) $request->input('sort', $rememberFilters && !$hasFilterQuery ? ($savedFilters['sort'] ?? $sortDefault) : $sortDefault)), 0, 40);
        $pageSize = in_array((int) ($workspacePreferences['page_size'] ?? 20), [10, 20, 50], true) ? (int) ($workspacePreferences['page_size'] ?? 20) : 20;
        // Listings Pagination Standard Repair v1.4.7:
        // keep the existing workspace page-size logic intact, but standardize Listings.
        if ($section === 'listings') {
            $pageSize = 10;
        }

        // Projects Pagination Standard Repair v1.5.1:
        // Projects use the same compact 10-item inventory pagination standard.
        if ($section === 'projects') {
            $pageSize = 10;
        }

        // Companies Pagination Standard v1.5.5:
        // Company portfolio inventory uses the compact 10-item pagination standard.
        if ($section === 'company') {
            $pageSize = 10;
        }

        // Inquiries Pagination Standard v1.5.8:
        // Customer inquiry queues use the compact 10-item pagination standard.
        if ($section === 'inquiries') {
            $pageSize = 10;
        }

        // Leads Pagination Standard v1.6.0:
        // Property lead queues use the compact 10-item pagination standard.
        if ($section === 'leads') {
            $pageSize = 10;
        }

        // Viewings Pagination Standard v1.6.3:
        // Viewing appointment queues use the compact 10-item pagination standard.
        if ($section === 'viewings') {
            $pageSize = 10;
        }

        if ($rememberFilters && $hasFilterQuery) {
            $workspacePreferences['last_filters'] = ['q'=>$search, 'status'=>$filters['status'], 'type'=>$filters['type'], 'location'=>$filters['location'], 'readiness'=>$filters['readiness'], 'quality'=>$filters['quality'], 'sort'=>$sort];
            $settingsRepository->save((int) $user['id'], 'developer', $workspacePreferences);
        }

        $repository = new WorkspaceReadRepository();
        $formOptions = $repository->developerOptions((int) $user['id']);
        $summary = $repository->developerSummary((int) $user['id']);
        $portfolioSummary = $repository->developerPortfolioSummary((int) $user['id'], $section);
        $propertyQualityOperations = [];
        if ($section === 'properties') {
            $propertyReadinessService = new \Barama\Services\PropertyReadinessService();
            if (strtolower(trim((string) $request->input('export', ''))) === 'readiness_csv') {
                return Response::binary(
                    $propertyReadinessService->csvReportForDeveloper((int) $user['id'], $filters),
                    'text/csv; charset=UTF-8',
                    ['Content-Disposition' => 'attachment; filename="barama-property-readiness-' . gmdate('Ymd') . '.csv"']
                );
            }
            $propertyQualityOperations = $propertyReadinessService->operationalSummaryForDeveloper((int) $user['id']);
        }
        $customerOperationsSummary = $repository->developerCustomerOperationsSummary(
            (int) $user['id'],
            $section
        );
        $workspaceManagementSummary = $repository->developerWorkspaceManagementSummary(
            (int) $user['id'],
            $section
        );
        $records = $repository->developerSection(
            (int) $user['id'],
            $section,
            $search,
            $page,
            $section === 'overview' ? min(6, $pageSize) : $pageSize,
            $filters,
            $sort
        );

        return Response::html(View::render('workspace/index', [
            'pageTitle' => self::SECTIONS[$section]['title'] . ' · Company / Vendor',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Company / Vendor Workspace',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'stats' => $this->statsForSection(
                $section,
                $summary,
                $portfolioSummary,
                $customerOperationsSummary,
                $workspaceManagementSummary
            ),
            'emptyState' => $this->emptyState($section),
            'sectionGuide' => $this->sectionGuide($section),
            'records' => $records,
            'columns' => $this->columns($section),
            'search' => $search,
            'tableFilters' => $filters,
            'tableSort' => $sort,
            'workspacePreferences' => $workspacePreferences,
            'filterOptions' => $this->filterOptions($section),
            'propertyQualityOperations' => $propertyQualityOperations,
            'formOptions' => $formOptions,
        ]));
    }

    /**
     * @param array<string,int> $summary
     * @param array<string,int> $portfolio
     * @return array<int,array{label:string,value:int,icon:string,href?:string}>
     */
    private function statsForSection(
        string $section,
        array $summary,
        array $portfolio,
        array $customerOperations,
        array $workspaceManagement
    ): array {
        return match ($section) {
            'team' => [
                ['label' => 'Team records', 'value' => $workspaceManagement['total'] ?? 0, 'icon' => 'users', 'href' => '/developer/team'],
                ['label' => 'Active members', 'value' => $workspaceManagement['active'] ?? 0, 'icon' => 'check', 'href' => '/developer/team?status=active'],
                ['label' => 'Managers', 'value' => $workspaceManagement['managers'] ?? 0, 'icon' => 'shield', 'href' => '/developer/team?type=manager'],
                ['label' => 'Agents', 'value' => $workspaceManagement['agents'] ?? 0, 'icon' => 'user', 'href' => '/developer/team?type=agent'],
                ['label' => 'Pending invites', 'value' => $workspaceManagement['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/team?status=pending'],
            ],
            'media' => [
                ['label' => 'Media attachments', 'value' => $workspaceManagement['total'] ?? 0, 'icon' => 'image', 'href' => '/developer/media'],
                ['label' => 'Images', 'value' => $workspaceManagement['images'] ?? 0, 'icon' => 'image'],
                ['label' => 'PDFs', 'value' => $workspaceManagement['pdfs'] ?? 0, 'icon' => 'layers'],
                ['label' => 'Primary images', 'value' => $workspaceManagement['primary'] ?? 0, 'icon' => 'check'],
                ['label' => 'Locked by workflow', 'value' => $workspaceManagement['locked'] ?? 0, 'icon' => 'lock'],
            ],
            'inquiries' => [
                ['label' => 'All inquiries', 'value' => $customerOperations['total'] ?? 0, 'icon' => 'message', 'href' => '/developer/inquiries'],
                ['label' => 'New', 'value' => $customerOperations['new'] ?? 0, 'icon' => 'bell', 'href' => '/developer/inquiries?status=new'],
                ['label' => 'Read', 'value' => $customerOperations['read'] ?? 0, 'icon' => 'eye', 'href' => '/developer/inquiries?status=read'],
                ['label' => 'Replied', 'value' => $customerOperations['replied'] ?? 0, 'icon' => 'check', 'href' => '/developer/inquiries?status=replied'],
                ['label' => 'Converted leads', 'value' => $customerOperations['converted'] ?? 0, 'icon' => 'pipeline'],
            ],
            'viewings' => [
                ['label' => 'All viewings', 'value' => $customerOperations['total'] ?? 0, 'icon' => 'calendar', 'href' => '/developer/viewings'],
                ['label' => 'Pending', 'value' => $customerOperations['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/viewings?status=pending'],
                ['label' => 'Confirmed', 'value' => $customerOperations['confirmed'] ?? 0, 'icon' => 'check', 'href' => '/developer/viewings?status=confirmed'],
                ['label' => 'Rescheduled', 'value' => $customerOperations['rescheduled'] ?? 0, 'icon' => 'calendar', 'href' => '/developer/viewings?status=rescheduled'],
                ['label' => 'Completed', 'value' => $customerOperations['completed'] ?? 0, 'icon' => 'shield', 'href' => '/developer/viewings?status=completed'],
            ],
            'leads' => [
                ['label' => 'All property leads', 'value' => $customerOperations['total'] ?? 0, 'icon' => 'pipeline', 'href' => '/developer/leads'],
                ['label' => 'New', 'value' => $customerOperations['new'] ?? 0, 'icon' => 'bell', 'href' => '/developer/leads?status=new'],
                ['label' => 'Qualified', 'value' => $customerOperations['qualified'] ?? 0, 'icon' => 'check', 'href' => '/developer/leads?status=qualified'],
                ['label' => 'Negotiation', 'value' => $customerOperations['negotiation'] ?? 0, 'icon' => 'message', 'href' => '/developer/leads?status=negotiation'],
                ['label' => 'Won', 'value' => $customerOperations['won'] ?? 0, 'icon' => 'shield', 'href' => '/developer/leads?status=won'],
            ],
            'company' => [
                ['label' => 'All companies', 'value' => $portfolio['total'] ?? 0, 'icon' => 'building', 'href' => '/developer/company'],
                ['label' => 'Draft', 'value' => $portfolio['draft'] ?? 0, 'icon' => 'edit', 'href' => '/developer/company?status=draft'],
                ['label' => 'Pending review', 'value' => $portfolio['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/company?status=pending'],
                ['label' => 'Approved', 'value' => $portfolio['approved'] ?? 0, 'icon' => 'shield', 'href' => '/developer/company?status=approved'],
                ['label' => 'Rejected', 'value' => $portfolio['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/developer/company?status=rejected'],
            ],
            'projects' => [
                ['label' => 'All projects', 'value' => $portfolio['total'] ?? 0, 'icon' => 'layers', 'href' => '/developer/projects'],
                ['label' => 'Draft', 'value' => $portfolio['draft'] ?? 0, 'icon' => 'edit', 'href' => '/developer/projects?status=draft'],
                ['label' => 'Pending review', 'value' => $portfolio['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/projects?status=pending'],
                ['label' => 'Approved', 'value' => $portfolio['approved'] ?? 0, 'icon' => 'shield', 'href' => '/developer/projects?status=approved'],
                ['label' => 'Published', 'value' => $portfolio['published'] ?? 0, 'icon' => 'home', 'href' => '/developer/projects?status=published'],
            ],
            'properties' => [
                ['label' => 'All properties', 'value' => $portfolio['total'] ?? 0, 'icon' => 'home', 'href' => '/developer/properties'],
                ['label' => 'Draft', 'value' => $portfolio['draft'] ?? 0, 'icon' => 'edit', 'href' => '/developer/properties?status=draft'],
                ['label' => 'Pending review', 'value' => $portfolio['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/properties?status=pending'],
                ['label' => 'Approved', 'value' => $portfolio['approved'] ?? 0, 'icon' => 'shield', 'href' => '/developer/properties?status=approved'],
                ['label' => 'Rejected', 'value' => $portfolio['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/developer/properties?status=rejected'],
                ['label' => 'Excellent · 100%', 'value' => $portfolio['excellent'] ?? 0, 'icon' => 'check'],
                ['label' => 'Good · 80–99%', 'value' => $portfolio['good'] ?? 0, 'icon' => 'shield'],
                ['label' => 'Needs attention · 50–79%', 'value' => $portfolio['needs_attention'] ?? 0, 'icon' => 'alert'],
                ['label' => 'Incomplete · below 50%', 'value' => $portfolio['incomplete'] ?? 0, 'icon' => 'alert'],
            ],
            'listings' => [
                // Listings Summary Focus v1.4.9 - primary operational states only.
                ['label' => 'All listings', 'value' => $portfolio['total'] ?? 0, 'icon' => 'tag', 'href' => '/developer/listings'],
                ['label' => 'Draft', 'value' => $portfolio['draft'] ?? 0, 'icon' => 'edit', 'href' => '/developer/listings?status=draft'],
                ['label' => 'Pending review', 'value' => $portfolio['pending'] ?? 0, 'icon' => 'clock', 'href' => '/developer/listings?status=pending'],
                ['label' => 'Published', 'value' => $portfolio['published'] ?? 0, 'icon' => 'home', 'href' => '/developer/listings?status=published'],
            ],
            default => [
                ['label' => 'Companies', 'value' => $summary['companies'], 'icon' => 'building'],
                ['label' => 'Properties', 'value' => $summary['properties'], 'icon' => 'home'],
                ['label' => 'Published', 'value' => $summary['published_listings'], 'icon' => 'tag'],
                ['label' => 'Open inquiries', 'value' => $summary['open_inquiries'], 'icon' => 'message'],
                ['label' => 'Viewings', 'value' => $summary['active_viewings'], 'icon' => 'calendar'],
                ['label' => 'Active leads', 'value' => $summary['active_leads'], 'icon' => 'pipeline'],
            ],
        };
    }

    /** @return array{icon:string,title:string,detail:string} */
    private function emptyState(string $section): array
    {
        return match ($section) {
            'company' => ['icon' => 'building', 'title' => 'No company records found', 'detail' => 'Create a company or adjust the current filters to manage your business portfolio.'],
            'projects' => ['icon' => 'layers', 'title' => 'No projects found', 'detail' => 'Development projects you create for your companies will appear here.'],
            'properties' => ['icon' => 'home', 'title' => 'No properties found', 'detail' => 'Create a canonical property record before creating sale or rental listings.'],
            'listings' => ['icon' => 'tag', 'title' => 'No listings found', 'detail' => 'Create a sale or rental offer from an approved property to reach marketplace customers.'],
            'inquiries' => ['icon' => 'message', 'title' => 'No inquiries found', 'detail' => 'Customer inquiries matching the current filters will appear here.'],
            'viewings' => ['icon' => 'calendar', 'title' => 'No viewing requests found', 'detail' => 'Customer viewing requests will appear here for confirmation and scheduling.'],
            'leads' => ['icon' => 'pipeline', 'title' => 'No leads found', 'detail' => 'Qualified inquiries converted to the company lead pipeline will appear here.'],
            'team' => ['icon' => 'users', 'title' => 'No team records found', 'detail' => 'Invite or activate company representatives to collaborate in this workspace.'],
            'media' => ['icon' => 'image', 'title' => 'No media found', 'detail' => 'Validated images and documents uploaded by this workspace will appear here.'],
            default => ['icon' => 'search', 'title' => 'No recent activity', 'detail' => 'Company marketplace activity will appear here as customers engage with your inventory.'],
        };
    }

    /** @return array{steps:array<int,string>,note:string}|array{} */
    private function sectionGuide(string $section): array
    {
        return match ($section) {
            'company' => ['steps' => ['Draft', 'Pending review', 'Approved'], 'note' => 'Rejected companies return to draft after you review the moderation note and make corrections.'],
            'projects' => ['steps' => ['Draft', 'Pending review', 'Approved', 'Published'], 'note' => 'Approved projects can be published after moderation without reopening content editing.'],
            'properties' => ['steps' => ['Draft', 'Pending review', 'Approved'], 'note' => 'Approved properties become eligible for marketplace listing offers.'],
            'listings' => ['steps' => ['Draft', 'Pending review', 'Published', 'Paused / Closed'], 'note' => 'Published listings can be paused, resumed, or closed while the underlying property remains canonical.'],
            default => [],
        };
    }

    /** @return array<int, array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['heading' => 'Workspace'],
            ['label' => 'Overview', 'path' => '/developer', 'icon' => 'grid'],
            ['heading' => 'Portfolio'],
            ['label' => 'Company', 'path' => '/developer/company', 'icon' => 'building'],
            ['label' => 'Projects', 'path' => '/developer/projects', 'icon' => 'layers'],
            ['label' => 'Properties', 'path' => '/developer/properties', 'icon' => 'home'],
            ['label' => 'Listings', 'path' => '/developer/listings', 'icon' => 'tag'],
            ['heading' => 'Customer Operations'],
            ['label' => 'Inquiries', 'path' => '/developer/inquiries', 'icon' => 'message'],
            ['label' => 'Viewings', 'path' => '/developer/viewings', 'icon' => 'calendar'],
            ['label' => 'Leads', 'path' => '/developer/leads', 'icon' => 'pipeline'],
            ['heading' => 'Workspace Management'],
            ['label' => 'Team', 'path' => '/developer/team', 'icon' => 'users'],
            ['label' => 'Media', 'path' => '/developer/media', 'icon' => 'image'],
            ['label' => 'Billing & Plan', 'path' => '/developer/billing', 'icon' => 'layers'],
            ['heading' => 'Settings'],
            ['label' => 'Basic', 'path' => '/developer/settings', 'icon' => 'settings'],
            ['label' => 'Profile', 'path' => '/developer/settings/profile', 'icon' => 'user'],
            ['label' => 'Privacy', 'path' => '/developer/settings/privacy', 'icon' => 'shield'],
            ['label' => 'Security', 'path' => '/developer/settings/security', 'icon' => 'lock'],
        ];
    }

    private function sectionForResource(string $resource): string
    {
        return match ($resource) {
            'company' => 'company',
            'project' => 'projects',
            'property' => 'properties',
            'listing' => 'listings',
            'inquiry' => 'inquiries',
            'viewing' => 'viewings',
            'lead' => 'leads',
            default => 'overview',
        };
    }

    /** @return array<string, mixed> */
    private function filterOptions(string $section): array
    {
        return match ($section) {
            'overview', 'inquiries' => [
                'statuses' => DomainRules::INQUIRY_STATUSES,
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest received',
                    'updated_asc' => 'Oldest received',
                ],
            ],
            'viewings' => [
                'statuses' => ['pending', 'confirmed', 'rescheduled', 'completed', 'declined', 'cancelled'],
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Customer A–Z',
                ],
            ],
            'company' => [
                'statuses' => DomainRules::COMPANY_STATUSES,
                'types' => DomainRules::COMPANY_TYPES,
                'location' => true,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                ],
            ],
            'projects' => [
                'statuses' => DomainRules::PROJECT_STATUSES,
                'types' => DomainRules::PROJECT_TYPES,
                'location' => true,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                ],
            ],
            'properties' => [
                'statuses' => DomainRules::PROPERTY_STATUSES,
                'types' => DomainRules::PROPERTY_TYPES,
                'location' => true,
                'readiness' => ['ready' => 'Ready', 'attention' => 'Needs attention'],
                'quality' => ['missing_media' => 'Missing media', 'missing_primary_media' => 'Missing primary media', 'missing_location' => 'Missing location', 'missing_description' => 'Short / missing description', 'parent_blocked' => 'Parent blocked', 'stale_draft' => 'Stale draft · 30+ days', 'stale_30_59' => 'Stale draft · 30–59 days', 'stale_60_89' => 'Stale draft · 60–89 days', 'stale_90_plus' => 'Stale draft · 90+ days'],
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Title A–Z',
                    'name_desc' => 'Title Z–A',
                    'readiness_desc' => 'Highest readiness first',
                    'readiness_asc' => 'Most incomplete first',
                ],
            ],
            'listings' => [
                'statuses' => DomainRules::LISTING_STATUSES,
                'types' => DomainRules::LISTING_TRANSACTION_TYPES,
                'location' => true,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'price_asc' => 'Price low to high',
                    'price_desc' => 'Price high to low',
                    'name_asc' => 'Property A–Z',
                ],
            ],
            'leads' => [
                'statuses' => DomainRules::LEAD_STAGES,
                'types' => DomainRules::LISTING_TRANSACTION_TYPES,
                'location' => true,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'price_asc' => 'Budget low to high',
                    'price_desc' => 'Budget high to low',
                    'name_asc' => 'Contact A–Z',
                ],
            ],
            'team' => [
                'statuses' => ['active', 'inactive', 'pending'],
                'types' => ['owner', 'manager', 'agent', 'member'],
                'location' => false,
                'sorts' => [
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                    'updated_desc' => 'Recently joined',
                ],
            ],
            'media' => [
                'statuses' => [],
                'types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest uploaded',
                    'updated_asc' => 'Oldest uploaded',
                    'name_asc' => 'Filename A–Z',
                ],
            ],
            default => ['statuses' => [], 'types' => [], 'location' => false, 'sorts' => []],
        };
    }

    /** @return array<string, string> */
    private function columns(string $section): array
    {
        return match ($section) {
            'overview' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'property_title' => 'Property',
                'status' => 'Status',
                'created_at' => 'Received',
            ],
            'inquiries' => [
                'contact_name' => 'Contact',
                'property_title' => 'Property',
                'company_name' => 'Company',
                'transaction_type' => 'Offer',
                'assigned_user_name' => 'Assigned to',
                'status' => 'Status',
                'created_at' => 'Received',
            ],

            'viewings' => [
                // Viewings Table Focus v1.6.4
                'customer_name' => 'Customer',
                'viewing_context' => 'Context',
                'viewing_schedule' => 'Schedule',
                'viewing_state' => 'State',
                'updated_at' => 'Updated',
            ],
            'company' => [
                // Companies Table Focus v1.5.6
                'name' => 'Company',
                'company_type' => 'Type',
                'company_context' => 'Context',
                'company_portfolio' => 'Portfolio',
                'company_state' => 'State',
                'updated_at' => 'Updated',
            ],
            'projects' => [
                // Projects Table Focus v1.5.2
                'name' => 'Project',
                'project_type' => 'Type',
                'project_context' => 'Context',
                'project_inventory' => 'Inventory',
                'project_state' => 'State',
                'updated_at' => 'Updated',
            ],
            'properties' => [
                'reference_code' => 'Reference',
                'title' => 'Property',
                'company_name' => 'Company',
                'location_name' => 'Location',
                'property_type' => 'Type',
                'listings_count' => 'Listings',
                'readiness_score' => 'Readiness',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'listings' => [
                // Listings Table Focus v1.4.8
                'property_title' => 'Property',
                'transaction_type' => 'Offer',
                'price' => 'Price',
                'listing_context' => 'Context',
                'listing_state' => 'State',
                'updated_at' => 'Updated',
            ],

            'leads' => [
                // Leads Table Focus v1.6.1
                'contact_name' => 'Contact',
                'lead_context' => 'Context',
                'lead_interest' => 'Interest',
                'lead_assignment' => 'Assignment',
                'lead_stage' => 'Stage',
                'lead_activity' => 'Activity',
            ],
            'team' => [
                'name' => 'Member',
                'company_name' => 'Company',
                'email' => 'Email',
                'member_role' => 'Role',
                'status' => 'Status',
                'joined_at' => 'Joined / invited',
                'expires_at' => 'Invite expiry',
            ],
            'media' => [
                'original_name' => 'File',
                'target_title' => 'Attached to',
                'company_name' => 'Company',
                'entity_type' => 'Record type',
                'collection' => 'Collection',
                'mime_type' => 'Type',
                'size_bytes' => 'Size',
                'is_primary' => 'Primary',
                'created_at' => 'Uploaded',
            ],
            default => [],
        };
    }
}
