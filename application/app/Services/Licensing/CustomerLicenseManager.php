<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/**
 * Customer-safe activation and validation. Does not issue keys or grant
 * entitlements. Expects a trusted host-provided installation identity.
 */
final class CustomerLicenseManager
{
    public function __construct(
        private readonly LicenseTransport $transport,
        private readonly LicenseStateStore $store,
        private readonly string $productCode = 'barama-real-estate',
    ) {}

    public function activate(string $key, string $domain, string $installationId): array
    {
        $this->assertDomain($domain);
        $this->assertInstallation($installationId);
        if (!preg_match('/\ABP-[A-Fa-f0-9]{40}\z/D', $key)) {
            throw new LicenseClientException('INVALID_LICENSE_KEY');
        }
        $result = $this->request('activate', [
            'license_key' => strtoupper($key), 'domain' => $domain,
            'installation_id' => $installationId,
        ]);
        $token = $result['activation_token'] ?? null;
        if (!is_string($token) || !preg_match('/\Abpa_[a-f0-9]{64}\z/D', $token)
            || ($result['product_code'] ?? null) !== $this->productCode
            || ($result['domain'] ?? null) !== $domain
            || ($result['status'] ?? null) !== 'active') {
            throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
        }
        $this->store->save([
            'token' => $token, 'domain' => $domain,
            'installation_id' => $installationId, 'product' => $this->productCode,
        ]);
        return ['active' => true, 'domain' => $domain, 'expires_at' => $result['expires_at'] ?? null];
    }

    public function verify(): array
    {
        $state = $this->state();
        $result = $this->request('verify', $this->authorizationBody($state));
        if (($result['valid'] ?? null) !== true
            || ($result['product_code'] ?? null) !== $this->productCode
            || ($result['domain'] ?? null) !== $state['domain']) {
            throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
        }
        return ['valid' => true, 'domain' => $state['domain'], 'expires_at' => $result['expires_at'] ?? null];
    }

    public function deactivate(): void
    {
        $state = $this->state();
        $result = $this->request('deactivate', $this->authorizationBody($state));
        if (($result['deactivated'] ?? null) !== true) {
            throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
        }
        $this->store->clear();
    }

    /** Returns display-safe status; activation tokens are never exposed. */
    public function status(): array
    {
        $state = $this->store->load();
        if ($state === null) return ['configured' => false];
        return ['configured' => true, 'domain' => $state['domain'], 'product_code' => $state['product']];
    }

    /** @return array{token:string,domain:string,installation_id:string,product:string} */
    private function state(): array
    {
        $state = $this->store->load();
        if ($state === null || ($state['product'] ?? '') !== $this->productCode
            || !isset($state['token'], $state['domain'], $state['installation_id'])
            || !is_string($state['token']) || !is_string($state['domain'])
            || !is_string($state['installation_id'])) {
            throw new LicenseClientException('ACTIVATION_REQUIRED', 403);
        }
        $this->assertDomain($state['domain']);
        $this->assertInstallation($state['installation_id']);
        return $state;
    }

    private function authorizationBody(array $state): array
    {
        return ['activation_token' => $state['token'],
            'domain' => $state['domain'], 'installation_id' => $state['installation_id']];
    }

    private function request(string $action, array $input): array
    {
        $reply = $this->transport->post($action, $input);
        $body = $reply['body'];
        if ($reply['status'] !== 200 || ($body['ok'] ?? null) !== true) {
            $code = is_string($body['error'] ?? null) && preg_match('/\A[A-Z_]{3,80}\z/', $body['error'])
                ? $body['error'] : 'LICENSE_REQUEST_REJECTED';
            throw new LicenseClientException($code, $reply['status']);
        }
        if (!is_array($body['result'] ?? null)) {
            throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
        }
        return $body['result'];
    }

    private function assertDomain(string $domain): void
    {
        if (strlen($domain) > 253 || !preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])\z/D', $domain)) {
            throw new LicenseClientException('INVALID_DOMAIN');
        }
    }

    private function assertInstallation(string $id): void
    {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{7,127}\z/D', $id)) {
            throw new LicenseClientException('INVALID_INSTALLATION_ID');
        }
    }
}
