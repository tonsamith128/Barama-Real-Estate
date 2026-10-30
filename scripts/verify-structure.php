<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$required = [
    'composer.json',
    'artisan',
    'bootstrap/app.php',
    'public/index.php',
    'config/app.php',
    'config/auth.php',
    'config/database.php',
    'config/session.php',
    'app/Models/User.php',
    'app/Models/Company.php',
    'app/Models/Project.php',
    'app/Models/Property.php',
    'app/Models/Listing.php',
    'app/Models/CompanyMembership.php',
    'app/Domain/RealEstate/MarketplaceStatus.php',
    'app/Http/Controllers/Frontend/Properties/PropertyController.php',
    'app/Http/Controllers/Frontend/Companies/CompanyController.php',
    'app/Http/Controllers/Frontend/Projects/ProjectController.php',
    'app/Http/Controllers/Developer/Dashboard/DeveloperDashboardController.php',
    'app/Services/Marketplace/PublishedListingSearch.php',
    'app/Services/Marketplace/PublishedCompanySearch.php',
    'app/Services/Marketplace/PublishedProjectSearch.php',
    'app/Services/Developer/AccessibleCompanies.php',
    'database/migrations/2026_10_30_000001_create_marketplace_tables.php',
    'database/migrations/2026_10_30_000002_create_company_memberships.php',
    'tests/Feature/Marketplace/PublicPropertyBrowseTest.php',
    'tests/Feature/Marketplace/PublicCompanyProjectTest.php',
    'tests/Feature/Developer/CompanyAccessTest.php',
    'app/Http/Controllers/Frontend/Home/HomeController.php',
    'app/Http/Controllers/Auth/Login/LoginController.php',
    'app/Http/Controllers/Auth/Register/RegisterController.php',
    'app/Http/Controllers/Auth/ForgotPassword/ForgotPasswordController.php',
    'app/Http/Controllers/Auth/ResetPassword/ResetPasswordController.php',
    'app/Http/Controllers/Auth/ConfirmEmail/EmailVerificationController.php',
    'app/Http/Controllers/Account/Dashboard/DashboardController.php',
    'database/migrations/2026_10_30_000000_create_users_and_password_reset_tokens.php',
    'routes/web.php',
    'routes/frontend.php',
    'routes/auth.php',
    'routes/account.php',
    'resources/views/frontend/home/index.blade.php',
    'resources/views/auth/login/index.blade.php',
    'resources/views/auth/register/index.blade.php',
    'resources/views/auth/forgot-password/index.blade.php',
    'resources/views/auth/reset-password/index.blade.php',
    'resources/views/auth/confirm-email/index.blade.php',
    'resources/css/frontend/app.css',
    'resources/css/admin/app.css',
    'tests/Feature/Auth/AuthenticationFlowTest.php',
    'tests/Feature/Auth/EmailVerificationTest.php',
    'tests/Feature/Auth/PasswordResetTest.php',
];

$errors = [];
foreach ($required as $path) {
    if (!is_file($root.'/'.$path)) {
        $errors[] = 'Missing: '.$path;
    }
}

$cssFiles = [];
$cssPath = $root.'/resources/css';
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssPath, FilesystemIterator::SKIP_DOTS),
);
foreach ($iter as $entry) {
    if ($entry->isFile() && strtolower($entry->getExtension()) === 'css') {
        $cssFiles[] = substr($entry->getPathname(), strlen($root) + 1);
    }
}
sort($cssFiles);
$expected = ['resources/css/admin/app.css', 'resources/css/frontend/app.css'];
if ($cssFiles !== $expected) {
    $errors[] = 'Unexpected CSS source files: '.json_encode($cssFiles);
}

foreach (['frontend', 'admin'] as $area) {
    $path = $root.'/resources/css/'.$area.'/app.css';
    if (is_file($path)) {
        $contents = (string) file_get_contents($path);
        if (!preg_match('/\/\* ===== '.strtoupper($area).' \/ 01 \/ [A-Z ]+ ===== \*\//', $contents)) {
            $errors[] = 'Missing structured section headers: '.$area;
        }
        if (str_contains($contents, '@import')) {
            $errors[] = 'CSS must be self-contained: '.$area;
        }
    }
}

if (is_dir($root.'/app/Modules')) {
    $errors[] = 'Duplicate custom Modules hierarchy remains present';
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
if (($composer['require']['laravel/framework'] ?? null) !== '^13.0') {
    $errors[] = 'Incorrect Laravel major requirement';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
fwrite(STDOUT, "LARAVEL_NATIVE_STRUCTURE_PASS\n");
