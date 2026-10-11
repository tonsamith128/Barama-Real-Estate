<?php

declare(strict_types=1);

namespace Barama\Security;

/**
 * Cloudflare Turnstile's server-side Siteverify adapter.
 *
 * This primitive is intentionally NOT wired to routes or configuration yet.
 * The caller must supply a private secret, the exact configured hostname and
 * a form-specific action before it may protect a route.
 */
final class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(
        string $secret,
        string $token,
        string $hostname,
        string $action,
        ?string $remoteIp = null
    ): bool {
        if ($secret === '' || strlen($secret) > 4096 ||
            $token === '' || strlen($token) > 2048 ||
            $hostname === '' || $action === '' ||
            !preg_match('/^[a-z0-9.-]{1,253}$/D', $hostname) ||
            !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $action)) {
            return false;
        }

        if ($remoteIp !== null && filter_var($remoteIp, FILTER_VALIDATE_IP) === false) {
            $remoteIp = null;
        }

        if (!function_exists('curl_init')) {
            return false;
        }

        $data = [
            'secret' => $secret,
            'response' => $token,
        ];
        if ($remoteIp !== null) {
            $data['remoteip'] = $remoteIp;
        }

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return false;
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data, '', '&', PHP_QUERY_RFC3986),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($ch);
        }

        if (!is_string($body) || strlen($body) > 8192 || $code !== 200) {
            return false;
        }

        try {
            $result = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($result) && self::acceptsResult($result, $hostname, $action);
    }
    /**
     * Pure, offline-testable decision for Siteverify JSON. The challenge token
     * is not accepted unless the remote server returned this payload.
     */
    public static function acceptsResult(array $result, string $hostname, string $action): bool
    {
        return ($result['success'] ?? false) === true
            && isset($result['hostname'], $result['action'])
            && is_string($result['hostname'])
            && is_string($result['action'])
            && hash_equals($hostname, strtolower(rtrim($result['hostname'], '.')))
            && hash_equals($action, $result['action']);
    }

}
