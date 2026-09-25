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
            'description' => 'Oversee property inquiries across companies, listing context, response state, and lead conversion without replacing company ownership.',
        ],
        'leads' => [
            'title' => 'Property Leads',
            'description' => 'Monitor company-owned property lead pipelines, source inquiries, property interest, assignment, and outcomes without taking over follow-up.',
        ],
        'catalog' => [
            'title' => 'Locations & Amenities',
            'description' => 'Maintain the canonical real-estate location hierarchy and amenity catalog with guarded deactivation for data already in use.',
        ],
        'media' => [
            'title' => 'Real Estate Media',
            'description' => 'Inspect listing media attachment context, primary-image usage, storage metadata, and safe orphan cleanup across the platform.',
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
            'catalog' => [
                ['label' => 'Locations', 'value' => $marketplace['locations'] ?? 0, 'icon' => 'map', 'href' => '/dashboard/catalog?type=location'],
                ['label' => 'Active locations', 'value' => $marketplace['active_locations'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/catalog?type=location&status=active'],
                ['label' => 'Amenities', 'value' => $marketplace['amenities'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/catalog?type=amenity'],
                ['label' => 'Active amenities', 'value' => $marketplace['active_amenities'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/catalog?type=amenity&status=active'],
            ],
            'media' => [
                ['label' => 'Media assets', 'value' => $marketplace['total'] ?? 0, 'icon' => 'image', 'href' => '/dashboard/media'],
                ['label' => 'Images', 'value' => $marketplace['images'] ?? 0, 'icon' => 'image'],
                ['label' => 'PDF documents', 'value' => $marketplace['pdfs'] ?? 0, 'icon' => 'layers'],
                ['label' => 'Orphaned assets', 'value' => $marketplace['orphaned'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/media?status=orphan'],
            ],
            'inquiries' => [
                ['label' => 'All inquiries', 'value' => $marketplace['total'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/inquiries'],
                ['label' => 'New', 'value' => $marketplace['new'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/inquiries?status=new'],
                ['label' => 'Awaiting response', 'value' => $marketplace['read'] ?? 0, 'icon' => 'eye', 'href' => '/dashboard/inquiries?status=read'],
                ['label' => 'Replied', 'value' => $marketplace['replied'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/inquiries?status=replied'],
                ['label' => 'Converted to lead', 'value' => $marketplace['converted'] ?? 0, 'icon' => 'pipeline'],
                ['label' => 'Closed / spam', 'value' => ($marketplace['closed'] ?? 0) + ($marketplace['spam'] ?? 0), 'icon' => 'shield'],
            ],
            'leads' => [
                ['label' => 'All property leads', 'value' => $marketplace['total'] ?? 0, 'icon' => 'pipeline', 'href' => '/dashboard/leads'],
                ['label' => 'Active pipeline', 'value' => $marketplace['active'] ?? 0, 'icon' => 'clock'],
                ['label' => 'Qualified', 'value' => $marketplace['qualified'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/leads?status=qualified'],
                ['label' => 'Negotiation', 'value' => $marketplace['negotiation'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/leads?status=negotiation'],
                ['label' => 'Won', 'value' => $marketplace['won'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/leads?status=won'],
                ['label' => 'Lost', 'value' => $marketplace['lost'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/leads?status=lost'],
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
            'catalog' => [
                'icon' => 'map',
                'title' => 'No catalog records match this view',
                'detail' => 'Create a location or amenity, or adjust the catalog status, type or search filters.',
            ],
            'media' => [
                'icon' => 'image',
                'title' => 'No media assets match this view',
                'detail' => 'Real-estate listing images and documents will appear here with their attachment context.',
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
            'inquiries' => [
                'icon' => 'message',
                'title' => 'No property inquiries match this view',
                'detail' => 'Adjust inquiry status or search by customer, property reference, listing, or company.',
            ],
            'leads' => [
                'icon' => 'pipeline',
                'title' => 'No property leads match this view',
                'detail' => 'Adjust pipeline stage, property interest, preferred location, or customer search filters.',
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
                    'usage_desc' => 'Most used',
                    'updated_desc' => 'Recently updated',
                ],
            ],
            'media' => [
                'statuses' => ['attached', 'orphan'],
                'types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Newest uploaded',
                    'updated_asc' => 'Oldest uploaded',
                    'name_asc' => 'Filename A–Z',
                    'size_desc' => 'Largest files',
                    'size_asc' => 'Smallest files',
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
                'property_reference' => 'Property',
                'assigned_user_name' => 'Assigned to',
                'status' => 'Status',
                'converted_lead_stage' => 'Lead stage',
                'created_at' => 'Received',
            ],
            'leads' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'source_property_reference' => 'Property',
                'transaction_type' => 'Interest',
                'preferred_location_name' => 'Preferred location',
                'budget_max' => 'Budget',
                'assigned_user_name' => 'Assigned to',
                'stage' => 'Stage',
                'updated_at' => 'Updated',
            ],
            'catalog' => [
                'entity_type' => 'Type',
                'title' => 'Name',
                'parent_name' => 'Parent',
                'context' => 'Category / type',
                'usage_count' => 'Used by',
                'children_count' => 'Children',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'media' => [
                'original_name' => 'File',
                'target_title' => 'Attached to',
                'attachment_state' => 'State',
                'attachment_count' => 'Attachments',
                'primary_count' => 'Primary',
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