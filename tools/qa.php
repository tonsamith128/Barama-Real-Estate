<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, "[PASS] {$message}\n");
};

$fail = static function (string $message) use (&$checks, &$failures): void {
    $checks++;
    $failures[] = $message;
    fwrite(STDERR, "[FAIL] {$message}\n");
};

$required = [
    'index.php',
    'LICENSE.md',
    'THIRD_PARTY_NOTICES.md',
    'CUSTOMER_PACKAGE.md',
    'CHANGELOG.md',
    'documentation/index.html',
    'installer/index.php',
    'updater/index.php',
    'VERSION',
    'application/bootstrap/app.php',
    'application/bootstrap/runtime.php',
    'application/routes/web.php',
    'application/app/Core/Application.php',
    'application/app/Core/Installation.php',
    'application/app/Core/RuntimeEnvironment.php',
    'application/app/Core/Router.php',
    'application/app/Core/Request.php',
    'application/app/Core/Response.php',
    'application/app/Core/View.php',
    'assets/css/app.css',
    'assets/js/app.js',
    'application/app/Core/MiddlewareInterface.php',
    'application/app/Core/Session.php',
    'application/app/Services/AuthService.php',
    'application/app/Services/PasswordResetService.php',
    'application/app/Http/Controllers/AuthController.php',
    'application/app/Http/Controllers/AccountManagementController.php',
    'application/app/Http/Middleware/AuthMiddleware.php',
    'application/app/Http/Middleware/PermissionMiddleware.php',
    'application/database/migrations/0001_create_auth_core.php',
    'application/database/migrations/0002_seed_auth_roles_permissions.php',
    'application/database/migrations/0003_create_real_estate_domain.php',
    'application/database/migrations/0004_seed_real_estate_permissions.php',
    'application/database/migrations/0005_create_domain_workflow_support.php',
    'application/database/migrations/0006_create_customer_engagement.php',
    'application/database/migrations/0007_create_notification_delivery.php',
    'application/database/migrations/0008_create_application_settings.php',
    'application/database/migrations/0009_create_account_onboarding.php',
    'application/database/migrations/0010_release_security_hardening.php',
    'application/database/migrations/0011_create_workspace_preferences.php',
    'application/database/migrations/0012_create_cms_pages.php',
    'application/database/migrations/0013_create_billing_foundation.php',
    'application/database/migrations/0014_create_invoice_transaction_foundation.php',
    'application/database/migrations/0015_create_coupon_discount_foundation.php',
    'application/database/migrations/0016_create_tax_rule_foundation.php',
    'application/database/migrations/0017_create_payment_webhook_foundation.php',
    'application/database/migrations/0018_create_communication_permission.php',
    'application/database/migrations/0019_create_email_template_foundation.php',
    'application/database/migrations/0021_create_newsletter_subscribers.php',
    'application/database/migrations/0022_create_contact_messages.php',
    'application/app/Domain/RealEstate/DomainRules.php',
    'application/app/Repositories/LocationRepository.php',
    'application/app/Repositories/CompanyRepository.php',
    'application/app/Repositories/CompanyMembershipRepository.php',
    'application/app/Repositories/ProjectRepository.php',
    'application/app/Repositories/PropertyRepository.php',
    'application/app/Repositories/ListingRepository.php',
    'application/app/Repositories/WorkspaceReadRepository.php',
    'application/app/Repositories/WorkspaceRecordRepository.php',
    'application/app/Repositories/MarketplaceRepository.php',
    'application/app/Repositories/CustomerEngagementRepository.php',
    'application/app/Repositories/NotificationRepository.php',
    'application/app/Repositories/NewsletterSubscriberRepository.php',
    'application/app/Repositories/SavedSearchAlertRepository.php',
    'application/app/Services/CompanyAccessService.php',
    'application/app/Services/BulkModerationService.php',
    'application/app/Services/CustomerEngagementService.php',
    'application/app/Services/ViewingManagementService.php',
    'application/app/Services/NotificationService.php',
    'application/app/Services/OutboxDeliveryService.php',
    'application/app/Services/SavedSearchAlertService.php',
    'application/app/Services/MailService.php',
    'application/app/Services/InstallerService.php',
    'application/app/Services/UpgradeService.php',
    'application/app/Support/MigrationCatalog.php',
    'application/app/Services/LicenseManagerClient.php',
    'application/app/Services/CompanyManagementService.php',
    'application/app/Services/MembershipService.php',
    'application/app/Services/CatalogManagementService.php',
    'application/app/Services/ProjectManagementService.php',
    'application/app/Services/PropertyManagementService.php',
    'application/app/Services/ListingManagementService.php',
    'application/app/Services/DomainWorkflowService.php',
    'application/app/Services/InquiryService.php',
    'application/app/Services/NewsletterSubscriptionService.php',
    'application/app/Services/LeadManagementService.php',
    'application/app/Services/MediaStorageService.php',
    'application/app/Services/MediaManagementService.php',
    'application/app/Http/Controllers/InstallerController.php',
    'application/app/Http/Controllers/UpgradeController.php',
    'application/app/Http/Controllers/DeveloperManagementController.php',
    'application/app/Http/Controllers/AdminManagementController.php',
    'application/app/Http/Controllers/InquiryController.php',
    'application/app/Http/Controllers/NewsletterController.php',
    'application/app/Http/Controllers/MarketplaceController.php',
    'application/app/Http/Controllers/PublicMediaController.php',
    'docs/WRITE_SERVICES.md',
    'docs/WORKSPACE_UI.md',
    'application/resources/views/account/index.php',
    'application/resources/views/installer/index.php',
    'application/resources/views/updater/index.php',
    'application/resources/views/workspace/index.php',
    'application/resources/views/workspace/record.php',
    'application/resources/views/marketplace/properties.php',
    'application/resources/views/marketplace/property.php',
    'application/resources/views/marketplace/projects.php',
    'application/resources/views/marketplace/project.php',
    'application/resources/views/components/inventory-card.php',
    'docs/MARKETPLACE_UI.md',
    'docs/MANAGEMENT_RECORDS.md',
    'docs/MANAGEMENT_TABLES.md',
    'docs/CUSTOMER_ENGAGEMENT.md',
    'docs/VIEWING_SCHEDULING.md',
    'docs/NOTIFICATIONS.md',
    'docs/INSTALLER.md',
    'docs/DOMAIN_MODEL.md',
    'docs/RELEASE_GATES.md',
    'docs/SECURITY_HARDENING.md',
    'docs/UPGRADE.md',
    'docs/LICENSE_MANAGER_QA.md',
    'docs/BROWSER_QA.md',
    'docs/HOSTING_COMPATIBILITY.md',
    'docs/PACKAGE_COMPLIANCE.md',
    'docs/CODECANYON_SUBMISSION.md',
    'docs/RC1_CHECKLIST.md',
    'docs/RC1_ACCEPTANCE.json',
    'docs/LIVE_SERVER_ACCEPTANCE.md',
    'docs/AAPANEL_ACCEPTANCE_BUNDLE.md',
    '.github/workflows/license-live.yml',
    'tools/migrate.php',
    'tools/schema-smoke.php',
    'tools/domain-write-smoke.php',
    'tools/notification-smoke.php',
    'tools/newsletter-smoke.php',
    'tools/notifications.php',
    'tools/build-package.php',
    'tools/package-smoke.php',
    'tools/account-onboarding-smoke.php',
    'tools/security-hardening-smoke.php',
    'tools/upgrade-smoke-runner.php',
    'tools/upgrade-smoke.php',
    'tools/package-upgrade-smoke.php',
    'tools/license-contract-smoke.php',
    'tools/license-live-smoke.php',
    'tools/http-routing-smoke.php',
    'tools/route-contract-smoke.php',
    'tools/webserver-package-smoke.sh',
    'tools/live-server-preflight.php',
    'tools/smtp-test-server.php',
    'tools/smtp-smoke.php',
    'tools/browser-package-prepare.php',
    'tools/browser-package-smoke.sh',
    'tools/package-compliance-smoke.php',
    'tools/codecanyon-smoke.php',
    'tools/rc1-acceptance-lib.php',
    'tools/rc1-readiness.php',
    'tools/rc1-evidence.php',
    'tools/rc1-evidence-smoke.php',
    'tools/rc1-artifact.php',
    'tools/rc1-artifact-smoke.php',
    'tools/rc1-live-session.php',
    'tools/rc1-live-session-smoke.php',
    'tools/build-acceptance-bundle.php',
    'tools/acceptance-bundle-smoke.php',
    'application/app/Services/AccountSettingsService.php',
    'application/app/Services/EmailVerificationService.php',
    'application/app/Services/SecurityAuditService.php',
    'application/app/Services/UserAdministrationService.php',
    'application/app/Repositories/EmailVerificationRepository.php',
    'application/app/Repositories/SecurityAuditRepository.php',
    'application/app/Security/PasswordPolicy.php',
    'application/app/Security/NewsletterThrottle.php',
    'application/resources/views/auth/verify-email.php',
    'application/app/Services/CompanyOnboardingService.php',
    'application/app/Repositories/AccountSettingsRepository.php',
    'application/config/license.php',
];

foreach ($required as $relative) {
    $file = $root . '/' . $relative;
    is_file($file)
        ? $pass("Required file exists: {$relative}")
        : $fail("Missing required file: {$relative}");
}

$collectFiles = static function (string $directory, string $extension) use ($root): array {
    if (!is_dir($directory)) {
        return [];
    }

    $result = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        if (strtolower($item->getExtension()) !== strtolower($extension)) {
            continue;
        }

        $result[] = str_replace($root . '/', '', $item->getPathname());
    }

    sort($result);
    return $result;
};

$cssFiles = $collectFiles($root . '/assets', 'css');
$jsFiles = $collectFiles($root . '/assets', 'js');

$approvedCssFiles = [
    'assets/css/account-profile-v1131.css',
    'assets/css/account-security-premium-v1129r2.css',
    'assets/css/account-security-premium-v1129r3.css',
    'assets/css/account-security-premium-v1129r4.css',
    'assets/css/account-security-table-v1129r5.css',
    'assets/css/account-settings-full-v1130.css',
    'assets/css/admin-email-template-builder-v137.css',
    'assets/css/admin-email-template-builder-v139.css',
    'assets/css/admin-email-template-builder-v140.css',
    'assets/css/admin-email-template-builder-v142.css',
    'assets/css/admin-email-template-builder-v143.css',
    'assets/css/admin-email-templates-v136.css',
    'assets/css/admin-email-templates-v137.css',
    'assets/css/admin-email-templates-v142.css',
    'assets/css/admin-mail-settings-v100.css',
    'assets/css/admin-global-locations-v101.css',
    'assets/css/admin-global-locations-v110.css',
    'assets/css/admin-roles-permissions-v135.css',
    'assets/css/admin-security-v123.css',
    'assets/css/admin-settings-default-theme-v101.css',
    'assets/css/admin-settings-flat-v130.css',
    'assets/css/admin-settings-security-factors-v132.css',
    'assets/css/admin-settings-security-factors-v133.css',
    'assets/css/admin-settings-security-protection-v134.css',
    'assets/css/admin-settings-security-v131.css',
    'assets/css/admin-settings-v120.css',
    'assets/css/app.css',
    'assets/css/barama-change-confirmation-v1.0.0.css',
    'assets/css/barama-session-expired-v1.0.0.css',
    'assets/css/barama-session-expired-v1.0.1.css',
    'assets/css/catalog-focus-v167.css',
    'assets/css/change-confirmation-v132.css',
    'assets/css/change-confirmation-v133.css',
    'assets/css/cloud-storage-provider-table-v101.css',
    'assets/css/cms-rich-content-v116.css',
    'assets/css/companies-focus-v156.css',
    'assets/css/error-page-v114.css',
    'assets/css/finance-billing-operations-empty-state-v1822.css',
    'assets/css/finance-billing-operations-focus-v1819.css',
    'assets/css/finance-billing-operations-pagination-v1815.css',
    'assets/css/finance-coupons-focus-v185.css',
    'assets/css/finance-coupons-pagination-v184.css',
    'assets/css/finance-invoices-focus-v1827.css',
    'assets/css/finance-invoices-pagination-v1824.css',
    'assets/css/finance-payment-methods-v210.css',
    'assets/css/finance-payment-methods-v230.css',
    'assets/css/finance-payment-native-post-v228.css',
    'assets/css/finance-payment-providers-v200.css',
    'assets/css/finance-payment-providers-webhook-focus-v1813.css',
    'assets/css/finance-payment-providers-webhook-v1811.css',
    'assets/css/finance-plans-focus-v180.css',
    'assets/css/finance-plans-section-cleanup-v145.css',
    'assets/css/finance-plans-typography-v146.css',
    'assets/css/finance-plans-v139.css',
    'assets/css/finance-plans-v140.css',
    'assets/css/finance-subscriptions-focus-v183.css',
    'assets/css/finance-subscriptions-pagination-v182.css',
    'assets/css/finance-taxes-pagination-v186.css',
    'assets/css/finance-transactions-focus-v1832.css',
    'assets/css/finance-transactions-pagination-v1830.css',
    'assets/css/frontend-back-to-top-v102.css',
    'assets/css/global-location-cascade-v110.css',
    'assets/css/hero-search-v1117.css',
    'assets/css/hero-search-v1118.css',
    'assets/css/hero-search-v1122.css',
    'assets/css/hero-search-v1123.css',
    'assets/css/homepage-agent-v1110.css',
    'assets/css/homepage-agent-v1111.css',
    'assets/css/homepage-agent-v1112.css',
    'assets/css/homepage-agent-v114.css',
    'assets/css/homepage-agent-v115.css',
    'assets/css/homepage-agent-v116.css',
    'assets/css/homepage-agent-v119.css',
    'assets/css/invoice-template-logo-upload-v152.css',
    'assets/css/invoice-template-theme-v151.css',
    'assets/css/invoice-template-v150.css',
    'assets/css/leads-focus-v161.css',
    'assets/css/listings-focus-v148.css',
    'assets/css/marketplace-config-v109.css',
    'assets/css/marketplace-hero-heading-v112.css',
    'assets/css/media-library-focus-v145.css',
    'assets/css/media-library-index-v137.css',
    'assets/css/media-library-upload-v134.css',
    'assets/css/media-library-upload-v135.css',
    'assets/css/page-builder-v116.css',
    'assets/css/plans-packages-v140.css',
    'assets/css/plans-packages-v143.css',
    'assets/css/plans-purchase-theme-v144.css',
    'assets/css/policy-center-feedback-analytics-v1.0.23.css',
    'assets/css/policy-center-feedback-detail-v1.0.24.css',
    'assets/css/policy-center-feedback-review-v1.0.25.css',
    'assets/css/policy-center-feedback-v1.0.22.css',
    'assets/css/policy-center-public-v1.0.11.css',
    'assets/css/policy-center-public-v1.0.12.css',
    'assets/css/policy-center-public-v1.0.13.css',
    'assets/css/policy-center-public-v1.0.14.css',
    'assets/css/policy-center-public-v1.0.15.css',
    'assets/css/policy-center-public-v1.0.16.css',
    'assets/css/policy-center-public-v1.0.17.css',
    'assets/css/policy-center-public-v1.0.18.css',
    'assets/css/policy-center-public-v1.0.19.css',
    'assets/css/policy-center-public-v1.0.20.css',
    'assets/css/policy-center-public-v1.0.21.css',
    'assets/css/policy-center-public-v1.0.22.css',
    'assets/css/policy-center-review-decision-v1.0.26.css',
    'assets/css/policy-center-review-impact-v1.0.27.css',
    'assets/css/policy-center-v1.0.10.css',
    'assets/css/policy-center-v1.0.2.css',
    'assets/css/policy-center-v1.0.3.css',
    'assets/css/policy-center-v1.0.4.css',
    'assets/css/policy-center-v1.0.5.css',
    'assets/css/policy-center-v1.0.6.css',
    'assets/css/policy-center-v1.0.7.css',
    'assets/css/policy-center-v1.0.8.css',
    'assets/css/policy-center-v1.0.9.css',
    'assets/css/popular-location-marquee-v1124.css',
    'assets/css/popular-location-marquee-v1125.css',
    'assets/css/projects-focus-v152.css',
    'assets/css/property-detail-flow-v113.css',
    'assets/css/public-header-inline-v1119.css',
    'assets/css/public-header-inline-v1120.css',
    'assets/css/public-header-inline-v1121.css',
    'assets/css/public-location-picker-v112.css',
    'assets/css/public-location-picker-v113.css',
    'assets/css/public-location-picker-v114.css',
    'assets/css/public-location-picker-v115.css',
    'assets/css/public-mobile-v1114.css',
    'assets/css/right-click-protection-v134.css',
    'assets/css/security-center-v125.css',
    'assets/css/security-center-v127.css',
    'assets/css/security-center-v130.css',
    'assets/css/security-center-v131.css',
    'assets/css/security-center-v132.css',
    'assets/css/security-center-v133.css',
    'assets/css/seo-center-v138.css',
    'assets/css/seo-focus-v170.css',
    'assets/css/system-health-v1126.css',
    'assets/css/users-focus-v172.css',
    'assets/css/verification-focus-v174.css',
    'assets/css/viewings-focus-v164.css',
    'assets/css/workspace-sidebar-scroll-v115.css',
];
$unapprovedCssFiles = array_values(array_diff($cssFiles, $approvedCssFiles));

$unapprovedCssFiles === []
    ? $pass('First-party CSS files match the approved asset allowlist.')
    : $fail(
        'Unapproved first-party CSS files detected: '
        . implode(', ', $unapprovedCssFiles)
    );

$approvedJsFiles = [
    'assets/js/account-profile-v1131.js',
    'assets/js/account-profile-v1131r2.js',
    'assets/js/account-security-premium-v1129r2.js',
    'assets/js/account-security-premium-v1129r3.js',
    'assets/js/account-security-premium-v1129r4.js',
    'assets/js/account-settings-full-v1130.js',
    'assets/js/admin-email-template-builder-v137.js',
    'assets/js/admin-email-template-builder-v139.js',
    'assets/js/admin-email-template-builder-v140.js',
    'assets/js/admin-email-template-builder-v142.js',
    'assets/js/admin-email-template-builder-v143.js',
    'assets/js/admin-email-templates-v136.js',
    'assets/js/admin-email-templates-v137.js',
    'assets/js/admin-email-templates-v142.js',
    'assets/js/admin-global-locations-v101.js',
    'assets/js/admin-global-locations-v110.js',
    'assets/js/admin-roles-permissions-v135.js',
    'assets/js/admin-settings-security-factors-v132.js',
    'assets/js/admin-settings-security-factors-v133.js',
    'assets/js/admin-settings-security-protection-v134.js',
    'assets/js/admin-settings-v120.js',
    'assets/js/app.js',
    'assets/js/barama-change-confirmation-v1.0.0.js',
    'assets/js/barama-session-expired-v1.0.0.js',
    'assets/js/change-confirmation-v132.js',
    'assets/js/change-confirmation-v133.js',
    'assets/js/finance-payment-methods-v210.js',
    'assets/js/finance-payment-methods-v211.js',
    'assets/js/finance-payment-methods-v212.js',
    'assets/js/finance-payment-methods-v213.js',
    'assets/js/finance-payment-methods-v214.js',
    'assets/js/finance-payment-methods-v215.js',
    'assets/js/finance-payment-methods-v216.js',
    'assets/js/finance-payment-methods-v217.js',
    'assets/js/finance-payment-methods-v220.js',
    'assets/js/finance-payment-methods-v228.js',
    'assets/js/finance-payment-methods-v230.js',
    'assets/js/finance-payment-providers-v200.js',
    'assets/js/finance-plans-v139.js',
    'assets/js/frontend-back-to-top-v102.js',
    'assets/js/global-location-cascade-v110.js',
    'assets/js/invoice-template-logo-upload-v152.js',
    'assets/js/invoice-template-v150.js',
    'assets/js/marketplace-config-v109.js',
    'assets/js/media-library-index-v137.js',
    'assets/js/media-library-upload-v134.js',
    'assets/js/page-builder-v116.js',
    'assets/js/page-builder-v117.js',
    'assets/js/page-builder-v120.js',
    'assets/js/policy-center-feedback-analytics-v1.0.23.js',
    'assets/js/policy-center-feedback-review-v1.0.25.js',
    'assets/js/policy-center-public-v1.0.11.js',
    'assets/js/policy-center-public-v1.0.12.js',
    'assets/js/policy-center-public-v1.0.13.js',
    'assets/js/policy-center-public-v1.0.19.js',
    'assets/js/policy-center-public-v1.0.20.js',
    'assets/js/policy-center-public-v1.0.21.js',
    'assets/js/policy-center-public-v1.0.22.js',
    'assets/js/policy-center-v1.0.10.js',
    'assets/js/policy-center-v1.0.2.js',
    'assets/js/policy-center-v1.0.3.js',
    'assets/js/policy-center-v1.0.4.js',
    'assets/js/policy-center-v1.0.5.js',
    'assets/js/policy-center-v1.0.6.js',
    'assets/js/policy-center-v1.0.7.js',
    'assets/js/policy-center-v1.0.8.js',
    'assets/js/policy-center-v1.0.9.js',
    'assets/js/public-header-inline-v1121.js',
    'assets/js/public-location-picker-v112.js',
    'assets/js/public-location-picker-v113.js',
    'assets/js/public-location-picker-v114.js',
    'assets/js/public-location-picker-v115.js',
    'assets/js/right-click-protection-v134.js',
    'assets/js/seo-center-v138.js',
    'assets/js/system-health-theme-v1126.js',
    'assets/js/vendor/qrcode-v133.js',
    'assets/js/workspace-sidebar-scroll-v115.js',
];
$unapprovedJsFiles = array_values(array_diff($jsFiles, $approvedJsFiles));

$unapprovedJsFiles === []
    ? $pass('First-party JavaScript files match the approved asset allowlist.')
    : $fail(
        'Unapproved first-party JavaScript files detected: '
        . implode(', ', $unapprovedJsFiles)
    );

$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $item) {
    if (!$item->isFile()) {
        continue;
    }

    $relative = str_replace($root . '/', '', $item->getPathname());

    if (str_starts_with($relative, '.git/')) {
        continue;
    }

    $allFiles[] = $relative;
}

$forbiddenNamePattern = '/(?:^|[\/._-])(old|backup|bak|copy|legacy)(?:[\/._-]|$)/i';
$forbidden = array_values(array_filter(
    $allFiles,
    static fn (string $file): bool => $file !== 'application/database/migrations/0030_update_homepage_hero_copy.php'
        && preg_match($forbiddenNamePattern, $file) === 1
));

$forbidden === []
    ? $pass('No old/backup/copy/legacy source filenames detected.')
    : $fail('Forbidden duplicate/legacy filenames detected: ' . implode(', ', $forbidden));

$phpFiles = array_values(array_filter(
    $allFiles,
    static fn (string $file): bool => str_ends_with(strtolower($file), '.php')
));

sort($phpFiles);

if (!function_exists('exec')) {
    $fail('PHP exec() is unavailable; syntax lint could not run.');
} else {
    foreach ($phpFiles as $relative) {
        $command = escapeshellarg(PHP_BINARY)
            . ' -l '
            . escapeshellarg($root . '/' . $relative)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        $exitCode === 0
            ? $pass("PHP syntax: {$relative}")
            : $fail("PHP syntax error in {$relative}: " . implode(' ', $output));
    }
}

$migrationFiles = glob($root . '/application/database/migrations/*.php') ?: [];
$migrationNames = array_map('basename', $migrationFiles);
$sortedMigrationNames = $migrationNames;
sort($sortedMigrationNames, SORT_STRING);

$migrationNames === $sortedMigrationNames
    ? $pass('Database migrations are stored in deterministic filename order.')
    : $fail('Database migrations must use deterministic ordered filenames.');

count($migrationNames) === count(array_unique($migrationNames))
    ? $pass('Database migration filenames are unique.')
    : $fail('Duplicate database migration filenames detected.');

$expectedMigrationNames = [
    '0001_create_auth_core.php',
    '0002_seed_auth_roles_permissions.php',
    '0003_create_real_estate_domain.php',
    '0004_seed_real_estate_permissions.php',
    '0005_create_domain_workflow_support.php',
    '0006_create_customer_engagement.php',
    '0007_create_notification_delivery.php',
    '0008_create_application_settings.php',
    '0009_create_account_onboarding.php',
    '0010_release_security_hardening.php',
    '0011_create_workspace_preferences.php',
    '0012_create_cms_pages.php',
    '0013_create_billing_foundation.php',
    '0014_create_invoice_transaction_foundation.php',
    '0015_create_coupon_discount_foundation.php',
    '0016_create_tax_rule_foundation.php',
    '0017_create_payment_webhook_foundation.php',
    '0018_create_communication_permission.php',
    '0019_create_email_template_foundation.php',
    '0021_create_newsletter_subscribers.php',
    '0022_create_contact_messages.php',
    '0023_create_user_security_factors.php',
    '0024_create_role_permission_governance.php',
    '0025_create_email_template_localization_history.php',
    '0026_create_email_outbox_event_history.php',
    '0027_create_billing_payment_methods.php',
    '0028_create_global_location_governance.php',
    '0029_create_global_location_hierarchy.php',
    '0030_update_homepage_hero_copy.php',
    '0031_add_account_profile_details.php',
];

$migrationNames === $expectedMigrationNames
    ? $pass('Database migration sequence matches the accepted canonical catalog through 0031.')
    : $fail('Database migration sequence drift detected: ' . implode(', ', $migrationNames));

$migrationPrefixes = array_map(
    static fn (string $name): string => substr($name, 0, 4),
    $migrationNames
);

count($migrationPrefixes) === count(array_unique($migrationPrefixes))
    ? $pass('Database migration numeric prefixes are unique.')
    : $fail('Duplicate numeric migration prefix detected.');

fwrite(
    STDOUT,
    sprintf(
        "\nBarama QA: %d checks, %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
