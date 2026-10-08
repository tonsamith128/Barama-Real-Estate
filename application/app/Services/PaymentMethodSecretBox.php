<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

final class PaymentMethodSecretBox
{
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'barama-payment-methods-v1';

    public function keyPath(): string
    {
        return APP_PATH . '/storage/app/payment-methods.key';
    }

    public function ensureKey(): string
    {
        $path = $this->keyPath();

        if (is_file($path)) {
            $decoded = base64_decode(trim((string) file_get_contents($path)), true);

            if (is_string($decoded) && strlen($decoded) === 32) {
                return $decoded;
            }

            throw new RuntimeException('Payment method secret key file is invalid.');
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create payment secret-key directory.');
        }

        $key = random_bytes(32);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($temporary, base64_encode($key) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write payment method secret key.');
        }

        @chmod($temporary, 0600);

        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to finalize payment method secret key.');
        }

        @chmod($path, 0600);
        return $key;
    }

    /** @param array<string,string> $values */
    public function encrypt(array $values): string
    {
        $json = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            throw new RuntimeException('Payment method secret encoding failed.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER,
            $this->ensureKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Payment method secret encryption failed.');
        }

        return 'v1.' . $this->b64($iv) . '.' . $this->b64($tag) . '.' . $this->b64($ciphertext);
    }

    /** @return array<string,string> */
    public function decrypt(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $parts = explode('.', $value);

        if (count($parts) !== 4 || $parts[0] !== 'v1') {
            throw new RuntimeException('Payment method secret format is invalid.');
        }

        $plaintext = openssl_decrypt(
            $this->unb64($parts[3]),
            self::CIPHER,
            $this->ensureKey(),
            OPENSSL_RAW_DATA,
            $this->unb64($parts[1]),
            $this->unb64($parts[2]),
            self::AAD
        );

        if (!is_string($plaintext)) {
            throw new RuntimeException('Payment method secret decryption failed.');
        }

        $decoded = json_decode($plaintext, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Payment method secret configuration is invalid.');
        }

        $result = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key) && is_scalar($item)) {
                $result[$key] = (string) $item;
            }
        }
        return $result;
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function unb64(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padding = strlen($padded) % 4;

        if ($padding !== 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);

        if (!is_string($decoded)) {
            throw new RuntimeException('Encrypted payment method value is malformed.');
        }

        return $decoded;
    }
}
