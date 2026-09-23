<?php

declare(strict_types=1);

function baramaRc1RequiredGates(): array
{
    return [
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
}

function baramaRc1EvidenceIssue(string $evidence): ?string
{
    $evidence = trim($evidence);

    if ($evidence === '') {
        return 'Evidence is empty.';
    }

    if (strlen($evidence) > 500) {
        return 'Evidence must not exceed 500 characters.';
    }

    if (preg_match('/[\r\n]/', $evidence) === 1) {
        return 'Evidence must be a single line.';
    }

    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $evidence) === 1) {
        return 'Evidence contains unsupported control characters.';
    }

    $secretPatterns = [
        '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i',
        '/\b(?:password|passwd|secret|token|activation[_ -]?token|purchase[_ -]?code|license[_ -]?key|api[_ -]?key|smtp[_ -]?password|db[_ -]?password)\b\s*[:=]\s*\S+/i',
        '/\b[A-Z][A-Z0-9_]*(?:PASSWORD|SECRET|TOKEN|KEY|CODE)\s*=\s*\S+/',
        '/https?:\/\/[^\s\/:@]+:[^@\s\/]+@/i',
        '/[?&](?:token|key|code|secret|password)=[^&\s]+/i',
        '/\beyJ[a-zA-Z0-9_-]{8,}\.[a-zA-Z0-9_-]{8,}\.[a-zA-Z0-9_-]{8,}\b/',
    ];

    foreach ($secretPatterns as $pattern) {
        if (preg_match($pattern, $evidence) === 1) {
            return 'Evidence appears to contain secret or credential material.';
        }
    }

    return null;
}

function baramaRc1ValidateRecord(
    array $record,
    bool $strict,
    string $sourceVersion
): array {
    $issues = [];
    $pending = [];
    $passed = [];
    $required = baramaRc1RequiredGates();
    $strict = $strict || $sourceVersion === '1.0.0-RC1';

    if (($record['schema_version'] ?? null) !== 1) {
        $issues[] = 'RC1 acceptance schema_version must be 1.';
    }

    if (($record['target_version'] ?? '') !== '1.0.0-RC1') {
        $issues[] = 'RC1 acceptance target_version must be 1.0.0-RC1.';
    }

    $knownTopLevel = array_merge(
        ['schema_version', 'target_version', 'updated_at'],
        array_keys($required)
    );

    foreach (array_keys($record) as $key) {
        if (!in_array($key, $knownTopLevel, true)) {
            $issues[] = 'Unknown top-level RC1 acceptance key: ' . (string) $key;
        }
    }

    $gateCount = 0;

    foreach ($required as $group => $gates) {
        $actualGroup = $record[$group] ?? null;

        if (!is_array($actualGroup)) {
            $issues[] = 'Missing RC1 acceptance group: ' . $group;
            continue;
        }

        foreach (array_keys($actualGroup) as $actualGate) {
            if (!in_array($actualGate, $gates, true)) {
                $issues[] = 'Unknown RC1 acceptance gate: '
                    . $group
                    . '.'
                    . (string) $actualGate;
            }
        }

        foreach ($gates as $gate) {
            $gateCount++;
            $path = $group . '.' . $gate;
            $entry = $actualGroup[$gate] ?? null;

            if (!is_array($entry)) {
                $issues[] = 'Missing RC1 acceptance gate: ' . $path;
                continue;
            }

            foreach (array_keys($entry) as $entryKey) {
                if (!in_array($entryKey, ['status', 'evidence'], true)) {
                    $issues[] = 'Unknown RC1 gate key: '
                        . $path
                        . '.'
                        . (string) $entryKey;
                }
            }

            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            $evidence = trim((string) ($entry['evidence'] ?? ''));

            if (!in_array($status, ['pending', 'pass'], true)) {
                $issues[] = 'Invalid RC1 gate status for ' . $path . '.';
                continue;
            }

            if ($status === 'pending') {
                $pending[] = $path;

                if ($evidence !== '') {
                    $issues[] = 'Pending RC1 gate must not retain evidence: ' . $path;
                }

                if ($strict) {
                    $issues[] = 'RC1 gate is still pending: ' . $path;
                }

                continue;
            }

            if ($evidence === '') {
                $issues[] = 'Passed RC1 gate requires non-secret evidence: ' . $path;
                continue;
            }

            $evidenceIssue = baramaRc1EvidenceIssue($evidence);

            if ($evidenceIssue !== null) {
                $issues[] = 'Unsafe RC1 evidence for ' . $path . ': ' . $evidenceIssue;
                continue;
            }

            $passed[] = $path;
        }
    }

    if ($strict && $pending === []) {
        $updatedAt = trim((string) ($record['updated_at'] ?? ''));

        if (
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $updatedAt) !== 1
            || strtotime($updatedAt) === false
        ) {
            $issues[] = 'Strict RC1 acceptance requires updated_at in UTC ISO-8601 format.';
        }
    }

    return [
        'issues' => array_values(array_unique($issues)),
        'pending' => $pending,
        'passed' => $passed,
        'gate_count' => $gateCount,
    ];
}
