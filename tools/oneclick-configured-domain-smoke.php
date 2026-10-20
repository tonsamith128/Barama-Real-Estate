<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/LicenseManagerClient.php';
require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickInstalledDomainV1.php';

use Barama\Services\Updates\OneClickInstalledDomainV1 as Domain;

function expectDomain(string $expected, array $config, string $label): void
{
    $actual = Domain::fromRuntimeConfig($config);
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': received "' . $actual . '" expected "' . $expected . '"');
    }
    echo 'PASS=' . $label . PHP_EOL;
}
expectDomain('baramastaging.tonsamith.com', ['APP_URL'=>'https://baramastaging.tonsamith.com/'], 'fresh_staging_domain');
expectDomain('baramarealestate.tonsamith.com', ['APP_URL'=>'https://baramarealestate.tonsamith.com'], 'production_domain');
expectDomain('example.com', ['APP_URL'=>'https://www.example.com/install/barama/'], 'subdirectory_hosting');
expectDomain('client.example.com', ['APP_URL'=>'http://client.example.com:8080/'], 'port_only');
expectDomain('Not recorded', [], 'no_config');
expectDomain('Not recorded', ['APP_URL'=>null], 'null_config');
expectDomain('Not recorded', ['APP_URL'=>'javascript://example.com/'], 'unsupported_scheme');
expectDomain('Not recorded', ['APP_URL'=>'//example.com/'], 'scheme_missing');
expectDomain('Not recorded', ['APP_URL'=>'https://user:password@example.com/'], 'userinfo_rejected');
expectDomain('Not recorded', ['APP_URL'=>'https://example.com?redirect=evil.com'], 'query_rejected');
expectDomain('Not recorded', ['APP_URL'=>'https://example.com#fragment'], 'fragment_rejected');
expectDomain('Not recorded', ['APP_URL'=>"https://example.com\n"], 'newline_rejected');
expectDomain('Not recorded', ['APP_URL'=>'https://evil.com%0a.com'], 'encoded_host_control_rejected');
expectDomain('Not recorded', ['APP_URL'=>'localhost'], 'localhost_rejected');
$_SERVER['HTTP_HOST'] = 'evil.example.com';
expectDomain('client.example.com', ['APP_URL'=>'https://client.example.com'], 'host_header_does_not_override_installed_config');
$source = file_get_contents(dirname(__DIR__) . '/application/app/Services/Updates/OneClickInstalledDomainV1.php');
if (!is_string($source)
    || str_contains($source, 'HTTP_HOST')
    || str_contains($source, 'X_FORWARDED_HOST')
    || preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i', $source)) {
    throw new RuntimeException('DOMAIN_DISPLAY_UNSAFE_SOURCE');
}
echo 'ONECLICK_CONFIGURED_DOMAIN_SMOKE=PASS' . PHP_EOL;
