<?php

declare(strict_types=1);

/**
 * Build an INTERNAL aaPanel acceptance bundle around an already-bound
 * Barama customer ZIP. This bundle is never a CodeCanyon customer package.
 *
 * Usage:
 * php tools/build-acceptance-bundle.php PACKAGE.zip MANIFEST.json \
 *   --source-commit=<40-hex> [--output=/path/bundle.zip]
 */

$root = dirname(__DIR__);

$fail = static function (string $message, int $code = 1): never {
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
};

$pass = static function (string $message): void {
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
};

if (!class_exists(ZipArchive::class)) {
    $fail('ZipArchive is required to build the aaPanel acceptance bundle.');
}

$packagePath = trim((string) ($argv[1] ?? ''));
$artifactPath = trim((string) ($argv[2] ?? ''));
$options = [];

foreach (array_slice($argv, 3) as $argument) {
    if (!str_starts_with($argument, '--')) {
        continue;
    }

    [$name, $value] = array_pad(
        explode('=', substr($argument, 2), 2),
        2,
        ''
    );
    $options[$name] = $value;
}

$sourceCommit = strtolower(
    trim((string) ($options['source-commit'] ?? ''))
);

if (
    $packagePath === ''
    || $artifactPath === ''
    || preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1
) {
    $fail(
        'Usage: php tools/build-acceptance-bundle.php PACKAGE.zip '
        . 'MANIFEST.json --source-commit=<40-hex> [--output=PATH]',
        2
    );
}

$version = trim((string) @file_get_contents($root . '/VERSION'));

if (
    $version === ''
    || preg_match(
        '/^(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)\\.(?:0|[1-9]\\d*)'
        . '(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/',
        $version
    ) !== 1
) {
    $fail('Source VERSION is missing or invalid.');
}

$artifactTool = $root . '/tools/rc1-artifact.php';

if (!is_file($artifactTool) || !function_exists('exec')) {
    $fail('Canonical artifact verifier is unavailable.');
}

$verifyCommand = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($artifactTool)
    . ' verify '
    . escapeshellarg($artifactPath)
    . ' '
    . escapeshellarg($packagePath)
    . ' '
    . escapeshellarg('--expect-version=' . $version)
    . ' '
    . escapeshellarg('--expect-commit=' . $sourceCommit)
    . ' 2>&1';

$verifyOutput = [];
$verifyCode = 1;
exec($verifyCommand, $verifyOutput, $verifyCode);

if ($verifyCode !== 0) {
    $fail(
        'Exact customer artifact binding must verify before '
        . 'the acceptance bundle can be built.'
    );
}

$artifact = json_decode(
    (string) @file_get_contents($artifactPath),
    true
);

if (!is_array($artifact)) {
    $fail('Verified artifact manifest could not be decoded.');
}

$expectedPackage = 'Barama-Real-Estate-' . $version . '.zip';

if (
    basename($packagePath) !== $expectedPackage
    || ($artifact['package_filename'] ?? '') !== $expectedPackage
) {
    $fail('Exact customer package filename does not match source VERSION.');
}

$sidecarPath = $packagePath . '.sha256';

if (!is_file($sidecarPath) || !is_readable($sidecarPath)) {
    $fail('Customer ZIP SHA-256 sidecar is missing.');
}

$bundleName = 'Barama-Real-Estate-aaPanel-Acceptance-' . $version;
$output = trim((string) ($options['output'] ?? ''));

if ($output === '') {
    $output = $root . '/build/acceptance/' . $bundleName . '.zip';
}

$outputDirectory = dirname($output);

if (
    !is_dir($outputDirectory)
    && !mkdir($outputDirectory, 0775, true)
    && !is_dir($outputDirectory)
) {
    $fail('Acceptance bundle output directory could not be created.');
}

if (is_file($output) && !@unlink($output)) {
    $fail('Existing acceptance bundle could not be replaced.');
}

$targetDomain = 'baramarealestate.tonsamith.com';
$targetUrl = 'https://' . $targetDomain;
$targetRoot = '/www/wwwroot/' . $targetDomain;
$phpBinary = '/www/server/php/84/bin/php';
$evidenceDirectory = '/root/barama-real-estate-acceptance-evidence';

$toolFiles = [
    'tools/rc1-acceptance-lib.php',
    'tools/rc1-artifact.php',
    'tools/live-server-preflight.php',
    'tools/rc1-live-session.php',
];

foreach ($toolFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        $fail('Required acceptance utility is missing: ' . $relative);
    }
}

$commitShort = substr($sourceCommit, 0, 12);
$artifactFilename = basename($artifactPath);
$sidecarFilename = basename($sidecarPath);

$verifyScript = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

BUNDLE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="/www/server/php/84/bin/php"

[ -x "$PHP" ] || {
    echo "ERROR: Expected aaPanel PHP 8.4 CLI is unavailable: $PHP"
    exit 1
}

cd "$BUNDLE_ROOT"
sha256sum -c BUNDLE_CONTENTS.sha256

"$PHP" "$BUNDLE_ROOT/tools/rc1-artifact.php" verify     "$BUNDLE_ROOT/candidate/__ARTIFACT__"     "$BUNDLE_ROOT/candidate/__PACKAGE__"     --expect-version="__VERSION__"     --expect-commit="__COMMIT__"

echo "Acceptance bundle and exact candidate binding verified."
BASH;

$sessionScript = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

BUNDLE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="/www/server/php/84/bin/php"
SITE="/www/wwwroot/baramarealestate.tonsamith.com"
URL="https://baramarealestate.tonsamith.com"
EVIDENCE="/root/barama-real-estate-acceptance-evidence"
PHASE="__PHASE__"

[ "$SITE" = "/www/wwwroot/baramarealestate.tonsamith.com" ] || {
    echo "ERROR: Unsafe Barama site path."
    exit 1
}

[ -x "$PHP" ] || {
    echo "ERROR: Expected aaPanel PHP 8.4 CLI is unavailable: $PHP"
    exit 1
}

[ -d "$SITE" ] || {
    echo "ERROR: Barama site root does not exist: $SITE"
    exit 1
}

mkdir -p "$EVIDENCE"
chmod 700 "$EVIDENCE" 2>/dev/null || true

REPORT="$EVIDENCE/__PHASE__-__VERSION__-__SHORT__.json"

"$PHP" "$BUNDLE_ROOT/tools/rc1-live-session.php"     --manifest="$BUNDLE_ROOT/candidate/__ARTIFACT__"     --package="$BUNDLE_ROOT/candidate/__PACKAGE__"     --root="$SITE"     --url="$URL"     --phase="$PHASE"     --expect-version="__VERSION__"     --expect-commit="__COMMIT__"     --output="$REPORT"

echo "Acceptance report: $REPORT"
BASH;

$replaceTokens = static function (
    string $template,
    array $tokens
): string {
    return strtr($template, $tokens);
};

$tokens = [
    '__ARTIFACT__' => $artifactFilename,
    '__PACKAGE__' => $expectedPackage,
    '__VERSION__' => $version,
    '__COMMIT__' => $sourceCommit,
    '__SHORT__' => $commitShort,
];

$verifyScript = $replaceTokens($verifyScript, $tokens);
$preinstallScript = $replaceTokens(
    str_replace('__PHASE__', 'preinstall', $sessionScript),
    $tokens
);
$installedScript = $replaceTokens(
    str_replace('__PHASE__', 'installed', $sessionScript),
    $tokens
);

$readme = <<<TEXT
# Barama Real Estate — Internal aaPanel Acceptance Bundle

INTERNAL QA ONLY. This ZIP is not a CodeCanyon customer package.

Bound candidate:
- Version: {$version}
- Source commit: {$sourceCommit}
- Customer ZIP: {$expectedPackage}
- Customer SHA-256: {$artifact['package_sha256']}

Hard scope:
- Domain: {$targetDomain}
- URL: {$targetUrl}
- Website root: {$targetRoot}
- PHP CLI: {$phpBinary}
- Evidence directory: {$evidenceDirectory}

Recommended sequence on aaPanel:
1. Extract this acceptance bundle under /root, never inside another website.
2. Run: bash bin/verify-bundle.sh
3. Before any destructive fresh-install test, create complete website-files AND database backups in aaPanel.
4. Deploy/extract only candidate/{$expectedPackage} to exactly {$targetRoot}. Deployment is intentionally not automated by this bundle.
5. Before entering installer credentials, run: bash bin/preinstall-readonly.sh
6. Complete the five-step installer manually.
7. Run: bash bin/installed-readonly.sh
8. Review the JSON evidence under {$evidenceDirectory}.
9. Record only genuinely passed RC1 gates through the repository's controlled evidence workflow.

Safety:
- The included verification/session scripts do not delete website files, change databases, sign in, activate/deactivate licenses, or send email.
- They are hard-scoped to {$targetRoot} and {$targetUrl}.
- Do not copy this internal bundle into the public website or CodeCanyon submission.
TEXT;

$rollback = <<<TEXT
# Rollback / Recovery Notes

This internal acceptance bundle itself performs read-only verification of the Barama website, aside from writing sanitized JSON evidence under {$evidenceDirectory}.

The bundle intentionally does NOT automate website deletion, database replacement, or rollback.

Before fresh-install or updater acceptance:
1. Create a complete aaPanel website backup for exactly {$targetRoot}.
2. Create a complete database backup for the Barama acceptance database.
3. Record the backup filenames and timestamps outside the public web root.
4. Verify both backups exist before changing the installed site.

If acceptance fails after deployment:
1. Stop the acceptance test.
2. Restore only the Barama website backup in aaPanel.
3. Restore only the Barama database backup.
4. Re-run the installed read-only preflight after recovery.
5. Do not touch any other website under /www/wwwroot.

Never use a Barama rollback command against /www/wwwroot itself or another domain.
TEXT;

$bundleManifest = [
    'schema_version' => 1,
    'product' => 'barama-real-estate',
    'purpose' => 'internal_aapanel_acceptance',
    'source_version' => $version,
    'source_commit' => $sourceCommit,
    'target_domain' => $targetDomain,
    'target_url' => $targetUrl,
    'target_root' => $targetRoot,
    'php_binary' => $phpBinary,
    'evidence_directory' => $evidenceDirectory,
    'candidate' => [
        'package_filename' => $expectedPackage,
        'package_sha256' => (string) $artifact['package_sha256'],
        'checksum_filename' => $sidecarFilename,
        'artifact_filename' => $artifactFilename,
    ],
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
];

$generated = [
    'INTERNAL-QA-ONLY.txt' =>
        "Barama Real Estate internal aaPanel acceptance bundle. "
        . "Do not submit this ZIP to CodeCanyon.\n",
    'README.md' => $readme . PHP_EOL,
    'ROLLBACK.md' => $rollback . PHP_EOL,
    'BUNDLE_MANIFEST.json' => json_encode(
        $bundleManifest,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
    ) . PHP_EOL,
    'bin/verify-bundle.sh' => $verifyScript . PHP_EOL,
    'bin/preinstall-readonly.sh' => $preinstallScript . PHP_EOL,
    'bin/installed-readonly.sh' => $installedScript . PHP_EOL,
];

$diskPayload = [
    'candidate/' . $expectedPackage => $packagePath,
    'candidate/' . $sidecarFilename => $sidecarPath,
    'candidate/' . $artifactFilename => $artifactPath,
];

foreach ($toolFiles as $relative) {
    $diskPayload[$relative] = $root . '/' . $relative;
}

$checksumLines = [];

foreach ($diskPayload as $relative => $path) {
    $hash = hash_file('sha256', $path);

    if (!is_string($hash)) {
        $fail('Could not hash bundle payload: ' . $relative);
    }

    $checksumLines[] = $hash . '  ' . $relative;
}

foreach ($generated as $relative => $content) {
    $checksumLines[] = hash('sha256', $content) . '  ' . $relative;
}

sort($checksumLines, SORT_STRING);
$generated['BUNDLE_CONTENTS.sha256'] =
    implode(PHP_EOL, $checksumLines) . PHP_EOL;

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    $fail('Acceptance bundle ZIP could not be created.');
}

$prefix = $bundleName . '/';
$directories = [
    'candidate/',
    'tools/',
    'bin/',
];

foreach ($directories as $directory) {
    $zip->addEmptyDir($prefix . $directory);
}

foreach ($diskPayload as $relative => $path) {
    if (!$zip->addFile($path, $prefix . $relative)) {
        $zip->close();
        @unlink($output);
        $fail('Could not add bundle payload: ' . $relative);
    }
}

foreach ($generated as $relative => $content) {
    if (!$zip->addFromString($prefix . $relative, $content)) {
        $zip->close();
        @unlink($output);
        $fail('Could not add generated bundle file: ' . $relative);
    }
}

$zip->setArchiveComment(
    'Barama Real Estate internal aaPanel acceptance bundle '
    . $version
    . ' @ '
    . $sourceCommit
    . '.'
);

if (!$zip->close()) {
    @unlink($output);
    $fail('Acceptance bundle ZIP could not be finalized.');
}

$bundleHash = hash_file('sha256', $output);

if (!is_string($bundleHash)) {
    @unlink($output);
    $fail('Acceptance bundle SHA-256 could not be calculated.');
}

file_put_contents(
    $output . '.sha256',
    $bundleHash . '  ' . basename($output) . PHP_EOL,
    LOCK_EX
);

$pass('Internal aaPanel acceptance bundle built: ' . $output);
$pass('Bundle SHA-256: ' . $bundleHash);
$pass('Exact candidate commit: ' . $sourceCommit);
