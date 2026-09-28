<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\AnnouncementRepository;
use Barama\Repositories\EmailTemplateRepository;
use Barama\Repositories\FinanceRepository;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\SiteContentRepository;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;
use Barama\Repositories\WorkspaceSettingsRepository;
use Barama\Services\AnnouncementManagementService;
use Barama\Services\EmailTemplateManagementService;

final class DashboardController
{
    private const SECTIONS = [
        'overview' => [
            'title' => 'Admin Control Center',
            'description' => 'Operate marketplace moderation, customer activity, platform data, users, and security from one focused administration workspace.',
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
        'viewings' => [
            'title' => 'Viewing Appointments',
            'description' => 'Monitor viewing requests, company scheduling state, customer preferences, and appointment outcomes without replacing company scheduling ownership.',
        ],
        'catalog' => [
            'title' => 'Locations & Amenities',
            'description' => 'Maintain the canonical real-estate location hierarchy and amenity catalog with guarded deactivation for data already in use.',
        ],
        'media' => [
            'title' => 'Real Estate Media',
            'description' => 'Inspect listing media attachment context, primary-image usage, storage metadata, and safe orphan cleanup across the platform.',
        ],
        'pages' => [
            'title' => 'Page Manager',
            'description' => 'Create and maintain public content pages with controlled publishing and SEO metadata.',
        ],
        'seo' => [
            'title' => 'SEO Center',
            'description' => 'Audit indexable public records for description, social-image, and image-alt-text coverage without taking over company content ownership.',
        ],
        'users' => [
            'title' => 'Users',
            'description' => 'Manage account status and administrator access without bypassing the canonical role system.',
        ],
        'verification' => [
            'title' => 'Verification Center',
            'description' => 'Review email verification and membership-derived Developer access consistency without manually overriding identity verification.',
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

    public function homepage(Request $request): Response
    {
        return Response::html(View::render('workspace/site-content', [
            'pageTitle' => 'Homepage & Navigation · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'homepage',
            'siteContent' => (new SiteContentRepository())->get(),
        ]));
    }

    public function announcements(Request $request): Response
    {
        $repository = new AnnouncementRepository();
        $search = mb_substr(trim((string) $request->input('q', '')), 0, 80);
        $status = strtolower(trim((string) $request->input('status', '')));
        $audience = strtolower(trim((string) $request->input('audience', '')));
        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $page = $page === false ? 1 : (int) $page;

        if (!in_array($status, ['', 'draft', 'scheduled', 'published', 'cancelled', 'archived'], true)) {
            $status = '';
        }

        if ($audience !== '' && !array_key_exists($audience, AnnouncementManagementService::audienceOptions())) {
            $audience = '';
        }

        return Response::html(View::render('workspace/communications-announcements', [
            'pageTitle' => 'Announcements · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'announcements',
            'announcementPage' => $repository->paginate($search, $status, $audience, $page, 20),
            'announcementSummary' => $repository->summary(),
            'announcementSearch' => $search,
            'announcementFilters' => ['status' => $status, 'audience' => $audience],
            'announcementAudiences' => AnnouncementManagementService::audienceOptions(),
            'announcementPriorities' => AnnouncementManagementService::priorityOptions(),
        ]));
    }

    public function notificationCenter(Request $request): Response
    {
        $repository = new NotificationRepository();

        return Response::html(View::render('workspace/communications-notifications', [
            'pageTitle' => 'Notification Center · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'notification-center',
            'notifications' => $repository->adminNotifications(),
            'communicationsSummary' => $repository->communicationsSummary(),
        ]));
    }

    public function mailQueue(Request $request): Response
    {
        $repository = new NotificationRepository();

        return Response::html(View::render('workspace/communications-mail-queue', [
            'pageTitle' => 'Mail Queue & Logs · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'mail-queue',
            'outbox' => $repository->adminOutbox(),
            'communicationsSummary' => $repository->communicationsSummary(),
        ]));
    }

    public function emailTemplates(Request $request): Response
    {
        $repository = new EmailTemplateRepository();

        return Response::html(View::render('workspace/communications-email-templates', [
            'pageTitle' => 'Email Templates · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'email-templates',
            'emailTemplates' => $repository->all(),
            'emailTemplateSummary' => $repository->summary(),
            'supportedTemplateKeys' => EmailTemplateManagementService::supportedKeys(),
        ]));
    }

    public function plans(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-plans', [
            'pageTitle' => 'Plans & Packages · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'plans',
            'plans' => $finance->plans(),
            'financeSummary' => $finance->summary(),
        ]));
    }

    public function subscriptions(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-subscriptions', [
            'pageTitle' => 'Subscriptions · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'subscriptions',
            'subscriptions' => $finance->subscriptions(),
            'plans' => $finance->activePlans(),
            'companies' => $finance->companies(),
            'financeSummary' => $finance->summary(),
        ]));
    }

    public function coupons(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-coupons', [
            'pageTitle' => 'Coupons · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'coupons',
            'coupons' => $finance->coupons(),
            'couponSummary' => $finance->couponSummary(),
        ]));
    }

    public function taxes(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-taxes', [
            'pageTitle' => 'Tax Rules · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'taxes',
            'taxRules' => $finance->taxRules(),
            'taxSummary' => $finance->taxSummary(),
        ]));
    }

    public function paymentProviders(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-payment-providers', [
            'pageTitle' => 'Payment Providers · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'payment-providers',
            'paymentProviders' => $finance->paymentProviders(),
            'webhookEvents' => $finance->webhookEvents(),
            'paymentProviderSummary' => $finance->paymentProviderSummary(),
        ]));
    }

    public function billingOperations(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-billing-operations', [
            'pageTitle' => 'Billing Operations · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'billing-operations',
            'billingOperationsSummary' => $finance->billingOperationsSummary(),
            'reconciliationInvoices' => $finance->reconciliationInvoices(),
            'webhookOperationalEvents' => $finance->webhookOperationalEvents(),
        ]));
    }

    public function invoices(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-invoices', [
            'pageTitle' => 'Invoices · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'invoices',
            'invoices' => $finance->invoices(),
            'companies' => $finance->companies(),
            'subscriptions' => $finance->subscriptions(),
            'ledgerSummary' => $finance->ledgerSummary(),
        ]));
    }

    public function transactions(Request $request): Response
    {
        $finance = new FinanceRepository();

        return Response::html(View::render('workspace/finance-transactions', [
            'pageTitle' => 'Transactions · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'transactions',
            'transactions' => $finance->transactions(),
            'companies' => $finance->companies(),
            'subscriptions' => $finance->subscriptions(),
            'invoices' => $finance->invoices(),
            'ledgerSummary' => $finance->ledgerSummary(),
        ]));
    }

    public function record(Request $request, string $resource, string $id): Response
    {
        $resource = strtolower(trim($resource));

        if (!in_array($resource, ['company', 'project', 'property', 'listing', 'inquiry', 'lead', 'viewing', 'media', 'page', 'user', 'audit'], true)
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
            ?? $record['original_name']
            ?? $record['property_title']
            ?? $record['contact_name']
            ?? $record['event_type']
            ?? ucfirst($resource)
        );

        return Response::html(View::render('workspace/record', [
            'pageTitle' => $title . ' · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
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
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => $section,
            'sectionTitle' => self::SECTIONS[$section]['title'],
            'sectionDescription' => self::SECTIONS[$section]['description'],
            'stats' => $this->statsForSection($section, $summary, $marketplaceSummary),
            'emptyState' => $this->emptyState($section),
            'sectionGuide' => $this->sectionGuide($section),
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
                ['label' => 'Ready for review', 'value' => $marketplace['ready'] ?? 0, 'icon' => 'check'],
                ['label' => 'Blocked by parent', 'value' => $marketplace['blocked'] ?? 0, 'icon' => 'alert'],
            ],
            'companies' => [
                ['label' => 'All companies', 'value' => $marketplace['total'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/companies'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/companies?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/companies?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/companies?status=approved'],
                ['label' => 'Rejected', 'value' => $marketplace['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/companies?status=rejected'],
                ['label' => 'Suspended', 'value' => $marketplace['suspended'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/companies?status=suspended'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/companies?status=archived'],
            ],
            'projects' => [
                ['label' => 'All projects', 'value' => $marketplace['total'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/projects'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/projects?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/projects?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/projects?status=approved'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/projects?status=published'],
                ['label' => 'Rejected', 'value' => $marketplace['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/projects?status=rejected'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/projects?status=archived'],
                ['label' => 'Completed', 'value' => $marketplace['completed'] ?? 0, 'icon' => 'home'],
            ],
            'properties' => [
                ['label' => 'All properties', 'value' => $marketplace['total'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/properties'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/properties?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/properties?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/properties?status=approved'],
                ['label' => 'Rejected', 'value' => $marketplace['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/properties?status=rejected'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/properties?status=archived'],
            ],
            'listings' => [
                ['label' => 'All listings', 'value' => $marketplace['total'] ?? 0, 'icon' => 'tag', 'href' => '/dashboard/listings'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/listings?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/listings?status=pending'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/listings?status=published'],
                ['label' => 'Paused', 'value' => $marketplace['paused'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/listings?status=paused'],
                ['label' => 'Rejected', 'value' => $marketplace['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/listings?status=rejected'],
                ['label' => 'Closed', 'value' => $marketplace['closed'] ?? 0, 'icon' => 'lock', 'href' => '/dashboard/listings?status=closed'],
                ['label' => 'Expired', 'value' => $marketplace['expired'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/listings?status=expired'],
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
                ['label' => 'Missing alt text', 'value' => $marketplace['missing_alt'] ?? 0, 'icon' => 'alert'],
                ['label' => 'PDF documents', 'value' => $marketplace['pdfs'] ?? 0, 'icon' => 'layers'],
                ['label' => 'Orphaned assets', 'value' => $marketplace['orphaned'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/media?status=orphan'],
            ],
            'pages' => [
                ['label' => 'All pages', 'value' => $marketplace['total'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/pages'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/pages?status=draft'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'globe', 'href' => '/dashboard/pages?status=published'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/pages?status=archived'],
                ['label' => 'Indexable', 'value' => $marketplace['indexable'] ?? 0, 'icon' => 'search'],
            ],
            'seo' => [
                ['label' => 'Public records', 'value' => $marketplace['total'] ?? 0, 'icon' => 'globe', 'href' => '/dashboard/seo'],
                ['label' => 'Attention required', 'value' => $marketplace['attention'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/seo?status=attention'],
                ['label' => 'Indexable', 'value' => $marketplace['indexable'] ?? 0, 'icon' => 'search'],
                ['label' => 'Missing description', 'value' => $marketplace['missing_description'] ?? 0, 'icon' => 'edit'],
                ['label' => 'Missing social image', 'value' => $marketplace['missing_social_image'] ?? 0, 'icon' => 'image'],
                ['label' => 'Missing alt text', 'value' => $marketplace['missing_alt_text'] ?? 0, 'icon' => 'alert'],
            ],
            'inquiries' => [
                ['label' => 'All inquiries', 'value' => $marketplace['total'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/inquiries'],
                ['label' => 'New', 'value' => $marketplace['new'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/inquiries?status=new'],
                ['label' => 'Awaiting response', 'value' => $marketplace['read'] ?? 0, 'icon' => 'eye', 'href' => '/dashboard/inquiries?status=read'],
                ['label' => 'Replied', 'value' => $marketplace['replied'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/inquiries?status=replied'],
                ['label' => 'Converted to lead', 'value' => $marketplace['converted'] ?? 0, 'icon' => 'pipeline'],
                ['label' => 'Unassigned', 'value' => $marketplace['unassigned'] ?? 0, 'icon' => 'users'],
                ['label' => 'Waiting 24h+', 'value' => $marketplace['awaiting_24h'] ?? 0, 'icon' => 'alert'],
                ['label' => 'Closed / spam', 'value' => ($marketplace['closed'] ?? 0) + ($marketplace['spam'] ?? 0), 'icon' => 'shield'],
            ],
            'leads' => [
                ['label' => 'All property leads', 'value' => $marketplace['total'] ?? 0, 'icon' => 'pipeline', 'href' => '/dashboard/leads'],
                ['label' => 'Active pipeline', 'value' => $marketplace['active'] ?? 0, 'icon' => 'clock'],
                ['label' => 'Qualified', 'value' => $marketplace['qualified'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/leads?status=qualified'],
                ['label' => 'Negotiation', 'value' => $marketplace['negotiation'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/leads?status=negotiation'],
                ['label' => 'Won', 'value' => $marketplace['won'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/leads?status=won'],
                ['label' => 'Lost', 'value' => $marketplace['lost'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/leads?status=lost'],
                ['label' => 'Unassigned', 'value' => $marketplace['unassigned'] ?? 0, 'icon' => 'users'],
                ['label' => 'Follow-up due', 'value' => $marketplace['follow_up_due'] ?? 0, 'icon' => 'clock'],
            ],
            'viewings' => [
                ['label' => 'All viewings', 'value' => $marketplace['total'] ?? 0, 'icon' => 'calendar', 'href' => '/dashboard/viewings'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/viewings?status=pending'],
                ['label' => 'Confirmed', 'value' => $marketplace['confirmed'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/viewings?status=confirmed'],
                ['label' => 'Rescheduled', 'value' => $marketplace['rescheduled'] ?? 0, 'icon' => 'calendar', 'href' => '/dashboard/viewings?status=rescheduled'],
                ['label' => 'Upcoming', 'value' => $marketplace['upcoming'] ?? 0, 'icon' => 'calendar'],
                ['label' => 'Completed', 'value' => $marketplace['completed'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/viewings?status=completed'],
                ['label' => 'Declined', 'value' => $marketplace['declined'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/viewings?status=declined'],
                ['label' => 'Cancelled', 'value' => $marketplace['cancelled'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/viewings?status=cancelled'],
            ],
            'users' => [
                ['label' => 'All users', 'value' => $marketplace['total'] ?? 0, 'icon' => 'users', 'href' => '/dashboard/users'],
                ['label' => 'Active', 'value' => $marketplace['active'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/users?status=active'],
                ['label' => 'Suspended', 'value' => $marketplace['suspended'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/users?status=suspended'],
                ['label' => 'Verified email', 'value' => $marketplace['verified'] ?? 0, 'icon' => 'mail'],
                ['label' => 'Unverified active', 'value' => $marketplace['unverified'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/verification?type=email_unverified'],
                ['label' => 'Developers', 'value' => $marketplace['developers'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/users?type=developer'],
                ['label' => 'Administrators', 'value' => $marketplace['administrators'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/users?type=administrator'],
                ['label' => 'Company access', 'value' => $marketplace['company_members'] ?? 0, 'icon' => 'building'],
            ],
            'verification' => [
                ['label' => 'All accounts', 'value' => $marketplace['total'] ?? 0, 'icon' => 'users', 'href' => '/dashboard/verification'],
                ['label' => 'Attention required', 'value' => $marketplace['attention'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/verification?status=attention'],
                ['label' => 'Unverified email', 'value' => $marketplace['unverified'] ?? 0, 'icon' => 'mail', 'href' => '/dashboard/verification?type=email_unverified'],
                ['label' => 'Missing developer access', 'value' => $marketplace['missing_developer'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/verification?type=developer_access_missing'],
                ['label' => 'Orphan developer access', 'value' => $marketplace['orphan_developer'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/verification?type=developer_access_orphan'],
            ],
            'audit' => [
                ['label' => 'All events', 'value' => $marketplace['total'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/audit'],
                ['label' => 'Authentication', 'value' => $marketplace['auth_events'] ?? 0, 'icon' => 'lock', 'href' => '/dashboard/audit?type=auth'],
                ['label' => 'Account security', 'value' => $marketplace['account_events'] ?? 0, 'icon' => 'user', 'href' => '/dashboard/audit?type=account'],
                ['label' => 'Administrator', 'value' => $marketplace['admin_events'] ?? 0, 'icon' => 'users', 'href' => '/dashboard/audit?type=admin'],
                ['label' => 'Last 24 hours', 'value' => $marketplace['recent_24h'] ?? 0, 'icon' => 'clock'],
            ],
            default => [
                ['label' => 'Pending approval', 'value' => $summary['pending_approvals'], 'icon' => 'shield', 'href' => '/dashboard/approvals'],
                ['label' => 'Companies', 'value' => $summary['companies'], 'icon' => 'building', 'href' => '/dashboard/companies'],
                ['label' => 'Properties', 'value' => $summary['properties'], 'icon' => 'home', 'href' => '/dashboard/properties'],
                ['label' => 'Published listings', 'value' => $summary['published_listings'], 'icon' => 'tag', 'href' => '/dashboard/listings?status=published'],
                ['label' => 'Open inquiries', 'value' => $summary['open_inquiries'], 'icon' => 'message', 'href' => '/dashboard/inquiries'],
                ['label' => 'Active leads', 'value' => $summary['active_leads'], 'icon' => 'pipeline', 'href' => '/dashboard/leads'],
                ['label' => 'Users', 'value' => $summary['users'], 'icon' => 'users', 'href' => '/dashboard/users'],
                ['label' => 'Security events', 'value' => $summary['security_events'], 'icon' => 'shield', 'href' => '/dashboard/audit'],
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
            'pages' => [
                'icon' => 'edit',
                'title' => 'No CMS pages match this view',
                'detail' => 'Create a page or adjust status, title, slug, or SEO search filters.',
            ],
            'seo' => [
                'icon' => 'search',
                'title' => 'No SEO governance records match this view',
                'detail' => 'Published public records will appear here with indexability, description, social-image, and alt-text coverage.',
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
            'viewings' => [
                'icon' => 'calendar',
                'title' => 'No viewing appointments match this view',
                'detail' => 'Adjust viewing status or search by customer, company, property, or listing reference.',
            ],
            'users' => [
                'icon' => 'users',
                'title' => 'No user accounts match this view',
                'detail' => 'Adjust account status, role or search by user, email, or company access.',
            ],
            'verification' => [
                'icon' => 'check',
                'title' => 'No verification records match this view',
                'detail' => 'Identity and Developer access consistency records will appear here when they match the selected verification filters.',
            ],
            'audit' => [
                'icon' => 'shield',
                'title' => 'No security events match this view',
                'detail' => 'Adjust event family or search by event, actor, subject, or safe metadata.',
            ],
            default => [
                'icon' => 'search',
                'title' => 'No records found',
                'detail' => 'Records matching this section and search will appear here.',
            ],
        };
    }

    /** @return array<int, array<string,string>> */
    private function navigation(): array
    {
        return [
            ['heading' => 'Control Center'],
            ['label' => 'Overview', 'path' => '/dashboard', 'icon' => 'grid'],
            ['label' => 'Approval Queue', 'path' => '/dashboard/approvals', 'icon' => 'shield'],

            ['heading' => 'Marketplace'],
            ['label' => 'Companies / Developers', 'path' => '/dashboard/companies', 'icon' => 'building'],
            ['label' => 'Projects', 'path' => '/dashboard/projects', 'icon' => 'layers'],
            ['label' => 'Properties', 'path' => '/dashboard/properties', 'icon' => 'home'],
            ['label' => 'Listings', 'path' => '/dashboard/listings', 'icon' => 'tag'],

            ['heading' => 'CRM'],
            ['label' => 'Inquiries', 'path' => '/dashboard/inquiries', 'icon' => 'message'],
            ['label' => 'Property Leads', 'path' => '/dashboard/leads', 'icon' => 'pipeline'],
            ['label' => 'Viewing Appointments', 'path' => '/dashboard/viewings', 'icon' => 'calendar'],

            ['heading' => 'Platform Data'],
            ['label' => 'Locations & Amenities', 'path' => '/dashboard/catalog', 'icon' => 'map'],

            ['heading' => 'Content & Discovery'],
            ['label' => 'Homepage & Navigation', 'path' => '/dashboard/homepage', 'icon' => 'home'],
            ['label' => 'Page Manager', 'path' => '/dashboard/pages', 'icon' => 'edit'],
            ['label' => 'Media Library', 'path' => '/dashboard/media', 'icon' => 'image'],
            ['label' => 'SEO Center', 'path' => '/dashboard/seo', 'icon' => 'search'],

            ['heading' => 'Finance'],
            ['label' => 'Plans & Packages', 'path' => '/dashboard/plans', 'icon' => 'layers'],
            ['label' => 'Subscriptions', 'path' => '/dashboard/subscriptions', 'icon' => 'building'],
            ['label' => 'Coupons', 'path' => '/dashboard/coupons', 'icon' => 'edit'],
            ['label' => 'Tax Rules', 'path' => '/dashboard/taxes', 'icon' => 'shield'],
            ['label' => 'Payment Providers', 'path' => '/dashboard/payment-providers', 'icon' => 'settings'],
            ['label' => 'Billing Operations', 'path' => '/dashboard/billing-operations', 'icon' => 'pipeline'],
            ['label' => 'Invoices', 'path' => '/dashboard/invoices', 'icon' => 'edit'],
            ['label' => 'Transactions', 'path' => '/dashboard/transactions', 'icon' => 'pipeline'],

            ['heading' => 'Communications'],
            ['label' => 'Notification Center', 'path' => '/dashboard/notifications', 'icon' => 'message'],
            ['label' => 'Announcements', 'path' => '/dashboard/announcements', 'icon' => 'bell'],
            ['label' => 'Email Templates', 'path' => '/dashboard/email-templates', 'icon' => 'mail'],
            ['label' => 'Mail Queue & Logs', 'path' => '/dashboard/mail-queue', 'icon' => 'pipeline'],

            ['heading' => 'Users & Security'],
            ['label' => 'Users', 'path' => '/dashboard/users', 'icon' => 'users'],
            ['label' => 'Verification Center', 'path' => '/dashboard/verification', 'icon' => 'check'],
            ['label' => 'Security Audit', 'path' => '/dashboard/audit', 'icon' => 'shield'],

            ['heading' => 'Workspace'],
            ['label' => 'Settings', 'path' => '/dashboard/settings', 'icon' => 'settings'],
        ];
    }

    /** @return array<string,mixed> */
    private function sectionGuide(string $section): array
    {
        if ($section !== 'overview') {
            return [];
        }

        return [
            'aria_label' => 'Admin control center workflow',
            'title' => 'Control center workflow',
            'subtitle' => 'A focused operating sequence for the live marketplace',
            'steps' => [
                'Review pending marketplace submissions and moderation history',
                'Monitor customer inquiries and active property lead pipelines',
                'Maintain canonical catalog, media, user access, and security evidence',
                'Use workspace settings only after operational changes are reviewed',
            ],
            'note' => 'Phase 1 shows only functioning administration modules. New controls will appear only when their routes, permissions, data model, and QA are implemented.',
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
            'viewing' => 'viewings',
            'media' => 'media',
            'page' => 'pages',
            'user' => 'users',
            'audit' => 'audit',
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
            'viewings' => [
                'statuses' => DomainRules::VIEWING_STATUSES,
                'types' => [],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Customer A–Z',
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
            'pages' => [
                'statuses' => ['draft', 'published', 'archived'],
                'types' => ['index,follow', 'noindex,follow', 'noindex,nofollow'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Title A–Z',
                    'name_desc' => 'Title Z–A',
                ],
            ],
            'seo' => [
                'statuses' => ['attention', 'ready'],
                'types' => ['listing', 'project', 'company', 'page'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Title A–Z',
                    'name_desc' => 'Title Z–A',
                ],
            ],
            'users' => [
                'statuses' => ['active', 'suspended', 'deactivated'],
                'types' => ['administrator', 'developer', 'user'],
                'location' => false,
                'sorts' => [
                    'updated_desc' => 'Recently updated',
                    'updated_asc' => 'Oldest updated',
                    'name_asc' => 'Name A–Z',
                    'name_desc' => 'Name Z–A',
                    'company_desc' => 'Most company access',
                ],
            ],
            'verification' => [
                'statuses' => ['attention', 'clear'],
                'types' => ['email_unverified', 'developer_access_missing', 'developer_access_orphan', 'clear'],
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
                'types' => ['auth', 'account', 'admin'],
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
                'readiness' => 'Readiness',
                'status' => 'Status',
                'updated_at' => 'Submitted',
            ],
            'companies' => [
                'name' => 'Company',
                'company_type' => 'Type',
                'location_name' => 'Location',
                'members_count' => 'Members',
                'projects_count' => 'Projects',
                'published_projects_count' => 'Published projects',
                'properties_count' => 'Properties',
                'approved_properties_count' => 'Approved properties',
                'published_listings_count' => 'Live listings',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'projects' => [
                'name' => 'Project',
                'company_name' => 'Company',
                'company_status' => 'Company status',
                'project_type' => 'Type',
                'location_name' => 'Location',
                'properties_count' => 'Properties',
                'approved_properties_count' => 'Approved properties',
                'marketplace_readiness' => 'Readiness',
                'completion_status' => 'Completion',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'properties' => [
                'reference_code' => 'Reference',
                'title' => 'Property',
                'company_name' => 'Company',
                'project_name' => 'Project',
                'location_name' => 'Location',
                'property_type' => 'Type',
                'listings_count' => 'Listings',
                'published_listings_count' => 'Published',
                'marketplace_readiness' => 'Readiness',
                'status' => 'Status',
                'updated_at' => 'Updated',
            ],
            'listings' => [
                'property_title' => 'Property',
                'company_name' => 'Company',
                'project_name' => 'Project',
                'transaction_type' => 'Offer',
                'price' => 'Price',
                'location_name' => 'Location',
                'marketplace_readiness' => 'Readiness',
                'status' => 'Status',
                'published_at' => 'Published',
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
                'next_follow_up_at' => 'Next follow-up',
                'updated_at' => 'Updated',
            ],
            'viewings' => [
                'customer_name' => 'Customer',
                'company_name' => 'Company',
                'reference_code' => 'Property',
                'transaction_type' => 'Offer',
                'preferred_date' => 'Preferred date',
                'scheduled_for' => 'Scheduled for',
                'status' => 'Status',
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
                'alt_text' => 'Alt text',
                'mime_type' => 'Type',
                'size_bytes' => 'Size',
                'created_at' => 'Uploaded',
            ],
            'pages' => [
                'title' => 'Page',
                'slug' => 'URL slug',
                'robots_directive' => 'Robots',
                'status' => 'Status',
                'published_at' => 'Published',
                'updated_by_name' => 'Updated by',
                'updated_at' => 'Updated',
            ],
            'seo' => [
                'entity_type' => 'Type',
                'title' => 'Public record',
                'path' => 'Public path',
                'index_state' => 'Index',
                'description_state' => 'Description',
                'image_state' => 'Social image',
                'alt_state' => 'Image alt text',
                'issue_state' => 'Governance',
                'updated_at' => 'Updated',
            ],
            'users' => [
                'name' => 'User',
                'email' => 'Email',
                'account_role' => 'Access',
                'company_count' => 'Companies',
                'owned_company_count' => 'Owner of',
                'email_state' => 'Email',
                'status' => 'Status',
                'last_login_at' => 'Last login',
            ],
            'verification' => [
                'name' => 'User',
                'email' => 'Email',
                'roles' => 'Roles',
                'company_count' => 'Companies',
                'developer_role' => 'Developer role',
                'verification_state' => 'State',
                'issue_type' => 'Issue',
                'updated_at' => 'Updated',
            ],
            'audit' => [
                'event_type' => 'Event',
                'event_family' => 'Family',
                'actor' => 'Actor',
                'subject' => 'Subject',
                'client_state' => 'Client data',
                'created_at' => 'Occurred',
            ],
            default => [],
        };
    }
}
