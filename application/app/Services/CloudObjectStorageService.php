<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\CloudStorageRepository;
use RuntimeException;

final class CloudObjectStorageService
{
    public function __construct(
        private readonly string $provider,
        private readonly ?array $providerConfig = null
    ) {
        if (!in_array($provider, ['s3', 'r2'], true)) {
            throw new RuntimeException('Unsupported cloud storage provider.');
        }
    }

    public function putFile(string $localPath, string $objectKey, string $mimeType): void
    {
        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new RuntimeException('Cloud upload source file is unavailable.');
        }

        $size = filesize($localPath);
        $hash = hash_file('sha256', $localPath);
        if ($size === false || !is_string($hash)) {
            throw new RuntimeException('Cloud upload source could not be verified.');
        }

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cloud upload source could not be opened.');
        }

        try {
            $response = $this->request(
                'PUT',
                $objectKey,
                $hash,
                [
                    'Content-Type: ' . $mimeType,
                    'Content-Length: ' . $size,
                ],
                static function ($curl) use ($handle, $size): void {
                    curl_setopt($curl, CURLOPT_UPLOAD, true);
                    curl_setopt($curl, CURLOPT_INFILE, $handle);
                    curl_setopt($curl, CURLOPT_INFILESIZE, $size);
                }
            );
        } finally {
            fclose($handle);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(
                'Cloud storage upload failed with HTTP ' . $response['status'] . '.'
            );
        }
    }

    /** @return array{status:int,body:string,headers:array<string,string>} */
    public function get(string $objectKey, ?string $range = null): array
    {
        $headers = [];
        if (is_string($range) && preg_match('/^bytes=\d*-\d*$/', trim($range)) === 1) {
            $headers[] = 'Range: ' . trim($range);
        }

        return $this->request(
            'GET',
            $objectKey,
            hash('sha256', ''),
            $headers
        );
    }

    public function delete(string $objectKey): void
    {
        $response = $this->request(
            'DELETE',
            $objectKey,
            hash('sha256', '')
        );

        if (!in_array($response['status'], [200, 202, 204, 404], true)) {
            throw new RuntimeException(
                'Cloud storage deletion failed with HTTP ' . $response['status'] . '.'
            );
        }
    }

    public function writeTest(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'barama-cloud-');
        if (!is_string($temporary)) {
            throw new RuntimeException('A temporary cloud-storage test file could not be created.');
        }

        $key = $this->prefix()
            . '.barama-health/'
            . bin2hex(random_bytes(10))
            . '.txt';

        try {
            if (file_put_contents($temporary, 'Barama cloud storage write test') === false) {
                throw new RuntimeException('A temporary cloud-storage test file could not be written.');
            }

            $this->putFile($temporary, $key, 'text/plain');
            $read = $this->get($key);
            if ($read['status'] !== 200 || $read['body'] !== 'Barama cloud storage write test') {
                throw new RuntimeException('Cloud storage write/read verification failed.');
            }
            $this->delete($key);
        } finally {
            @unlink($temporary);
        }
    }

    public function objectKey(string $relativePath): string
    {
        return $this->prefix() . ltrim($relativePath, '/');
    }

    /** @return array{status:int,body:string,headers:array<string,string>} */
    private function request(
        string $method,
        string $objectKey,
        string $payloadHash,
        array $extraHeaders = [],
        ?callable $configure = null
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for cloud storage.');
        }

        $config = $this->config();
        $bucket = (string) $config['bucket'];
        $region = $this->provider === 'r2' ? 'auto' : (string) $config['region'];
        $accessKey = (string) $config['access_key_id'];
        $secret = (string) $config['secret_access_key'];

        if ($bucket === '' || $accessKey === '' || $secret === '') {
            throw new RuntimeException('Cloud storage credentials are incomplete.');
        }

        if ($this->provider === 'r2') {
            $accountId = (string) $config['account_id'];
            if ($accountId === '') {
                throw new RuntimeException('Cloudflare R2 account ID is required.');
            }
            $host = $accountId . '.r2.cloudflarestorage.com';
        } else {
            $host = 's3.' . $region . '.amazonaws.com';
        }

        $canonicalUri = '/' . rawurlencode($bucket);
        $key = trim($objectKey, '/');
        if ($key !== '') {
            $canonicalUri .= '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        }

        $url = 'https://' . $host . $canonicalUri;
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $canonicalHeaders = 'host:' . $host . "\n"
            . 'x-amz-content-sha256:' . $payloadHash . "\n"
            . 'x-amz-date:' . $amzDate . "\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = strtoupper($method) . "\n"
            . $canonicalUri . "\n\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;
        $scope = $date . '/' . $region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $scope . "\n"
            . hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $responseHeaders = [];
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Cloud storage request could not be initialized.');
        }

        $headers = array_merge([
            'Authorization: ' . $authorization,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
        ], $extraHeaders);

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || str_starts_with($line, 'HTTP/')) {
                    return $length;
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        if ($configure !== null) {
            $configure($curl);
        }

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException(
                'Cloud storage network request failed.' . ($error !== '' ? ' ' . $error : '')
            );
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'headers' => $responseHeaders,
        ];
    }

    /** @return array<string,mixed> */
    private function config(): array
    {
        if (is_array($this->providerConfig)) {
            return $this->providerConfig;
        }

        $all = (new CloudStorageRepository())->get();
        $config = $all['providers'][$this->provider] ?? null;
        if (!is_array($config)) {
            throw new RuntimeException('Cloud storage provider configuration is unavailable.');
        }

        return $config;
    }

    private function prefix(): string
    {
        $prefix = trim((string) ($this->config()['prefix'] ?? 'barama/'), '/');
        return $prefix === '' ? '' : $prefix . '/';
    }
}
