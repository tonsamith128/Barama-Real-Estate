<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Root-CLI-only authenticated HTTPS consumer of Publisher artifact endpoint.
 * Not connected to the customer UI or to automatic installation.
 */
final class PrivateArtifactHttpClientV2
{
    private const URL = 'https://tonsamith.com/license/v2/update/artifact';

    /**
     * @param array<string,string> $activation exact existing installation credentials, not persisted
     * @param array<string,mixed> $envelope previously retrieved signed V2 envelope
     * @param ?callable(array<string,string>,callable(string):void):void $testTransport isolated test hook, no network
     * @return array{archive:string,receipt:string,bytes:int,sha256:string}
     */
    public static function download(
        array $activation, array $envelope, string $currentVersion,
        string $channel, string $root, ?callable $testTransport = null
    ): array {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('ROOT_CLI_REQUIRED');
        // Mandatory real Publisher signature verification before transfer.
        TrustedReleaseFeed::verifyEnvelope($envelope, $currentVersion, $channel);
        $releaseId = $envelope['manifest']['release_id'] ?? null;
        if (!is_string($releaseId)) throw new RuntimeException('SIGNED_RELEASE_ID_MISSING');
        $input = [];
        foreach (['activation_token', 'domain', 'installation_id', 'product_slug',
                  'channel', 'current_version', 'php_version'] as $field) {
            $val = $activation[$field] ?? null;
            if (!is_string($val) || $val === '' || strlen($val) > 512) {
                throw new RuntimeException('INSTALLATION_CREDENTIAL_INCOMPLETE');
            }
            $input[$field] = $val;
        }
        if ($input['product_slug'] !== 'barama-real-estate'
            || $input['channel'] !== $channel || $input['current_version'] !== $currentVersion) {
            throw new RuntimeException('INSTALLATION_CREDENTIAL_BINDING_INVALID');
        }
        $input['release_id'] = $releaseId;
        return PrivateArtifactReceiverV2::receive($envelope, $currentVersion, $channel, $root,
            static function (callable $sink) use ($input, $testTransport): void {
                if ($testTransport !== null) {
                    $testTransport($input, $sink);
                    return;
                }
                if (!extension_loaded('curl')) throw new RuntimeException('CURL_REQUIRED');
                $curl = curl_init(self::URL);
                if ($curl === false) throw new RuntimeException('HTTPS_CLIENT_UNAVAILABLE');
                $error = null;
                $bytes = 0;
                try {
                    $json = json_encode($input, JSON_THROW_ON_ERROR);
                    curl_setopt_array($curl, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $json,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/zip'],
                        CURLOPT_RETURNTRANSFER => false,
                        CURLOPT_FOLLOWLOCATION => false,
                        CURLOPT_MAXREDIRS => 0,
                        CURLOPT_CONNECTTIMEOUT => 8,
                        CURLOPT_TIMEOUT => 900,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_USERAGENT => 'Barama-Verified-Artifact-V2/1.0',
                        CURLOPT_WRITEFUNCTION => static function ($ch, string $part) use ($sink, &$error, &$bytes): int {
                            try { $sink($part); $bytes += strlen($part); return strlen($part); }
                            catch (\Throwable $e) { $error = $e; return 0; }
                        },
                    ]);
                    if (defined('CURLOPT_PROTOCOLS_STR')) {
                        curl_setopt($curl, constant('CURLOPT_PROTOCOLS_STR'), 'https');
                    } else {
                        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                    }
                    $ok = curl_exec($curl);
                    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
                    if ($error !== null) throw $error;
                    if ($ok !== true || $status !== 200 || !str_starts_with(strtolower($type), 'application/zip')) {
                        throw new RuntimeException('PUBLISHER_ARTIFACT_HTTP_NOT_ACCEPTED');
                    }
                } finally { curl_close($curl); }
            });
    }
}
