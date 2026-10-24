<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * BARAMA_DEMO_PUBLISHER_DOWNLOAD_V101
 * Guarded, bounded single-use download. The package is NEVER installed here.
 * Published checksum + detached Ed25519 signature must match a separately
 * pinned publisher key, even after an authorized private R2 redirect.
 */
final class DemoPublisherDownloadV1
{
    private const SOURCE = 'https://tonsamith.com';
    private const MAX_SIZE = 67108864;
    /** @var callable|null */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * @param array<string,mixed> $ticket Output of DemoPublisherApiV1::authorize
     * @param array<string,string> $identity Actual key info from signingIdentity()
     * @return array{path:string,sha256:string,bytes:int,signature_verified:bool}
     */
    public function receive(array $ticket, array $identity, string $pinnedFingerprint, string $vault): array
    {
        $metadata = $ticket['metadata'] ?? null;
        $path = (string)($ticket['download_path'] ?? '');
        if (!is_array($metadata) ||
            !preg_match('~^/api/demo-import/v1/packages/([A-Za-z0-9-]{5,90})/download\?token=([A-Za-z0-9_-]{32,128})$~D', $path, $groups) ||
            $groups[1] !== ($metadata['id'] ?? null) ||
            $groups[2] !== ($ticket['ticket'] ?? null)) {
            throw new RuntimeException('DEMO_DOWNLOAD_TICKET_PATH_INVALID');
        }
        $expectedHash = (string)($metadata['sha256'] ?? '');
        $signer = (string)($metadata['key_id'] ?? '');
        $public = (string)($identity['public_key'] ?? '');
        $fingerprint = (string)($identity['fingerprint'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1 ||
            preg_match('/^[a-f0-9]{64}$/D', $pinnedFingerprint) !== 1 ||
            !hash_equals($pinnedFingerprint, $fingerprint) ||
            !hash_equals($signer, (string)($identity['key_id'] ?? '')) ||
            !hash_equals($signer, substr($fingerprint, 0, 24))) {
            throw new RuntimeException('DEMO_PUBLISHER_KEY_NOT_PINNED_OR_CHANGED');
        }
        $dir = realpath($vault);
        if ($dir === false || is_link($vault) || !is_dir($dir) ||
            (fileperms($dir) & 0077) !== 0) {
            throw new RuntimeException('DEMO_PRIVATE_VAULT_NOT_READY');
        }
        $tmp = tempnam($dir, 'demo-receive-');
        if ($tmp === false) throw new RuntimeException('DEMO_PRIVATE_FILE_UNAVAILABLE');
        chmod($tmp, 0600);
        $out = fopen($tmp, 'w+b');
        if ($out === false) throw new RuntimeException('DEMO_PRIVATE_FILE_UNAVAILABLE');
        $finished = false;
        try {
            $this->stream(self::SOURCE . $path, $out, false);
            fflush($out);
            $size = ftell($out);
            fclose($out);
            $out = null;
            if (!is_int($size) || $size < 500 || $size > self::MAX_SIZE) {
                throw new RuntimeException('DEMO_PACKAGE_SIZE_REJECTED');
            }
            $actual = hash_file('sha256', $tmp);
            if (!hash_equals($expectedHash, $actual) ||
                !DemoPublisherApiV1::verifyPublishedSignature(
                    $expectedHash, (string)($metadata['signature'] ?? ''),
                    $public, $pinnedFingerprint)) {
                throw new RuntimeException('DEMO_PACKAGE_SIGNATURE_OR_SHA256_INVALID');
            }
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('DEMO_PACKAGE_ZIP_INVALID');
            }
            try {
                if (!$zip->locateName('manifest.json') && $zip->locateName('manifest.json') !== 0) {
                    throw new RuntimeException('DEMO_PACKAGE_MANIFEST_MISSING');
                }
            } finally { $zip->close(); }
            $destination = $dir . '/staged-' . bin2hex(random_bytes(16)) . '.zip';
            if (!rename($tmp, $destination)) {
                throw new RuntimeException('DEMO_VERIFIED_PACKAGE_STAGE_FAILED');
            }
            chmod($destination, 0600);
            $finished = true;
            return ['path' => $destination, 'sha256' => $actual,
                'bytes' => $size, 'signature_verified' => true];
        } finally {
            if (is_resource($out)) fclose($out);
            if (!$finished) @unlink($tmp);
        }
    }

    /** A redirect may be followed only to a recognized private R2 endpoint. */
    private function stream(string $url, $output, bool $cloud): void
    {
        $result = $this->transport !== null
            ? ($this->transport)($url, $output, $cloud)
            : $this->https($url, $output);
        if (!is_array($result)) throw new RuntimeException('DEMO_DOWNLOAD_TRANSPORT_FAILED');
        $status = (int)($result['status'] ?? 0);
        if ($status === 200) return;
        if (!$cloud && $status === 303) {
            $target = (string)($result['location'] ?? '');
            if (!$this->allowedCloud($target)) {
                throw new RuntimeException('DEMO_DOWNLOAD_REDIRECT_HOST_NOT_APPROVED');
            }
            rewind($output);
            ftruncate($output, 0);
            $this->stream($target, $output, true);
            return;
        }
        throw new RuntimeException('DEMO_DOWNLOAD_HTTP_UNAVAILABLE');
    }

    private function allowedCloud(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' ||
            isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['fragment']) || isset($parts['port'])) return false;
        $host = strtolower((string)($parts['host'] ?? ''));
        // R2 S3 API presigned URLs, never arbitrary redirect hosts or private IPs.
        return $host === 'r2.cloudflarestorage.com' ||
            (bool)preg_match('/^[a-f0-9]{32}\.r2\.cloudflarestorage\.com$/D', $host);
    }

    /** @return array{status:int,location:string} */
    private function https(string $url, $out): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('DEMO_CURL_MISSING');
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('DEMO_DOWNLOAD_CONNECTION_FAILED');
        $received = 0;
        $location = '';
        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 7,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_HTTPHEADER => ['Accept: application/zip',
                    'Cache-Control: no-store'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
                    if (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $piece) use ($out, &$received): int {
                    $received += strlen($piece);
                    if ($received > self::MAX_SIZE) return 0;
                    $n = fwrite($out, $piece);
                    return is_int($n) ? $n : 0;
                },
            ]);
            if (curl_exec($ch) !== true) throw new RuntimeException('DEMO_DOWNLOAD_CONNECTION_FAILED');
            return ['status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'location' => $location];
        } finally { curl_close($ch); }
    }
}
