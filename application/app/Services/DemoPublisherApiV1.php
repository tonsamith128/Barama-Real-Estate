<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;
use Throwable;

/**
 * BARAMA_DEMO_PUBLISHER_API_V101
 *
 * Existing TonSamith.com API with the publisher's supported v1 routes.
 * Bounded HTTPS requests only, activation token in Authorization header only.
 * Publisher catalog is informational until its Ed25519 key is independently
 * pinned and a downloaded ZIP is verified against published metadata.
 */
final class DemoPublisherApiV1
{
    private const HOST = 'https://tonsamith.com';
    private const PREFIX = '/api/demo-import/v1';
    private const PRODUCT = 'barama-real-estate';
    private const MAX_JSON = 1048576;
    // Independently verified against publisher-side keys/public.key, 2026-10-24.
    public const PINNED_PUBLIC_KEY_SHA256 = 'be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287';

    /** @var callable|null Fixture-only transport; production uses strict cURL. */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * Check availability and observe public signing identity (not automatic trust).
     *
     * @return array{algorithm:string,key_id:string,public_key:string,fingerprint:string}
     */
    public function signingIdentity(): array
    {
        $result = $this->json('GET', self::PREFIX . '/public-key');
        $identity = $result['signing'] ?? null;
        if (($result['success'] ?? null) !== true || !is_array($identity)
            || ($identity['algorithm'] ?? null) !== 'Ed25519') {
            throw new RuntimeException('DEMO_PUBLISHER_SIGNING_IDENTITY_MISSING');
        }
        $raw = base64_decode((string)($identity['public_key'] ?? ''), true);
        if (!is_string($raw) || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('DEMO_PUBLISHER_PUBLIC_KEY_INVALID');
        }
        $fingerprint = hash('sha256', $raw);
        $keyId = (string)($identity['key_id'] ?? '');
        if (strlen($keyId) !== 24 || !hash_equals(substr($fingerprint, 0, 24), $keyId)) {
            throw new RuntimeException('DEMO_PUBLISHER_KEY_ID_MISMATCH');
        }
        return ['algorithm' => 'Ed25519', 'key_id' => $keyId,
            'public_key' => (string)$identity['public_key'],
            'fingerprint' => $fingerprint];
    }

    /**
     * Accept the publisher's signing identity only if the full Ed25519
     * fingerprint matches the independently approved publisher-side key.
     * Key rotation is fail-closed until separately approved and deployed.
     */
    public function pinnedSigningIdentity(): array
    {
        $identity = $this->signingIdentity();
        self::requirePinnedIdentity($identity, self::PINNED_PUBLIC_KEY_SHA256);
        return $identity;
    }

    /**
     * Factored for negative/fixture coverage. Caller must supply an
     * independently verified fingerprint, NEVER one from the same API response.
     */
    public static function requirePinnedIdentity(array $identity, string $expectedFingerprint): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedFingerprint) !== 1 ||
            !isset($identity['fingerprint'], $identity['public_key'], $identity['key_id']) ||
            !is_string($identity['fingerprint']) ||
            !hash_equals($expectedFingerprint, $identity['fingerprint']) ||
            !is_string($identity['public_key']) ||
            !is_string($identity['key_id'])) {
            throw new RuntimeException('DEMO_PUBLISHER_PIN_MISMATCH');
        }
        $raw = base64_decode($identity['public_key'], true);
        if (!is_string($raw) || strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ||
            !hash_equals($expectedFingerprint, hash('sha256', $raw)) ||
            !hash_equals(substr($expectedFingerprint, 0, 24), $identity['key_id'])) {
            throw new RuntimeException('DEMO_PUBLISHER_PIN_MISMATCH');
        }
    }

    /**
     * Licensed listing includes only packages marked published by TonSamith.
     * A Draft is not discoverable and must NOT be exposed with backdoor access.
     *
     * @return array{packages:list<array<string,mixed>>,signing:array<string,mixed>}
     */
    public function discover(string $token, string $domain, string $installationId): array
    {
        $auth = $this->auth($token, $domain, $installationId);
        $path = self::PREFIX . '/products/' . self::PRODUCT . '/demos'
            . '?domain=' . rawurlencode($auth['domain'])
            . '&installation_id=' . rawurlencode($auth['installation_id']);
        $data = $this->json('GET', $path, null, $auth['token']);
        if (($data['success'] ?? false) !== true ||
            ($data['product_slug'] ?? null) !== self::PRODUCT ||
            !is_array($data['demos'] ?? null) ||
            count($data['demos']) > 100) {
            throw new RuntimeException('DEMO_PUBLISHER_CATALOG_INVALID');
        }
        $out = [];
        foreach ($data['demos'] as $row) {
            if (!is_array($row)) throw new RuntimeException('DEMO_PUBLISHER_CATALOG_INVALID');
            $out[] = $this->validateMetadata($row);
        }
        $signing = is_array($data['signing'] ?? null) ? $data['signing'] : [];
        return ['packages' => $out, 'signing' => $signing];
    }

    /** Authorize an exact published package. No installation is performed. */
    public function authorize(
        string $packageId,
        string $token,
        string $domain,
        string $installationId
    ): array {
        if (!$this->packageId($packageId)) throw new RuntimeException('DEMO_PACKAGE_ID_INVALID');
        $auth = $this->auth($token, $domain, $installationId);
        $data = $this->json('POST', self::PREFIX . '/authorize', [
            'package_id' => $packageId,
            'domain' => $auth['domain'],
            'installation_id' => $auth['installation_id'],
        ], $auth['token']);
        if (($data['success'] ?? false) !== true ||
            !is_array($data['package'] ?? null)) {
            throw new RuntimeException('DEMO_DOWNLOAD_NOT_AUTHORIZED');
        }
        $metadata = $this->validateMetadata($data['package']);
        if ($metadata['id'] !== $packageId) {
            throw new RuntimeException('DEMO_DOWNLOAD_PACKAGE_MISMATCH');
        }
        $ticket = (string)($data['download_token'] ?? '');
        $download = (string)($data['download_url'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $ticket)) {
            throw new RuntimeException('DEMO_DOWNLOAD_TOKEN_INVALID');
        }
        $expected = self::PREFIX . '/packages/' . rawurlencode($packageId)
            . '/download?token=' . rawurlencode($ticket);
        if (!hash_equals($expected, $download)) {
            throw new RuntimeException('DEMO_DOWNLOAD_PATH_NOT_APPROVED');
        }
        // The bearer download ticket is returned only to the caller. Never
        // put it in logging, flash messages, HTML, URL or persistent config.
        return ['metadata' => $metadata, 'ticket' => $ticket, 'download_path' => $download];
    }

    /**
     * Signature covers raw bytes of the package SHA-256 digest, not ZIP manifest.
     * Key is never accepted merely because an HTTPS endpoint returned it.
     */
    public static function verifyPublishedSignature(
        string $digestHex,
        string $signatureB64,
        string $publisherPublicKeyB64,
        string $pinnedFingerprint
    ): bool {
        if (!extension_loaded('sodium') ||
            preg_match('/^[a-f0-9]{64}$/D', $digestHex) !== 1 ||
            preg_match('/^[a-f0-9]{64}$/D', $pinnedFingerprint) !== 1) return false;
        $key = base64_decode($publisherPublicKeyB64, true);
        $signature = base64_decode($signatureB64, true);
        return is_string($key) && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES &&
            is_string($signature) && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES &&
            hash_equals($pinnedFingerprint, hash('sha256', $key)) &&
            sodium_crypto_sign_verify_detached($signature, hex2bin($digestHex), $key);
    }

    /** @return array<string,mixed> */
    private function validateMetadata(array $row): array
    {
        $id = (string)($row['id'] ?? '');
        $hash = (string)($row['package_sha256'] ?? '');
        $signature = (string)($row['package_signature'] ?? '');
        $keyId = (string)($row['signature_key_id'] ?? '');
        if (!$this->packageId($id) ||
            ($row['product_slug'] ?? null) !== self::PRODUCT ||
            !preg_match('/^[a-z0-9][a-z0-9-]{1,70}$/D', (string)($row['demo_slug'] ?? '')) ||
            !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?$/D', (string)($row['version'] ?? '')) ||
            preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1 ||
            !is_string(base64_decode($signature, true)) ||
            preg_match('/^[a-f0-9]{24}$/D', $keyId) !== 1 ||
            ($row['signature_algorithm'] ?? null) !== 'Ed25519' ||
            (int)($row['package_bytes'] ?? 0) < 500 ||
            (int)($row['package_bytes'] ?? 0) > 67108864) {
            throw new RuntimeException('DEMO_PUBLISHER_PACKAGE_METADATA_INVALID');
        }
        return [
            'id' => $id, 'product_slug' => self::PRODUCT,
            'demo_slug' => (string)$row['demo_slug'],
            'name' => mb_substr((string)($row['name'] ?? $row['demo_slug']), 0, 180),
            'version' => (string)$row['version'],
            'sha256' => $hash, 'signature' => $signature,
            'key_id' => $keyId, 'bytes' => (int)$row['package_bytes'],
            'latest' => ($row['is_latest'] ?? false) === true,
        ];
    }

    private function packageId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,89}$/D', $id) === 1;
    }

    /** @return array{token:string,domain:string,installation_id:string} */
    private function auth(string $token, string $domain, string $installationId): array
    {
        $token = trim($token);
        $domain = strtolower(trim($domain));
        $installationId = trim($installationId);
        if ($token === '' || strlen($token) > 512 ||
            preg_match('/[\x00-\x20\x7f]/', $token) ||
            !preg_match('/^[a-z0-9][a-z0-9.-]{3,248}$/D', $domain) ||
            str_contains($domain, '..') ||
            !preg_match('/^[a-zA-Z0-9_-]{8,120}$/D', $installationId)) {
            throw new RuntimeException('DEMO_LICENSE_BINDING_INVALID');
        }
        return ['token' => $token, 'domain' => $domain, 'installation_id' => $installationId];
    }

    /** @return array<string,mixed> */
    private function json(string $method, string $path, ?array $payload = null, string $bearer = ''): array
    {
        if (!str_starts_with($path, self::PREFIX . '/') ||
            str_contains($path, "\n") || str_contains($path, "\r") ||
            !in_array($method, ['GET','POST'], true)) {
            throw new RuntimeException('DEMO_PUBLISHER_REQUEST_DENIED');
        }
        $wire = $this->transport !== null
            ? ($this->transport)($method, $path, $payload, $bearer)
            : $this->https($method, $path, $payload, $bearer);
        if (!is_array($wire) || (int)($wire['status'] ?? 0) !== 200 ||
            !is_string($wire['body'] ?? null) || strlen($wire['body']) > self::MAX_JSON) {
            throw new RuntimeException('DEMO_PUBLISHER_HTTP_UNAVAILABLE_OR_REJECTED');
        }
        try {
            $parsed = json_decode($wire['body'], true, 20, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('DEMO_PUBLISHER_RESPONSE_INVALID');
        }
        if (!is_array($parsed)) throw new RuntimeException('DEMO_PUBLISHER_RESPONSE_INVALID');
        return $parsed;
    }

    /** @return array{status:int,body:string} */
    private function https(string $method, string $path, ?array $payload, string $bearer): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('DEMO_CURL_NOT_AVAILABLE');
        $ch = curl_init(self::HOST . $path);
        if ($ch === false) throw new RuntimeException('DEMO_PUBLISHER_CONNECT_FAILED');
        $body = '';
        try {
            $headers = ['Accept: application/json', 'Cache-Control: no-store'];
            if ($bearer !== '') $headers[] = 'Authorization: Bearer ' . $bearer;
            if ($payload !== null) $headers[] = 'Content-Type: application/json';
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POST => $method === 'POST',
                CURLOPT_POSTFIELDS => $payload === null ? null :
                    json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 18,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $piece) use (&$body): int {
                    if (strlen($body) + strlen($piece) > self::MAX_JSON) return 0;
                    $body .= $piece;
                    return strlen($piece);
                },
            ]);
            $worked = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($worked !== true) throw new RuntimeException('DEMO_PUBLISHER_CONNECT_FAILED');
            return ['status' => $status, 'body' => $body];
        } finally { curl_close($ch); }
    }
}
