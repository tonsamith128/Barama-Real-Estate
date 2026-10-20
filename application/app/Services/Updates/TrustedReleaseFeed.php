<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

// Also supports isolated CLI/CI smoke tests that load this service directly.
require_once __DIR__ . '/PublisherTrustPaths.php';

use Barama\Core\Installation;
use Barama\Services\LicenseManagerClient;
use Closure;
use RuntimeException;

/**
 * Read-only Publisher V2 discovery adapter. Does not download or install ZIPs.
 * Trust: HTTPS authenticated Publisher response + separately pinned Ed25519 public keys.
 * Independent from Publisher's private key registry and customer license storage.
 */
final class TrustedReleaseFeed
{
    public const URL = 'https://tonsamith.com/license/v2/update/manifest';
    private const CONFIG_FILE = '/config/update-feed-v2.json';
    private const KEYS_FILE = '/config/update-publisher-v2-keys.json';
    private const MAX_RESPONSE = 131072;
    private const MAX_ARCHIVE = 536870912;
    private const PREFIX = "TONSAMITH-RELEASE-MANIFEST-V2\n";
    private const MANIFEST_FIELDS = [
        'schema', 'publisher', 'key_id', 'release_id', 'product_slug',
        'version', 'channel', 'release_type', 'release_status', 'package_sha256',
        'package_bytes', 'min_php', 'minimum_app_version',
        'migration_policy', 'published_at', 'expires_at',
    ];

    /** Pure local readiness; no HTTP requests or private state writes. */
    public static function readiness(): array
    {
        $configured = self::configured();
        $keys = self::trustedKeys();
        $keyReady = count($keys) > 0;
        $runtime = extension_loaded('curl') && extension_loaded('sodium');
        return [
            'configured' => $configured,
            'publisher_key' => $keyReady,
            'https_available' => $runtime,
            'can_check' => $configured && $keyReady && $runtime
                && (string)config('license.server_url', '') === 'https://tonsamith.com'
                && (string)config('license.product_slug', '') === 'barama-real-estate',
        ];
    }

    /**
     * @param array<string,mixed> $license
     * @param array<string,mixed> $runtime
     * @param ?Closure(array<string,mixed>):array<string,mixed> $transport TEST ONLY; simulates HTTP reply {http_status,body}
     * @return array{status:string,entitlement:string,feed:string,version:string,checked_at:string}
     */
    public static function check(string $installedVersion, array $license, array $runtime, ?Closure $transport = null, bool $includeSignedEnvelope = false): array
    {
        $ready = self::readiness();
        if (!$ready['configured'] || !$ready['publisher_key']
            || (string)config('license.server_url', '') !== 'https://tonsamith.com'
            || (string)config('license.product_slug', '') !== 'barama-real-estate'
            || ($transport === null && !$ready['can_check'])) {
            throw new RuntimeException('UPDATE_PUBLISHER_NOT_CONFIGURED');
        }
        $id = strtolower(trim((string)($license['installation_id'] ?? '')));
        $actualId = strtolower(Installation::installationId());
        $domain = LicenseManagerClient::canonicalDomain((string)($runtime['APP_URL'] ?? ''));
        $recordedDomain = LicenseManagerClient::canonicalDomain((string)($license['domain'] ?? ''));
        $token = trim((string)($license['activation_token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)
            || !hash_equals($actualId, $id)
            || $domain === '' || !hash_equals($domain, $recordedDomain)
            || strtolower(trim((string)($license['status'] ?? ''))) !== 'active'
            || ($license['product_slug'] ?? null) !== 'barama-real-estate'
            || strlen($token) < 24 || strlen($token) > 512) {
            throw new RuntimeException('UPDATE_LICENSE_BINDING_INVALID');
        }
        if (!self::version($installedVersion)) {
            throw new RuntimeException('UPDATE_INSTALLED_VERSION_INVALID');
        }
        $channel = self::settings()['channel'];
        $payload = [
            'activation_token' => $token,
            'domain' => $domain,
            'installation_id' => $id,
            'product_slug' => 'barama-real-estate',
            'channel' => $channel,
            'current_version' => $installedVersion,
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
        ];
        $reply = $transport !== null ? $transport($payload) : self::request($payload);
        if (!is_array($reply) || !is_int($reply['http_status'] ?? null)
            || !is_array($reply['body'] ?? null) || array_is_list($reply['body'])) {
            throw new RuntimeException('UPDATE_PUBLISHER_RESPONSE_INVALID');
        }
        $status = $reply['http_status'];
        $response = $reply['body'];
        $result = [
            'status' => 'eligible',
            'entitlement' => 'Eligible',
            'feed' => 'No release published',
            'version' => '',
            'checked_at' => gmdate('Y-m-d H:i').' UTC',
        ];
        if ($status === 403 && ($response['success'] ?? null) === false
            && ($response['schema'] ?? null) === 'tonsamith-signed-discovery-v2'
            && in_array(($response['error'] ?? null), ['entitlement_denied', 'entitlement_not_configured'], true)) {
            $result['status'] = 'ineligible';
            // These two authoritative publisher responses must remain distinct.
            // Neither grants entitlement; expose only a safe allowlisted reason.
            $notConfigured = $response['error'] === 'entitlement_not_configured';
            $result['entitlement'] = $notConfigured ? 'Not configured' : 'Not entitled';
            $result['reason'] = $notConfigured ? 'not_configured' : 'denied';
            $result['feed'] = 'Not available';
            return $result;
        }
        if ($status !== 200 || ($response['success'] ?? null) !== true
            || ($response['schema'] ?? null) !== 'tonsamith-signed-discovery-v2'
            || ($response['entitled'] ?? null) !== true
            || !is_bool($response['update_available'] ?? null)) {
            throw new RuntimeException('UPDATE_PUBLISHER_RESPONSE_INVALID');
        }
        if ($response['update_available'] === false) {
            if (array_key_exists('envelope', $response)) throw new RuntimeException('UPDATE_RELEASE_METADATA_INVALID');
            return $result;
        }
        if (!is_array($response['envelope'] ?? null)) {
            throw new RuntimeException('UPDATE_RELEASE_METADATA_INVALID');
        }
        $result['version'] = self::verifyEnvelope($response['envelope'], $installedVersion, $channel);
        // Publisher-signed digest is safe metadata, not an activation token.
        // Bind the exact private staged ZIP to the authenticated discovery result.
        $result['archive_sha256'] = $response['envelope']['manifest']['package_sha256'];
        // Internal CLI downloader only. Existing callers receive no extra fields.
        if ($includeSignedEnvelope) $result['signed_envelope'] = $response['envelope'];
        $result['status'] = 'available';
        $result['feed'] = 'Signed release verified';
        return $result;
    }

    /** Exact match for Publisher ReleaseManifestV2::message() and verify(). */
    public static function verifyEnvelope(array $envelope, string $installedVersion, string $channel, ?int $now = null): string
    {
        if (array_keys($envelope) !== ['manifest', 'signature']
            || !is_array($envelope['manifest']) || !is_array($envelope['signature'])) {
            throw new RuntimeException('UPDATE_RELEASE_METADATA_INVALID');
        }
        $m = $envelope['manifest'];
        $s = $envelope['signature'];
        $fields = array_keys($m);
        $expected = self::MANIFEST_FIELDS;
        sort($fields, SORT_STRING); sort($expected, SORT_STRING);
        if ($fields !== $expected || $m['schema'] !== 'tonsamith-commercial-release-v2'
            || $m['publisher'] !== 'tonsamith.com') {
            throw new RuntimeException('UPDATE_MANIFEST_INVALID');
        }
        foreach (self::MANIFEST_FIELDS as $field) {
            if ($field === 'package_bytes') continue;
            if (!is_string($m[$field]) || strlen($m[$field]) > 190) {
                throw new RuntimeException('UPDATE_MANIFEST_INVALID');
            }
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,90}$/D', $m['key_id'])
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,100}$/D', $m['release_id'])
            || !preg_match('/^[a-z][a-z0-9-]{2,90}$/D', $m['product_slug'])
            || !self::version($m['version']) || !self::version($m['min_php'])
            || ($m['minimum_app_version'] !== '' && !self::version($m['minimum_app_version']))
            || !in_array($m['channel'], ['stable', 'beta'], true)
            || !in_array($m['release_type'], ['update', 'upgrade', 'security'], true)
            || $m['release_status'] !== 'published' || $m['migration_policy'] !== 'additive'
            || !preg_match('/^[a-f0-9]{64}$/D', $m['package_sha256'])
            || !is_int($m['package_bytes']) || $m['package_bytes'] < 1 || $m['package_bytes'] > self::MAX_ARCHIVE
            || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $m['published_at'])
            || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $m['expires_at'])
            || array_keys($s) !== ['algorithm', 'key_id', 'value']
            || ($s['algorithm'] ?? null) !== 'Ed25519'
            || !is_string($s['key_id'] ?? null) || !hash_equals($m['key_id'], $s['key_id'])
            || !is_string($s['value'] ?? null)) {
            throw new RuntimeException('UPDATE_MANIFEST_INVALID');
        }
        $keys = self::trustedKeys();
        $public = $keys[$s['key_id']] ?? null;
        $sig = base64_decode($s['value'], true);
        if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !is_string($sig) || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
            || !function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        }
        $canonical = $m; ksort($canonical, SORT_STRING);
        $message = self::PREFIX . json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!sodium_crypto_sign_verify_detached($sig, $message, $public)) {
            throw new RuntimeException('UPDATE_SIGNATURE_INVALID');
        }
        if (!hash_equals('barama-real-estate', $m['product_slug'])
            || !in_array($channel, ['stable', 'beta'], true)
            || ($channel === 'stable' && $m['channel'] !== 'stable')
            || version_compare($m['version'], $installedVersion, '<=')
            || ($m['minimum_app_version'] !== '' && version_compare($installedVersion, $m['minimum_app_version'], '<'))
            || version_compare(PHP_VERSION, $m['min_php'], '<')
            || $m['migration_policy'] !== 'additive') {
            throw new RuntimeException('UPDATE_RELEASE_INCOMPATIBLE');
        }
        $at = $now ?? time();
        $published = strtotime($m['published_at']); $expires = strtotime($m['expires_at']);
        if ($published === false || $expires === false || $published > $at + 300
            || $expires <= $at || $expires <= $published || $expires - $published > 180 * 86400) {
            throw new RuntimeException('UPDATE_RELEASE_EXPIRED');
        }
        return $m['version'];
    }

    private static function version(string $v): bool
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/D', $v) === 1;
    }

    /** Never follows redirects, limits response size, TLS-verified HTTPS only. */
    private static function request(array $payload): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('UPDATE_TRANSPORT_UNAVAILABLE');
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = '';
        $handle = curl_init(self::URL);
        if ($handle === false) throw new RuntimeException('UPDATE_TRANSPORT_UNAVAILABLE');
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'BaramaRealEstate-Updater/1.5.2-V2',
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE) return 0;
                    $body .= $chunk; return strlen($chunk);
                },
            ]);
            if (defined('CURLOPT_PROTOCOLS_STR')) curl_setopt($handle, constant('CURLOPT_PROTOCOLS_STR'), 'https');
            elseif (defined('CURLOPT_PROTOCOLS')) curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            $ok = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($ok === false || strlen($body) > self::MAX_RESPONSE || !in_array($status, [200, 403], true)) {
                throw new RuntimeException('UPDATE_PUBLISHER_UNAVAILABLE');
            }
        } finally { curl_close($handle); }
        try { $data = json_decode($body, true, 24, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('UPDATE_PUBLISHER_RESPONSE_INVALID'); }
        return ['http_status' => $status, 'body' => $data];
    }

    private static function configured(): bool
    {
        try { self::settings(); return true; }
        catch (RuntimeException) { return false; }
    }

    private static function settings(): array
    {
        $path = PublisherTrustPaths::feed();
        if (!self::safeFile($path, 4096)) throw new RuntimeException('UPDATE_PUBLISHER_NOT_CONFIGURED');
        try { $value = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('UPDATE_PUBLISHER_NOT_CONFIGURED'); }
        if (!is_array($value) || array_keys($value) !== ['enabled', 'contract', 'channel']
            || $value['enabled'] !== true
            || $value['contract'] !== 'tonsamith-signed-discovery-v2'
            || !in_array($value['channel'], ['stable','beta'], true)) {
            throw new RuntimeException('UPDATE_PUBLISHER_NOT_CONFIGURED');
        }
        return $value;
    }

    /** Publisher-format key catalog, independently pinned by the author; never fetched at runtime. */
    private static function trustedKeys(): array
    {
        $path = PublisherTrustPaths::keys();
        if (!self::safeFile($path, 65536) || !extension_loaded('sodium')) return [];
        try { $value = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return []; }
        if (!is_array($value) || array_keys($value) !== ['format','keys']
            || $value['format'] !== 'tonsamith-publisher-keys-v2'
            || !is_array($value['keys']) || count($value['keys']) > 32) return [];
        $result = [];
        foreach ($value['keys'] as $id => $row) {
            if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9-]{2,90}$/D', $id)
                || !is_array($row) || array_keys($row) !== ['public', 'status']
                || !is_string($row['public'])
                || !in_array($row['status'], ['active', 'retired', 'revoked'], true)) return [];
            $public = base64_decode($row['public'], true);
            if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || base64_encode($public) !== $row['public']) return [];
            if ($row['status'] !== 'revoked') $result[$id] = $public;
        }
        return $result;
    }

    private static function safeFile(string $path, int $max): bool
    {
        return PublisherTrustPaths::safeTrustFile($path, $max);
    }
}
