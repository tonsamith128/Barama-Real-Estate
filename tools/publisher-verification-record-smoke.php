<?php

declare(strict_types=1);

namespace Barama\Core {
    final class Installation
    {
        /** @var array<string,mixed> */
        public static array $license = [];
        /** @var array<string,mixed> */
        public static array $runtime = [];
        public static string $id = '';

        public static function licenseState(): array { return self::$license; }
        public static function runtimeConfig(): array { return self::$runtime; }
        public static function installationId(): string { return self::$id; }
        public static function refreshLicenseState(array $data): void
        {
            self::$license = array_merge(self::$license, $data);
        }
    }
}

namespace {
    use Barama\Core\Installation;
    use Barama\Services\PublisherVerificationRecord;

    function config(string $key, mixed $default = null): mixed
    {
        return $key === 'license.product_slug' ? 'barama-real-estate' : $default;
    }

    require_once dirname(__DIR__) . '/application/app/Services/LicenseManagerClient.php';
    require_once dirname(__DIR__) . '/application/app/Services/PublisherVerificationRecord.php';

    $id = str_repeat('a', 32);
    $license = [
        'activation_token' => 'qa-private-activation-token-1234567890',
        'installation_id' => $id,
        'domain' => 'barama.example.test',
        'product_slug' => 'barama-real-estate',
        'status' => 'active',
    ];
    $runtime = ['APP_URL' => 'https://barama.example.test'];
    Installation::$license = $license;
    Installation::$runtime = $runtime;
    Installation::$id = $id;

    $assert = static function (bool $condition, string $label): void {
        if (!$condition) {
            fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
            exit(1);
        }
        echo 'PASS: ' . $label . PHP_EOL;
    };

    $assert(PublisherVerificationRecord::read() === null, 'No verification is invented before a check');
    $assert(!PublisherVerificationRecord::remember('Publisher temporarily unavailable'), 'Transient failure is not persisted');
    $assert(PublisherVerificationRecord::remember('Active license verified'), 'Trusted active result saved to private state');

    $stored = Installation::$license['auto_updater_publisher_verification_v1'] ?? null;
    $assert(is_array($stored) && ($stored['version'] ?? null) === 1, 'Versioned receipt stored');
    $assert(!str_contains(var_export($stored, true), $license['activation_token']), 'Receipt never stores plaintext token');
    $assert(PublisherVerificationRecord::read()['state'] === 'Active license verified', 'Saved result restored in fresh request');
    $assert(PublisherVerificationRecord::read()['checked_at'] !== 'Never', 'Last successful check timestamp restored');
    $assert(PublisherVerificationRecord::fromState(
        Installation::$license, Installation::$runtime, $id, time() + 365 * 86400
    ) !== null, 'Saved result has no arbitrary expiration after one year');

    $otherToken = Installation::$license;
    $otherToken['activation_token'] = str_repeat('z', 32);
    $assert(PublisherVerificationRecord::fromState($otherToken, $runtime, $id) === null, 'Other token cannot inherit verification');
    $assert(PublisherVerificationRecord::fromState($license + ['auto_updater_publisher_verification_v1' => $stored],
        ['APP_URL' => 'https://other.example.test'], $id) === null, 'Other domain cannot inherit verification');
    $assert(PublisherVerificationRecord::fromState(Installation::$license, $runtime, str_repeat('b', 32)) === null,
        'Other installation cannot inherit verification');
    $mixed = Installation::$license;
    $mixed['publisher_v2_activation'] = ['version' => 1];
    $assert(PublisherVerificationRecord::fromState($mixed, $runtime, $id) === null,
        'Conflicting Publisher V2 activation cannot inherit V1 verification');
    $assert(PublisherVerificationRecord::fromState(Installation::$license,
        ['APP_URL' => 'http://barama.example.test'], $id) === null,
        'Insecure runtime URL cannot inherit verification');

    $deactivated = Installation::$license;
    $deactivated['status'] = 'deactivated';
    $assert(PublisherVerificationRecord::fromState($deactivated, $runtime, $id) === null, 'Deactivation invalidates display');
    $expired = Installation::$license;
    $expired['expires_at'] = '2020-01-01T00:00:00Z';
    $assert(PublisherVerificationRecord::fromState($expired, $runtime, $id) === null, 'Expired license cannot display verified');
    $blocked = Installation::$license;
    $blocked['publisher_v2_verification'] = [
        'domain' => 'barama.example.test', 'blocked_reason' => 'LICENSE_REVOKED',
    ];
    $assert(PublisherVerificationRecord::fromState($blocked, $runtime, $id) === null, 'Publisher V2 revocation overrides old green result');

    $before = Installation::$license['auto_updater_publisher_verification_v1'];
    $assert(!PublisherVerificationRecord::remember('Unable to verify'), 'Ambiguous failure cannot overwrite trusted state');
    $assert(Installation::$license['auto_updater_publisher_verification_v1'] === $before, 'Temporary failure preserves history');

    $assert(PublisherVerificationRecord::remember('Publisher confirmed revoked'), 'Definitive revocation replaces history');
    $assert(PublisherVerificationRecord::read()['state'] === 'Publisher confirmed revoked', 'Revoked status survives new session');
    $assert(PublisherVerificationRecord::remember('Active license verified'), 'Administrator can verify again after reactivation');
    $assert(PublisherVerificationRecord::read()['state'] === 'Active license verified', 'Fresh active evidence supersedes older revocation');

    echo 'PUBLISHER_VERIFICATION_PERSISTENCE=PASS' . PHP_EOL;
}
