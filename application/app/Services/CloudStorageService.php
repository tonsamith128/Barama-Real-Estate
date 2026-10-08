<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\CloudStorageRepository;
use DomainException;
use InvalidArgumentException;

final class CloudStorageService
{
    /** @return array<string,mixed> */
    public function viewModel(): array
    {
        $config = (new CloudStorageRepository())->get();
        $providers = [];

        foreach (['s3', 'r2'] as $name) {
            $provider = is_array($config['providers'][$name] ?? null)
                ? $config['providers'][$name]
                : [];
            $fingerprint = CloudStorageRepository::fingerprint($name, $provider);
            $mediaCount = $this->storageAssetCount($name);
            $providers[$name] = [
                'bucket' => (string) ($provider['bucket'] ?? ''),
                'region' => (string) ($provider['region'] ?? ($name === 'r2' ? 'auto' : 'ap-southeast-1')),
                'account_id' => (string) ($provider['account_id'] ?? ''),
                'access_key_id' => (string) ($provider['access_key_id'] ?? ''),
                'has_secret' => trim((string) ($provider['secret_access_key'] ?? '')) !== '',
                'prefix' => (string) ($provider['prefix'] ?? 'barama/'),
                'configured' => $this->isComplete($name, $provider),
                'tested_at' => $provider['tested_at'] ?? null,
                'tested' => (string) ($provider['tested_fingerprint'] ?? '') !== ''
                    && hash_equals((string) $provider['tested_fingerprint'], $fingerprint),
                'media_count' => $mediaCount,
                'location_locked' => $mediaCount > 0,
            ];
        }

        return [
            'active_disk' => in_array((string) ($config['active_disk'] ?? ''), ['private', 's3', 'r2'], true)
                ? (string) $config['active_disk']
                : 'private',
            'providers' => $providers,
            'curl_available' => function_exists('curl_init'),
            'openssl_available' => function_exists('openssl_encrypt'),
            'app_key_available' => trim((string) env('APP_KEY', '')) !== '',
        ];
    }

    /** @param array<string,mixed> $input */
    public function saveProvider(string $name, array $input): void
    {
        $this->assertAdministrator();
        $name = $this->providerName($name);
        $repo = new CloudStorageRepository();
        $config = $repo->get();
        $current = is_array($config['providers'][$name] ?? null)
            ? $config['providers'][$name]
            : [];

        $bucket = strtolower(trim((string) ($input['bucket'] ?? '')));
        if ($bucket === '' || preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket) !== 1) {
            throw new InvalidArgumentException('Enter a valid S3-compatible bucket name.');
        }

        $accessKey = trim((string) ($input['access_key_id'] ?? ''));
        if ($accessKey === '' || strlen($accessKey) > 128) {
            throw new InvalidArgumentException('Access key ID is required.');
        }

        $secret = trim((string) ($input['secret_access_key'] ?? ''));
        if ($secret === '') {
            $secret = (string) ($current['secret_access_key'] ?? '');
        }
        if ($secret === '' || strlen($secret) > 256) {
            throw new InvalidArgumentException('Secret access key is required.');
        }

        $prefix = trim((string) ($input['prefix'] ?? 'barama/'));
        $prefix = trim($prefix, '/');
        if ($prefix !== '' && preg_match('#^[A-Za-z0-9._/-]{1,160}$#', $prefix) !== 1) {
            throw new InvalidArgumentException('Storage prefix may contain letters, numbers, dots, underscores, hyphens, and slashes only.');
        }
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $provider = [
            'bucket' => $bucket,
            'region' => $name === 'r2'
                ? 'auto'
                : strtolower(trim((string) ($input['region'] ?? 'ap-southeast-1'))),
            'account_id' => $name === 'r2'
                ? strtolower(trim((string) ($input['account_id'] ?? '')))
                : '',
            'access_key_id' => $accessKey,
            'secret_access_key' => $secret,
            'prefix' => $prefix,
            'configured_at' => gmdate('c'),
            'tested_at' => null,
            'tested_fingerprint' => '',
        ];

        if ($name === 's3' && preg_match('/^[a-z0-9-]{3,32}$/', (string) $provider['region']) !== 1) {
            throw new InvalidArgumentException('Enter a valid AWS region such as ap-southeast-1.');
        }
        if ($name === 'r2' && preg_match('/^[a-f0-9]{32}$/', (string) $provider['account_id']) !== 1) {
            throw new InvalidArgumentException('Cloudflare R2 account ID must be a 32-character hexadecimal value.');
        }

        $mediaCount = $this->storageAssetCount($name);
        if (
            $mediaCount > 0
            && $this->locationSignature($name, $current) !== $this->locationSignature($name, $provider)
        ) {
            throw new DomainException(
                strtoupper($name)
                . ' already stores '
                . $mediaCount
                . ' media asset'
                . ($mediaCount === 1 ? '' : 's')
                . '. Bucket, region/account, and object prefix are locked until those objects are migrated. You may rotate credentials without changing the storage location.'
            );
        }

        $config['providers'][$name] = $provider;
        if (($config['active_disk'] ?? 'private') === $name) {
            $config['active_disk'] = 'private';
        }
        $repo->save($config);

        (new SecurityAuditService())->record(
            'admin.cloud_storage_provider_saved',
            (int) auth_user()['id'],
            null,
            [
                'provider' => $name,
                'bucket' => $bucket,
                'active_disk' => (string) $config['active_disk'],
                'media_count' => $mediaCount,
            ]
        );
    }

    public function testProvider(string $name): void
    {
        $this->assertAdministrator();
        $name = $this->providerName($name);
        $repo = new CloudStorageRepository();
        $config = $repo->get();
        $provider = $config['providers'][$name] ?? null;

        if (!is_array($provider) || !$this->isComplete($name, $provider)) {
            throw new DomainException('Save a complete provider configuration before testing it.');
        }

        (new CloudObjectStorageService($name, $provider))->writeTest();
        $provider['tested_at'] = gmdate('c');
        $provider['tested_fingerprint'] = CloudStorageRepository::fingerprint($name, $provider);
        $config['providers'][$name] = $provider;
        $repo->save($config);

        (new SecurityAuditService())->record(
            'admin.cloud_storage_provider_tested',
            (int) auth_user()['id'],
            null,
            ['provider' => $name, 'bucket' => (string) $provider['bucket']]
        );
    }

    public function activate(string $disk): void
    {
        $this->assertAdministrator();
        if (!in_array($disk, ['private', 's3', 'r2'], true)) {
            throw new InvalidArgumentException('Select a valid storage disk.');
        }

        $repo = new CloudStorageRepository();
        $config = $repo->get();

        if ($disk !== 'private') {
            $provider = $config['providers'][$disk] ?? null;
            if (!is_array($provider) || !$this->isComplete($disk, $provider)) {
                throw new DomainException('Configure the selected cloud storage provider first.');
            }
            $fingerprint = CloudStorageRepository::fingerprint($disk, $provider);
            if (
                trim((string) ($provider['tested_fingerprint'] ?? '')) === ''
                || !hash_equals((string) $provider['tested_fingerprint'], $fingerprint)
            ) {
                throw new DomainException('Run a successful connection test before activating this cloud storage provider.');
            }
        }

        $config['active_disk'] = $disk;
        $repo->save($config);

        (new SecurityAuditService())->record(
            'admin.cloud_storage_activated',
            (int) auth_user()['id'],
            null,
            ['disk' => $disk]
        );
    }

    public function activeDisk(): string
    {
        $disk = (string) ((new CloudStorageRepository())->get()['active_disk'] ?? 'private');
        return in_array($disk, ['s3', 'r2'], true) ? $disk : 'private';
    }

    /** @return array<string,mixed> */
    public function provider(string $name): array
    {
        $name = $this->providerName($name);
        $provider = (new CloudStorageRepository())->get()['providers'][$name] ?? null;
        if (!is_array($provider)) {
            throw new DomainException('Cloud storage provider configuration is unavailable.');
        }
        return $provider;
    }

    /** @param array<string,mixed> $provider */
    private function isComplete(string $name, array $provider): bool
    {
        if (
            trim((string) ($provider['bucket'] ?? '')) === ''
            || trim((string) ($provider['access_key_id'] ?? '')) === ''
            || trim((string) ($provider['secret_access_key'] ?? '')) === ''
        ) {
            return false;
        }
        if ($name === 'r2') {
            return preg_match('/^[a-f0-9]{32}$/', (string) ($provider['account_id'] ?? '')) === 1;
        }
        return trim((string) ($provider['region'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $provider */
    private function locationSignature(string $name, array $provider): string
    {
        $location = [
            'provider' => $name,
            'bucket' => strtolower(trim((string) ($provider['bucket'] ?? ''))),
            'prefix' => trim((string) ($provider['prefix'] ?? ''), '/'),
        ];

        if ($name === 'r2') {
            $location['account_id'] = strtolower(trim((string) ($provider['account_id'] ?? '')));
        } else {
            $location['region'] = strtolower(trim((string) ($provider['region'] ?? '')));
        }

        return hash(
            'sha256',
            json_encode($location, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
    }

    private function storageAssetCount(string $disk): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM media_assets WHERE disk = :disk'
        );
        $statement->execute(['disk' => $disk]);

        return (int) $statement->fetchColumn();
    }

    private function providerName(string $name): string
    {
        $name = strtolower(trim($name));
        if (!in_array($name, ['s3', 'r2'], true)) {
            throw new InvalidArgumentException('Select Amazon S3 or Cloudflare R2.');
        }
        return $name;
    }

    private function assertAdministrator(): void
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('dashboard.access')) {
            throw new DomainException('Administrator access is required to manage cloud storage.');
        }
        if (!function_exists('openssl_encrypt')) {
            throw new DomainException('OpenSSL is required before cloud storage can be configured.');
        }
        if (trim((string) env('APP_KEY', '')) === '') {
            throw new DomainException('APP_KEY is required before cloud storage credentials can be protected.');
        }
    }
}
