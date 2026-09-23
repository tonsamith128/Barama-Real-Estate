<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tools/rc1-acceptance-lib.php';

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
        $sourceVersion = trim((string) @file_get_contents($versionPath));
        $strict = $strictRequested || $sourceVersion === '1.0.0-RC1';
        $result = baramaRc1ValidateRecord(
            $decoded,
            $strict,
            $sourceVersion
        );

        (($decoded['schema_version'] ?? null) === 1)
            ? $pass('RC1 acceptance schema version is supported.')
            : null;

        (($decoded['target_version'] ?? '') === '1.0.0-RC1')
            ? $pass('RC1 acceptance target version is canonical.')
            : null;

        foreach ($result['passed'] as $gate) {
            $pass('RC1 gate evidence recorded: ' . $gate);
        }

        $pass(
            'RC1 acceptance record contains '
            . (string) $result['gate_count']
            . ' mandatory gates.'
        );

        if (!$strict && $result['pending'] !== []) {
            fwrite(
                STDOUT,
                '[INFO] '
                . count($result['pending'])
                . ' RC1 gate(s) remain pending during development.'
                . PHP_EOL
            );
        }

        if ($strict && $result['pending'] === []) {
            $pass('RC1 acceptance timestamp is recorded.');
        }

        foreach ($result['issues'] as $issue) {
            $fail($issue);
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
