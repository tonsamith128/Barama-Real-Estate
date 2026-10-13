<?php

declare(strict_types=1);

/**
 * Fail closed if the packaged five-step installer would reject approved
 * module-specific CSS or JavaScript merely because additional files exist.
 * Both Requirements and System Health must verify the two canonical files.
 */
function baramaAssertInstallerCanonicalAssets(string $source): void
{
    foreach ([
        'Canonical stylesheet' => 'assets/css/app.css',
        'Canonical JavaScript' => 'assets/js/app.js',
    ] as $label => $relative) {
        $literalLabel = preg_quote("'" . $label . "'", '~');
        $literalPath = preg_quote("'/" . $relative . "'", '~');
        $check = 'is_file\s*\(\s*BASE_PATH\s*\.\s*'
            . $literalPath
            . '\s*\)\s*&&\s*is_readable\s*\(\s*BASE_PATH\s*\.\s*'
            . $literalPath
            . '\s*\)';

        if (preg_match_all('~' . $literalLabel . '\s*,\s*' . $check . '~s', $source) !== 2) {
            throw new RuntimeException(
                'Customer installer asset check is incompatible with modular assets: '
                . $label . ' (' . $relative . ').'
            );
        }
    }
}
