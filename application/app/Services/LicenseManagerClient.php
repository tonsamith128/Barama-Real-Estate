<?php

declare(strict_types=1);

namespace Barama\Services;

use Closure;
use RuntimeException;

final class LicenseManagerClient
{
    public const ERROR_TEMPORARY_UNAVAILABLE = 50301;

    public static function isTemporaryFailure(RuntimeException $exception): bool
    {
        return $exception->getCode() === self::ERROR_TEMPORARY_UNAVAILABLE;
    }

    public static function isTemporaryHttpStatus(int $status): bool
    {
        return $status === 0
            || in_array($status, [408, 425, 429], true)
            || $status >= 500;
    }

    /**
     * Optional transport is used only by deterministic contract QA.
     *
     * @param Closure(string,array<string,mixed>): array<string,mixed>|null $transport
     */
    public function __construct(
        private readonly ?Closure $transport = null
    ) {
    }

    /** @return array<string,mixed> */
    public function activate(
        string $purchaseCode,
        string $domain,
        string $installationId,
        string $verificationMethod = 'auto'
    ): array {
        $purchaseCode = trim($purchaseCode);
        $domain = self::canonicalDomain($domain);
        $installationId = self::normalizeInstallationId($installationId);
        $verificationMethod = $this->resolveVerificationMethod(
            $purchaseCode,
            $verificationMethod
        );

        if (
            strlen($purchaseCode) < 8
            || strlen($purchaseCode) > 120
            || preg_match('/^[A-Za-z0-9-]+$/', $purchaseCode) !== 1
        ) {
            throw new RuntimeException(
                'Enter a valid license key or CodeCanyon purchase code.'
            );
        }

        $this->assertDomainAndInstallation($domain, $installationId);

        $payload = [
            'purchase_code' => $purchaseCode,
            'product_slug' => (string) config(
                'license.product_slug',
                'barama-real-estate'
            ),
            'domain' => $domain,
            'installation_id' => $installationId,
            'verification_method' => $verificationMethod,
        ];

        $itemId = (int) config('license.envato_item_id', 0);

        if ($itemId > 0) {
            $payload['envato_item_id'] = $itemId;
        }

        $response = $this->request(
            (string) config(
                'license.activate_path',
                '/license/v1/activate'
            ),
            $payload,
            'The license could not be verified for Barama Real Estate. Check the code and try again.'
        );

        $token = trim((string) ($response['activation_token'] ?? ''));
        $license = is_array($response['license'] ?? null)
            ? $response['license']
            : [];

        if (($response['success'] ?? false) !== true) {
            if (
                $token === ''
                || $license === []
                || !$this->isReusableSameDomainActivation(
                    $license,
                    $domain,
                    $installationId
                )
            ) {
                throw new RuntimeException(
                    $this->activationFailureMessage($response, $domain)
                );
            }
        }

        if ($token === '' || $license === []) {
            throw new RuntimeException(
                'License verification returned an incomplete response. Please try again.'
            );
        }

        $normalized = $this->assertActiveLicense(
            $license,
            $domain,
            $installationId
        );

        return [
            'activation_token' => $token,
            'license_id' => $license['id'] ?? null,
            'product_slug' => $normalized['product_slug'],
            'envato_item_id' => $license['envato_item_id']
                ?? ($itemId > 0 ? $itemId : null),
            'license_type' => (string) ($license['license_type'] ?? ''),
            'source' => (string) ($license['source'] ?? ''),
            'is_test' => (bool) ($license['is_test'] ?? false),
            'status' => $normalized['status'],
            'domain' => $domain,
            'installation_id' => $installationId,
            'verification_method' => $verificationMethod,
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
        $domain = self::canonicalDomain($domain);
        $installationId = self::normalizeInstallationId($installationId);

        if ($activationToken === '') {
            throw new RuntimeException(
                'License validation token is missing.'
            );
        }

        $this->assertDomainAndInstallation($domain, $installationId);

        return $this->request(
            (string) config(
                'license.validate_path',
                '/license/v1/validate'
            ),
            [
                'activation_token' => $activationToken,
                'domain' => $domain,
                'installation_id' => $installationId,
            ],
            'License validation could not be completed. Please try again.'
        );
    }

    /** @return array<string,mixed> */
    public function validateActive(
        string $activationToken,
        string $domain,
        string $installationId
    ): array {
        $domain = self::canonicalDomain($domain);
        $installationId = self::normalizeInstallationId($installationId);
        $response = $this->validate(
            $activationToken,
            $domain,
            $installationId
        );

        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException(
                $this->validationFailureMessage($response, $domain)
            );
        }

        $license = is_array($response['license'] ?? null)
            ? $response['license']
            : [];

        if ($license === []) {
            throw new RuntimeException(
                'License validation returned an incomplete response.'
            );
        }

        return $this->assertActiveLicense(
            $license,
            $domain,
            $installationId
        );
    }

    /** @return array<string,mixed> */
    public function deactivate(
        string $activationToken,
        string $domain,
        string $installationId
    ): array {
        $activationToken = trim($activationToken);
        $domain = self::canonicalDomain($domain);
        $installationId = self::normalizeInstallationId($installationId);

        if ($activationToken === '') {
            throw new RuntimeException(
                'License deactivation token is missing.'
            );
        }

        $this->assertDomainAndInstallation($domain, $installationId);

        $response = $this->request(
            (string) config(
                'license.deactivate_path',
                '/license/v1/deactivate'
            ),
            [
                'activation_token' => $activationToken,
                'domain' => $domain,
                'installation_id' => $installationId,
            ],
            'License deactivation could not be completed. Please try again.'
        );

        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException(
                $this->deactivationFailureMessage($response)
            );
        }

        return $response;
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
        } elseif (
            str_starts_with($domain, '[')
            && str_contains($domain, ']')
        ) {
            $end = strpos($domain, ']');
            $domain = $end !== false
                ? substr($domain, 1, $end - 1)
                : $domain;
        } else {
            $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
        }

        $domain = rtrim($domain, '.');

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    /**
     * @param array<string,mixed> $license
     * @return array<string,mixed>
     */
    private function assertActiveLicense(
        array $license,
        string $domain,
        string $installationId
    ): array {
        $expectedProduct = (string) config(
            'license.product_slug',
            'barama-real-estate'
        );
        $returnedProduct = trim(
            (string) ($license['product_slug'] ?? '')
        );

        if ($returnedProduct === '' || $returnedProduct !== $expectedProduct) {
            throw new RuntimeException(
                'This license is not valid for Barama Real Estate.'
            );
        }

        $returnedDomain = self::canonicalDomain(
            (string) ($license['domain'] ?? '')
        );

        if ($returnedDomain === '' || $returnedDomain !== $domain) {
            throw new RuntimeException(
                'This license is not valid for the current domain.'
            );
        }

        $returnedInstallation = trim(
            (string) ($license['installation_id'] ?? '')
        );

        if ($returnedInstallation !== '') {
            $returnedInstallation = self::normalizeInstallationId(
                $returnedInstallation
            );

            if (
                preg_match('/^[a-f0-9]{32}$/', $returnedInstallation) !== 1
                || $returnedInstallation !== $installationId
            ) {
                throw new RuntimeException(
                    'This license is not valid for this installation.'
                );
            }
        }

        $status = strtolower(trim(
            (string) ($license['status'] ?? '')
        ));

        if ($status !== 'active') {
            throw new RuntimeException('This license is not active.');
        }

        $itemId = (int) config('license.envato_item_id', 0);

        if (
            $itemId > 0
            && isset($license['envato_item_id'])
            && (int) $license['envato_item_id'] !== $itemId
        ) {
            throw new RuntimeException(
                'This license is not valid for Barama Real Estate.'
            );
        }

        return array_merge($license, [
            'product_slug' => $expectedProduct,
            'domain' => $domain,
            'installation_id' => $installationId,
            'status' => $status !== '' ? $status : 'active',
        ]);
    }

    /**
     * A duplicate activation is idempotent only when the License Manager
     * returns the existing token and an explicitly active license bound to
     * this exact product/domain/installation contract.
     *
     * @param array<string,mixed> $license
     */
    private function isReusableSameDomainActivation(
        array $license,
        string $domain,
        string $installationId
    ): bool {
        try {
            $this->assertActiveLicense(
                $license,
                $domain,
                $installationId
            );

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** @param array<string,mixed> $response */
    private function activationFailureMessage(
        array $response,
        string $domain
    ): string {
        $signal = $this->responseSignal($response);
        $existingDomain = $this->responseDomain($response);

        if (
            str_contains($signal, 'already')
            || str_contains($signal, 'domain_conflict')
            || str_contains($signal, 'activation_conflict')
            || str_contains($signal, 'already_activated')
            || str_contains($signal, 'already_used')
        ) {
            if ($existingDomain !== '') {
                if ($existingDomain === self::canonicalDomain($domain)) {
                    return 'This license is already active on this domain, but the existing activation could not be reused automatically. Retry validation or deactivate the existing activation first.';
                }

                return 'This license is already activated on '
                    . $existingDomain
                    . '. Deactivate it there before using it on this domain.';
            }

            return 'This license is already activated on another domain. Deactivate the existing activation before using it here.';
        }

        if (
            str_contains($signal, 'activation_limit')
            || str_contains($signal, 'limit reached')
            || str_contains($signal, 'maximum activ')
            || str_contains($signal, 'too many activ')
        ) {
            return 'This license has reached its activation limit. Deactivate an existing domain before continuing.';
        }

        if (str_contains($signal, 'expired')) {
            return 'This license has expired.';
        }

        if (str_contains($signal, 'revoked')) {
            return 'This license has been revoked.';
        }

        if (
            str_contains($signal, 'suspend')
            || str_contains($signal, 'disabled')
        ) {
            return 'This license is suspended and cannot be activated.';
        }

        if (
            str_contains($signal, 'product')
            && (
                str_contains($signal, 'invalid')
                || str_contains($signal, 'mismatch')
                || str_contains($signal, 'not valid')
            )
        ) {
            return 'This license is not valid for Barama Real Estate.';
        }

        if (
            str_contains($signal, 'invalid')
            || str_contains($signal, 'not found')
            || str_contains($signal, 'unknown')
            || str_contains($signal, 'purchase code')
        ) {
            return 'The purchase code or license key is invalid.';
        }

        return 'The license could not be verified for Barama Real Estate. Check the code and try again.';
    }

    /** @param array<string,mixed> $response */
    private function validationFailureMessage(
        array $response,
        string $domain
    ): string {
        $signal = $this->responseSignal($response);
        $existingDomain = $this->responseDomain($response);

        if (
            $existingDomain !== ''
            && $existingDomain !== self::canonicalDomain($domain)
        ) {
            return 'This license is active on '
                . $existingDomain
                . ', not on the current domain.';
        }

        if (str_contains($signal, 'expired')) {
            return 'This license has expired.';
        }

        if (str_contains($signal, 'revoked')) {
            return 'This license has been revoked.';
        }

        if (
            str_contains($signal, 'suspend')
            || str_contains($signal, 'disabled')
        ) {
            return 'This license is suspended.';
        }

        if (
            str_contains($signal, 'deactiv')
            || str_contains($signal, 'inactive')
        ) {
            return 'This license is not active.';
        }

        return 'This license could not be validated. Please validate it again.';
    }

    /** @param array<string,mixed> $response */
    private function deactivationFailureMessage(array $response): string
    {
        $signal = $this->responseSignal($response);

        if (
            str_contains($signal, 'already')
            && str_contains($signal, 'deactiv')
        ) {
            return 'This license activation is already deactivated.';
        }

        if (
            str_contains($signal, 'not found')
            || str_contains($signal, 'invalid token')
        ) {
            return 'The license activation could not be found.';
        }

        return 'License deactivation could not be completed. Please try again.';
    }

    /** @param array<string,mixed> $response */
    private function responseSignal(array $response): string
    {
        $parts = [];

        foreach (['code', 'error_code', 'error', 'message', 'detail', 'reason'] as $key) {
            $value = $response[$key] ?? null;

            if (is_scalar($value)) {
                $parts[] = (string) $value;
            } elseif (is_array($value)) {
                foreach (['code', 'message', 'detail', 'reason'] as $nestedKey) {
                    if (is_scalar($value[$nestedKey] ?? null)) {
                        $parts[] = (string) $value[$nestedKey];
                    }
                }
            }
        }

        $license = is_array($response['license'] ?? null)
            ? $response['license']
            : [];

        foreach (['status', 'reason'] as $key) {
            if (is_scalar($license[$key] ?? null)) {
                $parts[] = (string) $license[$key];
            }
        }

        return strtolower(implode(' ', $parts));
    }

    /** @param array<string,mixed> $response */
    private function responseDomain(array $response): string
    {
        $candidates = [
            $response['existing_domain'] ?? null,
            $response['activated_domain'] ?? null,
            $response['domain'] ?? null,
        ];

        $license = is_array($response['license'] ?? null)
            ? $response['license']
            : [];

        $candidates[] = $license['domain'] ?? null;

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $canonical = self::canonicalDomain((string) $candidate);

            if (
                $canonical !== ''
                && preg_match('/^[a-z0-9.-]+$/', $canonical) === 1
            ) {
                return $canonical;
            }
        }

        return '';
    }

    private function resolveVerificationMethod(
        string $purchaseCode,
        string $verificationMethod
    ): string {
        $verificationMethod = strtolower(trim($verificationMethod));

        if ($verificationMethod === 'auto') {
            return preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
                $purchaseCode
            ) === 1
                ? 'envato'
                : 'direct';
        }

        if (!in_array($verificationMethod, ['direct', 'envato'], true)) {
            throw new RuntimeException(
                'The purchase verification request is invalid.'
            );
        }

        return $verificationMethod;
    }

    private function assertDomainAndInstallation(
        string $domain,
        string $installationId
    ): void {
        if ($domain === '') {
            throw new RuntimeException(
                'The installation domain could not be verified.'
            );
        }

        if (preg_match('/^[a-f0-9]{32}$/', $installationId) !== 1) {
            throw new RuntimeException(
                'The installation identifier is invalid.'
            );
        }
    }

    private static function normalizeInstallationId(
        string $installationId
    ): string {
        return strtolower(trim($installationId));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function request(
        string $path,
        array $payload,
        string $failureMessage
    ): array {
        if ($this->transport instanceof Closure) {
            $response = ($this->transport)($path, $payload);

            if (!is_array($response)) {
                throw new RuntimeException(
                    'License service returned an invalid response.'
                );
            }

            return $response;
        }

        if (!extension_loaded('curl')) {
            throw new RuntimeException(
                'Secure license verification requires the PHP cURL extension.'
            );
        }

        $base = rtrim(
            (string) config(
                'license.server_url',
                'https://tonsamith.com'
            ),
            '/'
        );
        $url = $base . '/' . ltrim($path, '/');

        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException(
                'License verification is unavailable.'
            );
        }

        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'License service is temporarily unavailable. Please try again.',
                self::ERROR_TEMPORARY_UNAVAILABLE
            );
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => max(
                8,
                (int) config('license.request_timeout', 15)
            ),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: Barama-Real-Estate/'
                    . (
                        defined('APP_VERSION')
                            ? APP_VERSION
                            : 'installer'
                    ),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (
            defined('CURLOPT_PROTOCOLS')
            && defined('CURLPROTO_HTTPS')
        ) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $error !== '') {
            throw new RuntimeException(
                'License service is temporarily unavailable. Please try again.',
                self::ERROR_TEMPORARY_UNAVAILABLE
            );
        }

        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'License service is temporarily unavailable. Please try again.',
                self::ERROR_TEMPORARY_UNAVAILABLE
            );
        }

        if ($status < 200 || $status >= 300) {
            if (self::isTemporaryHttpStatus($status)) {
                throw new RuntimeException(
                    'License service is temporarily unavailable. Please try again.',
                    self::ERROR_TEMPORARY_UNAVAILABLE
                );
            }

            $decoded['_http_status'] = $status;

            if (!array_key_exists('success', $decoded)) {
                $decoded['success'] = false;
            }

            return $decoded;
        }

        return $decoded;
    }
}
