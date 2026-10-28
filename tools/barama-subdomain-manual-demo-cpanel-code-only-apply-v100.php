<?php
declare(strict_types=1);

/**
 * Barama barama.salabandet.com customer-demo cPanel code-only safety overlay.
 * CLI ONLY. No database writes, no demo import, no license changes.
 * The private kit must live OUTSIDE public_html.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
function stopPatch(string $reason): never {
    fwrite(STDERR, "STOP=" . $reason . PHP_EOL);
    exit(2);
}
function digest(string $path): string {
    $sha = is_file($path) ? hash_file('sha256', $path) : false;
    if (!is_string($sha)) stopPatch('CANNOT_HASH_SOURCE');
    return $sha;
}
function pathSafe(string $relative): bool {
    return preg_match('~^application/(?:app|resources)/[A-Za-z0-9_./-]+\.php$~D', $relative) === 1
        && !str_contains($relative, '..') && !str_contains($relative, '//');
}
function inputArgs(array $args): array {
    $params = [];
    foreach (array_slice($args, 1) as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $params[$key] = $value;
        } elseif (in_array($arg, ['--check', '--apply', '--rollback'], true)) {
            $params[substr($arg, 2)] = '1';
        } else {
            stopPatch('INVALID_ARGUMENT');
        }
    }
    return $params;
}
function copyAtomically(string $source, string $dest): void {
    if (!is_file($source) || is_link($source)) throw new RuntimeException('PATCH_SOURCE_UNSAFE');
    $temp = dirname($dest) . '/.barama-qa-' . bin2hex(random_bytes(12)) . '.tmp';
    if (!copy($source, $temp)) throw new RuntimeException('TEMP_COPY_FAILED');
    if (!chmod($temp, 0644) || !rename($temp, $dest)) {
        @unlink($temp);
        throw new RuntimeException('ATOMIC_REPLACE_FAILED');
    }
}
$opt = inputArgs($argv);
$actionCount = (int)isset($opt['check']) + (int)isset($opt['apply']) + (int)isset($opt['rollback']);
if ($actionCount !== 1) stopPatch('CHOOSE_ONE_ACTION');
$kit = realpath(__DIR__);
$root = realpath((string)($opt['root'] ?? ''));
if ($kit === false || $root === false || $root !== '/home/salalsxn/barama.salabandet.com') {
    stopPatch('CUSTOMER_QA_ROOT_NOT_VERIFIED');
}
if ($kit === $root || str_starts_with($kit . '/', $root . '/')) {
    stopPatch('KIT_MUST_BE_OUTSIDE_PUBLIC_HTML');
}
if (!is_file($root . '/VERSION') || trim((string)file_get_contents($root . '/VERSION')) !== '0.9.25') {
    stopPatch('BARAMA_INSTALLED_VERSION_NOT_0925');
}
$manifest = json_decode((string)file_get_contents($kit . '/manifest.json'), true);
if (!is_array($manifest) || ($manifest['kind'] ?? '') !== 'barama-manual-demo-policy-code-only-v1' ||
    !is_array($manifest['files'] ?? null) || count($manifest['files']) !== 7) {
    stopPatch('PACKAGE_MANIFEST_INVALID');
}
$lock = fopen($kit . '/.code-only-patch.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) stopPatch('PATCH_ALREADY_LOCKED');

$records = [];
$baseline = 0;
$patched = 0;
foreach ($manifest['files'] as $entry) {
    if (!is_array($entry) || !is_string($entry['path'] ?? null) ||
        !pathSafe($entry['path']) ||
        !preg_match('/^[a-f0-9]{64}$/D', (string)($entry['new_sha256'] ?? ''))) {
        stopPatch('BAD_MANIFEST_FILE');
    }
    $relative = $entry['path'];
    $source = $kit . '/payload/' . $relative;
    $dest = $root . '/' . $relative;
    $parent = realpath(dirname($dest));
    if (!$parent || !str_starts_with($parent . '/', $root . '/') || is_link($dest)) {
        stopPatch('PATCH_PATH_UNSAFE');
    }
    if (digest($source) !== $entry['new_sha256']) stopPatch('PATCH_PAYLOAD_TAMPERED');
    $old = $entry['old_sha256'] ?? null;
    if ($old !== null && (!is_string($old) || !preg_match('/^[a-f0-9]{64}$/D', $old))) {
        stopPatch('BASELINE_DIGEST_INVALID');
    }
    $actual = is_file($dest) ? digest($dest) : null;
    $new = $entry['new_sha256'];
    if ($actual === $new) {
        $patched++;
    } elseif ($actual === $old && ($old !== null || !file_exists($dest))) {
        $baseline++;
    } else {
        fwrite(STDERR, "FILE_MISMATCH=" . $relative . PHP_EOL);
        stopPatch('UNEXPECTED_INSTALLED_CODE');
    }
    $records[] = ['path'=>$relative, 'source'=>$source, 'dest'=>$dest, 'old'=>$old, 'new'=>$new];
}
if ($baseline > 0 && $patched > 0) stopPatch('PARTIALLY_PATCHED_SITE');
echo "APP_VERSION=0.9.25\n";
echo "SOURCE_FILES_MATCH_BASELINE=" . $baseline . "\n";
echo "SOURCE_FILES_MATCH_TARGET=" . $patched . "\n";
if (isset($opt['check'])) {
    echo "PATCH_STATUS=" . ($baseline === 7 ? 'READY' : 'ALREADY_APPLIED') . "\n";
    exit(0);
}
if (isset($opt['apply'])) {
    if (($opt['confirm'] ?? '') !== 'INSTALL_BARAMA_SUBDOMAIN_MANUAL_DEMO_SAFETY_V100') {
        stopPatch('CONFIRMATION_REQUIRED');
    }
    if ($patched === 7) { echo "PATCH_STATUS=ALREADY_APPLIED\n"; exit(0); }
    if ($baseline !== 7) stopPatch('SOURCE_NOT_CLEAN');
    $backup = $kit . '/rollback-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5));
    if (!mkdir($backup, 0700)) stopPatch('PRIVATE_ROLLBACK_DIRECTORY_FAILED');
    $receipt = [];
    foreach ($records as $record) {
        $saved = $backup . '/' . str_replace('/', '__', $record['path']);
        if ($record['old'] !== null && (!copy($record['dest'], $saved) || digest($saved) !== $record['old'])) {
            stopPatch('ROLLBACK_SNAPSHOT_FAILED_NO_WRITES');
        }
        $receipt[] = ['path'=>$record['path'], 'old_sha256'=>$record['old'], 'new_sha256'=>$record['new'], 'snapshot'=>basename($saved)];
    }
    if (file_put_contents($backup . '/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
        stopPatch('ROLLBACK_RECEIPT_FAILED_NO_WRITES');
    }
    try {
        foreach ($records as $record) {
            copyAtomically($record['source'], $record['dest']);
            if (digest($record['dest']) !== $record['new']) {
                throw new RuntimeException('AFTER_WRITE_HASH_MISMATCH');
            }
            echo "PATCHED=" . $record['path'] . "\n";
        }
    } catch (Throwable $e) {
        // Revert only locations that contain this candidate's bytes.
        foreach (array_reverse($records) as $record) {
            $current = is_file($record['dest']) ? digest($record['dest']) : null;
            if ($current !== $record['new']) continue;
            if ($record['old'] === null) {
                @unlink($record['dest']);
            } else {
                $saved = $backup . '/' . str_replace('/', '__', $record['path']);
                if (is_file($saved)) copyAtomically($saved, $record['dest']);
            }
        }
        stopPatch('PATCH_FAILED_AUTO_ROLLBACK_' . preg_replace('/[^A-Z0-9_]/', '_', strtoupper($e->getMessage())));
    }
    echo "CODE_ONLY_PATCH=PASS\n";
    echo "PRIVATE_ROLLBACK_DIR=" . $backup . "\n";
    echo "DATABASE_CHANGES=NONE\n";
    echo "LICENSE_CHANGES=NONE\n";
    echo "PUBLISHER_RELEASE_CHANGES=NONE\n";
    exit(0);
}
if (($opt['confirm'] ?? '') !== 'ROLLBACK_BARAMA_SUBDOMAIN_MANUAL_DEMO_SAFETY_V100') {
    stopPatch('ROLLBACK_CONFIRMATION_REQUIRED');
}
$backup = realpath((string)($opt['backup'] ?? ''));
if ($backup === false || !str_starts_with($backup, $kit . '/rollback-')) {
    stopPatch('ROLLBACK_PATH_INVALID');
}
$receipt = json_decode((string)file_get_contents($backup . '/receipt.json'), true);
if (!is_array($receipt) || count($receipt) !== count($records)) {
    stopPatch('ROLLBACK_RECEIPT_INVALID');
}
foreach ($records as $index=>$record) {
    $item = $receipt[$index] ?? [];
    if (($item['path'] ?? null) !== $record['path'] ||
        ($item['new_sha256'] ?? null) !== $record['new'] ||
        ($item['old_sha256'] ?? null) !== $record['old'] ||
        !is_file($record['dest']) || digest($record['dest']) !== $record['new']) {
        stopPatch('ROLLBACK_CURRENT_STATE_CHANGED');
    }
    if ($record['old'] !== null) {
        $saved = $backup . '/' . ($item['snapshot'] ?? '');
        if (!is_file($saved) || digest($saved) !== $record['old']) stopPatch('ROLLBACK_SNAPSHOT_CHANGED');
    }
}
foreach (array_reverse($records) as $record) {
    if ($record['old'] === null) {
        if (!unlink($record['dest'])) stopPatch('ROLLBACK_UNLINK_FAILED');
    } else {
        $saved = $backup . '/' . str_replace('/', '__', $record['path']);
        copyAtomically($saved, $record['dest']);
    }
}
echo "CODE_ONLY_ROLLBACK=PASS\n";
echo "DATABASE_CHANGES=NONE\n";
