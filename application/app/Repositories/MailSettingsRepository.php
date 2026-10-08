<?php

declare(strict_types=1);

namespace Barama\Repositories;

use RuntimeException;

/**
 * Private, encrypted, installation-scoped mail transport overrides.
 * Keeps SMTP credentials out of web responses, git, environment rewrites
 * and the user-visible application_settings table.
 */
final class MailSettingsRepository
{
    private const AAD = 'barama-mail-settings-v1';

    public function __construct(private readonly ?string $path = null)
    {
    }

    public function exists(): bool
    {
        return is_file($this->configPath());
    }

    /** @return array<string,mixed> */
    public function get(): array
    {
        $path = $this->configPath();
        if (!file_exists($path) && !is_link($path)) {
            return [];
        }
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Stored mail settings cannot be read safely.');
        }
        $encoded = file_get_contents($path);
        if (!is_string($encoded) || strlen($encoded) > 16384
            || !str_starts_with(trim($encoded), 'v1:')) {
            throw new RuntimeException('Stored mail settings are invalid.');
        }
        $raw = base64_decode(substr(trim($encoded), 3), true);
        if (!is_string($raw) || strlen($raw) < 29) {
            throw new RuntimeException('Stored mail settings are invalid.');
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16),
            self::AAD
        );
        if (!is_string($plain)) {
            throw new RuntimeException('Stored mail settings could not be decrypted.');
        }
        $data = json_decode($plain, true);
        if (!is_array($data) || !in_array($data['driver'] ?? null, ['mail', 'smtp'], true)) {
            throw new RuntimeException('Stored mail settings are invalid.');
        }
        return $data;
    }

    /** @param array<string,mixed> $config */
    public function save(array $config): void
    {
        if (!in_array($config['driver'] ?? null, ['mail', 'smtp'], true)) {
            throw new RuntimeException('Unsupported mail configuration.');
        }
        $plain = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );
        if (!is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('Mail settings encryption failed.');
        }
        $directory = dirname($this->configPath());
        if (is_link($directory)) {
            throw new RuntimeException('Mail settings directory is unsafe.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Mail settings directory could not be created.');
        }
        $path = $this->configPath();
        if (is_link($path)) {
            throw new RuntimeException('Mail settings path is unsafe.');
        }
        $temporary = tempnam($directory, '.mail-settings-');
        if ($temporary === false) {
            throw new RuntimeException('Mail settings staging failed.');
        }
        try {
            @chmod($temporary, 0600);
            $encoded = 'v1:' . base64_encode($iv . $tag . $cipher) . PHP_EOL;
            if (file_put_contents($temporary, $encoded, LOCK_EX) !== strlen($encoded)
                || !rename($temporary, $path)) {
                throw new RuntimeException('Mail settings could not be saved.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function key(): string
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL is required for encrypted mail settings.');
        }
        $appKey = trim((string) env('APP_KEY', ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required for encrypted mail settings.');
        }
        $decoded = base64_decode($appKey, true);
        $material = is_string($decoded) && $decoded !== '' ? $decoded : $appKey;
        return hash('sha256', $material, true);
    }

    private function configPath(): string
    {
        return $this->path ?? APP_PATH . '/storage/app/mail/settings.enc';
    }
}
