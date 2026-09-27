<?php

declare(strict_types=1);

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Controllers\AccountController;
use Barama\Http\Controllers\AccountManagementController;
use Barama\Http\Controllers\AdminManagementController;
use Barama\Http\Controllers\AuthController;
use Barama\Http\Controllers\CompanyController;
use Barama\Http\Controllers\CmsController;
use Barama\Http\Controllers\DashboardController;
use Barama\Http\Controllers\DeveloperController;
use Barama\Http\Controllers\DeveloperManagementController;
use Barama\Http\Controllers\WorkspaceSettingsController;
use Barama\Http\Controllers\HomeController;
use Barama\Http\Controllers\InquiryController;
use Barama\Http\Controllers\LocaleController;
use Barama\Http\Controllers\MarketplaceController;
use Barama\Http\Controllers\PublicMediaController;
use Barama\Http\Controllers\SeoController;
use Barama\Http\Middleware\AuthMiddleware;
use Barama\Http\Middleware\CsrfMiddleware;
use Barama\Http\Middleware\GuestMiddleware;
use Barama\Http\Middleware\PermissionMiddleware;

$guest = [new GuestMiddleware()];
$guestPost = [new GuestMiddleware(), new CsrfMiddleware()];
$csrf = [new CsrfMiddleware()];

$router->get('/', [HomeController::class, 'index']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);

$router->post('/locale', [LocaleController::class, 'update'], $csrf);

$router->get('/properties', [MarketplaceController::class, 'properties']);
$router->get('/properties/{slug}', [MarketplaceController::class, 'property']);
$router->get('/projects', [MarketplaceController::class, 'projects']);
$router->get('/projects/{slug}', [MarketplaceController::class, 'project']);
$router->get('/media/{uuid}', [PublicMediaController::class, 'show']);

$router->get('/company', [CompanyController::class, 'index']);
$router->get('/company/{slug}', [CompanyController::class, 'show']);
$router->get('/pages/{slug}', [CmsController::class, 'show']);

$router->post('/inquiries', [InquiryController::class, 'store'], $csrf);

$router->get('/login', [AuthController::class, 'showLogin'], $guest);
$router->post('/login', [AuthController::class, 'login'], $guestPost);

$router->get('/register', [AuthController::class, 'showRegister'], $guest);
$router->post('/register', [AuthController::class, 'register'], $guestPost);

$router->get(
    '/verify-email',
    [AuthController::class, 'showVerifyEmail'],
    [new AuthMiddleware()]
);
$router->post(
    '/verify-email/resend',
    [AuthController::class, 'resendVerification'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);
$router->get(
    '/verify-email/{token}',
    [AuthController::class, 'showVerifyEmailToken'],
    [new AuthMiddleware()]
);
$router->post(
    '/verify-email/{token}',
    [AuthController::class, 'verifyEmail'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);

$router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], $guest);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink'], $guestPost);

$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], $guest);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], $guestPost);

$router->post(
    '/logout',
    [AuthController::class, 'logout'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);

$router->get(
    '/account',
    [AccountController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access')]
);
$router->post(
    '/account/profile',
    [AccountManagementController::class, 'updateProfile'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/privacy',
    [AccountManagementController::class, 'updatePrivacy'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/privacy/clear-history',
    [AccountManagementController::class, 'clearRecentViews'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/security/password',
    [AccountManagementController::class, 'changePassword'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/security/email',
    [AccountManagementController::class, 'requestEmailChange'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/security/deactivate',
    [AccountManagementController::class, 'deactivateAccount'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->get(
    '/account/email-change/{token}',
    [AccountController::class, 'emailChange'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access')]
);
$router->post(
    '/account/email-change/{token}',
    [AccountManagementController::class, 'confirmEmailChange'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/company/onboard',
    [AccountManagementController::class, 'submitCompanyOnboarding'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/company-invitations/{id}/accept',
    [AccountManagementController::class, 'acceptCompanyInvitation'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/saved/{id}',
    [AccountManagementController::class, 'saveListing'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/saved/{id}/remove',
    [AccountManagementController::class, 'removeSavedListing'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/compare/{id}',
    [AccountManagementController::class, 'addComparison'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/compare/{id}/remove',
    [AccountManagementController::class, 'removeComparison'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/alerts',
    [AccountManagementController::class, 'createSavedSearch'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/alerts/{id}',
    [AccountManagementController::class, 'updateSavedSearch'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/alerts/{id}/delete',
    [AccountManagementController::class, 'deleteSavedSearch'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/viewings/{id}',
    [AccountManagementController::class, 'createViewing'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/viewings/{id}/cancel',
    [AccountManagementController::class, 'cancelViewing'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/notifications/{id}/read',
    [AccountManagementController::class, 'markNotificationRead'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/notifications/read-all',
    [AccountManagementController::class, 'markAllNotificationsRead'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->post(
    '/account/notification-preferences',
    [AccountManagementController::class, 'updateNotificationPreferences'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]
);
$router->get('/account/settings', [WorkspaceSettingsController::class, 'accountIndex'], [new AuthMiddleware(), new PermissionMiddleware('account.access')]);
$router->get('/account/settings/{section}', [WorkspaceSettingsController::class, 'account'], [new AuthMiddleware(), new PermissionMiddleware('account.access')]);
$router->post('/account/settings/{section}', [WorkspaceSettingsController::class, 'saveAccount'], [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]);

$router->get(
    '/account/{section}',
    [AccountController::class, 'section'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access')]
);

$router->get(
    '/developer',
    [DeveloperController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);
$router->get(
    '/developer/manage/{resource}/{id}',
    [DeveloperController::class, 'record'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);
$router->get('/developer/settings', [WorkspaceSettingsController::class, 'developerIndex'], [new AuthMiddleware(), new PermissionMiddleware('developer.access')]);
$router->get('/developer/settings/{section}', [WorkspaceSettingsController::class, 'developer'], [new AuthMiddleware(), new PermissionMiddleware('developer.access')]);
$router->post('/developer/settings/{section}', [WorkspaceSettingsController::class, 'saveDeveloper'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]);

$router->get(
    '/developer/{section}',
    [DeveloperController::class, 'section'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);

$router->post(
    '/developer/companies',
    [DeveloperManagementController::class, 'createCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}',
    [DeveloperManagementController::class, 'updateCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/members',
    [DeveloperManagementController::class, 'addMember'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/members/{userId}/role',
    [DeveloperManagementController::class, 'updateMemberRole'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/members/{userId}/deactivate',
    [DeveloperManagementController::class, 'deactivateMember'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/companies/{id}/invitations/{invitationId}/revoke',
    [DeveloperManagementController::class, 'revokeInvitation'],
    [new AuthMiddleware(), new PermissionMiddleware('company.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/projects',
    [DeveloperManagementController::class, 'createProject'],
    [new AuthMiddleware(), new PermissionMiddleware('project.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/projects/{id}',
    [DeveloperManagementController::class, 'updateProject'],
    [new AuthMiddleware(), new PermissionMiddleware('project.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/properties',
    [DeveloperManagementController::class, 'createProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('property.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/properties/{id}',
    [DeveloperManagementController::class, 'updateProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('property.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/listings',
    [DeveloperManagementController::class, 'createListing'],
    [new AuthMiddleware(), new PermissionMiddleware('listing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/listings/{id}',
    [DeveloperManagementController::class, 'updateListing'],
    [new AuthMiddleware(), new PermissionMiddleware('listing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/workflow/{resource}/{id}',
    [DeveloperManagementController::class, 'workflow'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]
);
$router->post(
    '/developer/viewings/{id}',
    [DeveloperManagementController::class, 'updateViewing'],
    [new AuthMiddleware(), new PermissionMiddleware('inquiry.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/inquiries/{id}/lead',
    [DeveloperManagementController::class, 'convertLead'],
    [new AuthMiddleware(), new PermissionMiddleware('lead.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/media/{id}/alt-text',
    [DeveloperManagementController::class, 'updateMediaAltText'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/media/{id}/primary',
    [DeveloperManagementController::class, 'setPrimaryMedia'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/media/{id}/remove',
    [DeveloperManagementController::class, 'removeMedia'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
$router->post(
    '/developer/media',
    [DeveloperManagementController::class, 'uploadMedia'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);

$router->get(
    '/dashboard',
    [DashboardController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->get(
    '/dashboard/manage/{resource}/{id}',
    [DashboardController::class, 'record'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->get(
    '/dashboard/plans',
    [DashboardController::class, 'plans'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/plans',
    [AdminManagementController::class, 'createPlan'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/plans/{id}/status',
    [AdminManagementController::class, 'updatePlanStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/subscriptions',
    [DashboardController::class, 'subscriptions'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/subscriptions',
    [AdminManagementController::class, 'assignSubscription'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/subscriptions/{id}/status',
    [AdminManagementController::class, 'updateSubscriptionStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/coupons',
    [DashboardController::class, 'coupons'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/coupons',
    [AdminManagementController::class, 'createCoupon'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/coupons/{id}/status',
    [AdminManagementController::class, 'updateCouponStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/taxes',
    [DashboardController::class, 'taxes'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/taxes',
    [AdminManagementController::class, 'createTaxRule'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/taxes/{id}/status',
    [AdminManagementController::class, 'updateTaxRuleStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/invoices',
    [DashboardController::class, 'invoices'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/invoices',
    [AdminManagementController::class, 'createInvoice'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/invoices/{id}/status',
    [AdminManagementController::class, 'updateInvoiceStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/transactions',
    [DashboardController::class, 'transactions'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/transactions',
    [AdminManagementController::class, 'createTransaction'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/homepage',
    [DashboardController::class, 'homepage'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/homepage',
    [AdminManagementController::class, 'updateSiteContent'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->get('/dashboard/settings', [WorkspaceSettingsController::class, 'dashboardIndex'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->get('/dashboard/settings/{section}', [WorkspaceSettingsController::class, 'dashboard'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->post('/dashboard/settings/{section}', [WorkspaceSettingsController::class, 'saveDashboard'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);

$router->get(
    '/dashboard/{section}',
    [DashboardController::class, 'section'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->post(
    '/dashboard/locations',
    [AdminManagementController::class, 'createLocation'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/amenities',
    [AdminManagementController::class, 'createAmenity'],
    [new AuthMiddleware(), new PermissionMiddleware('amenity.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/locations/{id}/status',
    [AdminManagementController::class, 'updateLocationStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/amenities/{id}/status',
    [AdminManagementController::class, 'updateAmenityStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('amenity.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/pages',
    [AdminManagementController::class, 'createPage'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/pages/{id}',
    [AdminManagementController::class, 'updatePage'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/pages/{id}/status',
    [AdminManagementController::class, 'updatePageStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/media/{id}/alt-text',
    [AdminManagementController::class, 'updateMediaAltText'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/media/{id}/orphan',
    [AdminManagementController::class, 'deleteOrphanMedia'],
    [new AuthMiddleware(), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/bulk-workflow',
    [AdminManagementController::class, 'bulkWorkflow'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/workflow/{resource}/{id}',
    [AdminManagementController::class, 'workflow'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/status',
    [AdminManagementController::class, 'updateUserStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/users/{id}/administrator',
    [AdminManagementController::class, 'setAdministrator'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/developer-access/reconcile',
    [AdminManagementController::class, 'reconcileDeveloperAccess'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/sessions/revoke',
    [AdminManagementController::class, 'revokeUserSessions'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->get('/health', static function (Request $request): Response {
    return Response::json([
        'status' => 'ok',
        'application' => (string) config('app.name'),
        'version' => APP_VERSION,
    ]);
});
