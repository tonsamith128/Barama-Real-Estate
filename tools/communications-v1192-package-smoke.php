<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$builder = $root . '/tools/build-communications-v1192-release.php';
$manifestPath = $root . '/docs/releases/admin-communications-v1.19.2.json';
$releaseName = 'Barama-Communications-Phase7-v1.19.2';
$temp = sys_get_temp_dir()
    . '/barama-communications-v1192-'
    . bin2hex(random_bytes(5));
$output = $temp . '/' . $releaseName . '.zip';

$checks = 0;
$failures = [];

$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

$fail = static function (
    string $message
) use (&$checks, &$failures): void {
    $checks++;
    $failures[] = $message;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

if (
    !class_exists(ZipArchive::class)
    || !function_exists('exec')
    || !is_file($builder)
    || !is_file($manifestPath)
    || (!mkdir($temp, 0700, true) && !is_dir($temp))
) {
    fwrite(
        STDERR,
        "[FAIL] Communications release-bundle smoke prerequisites are unavailable.\n"
    );
    exit(1);
}

$manifest = json_decode(
    (string) file_get_contents($manifestPath),
    true,
    512,
    JSON_THROW_ON_ERROR
);

$command = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($builder)
    . ' '
    . escapeshellarg('--output=' . $output)
    . ' 2>&1';

$lines = [];
$exitCode = 1;
exec($command, $lines, $exitCode);

if (
    $exitCode === 0
    && is_file($output)
    && is_file($output . '.sha256')
) {
    $pass('Manifest-bound Communications v1.19.2 bundle builds.');
} else {
    $fail(
        'Communications v1.19.2 bundle build failed: '
        . implode(' ', $lines)
    );
}

$zip = new ZipArchive();
$entries = [];
$prefix = $releaseName . '/';

if ($zip->open($output) === true) {
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);

        if (is_string($name)) {
            $entries[] = $name;
        }
    }
} else {
    $fail('Built Communications bundle could not be opened.');
}

$required = [
    $prefix . 'manifest/admin-communications-v1.19.2.json',
    $prefix . 'bin/predeploy.php',
    $prefix . 'bin/postdeploy.php',
    $prefix . 'bin/rollback-db.php',
    $prefix . 'bin/deploy.sh',
    $prefix . 'bin/rollback.sh',
    $prefix . 'README.txt',
    $prefix . 'ROLLBACK.txt',
    $prefix . 'BUNDLE_CONTENTS.sha256',
];

foreach ((array) ($manifest['production_files'] ?? []) as $entry) {
    if (is_array($entry) && isset($entry['path'])) {
        $required[] = $prefix . 'payload/' . $entry['path'];
    }
}

$missing = array_values(array_diff($required, $entries));

if ($missing === []) {
    $pass('Bundle contains the exact manifest payload and release helpers.');
} else {
    $fail(
        'Communications bundle is missing required entries: '
        . implode(', ', $missing)
    );
}

$forbiddenFragments = [
    '/.github/',
    '/tools/communications-announcement-smoke.php',
    '/tools/qa.php',
    '/tools/build-communications-v1192-release.php',
    '/application/storage/',
    '/.env',
];

$forbiddenFound = [];

foreach ($entries as $entry) {
    foreach ($forbiddenFragments as $fragment) {
        if (str_contains($entry, $fragment)) {
            $forbiddenFound[] = $entry;
        }
    }
}

if ($forbiddenFound === []) {
    $pass('Bundle excludes CI, smoke, secrets and unrelated runtime state.');
} else {
    $fail(
        'Forbidden bundle content detected: '
        . implode(', ', array_unique($forbiddenFound))
    );
}

$checksumText = (string) $zip->getFromName(
    $prefix . 'BUNDLE_CONTENTS.sha256'
);
$checksumOk = true;

foreach (preg_split('/\\R/', trim($checksumText)) ?: [] as $line) {
    if ($line === '') {
        continue;
    }

    if (
        preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $match) !== 1
    ) {
        $checksumOk = false;
        break;
    }

    $content = $zip->getFromName($prefix . $match[2]);

    if (
        !is_string($content)
        || !hash_equals($match[1], hash('sha256', $content))
    ) {
        $checksumOk = false;
        break;
    }
}

$checksumOk
    ? $pass('Bundle checksum inventory verifies every packaged byte.')
    : $fail('Bundle checksum inventory is invalid.');

$deploy = (string) $zip->getFromName($prefix . 'bin/deploy.sh');
$rollback = (string) $zip->getFromName($prefix . 'bin/rollback.sh');

if (
    str_contains(
        $deploy,
        'SITE="/www/wwwroot/baramarealestate.tonsamith.com"'
    )
    && str_contains(
        $deploy,
        'PHP="/www/server/php/84/bin/php"'
    )
    && str_contains($deploy, 'communications-v1.19.2')
    && str_contains($deploy, 'sha256sum -c BUNDLE_CONTENTS.sha256')
    && str_contains($deploy, 'tools/migrate.php')
    && str_contains($deploy, 'HTTP_CODE')
) {
    $pass('Deploy harness is hard-scoped and includes integrity/migration/HTTP gates.');
} else {
    $fail('Deploy harness scope or acceptance contract is incomplete.');
}

if (
    str_contains($rollback, 'rollback-db.php')
    && str_contains($rollback, 'baseline.tsv')
    && str_contains($rollback, 'postdeploy.php')
    && str_contains($rollback, 'predeploy.php')
) {
    $pass('Rollback harness verifies target state and restores the accepted base.');
} else {
    $fail('Rollback harness safety contract is incomplete.');
}

$forbiddenCommands = [
    'systemctl ',
    'service nginx',
    'nginx -s',
    'reboot',
    'shutdown',
    'rm -rf /www/wwwroot',
];

$dangerous = [];

foreach ($forbiddenCommands as $commandNeedle) {
    if (
        str_contains($deploy, $commandNeedle)
        || str_contains($rollback, $commandNeedle)
    ) {
        $dangerous[] = $commandNeedle;
    }
}

if ($dangerous === []) {
    $pass('Release harness contains no service restart or broad destructive commands.');
} else {
    $fail(
        'Dangerous release command detected: '
        . implode(', ', $dangerous)
    );
}

$zip->close();

$sidecar = trim((string) file_get_contents($output . '.sha256'));
$actualBundleHash = hash_file('sha256', $output);

if (
    is_string($actualBundleHash)
    && str_starts_with($sidecar, $actualBundleHash . '  ')
) {
    $pass('Release ZIP SHA-256 sidecar matches the built artifact.');
} else {
    $fail('Release ZIP SHA-256 sidecar does not match.');
}

foreach (glob($temp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($temp);

fwrite(
    STDOUT,
    sprintf(
        "\nBarama Communications v1.19.2 bundle smoke: %d check(s), %d failure(s).\n",
        $checks,
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);
