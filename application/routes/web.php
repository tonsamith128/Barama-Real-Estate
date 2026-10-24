<?php

declare(strict_types=1);

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Http\Controllers\AccountController;
use Barama\Http\Controllers\AccountAvatarController;
use Barama\Http\Controllers\AccountManagementController;
use Barama\Http\Controllers\AdminManagementController;
use Barama\Http\Controllers\AppearanceController;
use Barama\Http\Controllers\BuiltInPageAdminController;
use Barama\Http\Controllers\BuiltInPageController;
use Barama\Http\Controllers\AuthController;
use Barama\Http\Controllers\CompanyController;
use Barama\Http\Controllers\ContactMessageController;
use Barama\Http\Controllers\CmsController;
use Barama\Http\Controllers\LicenseSettingsController;
use Barama\Http\Controllers\DashboardController;
use Barama\Http\Controllers\DeveloperController;
use Barama\Http\Controllers\DeveloperNotificationsController;
use Barama\Http\Controllers\NotificationActionController;
use Barama\Http\Controllers\DeveloperManagementController;
use Barama\Http\Controllers\WorkspaceSettingsController;
use Barama\Http\Controllers\FooterController;
use Barama\Http\Controllers\HomeController;
use Barama\Http\Controllers\InquiryController;
use Barama\Http\Controllers\LocaleController;
use Barama\Http\Controllers\MarketplaceController;
use Barama\Http\Controllers\NewsletterController;
use Barama\Http\Controllers\PublicMediaController;
use Barama\Http\Controllers\RolePermissionController; // BARAMA_RBAC_ROUTE_V135
use Barama\Http\Controllers\PaymentWebhookController;
use Barama\Http\Controllers\SeoController;
use Barama\Http\Controllers\SystemHealthController;
use Barama\Http\Controllers\SystemCenterController;
use Barama\Http\Controllers\SystemToolController;
use Barama\Http\Controllers\DemoImporterController;
use Barama\Http\Controllers\PlanPurchaseController;
use Barama\Http\Controllers\InvoiceTemplateController;
use Barama\Http\Middleware\AuthMiddleware;
use Barama\Http\Middleware\CsrfMiddleware;
use Barama\Http\Middleware\GuestMiddleware;
use Barama\Http\Middleware\PermissionMiddleware;

$guest = [new GuestMiddleware()];
$guestPost = [new GuestMiddleware(), new CsrfMiddleware()];
$csrf = [new CsrfMiddleware()];
/* BARAMA_TURNSTILE_REAL_V110: customer-key verification, default disabled */
$router->get('/dashboard/settings/security/turnstile',
    [\Barama\Http\Controllers\TurnstileSettingsController::class, 'show'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->post('/dashboard/settings/security/turnstile',
    [\Barama\Http\Controllers\TurnstileSettingsController::class, 'change'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);


$router->get('/', [HomeController::class, 'index']);
$router->get('/workspace-access', [AccountController::class, 'workspaceAccess'], [new AuthMiddleware()]);
$router->get('/appearance.css', [AppearanceController::class, 'stylesheet']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);

$router->post('/locale', [LocaleController::class, 'update'], $csrf);
// BARAMA_NOTIFICATION_INTERACTION_V112: recipient-owned, CSRF protected open-and-read.
$router->post('/notifications/{id}/open', [NotificationActionController::class, 'open'], [new AuthMiddleware(), new CsrfMiddleware()]);

$router->get('/properties', [MarketplaceController::class, 'properties']);
$router->get('/properties/{slug}', [MarketplaceController::class, 'property']);
$router->get('/projects', [MarketplaceController::class, 'projects']);
$router->get('/projects/{slug}', [MarketplaceController::class, 'project']);
$router->get('/media/{uuid}', [PublicMediaController::class, 'show']);

$router->get('/plans', [PlanPurchaseController::class, 'index']);
$router->get('/company', [CompanyController::class, 'index']);
$router->get('/company/{slug}', [CompanyController::class, 'show']);
$router->get('/about', [BuiltInPageController::class, 'about']);
$router->get('/contact', [BuiltInPageController::class, 'contact']);
$router->post('/contact/messages', [ContactMessageController::class, 'store'], [new CsrfMiddleware(), new \Barama\Http\Middleware\TurnstileFormMiddleware('contact')]);
$router->get('/pages/{slug}', [CmsController::class, 'legacy']);

$router->post('/inquiries', [InquiryController::class, 'store'], [new CsrfMiddleware(), new \Barama\Http\Middleware\TurnstileFormMiddleware('inquiry')]);
$router->post('/subscribe', [NewsletterController::class, 'store'], $csrf);
$router->post(
    '/payments/webhooks/{provider}',
    [PaymentWebhookController::class, 'receive']
);

$router->get('/login', [AuthController::class, 'showLogin'], $guest);
$router->post('/login', [AuthController::class, 'login'], [new GuestMiddleware(), new CsrfMiddleware(), new \Barama\Http\Middleware\TurnstileFormMiddleware('login')]);
$router->get('/mfa-challenge', [AuthController::class, 'showMfaChallenge'], $guest);
$router->post('/mfa-challenge', [AuthController::class, 'mfaChallenge'], $guestPost);

$router->get('/register', [AuthController::class, 'showRegister'], $guest);
$router->post('/register', [AuthController::class, 'register'], [new GuestMiddleware(), new CsrfMiddleware(), new \Barama\Http\Middleware\TurnstileFormMiddleware('register')]);

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
$router->post(
    '/verify-email/change',
    [AuthController::class, 'changeVerificationEmail'],
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
$router->post('/forgot-password', [AuthController::class, 'sendResetLink'], [new GuestMiddleware(), new CsrfMiddleware(), new \Barama\Http\Middleware\TurnstileFormMiddleware('forgot_password')]);

$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], $guest);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], $guestPost);

$router->post(
    '/logout',
    [AuthController::class, 'logout'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);

$router->post(
    '/security/change-confirmation',
    [WorkspaceSettingsController::class, 'authorizeChange'],
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
$router->get(
    '/account/avatar/{uuid}',
    [AccountAvatarController::class, 'show'],
    // Owner-only resolution is enforced by AccountAvatarController; all signed-in workspaces may view their own avatar.
    [new AuthMiddleware()]
);

$router->get('/account/settings', [WorkspaceSettingsController::class, 'accountIndex'], [new AuthMiddleware(), new PermissionMiddleware('account.access')]);
$router->get('/account/settings/{section}', [WorkspaceSettingsController::class, 'account'], [new AuthMiddleware(), new PermissionMiddleware('account.access')]);
$router->post('/account/settings/{section}', [WorkspaceSettingsController::class, 'saveAccount'], [new AuthMiddleware(), new PermissionMiddleware('account.access'), new CsrfMiddleware()]);

$router->get(
    '/account/developer/apply',
    [AccountController::class, 'applyDeveloper'],
    [new AuthMiddleware(), new PermissionMiddleware('account.access')]
);

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
$router->get(
    '/developer/billing',
    [DeveloperController::class, 'billing'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);
$router->get('/developer/settings', [WorkspaceSettingsController::class, 'developerIndex'], [new AuthMiddleware(), new PermissionMiddleware('developer.access')]);
$router->get('/developer/settings/{section}', [WorkspaceSettingsController::class, 'developer'], [new AuthMiddleware(), new PermissionMiddleware('developer.access')]);
$router->post('/developer/settings/{section}', [WorkspaceSettingsController::class, 'saveDeveloper'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]);

$router->get(
    '/developer/plan-purchase/{id}',
    [PlanPurchaseController::class, 'purchase'],
    [new AuthMiddleware(), new PermissionMiddleware('developer.access')]
);
$router->post(
    '/developer/plan-purchase/{id}',
    [PlanPurchaseController::class, 'submit'],
    [
        new AuthMiddleware(),
        new PermissionMiddleware('developer.access'),
        new CsrfMiddleware()
    ]
);

// BARAMA_DEVELOPER_NOTIFICATIONS_V111: user-owned inbox, before dynamic /developer/{section}.
$router->get('/developer/notifications', [DeveloperNotificationsController::class, 'index'], [new AuthMiddleware(), new PermissionMiddleware('developer.access')]);
$router->post('/developer/notifications/read-all', [DeveloperNotificationsController::class, 'readAll'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]);
$router->post('/developer/notifications/{id}/read', [DeveloperNotificationsController::class, 'read'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new CsrfMiddleware()]);

// BARAMA_DEVELOPER_DEDICATED_CREATE_V109: authorized full-page creation routes.
$router->get('/developer/company/new', [DeveloperController::class, 'newCompany'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new PermissionMiddleware('company.manage')]);
$router->get('/developer/projects/new', [DeveloperController::class, 'newProject'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new PermissionMiddleware('project.manage')]);
$router->get('/developer/properties/new', [DeveloperController::class, 'newProperty'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new PermissionMiddleware('property.manage')]);
$router->get('/developer/listings/new', [DeveloperController::class, 'newListing'], [new AuthMiddleware(), new PermissionMiddleware('developer.access'), new PermissionMiddleware('listing.manage')]);

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
    '/dashboard/plans/{id}',
    [AdminManagementController::class, 'updatePlan'],
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
    '/dashboard/payment-providers',
    [DashboardController::class, 'paymentProviders'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/payment-providers',
    [AdminManagementController::class, 'createPaymentProvider'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
/* BARAMA_PAYMENT_PROVIDERS_PREMIUM_V100 */
$router->post(
    '/dashboard/payment-providers/{id}',
    [AdminManagementController::class, 'updatePaymentProvider'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
/* BARAMA_PAYMENT_METHODS_V120 */
$router->post(
    '/dashboard/payment-methods',
    [AdminManagementController::class, 'createManualPaymentMethod'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
/* BARAMA_PAYMENT_METHODS_V110 */
$router->post(
    '/dashboard/payment-methods/{id}/status',
    [AdminManagementController::class, 'updatePaymentMethodStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/payment-methods/{id}/configure',
    [AdminManagementController::class, 'configurePaymentMethod'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/payment-providers/{id}/status',
    [AdminManagementController::class, 'updatePaymentProviderStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/billing-operations',
    [DashboardController::class, 'billingOperations'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage')]
);
$router->post(
    '/dashboard/billing-operations/webhooks/{id}/retry',
    [AdminManagementController::class, 'retryBillingWebhook'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/billing-operations/webhooks/{id}/ignore',
    [AdminManagementController::class, 'ignoreBillingWebhook'],
    [new AuthMiddleware(), new PermissionMiddleware('billing.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/invoices/template',
    [InvoiceTemplateController::class, 'save'],
    [
        new AuthMiddleware(),
        new PermissionMiddleware('billing.manage'),
        new CsrfMiddleware()
    ]
);
$router->post(
    '/dashboard/invoices/template/reset',
    [InvoiceTemplateController::class, 'reset'],
    [
        new AuthMiddleware(),
        new PermissionMiddleware('billing.manage'),
        new CsrfMiddleware()
    ]
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
    '/dashboard/notifications',
    [DashboardController::class, 'notificationCenter'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
);
$router->get(
    '/dashboard/email-templates',
    [DashboardController::class, 'emailTemplates'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
);
$router->get(
    '/dashboard/email-templates/create',
    [DashboardController::class, 'createEmailTemplateBuilder'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
); // BARAMA_EMAIL_TEMPLATE_CREATE_ROUTE_V142
$router->get(
    '/dashboard/email-templates/media-library',
    [AdminManagementController::class, 'emailTemplateMediaLibrary'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new PermissionMiddleware('media.manage')]
); // BARAMA_EMAIL_TEMPLATE_MEDIA_LIBRARY_ROUTE_V143
$router->get(
    '/dashboard/email-templates/{key}/builder',
    [DashboardController::class, 'emailTemplateBuilder'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
); // BARAMA_EMAIL_TEMPLATE_BUILDER_ROUTE_V137
$router->post(
    '/dashboard/email-templates/{key}/test',
    [AdminManagementController::class, 'testEmailTemplate'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
); // BARAMA_EMAIL_TEMPLATE_TEST_ROUTE_V139
$router->post(
    '/dashboard/email-templates/{key}/variants/{locale}',
    [AdminManagementController::class, 'saveEmailTemplateVariant'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
); // BARAMA_EMAIL_TEMPLATE_LOCALIZATION_ROUTES_V140
$router->post(
    '/dashboard/email-templates/{key}/variants/{locale}/status',
    [AdminManagementController::class, 'updateEmailTemplateVariantStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/email-templates/{key}/revisions/{revisionId}/restore',
    [AdminManagementController::class, 'restoreEmailTemplateRevision'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/email-templates',
    [AdminManagementController::class, 'createEmailTemplate'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/email-templates/{id}',
    [AdminManagementController::class, 'updateEmailTemplate'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/email-templates/{id}/status',
    [AdminManagementController::class, 'updateEmailTemplateStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
/* BARAMA_MAIL_ANALYTICS_V150 */
$router->get(
    '/dashboard/mail-analytics',
    [DashboardController::class, 'mailAnalytics'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
);
$router->get(
    '/dashboard/mail-queue',
    [DashboardController::class, 'mailQueue'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
);
$router->post(
    '/dashboard/mail-queue/{id}/requeue',
    [AdminManagementController::class, 'requeueMail'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/mail-queue/{id}/cancel',
    [AdminManagementController::class, 'cancelMail'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
/* BARAMA_MAIL_QUEUE_OPERATIONS_V120 */
$router->get(
    '/dashboard/mail-queue/{id}/details',
    [AdminManagementController::class, 'mailQueueDetail'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage')]
);
$router->post(
    '/dashboard/mail-queue/bulk',
    [AdminManagementController::class, 'bulkMail'],
    [new AuthMiddleware(), new PermissionMiddleware('communication.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/appearance',
    [DashboardController::class, 'appearance'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/appearance/reset',
    [AdminManagementController::class, 'resetAppearance'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/appearance/{feature}',
    [AdminManagementController::class, 'updateAppearanceFeature'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/appearance/{feature}/toggle',
    [AdminManagementController::class, 'toggleAppearanceFeature'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/marketplace-pages/{page}/configuration',
    [AdminManagementController::class, 'updateMarketplacePageConfiguration'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);

$router->get(
    '/dashboard/homepage',
    [DashboardController::class, 'homepage'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/homepage/{feature}',
    [AdminManagementController::class, 'updateHomepageFeature'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/homepage/{feature}/toggle',
    [AdminManagementController::class, 'toggleHomepageFeature'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->get(
    '/dashboard/about',
    [DashboardController::class, 'about'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->get(
    '/dashboard/contact',
    [DashboardController::class, 'contact'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->get(
    '/dashboard/footer',
    [FooterController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/footer',
    [FooterController::class, 'update'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);

$router->get(
    '/dashboard/navigation',
    [DashboardController::class, 'navigationManager'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/navigation',
    [AdminManagementController::class, 'createNavigation'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/navigation/{id}',
    [AdminManagementController::class, 'updateNavigation'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/navigation/{id}/toggle',
    [AdminManagementController::class, 'toggleNavigation'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/navigation/{id}/delete',
    [AdminManagementController::class, 'deleteNavigation'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/built-in-pages/{page}/{section}',
    [BuiltInPageAdminController::class, 'update'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/built-in-pages/{page}/{section}/toggle',
    [BuiltInPageAdminController::class, 'toggle'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/contact/messages/{id}/status',
    [BuiltInPageAdminController::class, 'contactMessageStatus'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);

$router->get(
    '/dashboard/roles-permissions',
    [DashboardController::class, 'rolesPermissions'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->post(
    '/dashboard/roles-permissions/{role}',
    [RolePermissionController::class, 'update'],
    [new AuthMiddleware(), new PermissionMiddleware('roles.manage'), new CsrfMiddleware()]
); // BARAMA_RBAC_ROUTES_V135

/* BARAMA_GLOBAL_LOCATION_OPTIONS_ROUTE_V110 */
$router->get(
    '/location-options',
    [\Barama\Http\Controllers\LocationOptionsController::class, 'index']
);

/* BARAMA_LOCATION_PROVIDER_V117A7: authenticated temporary Mapbox search. */
$router->get(
    '/location-provider/suggest',
    [\Barama\Http\Controllers\LocationProviderController::class, 'suggest'],
    [new AuthMiddleware()]
);

/* BARAMA_GLOBAL_LOCATIONS_ROUTES_V101 */
$router->get(
    '/dashboard/settings/locations',
    [WorkspaceSettingsController::class, 'dashboardLocations'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage')]
);
$router->post(
    '/dashboard/settings/locations',
    [WorkspaceSettingsController::class, 'saveDashboardLocations'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage'), new CsrfMiddleware()]
);

$router->get('/dashboard/settings', [WorkspaceSettingsController::class, 'dashboardIndex'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->get('/dashboard/settings/{section}', [WorkspaceSettingsController::class, 'dashboard'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->post('/dashboard/settings/{section}', [WorkspaceSettingsController::class, 'saveDashboard'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);


// BARAMA_POLICY_CENTER_V102
$router->get(
    '/policies',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'index']
);
$router->get(
    '/dashboard/policies',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'adminIndex'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->get(
    '/dashboard/policies/create',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'create'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->get(
    '/dashboard/policies/edit',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'edit'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/policies/create',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'store'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/update',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'update'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/delete',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'delete'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/duplicate',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'duplicate'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);

// BARAMA_POLICY_CATEGORIES_V106
$router->get(
    '/dashboard/policies/categories',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'categories'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->post(
    '/dashboard/policies/categories/create',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'categoryStore'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/categories/update',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'categoryUpdate'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/categories/delete',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'categoryDelete'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/policies/categories/merge',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'categoryMerge'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage'), new CsrfMiddleware()]
);
// /BARAMA_POLICY_CATEGORIES_V106


// BARAMA_POLICY_FEEDBACK_V1022
$router->post(
    '/policies/feedback',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'feedback'],
    [new \Barama\Http\Middleware\CsrfMiddleware()]
);
// /BARAMA_POLICY_FEEDBACK_V1022


// BARAMA_POLICY_FEEDBACK_ANALYTICS_V1023
$router->get(
    '/dashboard/policies/feedback',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'feedbackAnalytics'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
// /BARAMA_POLICY_FEEDBACK_ANALYTICS_V1023


// BARAMA_POLICY_FEEDBACK_DETAIL_V1024
$router->get(
    '/dashboard/policies/feedback/detail',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'feedbackAnalyticsDetail'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
// /BARAMA_POLICY_FEEDBACK_DETAIL_V1024


// BARAMA_POLICY_FEEDBACK_REVIEW_V1025
$router->get(
    '/dashboard/policies/feedback/review',
    [\Barama\Http\Controllers\PolicyCenterController::class, 'feedbackReviewQueue'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
// /BARAMA_POLICY_FEEDBACK_REVIEW_V1025

// /BARAMA_POLICY_CENTER_V102

/* Customer-side license settings: protected by admin and CSRF middleware. */
$router->get(
    '/dashboard/license',
    [LicenseSettingsController::class, 'index'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->post(
    '/dashboard/license/actions',
    [LicenseSettingsController::class, 'action'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

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
// BARAMA_CATALOG_LOCATION_EDIT_V117A9: admin-only, source-preserving edit endpoints.
$router->get(
    '/dashboard/locations/{id}/edit',
    [DashboardController::class, 'editCatalogLocation'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage')]
);
$router->post(
    '/dashboard/locations/{id}',
    [\Barama\Http\Controllers\CatalogLocationEditController::class, 'save'],
    [new AuthMiddleware(), new PermissionMiddleware('location.manage'), new CsrfMiddleware()]
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
$router->get(
    '/dashboard/pages/new',
    [DashboardController::class, 'createPageBuilder'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
);
$router->get(
    '/dashboard/pages/{id}/edit',
    [DashboardController::class, 'editPageBuilder'],
    [new AuthMiddleware(), new PermissionMiddleware('page.manage')]
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
    '/dashboard/companies/{id}',
    [AdminManagementController::class, 'updateCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('company.manage') /* BARAMA_A48D1_COMPANY_GUARD */, new CsrfMiddleware()]
);
$router->post(
    '/dashboard/companies/{id}/delete',
    [AdminManagementController::class, 'deleteCompany'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('company.manage') /* BARAMA_A48D1_COMPANY_GUARD */, new CsrfMiddleware()]
);
$router->post(
    '/dashboard/projects/{id}',
    [AdminManagementController::class, 'updateProject'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('project.manage') /* BARAMA_A48D1_PROJECT_GUARD */, new CsrfMiddleware()]
);
$router->post(
    '/dashboard/projects/{id}/delete',
    [AdminManagementController::class, 'deleteProject'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('project.manage') /* BARAMA_A48D1_PROJECT_GUARD */, new CsrfMiddleware()]
);

$router->post(
    '/dashboard/properties/{id}',
    [AdminManagementController::class, 'updateProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('property.manage') /* BARAMA_A48D1_PROPERTY_GUARD */, new CsrfMiddleware()]
);
$router->post(
    '/dashboard/properties/{id}/delete',
    [AdminManagementController::class, 'deleteProperty'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('property.manage') /* BARAMA_A48D1_PROPERTY_GUARD */, new CsrfMiddleware()]
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

$router->get(
    '/dashboard/users/new',
    [DashboardController::class, 'newUser'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]
);
$router->post(
    '/dashboard/users/create',
    [AdminManagementController::class, 'createCustomer'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);
$router->post(
    '/dashboard/users/{id}/verify',
    [AdminManagementController::class, 'verifyUserEmail'],
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
    [new AuthMiddleware(), new PermissionMiddleware('roles.manage') /* BARAMA_A45_ROLE_MANAGEMENT_ROUTE */, new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/developer-access/reconcile',
    [AdminManagementController::class, 'reconcileDeveloperAccess'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/company-access/downgrade',
    [AdminManagementController::class, 'downgradeCompanyAccount'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

$router->post(
    '/dashboard/users/{id}/sessions/revoke',
    [AdminManagementController::class, 'revokeUserSessions'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]
);

/* BARAMA_SYSTEM_TOOL_V100_ADMIN_ROUTES — Router also enforces ChangeConfirmation on all mutations. */
$toolAuth = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')];
$toolWrite = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()];
$router->get('/dashboard/system/tool', [SystemToolController::class, 'index'], $toolAuth);
$router->get('/dashboard/system/tool/export', [SystemToolController::class, 'export'], $toolAuth);
$router->get('/dashboard/system/tool/download', [SystemToolController::class, 'download'], $toolAuth);
$router->post('/dashboard/system/tool/import', [SystemToolController::class, 'import'], $toolWrite);
$router->post('/dashboard/system/tool/backup', [SystemToolController::class, 'backup'], $toolWrite);
$router->post('/dashboard/system/tool/restore', [SystemToolController::class, 'restore'], $toolWrite);

/* BARAMA_DEMO_IMPORT_V100: the existing router also requires Change Confirmation on protected POSTs. */
$demoAuth = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')];
$demoPost = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()];
$router->get('/dashboard/system/demo-importer', [DemoImporterController::class, 'index'], $demoAuth);
$router->get('/dashboard/system/demo-importer/connection', [DemoImporterController::class, 'connection'], $demoAuth);
$router->get('/dashboard/system/demo-importer/catalog', [DemoImporterController::class, 'catalog'], $demoAuth);
$router->post('/dashboard/system/demo-importer/manual', [DemoImporterController::class, 'manual'], $demoPost);
$router->post('/dashboard/system/demo-importer/auto', [DemoImporterController::class, 'automatic'], $demoPost);
$router->post('/dashboard/system/demo-importer/install', [DemoImporterController::class, 'install'], $demoPost);

/* BARAMA_SYSTEM_CENTER_V100_ADMIN_ROUTES */
$router->get('/dashboard/system/about', [SystemCenterController::class, 'about'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->get('/dashboard/system/updates', [SystemCenterController::class, 'updates'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->get('/dashboard/system/health', [SystemCenterController::class, 'health'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->get('/dashboard/system/auto-updater', [SystemCenterController::class, 'autoUpdater'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
$router->post('/dashboard/system/auto-updater/check-license', [SystemCenterController::class, 'validatePublisherLicense'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/inspect-staged-release', [SystemCenterController::class, 'inspectStagedRelease'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/check-updates', [SystemCenterController::class, 'checkUpdateEntitlement'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/update-now', [SystemCenterController::class, 'requestOneClickUpdate'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->get('/dashboard/system/auto-updater/status', [SystemCenterController::class, 'oneClickStatus'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);
/* Portable customer file-only flow: no root worker, authenticated administrator actions. */
$router->post('/dashboard/system/auto-updater/portable-update-now', [SystemCenterController::class, 'portableBegin'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/portable-install', [SystemCenterController::class, 'portableInstall'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/portable-finish', [SystemCenterController::class, 'portableFinish'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->post('/dashboard/system/auto-updater/portable-recover', [SystemCenterController::class, 'portableRecover'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()]);
$router->get('/dashboard/system/auto-updater/portable-status', [SystemCenterController::class, 'portableStatus'], [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')]);

/* BARAMA_PUBLIC_SYSTEM_STATUS_V1126 */
$router->get('/health', [SystemHealthController::class, 'index']);
$router->get('/health.json', [SystemHealthController::class, 'machine']);

/*
 * Keep this final one-segment route last.
 * All canonical Barama routes above take precedence; remaining root slugs
 * are resolved as managed CMS pages.
 */
$router->get('/{slug}', [CmsController::class, 'show']);
