<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Core\View;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\AppearanceRepository;
use Barama\Repositories\ContactMessageRepository;
use Barama\Repositories\EmailTemplateRepository;
use Barama\Repositories\FinanceRepository;
use Barama\Repositories\PaymentMethodRepository;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\MailAnalyticsRepository;
use Barama\Repositories\PageRepository;
use Barama\Repositories\SecurityCenterRepository;
use Barama\Repositories\SiteContentRepository;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;
use Barama\Repositories\WorkspaceSettingsRepository;
use Barama\Services\EmailTemplateManagementService;
use Barama\Services\RolePermissionGovernanceService; // BARAMA_RBAC_V135
use Barama\Services\SeoCenterService;

use Barama\Services\SecurityAlertService;
use Barama\Services\SecurityAuditService;
use Barama\Services\AuthService;

use Barama\Services\SecurityIncidentService;

use Barama\Services\MediaLibraryUploadService;
use Barama\Services\MediaLibraryPreviewService;

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
            'title' => 'Media Library',
            'description' => 'Manage reusable images, documents, PDFs, video and audio alongside real-estate attachment context and safe orphan cleanup.',
        ],
        'pages' => [
            'title' => 'Page Manager',
            'description' => 'Create and maintain standard public CMS pages. About Us and Contact Us are managed separately as built-in structured pages.',
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
            'title' => 'Security Center',
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

    public function seoCenter(Request $request): Response
    {
        $search = mb_substr(
            trim((string) $request->input('q', '')),
            0,
            80
        );

        $page = filter_var(
            $request->input('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $page = $page === false ? 1 : (int) $page;

        $filters = [
            'status' => mb_substr(
                trim((string) $request->input('status', '')),
                0,
                40
            ),
            'type' => mb_substr(
                trim((string) $request->input('type', '')),
                0,
                40
            ),
        ];

        $sort = mb_substr(
            trim((string) $request->input('sort', 'updated_desc')),
            0,
            40
        );

        $seoCenter = (new SeoCenterService())->dashboard(
            $search,
            $page,
            $filters,
            $sort
        );

        return Response::html(View::render('workspace/seo-center', [
            'pageTitle' => 'SEO Center · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'seo',
            'seoCenter' => $seoCenter,
        ]));
    }

    public function seoExport(Request $request): Response
    {
        $search = mb_substr(
            trim((string) $request->input('q', '')),
            0,
            80
        );

        $filters = [
            'status' => mb_substr(
                trim((string) $request->input('status', '')),
                0,
                40
            ),
            'type' => mb_substr(
                trim((string) $request->input('type', '')),
                0,
                40
            ),
        ];

        $sort = mb_substr(
            trim((string) $request->input('sort', 'updated_desc')),
            0,
            40
        );

        $rows = (new SeoCenterService())->exportRows(
            $search,
            $filters,
            $sort
        );

        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            return Response::html('Unable to create SEO export.', 500);
        }

        $safe = static function (mixed $value): string {
            $text = str_replace(
                ["\r\n", "\r", "\n"],
                ' ',
                trim((string) $value)
            );

            if (
                $text !== ''
                && in_array($text[0], ['=', '+', '-', '@'], true)
            ) {
                $text = "'" . $text;
            }

            return $text;
        };

        fputcsv($stream, [
            'Type',
            'Public record',
            'Public path',
            'SEO score',
            'Coverage',
            'Index',
            'Description',
            'Social image',
            'Image alt text',
            'Issues',
            'Updated',
        ]);

        foreach ($rows as $row) {
            $issues = is_array($row['issues'] ?? null)
                ? array_map(
                    static fn (array $issue): string =>
                        (string) ($issue['label'] ?? ''),
                    $row['issues']
                )
                : [];

            fputcsv($stream, array_map($safe, [
                ucfirst((string) ($row['entity_type'] ?? '')),
                (string) ($row['title'] ?? ''),
                (string) ($row['public_path'] ?? ''),
                (string) ($row['seo_score'] ?? 0),
                (string) ($row['seo_label'] ?? ''),
                (string) ($row['index_state'] ?? ''),
                (string) ($row['description_state'] ?? ''),
                (string) ($row['image_state'] ?? ''),
                (string) ($row['alt_state'] ?? ''),
                implode('; ', $issues),
                (string) ($row['updated_at'] ?? ''),
            ]));
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if (!is_string($csv)) {
            return Response::html('Unable to build SEO export.', 500);
        }

        return Response::binary(
            "\xEF\xBB\xBF" . $csv,
            'text/csv; charset=UTF-8',
            [
                'Content-Disposition' =>
                    'attachment; filename="barama-seo-audit-'
                    . gmdate('Ymd-His')
                    . '.csv"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
    public function appearance(Request $request): Response
    {
        $appearance = (new AppearanceRepository())->get();

        return Response::html(View::render('workspace/appearance', [
            'pageTitle' => 'Appearance · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'appearance',
            'appearance' => $appearance,
            'appearanceVersion' => AppearanceRepository::version($appearance),
        ]));
    }

    public function homepage(Request $request): Response
    {
        $config = (new SiteContentRepository())->get();

        return Response::html(View::render('workspace/homepage', [
            'pageTitle' => 'Homepage · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'homepage',
            'homepage' => is_array($config['homepage'] ?? null)
                ? $config['homepage']
                : [],
        ]));
    }

    public function about(Request $request): Response
    {
        $config = (new SiteContentRepository())->get();

        return Response::html(View::render('workspace/builtin-page', [
            'pageTitle' => 'About · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'about',
            'builtInPageType' => 'about',
            'builtInPage' => is_array($config['about'] ?? null)
                ? $config['about']
                : SiteContentRepository::defaults()['about'],
            'contactMessages' => [],
            'contactMessageSummary' => [],
        ]));
    }

    public function contact(Request $request): Response
    {
        $config = (new SiteContentRepository())->get();
        $messages = new ContactMessageRepository();

        return Response::html(View::render('workspace/builtin-page', [
            'pageTitle' => 'Contact · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'contact',
            'builtInPageType' => 'contact',
            'builtInPage' => is_array($config['contact'] ?? null)
                ? $config['contact']
                : SiteContentRepository::defaults()['contact'],
            'contactMessages' => $messages->recent(30),
            'contactMessageSummary' => $messages->summary(),
        ]));
    }

    public function navigationManager(Request $request): Response
    {
        $config = (new SiteContentRepository())->get();

        return Response::html(View::render('workspace/navigation', [
            'pageTitle' => 'Navigation · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'navigation',
            'navigationItems' => is_array($config['navigation'] ?? null)
                ? array_values($config['navigation'])
                : [],
        ]));
    }

    public function mediaPreview(Request $request, string $uuid): Response
    {
        return (new MediaLibraryPreviewService())->response(
            $request,
            $uuid
        );
    }
    public function mediaUpload(Request $request): Response
    {
        $service = new MediaLibraryUploadService();

        return Response::html(View::render('workspace/media-upload', [
            'pageTitle' => 'Upload Media · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'media',
            'mediaUploadCapabilities' => $service->capabilities(),
        ]));
    }

    public function storeMediaUpload(Request $request): Response
    {
        $files = $request->files();

        try {
            $result = (new MediaLibraryUploadService())->storeMany(
                is_array($files['media_files'] ?? null)
                    ? $files['media_files']
                    : []
            );

            $count = (int) ($result['count'] ?? 0);
            $message = $count === 1
                ? '1 media file was added to the Media Library.'
                : $count . ' media files were added to the Media Library.';

            return Response::redirect(
                '/dashboard/media/upload?upload_status=' . rawurlencode($message)
            );
        } catch (\DomainException|\InvalidArgumentException|\RuntimeException $exception) {
            return Response::redirect(
                '/dashboard/media/upload?upload_error=' . rawurlencode($exception->getMessage())
            );
        }
    }
    public function createPageBuilder(Request $request): Response
    {
        return Response::html(View::render('workspace/page-builder', [
            'pageTitle' => 'Create New Page · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'pages',
            'pageBuilderMode' => 'create',
            'record' => [
                'id' => 0,
                'title' => '',
                'slug' => '',
                'excerpt' => '',
                'content_text' => '',
                'status' => 'draft',
                'seo_title' => '',
                'seo_description' => '',
                'robots_directive' => 'index,follow',
                'published_at' => null,
                'updated_at' => null,
            ],
        ]));
    }

    public function editPageBuilder(
        Request $request,
        string $id
    ): Response {
        if (!ctype_digit($id) || (int) $id < 1) {
            return Response::html(
                View::render(
                    'errors/404',
                    ['pageTitle' => 'Page not found']
                ),
                404
            );
        }

        $record = (new PageRepository())->find((int) $id);

        if ($record === null) {
            return Response::html(
                View::render(
                    'errors/404',
                    ['pageTitle' => 'Page not found']
                ),
                404
            );
        }

        return Response::html(
            View::render('workspace/page-builder', [
                'pageTitle' =>
                    (string) ($record['title'] ?? 'Page')
                    . ' · Page Builder',
                'activeArea' => 'dashboard',
                'workspaceType' => 'admin',
                'workspaceLabel' => 'Admin Control Center',
                'workspaceNavigation' => $this->navigation(),
                'activeSection' => 'pages',
                'pageBuilderMode' => 'edit',
                'record' => $record,
            ])
        );
    }
    /* BARAMA_RBAC_PAGE_V135 */
    public function rolesPermissions(Request $request): Response
    {
        $roleGovernance = (new RolePermissionGovernanceService())->viewModel();

        return Response::html(View::render('workspace/roles-permissions', [
            'pageTitle' => 'Roles & Permissions · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'roles-permissions',
            'roleGovernance' => $roleGovernance,
            'saved' => (string) $request->input('saved', ''),
            'error' => (string) $request->input('error', ''),
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

    /* BARAMA_MAIL_QUEUE_OPERATIONS_V110 */
    public function mailQueue(Request $request): Response
    {
        $repository = new NotificationRepository();
        $validStatuses = ['pending', 'processing', 'failed', 'sent', 'cancelled'];
        $validPageSizes = [25, 50, 100];

        $cleanText = static function (mixed $value): string {
            $value = trim((string) $value);
            return mb_substr($value, 0, 160);
        };
        $cleanDate = static function (mixed $value): string {
            $value = trim((string) $value);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
                return '';
            }
            return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
                ? $value
                : '';
        };

        $status = strtolower($cleanText($request->input('status', '')));
        if (!in_array($status, $validStatuses, true)) {
            $status = '';
        }

        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, $validPageSizes, true)) {
            $perPage = 25;
        }

        $filters = [
            'recipient' => $cleanText($request->input('recipient', '')),
            'subject' => $cleanText($request->input('subject', '')),
            'status' => $status,
            'date_from' => $cleanDate($request->input('date_from', '')),
            'date_to' => $cleanDate($request->input('date_to', '')),
        ];
        $page = max(1, (int) $request->input('page', 1));
        $outboxPage = $repository->adminOutboxPage($filters, $page, $perPage);

        return Response::html(View::render('workspace/communications-mail-queue', [
            'pageTitle' => 'Mail Queue & Logs · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'mail-queue',
            'outbox' => $outboxPage['rows'],
            'mailQueueFilters' => $filters,
            'mailQueuePagination' => $outboxPage,
            'communicationsSummary' => $repository->communicationsSummary(),
        ]));
    }

    /* BARAMA_MAIL_ANALYTICS_V150 */
    public function mailAnalytics(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        try {
            $analytics = (new MailAnalyticsRepository())->report($days);
        } catch (\Throwable $exception) {
            error_log(
                '[Barama Mail Analytics] '
                . $exception::class
                . ': '
                . $exception->getMessage()
            );

            $analytics = [
                'available' => false,
                'days' => $days,
                'tracking_started_at' => '',
                'attempts' => 0,
                'retries' => 0,
                'sent' => 0,
                'failed' => 0,
                'manual_requeues' => 0,
                'manual_cancels' => 0,
                'attempt_success_rate' => null,
                'average_retry_seconds' => null,
                'daily' => [],
                'errors' => [],
                'attempt_distribution' => [],
                'queue_health' => [],
            ];
        }

        return Response::html(View::render('workspace/communications-mail-analytics', [
            'pageTitle' => 'Mail Analytics · Admin',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'mail-analytics',
            'mailAnalytics' => $analytics,
            'mailAnalyticsDays' => $days,
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

    /* BARAMA_EMAIL_TEMPLATE_CREATE_BUILDER_V142 */
    public function createEmailTemplateBuilder(Request $request): Response
    {
        $defaults = \Barama\Services\EmailTemplateManagementService::customBuilderDefaults();
        $key = 'custom_new';

        return Response::html(View::render('workspace/email-template-builder', [
            'pageTitle' => 'Create New · Email Template Builder',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'email-templates',
            'emailTemplateCreateMode' => true,
            'emailTemplateKey' => $key,
            'emailTemplateDefinition' => [
                'label' => 'Create New',
                'group' => 'Custom',
                'description' => 'Create a reusable custom email template with the full Barama Email Template Builder.',
                'security_sensitive' => false,
            ],
            'emailTemplate' => null,
            'emailTemplateLocale' => 'en',
            'emailTemplateLocales' => \Barama\Services\EmailTemplateManagementService::supportedLocales(),
            'emailTemplateVariant' => null,
            'emailTemplateRevisions' => [],
            'emailTemplateDefaults' => $defaults,
            'emailTemplateVariables' => \Barama\Services\EmailTemplateManagementService::variablesForKey($key),
            'emailTemplateSamples' => \Barama\Services\EmailTemplateManagementService::sampleVariablesForKey($key),
        ]));
    }

    /* BARAMA_EMAIL_TEMPLATE_BUILDER_V137 */
    public function emailTemplateBuilder(Request $request, string $key): Response
    {
        $key = strtolower(trim($key));
        $definition = \Barama\Services\EmailTemplateCatalog::get($key);
        if ($definition === null && \Barama\Services\EmailTemplateManagementService::isCustomKey($key)) {
            $customTemplate = (new EmailTemplateRepository())->findByKey($key);
            $definition = \Barama\Services\EmailTemplateManagementService::definitionForKey($key, $customTemplate);
        }

        if ($definition === null) {
            return Response::html(
                View::render('errors/404', ['pageTitle' => 'Email template not found']),
                404
            );
        }

        $repository = new EmailTemplateRepository();
        /* BARAMA_EMAIL_TEMPLATE_LOCALIZATION_CONTEXT_V140 */
        $locale = strtolower(trim((string) $request->input('locale', 'en')));
        if (!in_array($locale, ['en', 'km', 'zh'], true)) { $locale = 'en'; }
        $template = $repository->findByKey($key);
        $localization = new \Barama\Repositories\EmailTemplateLocalizationRepository();
        $variant = $template !== null && $locale !== 'en'
            ? $localization->variantForKey($key, $locale)
            : null;
        $revisions = $template !== null
            ? $localization->revisionsForKey($key, $locale, 20)
            : [];

        return Response::html(View::render('workspace/email-template-builder', [
            'pageTitle' => (string) $definition['label'] . ' · Email Template Builder',
            'activeArea' => 'dashboard',
            'workspaceType' => 'admin',
            'workspaceLabel' => 'Admin Control Center',
            'workspaceNavigation' => $this->navigation(),
            'activeSection' => 'email-templates',
            'emailTemplateKey' => $key,
            'emailTemplateDefinition' => $definition,
            'emailTemplate' => $template,
            'emailTemplateLocale' => $locale,
            'emailTemplateLocales' => \Barama\Services\EmailTemplateManagementService::supportedLocales(),
            'emailTemplateVariant' => $variant,
            'emailTemplateRevisions' => $revisions,
            'emailTemplateDefaults' => \Barama\Services\EmailTemplateManagementService::isCustomKey($key)
                ? \Barama\Services\EmailTemplateManagementService::customBuilderDefaults()
                : \Barama\Services\EmailTemplateCatalog::defaults($key),
            'emailTemplateVariables' => \Barama\Services\EmailTemplateManagementService::variablesForKey($key),
            'emailTemplateSamples' => \Barama\Services\EmailTemplateManagementService::sampleVariablesForKey($key),
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
        $paymentMethods = new PaymentMethodRepository();

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
            'paymentMethods' => $paymentMethods->allAdmin(),
            'paymentMethodSummary' => $paymentMethods->summary(),
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

    public function securityCenter(Request $request): Response
    {
        $filters = $this->securityCenterFilters($request);
        $repository = new SecurityCenterRepository();
        $alertService = new SecurityAlertService();
        $incidentService = new SecurityIncidentService();
        $incidentFilters = $this->securityIncidentFilters($request);
        $incidentPage = filter_var(
            $request->input('incident_page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $incidentPage = $incidentPage === false ? 1 : (int) $incidentPage;
        $policy = $alertService->policy();
        $error = trim((string) $request->input('error', ''));

        try {
            $page = filter_var(
                $request->input('page', 1),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $page = $page === false ? 1 : (int) $page;

            $events = $repository->events(
                $filters,
                $page,
                10
            );
        } catch (\InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $filters['date_from'] = '';
            $filters['date_to'] = '';
            $events = $repository->events(
                $filters,
                1,
                10
            );
        }

        return Response::html(View::render(
            'workspace/security-center',
            [
                'pageTitle' => 'Security Center · Admin',
                'activeArea' => 'dashboard',
                'workspaceType' => 'admin',
                'workspaceLabel' => 'Admin Control Center',
                'workspaceNavigation' => $this->navigation(),
                'activeSection' => 'audit',
                'securitySummary' => $repository->summary(),
                'securityEvents' => $events,
                'securityEventTypes' => $repository->eventTypes(),
                'securityThrottleRows' => $repository->throttleRows(30),
                'securityThrottlePolicy' => $repository->throttlePolicy(),
                'securityFilters' => $filters,
                'securityError' => $error,
                'securityAlertPolicy' => $policy,
                'securitySignals' => $repository->signals((int) $policy['window_minutes']),
                'securityTrend' => $repository->trend(7),
                'securityRetention' => $repository->retentionStatus((int) $policy['retention_days']),
                'securityIncidentPage' => $incidentService->index($incidentFilters, $incidentPage, 10),
                'securityIncidentMetrics' => $incidentService->metrics(),
                'securityIncidentFilters' => $incidentFilters,
                'securityIncidentSlaPolicy' => $incidentService->slaPolicy(),
                'securityPhase6Policy' => $incidentService->phase6Policy(),
                'securityIncidentWorkload' => $incidentService->workloadSummary(),
                'securityReviewStatus' => $incidentService->reviewStatus(),
                'securityLatestReview' => $incidentService->latestReview(),
                'securityIncidentAdministrators' => $incidentService->administrators(),
                'securityNotificationHistory' => $repository->securityNotificationHistory(20),
            ]
        ));
    }

    public function securityPolicy(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $policy = (new SecurityAlertService())
                ->savePolicy($request->all());

            (new SecurityAuditService())->record(
                'admin.security_policy_updated',
                (int) $user['id'],
                (int) $user['id'],
                [
                    'failed_login_threshold' =>
                        (int) $policy['failed_login_threshold'],
                    'locked_login_threshold' =>
                        (int) $policy['locked_login_threshold'],
                    'active_lockout_threshold' =>
                        (int) $policy['active_lockout_threshold'],
                    'window_minutes' =>
                        (int) $policy['window_minutes'],
                    'cooldown_minutes' =>
                        (int) $policy['cooldown_minutes'],
                    'retention_days' =>
                        (int) $policy['retention_days'],
                    'in_app_alerts' =>
                        !empty($policy['in_app_alerts']) ? 1 : 0,
                    'email_alerts' =>
                        !empty($policy['email_alerts']) ? 1 : 0,
                ]
            );

            return Response::redirect(
                '/dashboard/audit?security_status='
                . rawurlencode('Security alert policy saved.')
            );
        } catch (\InvalidArgumentException $exception) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode($exception->getMessage())
            );
        }
    }

    public function evaluateSecurityAlerts(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        $result = (new SecurityAlertService())->evaluate();

        (new SecurityAuditService())->record(
            'admin.security_alert_evaluated',
            (int) $user['id'],
            (int) $user['id'],
            [
                'triggered' => count($result['triggered']),
                'notifications' => (int) $result['notifications'],
                'emails' => (int) $result['emails'],
            ]
        );

        $message = count($result['triggered']) > 0
            ? sprintf(
                'Evaluation complete: %d alert condition(s) active.',
                count($result['triggered'])
            )
            : 'Evaluation complete: no alert thresholds are active.';

        return Response::redirect(
            '/dashboard/audit?security_status='
            . rawurlencode($message)
        );
    }
    public function securityPhase6Policy(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            (new SecurityIncidentService())->savePhase6Policy(
                $request->all(),
                (int) $user['id']
            );

            return Response::redirect(
                '/dashboard/audit?security_status='
                . rawurlencode('Phase 6 security operations policy saved.')
                . '#security-phase6'
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode($exception->getMessage())
                . '#security-phase6'
            );
        }
    }

    public function securityEscalationsRun(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $result = (new SecurityIncidentService())
                ->evaluateEscalations((int) $user['id'], false);

            $message = sprintf(
                'Escalation evaluation: %d evaluated, %d escalated, %d assigned, %d notified.',
                (int) $result['evaluated'],
                (int) $result['escalated'],
                (int) $result['assigned'],
                (int) $result['notified']
            );

            return Response::redirect(
                '/dashboard/audit?security_status='
                . rawurlencode($message)
                . '#security-phase6'
            );
        } catch (\DomainException $exception) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode($exception->getMessage())
                . '#security-phase6'
            );
        }
    }

    public function securityReviewRun(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        try {
            $report = (new SecurityIncidentService())
                ->runSecurityReview(
                    (int) $user['id'],
                    true,
                    false
                );

            $metrics = is_array($report['metrics'] ?? null)
                ? $report['metrics']
                : [];

            $message = sprintf(
                'Security review generated: %d overdue, %d critical.',
                (int) ($metrics['overdue'] ?? 0),
                (int) ($metrics['critical'] ?? 0)
            );

            return Response::redirect(
                '/dashboard/audit?security_status='
                . rawurlencode($message)
                . '#security-phase6-review'
            );
        } catch (\DomainException $exception) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode($exception->getMessage())
                . '#security-phase6-review'
            );
        }
    }
    public function securityIncidentsAcknowledge(Request $request): Response
    {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        $ids = $request->input('incident_ids', []);
        $ids = is_array($ids) ? $ids : [];

        try {
            $result = (new SecurityIncidentService())->acknowledgeMany(
                $ids,
                (int) $user['id']
            );

            $message = sprintf(
                'Bulk acknowledgment complete: %d updated, %d skipped.',
                (int) $result['updated'],
                (int) $result['skipped']
            );

            return Response::redirect(
                '/dashboard/audit?security_status=' . rawurlencode($message)
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return Response::redirect(
                '/dashboard/audit?error=' . rawurlencode($exception->getMessage())
            );
        }
    }

    public function securityIncidentExport(Request $request): Response
    {
        $filters = $this->securityIncidentFilters($request);
        $rows = (new SecurityIncidentService())->export($filters, 500);
        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new \RuntimeException(
                'Incident CSV export could not be prepared.'
            );
        }

        fputcsv($handle, [
            'Incident ID',
            'Event Type',
            'Status',
            'Severity',
            'SLA State',
            'Age',
            'SLA Hours',
            'Due At',
            'Assignee',
            'Actor',
            'Subject',
            'Created At',
            'Resolved At',
        ]);

        foreach ($rows as $row) {
            $state = is_array($row['state'] ?? null)
                ? $row['state']
                : [];

            fputcsv($handle, [
                $this->securityCsvCell((string) ($row['id'] ?? '')),
                $this->securityCsvCell((string) ($row['event_type'] ?? '')),
                $this->securityCsvCell((string) ($state['status'] ?? 'open')),
                $this->securityCsvCell((string) ($state['severity'] ?? 'medium')),
                $this->securityCsvCell((string) ($row['sla_state'] ?? '')),
                $this->securityCsvCell((string) ($row['age_label'] ?? '')),
                $this->securityCsvCell((string) ($row['sla_hours'] ?? '')),
                $this->securityCsvCell((string) ($row['due_at'] ?? '')),
                $this->securityCsvCell((string) ($state['assignee_name'] ?? '')),
                $this->securityCsvCell((string) ($row['actor'] ?? '')),
                $this->securityCsvCell((string) ($row['subject'] ?? '')),
                $this->securityCsvCell((string) ($row['created_at'] ?? '')),
                $this->securityCsvCell((string) ($state['resolved_at'] ?? '')),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if (!is_string($csv)) {
            throw new \RuntimeException(
                'Incident CSV export could not be finalized.'
            );
        }

        return Response::binary(
            "\xEF\xBB\xBF" . $csv,
            'text/csv; charset=UTF-8',
            [
                'Content-Disposition' =>
                    'attachment; filename="barama-security-incidents-'
                    . gmdate('Ymd-His') . '.csv"',
                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    /** @return array<string,string> */
    private function securityIncidentFilters(Request $request): array
    {
        $status = strtolower(trim((string) $request->input(
            'incident_status',
            ''
        )));
        $severity = strtolower(trim((string) $request->input(
            'incident_severity',
            ''
        )));
        $sla = strtolower(trim((string) $request->input(
            'incident_sla',
            ''
        )));
        $assignee = trim((string) $request->input(
            'incident_assignee',
            ''
        ));

        if (!in_array(
            $status,
            ['', 'open', 'acknowledged', 'investigating', 'resolved'],
            true
        )) {
            $status = '';
        }

        if (!in_array(
            $severity,
            ['', 'low', 'medium', 'high', 'critical'],
            true
        )) {
            $severity = '';
        }

        if (!in_array($sla, ['', 'overdue', 'on_track'], true)) {
            $sla = '';
        }

        if (
            $assignee !== ''
            && $assignee !== 'unassigned'
            && filter_var(
                $assignee,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            $assignee = '';
        }

        return [
            'incident_q' => mb_substr(
                trim((string) $request->input('incident_q', '')),
                0,
                100
            ),
            'incident_status' => $status,
            'incident_severity' => $severity,
            'incident_assignee' => $assignee,
            'incident_sla' => $sla,
        ];
    }
    public function securityIncident(
        Request $request,
        string $id
    ): Response {
        $user = (new AuthService())->user();

        if ($user === null) {
            return Response::redirect('/login');
        }

        $eventId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($eventId === false) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode('Security incident identifier is invalid.')
            );
        }

        try {
            (new SecurityIncidentService())->update(
                (int) $eventId,
                (int) $user['id'],
                $request->all()
            );

            return Response::redirect(
                '/dashboard/audit?security_status='
                . rawurlencode('Security incident updated.')
                . '#security-incident-' . (int) $eventId
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return Response::redirect(
                '/dashboard/audit?error='
                . rawurlencode($exception->getMessage())
                . '#security-incident-' . (int) $eventId
            );
        }
    }
    public function securityExport(Request $request): Response
    {
        $filters = $this->securityCenterFilters($request);

        try {
            $rows = (new SecurityCenterRepository())
                ->exportEvents($filters, 5000);
        } catch (\InvalidArgumentException $exception) {
            return Response::redirect(
                url(
                    '/dashboard/audit?error='
                    . rawurlencode($exception->getMessage())
                )
            );
        }

        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new \RuntimeException(
                'Security CSV export could not be prepared.'
            );
        }

        fputcsv(
            $handle,
            [
                'ID',
                'Event Type',
                'Family',
                'Actor',
                'Subject',
                'Client State',
                'Created At',
            ]
        );

        foreach ($rows as $row) {
            fputcsv(
                $handle,
                [
                    $this->securityCsvCell((string) ($row['id'] ?? '')),
                    $this->securityCsvCell((string) ($row['event_type'] ?? '')),
                    $this->securityCsvCell((string) ($row['event_family'] ?? '')),
                    $this->securityCsvCell((string) ($row['actor'] ?? '')),
                    $this->securityCsvCell((string) ($row['subject'] ?? '')),
                    $this->securityCsvCell((string) ($row['client_state'] ?? '')),
                    $this->securityCsvCell((string) ($row['created_at'] ?? '')),
                ]
            );
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if (!is_string($csv)) {
            throw new \RuntimeException(
                'Security CSV export could not be finalized.'
            );
        }

        $filename = 'barama-security-events-'
            . gmdate('Ymd-His')
            . '.csv';

        return Response::binary(
            "\xEF\xBB\xBF" . $csv,
            'text/csv; charset=UTF-8',
            [
                'Content-Disposition' =>
                    'attachment; filename="' . $filename . '"',
                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }

    /** @return array<string,string> */
    private function securityCenterFilters(Request $request): array
    {
        return [
            'q' => mb_substr(
                trim((string) $request->input('q', '')),
                0,
                100
            ),
            'family' => mb_substr(
                strtolower(trim(
                    (string) $request->input('family', '')
                )),
                0,
                20
            ),
            'event_type' => mb_substr(
                trim((string) $request->input('event_type', '')),
                0,
                100
            ),
            'date_from' => mb_substr(
                trim((string) $request->input('date_from', '')),
                0,
                10
            ),
            'date_to' => mb_substr(
                trim((string) $request->input('date_to', '')),
                0,
                10
            ),
        ];
    }

    private function securityCsvCell(string $value): string
    {
        $value = str_replace(
            ["\r", "\n", "\0"],
            [' ', ' ', ''],
            $value
        );

        if (
            $value !== ''
            && in_array($value[0], ['=', '+', '-', '@'], true)
        ) {
            return "'" . $value;
        }

        return $value;
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

        if ($resource === 'page') {
            return Response::redirect(
                '/dashboard/pages/' . (int) $id . '/edit'
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

        $workspaceRead = new WorkspaceReadRepository();
        $adminEditMode = in_array($resource, ['company', 'project', 'property'], true)
            && in_array((string) $request->input('edit', ''), ['1', 'true', 'yes'], true);

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
            'adminEditMode' => $adminEditMode,
            'formOptions' => [
                'locations' => $workspaceRead->adminLocations(),
                'projects' => $resource === 'property'
                    ? $workspaceRead->adminProjectsForCompany((int) ($record['company_id'] ?? 0))
                    : [],
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
        $preferredPageSize = in_array((int) ($workspacePreferences['page_size'] ?? 20), [10, 20, 50], true)
            ? (int) ($workspacePreferences['page_size'] ?? 20)
            : 20;
        $pageSize = in_array(
            $section,
            ['companies', 'projects', 'properties', 'media'],
            true
        )
            ? 10
            : $preferredPageSize;
        // Listings Pagination Standard Repair v1.4.7:
        // keep the existing workspace page-size logic intact, but standardize Listings.
        if ($section === 'listings') {
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

        // Catalog Pagination Standard v1.6.6:
        // Locations & Amenities inventory uses the compact 10-item pagination standard.
        if ($section === 'catalog') {
            $pageSize = 10;
        }

        // Pages Pagination Standard v1.6.8:
        // CMS Page Manager uses the compact 10-item pagination standard.
        if ($section === 'pages') {
            $pageSize = 10;
        }

        // SEO Pagination Standard v1.6.9:
        // SEO governance inventory uses the compact 10-item pagination standard.
        if ($section === 'seo') {
            $pageSize = 10;
        }

        // Users Pagination Standard v1.7.1:
        // User account inventory uses the compact 10-item pagination standard.
        if ($section === 'users') {
            $pageSize = 10;
        }

        // Verification Pagination Standard v1.7.3:
        // Verification Center uses the compact 10-item pagination standard.
        if ($section === 'verification') {
            $pageSize = 10;
        }

        // Audit Pagination Standard v1.7.5:
        // Security Audit uses the compact 10-item pagination standard.
        if ($section === 'audit') {
            $pageSize = 10;
        }

        // Approvals Pagination Standard v1.7.6:
        // Approval Queue uses the compact 10-item pagination standard.
        if ($section === 'approvals') {
            $pageSize = 10;
        }

        if ($rememberFilters && $hasFilterQuery && $settingsUser !== null) {
            $workspacePreferences['last_filters'] = ['q'=>$search, 'status'=>$filters['status'], 'type'=>$filters['type'], 'location'=>$filters['location'], 'readiness'=>$filters['readiness'], 'quality'=>$filters['quality'], 'sort'=>$sort];
            $settingsRepository->save((int) $settingsUser['id'], 'dashboard', $workspacePreferences);
        }

        $repository = new WorkspaceReadRepository();
        $siteContent = in_array($section, ['properties', 'projects', 'companies'], true)
            ? (new SiteContentRepository())->get()
            : [];
        $marketplacePageConfig = in_array($section, ['properties', 'projects', 'companies'], true)
            && is_array($siteContent['marketplace_pages'][$section] ?? null)
                ? $siteContent['marketplace_pages'][$section]
                : [];
        $summary = $repository->adminSummary();
        $marketplaceSummary = $repository->adminMarketplaceSummary($section);
        $propertyQualityOperations = [];
        if ($section === 'properties') {
            $propertyReadinessService = new \Barama\Services\PropertyReadinessService();
            if (strtolower(trim((string) $request->input('export', ''))) === 'readiness_csv') {
                return Response::binary(
                    $propertyReadinessService->csvReportForAdmin($filters),
                    'text/csv; charset=UTF-8',
                    ['Content-Disposition' => 'attachment; filename="barama-property-readiness-' . gmdate('Ymd') . '.csv"']
                );
            }
            $propertyQualityOperations = $propertyReadinessService->operationalSummaryForAdmin();
        }
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
            'propertyQualityOperations' => $propertyQualityOperations,
            'formOptions' => [
                'locations' => $repository->adminLocations(),
            ],
            'marketplacePageConfig' => $marketplacePageConfig,
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
                // Companies Summary Focus v1.5.7 - primary operational states only.
                ['label' => 'All companies', 'value' => $marketplace['total'] ?? 0, 'icon' => 'building', 'href' => '/dashboard/companies'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/companies?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/companies?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/companies?status=approved'],
                ['label' => 'Suspended', 'value' => $marketplace['suspended'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/companies?status=suspended'],
            ],
            'projects' => [
                // Projects Summary Focus v1.5.3 - primary moderation/publication states only.
                // Projects Summary Verification Repair v1.5.4
                ['label' => 'All projects', 'value' => $marketplace['total'] ?? 0, 'icon' => 'layers', 'href' => '/dashboard/projects'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/projects?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/projects?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/projects?status=approved'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/projects?status=published'],
            ],
            'properties' => [
                ['label' => 'All properties', 'value' => $marketplace['total'] ?? 0, 'icon' => 'home', 'href' => '/dashboard/properties'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/properties?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/properties?status=pending'],
                ['label' => 'Approved', 'value' => $marketplace['approved'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/properties?status=approved'],
                ['label' => 'Rejected', 'value' => $marketplace['rejected'] ?? 0, 'icon' => 'alert', 'href' => '/dashboard/properties?status=rejected'],
                ['label' => 'Archived', 'value' => $marketplace['archived'] ?? 0, 'icon' => 'shield', 'href' => '/dashboard/properties?status=archived'],
                ['label' => 'Excellent · 100%', 'value' => $marketplace['excellent'] ?? 0, 'icon' => 'check'],
                ['label' => 'Good · 80–99%', 'value' => $marketplace['good'] ?? 0, 'icon' => 'shield'],
                ['label' => 'Needs attention · 50–79%', 'value' => $marketplace['needs_attention'] ?? 0, 'icon' => 'alert'],
                ['label' => 'Incomplete · below 50%', 'value' => $marketplace['incomplete'] ?? 0, 'icon' => 'alert'],
            ],
            'listings' => [
                // Listings Summary Focus v1.4.9 - primary operational states only.
                ['label' => 'All listings', 'value' => $marketplace['total'] ?? 0, 'icon' => 'tag', 'href' => '/dashboard/listings'],
                ['label' => 'Draft', 'value' => $marketplace['draft'] ?? 0, 'icon' => 'edit', 'href' => '/dashboard/listings?status=draft'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/listings?status=pending'],
                ['label' => 'Published', 'value' => $marketplace['published'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/listings?status=published'],
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
                // Inquiries Summary Focus v1.5.9 - primary response workflow only.
                ['label' => 'All inquiries', 'value' => $marketplace['total'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/inquiries'],
                ['label' => 'New', 'value' => $marketplace['new'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/inquiries?status=new'],
                ['label' => 'Awaiting response', 'value' => $marketplace['read'] ?? 0, 'icon' => 'eye', 'href' => '/dashboard/inquiries?status=read'],
                ['label' => 'Replied', 'value' => $marketplace['replied'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/inquiries?status=replied'],
                ['label' => 'Waiting 24h+', 'value' => $marketplace['awaiting_24h'] ?? 0, 'icon' => 'alert'],
            ],

            'leads' => [
                // Leads Summary Focus v1.6.2 - daily CRM operating signals only.
                ['label' => 'All property leads', 'value' => $marketplace['total'] ?? 0, 'icon' => 'pipeline', 'href' => '/dashboard/leads'],
                ['label' => 'Active pipeline', 'value' => $marketplace['active'] ?? 0, 'icon' => 'clock'],
                ['label' => 'Qualified', 'value' => $marketplace['qualified'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/leads?status=qualified'],
                ['label' => 'Negotiation', 'value' => $marketplace['negotiation'] ?? 0, 'icon' => 'message', 'href' => '/dashboard/leads?status=negotiation'],
                ['label' => 'Unassigned', 'value' => $marketplace['unassigned'] ?? 0, 'icon' => 'users'],
                ['label' => 'Follow-up due', 'value' => $marketplace['follow_up_due'] ?? 0, 'icon' => 'clock'],
            ],

            'viewings' => [
                // Viewings Summary Focus v1.6.5 - active scheduling signals only.
                ['label' => 'All viewings', 'value' => $marketplace['total'] ?? 0, 'icon' => 'calendar', 'href' => '/dashboard/viewings'],
                ['label' => 'Pending', 'value' => $marketplace['pending'] ?? 0, 'icon' => 'clock', 'href' => '/dashboard/viewings?status=pending'],
                ['label' => 'Confirmed', 'value' => $marketplace['confirmed'] ?? 0, 'icon' => 'check', 'href' => '/dashboard/viewings?status=confirmed'],
                ['label' => 'Upcoming', 'value' => $marketplace['upcoming'] ?? 0, 'icon' => 'calendar'],
                ['label' => 'Rescheduled', 'value' => $marketplace['rescheduled'] ?? 0, 'icon' => 'calendar', 'href' => '/dashboard/viewings?status=rescheduled'],
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
    /**
     * Policy Center uses the canonical administrator navigation instead of
     * maintaining a second sidebar definition.
     *
     * @return array<int, array<string,string>>
     */
    public function policyCenterNavigation(): array
    {
        return $this->navigation();
    }

    private function navigation(): array
    {
        return [
            ['heading' => 'Control Center'],
            ['label' => 'Overview', 'path' => '/dashboard', 'icon' => 'grid'],
            ['label' => 'Approval Queue', 'path' => '/dashboard/approvals', 'icon' => 'shield'],

            ['heading' => 'Marketplace'],
            ['label' => 'Listings', 'path' => '/dashboard/listings', 'icon' => 'tag'],

            ['heading' => 'CRM'],
            ['label' => 'Inquiries', 'path' => '/dashboard/inquiries', 'icon' => 'message'],
            ['label' => 'Property Leads', 'path' => '/dashboard/leads', 'icon' => 'pipeline'],
            ['label' => 'Viewing Appointments', 'path' => '/dashboard/viewings', 'icon' => 'calendar'],

            ['heading' => 'Platform Data'],
            ['label' => 'Locations & Amenities', 'path' => '/dashboard/catalog', 'icon' => 'map'],

            ['heading' => 'Content & Discovery'],
            ['label' => 'Appearance', 'path' => '/dashboard/appearance', 'icon' => 'settings'],
            ['label' => 'Homepage', 'path' => '/dashboard/homepage', 'icon' => 'home'],
            // BARAMA_POLICY_CENTER_V103_MENU
            ['label' => 'Policy Center', 'path' => '/dashboard/policies', 'icon' => 'shield'],
            ['label' => 'Properties', 'path' => '/dashboard/properties', 'icon' => 'home'],
            ['label' => 'Projects', 'path' => '/dashboard/projects', 'icon' => 'layers'],
            ['label' => 'Companies', 'path' => '/dashboard/companies', 'icon' => 'building'],
            ['label' => 'Navigation', 'path' => '/dashboard/navigation', 'icon' => 'menu'],
            ['label' => 'About', 'path' => '/dashboard/about', 'icon' => 'users'],
            ['label' => 'Contact', 'path' => '/dashboard/contact', 'icon' => 'mail'],
            // BARAMA_POLICY_CENTER_V102\n            ['label' => 'Policy Center', 'path' => '/dashboard/policies', 'icon' => 'shield'],\n            ['label' => 'Page Manager', 'path' => '/dashboard/pages', 'icon' => 'edit'],
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
            ['label' => 'Email Templates', 'path' => '/dashboard/email-templates', 'icon' => 'mail'],
            ['label' => 'Mail Queue & Logs', 'path' => '/dashboard/mail-queue', 'icon' => 'pipeline'],
            ['label' => 'Mail Analytics', 'path' => '/dashboard/mail-analytics', 'icon' => 'database'],

            ['heading' => 'Users & Security'],
            ['label' => 'Users', 'path' => '/dashboard/users', 'icon' => 'users'],
            ['label' => 'Roles & Permissions', 'path' => '/dashboard/roles-permissions', 'icon' => 'key'], // BARAMA_RBAC_NAV_V135
            ['label' => 'Verification Center', 'path' => '/dashboard/verification', 'icon' => 'check'],
            ['label' => 'Security Center', 'path' => '/dashboard/audit', 'icon' => 'shield'],

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
                'types' => [
                    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                    'application/pdf', 'text/plain', 'text/csv', 'application/rtf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.oasis.opendocument.text',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.oasis.opendocument.presentation',
                    'video/mp4', 'video/webm', 'video/quicktime', 'video/ogg',
                    'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4',
                    'audio/x-m4a', 'audio/ogg', 'audio/flac', 'audio/x-flac',
                ],                'location' => false,
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
                'project_name' => 'Project',
                'location_name' => 'Location',
                'property_type' => 'Type',
                'listings_count' => 'Listings',
                'published_listings_count' => 'Published',
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
                // Leads Table Focus v1.6.1
                'contact_name' => 'Contact',
                'lead_context' => 'Context',
                'lead_interest' => 'Interest',
                'lead_assignment' => 'Assignment',
                'lead_stage' => 'Stage',
                'lead_activity' => 'Activity',
            ],

            'viewings' => [
                // Viewings Table Focus v1.6.4
                'customer_name' => 'Customer',
                'viewing_context' => 'Context',
                'viewing_schedule' => 'Schedule',
                'viewing_state' => 'State',
                'updated_at' => 'Updated',
            ],

            'catalog' => [
                // Catalog Table Focus v1.6.7
                'catalog_item' => 'Catalog Item',
                'catalog_hierarchy' => 'Hierarchy',
                'catalog_usage' => 'Usage',
                'catalog_state' => 'State',
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
                // SEO Table Focus v1.7.0
                'seo_record' => 'Public Record',
                'seo_coverage' => 'Coverage',
                'seo_governance' => 'Governance',
                'updated_at' => 'Updated',
            ],

            'users' => [
                // Users Table Focus v1.7.2
                'user_identity' => 'User',
                'user_access' => 'Access',
                'user_state' => 'State',
                'user_activity' => 'Activity',
            ],

            'verification' => [
                // Verification Table Focus v1.7.4
                'verification_account' => 'Account',
                'verification_access' => 'Access',
                'verification_state_focus' => 'Verification',
                'verification_issue' => 'Issue',
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
