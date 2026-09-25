<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;
use Barama\Repositories\WorkspaceSettingsRepository;

final class DashboardController
{
    private const SECTIONS = [
        'overview' => [
            'title' => 'Admin Overview',
            'description' => 'Monitor marketplace moderation, inventory, inquiries, and lead activity from one operational view.',
        ],
        'approvals' => [
            'title' => 'Approval Queue',
            'description' => 'Review companies, projects, properties, and listings submitted for marketplace approval.',
        ],
        'companies' => [
            'title' => 'Companies',
            'description' => 'Review developer and agency records, verification status, and publication eligibility.',
        ],
        'projects' => [
            'title' => 'Projects',
            'description' => 'Moderate development projects and control publication state.',
        ],
        'properties' => [
            'title' => 'Properties',
            'description' => 'Review property inventory and approval state independently from listing offers.',
        ],
        'listings' => [
            'title' => 'Listings',
            'description' => 'Moderate sale and rental offers and manage marketplace publication state.',
        ],
        'inquiries' => [
            'title' => 'Inquiries',
            'description' => 'Inspect marketplace inquiry volume and operational status across companies.',
        ],
        'leads' => [
            'title' => 'Leads',
            'description' => 'Inspect lead pipeline activity without taking ownership away from company teams.',
        ],
        'catalog' => [
            'title' => 'Locations & Amenities',
            'description' => 'Maintain the canonical location hierarchy and amenity catalog used by marketplace inventory.',
        ],
        'media' => [
            'title' => 'Media',
            'description' => 'Inspect uploaded media metadata and storage usage across the platform.',
        ],
        'users' => [
            'title' => 'Users',
            'description' => 'Manage account status and administrator access without bypassing the canonical role system.',
        ],
        'audit' => [
            'title' => 'Security Audit',
            'description' => 'Review immutable authentication and account-security events with hashed client-origin metadata.',
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

    public function record(Request $request, string $resource, string $id): Response
    {
        $resource = strtolower(trim($resource));

        if (!in_array($resource, ['company', 'project', 'property', 'listing', 'inquiry', 'lead'], true)
            || !ctype_digit($id)
            || (int) $id < 1
        ) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Record not found']),
                404
            );
        }

        $data = (new WorkspaceRecordRepository())->adminRecord($resource, (int) $id);

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
            'pageTitle' => $title . ' · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Dashboard',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $this->sectionForResource($resource),
            'recordResource' => $resource,
            'record' => $record,
            'history' => $data['history'],
            'media' => $data['media'],
            'formOptions' => [
                'locations' => (new WorkspaceReadRepository())->adminLocations(),
            ],
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

        $settingsUser = auth_user();
        $settingsRepository = new WorkspaceSettingsRepository();
        $workspacePreferences = $settingsUser !== null ? $settingsRepository->get((int) $settingsUser['id'], 'dashboard') : [];
        $rememberFilters = !empty($workspacePreferences['remember_filters']);
        $savedFilters = is_array($workspacePreferences['last_filters'] ?? null) ? $workspacePreferences['last_filters'] : [];
        $hasFilterQuery = isset($_GET['q']) || isset($_GET['status']) || isset($_GET['type']) || isset($_GET['location']) || isset($_GET['sort']);

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
        ];
        $sortDefault = (string) ($workspacePreferences['default_sort'] ?? 'updated_desc');
        $sort = mb_substr(trim((string) $request->input('sort', $rememberFilters && !$hasFilterQuery ? ($savedFilters['sort'] ?? $sortDefault) : $sortDefault)), 0, 40);
        $pageSize = in_array((int) ($workspacePreferences['page_size'] ?? 20), [10, 20, 50], true) ? (int) ($workspacePreferences['page_size'] ?? 20) : 20;
        if ($rememberFilters && $hasFilterQuery && $settingsUser !== null) {
            $workspacePreferences['last_filters'] = ['q'=>$search, 'status'=>$filters['status'], 'type'=>$filters['type'], 'location'=>$filters['location'], 'sort'=>$sort];
            $settingsRepository->save((int) $settingsUser['id'], 'dashboard', $workspacePreferences);
        }

        $repository = new WorkspaceReadRepository();
        $summary = $repository->adminSummary();
        $marketplaceSummary = $repository->adminMarketplaceSummary($section);
        $records = $repository->adminSection(
            $section,
            $search,
            $page,
            $section === 'overview' ? min(8, $pageSize) : $pageSize,
            $filters,
            $sort
        );

        return Response::html(View::render('workspace/index', [
            'pageTitle' => self::SECTIONS[$section]['title'] . ' · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Dashboard',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'stats' => $this->statsForSection($section, $summary, $marketplaceSummary),
            'emptyState' => $this->emptyState($section),
            'records' => $records,
            'columns' => $this->columns($section),
            'search' => $search,
            'tableFilters' => $filters,
            'tableSort' => $sort,
            'workspacePreferences' => $workspacePreferences,
            'filterOptions' => $this->filterOptions($section),
            'formOptions' => [
                'locations' => $repository->adminLocations(),
            ],
        ]));
    }

    /**
     * @param array<string,int> $summary
     * @param array<string,int> $marketplace
     * @return array<int,array{label:string,value:int,icon:string,href?:string}>
     */
    private function statsForSection(string $section, array $summary, array $marketplace): array
    {
        return match ($section) {
            'approvals' => [
                ['label' => 'Pending companies', 'value' => $marketplace['companies'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/approvals?type=company'],
                ['label' => 'Pending projects', 'value' => $marketplace['projects'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/approvals?type=project'],
                ['label' => 'Pending properties', 'value' => $marketplace['properties'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/approvals?type=property'],
                ['label' => 'Pending listings', 'value' => $marketplace['listings'] ?? 0, 'icon' => 'tag', 'href' => '/dashboard/approvals?type=listing'],
            ],
            'companies' => [
                ['label' => 'All companies', 'value' => $marketplace['total'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/companies'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/companies?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/companies?status=approved'],
                ['label' => 'Suspended', 'value' => $marketplace['suspended'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/companies?status=suspended'],
            ],
            'projects' => [
                ['label' => 'All projects', 'value' => $marketplace['total'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/projects'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/projects?status=pending'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/projects?status=published'],
                ['label' => 'Completed', 'value' => $marketplace['completed'] ?? 0, 'icon' => 'home'],
            ],
            'properties' => [
                ['label' => 'All properties', 'value' => $marketplace['total'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/properties'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/properties?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/properties?status=approved'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/properties?status=archived'],
            ],
            'listings' => [
                ['label' => 'All listings', 'value' => $marketplace['total'] ?? 0, 'icon' => 'tag', 'href' => '/dashboard/listings'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/listings?status=pending'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/listings?status=published'],
                ['label' => 'Paused', 'value' => $marketplace['paused'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/listings?status=paused'],
            ],
            default => [
                ['label' => 'Pending approval', 'value' => $summary['pending_approvals'], 'icon' => 'shield'],
                ['label' => 'Companies', 'value' => $summary['companies'], 'icon' => 'building'],
                ['label' => 'Properties', 'value' => $summary['properties'], 'icon' => 'home'],
                ['label' => 'Published', 'value' => $summary['published_listings'], 'icon' => 'tag'],
                ['label' => 'Open inquiries', 'value' => $summary['open_inquiries'], 'icon' => 'message'],
                ['label' => 'Users', 'value' => $summary['users'], 'icon' => 'users'],
                ['label' => 'Security events', 'value' => $summary['security_events'], 'icon' => 'shield'],
            ],
        };
    }

    /** @return array{icon:string,title:string,detail:string} */
    private function emptyState(string $section): array
    {
        return match ($section) {
            'approvals' => [
                'icon' => 'check',
                'title' => 'Approval queue is clear',
                'detail' => 'New Company, Project, Property and Listing submissions will appear here for review.',
            ],
            'companies' => [
                'icon' => 'building',
                'title' => 'No companies match this view',
                'detail' => 'Try another status, company type, location or search term.',
            ],
            'projects' => [
                'icon' => 'layers',
                'title' => 'No projects match this view',
                'detail' => 'Adjust the project status, type, location or search filters.',
            ],
            'properties' => [
                'icon' => 'home',
                'title' => 'No properties match this view',
                'detail' => 'Adjust the property status, type, location or search filters.',
            ],
            'listings' => [
                'icon' => 'tag',
                'title' => 'No listings match this view',
                'detail' => 'Adjust offer type, publication status, location or search filters.',
            ],
            default => [
                'icon' => 'search',
                'title' => 'No records found',
                'detail' => 'Records matching this section and search will appear here.',
            ],
        };
    }

    /** @return array<int, array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['heading' => 'Operations'],
            ['label' => 'Overview', 'path' => '/dashboard', 'icon' => 'grid'],
            ['label' => 'Approvals', 'path' => '/dashboard/approvals', 'icon' => 'shield'],
            ['heading' => 'Marketplace'],
            ['label' => 'Companies', 'path' => '/dashboard/companies', 'icon' => 'building'],
            ['label' => 'Projects', 'path' => '/dashboard/projects', 'icon' => 'layers'],
            ['label' => 'Properties', 'path' => '/dashboard/properties', 'icon' => 'home'],
            ['label' => 'Listings', 'path' => '/dashboard/listings', 'icon' => 'tag'],
            ['heading' => 'Customer Operations'],
            ['label' => 'Inquiries', 'path' => '/dashboard/inquiries', 'icon' => 'message'],
            ['label' => 'Leads', 'path' => '/dashboard/leads', 'icon' => 'pipeline'],
            ['heading' => 'Platform Data'],
            ['label' => 'Catalog', 'path' => '/dashboard/catalog', 'icon' => 'map'],
            ['label' => 'Media', 'path' => '/dashboard/media', 'icon' => 'image'],
            ['heading' => 'Administration'],
            ['label' => 'Users', 'path' => '/dashboard/users', 'icon' => 'users'],
            ['label' => 'Security Audit', 'path' => '/dashboard/audit', 'icon' => 'shield'],
            ['heading' => 'Settings'],
            ['label' => 'Basic', 'path' => '/dashboard/settings', 'icon' => 'settings'],
            ['label' => 'Profile', 'path' => '/dashboard/settings/profile', 'icon' => 'user'],
            ['label' => 'Privacy', 'path' => '/dashboard/settings/privacy', 'icon' => 'shield'],
            ['label' => 'Security', 'path' => '/dashboard/settings/security', 'icon' => 'lock'],
        ];
    }

    private function sectionForResource(string $resource): string
    {
        return match ($resource) {
            'company' => 'companies',
            'project' => 'projects',
            'property' => 'properties',
            'listing' => 'listings',
            'inquiry' => 'inquiries',
            'lead' => 'leads',
            default => 'overview',
        };
    }

    /** @return array<string, mixed> */
    private function filterOptions(string $section): array
    {
        return match ($section) {
            'overview', 'approvals' => [
                'statuses' => [],
                'types' => ['company', 'project', 'property', 'listing'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest submitted',
                    'updated_asc' => 'Oldest submitted',
                    'name_asc' => 'Record A–Z',
                ],
            ],
            'companies' => [
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
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Title A–Z',
                    'name_desc' => 'Title Z–A',
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
            'inquiries' => [
                'statuses' => DomainRules::INQUIRY_STATUSES,
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest received',
                    'updated_asc' => 'Oldest received',
                    'name_asc' => 'Contact A–Z',
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
            'catalog' => [
                'statuses' => ['active', 'inactive'],
                'types' => ['location', 'amenity'],
                'location' => false,
                'sorts' => [
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                    'updated_desc' => 'Recently updated',
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
            'users' => [
                'statuses' => ['active', 'suspended', 'deactivated'],
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                ],
            ],
            'audit' => [
                'statuses' => [],
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest event',
                    'updated_asc' => 'Oldest event',
                    'name_asc' => 'Event A–Z',
                ],
            ],
            default => ['statuses' => [], 'types' => [], 'location' => false, 'sorts' => []],
        };
    }

    /** @return array<string, string> */
    private function columns(string $section): array
    {
        return match ($section) {
            'overview', 'approvals' => [
                'entity_type' => 'Type',
                'title' => 'Record',
                'context' => 'Context',
                'status' => 'Status',
                'updated_at' => 'Submitted',
            ],
            'companies' => [
                'name' => 'Company',
                'company_type' => 'Type',
                'location_name' => 'Location',
                'projects_count' => 'Projects',
                'properties_count' => 'Properties',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'projects' => [
                'name' => 'Project',
                'company_name' => 'Company',
                'project_type' => 'Type',
                'location_name' => 'Location',
                'properties_count' => 'Properties',
                'completion_status' => 'Completion',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'properties' => [
                'reference_code' => 'Reference',
                'title' => 'Property',
                'company_name' => 'Company',
                'location_name' => 'Location',
                'property_type' => 'Type',
                'listings_count' => 'Listings',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'listings' => [
                'property_title' => 'Property',
                'company_name' => 'Company',
                'transaction_type' => 'Offer',
                'price' => 'Price',
                'location_name' => 'Location',
                'status' => 'Status',
                'expires_at' => 'Expires',
                'updated_at' => 'Updated',
            ],
            'inquiries' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'source' => 'Source',
                'status' => 'Status',
                'created_at' => 'Received',
            ],
            'leads' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'transaction_type' => 'Interest',
                'budget_max' => 'Budget',
                'stage' => 'Stage',
                'updated_at' => 'Updated',
            ],
            'catalog' => [
                'entity_type' => 'Type',
                'title' => 'Name',
                'context' => 'Category',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'media' => [
                'original_name' => 'File',
                'uploaded_by' => 'Uploaded by',
                'mime_type' => 'Type',
                'size_bytes' => 'Size',
                'created_at' => 'Uploaded',
            ],
            'users' => [
                'name' => 'User',
                'email' => 'Email',
                'roles' => 'Roles',
                'email_state' => 'Email',
                'status' => 'Status',
                'last_login_at' => 'Last login',
            ],
            'audit' => [
                'event_type' => 'Event',
                'actor' => 'Actor',
                'subject' => 'Subject',
                'created_at' => 'Occurred',
            ],
            default => [],
        };
    }
}
