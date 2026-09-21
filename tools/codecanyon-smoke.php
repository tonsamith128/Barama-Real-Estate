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
    'LICENSE.md',
    'THIRD_PARTY_NOTICES.md',
    'CUSTOMER_PACKAGE.md',
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
    'Documentation and support',
] as $marker) {
    if (!str_contains($documentation, $marker)) {
        throw new RuntimeException(
            'Packaged HTML documentation is incomplete: ' . $marker
        );
    }
}

$runtimePrefixes = [
    'application/',
    'installer/',
    'updater/',
    'assets/',
    'documentation/',
];

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
        throw new RuntimeException(
            'Inline CSS style attribute detected in shipped markup: ' . $entry
        );
    }

    if (
        in_array($extension, ['php', 'html'], true)
        && preg_match('/\son[a-z]+\s*=\s*["\']/i', $content) === 1
    ) {
        throw new RuntimeException(
            'Inline JavaScript event handler detected in shipped markup: ' . $entry
        );
    }

    if (
        in_array($extension, ['php', 'js'], true)
        && preg_match('/\beval\s*\(/i', $content) === 1
    ) {
        throw new RuntimeException(
            'Runtime eval() usage detected: ' . $entry
        );
    }

    if (
        $extension === 'php'
        && preg_match('/<\?(?!php|=)/i', $content) === 1
    ) {
        throw new RuntimeException(
            'Unsafe PHP short tag detected: ' . $entry
        );
    }

    if (
        $extension === 'php'
        && preg_match('/\b(?:var_dump|print_r)\s*\(/i', $content) === 1
    ) {
        throw new RuntimeException(
            'Runtime debug dump detected: ' . $entry
        );
    }
}

$zip->close();

fwrite(
    STDOUT,
    '[PASS] CodeCanyon exact-package QA: HTML help file, installation/use/upgrade '
    . 'documentation, credits, no inline CSS/JS handlers, no eval(), no unsafe '
    . 'short tags and no runtime debug dumps verified.'
    . PHP_EOL
);
