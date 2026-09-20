<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;

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
            'pageTitle' => $title . ' · Developer',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Developer Workspace',
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
        $formOptions = $repository->developerOptions((int) $user['id']);
        $summary = $repository->developerSummary((int) $user['id']);
        $records = $repository->developerSection(
            (int) $user['id'],
            $section,
            $search,
            $page,
            $section === 'overview' ? 6 : 20,
            $filters,
            $sort
        );

        return Response::html(View::render('workspace/index', [
            'pageTitle' => self::SECTIONS[$section]['title'] . ' · Developer',
            'activeArea' => 'developer',
            'workspaceType' => 'developer',
            'workspaceLabel' => 'Developer Workspace',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'stats' => [
                ['label' => 'Companies', 'value' => $summary['companies'], 'icon' => 'building'],
                ['label' => 'Properties', 'value' => $summary['properties'], 'icon' => 'home'],
                ['label' => 'Published', 'value' => $summary['published_listings'], 'icon' => 'tag'],
                ['label' => 'Open inquiries', 'value' => $summary['open_inquiries'], 'icon' => 'message'],
                ['label' => 'Viewings', 'value' => $summary['active_viewings'], 'icon' => 'calendar'],
                ['label' => 'Active leads', 'value' => $summary['active_leads'], 'icon' => 'pipeline'],
            ],
            'records' => $records,
            'columns' => $this->columns($section),
            'search' => $search,
            'tableFilters' => $filters,
            'tableSort' => $sort,
            'filterOptions' => $this->filterOptions($section),
            'formOptions' => $formOptions,
        ]));
    }

    /** @return array<int, array{label:string,path:string,icon:string}> */
    private function navigation(): array
    {
        return [
            ['label' => 'Overview', 'path' => '/developer', 'icon' => 'grid'],
            ['label' => 'Company', 'path' => '/developer/company', 'icon' => 'building'],
            ['label' => 'Projects', 'path' => '/developer/projects', 'icon' => 'layers'],
            ['label' => 'Properties', 'path' => '/developer/properties', 'icon' => 'home'],
            ['label' => 'Listings', 'path' => '/developer/listings', 'icon' => 'tag'],
            ['label' => 'Inquiries', 'path' => '/developer/inquiries', 'icon' => 'message'],
            ['label' => 'Viewings', 'path' => '/developer/viewings', 'icon' => 'calendar'],
            ['label' => 'Leads', 'path' => '/developer/leads', 'icon' => 'pipeline'],
            ['label' => 'Team', 'path' => '/developer/team', 'icon' => 'users'],
            ['label' => 'Media', 'path' => '/developer/media', 'icon' => 'image'],
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
                'statuses' => ['active', 'inactive'],
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
            'overview', 'inquiries' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'property_title' => 'Property',
                'status' => 'Status',
                'created_at' => 'Received',
            ],
            'viewings' => [
                'customer_name' => 'Customer',
                'property_title' => 'Property',
                'company_name' => 'Company',
                'preferred_date' => 'Preferred date',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'company' => [
                'name' => 'Company',
                'company_type' => 'Type',
                'member_role' => 'My role',
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
                'property_type' => 'Type',
                'company_name' => 'Company',
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
            'leads' => [
                'contact_name' => 'Contact',
                'company_name' => 'Company',
                'transaction_type' => 'Interest',
                'budget_max' => 'Budget',
                'stage' => 'Stage',
                'updated_at' => 'Updated',
            ],
            'team' => [
                'name' => 'Member',
                'company_name' => 'Company',
                'email' => 'Email',
                'member_role' => 'Role',
                'status' => 'Status',
                'joined_at' => 'Joined',
            ],
            'media' => [
                'original_name' => 'File',
                'mime_type' => 'Type',
                'size_bytes' => 'Size',
                'width_px' => 'Dimensions',
                'created_at' => 'Uploaded',
            ],
            default => [],
        };
    }
}
