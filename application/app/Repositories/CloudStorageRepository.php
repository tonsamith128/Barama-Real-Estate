<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use RuntimeException;

final class CloudStorageRepository
{
    private const SETTING_KEY = 'storage.cloud';

    /** @return array<string,mixed> */
    public function get(): array
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => self::SETTING_KEY]);
        $json = $statement->fetchColumn();
        $config = self::defaults();

        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $config = array_replace_recursive($config, $decoded);
            }
        }

        foreach (['s3', 'r2'] as $provider) {
            $encrypted = (string) ($config['providers'][$provider]['secret_access_key'] ?? '');
            $config['providers'][$provider]['secret_access_key'] = $encrypted !== ''
                ? $this->decrypt($encrypted)
                : '';
        }

        return $config;
    }

    /** @param array<string,mixed> $config */
    public function save(array $config): void
    {
        $stored = array_replace_recursive(self::defaults(), $config);

        foreach (['s3', 'r2'] as $provider) {
            $secret = trim((string) ($stored['providers'][$provider]['secret_access_key'] ?? ''));
            $stored['providers'][$provider]['secret_access_key'] = $secret !== ''
                ? $this->encrypt($secret)
                : '';
        }

        $json = json_encode(
            $stored,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings (setting_key, setting_value, value_type)
             VALUES (:setting_key, :setting_value, "json")
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = "json",
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'setting_key' => self::SETTING_KEY,
            'setting_value' => $json,
        ]);
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $provider = static fn (): array => [
            'bucket' => '',
            'region' => '',
            'account_id' => '',
            'access_key_id' => '',
            'secret_access_key' => '',
            'prefix' => 'barama/',
            'configured_at' => null,
            'tested_at' => null,
            'tested_fingerprint' => '',
        ];

        return [
            'active_disk' => 'private',
            'providers' => [
                's3' => array_replace($provider(), ['region' => 'ap-southeast-1']),
                'r2' => array_replace($provider(), ['region' => 'auto']),
            ],
        ];
    }

    /** @param array<string,mixed> $provider */
    public static function fingerprint(string $name, array $provider): string
    {
        return hash('sha256', json_encode([
            'provider' => $name,
            'bucket' => (string) ($provider['bucket'] ?? ''),
            'region' => (string) ($provider['region'] ?? ''),
            'account_id' => (string) ($provider['account_id'] ?? ''),
            'access_key_id' => (string) ($provider['access_key_id'] ?? ''),
            'secret_hash' => hash('sha256', (string) ($provider['secret_access_key'] ?? '')),
            'prefix' => (string) ($provider['prefix'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    private function encryptionKey(): string
    {
        $appKey = trim((string) env('APP_KEY', ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required before cloud storage credentials can be saved.');
        }

        $decoded = base64_decode($appKey, true);
        $material = is_string($decoded) && $decoded !== '' ? $decoded : $appKey;

        return hash('sha256', $material, true);
    }

    private function encrypt(string $value): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to protect cloud storage credentials.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::SETTING_KEY,
            16
        );

        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Cloud storage credential encryption failed.');
        }

        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'v1:') || !function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = base64_decode(substr($value, 3), true);
        if (!is_string($payload) || strlen($payload) < 29) {
            return '';
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::SETTING_KEY
        );

        return is_string($plain) ? $plain : '';
    }
}
