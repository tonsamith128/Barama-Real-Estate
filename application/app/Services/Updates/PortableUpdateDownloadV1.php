<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Core\Installation;
use Barama\Services\LicenseManagerClient;
use Closure;
use RuntimeException;

/**
 * A bounded, authenticated Publisher V2 ZIP downloader for ordinary PHP hosting.
 *
 * No root/CLI/cron/systemd. Does NOT install, migrate, create backups or
 * authorize Update Now. The administrator controller must enforce auth/CSRF
 * before calling this service. Private vault is supplied by trusted server
 * configuration, NEVER a request parameter, and MUST be outside site root.
 */
final class PortableUpdateDownloadV1
{
    private const ARTIFACT_URL = 'https://tonsamith.com/license/v2/update/artifact';
    private const MAX_ZIP = 8388608; // File-only initial customer MVP.
    private const MAX_SECONDS = 90;

    /**
     * TEST-only closures require PHP CLI + BARAMA_PORTABLE_FIXTURE_ONLY.
     *
     * @param ?Closure(string):array $testDiscovery
     * @param ?Closure(array,callable):array $testTransfer
     * @return array{job_id:string,archive:string,envelope:string,receipt:string,bytes:int,sha256:string,version:string,state:string}
     */
    public static function fetch(
        string $site, string $vault, string $installedVersion,
        ?Closure $testDiscovery = null, ?Closure $testTransfer = null
    ): array {
        if (PublisherTrustPaths::isProtectedStaging()) {
            throw new RuntimeException('PORTABLE_STAGING_ROOT_WORKER_ONLY');
        }
        if (($testDiscovery !== null || $testTransfer !== null)
            && (PHP_SAPI !== 'cli' || !defined('BARAMA_PORTABLE_FIXTURE_ONLY')
                || BARAMA_PORTABLE_FIXTURE_ONLY !== true)) {
            throw new RuntimeException('PORTABLE_TEST_TRANSPORT_FORBIDDEN');
        }
        self::safeRoots($site, $vault);
        $versionPath = $site . '/VERSION';
        if (is_link($versionPath) || !is_file($versionPath)
            || trim((string) file_get_contents($versionPath)) !== $installedVersion) {
            throw new RuntimeException('PORTABLE_INSTALLED_VERSION_CHANGED');
        }
        $license = $testDiscovery === null ? Installation::licenseState() : [];
        $runtime = $testDiscovery === null ? Installation::runtimeConfig() : [];
        $feed = $testDiscovery !== null ? $testDiscovery($installedVersion) :
            TrustedReleaseFeed::check($installedVersion, $license, $runtime, null, true);
        if (!is_array($feed) || ($feed['status'] ?? '') !== 'available'
            || ($feed['entitlement'] ?? '') !== 'Eligible'
            || ($feed['feed'] ?? '') !== 'Signed release verified'
            || !is_array($feed['signed_envelope'] ?? null)) {
            throw new RuntimeException('PORTABLE_AUTHORIZED_RELEASE_UNAVAILABLE');
        }
        $envelope = $feed['signed_envelope'];
        $m = $envelope['manifest'] ?? null;
        if (!is_array($m) || !is_string($m['channel'] ?? null)) {
            throw new RuntimeException('PORTABLE_SIGNED_METADATA_INVALID');
        }
        $version = TrustedReleaseFeed::verifyEnvelope($envelope, $installedVersion, $m['channel']);
        $size = $m['package_bytes'];
        $digest = $m['package_sha256'];
        if (!is_int($size) || $size < 1 || $size > self::MAX_ZIP
            || !is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
            || ($feed['archive_sha256'] ?? null) !== $digest
            || ($feed['version'] ?? null) !== $version) {
            throw new RuntimeException('PORTABLE_SIGNED_ZIP_NOT_SUPPORTED');
        }

        // The release ID is never a destination path. Always random and
        // exclusively created inside the application-chosen private vault.
        $id = 'job-' . bin2hex(random_bytes(16));
        $dir = $vault . '/' . $id;
        if (is_link($dir) || file_exists($dir) || !@mkdir($dir, 0700)) {
            throw new RuntimeException('PORTABLE_STAGE_JOB_CONFLICT');
        }
        $incoming = $dir . '/.incoming';
        $archive = $dir . '/release.zip';
        $envPath = $dir . '/envelope.json';
        $receipt = $dir . '/receipt.json';
        $handle = null;
        try {
            $handle = @fopen($incoming, 'xb');
            if ($handle === false || !@chmod($incoming, 0600)) {
                throw new RuntimeException('PORTABLE_DOWNLOAD_STAGE_UNWRITABLE');
            }
            $count = 0;
            $hash = hash_init('sha256');
            $writer = static function (string $chunk) use ($handle, $size, &$count, $hash): void {
                $len = strlen($chunk);
                if ($len < 1 || $count + $len > $size) {
                    throw new RuntimeException('PORTABLE_DOWNLOAD_TOO_LARGE');
                }
                $offset = 0;
                while ($offset < $len) {
                    $written = fwrite($handle, substr($chunk, $offset));
                    if (!is_int($written) || $written < 1) {
                        throw new RuntimeException('PORTABLE_DOWNLOAD_IO_FAILED');
                    }
                    $offset += $written;
                }
                $count += $len;
                hash_update($hash, $chunk);
            };
            $payload = $testDiscovery === null
                ? self::publisherPayload($installedVersion, $license, $runtime, $m)
                : ['fixture' => true, 'release_id' => $m['release_id']];
            $response = $testTransfer !== null
                ? $testTransfer($payload, $writer)
                : self::transfer($payload, $writer);
            if (!is_array($response) || ($response['status'] ?? null) !== 200
                || ($response['mime'] ?? null) !== 'application/zip') {
                throw new RuntimeException('PORTABLE_ARTIFACT_HTTP_REJECTED');
            }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new RuntimeException('PORTABLE_DOWNLOAD_FLUSH_FAILED');
            }
            fclose($handle);
            $handle = null;
            if ($count !== $size
                || !hash_equals($digest, hash_final($hash))
                || !hash_equals($digest, (string) hash_file('sha256', $incoming))) {
                throw new RuntimeException('PORTABLE_DOWNLOAD_DIGEST_MISMATCH');
            }
            if (is_link($archive) || file_exists($archive)
                || !rename($incoming, $archive)) {
                throw new RuntimeException('PORTABLE_DOWNLOAD_STAGE_CONFLICT');
            }
            // Store the original signed envelope, not a fabricated one.
            self::exclusiveJson($envPath, $envelope);
            self::exclusiveJson($receipt, [
                'schema' => 'barama-portable-artifact-v1',
                'job_id' => $id,
                'release_id' => $m['release_id'],
                'version' => $version,
                'archive_sha256' => $digest,
                'archive_bytes' => $size,
                'state' => 'verified_download_not_installed',
            ]);
            return [
                'job_id' => $id, 'archive' => $archive,
                'envelope' => $envPath, 'receipt' => $receipt,
                'bytes' => $size, 'sha256' => $digest, 'version' => $version,
                'state' => 'verified_download_not_installed',
            ];
        } catch (\Throwable) {
            // Any partial directory remains private, never eligible for apply.
            throw new RuntimeException('PORTABLE_DOWNLOAD_FAILED_PRIVATE_STAGE_REVIEW');
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_file($incoming)) @unlink($incoming);
        }
    }

    private static function publisherPayload(
        string $installed, array $license, array $runtime, array $manifest
    ): array {
        $id = strtolower(trim((string) ($license['installation_id'] ?? '')));
        $domain = LicenseManagerClient::canonicalDomain((string) ($runtime['APP_URL'] ?? ''));
        $recorded = LicenseManagerClient::canonicalDomain((string) ($license['domain'] ?? ''));
        $token = $license['activation_token'] ?? null;
        if (!is_string($token) || strlen($token) < 24 || strlen($token) > 512
            || !is_string($id) || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1
            || $domain === '' || !hash_equals($domain, $recorded)
            || !hash_equals($id, strtolower(Installation::installationId()))
            || ($license['product_slug'] ?? null) !== 'barama-real-estate'
            || strtolower((string) ($license['status'] ?? '')) !== 'active') {
            throw new RuntimeException('PORTABLE_LICENSE_BINDING_INVALID');
        }
        return [
            'activation_token' => $token,
            'domain' => $domain,
            'installation_id' => $id,
            'product_slug' => 'barama-real-estate',
            'channel' => $manifest['channel'],
            'current_version' => $installed,
            'php_version' => PHP_VERSION,
            'release_id' => $manifest['release_id'],
        ];
    }

    /** Fixed HTTPS endpoint, strict TLS, no redirects, bounded streaming. */
    private static function transfer(array $payload, callable $writer): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PORTABLE_CURL_UNAVAILABLE');
        }
        $curl = curl_init(self::ARTIFACT_URL);
        if ($curl === false) throw new RuntimeException('PORTABLE_CURL_UNAVAILABLE');
        $callbackFailed = false;
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/zip'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => self::MAX_SECONDS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Barama-Portable-AutoUpdater/1.0',
                CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use ($writer, &$callbackFailed): int {
                    try { $writer($chunk); return strlen($chunk); }
                    catch (\Throwable) { $callbackFailed = true; return 0; }
                },
            ]);
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                curl_setopt($curl, constant('CURLOPT_PROTOCOLS_STR'), 'https');
            } else {
                curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $mime = strtolower(trim(explode(';', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
            if ($callbackFailed || $ok !== true) {
                throw new RuntimeException('PORTABLE_ARTIFACT_TRANSFER_FAILED');
            }
            return ['status' => $status, 'mime' => $mime];
        } finally {
            // PHP 8.5: CurlHandle is released by dropping references.
            unset($curl);
        }
    }

    private static function safeRoots(string $site, string $vault): void
    {
        if ($site === '/' || is_link($site) || !is_dir($site) || realpath($site) !== $site
            || is_link($vault) || !is_dir($vault) || realpath($vault) !== $vault
            || (fileperms($vault) & 0077) !== 0 || !is_writable($vault)
            || $site === $vault
            || str_starts_with($site . '/', $vault . '/')
            || str_starts_with($vault . '/', $site . '/')) {
            throw new RuntimeException('PORTABLE_PRIVATE_VAULT_UNSAFE');
        }
    }

    private static function exclusiveJson(string $path, array $data): void
    {
        $file = @fopen($path, 'xb');
        if ($file === false) throw new RuntimeException('PORTABLE_DOWNLOAD_RECEIPT_FAILED');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('PORTABLE_DOWNLOAD_RECEIPT_FAILED');
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($file, $json) !== strlen($json) || !fflush($file)
                || (function_exists('fsync') && !fsync($file))) {
                throw new RuntimeException('PORTABLE_DOWNLOAD_RECEIPT_FAILED');
            }
        } finally { fclose($file); }
    }
}
