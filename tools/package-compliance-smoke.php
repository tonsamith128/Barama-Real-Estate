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
