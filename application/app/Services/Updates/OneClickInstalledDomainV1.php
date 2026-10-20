<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Services\LicenseManagerClient;

/**
 * Safe display-only domain from installed, private APP_URL configuration.
 * Never trusts HTTP Host headers, proxy headers or request parameters.
 * Never makes a licensing or installation authorization decision.
 */
final class OneClickInstalledDomainV1
{
    public static function fromRuntimeConfig(array $runtime): string
    {
        $raw = $runtime['APP_URL'] ?? null;
        if (!is_string($raw) || $raw === '' || strlen($raw) > 2048
            || preg_match('/[\x00-\x20\x7f]/', $raw) === 1) {
            return 'Not recorded';
        }
        $url = parse_url($raw);
        if (!is_array($url)
            || !in_array(strtolower((string) ($url['scheme'] ?? '')), ['https','http'], true)
            || !is_string($url['host'] ?? null)
            || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment'])
            || (isset($url['path']) && !in_array($url['path'], ['', '/'], true))) {
            return 'Not recorded';
        }
        $domain = LicenseManagerClient::canonicalDomain($raw);
        return strlen($domain) <= 253
            && preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,24}$/D', $domain) === 1
            ? $domain : 'Not recorded';
    }

    private function __construct()
    {
    }
}
