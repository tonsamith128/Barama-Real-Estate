<?php

declare(strict_types=1);

/**
 * Unprivileged staging CLI downloader. ALL downloaded material is untrusted
 * until the independent root-owned worker verifies the Ed25519 envelope and
 * archive digest again. No installation, SQL writes or root commands.
 */
$root = '/www/wwwroot/baramastaging.tonsamith.com';
$job = $argv[2] ?? null;
if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid')
    || posix_geteuid() === 0 || count($argv) !== 3
    || ($argv[1] ?? '') !== '--download-published'
    || !is_string($job)
    || preg_match('/^job-[a-f0-9]{32}$/D', $job) !== 1
    || is_link($root) || !is_dir($root) || realpath($root) !== $root) {
    fwrite(STDERR, "ONECLICK_DOWNLOAD_UNPRIVILEGED_CLI_REQUIRED\n");
    exit(70);
}

use Barama\Core\Installation;
use Barama\Services\LicenseManagerClient;
use Barama\Services\Updates\TrustedReleaseFeed;

try {
    require_once $root . '/application/bootstrap/runtime.php';
    $installed = (string) APP_VERSION;
    $license = Installation::licenseState();
    $runtime = Installation::runtimeConfig();
    $feed = TrustedReleaseFeed::check($installed, $license, $runtime, null, true);
    if (($feed['status'] ?? null) !== 'available'
        || ($feed['entitlement'] ?? null) !== 'Eligible'
        || ($feed['feed'] ?? null) !== 'Signed release verified'
        || !is_array($feed['signed_envelope'] ?? null)) {
        throw new RuntimeException('PUBLISHER_RELEASE_NOT_AVAILABLE');
    }
    $env = $feed['signed_envelope'];
    $manifest = $env['manifest'] ?? null;
    if (!is_array($manifest)) throw new RuntimeException('SIGNED_MANIFEST_MISSING');
    $digest = $manifest['package_sha256'] ?? null;
    $size = $manifest['package_bytes'] ?? null;
    $releaseId = $manifest['release_id'] ?? null;
    $version = $manifest['version'] ?? null;
    if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
        || !is_int($size) || $size < 1 || $size > 536870912
        || !is_string($releaseId)
        || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,100}$/D', $releaseId) !== 1
        || !is_string($version)
        || !hash_equals((string) $feed['archive_sha256'], $digest)
        || !hash_equals((string) $feed['version'], $version)) {
        throw new RuntimeException('SIGNED_RELEASE_INCONSISTENT');
    }

    $domain = LicenseManagerClient::canonicalDomain((string) ($runtime['APP_URL'] ?? ''));
    if ($domain === '' || $domain !== LicenseManagerClient::canonicalDomain(
            (string) ($license['domain'] ?? '')
        )) {
        throw new RuntimeException('LICENSE_DOMAIN_MISMATCH');
    }
    $token = $license['activation_token'] ?? null;
    $installationId = $license['installation_id'] ?? null;
    if (!is_string($token) || strlen($token) < 24 || strlen($token) > 512
        || !is_string($installationId)
        || preg_match('/^[a-f0-9]{32}$/D', $installationId) !== 1) {
        throw new RuntimeException('LICENSE_BINDING_INVALID');
    }
    $request = [
        'activation_token' => $token,
        'domain' => $domain,
        'installation_id' => $installationId,
        'product_slug' => 'barama-real-estate',
        'channel' => (string) $manifest['channel'],
        'current_version' => $installed,
        'php_version' => PHP_VERSION,
        'release_id' => $releaseId,
    ];

    $base = $root . '/application/storage/app/updates';
    $chain = $root;
    foreach (['application', 'storage', 'app', 'updates'] as $part) {
        $chain .= '/' . $part;
        if (is_link($chain) || !is_dir($chain)) throw new RuntimeException('DOWNLOAD_PARENT_UNSAFE');
    }
    if ($base !== $chain) throw new RuntimeException('DOWNLOAD_PARENT_CHANGED');
    $dir = $base . '/oneclick-delivery';
    if (!is_dir($dir) && !@mkdir($dir, 0700)) throw new RuntimeException('DOWNLOAD_DIRECTORY_UNAVAILABLE');
    if (is_link($dir) || !is_dir($dir) || (fileperms($dir) & 0077) !== 0) {
        throw new RuntimeException('DOWNLOAD_DIRECTORY_UNSAFE');
    }
    $destination = $dir . '/' . $job;
    if (is_link($destination) || file_exists($destination) || !@mkdir($destination, 0700)) {
        throw new RuntimeException('DOWNLOAD_JOB_ALREADY_EXISTS');
    }

    $temp = $destination . '/.incoming';
    $archive = $destination . '/release.zip';
    $envelopeFile = $destination . '/envelope.json';
    $receipt = $destination . '/receipt.json';
    $stream = null;
    try {
        $stream = @fopen($temp, 'xb');
        if ($stream === false || !chmod($temp, 0600)) {
            throw new RuntimeException('DOWNLOAD_TEMPORARY_FILE_FAILED');
        }
        $seen = 0;
        $hash = hash_init('sha256');
        $transportError = false;
        $curl = curl_init('https://tonsamith.com/license/v2/update/artifact');
        if ($curl === false) throw new RuntimeException('DOWNLOAD_HTTPS_CLIENT_UNAVAILABLE');
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($request, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/zip'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 900,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Barama-OneClick-Unprivileged/1.0',
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (
                    $stream, $size, &$seen, $hash, &$transportError
                ): int {
                    $n = strlen($chunk);
                    if ($n < 1 || $seen + $n > $size) { $transportError = true; return 0; }
                    $offset = 0;
                    while ($offset < $n) {
                        $w = fwrite($stream, substr($chunk, $offset));
                        if (!is_int($w) || $w < 1) { $transportError = true; return 0; }
                        $offset += $w;
                    }
                    $seen += $n;
                    hash_update($hash, $chunk);
                    return $n;
                },
            ]);
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                curl_setopt($curl, constant('CURLOPT_PROTOCOLS_STR'), 'https');
            } else {
                curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $mime = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
            if ($transportError || $ok !== true || $status !== 200
                || !str_starts_with($mime, 'application/zip')) {
                throw new RuntimeException('AUTHENTICATED_ARTIFACT_HTTP_REJECTED');
            }
        } finally {
            unset($curl); // PHP 8.5-compatible cleanup
        }
        if (!fflush($stream) || (function_exists('fsync') && !fsync($stream))) {
            throw new RuntimeException('DOWNLOAD_FLUSH_FAILED');
        }
        fclose($stream);
        $stream = null;
        if ($seen !== $size || !hash_equals($digest, hash_final($hash))
            || !hash_equals($digest, (string) hash_file('sha256', $temp))) {
            throw new RuntimeException('DOWNLOADED_ARCHIVE_DIGEST_MISMATCH');
        }
        if (!link($temp, $archive)) throw new RuntimeException('DOWNLOAD_EXCLUSIVE_STAGE_FAILED');
        unlink($temp);
        foreach ([
            $envelopeFile => ['schema' => 'barama-oneclick-downloaded-envelope-v1', 'envelope' => $env],
            $receipt => ['schema' => 'barama-oneclick-download-receipt-v1',
                'sha256' => $digest, 'bytes' => $size, 'version' => $version],
        ] as $path => $record) {
            $fd = @fopen($path, 'xb');
            if ($fd === false) throw new RuntimeException('DOWNLOAD_RECEIPT_CREATE_FAILED');
            try {
                chmod($path, 0600);
                $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                if (fwrite($fd, $payload) !== strlen($payload) || !fflush($fd)) {
                    throw new RuntimeException('DOWNLOAD_RECEIPT_WRITE_FAILED');
                }
            } finally { fclose($fd); }
        }

        echo json_encode([
            'schema' => 'barama-oneclick-untrusted-download-v1',
            'archive' => $archive,
            'envelope' => $envelopeFile,
            'archive_sha256' => $digest,
            'bytes' => $size,
            'version' => $version,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    } finally {
        if (is_resource($stream)) fclose($stream);
        if (is_file($temp)) @unlink($temp);
        // Failed partial job directories are preserved for forensic inspection,
        // never treated as verified by the worker without complete receipts.
    }
} catch (Throwable) {
    // Never expose activation credentials, domain binding or SQL configuration.
    fwrite(STDERR, "ONECLICK_AUTHENTICATED_DOWNLOAD_BLOCKED\n");
    exit(71);
}
