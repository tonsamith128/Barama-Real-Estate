<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tools/rc1-acceptance-lib.php';

$usage = static function (): void {
    fwrite(
        STDOUT,
        "Usage:\n"
        . "  php tools/rc1-evidence.php --gate=GROUP.GATE --status=pass "
        . "--evidence=\"non-secret evidence\" [--apply]\n"
        . "  php tools/rc1-evidence.php --gate=GROUP.GATE --status=pending [--apply]\n\n"
        . "Dry-run is the default. Add --apply only after reviewing the proposed change.\n"
    );
};

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    $usage();
    exit(0);
}

$options = getopt(
    '',
    [
        'gate:',
        'status:',
        'evidence::',
        'record::',
        'apply',
        'help',
    ]
);

$gatePath = trim((string) ($options['gate'] ?? ''));
$status = strtolower(trim((string) ($options['status'] ?? '')));
$evidence = trim((string) ($options['evidence'] ?? ''));
$apply = array_key_exists('apply', $options);

if ($gatePath === '' || $status === '') {
    fwrite(STDERR, "[FAIL] --gate and --status are required.\n\n");
    $usage();
    exit(1);
}

if (!in_array($status, ['pending', 'pass'], true)) {
    fwrite(STDERR, "[FAIL] --status must be pending or pass.\n");
    exit(1);
}

$parts = explode('.', $gatePath, 2);

if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
    fwrite(STDERR, "[FAIL] --gate must use GROUP.GATE format.\n");
    exit(1);
}

[$group, $gate] = $parts;
$required = baramaRc1RequiredGates();

if (
    !isset($required[$group])
    || !in_array($gate, $required[$group], true)
) {
    fwrite(STDERR, "[FAIL] Unknown RC1 acceptance gate: {$gatePath}\n");
    exit(1);
}

if ($status === 'pass') {
    $evidenceIssue = baramaRc1EvidenceIssue($evidence);

    if ($evidenceIssue !== null) {
        fwrite(STDERR, '[FAIL] ' . $evidenceIssue . PHP_EOL);
        exit(1);
    }
} elseif ($evidence !== '') {
    fwrite(
        STDERR,
        "[FAIL] Pending gates must not include evidence. "
        . "Set the gate to pending without --evidence.\n"
    );
    exit(1);
}

$recordPath = $root . '/docs/RC1_ACCEPTANCE.json';
$overridePath = trim((string) ($options['record'] ?? ''));

if ($overridePath !== '') {
    if (getenv('BARAMA_RC1_TEST_MODE') !== '1') {
        fwrite(
            STDERR,
            "[FAIL] --record is reserved for the isolated RC1 evidence smoke test.\n"
        );
        exit(1);
    }

    $recordPath = $overridePath;
}

if (!is_file($recordPath) || !is_readable($recordPath)) {
    fwrite(STDERR, "[FAIL] RC1 acceptance record is missing or unreadable.\n");
    exit(1);
}

$originalRaw = (string) file_get_contents($recordPath);
$originalHash = hash('sha256', $originalRaw);
$record = json_decode($originalRaw, true);

if (!is_array($record)) {
    fwrite(STDERR, "[FAIL] RC1 acceptance record is not valid JSON.\n");
    exit(1);
}

$sourceVersion = trim((string) @file_get_contents($root . '/VERSION'));
$currentValidation = baramaRc1ValidateRecord(
    $record,
    false,
    $sourceVersion
);

if ($currentValidation['issues'] !== []) {
    foreach ($currentValidation['issues'] as $issue) {
        fwrite(STDERR, '[FAIL] Existing acceptance record: ' . $issue . PHP_EOL);
    }

    exit(1);
}

$record[$group][$gate] = [
    'status' => $status,
    'evidence' => $status === 'pass' ? $evidence : '',
];
$record['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');

$proposedValidation = baramaRc1ValidateRecord(
    $record,
    false,
    $sourceVersion
);

if ($proposedValidation['issues'] !== []) {
    foreach ($proposedValidation['issues'] as $issue) {
        fwrite(STDERR, '[FAIL] Proposed acceptance record: ' . $issue . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, '[PASS] RC1 gate recognized: ' . $gatePath . PHP_EOL);
fwrite(STDOUT, '[PASS] Proposed status: ' . $status . PHP_EOL);

if ($status === 'pass') {
    fwrite(
        STDOUT,
        '[PASS] Evidence accepted: '
        . strlen($evidence)
        . ' character(s), SHA256 '
        . substr(hash('sha256', $evidence), 0, 12)
        . PHP_EOL
    );
} else {
    fwrite(STDOUT, '[PASS] Pending state will contain no evidence.' . PHP_EOL);
}

if (!$apply) {
    fwrite(
        STDOUT,
        '[DRY-RUN] No file changed. Re-run with --apply after reviewing the result.'
        . PHP_EOL
    );
    exit(0);
}

$currentRaw = (string) file_get_contents($recordPath);

if (hash('sha256', $currentRaw) !== $originalHash) {
    fwrite(
        STDERR,
        "[FAIL] RC1 acceptance record changed during this operation; no update applied.\n"
    );
    exit(1);
}

$encoded = json_encode(
    $record,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

if (!is_string($encoded)) {
    fwrite(STDERR, "[FAIL] RC1 acceptance record could not be encoded.\n");
    exit(1);
}

$encoded .= PHP_EOL;
$directory = dirname($recordPath);
$tempPath = tempnam($directory, '.rc1-acceptance-');

if ($tempPath === false) {
    fwrite(STDERR, "[FAIL] Could not create an atomic RC1 acceptance temp file.\n");
    exit(1);
}

$written = file_put_contents($tempPath, $encoded, LOCK_EX);

if ($written !== strlen($encoded)) {
    @unlink($tempPath);
    fwrite(STDERR, "[FAIL] Could not write the complete RC1 acceptance record.\n");
    exit(1);
}

$permissions = @fileperms($recordPath);

if (is_int($permissions)) {
    @chmod($tempPath, $permissions & 0777);
}

if (!@rename($tempPath, $recordPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "[FAIL] Could not atomically replace the RC1 acceptance record.\n");
    exit(1);
}

fwrite(
    STDOUT,
    '[PASS] RC1 acceptance record updated atomically at '
    . $record['updated_at']
    . PHP_EOL
);
