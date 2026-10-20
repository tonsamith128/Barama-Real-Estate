<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickInspectionFreshnessV1.php';

use Barama\Services\Updates\OneClickInspectionFreshnessV1;

$now = 1781844000;
$base = [
    'status' => 'Signed package verified (not installed)',
    'verified_at' => $now - 30,
    'site_version' => '0.9.22-dev',
    'version' => '0.9.23',
    'channel' => 'stable',
    'files' => 3,
    'archive_sha256' => str_repeat('a', 64),
    'trusted_keys_sha256' => str_repeat('b', 64),
];

$assess = static function (
    mixed $record, int $clock = 1781844000, string $installed = '0.9.22-dev',
    bool $ready = true, ?string $archive = null, ?string $keys = null
): array {
    return OneClickInspectionFreshnessV1::display(
        $record, $clock, $installed, $ready,
        $archive ?? str_repeat('a', 64), $keys ?? str_repeat('b', 64)
    );
};
$assert = static function (bool $ok, string $name): void {
    if (!$ok) {
        fwrite(STDERR, 'FAIL=' . $name . PHP_EOL);
        exit(1);
    }
    echo 'PASS=' . $name . PHP_EOL;
};

$valid = $assess($base);
$assert(($valid['verified'] ?? false) === true
    && $valid['status'] === 'Signed package verified (not installed)'
    && $valid['files'] === 3
    && $valid['version'] === '0.9.23'
    && $valid['archive_sha256'] === str_repeat('a', 64)
    && $valid['install_enabled'] === false, 'fresh_signed_snapshot_not_install_authority');

$cases = [
    'missing_session' => null,
    'forged_boolean' => ['verified' => true, 'install_enabled' => true],
    'old_format_without_digest' => [
        'status' => 'Signed package verified (not installed)', 'version' => '0.9.23', 'files' => 3
    ],
    'expired' => array_replace($base, ['verified_at' => $now - 601]),
    'future_timestamp' => array_replace($base, ['verified_at' => $now + 1]),
    'wrong_installed_site' => array_replace($base, ['site_version' => '0.9.21']),
    'downgrade' => array_replace($base, ['version' => '0.9.21']),
    'equal_version' => array_replace($base, ['version' => '0.9.22-dev']),
    'invalid_channel' => array_replace($base, ['channel' => 'nightly']),
    'invalid_file_count' => array_replace($base, ['files' => 0]),
    'string_file_count' => array_replace($base, ['files' => '3']),
    'forged_uppercase_digest' => array_replace($base, ['archive_sha256' => strtoupper(str_repeat('a', 64))]),
    'other_key_catalog' => array_replace($base, ['trusted_keys_sha256' => str_repeat('c', 64)]),
];
foreach ($cases as $name => $record) {
    $result = $assess($record);
    $assert($result['verified'] === false && $result['install_enabled'] === false
        && $result['archive_sha256'] === '', $name . '_blocked');
}
foreach ([
    'current_archive_changed' => $assess($base, $now, '0.9.22-dev', true, str_repeat('c', 64)),
    'current_keys_changed' => $assess($base, $now, '0.9.22-dev', true, null, str_repeat('c', 64)),
    'inspector_unready' => $assess($base, $now, '0.9.22-dev', false),
    'current_version_changed' => $assess($base, $now, '0.9.23'),
    'time_too_old' => $assess($base, $now + 601),
] as $name => $result) {
    $assert($result['verified'] === false && $result['install_enabled'] === false,
        $name . '_blocked');
}

$denied = $assess(['status' => 'Inspection unavailable or rejected']);
$assert($denied['status'] === 'Inspection unavailable or rejected'
    && $denied['verified'] === false, 'rejected_inspection_preserved_without_approval');

$source = file_get_contents(dirname(__DIR__)
    . '/application/app/Services/Updates/OneClickInspectionFreshnessV1.php');
$assert(is_string($source) && !preg_match(
    '/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i',
    $source
), 'no_write_or_execution_code');
echo 'ONECLICK_SIGNED_INSPECTION_FRESHNESS_SMOKE=PASS' . PHP_EOL;
