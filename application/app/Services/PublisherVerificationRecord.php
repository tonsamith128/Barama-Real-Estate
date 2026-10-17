<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Installation;

/**
 * Display-only history of an explicit publisher validation.
 *
 * Kept in the existing private, persistent installation license record.
 * It is not an activation token, a renewal, or an update entitlement.
 * Read operations never contact the publisher or mutate license state.
 */
final class PublisherVerificationRecord
{
    private const KEY = 'auto_updater_publisher_verification_v1';
    private const VERIFIED = 'Active license verified';
    private const STATES = [
        self::VERIFIED,
        'Publisher confirmed revoked',
        'Publisher confirmed suspended',
        'Publisher confirmed expired',
    ];

    /** @param array<string,mixed> $license @param array<string,mixed> $runtime
     * @return array{state:string,checked_at:string}|null
     */
    public static function fromState(
        array $license,
        array $runtime,
        string $installationId,
        ?int $now = null
    ): ?array {
        $now ??= time();
        $stored = $license[self::KEY] ?? null;
        if (!is_array($stored)
            || ($stored['version'] ?? null) !== 1
            || !is_int($stored['at'] ?? null)
            || $stored['at'] < 1
            || $stored['at'] > $now + 60
            || !in_array($stored['state'] ?? null, self::STATES, true)
            || !is_string($stored['binding'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $stored['binding']) !== 1
        ) {
            return null;
        }

        $binding = self::binding($license, $runtime, $installationId, $now);
        if ($binding === null || !hash_equals($binding, $stored['binding'])) {
            return null;
        }

        // A blocked Publisher V2 receipt must never coexist with a green
        // Auto Updater badge, even if the earlier V1 check was successful.
        $v2 = $license['publisher_v2_verification'] ?? null;
        if ($stored['state'] === self::VERIFIED
            && is_array($v2)
            && in_array($v2['blocked_reason'] ?? null,
                ['LICENSE_REVOKED', 'LICENSE_SUSPENDED', 'LICENSE_EXPIRED'], true)
            && LicenseManagerClient::canonicalDomain((string) ($v2['domain'] ?? ''))
                === LicenseManagerClient::canonicalDomain((string) ($license['domain'] ?? ''))
        ) {
            return null;
        }

        return [
            'state' => $stored['state'],
            'checked_at' => gmdate('Y-m-d H:i', $stored['at']) . ' UTC',
        ];
    }

    /**
     * Call only following an explicit administrator-triggered validation.
     * Do not overwrite last trusted evidence after a timeout or network error.
     */
    public static function remember(string $state): bool
    {
        if (!in_array($state, self::STATES, true)) {
            return false;
        }

        $license = Installation::licenseState();
        $binding = self::binding(
            $license,
            Installation::runtimeConfig(),
            Installation::installationId()
        );
        if ($binding === null) {
            return false;
        }

        Installation::refreshLicenseState([self::KEY => [
            'version' => 1,
            'state' => $state,
            'at' => time(),
            'binding' => $binding,
        ]]);

        return true;
    }

    /** @return array{state:string,checked_at:string}|null */
    public static function read(): ?array
    {
        return self::fromState(
            Installation::licenseState(),
            Installation::runtimeConfig(),
            Installation::installationId()
        );
    }

    /** @param array<string,mixed> $license @param array<string,mixed> $runtime */
    private static function binding(
        array $license,
        array $runtime,
        string $installationId,
        ?int $now = null
    ): ?string {
        $now ??= time();
        $token = $license['activation_token'] ?? null;
        $savedId = $license['installation_id'] ?? null;
        $product = $license['product_slug'] ?? null;
        $domain = LicenseManagerClient::canonicalDomain((string) ($runtime['APP_URL'] ?? ''));
        $savedDomain = LicenseManagerClient::canonicalDomain((string) ($license['domain'] ?? ''));

        $url = parse_url((string) ($runtime['APP_URL'] ?? ''));
        if (!is_array($url) || ($url['scheme'] ?? null) !== 'https'
            || !is_string($url['host'] ?? null)
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || ($license['publisher_v2_activation'] ?? null) !== null) {
            return null;
        }

        if (!is_string($token) || strlen(trim($token)) < 16 || strlen($token) > 512
            || !is_string($savedId) || preg_match('/^[a-f0-9]{32}$/D', $savedId) !== 1
            || preg_match('/^[a-f0-9]{32}$/D', $installationId) !== 1
            || !hash_equals($installationId, $savedId)
            || $domain === '' || !hash_equals($domain, $savedDomain)
            || $product !== 'barama-real-estate'
            || $product !== (string) config('license.product_slug', '')
            || strtolower((string) ($license['status'] ?? '')) !== 'active'
        ) {
            return null;
        }

        $expiry = $license['expires_at'] ?? null;
        if (is_string($expiry) && trim($expiry) !== '') {
            $timestamp = strtotime($expiry);
            if ($timestamp !== false && $timestamp <= $now) {
                return null;
            }
        }

        return hash_hmac(
            'sha256',
            implode("\0", [$product, $domain, $installationId]),
            $token
        );
    }
}
