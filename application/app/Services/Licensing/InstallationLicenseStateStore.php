<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

use Barama\Core\Installation;
use RuntimeException;

/**
 * Store an AES-256-GCM encrypted activation token in the canonical installer
 * state. This does not create a separate customer licensing database.
 * The key is stored independently in a private 0600 file and must be included
 * in encrypted system backups. Never use a public or browser-provided key.
 */
final class InstallationLicenseStateStore implements LicenseStateStore
{
    private const FIELD = 'publisher_v2_activation';
    private const KEY_FILE = 'license-v2-encryption.php';

    /** @return array{token:string,domain:string,installation_id:string,product:string}|null */
    public function load(): ?array
    {
        $record = Installation::licenseState()[self::FIELD] ?? null;
        if ($record === null) {
            return null;
        }
        if (!is_array($record) || ($record['version'] ?? null) !== 1
            || !is_string($record['ciphertext'] ?? null)
            || !is_string($record['iv'] ?? null)
            || !is_string($record['tag'] ?? null)) {
            throw new LicenseClientException('INVALID_LICENSE_STATE', 500);
        }
        $ciphertext = base64_decode($record['ciphertext'], true);
        $iv = base64_decode($record['iv'], true);
        $tag = base64_decode($record['tag'], true);
        if (!is_string($ciphertext) || !is_string($iv) || !is_string($tag)
            || strlen($iv) !== 12 || strlen($tag) !== 16) {
            throw new LicenseClientException('INVALID_LICENSE_STATE', 500);
        }
        if (!function_exists('openssl_decrypt')) {
            throw new LicenseClientException('OPENSSL_REQUIRED', 500);
        }
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(false), OPENSSL_RAW_DATA,
            $iv, $tag, $this->aad());
        if (!is_string($plain)) {
            throw new LicenseClientException('LICENSE_STATE_DECRYPTION_FAILED', 500);
        }
        try {
            $state = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LicenseClientException('INVALID_LICENSE_STATE', 500);
        }
        if (!is_array($state) || !isset($state['token'], $state['domain'],
            $state['installation_id'], $state['product'])
            || count($state) !== 4 || !is_string($state['token'])
            || !is_string($state['domain']) || !is_string($state['installation_id'])
            || !is_string($state['product'])) {
            throw new LicenseClientException('INVALID_LICENSE_STATE', 500);
        }
        return $state;
    }

    public function save(array $state): void
    {
        if (count($state) !== 4 || !isset($state['token'], $state['domain'],
            $state['installation_id'], $state['product'])) {
            throw new LicenseClientException('INVALID_LICENSE_STATE', 500);
        }
        $plain = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $this->key(true),
            OPENSSL_RAW_DATA, $iv, $tag, $this->aad(), 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new LicenseClientException('LICENSE_STATE_ENCRYPTION_FAILED', 500);
        }
        Installation::refreshLicenseState([self::FIELD => [
            'version' => 1,
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
        ]]);
    }

    public function clear(): void
    {
        if ((Installation::licenseState()[self::FIELD] ?? null) !== null) {
            Installation::refreshLicenseState([self::FIELD => null]);
        }
    }

    private function aad(): string
    {
        return 'barama:v2:' . Installation::installationId();
    }

    private function key(bool $create): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new LicenseClientException('OPENSSL_REQUIRED', 500);
        }
        $directory = dirname(Installation::licensePath());
        if (!is_dir($directory) || is_link($directory)) {
            throw new LicenseClientException('LICENSE_PRIVATE_DIRECTORY_MISSING', 500);
        }
        $path = $directory . '/' . self::KEY_FILE;
        if (is_link($path)) {
            throw new LicenseClientException('LICENSE_KEY_FILE_INVALID', 500);
        }
        if (!file_exists($path) && $create) {
            $handle = @fopen($path, 'x');
            if ($handle !== false) {
                try {
                    @chmod($path, 0600);
                    $key = random_bytes(32);
                    $payload = "<?php\nif (!defined('BARAMA_BOOTSTRAP')) { http_response_code(404); exit; }\nreturn '" . bin2hex($key) . "';\n";
                    if (fwrite($handle, $payload) !== strlen($payload)) {
                        throw new RuntimeException('Unable to persist license encryption key');
                    }
                    fflush($handle);
                } finally {
                    fclose($handle);
                }
            }
        }
        if (is_link($path) || !is_file($path)
            || (fileperms($path) & 0077) !== 0) {
            throw new LicenseClientException('LICENSE_KEY_FILE_INVALID', 500);
        }
        $hex = require $path;
        if (!is_string($hex) || preg_match('/\A[a-f0-9]{64}\z/D', $hex) !== 1) {
            throw new LicenseClientException('LICENSE_KEY_FILE_INVALID', 500);
        }
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            throw new LicenseClientException('LICENSE_KEY_FILE_INVALID', 500);
        }
        return $bytes;
    }
}
