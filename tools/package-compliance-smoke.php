<?php

declare(strict_types=1);

$zipPath = $argv[1] ?? '';

if ($zipPath === '' || !is_file($zipPath) || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('Exact customer ZIP is unavailable for compliance QA.');
}

$zip = new ZipArchive();

if ($zip->open($zipPath) !== true) {
    throw new RuntimeException('Customer package could not be opened for compliance QA.');
}

$root = dirname(__DIR__);
require_once __DIR__ . '/installer-asset-contract.php';

$installerSource = $zip->getFromName(
    'application/app/Services/InstallerService.php'
);
if (!is_string($installerSource)) {
    throw new RuntimeException('Customer ZIP does not contain InstallerService.');
}
baramaAssertInstallerCanonicalAssets($installerSource);

foreach ([
    'application/app/Services/Updates/TrustedReleaseFeed.php',
    'application/app/Services/Updates/SignedReleaseInspector.php',
    'application/app/Services/Updates/PrivateArtifactHttpClientV2.php',
    'application/app/Services/Updates/PrivateArtifactReceiverV2.php',
    'application/app/Http/Controllers/SystemCenterController.php',
] as $updateComponent) {
    if (!is_string($zip->getFromName($updateComponent))) {
        throw new RuntimeException('V2 updater component missing from customer ZIP: ' . $updateComponent);
    }
}
$sourceVersion = trim((string) @file_get_contents($root . '/VERSION'));

if (
    $sourceVersion === ''
    || preg_match(
        '/^(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/',
        $sourceVersion
    ) !== 1
) {
    throw new RuntimeException('Source VERSION is missing or invalid.');
}

$expectedArchiveName = 'Barama-Real-Estate-' . $sourceVersion . '.zip';

if (basename($zipPath) !== $expectedArchiveName) {
    throw new RuntimeException(
        'Customer package filename does not match source VERSION: expected '
        . $expectedArchiveName
        . ', received '
        . basename($zipPath)
    );
}

$checksumPath = $zipPath . '.sha256';

if (!is_file($checksumPath)) {
    throw new RuntimeException(
        'Customer package SHA-256 sidecar is missing: ' . basename($checksumPath)
    );
}

$actualHash = hash_file('sha256', $zipPath);

if (!is_string($actualHash)) {
    throw new RuntimeException('Customer package SHA-256 could not be calculated.');
}

$expectedChecksumLine = $actualHash . '  ' . $expectedArchiveName;
$actualChecksumLine = trim((string) file_get_contents($checksumPath));

if (!hash_equals($expectedChecksumLine, $actualChecksumLine)) {
    throw new RuntimeException(
        'Customer package SHA-256 sidecar does not match the exact ZIP.'
    );
}

$packageVersion = trim((string) $zip->getFromName('VERSION'));

if ($packageVersion !== $sourceVersion) {
    throw new RuntimeException(
        'Packaged VERSION does not match source VERSION: source '
        . $sourceVersion
        . ', package '
        . ($packageVersion !== '' ? $packageVersion : '[missing]')
    );
}

$expectedArchiveComment = 'Barama Real Estate '
    . $sourceVersion
    . ' customer package.';
$archiveComment = (string) $zip->getArchiveComment();

if ($archiveComment !== $expectedArchiveComment) {
    throw new RuntimeException(
        'Customer ZIP archive comment does not match source VERSION.'
    );
}

$versionMarker = '- Current development line: `' . $sourceVersion . '`';
$sourceReadme = (string) @file_get_contents($root . '/README.md');
$packageReadme = (string) $zip->getFromName('README.md');

if (
    !str_contains($sourceReadme, $versionMarker)
    || !str_contains($packageReadme, $versionMarker)
) {
    throw new RuntimeException(
        'README current development line does not match source VERSION.'
    );
}

$packageDocumentation = (string) $zip->getFromName(
    'documentation/index.html'
);
$packageInstallerView = (string) $zip->getFromName(
    'application/resources/views/installer/index.php'
);
$packageInstallerController = (string) $zip->getFromName(
    'application/app/Http/Controllers/InstallerController.php'
);

foreach ([
    'Step 4 Website includes SMTP',
    'installation is blocked server-side until the exact submitted mail configuration passes a test',
    'SMTP passwords are never repopulated after validation errors',
] as $retiredMailClaim) {
    if (str_contains($packageReadme, $retiredMailClaim)) {
        throw new RuntimeException(
            'README contains retired installer mail behavior: '
            . $retiredMailClaim
        );
    }
}

foreach ([
    'Step 4 collects website and administrator details only',
    'installation initializes PHP mail as the safe default',
    'SMTP remains supported by the canonical mail transport',
] as $readmeMailMarker) {
    if (!str_contains($packageReadme, $readmeMailMarker)) {
        throw new RuntimeException(
            'README mail setup is not aligned with the current installer: '
            . $readmeMailMarker
        );
    }
}

if (
    !str_contains(
        $packageDocumentation,
        'Email delivery is intentionally kept out of the installer.'
    )
    || !str_contains(
        $packageDocumentation,
        'Barama starts with PHP mail'
    )
    || !str_contains(
        $packageInstallerView,
        'Email delivery defaults to PHP mail'
    )
    || !str_contains(
        $packageInstallerController,
        "'mail_driver' => 'mail'"
    )
) {
    throw new RuntimeException(
        'Installer/README/HTML documentation mail behavior is not in parity.'
    );
}

$entries = [];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);

    if (is_string($name)) {
        $entries[] = str_replace('\\', '/', $name);
    }
}

foreach ([
    'LICENSE.md',
    'THIRD_PARTY_NOTICES.md',
    'CUSTOMER_PACKAGE.md',
    'CHANGELOG.md',
    'documentation/index.html',
    'README.md',
    'VERSION',
    'application/app/Support/MigrationCatalog.php',
    'application/database/migrations/0010_release_security_hardening.php',
    'application/database/migrations/0011_create_workspace_preferences.php',
] as $required) {
    if (!in_array($required, $entries, true)) {
        throw new RuntimeException('Customer package compliance file is missing: ' . $required);
    }
}

$forbiddenPrefixes = [
    '.git/',
    '.github/',
    'docs/',
    'node_modules/',
    'vendor/',
    'build/',
    'dist/',
];

$forbiddenExact = [
    '.env',
    '.env.example',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'pnpm-lock.yaml',
    'yarn.lock',
];

foreach ($entries as $entry) {
    foreach ($forbiddenPrefixes as $prefix) {
        if (str_starts_with($entry, $prefix)) {
            throw new RuntimeException(
                'Customer package contains development-only path: ' . $entry
            );
        }
    }

    if (in_array($entry, $forbiddenExact, true)) {
        throw new RuntimeException(
            'Customer package contains development manifest/state: ' . $entry
        );
    }

    if (
        preg_match(
            '/(?:^|\/)(?:demo|sample|fixture|fixtures)(?:[\/._-]|$)/i',
            $entry
        ) === 1
    ) {
        throw new RuntimeException(
            'Customer package contains demo/sample fixture path: ' . $entry
        );
    }

    if (
        preg_match('/\.(?:log|sql|sqlite|bak|backup|dump)$/i', $entry) === 1
    ) {
        throw new RuntimeException(
            'Customer package contains runtime/development data: ' . $entry
        );
    }
}

$sourcePaths = static function (
    string $pattern,
    string $prefix
) use ($root): array {
    $files = glob($root . '/' . $pattern) ?: [];
    $result = array_map(
        static fn (string $file): string => $prefix . basename($file),
        $files
    );
    sort($result, SORT_STRING);

    return $result;
};

$packageCss = array_values(array_filter(
    $entries,
    static fn (string $entry): bool =>
        preg_match('#^assets/css/[^/]+\.css$#i', $entry) === 1
));
$packageJs = array_values(array_filter(
    $entries,
    static fn (string $entry): bool =>
        preg_match('#^assets/js/[^/]+\.js$#i', $entry) === 1
));
sort($packageCss, SORT_STRING);
sort($packageJs, SORT_STRING);

$sourceCss = $sourcePaths('assets/css/*.css', 'assets/css/');
$sourceJs = $sourcePaths('assets/js/*.js', 'assets/js/');

if ($packageCss !== $sourceCss) {
    throw new RuntimeException(
        'Customer package stylesheet inventory does not match canonical source.'
    );
}

if ($packageJs !== $sourceJs) {
    throw new RuntimeException(
        'Customer package JavaScript inventory does not match canonical source.'
    );
}

$packageMigrations = array_values(array_filter(
    $entries,
    static fn (string $entry): bool =>
        preg_match(
            '#^application/database/migrations/\d{4}_[^/]+\.php$#',
            $entry
        ) === 1
));
sort($packageMigrations, SORT_STRING);

$sourceMigrations = $sourcePaths(
    'application/database/migrations/*.php',
    'application/database/migrations/'
);

if ($packageMigrations !== $sourceMigrations) {
    throw new RuntimeException(
        'Customer package migration inventory does not match canonical source.'
    );
}

$migrationPrefixes = array_map(
    static fn (string $entry): string => substr(basename($entry), 0, 4),
    $packageMigrations
);

if (count($migrationPrefixes) !== count(array_unique($migrationPrefixes))) {
    throw new RuntimeException(
        'Customer package contains duplicate numeric migration prefixes.'
    );
}

if (in_array(
    'application/database/migrations/0010_create_workspace_preferences.php',
    $entries,
    true
)) {
    throw new RuntimeException(
        'Historical duplicate 0010 workspace migration is still packaged.'
    );
}

$allowedHosts = [
    // The official Cloudflare Turnstile widget is used by the built-in admin security test.
    'challenges.cloudflare.com',
    'dash.cloudflare.com', // Host-linked admin setup guidance for Turnstile.
    'api.mapbox.com', // Barama's first-party Mapbox location search integration.
    'cdn.jsdelivr.net',
    'codecanyon.net',
    'getbootstrap.com',
    'github.com',
    'tonsamith.com',
    'youtube.com',
    'youtube-nocookie.com',
];
$reservedExampleHosts = [
    'example.com',
    'example.net',
    'example.org',
];

$foundHosts = [];
$testMarkers = [
    'root_test_password',
    'barama_test_password',
    'package-qa@example.test',
    'upgrade-admin@example.test',
    'BARAMA-CONTRACT-TEST',
    'Barama!Package2026Strong',
];

foreach ($entries as $entry) {
    if (str_ends_with($entry, '/')) {
        continue;
    }

    $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

    if (
        !in_array(
            $extension,
            ['php', 'md', 'css', 'js', 'txt', 'json', 'htaccess'],
            true
        )
        && basename($entry) !== '.htaccess'
    ) {
        continue;
    }

    $content = $zip->getFromName($entry);

    if (!is_string($content)) {
        continue;
    }

    foreach ($testMarkers as $marker) {
        if (str_contains($content, $marker)) {
            throw new RuntimeException(
                'Customer package contains internal QA/test marker in '
                . $entry
                . ': '
                . $marker
            );
        }
    }

    if (
        str_starts_with(
            $entry,
            'application/database/migrations/'
        )
        && preg_match(
            '/INSERT\s+(?:IGNORE\s+)?INTO\s+'
            . '(?:users|companies|projects|properties|listings|inquiries|leads)\b/i',
            $content
        ) === 1
    ) {
        throw new RuntimeException(
            'Production migration appears to seed customer/demo business data: '
            . $entry
        );
    }

    if (
        str_starts_with(
            $entry,
            'application/database/migrations/'
        )
        && preg_match('/^use\s+(?:PDO|RuntimeException);\s*$/m', $content) === 1
    ) {
        throw new RuntimeException(
            'Production migration contains redundant global namespace import: '
            . $entry
        );
    }

    if (
        preg_match_all(
            '#https://((?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)#i',
            $content,
            $matches
        ) === false
    ) {
        throw new RuntimeException('External-host scan failed for ' . $entry);
    }

    foreach ($matches[1] ?? [] as $host) {
        $host = strtolower((string) $host);

        if (
            in_array($host, $reservedExampleHosts, true)
            || str_ends_with($host, '.test')
        ) {
            continue;
        }

        $foundHosts[$host] = true;
        $allowed = false;

        foreach ($allowedHosts as $allowedHost) {
            if (
                $host === $allowedHost
                || str_ends_with($host, '.' . $allowedHost)
            ) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new RuntimeException(
                'Customer package contains undeclared external HTTPS host: '
                . $host
                . ' in '
                . $entry
            );
        }
    }
}

$migrationCatalog = (string) $zip->getFromName(
    'application/app/Support/MigrationCatalog.php'
);
$installerMigrationContract = (string) $zip->getFromName(
    'application/app/Services/InstallerService.php'
);
$upgradeMigrationContract = (string) $zip->getFromName(
    'application/app/Services/UpgradeService.php'
);

if (
    !str_contains($migrationCatalog, '0011_create_workspace_preferences.php')
    || !str_contains($migrationCatalog, '0010_create_workspace_preferences.php')
    || !str_contains($installerMigrationContract, 'MigrationCatalog::appliedLookup')
    || !str_contains($upgradeMigrationContract, 'MigrationCatalog::pending')
) {
    throw new RuntimeException(
        'Customer package installer/updater migration compatibility contract is incomplete.'
    );
}

$notices = (string) $zip->getFromName('THIRD_PARTY_NOTICES.md');
$package = (string) $zip->getFromName('CUSTOMER_PACKAGE.md');

foreach ([
    'Bootstrap 5.3.3',
    'MIT License',
    'cdn.jsdelivr.net',
] as $noticeMarker) {
    if (!str_contains($notices, $noticeMarker)) {
        throw new RuntimeException(
            'Third-party notices are incomplete: ' . $noticeMarker
        );
    }
}

foreach ([
    'does not seed demo companies',
    'No Node.js production runtime',
    'THIRD_PARTY_NOTICES.md',
] as $packageMarker) {
    if (!str_contains($package, $packageMarker)) {
        throw new RuntimeException(
            'Customer package manifest is incomplete: ' . $packageMarker
        );
    }
}

$zip->close();

ksort($foundHosts);

fwrite(
    STDOUT,
    '[PASS] Exact customer ZIP compliance verified: notices, clean production '
    . 'defaults, no demo inventory/test credentials/dev dependencies, canonical '
    . 'assets and declared external hosts ['
    . implode(', ', array_keys($foundHosts))
    . '].'
    . PHP_EOL
);
