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
    'VERSION',
    'application/bootstrap/app.php',
    'application/routes/web.php',
    'application/app/Core/Application.php',
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
    'application/app/Http/Middleware/AuthMiddleware.php',
    'application/app/Http/Middleware/PermissionMiddleware.php',
    'application/database/migrations/0001_create_auth_core.php',
    'application/database/migrations/0002_seed_auth_roles_permissions.php',
    'application/database/migrations/0003_create_real_estate_domain.php',
    'application/database/migrations/0004_seed_real_estate_permissions.php',
    'application/app/Domain/RealEstate/DomainRules.php',
    'application/app/Repositories/LocationRepository.php',
    'application/app/Repositories/CompanyRepository.php',
    'application/app/Repositories/CompanyMembershipRepository.php',
    'application/app/Repositories/ProjectRepository.php',
    'application/app/Repositories/PropertyRepository.php',
    'application/app/Repositories/ListingRepository.php',
    'application/app/Services/CompanyAccessService.php',
    'docs/DOMAIN_MODEL.md',
    'tools/migrate.php',
    'tools/schema-smoke.php',
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

fwrite(
    STDOUT,
    sprintf(
        "\nBarama QA: %d checks, %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
