<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Repositories\WorkspaceReadRepository;

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

        $repository = new WorkspaceReadRepository();
        $summary = $repository->adminSummary();
        $records = $repository->adminSection(
            $section,
            $search,
            $page,
            $section === 'overview' ? 8 : 20
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
