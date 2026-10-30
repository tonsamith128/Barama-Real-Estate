<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Closure;
use RuntimeException;

/**
 * Standalone publisher client: safe to load before Laravel, Composer and .env.
 * Uses the original Barama License Manager v1 JSON contract; purchase codes
 * are never saved, displayed, logged, or included in local receipts.
 */
final class PublisherLicenseClient
{
    private const PRODUCT = 'barama-real-estate';
    private const SERVER = 'https://tonsamith.com';
    private const MAX_BODY = 16384;

    public static function installationId(string $root): string
    {
        $file = $root.'/storage/app/license-installation-id';
        if (is_file($file)) {
            $id = trim((string) file_get_contents($file));
            if (preg_match('/^[a-f0-9]{32}$/D', $id) === 1) {
                return $id;
            }
            throw new RuntimeException('Invalid saved installation identity.');
        }

        $id = bin2hex(random_bytes(16));
        $handle = @fopen($file, 'x');
        if ($handle === false) {
            if (is_file($file)) {
                return self::installationId($root);
            }
            throw new RuntimeException('Private storage must be writable for license activation.');
        }
        chmod($file, 0600);
        try {
            if (fwrite($handle, $id."\n") !== strlen($id) + 1) {
                throw new RuntimeException('Cannot create private installation identity.');
            }
        } finally {
            fclose($handle);
        }
        return $id;
    }

    public static function normalizedDomain(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** @return array<string,mixed> */
    public static function activate(
        string $purchaseCode,
        string $domain,
        string $installationId,
        ?Closure $testTransport = null,
    ): array {
        $purchaseCode = trim($purchaseCode);
        $domain = self::normalizedDomain($domain);
        if (strlen($purchaseCode) < 8 || strlen($purchaseCode) > 120 ||
            preg_match('/^[A-Za-z0-9-]+$/D', $purchaseCode) !== 1) {
            throw new RuntimeException('Enter a valid purchase code or issued license key.');
        }
        self::validateIdentity($domain, $installationId);
        $method = preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $purchaseCode)
            ? 'envato' : 'direct';

        $payload = [
            'purchase_code' => $purchaseCode,
            'product_slug' => self::PRODUCT,
            'domain' => $domain,
            'installation_id' => $installationId,
            'verification_method' => $method,
        ];

        $answer = $testTransport
            ? $testTransport('/license/v1/activate', $payload)
            : self::post('/license/v1/activate', $payload);
        unset($purchaseCode, $payload);

        return self::validatedReceipt($answer, $domain, $installationId);
    }

    /** @return array<string,mixed> */
    public static function verify(string $token, string $domain, string $installationId, ?Closure $testTransport = null): array
    {
        self::validateIdentity($domain, $installationId);
        if (strlen($token) < 16 || strlen($token) > 1024) {
            throw new RuntimeException('No valid activation receipt was found.');
        }
        $payload = [
            'activation_token' => $token,
            'domain' => $domain,
            'installation_id' => $installationId,
        ];
        $answer = $testTransport
            ? $testTransport('/license/v1/validate', $payload)
            : self::post('/license/v1/validate', $payload);
        return self::validatedReceipt($answer, $domain, $installationId, $token);
    }

    private static function validateIdentity(string $domain, string $id): void
    {
        $validDomain = preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain) === 1
            || in_array($domain, ['localhost', '127.0.0.1'], true);
        if (!$validDomain || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new RuntimeException('Invalid license domain or installation identity.');
        }
    }

    /** @return array<string,mixed> */
    private static function validatedReceipt(mixed $reply, string $domain, string $id, ?string $previousToken = null): array
    {
        if (!is_array($reply) || ($reply['success'] ?? null) !== true ||
            !is_array($reply['license'] ?? null)) {
            throw new RuntimeException('Purchase code could not be verified by the licensing service.');
        }
        $license = $reply['license'];
        $token = $previousToken ?? ($reply['activation_token'] ?? null);
        if (!is_string($token) || strlen($token) < 16 || strlen($token) > 1024 ||
            ($license['product_slug'] ?? null) !== self::PRODUCT ||
            self::normalizedDomain((string) ($license['domain'] ?? '')) !== $domain ||
            (isset($license['installation_id']) && $license['installation_id'] !== '' &&
                $license['installation_id'] !== $id) ||
            ($license['status'] ?? null) !== 'active') {
            throw new RuntimeException('License response does not match this product, domain or installation.');
        }
        return [
            'product_slug' => self::PRODUCT,
            'domain' => $domain,
            'installation_id' => $id,
            'status' => 'active',
            'activation_token' => $token,
            'source' => (string) ($license['source'] ?? ''),
            'validated_at' => gmdate('c'),
        ];
    }

    /** @param array<string,mixed> $payload */
    private static function post(string $path, array $payload): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP cURL is required for license verification.');
        }
        $curl = curl_init(self::SERVER.$path);
        if ($curl === false) {
            throw new RuntimeException('License service is unavailable.');
        }
        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'Barama-Laravel-License/1.0',
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if (!is_string($body) || strlen($body) > self::MAX_BODY ||
                $status < 200 || $status >= 500) {
                throw new RuntimeException('License service is unavailable; no installation data was changed.');
            }
            $json = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($json) || array_is_list($json)) {
                throw new RuntimeException('Invalid license service response.');
            }
            return $json;
        } finally {
            curl_close($curl);
        }
    }

    public static function receipt(string $root): ?array
    {
        $file = $root.'/storage/app/license-activation.json';
        if (!is_file($file)) {
            return null;
        }
        $record = json_decode((string) file_get_contents($file), true);
        return is_array($record) &&
            ($record['product_slug'] ?? null) === self::PRODUCT &&
            ($record['status'] ?? null) === 'active' &&
            is_string($record['activation_token'] ?? null)
            ? $record : null;
    }

    public static function saveReceipt(string $root, array $record): void
    {
        if (($record['product_slug'] ?? null) !== self::PRODUCT ||
            ($record['status'] ?? null) !== 'active' ||
            !is_string($record['activation_token'] ?? null)) {
            throw new RuntimeException('Cannot store an unverified license.');
        }
        $target = $root.'/storage/app/license-activation.json';
        $temp = $target.'.'.bin2hex(random_bytes(8)).'.tmp';
        $handle = @fopen($temp, 'x');
        if ($handle === false) {
            throw new RuntimeException('Private license storage is unavailable.');
        }
        chmod($temp, 0600);
        try {
            $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (fwrite($handle, $json."\n") !== strlen($json) + 1) {
                throw new RuntimeException('Failed to persist license receipt.');
            }
        } finally {
            fclose($handle);
        }
        if (!rename($temp, $target)) {
            @unlink($temp);
            throw new RuntimeException('Failed to activate verified license receipt.');
        }
    }
}
