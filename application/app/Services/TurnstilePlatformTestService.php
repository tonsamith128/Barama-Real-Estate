<?php

declare(strict_types=1);

namespace Barama\Services;

/**
 * Authenticated ADMIN QA ONLY. Official Cloudflare public test keys.
 * This service is not real CAPTCHA protection and must not be used by forms.
 */
final class TurnstilePlatformTestService
{
    public const SITE_KEY = '1x00000000000000000000AA';
    private const SECRETS = [
        'pass' => '1x0000000000000000000000000000000AA',
        'fail' => '2x0000000000000000000000000000000AA',
        'replay' => '3x0000000000000000000000000000000AA',
    ];
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** @return array{transport:bool, success:bool, errors:array<int,string>} */
    public static function verify(string $token, string $scenario): array
    {
        $failure = ['transport' => false, 'success' => false, 'errors' => ['invalid-request']];
        if (strlen($token) < 1 || strlen($token) > 2048 || !isset(self::SECRETS[$scenario])) {
            return $failure;
        }
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            return ['transport' => false, 'success' => false, 'errors' => ['verification-unavailable']];
        }
        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            return ['transport' => false, 'success' => false, 'errors' => ['verification-unavailable']];
        }
        $configured = curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => self::SECRETS[$scenario], 'response' => $token,
            ], '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'Barama-Turnstile-Platform-QA/1.0',
        ]);
        if (!$configured) {
            unset($handle);
            return ['transport' => false, 'success' => false, 'errors' => ['verification-unavailable']];
        }
        $payload = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        unset($handle); // PHP 8.5 compatible handle release.
        if (!is_string($payload) || $payload === '' || strlen($payload) > 8192 || $httpCode !== 200) {
            return ['transport' => false, 'success' => false, 'errors' => ['verification-unavailable']];
        }
        try {
            $result = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['transport' => false, 'success' => false, 'errors' => ['invalid-siteverify-response']];
        }
        if (!is_array($result) || !is_bool($result['success'] ?? null)) {
            return ['transport' => false, 'success' => false, 'errors' => ['invalid-siteverify-response']];
        }
        $codes = ['invalid-input-response', 'invalid-input-secret', 'timeout-or-duplicate',
            'bad-request', 'internal-error', 'missing-input-response'];
        $errors = array_values(array_filter(
            is_array($result['error-codes'] ?? null) ? $result['error-codes'] : [],
            static fn (mixed $x): bool => is_string($x) && in_array($x, $codes, true)
        ));
        return ['transport' => true, 'success' => $result['success'], 'errors' => $errors];
    }
}
