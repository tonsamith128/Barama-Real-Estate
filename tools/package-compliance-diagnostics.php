<?php

declare(strict_types=1);

$zipPath = $argv[1] ?? '';
$mode = $argv[2] ?? '';

if ($zipPath === '' || !is_file($zipPath) || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('Diagnostic customer ZIP is unavailable.');
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    throw new RuntimeException('Diagnostic customer ZIP could not be opened.');
}

$root = dirname(__DIR__);
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (is_string($name)) {
        $entries[] = str_replace('\\', '/', $name);
    }
}

$sourceFiles = static function (string $pattern, string $prefix) use ($root): array {
    $files = glob($root . '/' . $pattern) ?: [];
    $result = array_map(
        static fn (string $file): string => $prefix . basename($file),
        $files
    );
    sort($result, SORT_STRING);
    return $result;
};

switch ($mode) {
    case 'metadata':
        $sourceVersion = trim((string) @file_get_contents($root . '/VERSION'));
        if ($sourceVersion === '') {
            throw new RuntimeException('Source VERSION is missing.');
        }
        $expectedName = 'Barama-Real-Estate-' . $sourceVersion . '.zip';
        if (basename($zipPath) !== $expectedName) {
            throw new RuntimeException('Archive filename does not match VERSION.');
        }
        $checksumPath = $zipPath . '.sha256';
        $hash = hash_file('sha256', $zipPath);
        if (!is_string($hash) || !is_file($checksumPath)) {
            throw new RuntimeException('Checksum metadata is unavailable.');
        }
        if (trim((string) file_get_contents($checksumPath)) !== $hash . '  ' . $expectedName) {
            throw new RuntimeException('Checksum sidecar mismatch.');
        }
        if (trim((string) $zip->getFromName('VERSION')) !== $sourceVersion) {
            throw new RuntimeException('Packaged VERSION mismatch.');
        }
        if ((string) $zip->getArchiveComment() !== 'Barama Real Estate ' . $sourceVersion . ' customer package.') {
            throw new RuntimeException('Archive comment mismatch.');
        }
        $marker = '- Current development line: `' . $sourceVersion . '`';
        if (!str_contains((string) @file_get_contents($root . '/README.md'), $marker)
            || !str_contains((string) $zip->getFromName('README.md'), $marker)) {
            throw new RuntimeException('README version marker mismatch.');
        }
        break;

    case 'structure':
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
            'application/database/migrations/0022_create_contact_messages.php',
        ] as $required) {
            if (!in_array($required, $entries, true)) {
                throw new RuntimeException('Required customer package file is missing.');
            }
        }
        $forbiddenPrefixes = ['.git/', '.github/', 'docs/', 'node_modules/', 'vendor/', 'build/', 'dist/'];
        $forbiddenExact = [
            '.env', '.env.example', 'composer.json', 'composer.lock', 'package.json',
            'package-lock.json', 'pnpm-lock.yaml', 'yarn.lock',
        ];
        foreach ($entries as $entry) {
            foreach ($forbiddenPrefixes as $prefix) {
                if (str_starts_with($entry, $prefix)) {
                    throw new RuntimeException('Development-only path leaked into customer package.');
                }
            }
            if (in_array($entry, $forbiddenExact, true)) {
                throw new RuntimeException('Development manifest/state leaked into customer package.');
            }
            if (preg_match('/(?:^|\/)(?:demo|sample|fixture|fixtures)(?:[\/._-]|$)/i', $entry) === 1) {
                throw new RuntimeException('Demo/sample fixture path leaked into customer package.');
            }
            if (preg_match('/\.(?:log|sql|sqlite|bak|backup|dump)$/i', $entry) === 1) {
                throw new RuntimeException('Runtime/development data leaked into customer package.');
            }
        }
        break;

    case 'mail':
        $readme = (string) $zip->getFromName('README.md');
        $documentation = (string) $zip->getFromName('documentation/index.html');
        $view = (string) $zip->getFromName('application/resources/views/installer/index.php');
        $controller = (string) $zip->getFromName('application/app/Http/Controllers/InstallerController.php');
        foreach ([
            'Step 4 Website includes SMTP',
            'installation is blocked server-side until the exact submitted mail configuration passes a test',
            'SMTP passwords are never repopulated after validation errors',
        ] as $retired) {
            if (str_contains($readme, $retired)) {
                throw new RuntimeException('README contains retired installer mail behavior.');
            }
        }
        foreach ([
            'Step 4 collects website and administrator details only',
            'installation initializes PHP mail as the safe default',
            'SMTP remains supported by the canonical mail transport',
        ] as $marker) {
            if (!str_contains($readme, $marker)) {
                throw new RuntimeException('README mail contract marker is missing.');
            }
        }
        if (!str_contains($documentation, 'Email delivery is intentionally kept out of the installer.')
            || !str_contains($documentation, 'Barama starts with PHP mail')
            || !str_contains($view, 'Email delivery defaults to PHP mail')
            || !str_contains($controller, "'mail_driver' => 'mail'")) {
            throw new RuntimeException('Installer/documentation mail behavior is not in parity.');
        }
        break;

    case 'assets':
        $packageCss = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => preg_match('#^assets/css/[^/]+\.css$#i', $entry) === 1
        ));
        $packageJs = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => preg_match('#^assets/js/[^/]+\.js$#i', $entry) === 1
        ));
        sort($packageCss, SORT_STRING);
        sort($packageJs, SORT_STRING);
        $sourceCss = $sourceFiles('assets/css/*.css', 'assets/css/');
        $sourceJs = $sourceFiles('assets/js/*.js', 'assets/js/');
        if ($packageCss !== $sourceCss || $packageJs !== $sourceJs) {
            throw new RuntimeException('Package asset parity differs from source.');
        }
        break;

    case 'migrations':
        $package = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => preg_match('#^application/database/migrations/\d{4}_[^/]+\.php$#', $entry) === 1
        ));
        sort($package, SORT_STRING);
        $source = $sourceFiles(
            'application/database/migrations/*.php',
            'application/database/migrations/'
        );
        if ($package !== $source) {
            throw new RuntimeException('Package migration parity differs from source.');
        }
        break;

    case 'markers':
        $markers = [
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
            $content = $zip->getFromName($entry);
            if (!is_string($content)) {
                continue;
            }
            foreach ($markers as $marker) {
                if (str_contains($content, $marker)) {
                    throw new RuntimeException('Internal QA marker found in packaged runtime source.');
                }
            }
        }
        break;

    case 'migration-safety':
        foreach ($entries as $entry) {
            if (!str_starts_with($entry, 'application/database/migrations/') || !str_ends_with($entry, '.php')) {
                continue;
            }
            $content = (string) $zip->getFromName($entry);
            if (preg_match(
                '/INSERT\s+(?:IGNORE\s+)?INTO\s+(?:users|companies|projects|properties|listings|inquiries|leads)\b/i',
                $content
            ) === 1) {
                throw new RuntimeException('Production migration seeds customer/demo business data.');
            }
            if (preg_match('/^use\s+(?:PDO|RuntimeException);\s*$/m', $content) === 1) {
                throw new RuntimeException('Production migration contains redundant global import.');
            }
        }
        break;

    case 'hosts':
        $allowedHosts = [
            'cdn.jsdelivr.net',
            'getbootstrap.com',
            'github.com',
            'tonsamith.com',
        ];
        foreach ($entries as $entry) {
            if (str_ends_with($entry, '/')) {
                continue;
            }
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, ['php', 'md', 'css', 'js', 'txt', 'json', 'htaccess'], true)
                && basename($entry) !== '.htaccess') {
                continue;
            }
            $content = $zip->getFromName($entry);
            if (!is_string($content)) {
                continue;
            }
            preg_match_all('#https://([A-Za-z0-9.-]+)#i', $content, $matches);
            foreach ($matches[1] ?? [] as $host) {
                $host = strtolower((string) $host);
                $allowed = false;
                foreach ($allowedHosts as $allowedHost) {
                    if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                        $allowed = true;
                        break;
                    }
                }
                if (!$allowed) {
                    throw new RuntimeException('Undeclared external HTTPS host found in customer ZIP.');
                }
            }
        }
        break;

    case 'contracts':
        $migrationCatalog = (string) $zip->getFromName('application/app/Support/MigrationCatalog.php');
        $installer = (string) $zip->getFromName('application/app/Services/InstallerService.php');
        $upgrade = (string) $zip->getFromName('application/app/Services/UpgradeService.php');
        if (!str_contains($migrationCatalog, '0011_create_workspace_preferences.php')
            || !str_contains($migrationCatalog, '0010_create_workspace_preferences.php')
            || !str_contains($installer, 'MigrationCatalog::appliedLookup')
            || !str_contains($upgrade, 'MigrationCatalog::pending')) {
            throw new RuntimeException('Installer/updater migration compatibility contract is incomplete.');
        }
        $notices = (string) $zip->getFromName('THIRD_PARTY_NOTICES.md');
        $package = (string) $zip->getFromName('CUSTOMER_PACKAGE.md');
        foreach (['Bootstrap 5.3.3', 'MIT License', 'cdn.jsdelivr.net'] as $marker) {
            if (!str_contains($notices, $marker)) {
                throw new RuntimeException('Third-party notice marker is missing.');
            }
        }
        foreach (['does not seed demo companies', 'No Node.js production runtime', 'THIRD_PARTY_NOTICES.md'] as $marker) {
            if (!str_contains($package, $marker)) {
                throw new RuntimeException('Customer package marker is missing.');
            }
        }
        break;

    default:
        throw new InvalidArgumentException('Unknown diagnostic mode.');
}

$zip->close();
fwrite(STDOUT, '[PASS] Package compliance diagnostic: ' . $mode . PHP_EOL);
