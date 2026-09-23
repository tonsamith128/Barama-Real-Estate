<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$recordPath = $root . '/docs/RC1_ACCEPTANCE.json';
$versionPath = $root . '/VERSION';
$strictRequested = in_array('--strict', $argv, true);

$failures = [];
$passes = 0;

$pass = static function (string $message) use (&$passes): void {
    $passes++;
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

if (!is_file($recordPath) || !is_readable($recordPath)) {
    $fail('RC1 acceptance record is missing or unreadable.');
} else {
    $decoded = json_decode(
        (string) file_get_contents($recordPath),
        true
    );

    if (!is_array($decoded)) {
        $fail('RC1 acceptance record is not valid JSON.');
    } else {
        $required = [
            'automated' => [
                'foundation_qa',
                'exact_package_webserver_qa',
                'exact_package_browser_accessibility_qa',
            ],
            'live_server' => [
                'direct_license_activation',
                'envato_purchase_activation',
                'apache_installer_acceptance',
                'nginx_installer_acceptance',
                'route_fallback_acceptance',
                'administrator_auth_workspace_acceptance',
                'disposable_customer_lifecycle_acceptance',
                'production_mail_delivery',
                'updater_acceptance',
            ],
            'human' => [
                'desktop_tablet_mobile_visual_review',
                'light_dark_visual_review',
                'keyboard_focus_review',
                'english_content_review',
                'khmer_content_review',
                'chinese_content_review',
                'safari_ios_acceptance',
                'edge_chrome_acceptance',
                'customer_html_help_review',
                'online_documentation_parity_review',
                'codecanyon_package_submission_review',
            ],
        ];

        if (($decoded['schema_version'] ?? null) !== 1) {
            $fail('RC1 acceptance schema_version must be 1.');
        } else {
            $pass('RC1 acceptance schema version is supported.');
        }

        if (($decoded['target_version'] ?? '') !== '1.0.0-RC1') {
            $fail('RC1 acceptance target_version must be 1.0.0-RC1.');
        } else {
            $pass('RC1 acceptance target version is canonical.');
        }

        $sourceVersion = trim(
            (string) @file_get_contents($versionPath)
        );
        $strict = $strictRequested || $sourceVersion === '1.0.0-RC1';
        $pending = 0;
        $gateCount = 0;

        foreach ($required as $group => $gates) {
            $actualGroup = $decoded[$group] ?? null;

            if (!is_array($actualGroup)) {
                $fail('Missing RC1 acceptance group: ' . $group);
                continue;
            }

            foreach ($gates as $gate) {
                $gateCount++;
                $entry = $actualGroup[$gate] ?? null;

                if (!is_array($entry)) {
                    $fail(
                        'Missing RC1 acceptance gate: '
                        . $group
                        . '.'
                        . $gate
                    );
                    continue;
                }

                $status = strtolower(trim(
                    (string) ($entry['status'] ?? '')
                ));
                $evidence = trim(
                    (string) ($entry['evidence'] ?? '')
                );

                if (!in_array($status, ['pending', 'pass'], true)) {
                    $fail(
                        'Invalid RC1 gate status for '
                        . $group
                        . '.'
                        . $gate
                        . '.'
                    );
                    continue;
                }

                if ($status === 'pass') {
                    if ($evidence === '') {
                        $fail(
                            'Passed RC1 gate requires non-secret evidence: '
                            . $group
                            . '.'
                            . $gate
                        );
                    } else {
                        $pass(
                            'RC1 gate evidence recorded: '
                            . $group
                            . '.'
                            . $gate
                        );
                    }

                    continue;
                }

                $pending++;

                if ($strict) {
                    $fail(
                        'RC1 gate is still pending: '
                        . $group
                        . '.'
                        . $gate
                    );
                }
            }
        }

        $knownGroups = ['schema_version', 'target_version', 'updated_at'];
        $knownGroups = array_merge(
            $knownGroups,
            array_keys($required)
        );

        foreach (array_keys($decoded) as $key) {
            if (!in_array($key, $knownGroups, true)) {
                $fail(
                    'Unknown top-level RC1 acceptance key: '
                    . (string) $key
                );
            }
        }

        $pass(
            'RC1 acceptance record contains '
            . $gateCount
            . ' mandatory gates.'
        );

        if (!$strict && $pending > 0) {
            fwrite(
                STDOUT,
                '[INFO] '
                . $pending
                . ' RC1 gate(s) remain pending during development.'
                . PHP_EOL
            );
        }

        if ($strict && $pending === 0) {
            $updatedAt = trim(
                (string) ($decoded['updated_at'] ?? '')
            );

            if (
                $updatedAt === ''
                || strtotime($updatedAt) === false
            ) {
                $fail(
                    'Strict RC1 acceptance requires a valid updated_at timestamp.'
                );
            } else {
                $pass('RC1 acceptance timestamp is recorded.');
            }
        }
    }
}

fwrite(
    STDOUT,
    sprintf(
        PHP_EOL
        . 'Barama RC1 readiness: %d pass(es), %d failure(s).'
        . PHP_EOL,
        $passes,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
