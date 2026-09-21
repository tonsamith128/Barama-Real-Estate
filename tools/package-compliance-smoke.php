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

$firstPartyCss = array_values(array_filter(
    $entries,
    static fn (string $entry): bool =>
        preg_match('#^assets/css/[^/]+\.css$#i', $entry) === 1
));
$firstPartyJs = array_values(array_filter(
    $entries,
    static fn (string $entry): bool =>
        preg_match('#^assets/js/[^/]+\.js$#i', $entry) === 1
));

if ($firstPartyCss !== ['assets/css/app.css']) {
    throw new RuntimeException(
        'Customer package first-party stylesheet policy is not canonical.'
    );
}

if ($firstPartyJs !== ['assets/js/app.js']) {
    throw new RuntimeException(
        'Customer package first-party JavaScript policy is not canonical.'
    );
}

$allowedHosts = [
    'cdn.jsdelivr.net',
    'getbootstrap.com',
    'github.com',
    'tonsamith.com',
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
        preg_match_all(
            '#https://([A-Za-z0-9.-]+)#i',
            $content,
            $matches
        ) === false
    ) {
        throw new RuntimeException('External-host scan failed for ' . $entry);
    }

    foreach ($matches[1] ?? [] as $host) {
        $host = strtolower((string) $host);
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
