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
    'application/database/migrations/0021_create_newsletter_subscribers.php',
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

$cssFiles === ['assets/css/app.css']
    ? $pass('Exactly one first-party CSS file is present.')
    : $fail('First-party CSS must remain canonical: assets/css/app.css only.');

$jsFiles === ['assets/js/app.js']
    ? $pass('Exactly one first-party JavaScript file is present.')
    : $fail('First-party JavaScript must remain canonical: assets/js/app.js only.');

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
    static fn (string $file): bool => preg_match($forbiddenNamePattern, $file) === 1
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
];

$migrationNames === $expectedMigrationNames
    ? $pass('Database migration sequence matches canonical 0001-0011.')
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
