<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tools/rc1-acceptance-lib.php';

$fail = static function (string $message, int $code = 1): never {
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
};

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    fwrite(
        STDOUT,
        "Usage:\n"
        . "  php tools/rc1-live-session.php --manifest=PATH --package=PATH --root=PATH "
        . "--url=https://example.com --phase=preinstall|installed "
        . "--expect-version=VERSION --expect-commit=<40-hex> [--output=PATH] [--json]\n"
    );
    exit(0);
}

$options = getopt('', [
    'manifest:',
    'package:',
    'root:',
    'url:',
    'phase:',
    'expect-version:',
    'expect-commit:',
    'output::',
    'json',
]);

$manifestPath = trim((string) ($options['manifest'] ?? ''));
$packagePath = trim((string) ($options['package'] ?? ''));
$packageRoot = rtrim(
    trim((string) ($options['root'] ?? '')),
    DIRECTORY_SEPARATOR
);
$baseUrl = rtrim(trim((string) ($options['url'] ?? '')), '/');
$phase = strtolower(trim((string) ($options['phase'] ?? '')));
$expectVersion = trim((string) ($options['expect-version'] ?? ''));
$expectCommit = strtolower(
    trim((string) ($options['expect-commit'] ?? ''))
);
$outputPath = trim((string) ($options['output'] ?? ''));
$jsonOutput = array_key_exists('json', $options);

foreach ([
    'manifest' => $manifestPath,
    'package' => $packagePath,
    'root' => $packageRoot,
    'url' => $baseUrl,
    'phase' => $phase,
    'expect-version' => $expectVersion,
    'expect-commit' => $expectCommit,
] as $name => $value) {
    if ($value === '') {
        $fail('Missing required option --' . $name . '.', 2);
    }
}

if (!in_array($phase, ['preinstall', 'installed'], true)) {
    $fail('--phase must be preinstall or installed.', 2);
}

if (
    preg_match(
        '/^(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)'
        . '(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/',
        $expectVersion
    ) !== 1
) {
    $fail('--expect-version is invalid.', 2);
}

if (preg_match('/^[a-f0-9]{40}$/', $expectCommit) !== 1) {
    $fail(
        '--expect-commit must be a 40-character hexadecimal commit SHA.',
        2
    );
}

if (!is_file($manifestPath) || !is_readable($manifestPath)) {
    $fail('Artifact manifest is missing or unreadable.');
}

if (!is_file($packagePath) || !is_readable($packagePath)) {
    $fail('Exact customer ZIP is missing or unreadable.');
}

if (!is_dir($packageRoot)) {
    $fail('Extracted package root does not exist.');
}

$urlParts = parse_url($baseUrl);

if (
    !is_array($urlParts)
    || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
    || trim((string) ($urlParts['host'] ?? '')) === ''
    || isset($urlParts['user'])
    || isset($urlParts['pass'])
    || isset($urlParts['query'])
    || isset($urlParts['fragment'])
) {
    $fail(
        '--url must be a credential-free HTTPS base URL without query or fragment.',
        2
    );
}

if (!function_exists('exec')) {
    $fail(
        'PHP exec() is unavailable; the acceptance session guard cannot run.'
    );
}

$testMode = getenv('BARAMA_RC1_SESSION_TEST_MODE') === '1';
$artifactTool = $root . '/tools/rc1-artifact.php';
$preflightTool = $root . '/tools/live-server-preflight.php';

if ($testMode) {
    $artifactOverride = trim(
        (string) getenv('BARAMA_RC1_SESSION_ARTIFACT_TOOL')
    );
    $preflightOverride = trim(
        (string) getenv('BARAMA_RC1_SESSION_PREFLIGHT_TOOL')
    );

    if ($artifactOverride !== '') {
        $artifactTool = $artifactOverride;
    }

    if ($preflightOverride !== '') {
        $preflightTool = $preflightOverride;
    }
}

foreach ([$artifactTool, $preflightTool] as $tool) {
    if (!is_file($tool) || !is_readable($tool)) {
        $fail('Required acceptance utility is missing or unreadable.');
    }
}

$run = static function (string $tool, array $arguments): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($tool);

    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg((string) $argument);
    }

    $output = [];
    $code = 1;
    exec($command . ' 2>&1', $output, $code);

    return [
        'code' => $code,
        'lines' => array_values(array_filter(
            array_map('trim', $output),
            static fn (string $line): bool => $line !== ''
        )),
    ];
};

$artifactResult = $run($artifactTool, [
    'verify',
    $manifestPath,
    $packagePath,
    '--expect-version=' . $expectVersion,
    '--expect-commit=' . $expectCommit,
]);

if ($artifactResult['code'] !== 0) {
    $fail(
        'Artifact binding verification failed. '
        . 'No acceptance session report was created.'
    );
}

$manifest = json_decode(
    (string) file_get_contents($manifestPath),
    true
);

if (!is_array($manifest)) {
    $fail('Verified artifact manifest could not be decoded.');
}

foreach ([
    'schema_version',
    'product',
    'source_version',
    'source_commit',
    'package_filename',
    'package_sha256',
    'package_size_bytes',
    'checksum_filename',
    'generated_at',
] as $key) {
    if (!array_key_exists($key, $manifest)) {
        $fail(
            'Verified artifact manifest is missing required field: '
            . $key
            . '.'
        );
    }
}

$preflightResult = $run($preflightTool, [
    '--root=' . $packageRoot,
    '--url=' . $baseUrl,
    '--phase=' . $phase,
    '--json',
]);

$preflight = null;

for (
    $index = count($preflightResult['lines']) - 1;
    $index >= 0;
    $index--
) {
    $decoded = json_decode(
        $preflightResult['lines'][$index],
        true
    );

    if (is_array($decoded)) {
        $preflight = $decoded;
        break;
    }
}

if (!is_array($preflight)) {
    $fail(
        'Live-server preflight did not return a machine-readable report. '
        . 'No acceptance session report was created.'
    );
}

$preflightPhase = strtolower(
    trim((string) ($preflight['phase'] ?? ''))
);
$preflightVersion = trim(
    (string) ($preflight['version'] ?? '')
);
$preflightHost = strtolower(
    trim((string) ($preflight['url_host'] ?? ''))
);
$urlHost = strtolower(
    trim((string) ($urlParts['host'] ?? ''))
);

if (!hash_equals($phase, $preflightPhase)) {
    $fail(
        'Preflight report phase does not match '
        . 'the requested acceptance phase.'
    );
}

if (!hash_equals($expectVersion, $preflightVersion)) {
    $fail(
        'Preflight report version does not match '
        . 'the expected source version.'
    );
}

if (!hash_equals($urlHost, $preflightHost)) {
    $fail(
        'Preflight report host does not match '
        . 'the requested acceptance URL.'
    );
}

$preflightChecks = [];

foreach ($preflight['checks'] ?? [] as $check) {
    if (!is_array($check)) {
        $fail('Preflight report contains an invalid check entry.');
    }

    $level = strtolower(
        trim((string) ($check['level'] ?? ''))
    );
    $title = trim((string) ($check['title'] ?? ''));
    $detail = trim((string) ($check['detail'] ?? ''));

    if (
        !in_array($level, ['pass', 'warn', 'fail'], true)
        || $title === ''
    ) {
        $fail(
            'Preflight report contains an invalid check result.'
        );
    }

    foreach ([$title, $detail] as $evidenceText) {
        if ($evidenceText === '') {
            continue;
        }

        $issue = baramaRc1EvidenceIssue($evidenceText);

        if ($issue !== null) {
            $fail(
                'Preflight report contains evidence that is not safe '
                . 'to retain: '
                . $issue
            );
        }
    }

    $preflightChecks[] = [
        'level' => $level,
        'title' => $title,
        'detail' => $detail,
    ];
}

$passed = (int) ($preflight['passed'] ?? 0);
$warnings = (int) ($preflight['warnings'] ?? 0);
$failed = (int) ($preflight['failed'] ?? 0);
$preflightOverall = strtolower(
    trim((string) ($preflight['overall'] ?? ''))
);

if (
    $passed < 0
    || $warnings < 0
    || $failed < 0
    || $passed + $warnings + $failed !== count($preflightChecks)
) {
    $fail('Preflight report result counts are inconsistent.');
}

$sessionStatus = 'failed';

if ($preflightResult['code'] === 0 && $failed === 0) {
    $sessionStatus = (
        $warnings === 0
        && $preflightOverall === 'ready'
    )
        ? 'passed'
        : 'review_required';
}

$urlPath = '/' . trim(
    (string) ($urlParts['path'] ?? ''),
    '/'
);
$urlPath = $urlPath === '/' ? '/' : rtrim($urlPath, '/');

$sessionFingerprint = hash(
    'sha256',
    implode("\n", [
        'barama-live-acceptance-session-v1',
        $expectVersion,
        $expectCommit,
        (string) $manifest['package_filename'],
        (string) $manifest['package_sha256'],
        $urlHost,
        $urlPath,
        $phase,
    ])
);

$report = [
    'schema_version' => 1,
    'product' => 'barama-real-estate',
    'session_status' => $sessionStatus,
    'phase' => $phase,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'session_fingerprint' => $sessionFingerprint,
    'artifact' => [
        'source_version' => (string) $manifest['source_version'],
        'source_commit' => (string) $manifest['source_commit'],
        'package_filename' => (string) $manifest['package_filename'],
        'package_sha256' => (string) $manifest['package_sha256'],
        'package_size_bytes' => (int) $manifest['package_size_bytes'],
        'checksum_filename' => (string) $manifest['checksum_filename'],
        'manifest_filename' => basename($manifestPath),
    ],
    'environment' => [
        'url_host' => $urlHost,
        'url_path' => $urlPath,
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'os_family' => PHP_OS_FAMILY,
    ],
    'preflight' => [
        'overall' => $preflightOverall,
        'passed' => $passed,
        'warnings' => $warnings,
        'failed' => $failed,
        'checks' => $preflightChecks,
    ],
];

$evidenceSummary = sprintf(
    '%s live preflight %s for %s; Barama %s; commit %s; '
    . 'ZIP SHA-256 %s; session %s.',
    $phase,
    $sessionStatus,
    $urlHost,
    $expectVersion,
    substr($expectCommit, 0, 12),
    substr((string) $manifest['package_sha256'], 0, 16),
    substr($sessionFingerprint, 0, 16)
);

$evidenceIssue = baramaRc1EvidenceIssue($evidenceSummary);

if ($evidenceIssue !== null) {
    $fail(
        'Generated evidence summary is not safe to retain: '
        . $evidenceIssue
    );
}

$report['evidence_summary'] = $evidenceSummary;

$encoded = json_encode(
    $report,
    JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
) . PHP_EOL;

if ($outputPath !== '') {
    $directory = dirname($outputPath);

    if (!is_dir($directory)) {
        $fail(
            'Acceptance report output directory does not exist.'
        );
    }

    $temporary = tempnam(
        $directory,
        '.barama-live-session-'
    );

    if (
        $temporary === false
        || file_put_contents(
            $temporary,
            $encoded,
            LOCK_EX
        ) !== strlen($encoded)
    ) {
        if (is_string($temporary)) {
            @unlink($temporary);
        }

        $fail(
            'Acceptance session report could not be written.'
        );
    }

    @chmod($temporary, 0640);

    if (!@rename($temporary, $outputPath)) {
        @unlink($temporary);
        $fail(
            'Acceptance session report could not be finalized atomically.'
        );
    }

    @chmod($outputPath, 0640);
}

if ($jsonOutput || $outputPath === '') {
    fwrite(STDOUT, $encoded);
} else {
    fwrite(
        STDOUT,
        '[PASS] Acceptance session report: '
        . $outputPath
        . PHP_EOL
    );
    fwrite(
        STDOUT,
        '[INFO] Status: '
        . $sessionStatus
        . PHP_EOL
    );
    fwrite(
        STDOUT,
        '[INFO] Session: '
        . $sessionFingerprint
        . PHP_EOL
    );
    fwrite(
        STDOUT,
        '[INFO] Evidence: '
        . $evidenceSummary
        . PHP_EOL
    );
}

exit(match ($sessionStatus) {
    'passed' => 0,
    'review_required' => 3,
    default => 1,
});
