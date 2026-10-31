<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Closure;
use Illuminate\Validation\ValidationException;

final class LicenseStatusService
{
    /**
     * The receipt shows the last locally recorded publisher response.
     * It is not live evidence of current entitlement until revalidated.
     */
    public function summary(string $root, string $domain): array
    {
        $domain = PublisherLicenseClient::normalizedDomain($domain);
        $receipt = PublisherLicenseClient::receipt($root);

        if ($receipt === null) {
            return [
                'activated' => false,
                'domain' => $domain,
                'domain_matches' => null,
                'validated_at' => null,
                'source' => null,
                'status' => 'not_activated',
            ];
        }

        return [
            'activated' => true,
            'domain' => $domain,
            'licensed_domain' => (string) ($receipt['domain'] ?? ''),
            'domain_matches' => $domain === PublisherLicenseClient::normalizedDomain(
                (string) ($receipt['domain'] ?? ''),
            ),
            'validated_at' => $receipt['validated_at'] ?? null,
            'source' => $receipt['source'] ?? null,
            'status' => 'last_recorded_active',
        ];
    }

    /**
     * Remote verification occurs only on an explicit administrator action.
     * The old receipt is left intact on a failed network or revocation result,
     * but the controller reports verification failure, never a fresh success.
     *
     * @param Closure|null $testTransport Only for isolated tests.
     */
    public function revalidate(
        string $root, string $domain, ?Closure $testTransport = null,
    ): array {
        $domain = PublisherLicenseClient::normalizedDomain($domain);
        $receipt = PublisherLicenseClient::receipt($root);
        if ($receipt === null) {
            throw ValidationException::withMessages([
                'license' => 'No activation receipt was found. Activate the installation first.',
            ]);
        }

        if (PublisherLicenseClient::normalizedDomain((string) ($receipt['domain'] ?? '')) !== $domain) {
            throw ValidationException::withMessages([
                'license' => 'The recorded license belongs to a different domain. Revalidation is blocked.',
            ]);
        }

        $installationId = PublisherLicenseClient::installationId($root);
        if (!hash_equals($installationId, (string) ($receipt['installation_id'] ?? ''))) {
            throw ValidationException::withMessages([
                'license' => 'The recorded installation identity does not match. No license data was changed.',
            ]);
        }

        $verified = PublisherLicenseClient::verify(
            (string) $receipt['activation_token'],
            $domain,
            $installationId,
            $testTransport,
        );

        PublisherLicenseClient::saveReceipt($root, $verified);

        return $verified;
    }
}
