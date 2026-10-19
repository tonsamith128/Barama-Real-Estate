<?php

declare(strict_types=1);

namespace Barama\Services;

/**
 * Offline-only verification for author-signed CodeCanyon update metadata.
 *
 * This class NEVER downloads, extracts, writes, or installs any package.
 * The caller must obtain the manifest from a separately authenticated release
 * service and pin its signing public key outside mutable runtime settings.
 */
final class SignedUpdateManifest
{
    public function verify(
        string $json,
        string $signatureBase64,
        string $publicKeyPem,
        string $productSlug,
        string $installedVersion,
        string $allowedDownloadHost
    ): ?array {
        if ($json === '' || strlen($json) > 32768 ||
            $signatureBase64 === '' || strlen($signatureBase64) > 8192 ||
            $publicKeyPem === '' || strlen($publicKeyPem) > 8192) {
            return null;
        }

        $signature = base64_decode($signatureBase64, true);
        if ($signature === false || strlen($signature) < 128 || strlen($signature) > 1024) {
            return null;
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return null;
        }
        try {
            $details = openssl_pkey_get_details($key);
            if (!is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA ||
                ($details['bits'] ?? 0) < 3072 ||
                openssl_verify($json, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
                return null;
            }
        } finally {
            // OpenSSLAsymmetricKey uses normal PHP reference-counted cleanup.
            unset($key);
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) ||
            array_keys($data) !== ['product', 'version', 'url', 'sha256', 'bytes'] ||
            ($data['product'] ?? null) !== $productSlug ||
            !isset($data['version'], $data['url'], $data['sha256'], $data['bytes']) ||
            !is_string($data['version']) || !is_string($data['url']) ||
            !is_string($data['sha256']) || !is_int($data['bytes']) ||
            !preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $data['version']) ||
            version_compare($data['version'], $installedVersion, '<=') ||
            !preg_match('/^[0-9a-f]{64}$/D', $data['sha256']) ||
            $data['bytes'] < 1 || $data['bytes'] > 536870912) {
            return null;
        }

        $url = parse_url($data['url']);
        if ($url === false ||
            ($url['scheme'] ?? null) !== 'https' ||
            strtolower((string) ($url['host'] ?? '')) !== strtolower($allowedDownloadHost) ||
            $allowedDownloadHost === '' ||
            isset($url['user']) || isset($url['pass']) || isset($url['fragment']) ||
            (isset($url['port']) && $url['port'] !== 443) ||
            !isset($url['path']) || !str_starts_with($url['path'], '/') ||
            str_contains($url['path'], '\\') || str_contains($url['path'], '..')) {
            return null;
        }

        return $data;
    }

    public function verifyPackage(string $localPath, string $sha256, int $expectedBytes): bool
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $sha256) ||
            $expectedBytes < 1 || $expectedBytes > 536870912 ||
            !is_file($localPath) || is_link($localPath) ||
            filesize($localPath) !== $expectedBytes) {
            return false;
        }
        $actual = hash_file('sha256', $localPath);
        return is_string($actual) && hash_equals($sha256, $actual);
    }
}
