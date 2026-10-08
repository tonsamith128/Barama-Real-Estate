<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Installation;
use Barama\Core\RuntimeEnvironment;
use Barama\Services\InstallerService;
use Barama\Services\LicenseManagerClient;
use Barama\Support\MigrationCatalog;

$packagePath = $argv[1] ?? '';
if ($packagePath === '' || !is_file($packagePath)) {
    throw new RuntimeException('A valid customer package ZIP is required.');
}

$temporaryRoot = sys_get_temp_dir()
    . '/barama-package-smoke-'
    . bin2hex(random_bytes(6));

if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
    throw new RuntimeException('Unable to create package smoke directory.');
}

$cleanup = static function (string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
};

try {
    $archive = new ZipArchive();
    if ($archive->open($packagePath) !== true) {
        throw new RuntimeException('Unable to open customer package ZIP.');
    }

    if (!$archive->extractTo($temporaryRoot)) {
        $archive->close();
        throw new RuntimeException('Unable to extract customer package ZIP.');
    }
    $archive->close();

    $requiredFiles = [
        '.htaccess',
        'index.php',
        'VERSION',
        'LICENSE.md',
        'THIRD_PARTY_NOTICES.md',
        'CUSTOMER_PACKAGE.md',
        'CHANGELOG.md',
        'documentation/index.html',
        'installer/index.php',
        'updater/index.php',
        'application/bootstrap/app.php',
        'application/bootstrap/runtime.php',
        'application/routes/web.php',
        'application/config/app.php',
        'application/config/auth.php',
        'application/config/database.php',
        'application/config/domain.php',
        'application/config/license.php',
        'application/config/mail.php',
        'application/app/Core/Installation.php',
        'application/app/Core/RuntimeEnvironment.php',
        'application/app/Services/InstallerService.php',
        'application/app/Services/UpgradeService.php',
        'application/app/Services/LicenseManagerClient.php',
        'application/app/Support/MigrationCatalog.php',
        'application/resources/views/installer/index.php',
        'application/resources/views/updater/index.php',
        'assets/css/app.css',
        'assets/js/app.js',
    ];

    foreach ($requiredFiles as $relative) {
        if (!is_file($temporaryRoot . '/' . $relative)) {
            throw new RuntimeException(
                'Package missing required file: ' . $relative
            );
        }
    }

    $migrationFiles = glob(
        $temporaryRoot . '/application/database/migrations/*.php'
    ) ?: [];
    $migrationNames = array_map('basename', $migrationFiles);
    sort($migrationNames, SORT_STRING);

    $expectedMigrations = [
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

    if ($migrationNames !== $expectedMigrations) {
        throw new RuntimeException(
            'Package migration inventory does not match the canonical catalog.'
        );
    }

    // Inspect nested first-party assets and licensed vendor assets as well.
    $assetIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $temporaryRoot . '/assets',
            FilesystemIterator::SKIP_DOTS
        )
    );
    $cssNames = [];
    $jsNames = [];
    foreach ($assetIterator as $asset) {
        if (!$asset->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($asset->getPathname(), strlen($temporaryRoot) + 1));
        if (str_ends_with($relative, '.css')) {
            $cssNames[] = $relative;
        } elseif (str_ends_with($relative, '.js')) {
            $jsNames[] = $relative;
        }
    }
    sort($cssNames, SORT_STRING);
    sort($jsNames, SORT_STRING);

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
    'assets/css/admin-mail-queue-compact-v100.css',
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
    'assets/css/footer-management-v100.css',
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

    $unapprovedCssFiles = array_values(array_diff($cssNames, $approvedCssFiles));
    $unapprovedJsFiles = array_values(array_diff($jsNames, $approvedJsFiles));
    $missingCssFiles = array_values(array_diff($approvedCssFiles, $cssNames));
    $missingJsFiles = array_values(array_diff($approvedJsFiles, $jsNames));

    if (
        !in_array('assets/css/app.css', $cssNames, true)
        || $unapprovedCssFiles !== []
        || $missingCssFiles !== []
    ) {
        throw new RuntimeException(
            'Package CSS inventory contains missing core or unapproved modular assets.'
        );
    }

    if (
        !in_array('assets/js/app.js', $jsNames, true)
        || $unapprovedJsFiles !== []
        || $missingJsFiles !== []
    ) {
        throw new RuntimeException(
            'Package JavaScript inventory contains missing core or unapproved modular assets.'
        );
    }

    $database = (string) (getenv('PACKAGE_DB_DATABASE') ?: 'barama_package_test');
    $host = (string) (getenv('PACKAGE_DB_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('PACKAGE_DB_PORT') ?: 3306);
    $username = (string) (getenv('PACKAGE_DB_USERNAME') ?: 'root');
    $password = (string) (getenv('PACKAGE_DB_PASSWORD') ?: '');

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
    $pdo->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    $pdo->exec(
        'CREATE DATABASE '
        . $quotedDatabase
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $envPath = $temporaryRoot . '/.env';
    $installedPath = $temporaryRoot . '/storage/installed.json';
    $runtimePath = $temporaryRoot . '/storage/runtime';
    $logsPath = $temporaryRoot . '/storage/logs';
    $backupsPath = $temporaryRoot . '/storage/backups';
    $updatesPath = $temporaryRoot . '/storage/updates';
    $bootstrapCache = $temporaryRoot . '/application/bootstrap/cache';

    foreach (
        [
            dirname($installedPath),
            $runtimePath,
            $logsPath,
            $backupsPath,
            $updatesPath,
            $bootstrapCache,
        ] as $directory
    ) {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    if (!defined('BASE_PATH')) {
        define('BASE_PATH', $temporaryRoot);
    }

    require $temporaryRoot . '/application/bootstrap/app.php';

    $viewSource = (string) file_get_contents(
        $temporaryRoot . '/application/resources/views/installer/index.php'
    );

    if (
        preg_match_all('/^\s*[1-5]\s*=>\s*\[/m', $viewSource) !== 5
        || str_contains($viewSource, 'data-open-terms')
        || !str_contains($viewSource, 'data-terms-agreement')
        || !str_contains($viewSource, 'data-accept-terms')
        || !str_contains($viewSource, 'data-license-validate')
        || !str_contains($viewSource, 'data-generate-password')
        || str_contains($viewSource, 'data-mail-test')
        || str_contains($viewSource, 'name="mail_driver"')
        || str_contains($viewSource, 'name="mail_host"')
        || str_contains($viewSource, 'name="mail_password"')
        || !str_contains(
            $viewSource,
            'Email delivery defaults to PHP mail and can be configured later from the application settings.'
        )
        || str_contains($viewSource, 'name="license_method"')
        || str_contains($viewSource, 'data-license-method')
        || str_contains($viewSource, 'Ton Samith License')
        || !str_contains($viewSource, 'Purchase code / license key')
        || !str_contains($viewSource, 'Envato Market License &amp; Terms')
        || !str_contains($viewSource, 'Support from the Author')
        || !str_contains(
            $viewSource,
            'I agree to the Envato license terms, author disclaimer and support terms.'
        )
        || str_contains($viewSource, "<?= \$termsReviewed ? 'checked' : '' ?>")
        || !str_contains($viewSource, 'action="<?= e($installerUrl) ?>"')
        || !str_contains($viewSource, 'data-theme-toggle')
        || !str_contains($viewSource, 'data-installer-language')
        || !str_contains($viewSource, '🇰🇭')
        || !str_contains($viewSource, 'installer-requirements-table')
        || !str_contains($viewSource, 'name="db_mode"')
        || !str_contains($viewSource, 'Existing Database')
        || !str_contains($viewSource, 'Create Database')
        || !str_contains($viewSource, 'Database Server Admin Access')
        || !str_contains($viewSource, 'name="db_admin_username"')
        || !str_contains($viewSource, 'name="db_new_database"')
        || !str_contains($viewSource, 'name="db_new_password_confirmation"')
        || !str_contains($viewSource, 'installer-form__grid--pair')
        || !str_contains($viewSource, 'data-1p-ignore')
        || str_contains($viewSource, 'name="create_database"')
    ) {
        throw new RuntimeException('Five-step installer controls are incomplete.');
    }

    $stylesheetSource = (string) file_get_contents($temporaryRoot . '/assets/css/app.css');
    $javascriptSource = (string) file_get_contents($temporaryRoot . '/assets/js/app.js');

    if (
        !str_contains($stylesheetSource, '@media (max-width: 991.98px)')
        || !str_contains($stylesheetSource, '@media (max-width: 575.98px)')
        || !str_contains($stylesheetSource, ':focus-visible')
        || !str_contains($javascriptSource, 'window.localStorage.setItem(storageKey, next)')
        || !str_contains($javascriptSource, "workspaceOpen?.setAttribute('aria-expanded', 'false')")
        || !str_contains($javascriptSource, "workspaceOpen.setAttribute('aria-expanded', 'true')")
        || !str_contains($stylesheetSource, '.installer-requirements-table')
        || !str_contains($stylesheetSource, '.installer-database-mode')
        || !str_contains($stylesheetSource, '.installer-form__grid--pair')
        || !str_contains($stylesheetSource, 'background: transparent')
        || !str_contains($javascriptSource, '[data-database-mode]')
        || !str_contains($javascriptSource, 'Create Database & Continue')
        || !str_contains($javascriptSource, 'barama-installer-language')
        || str_contains($javascriptSource, 'Send a successful test email before installation.')
        || str_contains($javascriptSource, '[data-mail-test]')
    ) {
        throw new RuntimeException('Responsive or Light/Dark installer behavior is incomplete.');
    }

    $layoutSource = (string) file_get_contents(
        $temporaryRoot . '/application/resources/views/layouts/app.php'
    );
    if (
        !str_contains($layoutSource, 'data-bs-theme="light"')
        || str_contains($layoutSource, 'data-bs-theme="dark"')
    ) {
        throw new RuntimeException('Default application theme must be Light.');
    }

    $licenseClient = new LicenseManagerClient();
    $licenseConfig = $licenseClient->configuration();
    if (
        ($licenseConfig['host'] ?? '') !== 'https://tonsamith.com'
        || ($licenseConfig['product'] ?? '') !== 'barama-real-estate'
        || ($licenseConfig['api_key'] ?? null) !== null
    ) {
        throw new RuntimeException(
            'Customer package License Manager configuration is unsafe.'
        );
    }

    $requirements = (new InstallerService())->requirements();
    $requiredLabels = array_column($requirements, 'label');
    foreach (
        [
            'PHP 8.2 or newer',
            'PDO MySQL',
            'Writable runtime',
            'Core CSS asset',
            'Core JavaScript asset',
        ] as $requiredLabel
    ) {
        if (!in_array($requiredLabel, $requiredLabels, true)) {
            throw new RuntimeException(
                'Package installer requirement missing: ' . $requiredLabel
            );
        }
    }

    $databaseConfig = [
        'driver' => 'mysql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8mb4',
    ];

    $installer = new InstallerService();
    $installer->writeEnvironment([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'http://localhost',
        'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
        'DB_HOST' => $host,
        'DB_PORT' => (string) $port,
        'DB_DATABASE' => $database,
        'DB_USERNAME' => $username,
        'DB_PASSWORD' => $password,
        'MAIL_DRIVER' => 'mail',
        'MAIL_HOST' => '',
        'MAIL_PORT' => '587',
        'MAIL_USERNAME' => '',
        'MAIL_PASSWORD' => '',
        'MAIL_ENCRYPTION' => 'tls',
        'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
        'MAIL_FROM_NAME' => 'Barama Real Estate',
    ]);

    $environment = RuntimeEnvironment::read($envPath);
    if (
        ($environment['APP_ENV'] ?? '') !== 'production'
        || ($environment['APP_DEBUG'] ?? '') !== 'false'
        || ($environment['MAIL_DRIVER'] ?? '') !== 'mail'
    ) {
        throw new RuntimeException('Package environment write failed.');
    }

    Database::reset();
    $installer->testDatabase($databaseConfig);
    $installer->migrate($databaseConfig);

    $pdo = Database::connection();
    $migrationRows = $pdo
        ->query('SELECT migration FROM schema_migrations ORDER BY migration')
        ->fetchAll(PDO::FETCH_COLUMN);
    if ($migrationRows !== $expectedMigrations) {
        throw new RuntimeException(
            'Package migrations did not apply the canonical catalog.'
        );
    }

    $health = $installer->health();
    if (empty($health['healthy'])) {
        throw new RuntimeException('Package installer health check failed.');
    }

    fwrite(
        STDOUT,
        "[PASS] Exact customer package installer, migrations and health verified.\n"
    );
} finally {
    $cleanup($temporaryRoot);
}
