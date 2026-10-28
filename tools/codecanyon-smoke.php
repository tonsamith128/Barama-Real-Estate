<?php

declare(strict_types=1);

$zipPath = $argv[1] ?? '';

if ($zipPath === '' || !is_file($zipPath) || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('Exact customer ZIP is unavailable for CodeCanyon QA.');
}

$zip = new ZipArchive();

if ($zip->open($zipPath) !== true) {
    throw new RuntimeException('Customer package could not be opened for CodeCanyon QA.');
}

$entries = [];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);

    if (is_string($name)) {
        $entries[] = str_replace('\\', '/', $name);
    }
}

foreach ([
    'documentation/index.html',
    'application/app/Services/DemoCustomerReleasePolicyV1.php',
    'LICENSE.md',
    'THIRD_PARTY_NOTICES.md',
    'CUSTOMER_PACKAGE.md',
    'CHANGELOG.md',
] as $required) {
    if (!in_array($required, $entries, true)) {
        throw new RuntimeException(
            'CodeCanyon customer help/compliance file is missing: ' . $required
        );
    }
}

$documentation = (string) $zip->getFromName('documentation/index.html');

foreach ([
    'System requirements',
    'One-click installation',
    'License verification',
    'Accounts and workspaces',
    'Email delivery',
    'Scheduled notification jobs',
    'Upgrade',
    'Third-party assets',
    'Version history',
    'Documentation and support',
] as $marker) {
    if (!str_contains($documentation, $marker)) {
        throw new RuntimeException(
            'Packaged HTML documentation is incomplete: ' . $marker
        );
    }
}

// BARAMA_PACKAGED_CUSTOMER_RESOURCES_V102: validate the exact customer
// archive rather than only source files. Links are external references;
// the help document itself remains fully usable without live networking.
$officialLinks = [
    'Support' => 'https://tonsamith.com/contact',
    'Live Demo' => 'https://baramarealestate.tonsamith.com',
    'Online Documentation' => 'https://docs.tonsamith.com/docs/?=228711-barama-real-estate-property-marketplace-developer-management-php-script',
    'Product Description' => 'https://demo.tonsamith.com/demo/257404-barama-real-estate-property-marketplace-developer-management-php-script',
];
$packageReadme = (string) $zip->getFromName('README.md');
$packageNotes = (string) $zip->getFromName('CUSTOMER_PACKAGE.md');
foreach ($officialLinks as $label => $url) {
    foreach ([
        'documentation/index.html' => $documentation,
        'README.md' => $packageReadme,
        'CUSTOMER_PACKAGE.md' => $packageNotes,
    ] as $file => $content) {
        if (!str_contains($content, $url)) {
            throw new RuntimeException(
                'Customer ZIP official ' . $label . ' link missing in ' . $file
            );
        }
    }
}
foreach ([
    'Signed Live Demo importer',
    '/dashboard/system/demo-importer?mode=auto',
    'publisher-signed package',
    'Downloading and verifying do not themselves install demo content.',
    'Customer-release requirements:',
    'v0.9.26 or newer',
] as $guide) {
    if (!str_contains($documentation, $guide)) {
        throw new RuntimeException(
            'Customer ZIP demo import documentation missing: ' . $guide
        );
    }
}
// A versioned customer package is not proof of marketplace publication.
// Keep the release status explicit until CodeCanyon actually approves the item.
if (!str_contains($documentation, 'not yet published or approved on CodeCanyon')) {
    throw new RuntimeException('Customer release status is missing or presented as already published.');
}

$releaseSource = (string) $zip->getFromName('application/app/Services/DemoCustomerReleasePolicyV1.php');
if (!str_contains($releaseSource, 'FIRST_COMPLETE_DEMO_VERSION') ||
    !str_contains($releaseSource, 'REQUIRED_PUBLIC_POLICIES')) {
    throw new RuntimeException('Signed customer demo release gate missing from final ZIP.');
}

$runtimePrefixes = [
    'application/',
    'installer/',
    'updater/',
    'assets/',
    'documentation/',
];

$hasUnsafePhpShortTag = static function (string $content): bool {
    foreach (token_get_all($content) as $token) {
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_OPEN_TAG) {
            $tag = strtolower(ltrim((string) $token[1]));

            if (!str_starts_with($tag, '<?php')) {
                return true;
            }
        }

        if (
            $token[0] === T_INLINE_HTML
            && preg_match('/<\?(?!xml\b)/i', (string) $token[1]) === 1
        ) {
            return true;
        }
    }

    return false;
};

$violations = [];

foreach ($entries as $entry) {
    if (str_ends_with($entry, '/')) {
        continue;
    }

    $isRuntime = false;

    foreach ($runtimePrefixes as $prefix) {
        if (str_starts_with($entry, $prefix)) {
            $isRuntime = true;
            break;
        }
    }

    if (!$isRuntime && !in_array($entry, ['index.php', '.htaccess'], true)) {
        continue;
    }

    $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

    if (
        !in_array($extension, ['php', 'html', 'css', 'js'], true)
        && basename($entry) !== '.htaccess'
    ) {
        continue;
    }

    $content = $zip->getFromName($entry);

    if (!is_string($content)) {
        continue;
    }

    if (
        in_array($extension, ['php', 'html'], true)
        && preg_match('/\sstyle\s*=\s*["\']/i', $content) === 1
    ) {
        $violations[] = 'Inline CSS style attribute detected in shipped markup: ' . $entry;
    }

    if (
        in_array($extension, ['php', 'html'], true)
        && preg_match('/\son[a-z]+\s*=\s*["\']/i', $content) === 1
    ) {
        $violations[] = 'Inline JavaScript event handler detected in shipped markup: ' . $entry;
    }

    if (
        in_array($extension, ['php', 'js'], true)
        && preg_match('/\beval\s*\(/i', $content) === 1
    ) {
        $violations[] = 'Runtime eval() usage detected: ' . $entry;
    }

    if ($extension === 'php' && $hasUnsafePhpShortTag($content)) {
        $violations[] = 'Unsafe PHP short tag detected: ' . $entry;
    }

    if (
        $extension === 'php'
        && preg_match('/\b(?:var_dump|print_r)\s*\(/i', $content) === 1
    ) {
        $violations[] = 'Runtime debug dump detected: ' . $entry;
    }
}

$zip->close();

if ($violations !== []) {
    throw new RuntimeException(
        "CodeCanyon package compliance failures:\n- " . implode("\n- ", $violations)
    );
}

fwrite(
    STDOUT,
    '[PASS] CodeCanyon exact-package QA: HTML help file, installation/use/upgrade '
    . 'documentation, credits, no inline CSS/JS handlers, no eval(), no unsafe '
    . 'short tags and no runtime debug dumps verified.'
    . PHP_EOL
);
