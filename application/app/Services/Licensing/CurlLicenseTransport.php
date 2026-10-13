<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/** Locked HTTPS origin. Does not follow redirects or log credentials. */
final class CurlLicenseTransport implements LicenseTransport
{
    private const MAX_BODY_BYTES = 16384;
    private readonly string $origin;

    public function __construct(string $origin)
    {
        $parts = parse_url($origin);
        if ($parts === false || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'], $parts['pass'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || (isset($parts['path']) && !in_array($parts['path'], ['', '/'], true))
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new LicenseClientException('INVALID_PUBLISHER_ORIGIN');
        }
        $this->origin = 'https://' . strtolower($parts['host']);
    }

    public function post(string $action, array $payload): array
    {
        if (!in_array($action, ['activate', 'verify', 'deactivate'], true)) {
            throw new LicenseClientException('INVALID_API_ACTION');
        }
        if (!function_exists('curl_init')) {
            throw new LicenseClientException('CURL_REQUIRED', 503);
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > self::MAX_BODY_BYTES) {
            throw new LicenseClientException('REQUEST_TOO_LARGE', 413);
        }
        $handle = curl_init($this->origin . '/api/v2/license/' . $action);
        if ($handle === false) {
            throw new LicenseClientException('LICENSE_SERVER_UNAVAILABLE', 503);
        }
        $received = '';
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Barama-License-Client/1.0',
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$received): int {
                    if (strlen($received) + strlen($chunk) > 16384) return 0;
                    $received .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $status < 200 || $status > 499) {
                throw new LicenseClientException('LICENSE_SERVER_UNAVAILABLE', 503);
            }
            try {
                $body = json_decode($received, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
            }
            if (!is_array($body) || array_is_list($body)) {
                throw new LicenseClientException('INVALID_PUBLISHER_RESPONSE', 502);
            }
            return ['status' => $status, 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }
}
