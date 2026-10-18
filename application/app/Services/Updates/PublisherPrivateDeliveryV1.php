<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Core\Installation;
use Barama\Services\LicenseManagerClient;
use RuntimeException;

/**
 * Phase 4D1: root-only, owner-confirmed, *private* authenticated release download.
 *
 * Never copies artifacts into application staging, installs code, touches SQL or
 * enables automatic updates. Existing publisher, receiver and Ed25519 verification
 * are reused rather than trusting any unsigned network response.
 */
final class PublisherPrivateDeliveryV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const VAULT = '/root/Barama-Publisher-Private-Releases';
    private const MAX_RESPONSE = 131072;

    /** @return array<string,mixed> */
    public static function readiness(string $site): array
    {
        self::guard($site);
        $paths = [
            'version' => $site . '/VERSION',
            'installation' => Installation::installationIdPath(),
            'license' => Installation::licensePath(),
            'publisher_feed' => APP_PATH . '/config/update-feed-v2.json',
            'trusted_keys' => APP_PATH . '/config/update-publisher-v2-keys.json',
        ];
        $missing = [];
        foreach ($paths as $name => $path) {
            if (is_link($path) || !is_file($path) || !is_readable($path)) $missing[] = $name;
        }
        $trust = TrustedReleaseFeed::readiness();
        return [
            'mode' => 'ROOT_PRIVATE_PUBLISHER_DELIVERY_READINESS',
            'site' => 'STAGING_ONLY',
            'required_files_present' => $missing === [],
            'missing' => $missing,
            'publisher_can_check' => $trust['can_check'] ?? false,
            'authenticated_download_attempted' => false,
            'publisher_release_downloaded' => false,
            'installed_version_unchanged' => true,
            'application_writes' => false,
            'database_connections' => false,
            'auto_install_enabled' => false,
        ];
    }

    /**
     * Explicit root-CLI action only. HTTPS publisher endpoints authorize both
     * discovery and artifact download; all archive bytes remain under /root.
     *
     * @param ?callable(array<string,string>):array<string,mixed> $testManifest CI-only
     * @param ?callable(array<string,string>,callable(string):void):void $testArtifact CI-only
     * @return array<string,mixed>
     */
    public static function download(
        string $site,
        string $confirmation,
        ?callable $testManifest = null,
        ?callable $testArtifact = null
    ): array {
        self::guard($site);
        if ($confirmation !== 'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY') {
            throw new RuntimeException('PUBLISHER_DELIVERY_OWNER_CONFIRMATION_REQUIRED');
        }
        if (($testManifest !== null || $testArtifact !== null)
            && !self::ciFixture($site)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_TEST_HOOK_FORBIDDEN');
        }
        $readiness = self::readiness($site);
        if (!$readiness['required_files_present'] || !$readiness['publisher_can_check']) {
            throw new RuntimeException('PUBLISHER_DELIVERY_PREREQUISITES_MISSING');
        }
        $installed = trim((string)file_get_contents($site . '/VERSION'));
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/D', $installed)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_VERSION_INVALID');
        }
        $license = Installation::licenseState();
        $runtime = Installation::runtimeConfig();
        $result = TrustedReleaseFeed::check($installed, $license, $runtime);
        if (($result['status'] ?? null) === 'ineligible') {
            return self::notDownloaded('PUBLISHER_ENTITLEMENT_DENIED');
        }
        if (($result['status'] ?? null) === 'eligible') {
            return self::notDownloaded('NO_NEW_PUBLISHER_RELEASE');
        }
        $expected = $result['archive_sha256'] ?? null;
        $expectedVersion = $result['version'] ?? null;
        if (($result['status'] ?? null) !== 'available'
            || !is_string($expected) || preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1
            || !is_string($expectedVersion)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_SIGNED_DISCOVERY_INVALID');
        }
        // The regular publisher update check already validated the exact
        // active installation ID, product and current canonical domain.
        $domain = LicenseManagerClient::canonicalDomain((string)($runtime['APP_URL'] ?? ''));
        $savedDomain = LicenseManagerClient::canonicalDomain((string)($license['domain'] ?? ''));
        $activation = (string)($license['activation_token'] ?? '');
        $installationId = (string)($license['installation_id'] ?? '');
        if ($domain === '' || !hash_equals($domain, $savedDomain)
            || $activation === '' || strlen($activation) > 512
            || !preg_match('/^[a-f0-9]{32}$/D', $installationId)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_BINDING_CHANGED');
        }
        $channel = self::channel();
        $request = [
            'activation_token' => $activation,
            'domain' => $domain,
            'installation_id' => $installationId,
            'product_slug' => 'barama-real-estate',
            'channel' => $channel,
            'current_version' => $installed,
            'php_version' => PHP_VERSION,
        ];

        $response = $testManifest !== null ? $testManifest($request) : self::requestManifest($request);
        if (!is_array($response) || ($response['success'] ?? null) !== true
            || ($response['schema'] ?? null) !== 'tonsamith-signed-discovery-v2'
            || ($response['entitled'] ?? null) !== true
            || ($response['update_available'] ?? null) !== true
            || !is_array($response['envelope'] ?? null)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_ENTITLEMENT_NOT_CONFIRMED');
        }
        $envelope = $response['envelope'];
        $signedVersion = TrustedReleaseFeed::verifyEnvelope($envelope, $installed, $channel);
        $manifest = $envelope['manifest'];
        if (!hash_equals($expected, (string)($manifest['package_sha256'] ?? ''))
            || !hash_equals($expectedVersion, $signedVersion)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_RELEASE_CHANGED');
        }

        $vault = self::privateVault($site);
        $lockPath = $vault . '/.publisher-delivery.lock';
        // Never follow a pre-existing symlink with fopen, even as root.
        if (is_link($lockPath)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_LOCK_UNAVAILABLE');
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || is_link($lockPath)) {
            if (is_resource($lock)) fclose($lock);
            throw new RuntimeException('PUBLISHER_DELIVERY_LOCK_UNAVAILABLE');
        }
        try {
            @chmod($lockPath, 0600);
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('PUBLISHER_DELIVERY_ALREADY_RUNNING');
            }
            $receipt = PrivateArtifactHttpClientV2::download(
                $request, $envelope, $installed, $channel, $vault, $testArtifact
            );
            if (!hash_equals($expected, (string)($receipt['sha256'] ?? ''))) {
                throw new RuntimeException('PUBLISHER_DELIVERY_RECEIPT_MISMATCH');
            }
            return [
                'status' => 'AUTHENTICATED_ROOT_PRIVATE_ARTIFACT_RECEIVED',
                'archive' => $receipt['archive'],
                'receipt' => $receipt['receipt'],
                'bytes' => $receipt['bytes'],
                'sha256' => $expected,
                'version' => $signedVersion,
                'application_writes' => false,
                'database_connections' => false,
                'auto_install_enabled' => false,
                'installed_version_unchanged' => true,
                'website_release_staged' => false,
            ];
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function notDownloaded(string $status): array
    {
        return [
            'status' => $status,
            'publisher_release_downloaded' => false,
            'application_writes' => false,
            'database_connections' => false,
            'auto_install_enabled' => false,
        ];
    }

    private static function guard(string $site): void
    {
        if (PHP_SAPI !== 'cli' || !function_exists('posix_geteuid')
            || posix_geteuid() !== 0) {
            throw new RuntimeException('PUBLISHER_DELIVERY_ROOT_CLI_REQUIRED');
        }
        if ($site !== self::STAGING && !self::ciFixture($site)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_STAGING_ONLY');
        }
        if (is_link($site) || !is_dir($site) || realpath($site) !== $site
            || !defined('BASE_PATH') || BASE_PATH !== $site
            || !defined('APP_PATH') || APP_PATH !== $site . '/application'
            || is_link(APP_PATH)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_ROOT_UNTRUSTED');
        }
    }

    private static function ciFixture(string $site): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true'
            && getenv('GITHUB_REPOSITORY') === 'tonsamith128/Barama-Real-Estate'
            && preg_match('~^/tmp/barama-publisher-delivery-[a-f0-9]{16}/site$~D', $site) === 1;
    }

    private static function channel(): string
    {
        $path = APP_PATH . '/config/update-feed-v2.json';
        if (is_link($path) || !is_file($path) || !is_readable($path)
            || filesize($path) > 4096) {
            throw new RuntimeException('PUBLISHER_DELIVERY_CHANNEL_UNAVAILABLE');
        }
        try {
            $data = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('PUBLISHER_DELIVERY_CHANNEL_UNAVAILABLE');
        }
        if (!is_array($data) || !in_array($data['channel'] ?? null, ['stable', 'beta'], true)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_CHANNEL_UNAVAILABLE');
        }
        return $data['channel'];
    }

    private static function privateVault(string $site): string
    {
        $path = self::ciFixture($site)
            ? dirname($site) . '/root-private' : self::VAULT;
        if (!is_dir($path) && !@mkdir($path, 0700, true)) {
            throw new RuntimeException('PUBLISHER_DELIVERY_VAULT_UNAVAILABLE');
        }
        $real = realpath($path);
        $st = @stat($path);
        if (is_link($path) || $real !== $path || !is_array($st)
            || (($st['mode'] & 0077) !== 0)
            || (!self::ciFixture($site) && !str_starts_with($path, '/root/'))) {
            throw new RuntimeException('PUBLISHER_DELIVERY_VAULT_UNSAFE');
        }
        return $path;
    }

    /** HTTPS-only, authenticated publisher discovery. Never store request credentials. */
    private static function requestManifest(array $payload): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('PUBLISHER_DELIVERY_CURL_UNAVAILABLE');
        $curl = curl_init(TrustedReleaseFeed::URL);
        if ($curl === false) throw new RuntimeException('PUBLISHER_DELIVERY_CURL_UNAVAILABLE');
        $body = '';
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                curl_setopt($curl, constant('CURLOPT_PROTOCOLS_STR'), 'https');
            } else {
                curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok !== true || $status !== 200 || strlen($body) > self::MAX_RESPONSE) {
                throw new RuntimeException('PUBLISHER_DELIVERY_MANIFEST_NOT_AUTHORIZED');
            }
        } finally {
            curl_close($curl);
        }
        try {
            $parsed = json_decode($body, true, 24, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('PUBLISHER_DELIVERY_MANIFEST_INVALID');
        }
        if (!is_array($parsed)) throw new RuntimeException('PUBLISHER_DELIVERY_MANIFEST_INVALID');
        return $parsed;
    }
}
