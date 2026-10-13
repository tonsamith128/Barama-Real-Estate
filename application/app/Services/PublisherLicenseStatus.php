<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

/** Read-only, fail-closed publisher verification; does not mutate persisted license records. */
final class PublisherLicenseStatus
{
    /** @param array<string,mixed> $local @param array<string,mixed> $runtime
     *  @param callable(string,string,string):array<string,mixed> $validator
     *  @return array{state:string}
     */
    public static function probe(array $local, array $runtime, callable $validator): array
    {
        $token = $local['activation_token'] ?? null;
        $installation = $local['installation_id'] ?? null;
        if (!is_string($token) || $token === '' || strlen($token) > 512
            || !is_string($installation) || preg_match('/^[a-f0-9]{32}$/D', $installation) !== 1) {
            return ['state' => 'License not configured'];
        }
        $domain = LicenseManagerClient::canonicalDomain((string) ($runtime['APP_URL'] ?? ''));
        $storedDomain = LicenseManagerClient::canonicalDomain((string) ($local['domain'] ?? ''));
        if ($domain !== 'baramarealestate.tonsamith.com' || $storedDomain !== $domain
            || ($local['product_slug'] ?? null) !== 'barama-real-estate') {
            return ['state' => 'License binding mismatch'];
        }
        try {
            // The existing *raw validate* returns a server response, including non-active states.
            // We do not treat an unconfirmed or malformed response as authentic status evidence.
            $response = $validator($token, $domain, $installation);
            if (!is_array($response) || ($response['success'] ?? null) !== true
                || !is_array($response['license'] ?? null)) {
                return ['state' => 'Unable to verify'];
            }
            $license = $response['license'];
            if (($license['product_slug'] ?? null) !== 'barama-real-estate'
                || LicenseManagerClient::canonicalDomain((string) ($license['domain'] ?? '')) !== $domain
                || (isset($license['installation_id']) && $license['installation_id'] !== $installation)) {
                return ['state' => 'License binding mismatch'];
            }
            return match (strtolower((string) ($license['status'] ?? ''))) {
                'active' => ['state' => 'Active license verified'],
                'revoked' => ['state' => 'Publisher confirmed revoked'],
                'suspended' => ['state' => 'Publisher confirmed suspended'],
                'expired' => ['state' => 'Publisher confirmed expired'],
                default => ['state' => 'Unable to verify'],
            };
        } catch (RuntimeException $exception) {
            if (LicenseManagerClient::isTemporaryFailure($exception)) {
                return ['state' => 'Publisher temporarily unavailable'];
            }
            return ['state' => 'Unable to verify'];
        } catch (\Throwable) {
            return ['state' => 'Unable to verify'];
        }
    }
}
