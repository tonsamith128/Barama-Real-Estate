<?php

declare(strict_types=1);

// Standalone fixture for installed-license/publisher status parity.
// No network, database, installation files or live secrets.
namespace Barama\Services {
    final class LicenseManagerClient
    {
        public static function canonicalDomain(string $value): string
        {
            $value = strtolower(trim($value));
            if ($value === '') return '';
            $host = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : '';
        }
        public static function isTemporaryFailure(\RuntimeException $e): bool { return false; }
    }
}

namespace {
    require_once dirname(__DIR__) . '/application/app/Services/PublisherLicenseStatus.php';

    use Barama\Services\PublisherLicenseStatus;

    $calls = 0;
    $installation = str_repeat('a', 32);
    $base = [
        'activation_token' => 'fixture-token-do-not-transmit',
        'installation_id' => $installation,
        'product_slug' => 'barama-real-estate',
        'status' => 'active',
    ];
    $assert = static function (bool $ok, string $name): void {
        if (!$ok) { fwrite(STDERR, "FAIL={$name}\n"); exit(1); }
        echo "PASS={$name}\n";
    };
    $validator = static function (string $expected) use (&$calls, $installation): callable {
        return static function (string $token, string $domain, string $id) use (&$calls, $expected, $installation): array {
            $calls++;
            if ($domain !== $expected || $id !== $installation || $token !== 'fixture-token-do-not-transmit') {
                throw new \RuntimeException('BAD_FIXTURE_BINDING');
            }
            return ['success' => true, 'license' => [
                'status' => 'active', 'product_slug' => 'barama-real-estate',
                'domain' => $expected, 'installation_id' => $installation,
            ]];
        };
    };

    foreach (['baramastaging.tonsamith.com', 'baramarealestate.tonsamith.com', 'customer.example.test'] as $host) {
        $local = $base + ['domain' => $host];
        $before = $calls;
        $result = PublisherLicenseStatus::probe($local, ['APP_URL'=>'https://' . $host], $validator($host));
        $assert($result['state'] === 'Active license verified', 'valid_installed_domain_' . $host);
        $assert($calls === $before + 1, 'validator_called_once_' . $host);
    }

    $local = $base + ['domain' => 'baramastaging.tonsamith.com'];
    $before = $calls;
    $badDomain = PublisherLicenseStatus::probe($local, ['APP_URL'=>'https://other.example.test'], $validator('other.example.test'));
    $assert($badDomain['state'] === 'License binding mismatch' && $calls === $before, 'domain_mismatch_denied_offline');

    $wrongProduct = $local; $wrongProduct['product_slug'] = 'another-product';
    $assert(PublisherLicenseStatus::probe($wrongProduct, ['APP_URL'=>'https://baramastaging.tonsamith.com'], $validator('baramastaging.tonsamith.com'))['state'] === 'License binding mismatch'
        && $calls === $before, 'wrong_product_denied_offline');

    $wrongScheme = PublisherLicenseStatus::probe($local, ['APP_URL'=>'http://baramastaging.tonsamith.com'], $validator('baramastaging.tonsamith.com'));
    $assert($wrongScheme['state'] === 'License binding mismatch' && $calls === $before, 'http_denied_offline');

    $wrongId = $local; $wrongId['installation_id'] = 'not-an-id';
    $assert(PublisherLicenseStatus::probe($wrongId, ['APP_URL'=>'https://baramastaging.tonsamith.com'], $validator('baramastaging.tonsamith.com'))['state'] === 'License not configured'
        && $calls === $before, 'invalid_installation_id_denied_offline');

    $remoteMismatch = PublisherLicenseStatus::probe($local, ['APP_URL'=>'https://baramastaging.tonsamith.com'],
        static fn(string $token, string $domain, string $id): array => [
            'success' => true, 'license' => ['status'=>'active', 'product_slug'=>'barama-real-estate',
                'domain'=>'other.example.test', 'installation_id'=>$id]
        ]);
    $assert($remoteMismatch['state'] === 'License binding mismatch', 'publisher_response_mismatch_denied');

    $revoked = PublisherLicenseStatus::probe($local, ['APP_URL'=>'https://baramastaging.tonsamith.com'],
        static fn(string $token, string $domain, string $id): array => [
            'success' => true, 'license' => ['status'=>'revoked', 'product_slug'=>'barama-real-estate',
                'domain'=>$domain, 'installation_id'=>$id]
        ]);
    $assert($revoked['state'] === 'Publisher confirmed revoked', 'revocation_preserved');

    echo "VALIDATION_NETWORK_REQUESTS=NO\n";
    echo "DATABASE_WRITES=NO\n";
}
