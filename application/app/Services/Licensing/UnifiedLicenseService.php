<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

use Barama\Core\Installation;
use Barama\Services\LicenseManagerClient;
use RuntimeException;

/**
 * Canonical customer licensing boundary, shared with the pre-existing one-click
 * installer and Auto Updater. Reuses Installation::licenseState() and the
 * installed LicenseManagerClient; never issues a second Publisher V2 token.
 *
 * This service does not read, log, render or retain a purchase code after the
 * explicit activation request. It does not make network requests on GET.
 */
final class UnifiedLicenseService
{
    private const PRODUCT = 'barama-real-estate';

    /** @return array<string,mixed> */
    public function localStatus(): array
    {
        $receipt = Installation::licenseState();
        if (!$this->hasReceipt($receipt)) {
            return [
                'configured' => false, 'source' => 'canonical_v1',
                'state' => 'pending', 'status_title' => 'Activation required',
                'status_hint' => 'Use your CodeCanyon purchase code or issued license key',
            ];
        }

        if (($receipt['status'] ?? null) === 'deactivated'
            && trim((string) ($receipt['activation_token'] ?? '')) === '') {
            return [
                'configured' => false, 'source' => 'canonical_v1',
                'state' => 'pending', 'status_title' => 'Activation required',
                'status_hint' => 'Previously deactivated; a valid key is required to reactivate',
            ];
        }

        $valid = $this->receiptMatches($receipt);
        $last = $this->verifiedTimestamp($receipt);
        $intervalHours = max(1, min(168, (int) config('license.validation_interval_hours', 24)));
        $recent = $valid && $last !== null && time() - $last <= $intervalHours * 3600;
        $entrypoint = ($receipt['activation_entrypoint'] ?? null) === 'license_center'
            ? 'License & Activation' : 'One-click Installer / Auto Updater';
        $status = strtolower(trim((string) ($receipt['status'] ?? '')));
        $statusTitle = !$valid
            ? (in_array($status, ['revoked', 'suspended', 'expired'], true)
                ? 'License ' . $status : 'License needs attention')
            : ($recent ? 'License verified' : 'Verification recommended');

        return [
            'configured' => true,
            'source' => 'installer_v1',
            'installer_recognized' => $valid,
            'activation_entrypoint' => $entrypoint,
            'state' => !$valid ? 'error' : ($recent ? 'verified' : 'pending'),
            'status_title' => $statusTitle,
            'status_hint' => !$valid ? 'Existing activation requires review'
                : ($recent ? 'Verified activation shared across all licensing screens'
                    : 'Installed license recognized; last verification is older'),
            'domain' => LicenseManagerClient::canonicalDomain((string) ($receipt['domain'] ?? '')),
            'product_code' => (string) ($receipt['product_slug'] ?? ''),
            'last_verified_at' => $last === null ? null : gmdate('Y-m-d H:i:s', $last) . ' UTC',
            'expires_at' => is_string($receipt['expires_at'] ?? null) ? $receipt['expires_at'] : null,
        ];
    }

    /** @return array<string,mixed> */
    public function run(string $action, array $input): array
    {
        return match ($action) {
            'activate' => $this->activate($input),
            'verify' => $this->verify(),
            'deactivate' => $this->deactivate(),
            default => throw new LicenseClientException('LICENSE_ACTION_NOT_FOUND', 404),
        };
    }

    /** @param array<string,mixed> $receipt */
    public function hasReceipt(array $receipt): bool
    {
        return array_key_exists('activation_token', $receipt)
            || array_key_exists('license_id', $receipt)
            || array_key_exists('verification_method', $receipt);
    }

    /** @return array<string,mixed> */
    private function activate(array $input): array
    {
        $receipt = Installation::licenseState();
        $local = $this->localStatus();
        if (($local['installer_recognized'] ?? false) === true) {
            throw new LicenseClientException('LICENSE_ALREADY_ACTIVE', 409);
        }
        if ($this->hasReceipt($receipt)
            && !(($receipt['status'] ?? null) === 'deactivated'
                && trim((string) ($receipt['activation_token'] ?? '')) === '')) {
            throw new LicenseClientException('EXISTING_LICENSE_NEEDS_REVIEW', 409);
        }
        $key = $input['license_key'] ?? $input['purchase_code'] ?? null;
        if (!is_string($key)) {
            throw new LicenseClientException('INVALID_LICENSE_KEY', 422);
        }
        $key = trim($key);
        if (strlen($key) < 8 || strlen($key) > 120
            || preg_match('/^[A-Za-z0-9-]+$/D', $key) !== 1) {
            throw new LicenseClientException('INVALID_LICENSE_KEY', 422);
        }
        [$domain, $id] = $this->identity();
        try {
            $activated = (new LicenseManagerClient())->activate($key, $domain, $id, 'auto');
        } catch (RuntimeException) {
            throw new LicenseClientException('LICENSE_ACTIVATION_FAILED', 422);
        } finally {
            unset($key);
        }
        if (!is_string($activated['activation_token'] ?? null)
            || trim($activated['activation_token']) === ''
            || ($activated['product_slug'] ?? null) !== $this->product()
            || LicenseManagerClient::canonicalDomain((string) ($activated['domain'] ?? '')) !== $domain
            || ($activated['installation_id'] ?? null) !== $id
            || ($activated['status'] ?? null) !== 'active') {
            throw new LicenseClientException('LICENSE_RESPONSE_MISMATCH', 503);
        }
        // The pre-existing updater and installer read this exact private receipt.
        // The original purchase code is not included in persisted fields.
        Installation::refreshLicenseState([
            'activation_token' => $activated['activation_token'],
            'license_id' => $activated['license_id'] ?? null,
            'product_slug' => $activated['product_slug'],
            'envato_item_id' => $activated['envato_item_id'] ?? null,
            'license_type' => $activated['license_type'] ?? '',
            'source' => $activated['source'] ?? '',
            'is_test' => (bool) ($activated['is_test'] ?? false),
            'status' => 'active',
            'domain' => $domain,
            'installation_id' => $id,
            'verification_method' => $activated['verification_method'] ?? 'direct',
            'validated_at' => $activated['validated_at'] ?? gmdate('c'),
            'last_validated_at' => gmdate('c'),
            'activation_entrypoint' => 'license_center',
        ]);
        return ['source' => 'canonical_v1', 'status' => 'active', 'domain' => $domain];
    }

    /** @return array<string,mixed> */
    private function verify(): array
    {
        $receipt = Installation::licenseState();
        if (!$this->receiptMatches($receipt)) {
            throw new LicenseClientException('ACTIVATION_REQUIRED_OR_INVALID', 409);
        }
        [$domain, $id] = $this->identity();
        try {
            $validated = (new LicenseManagerClient())->validateActive(
                (string) $receipt['activation_token'], $domain, $id
            );
        } catch (RuntimeException) {
            // A network failure must never delete or replace an existing token.
            throw new LicenseClientException('LICENSE_VERIFICATION_UNAVAILABLE', 503);
        }
        if (($validated['status'] ?? null) !== 'active'
            || ($validated['product_slug'] ?? null) !== $this->product()
            || LicenseManagerClient::canonicalDomain((string) ($validated['domain'] ?? '')) !== $domain
            || (!empty($validated['installation_id']) && $validated['installation_id'] !== $id)) {
            throw new LicenseClientException('LICENSE_RESPONSE_MISMATCH', 503);
        }
        Installation::refreshLicenseState(['status' => 'active', 'last_validated_at' => gmdate('c')]);
        return ['source' => 'canonical_v1', 'status' => 'active', 'domain' => $domain];
    }

    /** @return array<string,mixed> */
    private function deactivate(): array
    {
        $receipt = Installation::licenseState();
        if (!$this->receiptMatches($receipt)) {
            throw new LicenseClientException('ACTIVATION_REQUIRED_OR_INVALID', 409);
        }
        [$domain, $id] = $this->identity();
        try {
            (new LicenseManagerClient())->deactivate(
                (string) $receipt['activation_token'], $domain, $id
            );
        } catch (RuntimeException) {
            throw new LicenseClientException('LICENSE_DEACTIVATION_UNAVAILABLE', 503);
        }
        // Never clear local activation before successful remote deactivation.
        Installation::refreshLicenseState([
            'activation_token' => '', 'status' => 'deactivated',
            'last_validated_at' => null, 'deactivated_at' => gmdate('c'),
        ]);
        return ['source' => 'canonical_v1', 'status' => 'deactivated', 'domain' => $domain];
    }

    /** @param array<string,mixed> $receipt */
    private function receiptMatches(array $receipt): bool
    {
        [$domain, $id] = $this->identity();
        return is_string($receipt['activation_token'] ?? null)
            && strlen(trim($receipt['activation_token'])) >= 16
            && ($receipt['product_slug'] ?? null) === $this->product()
            && LicenseManagerClient::canonicalDomain((string) ($receipt['domain'] ?? '')) === $domain
            && hash_equals($id, (string) ($receipt['installation_id'] ?? ''))
            && in_array($receipt['verification_method'] ?? null, ['direct', 'envato'], true)
            && strtolower(trim((string) ($receipt['status'] ?? ''))) === 'active';
    }

    /** @return array{string,string} */
    private function identity(): array
    {
        if (!Installation::isInstalled()) {
            throw new LicenseClientException('INSTALLATION_REQUIRED', 409);
        }
        $url = (string) config('app.url', '');
        $components = parse_url($url);
        if (!is_array($components) || ($components['scheme'] ?? null) !== 'https'
            || !is_string($components['host'] ?? null)
            || $components['host'] === '' || isset($components['user']) || isset($components['pass'])) {
            throw new LicenseClientException('CANONICAL_HTTPS_URL_REQUIRED', 503);
        }
        $host = LicenseManagerClient::canonicalDomain($components['host']);
        $id = Installation::installationId();
        if ($host === '' || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new LicenseClientException('INSTALLATION_IDENTITY_INVALID', 503);
        }
        return [$host, $id];
    }

    private function product(): string
    {
        return (string) config('license.product_slug', self::PRODUCT);
    }

    /** @param array<string,mixed> $receipt */
    private function verifiedTimestamp(array $receipt): ?int
    {
        $date = $receipt['last_validated_at'] ?? $receipt['validated_at'] ?? null;
        if (!is_string($date) || trim($date) === '') {
            return null;
        }
        $time = strtotime($date);
        return is_int($time) && $time > 0 && $time <= time() ? $time : null;
    }
}
