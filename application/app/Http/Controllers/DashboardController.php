<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;

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

        $search = mb_substr(trim((string) $request->input('q', '')), 0, 80);
        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $page = $page === false ? 1 : (int) $page;

        $filters = [
            'status' => mb_substr(trim((string) $request->input('status', '')), 0, 40),
            'type' => mb_substr(trim((string) $request->input('type', '')), 0, 80),
            'location' => trim((string) $request->input('location', '')),
        ];
        $sort = mb_substr(trim((string) $request->input('sort', 'updated_desc')), 0, 40);

        $repository = new WorkspaceReadRepository();
        $summary = $repository->adminSummary();
        $records = $repository->adminSection(
            $section,
            $search,
            $page,
            $section === 'overview' ? 8 : 20,
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
            'stats' => [
                ['label' => 'Pending approval', 'value' => $summary['pending_approvals'], 'icon' => 'shield'],
                ['label' => 'Companies', 'value' => $summary['companies'], 'icon' => 'building'],
                ['label' => 'Properties', 'value' => $summary['properties'], 'icon' => 'home'],
                ['label' => 'Published', 'value' => $summary['published_listings'], 'icon' => 'tag'],
                ['label' => 'Open inquiries', 'value' => $summary['open_inquiries'], 'icon' => 'message'],
            ],
            'records' => $records,
            'columns' => $this->columns($section),
            'search' => $search,
            'tableFilters' => $filters,
            'tableSort' => $sort,
            'filterOptions' => $this->filterOptions($section),
            'formOptions' => [
                'locations' => $repository->adminLocations(),
            ],
        ]));
    }

    /** @return array<int, array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['label' => 'Overview', 'path' => '/dashboard', 'icon' => 'grid'],
            ['label' => 'Approvals', 'path' => '/dashboard/approvals', 'icon' => 'shield'],
            ['label' => 'Companies', 'path' => '/dashboard/companies', 'icon' => 'building'],
            ['label' => 'Projects', 'path' => '/dashboard/projects', 'icon' => 'layers'],
            ['label' => 'Properties', 'path' => '/dashboard/properties', 'icon' => 'home'],
            ['label' => 'Listings', 'path' => '/dashboard/listings', 'icon' => 'tag'],
            ['label' => 'Inquiries', 'path' => '/dashboard/inquiries', 'icon' => 'message'],
            ['label' => 'Leads', 'path' => '/dashboard/leads', 'icon' => 'pipeline'],
            ['label' => 'Catalog', 'path' => '/dashboard/catalog', 'icon' => 'map'],
            ['label' => 'Media', 'path' => '/dashboard/media', 'icon' => 'image'],
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
                'email' => 'Email',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'projects' => [
                'name' => 'Project',
                'company_name' => 'Company',
                'project_type' => 'Type',
                'completion_status' => 'Completion',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'properties' => [
                'reference_code' => 'Reference',
                'title' => 'Property',
                'company_name' => 'Company',
                'property_type' => 'Type',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'listings' => [
                'property_title' => 'Property',
                'transaction_type' => 'Offer',
                'price' => 'Price',
                'status' => 'Status',
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
            default => [],
        };
    }
}
