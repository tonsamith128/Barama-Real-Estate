<?php

declare(strict_types=1);

namespace Barama\Repositories;

use RuntimeException;

/** Private encrypted global location provider configuration; never expose token to a view. */
final class LocationProviderRepository
{
    private const AAD = 'barama-location-provider-v117a7';

    /** @return array{provider:string,token:string,tested_digest:string,tested_at:string} */
    public function get(): array
    {
        $defaults = ['provider'=>'simple','token'=>'','tested_digest'=>'','tested_at'=>''];
        $path = $this->path();
        if (!file_exists($path) && !is_link($path)) {
            return $defaults;
        }
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Location provider settings could not be read safely.');
        }
        $contents = file_get_contents($path);
        if (!is_string($contents) || strlen($contents) > 8192 || !str_starts_with(trim($contents), 'v1:')) {
            throw new RuntimeException('Location provider settings are invalid.');
        }
        $binary = base64_decode(substr(trim($contents), 3), true);
        if (!is_string($binary) || strlen($binary) < 29) {
            throw new RuntimeException('Location provider settings are invalid.');
        }
        $plain = openssl_decrypt(substr($binary,28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($binary,0,12), substr($binary,12,16), self::AAD);
        if (!is_string($plain)) {
            throw new RuntimeException('Location provider settings could not be decrypted.');
        }
        $stored = json_decode($plain, true);
        if (!is_array($stored) || !in_array($stored['provider'] ?? null, ['simple','mapbox'], true)) {
            throw new RuntimeException('Location provider settings are invalid.');
        }
        return array_replace($defaults, array_intersect_key($stored,$defaults));
    }

    /** @param array<string,string> $config */
    public function save(array $config): void
    {
        if (!in_array($config['provider'] ?? null, ['simple','mapbox'], true)) {
            throw new RuntimeException('Unsupported location provider.');
        }
        $content = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($content, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, self::AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('Location provider encryption failed.');
        }
        $path = $this->path();
        $dir = dirname($path);
        if (is_link($dir) || is_link($path)) {
            throw new RuntimeException('Location provider settings path is unsafe.');
        }
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create protected location provider directory.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Protected location provider directory is not writable.');
        }
        $temp = tempnam($dir, '.location-provider-');
        if (!is_string($temp)) {
            throw new RuntimeException('Unable to stage location provider settings.');
        }
        try {
            @chmod($temp, 0600);
            $data = 'v1:'.base64_encode($nonce.$tag.$cipher).PHP_EOL;
            if (file_put_contents($temp, $data, LOCK_EX) !== strlen($data) || !rename($temp,$path)) {
                throw new RuntimeException('Unable to save protected location provider settings.');
            }
        } finally {
            if (is_file($temp)) @unlink($temp);
        }
    }

    public function activeProvider(): string
    {
        $config = $this->get();
        if ($config['provider'] !== 'mapbox') return 'simple';
        return $this->tested($config) ? 'mapbox' : 'simple';
    }

    /** @param array<string,string> $config */
    public function tested(array $config): bool
    {
        $token = (string)($config['token'] ?? '');
        $digest = (string)($config['tested_digest'] ?? '');
        return $token !== '' && strlen($digest) === 64 && hash_equals($digest, hash('sha256', $token));
    }

    private function key(): string
    {
        $key = trim((string)env('APP_KEY',''));
        if ($key === '' || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('APP_KEY and OpenSSL are required for Mapbox credential storage.');
        }
        $decoded = base64_decode($key, true);
        return hash('sha256', is_string($decoded) && $decoded !== '' ? $decoded : $key, true);
    }

    private function path(): string
    {
        return APP_PATH . '/storage/app/location-provider/provider.enc';
    }
}
