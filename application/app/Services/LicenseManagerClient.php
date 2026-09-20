<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

final class LicenseManagerClient
{
    /** @return array<string,mixed> */
    public function activate(
        string $purchaseCode,
        string $domain,
        string $installationId
    ): array {
        $purchaseCode = trim($purchaseCode);
        $domain = self::canonicalDomain($domain);
        $installationId = strtolower(trim($installationId));

        if (
            strlen($purchaseCode) < 8
            || strlen($purchaseCode) > 120
            || preg_match('/^[A-Za-z0-9-]+$/', $purchaseCode) !== 1
        ) {
            throw new RuntimeException('Enter a valid CodeCanyon purchase code or license key.');
        }

        if ($domain === '') {
            throw new RuntimeException('The installation domain could not be verified.');
        }

        if (preg_match('/^[a-f0-9]{32}$/', $installationId) !== 1) {
            throw new RuntimeException('The installation identifier is invalid.');
        }

        $payload = [
            'purchase_code' => $purchaseCode,
            'product_slug' => (string) config('license.product_slug', 'barama-real-estate'),
            'domain' => $domain,
            'installation_id' => $installationId,
        ];

        $itemId = (int) config('license.envato_item_id', 0);

        if ($itemId > 0) {
            $payload['envato_item_id'] = $itemId;
        }

        $response = $this->request(
            (string) config('license.activate_path', '/license/v1/activate'),
            $payload
        );

        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException(
                'The purchase code could not be verified for Barama Real Estate. Check the code and try again.'
            );
        }

        $token = trim((string) ($response['activation_token'] ?? ''));
        $license = is_array($response['license'] ?? null) ? $response['license'] : [];

        if ($token === '' || $license === []) {
            throw new RuntimeException('Purchase verification returned an incomplete response. Please try again.');
        }

        $expectedProduct = (string) config('license.product_slug', 'barama-real-estate');
        $returnedProduct = trim((string) ($license['product_slug'] ?? $expectedProduct));

        if ($returnedProduct !== $expectedProduct) {
            throw new RuntimeException('This purchase code is not valid for Barama Real Estate.');
        }

        $returnedDomain = self::canonicalDomain((string) ($license['domain'] ?? $domain));

        if ($returnedDomain !== '' && $returnedDomain !== $domain) {
            throw new RuntimeException('This purchase code is not valid for the current domain.');
        }

        $status = strtolower(trim((string) ($license['status'] ?? 'active')));

        if (in_array($status, ['revoked', 'suspended', 'invalid', 'deactivated', 'expired'], true)) {
            throw new RuntimeException('This license is not active.');
        }

        if (
            $itemId > 0
            && isset($license['envato_item_id'])
            && (int) $license['envato_item_id'] !== $itemId
        ) {
            throw new RuntimeException('This purchase code is not valid for Barama Real Estate.');
        }

        return [
            'activation_token' => $token,
            'license_id' => $license['id'] ?? null,
            'product_slug' => $expectedProduct,
            'envato_item_id' => $license['envato_item_id'] ?? ($itemId > 0 ? $itemId : null),
            'license_type' => (string) ($license['license_type'] ?? ''),
            'source' => (string) ($license['source'] ?? ''),
            'is_test' => (bool) ($license['is_test'] ?? false),
            'status' => $status !== '' ? $status : 'active',
            'domain' => $domain,
            'installation_id' => $installationId,
            'validated_at' => gmdate('c'),
        ];
    }

    /** @return array<string,mixed> */
    public function validate(
        string $activationToken,
        string $domain,
        string $installationId
    ): array {
        $activationToken = trim($activationToken);

        if ($activationToken === '') {
            throw new RuntimeException('License validation token is missing.');
        }

        return $this->request(
            (string) config('license.validate_path', '/license/v1/validate'),
            [
                'activation_token' => $activationToken,
                'domain' => self::canonicalDomain($domain),
                'installation_id' => strtolower(trim($installationId)),
            ]
        );
    }

    public static function canonicalDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return '';
        }

        if (str_contains($domain, '://')) {
            $host = parse_url($domain, PHP_URL_HOST);
            $domain = is_string($host) ? $host : '';
        } elseif (str_starts_with($domain, '[') && str_contains($domain, ']')) {
            $end = strpos($domain, ']');
            $domain = $end !== false ? substr($domain, 1, $end - 1) : $domain;
        } else {
            $domain = preg_replace('/:\\d+$/', '', $domain) ?? $domain;
        }

        $domain = rtrim($domain, '.');

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function request(string $path, array $payload): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('Secure purchase verification requires the PHP cURL extension.');
        }

        $base = rtrim((string) config('license.server_url', 'https://tonsamith.com'), '/');
        $url = $base . '/' . ltrim($path, '/');

        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('Purchase verification is unavailable.');
        }

        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException('Purchase verification is temporarily unavailable. Please try again.');
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => max(8, (int) config('license.request_timeout', 15)),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: Barama-Real-Estate/' . (defined('APP_VERSION') ? APP_VERSION : 'installer'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $error !== '') {
            throw new RuntimeException('Purchase verification is temporarily unavailable. Please try again.');
        }

        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Purchase verification is temporarily unavailable. Please try again.');
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'The purchase code could not be verified for Barama Real Estate. Check the code and try again.'
            );
        }

        return $decoded;
    }
}
