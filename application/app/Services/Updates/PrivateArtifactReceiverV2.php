<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Receives bytes from a future authenticated HTTPS transfer into root-private staging.
 * Requires a separately pinned Publisher V2 signature. No HTTP, extraction or install.
 * CLI-only: /root must not be writable by the web process.
 */
final class PrivateArtifactReceiverV2
{
    private const MAX = 536870912;

    /**
     * @param array<string,mixed> $envelope genuine signed Publisher V2 envelope
     * @param callable(callable(string):void):void $transfer byte source, no implicit network
     * @return array{archive:string,receipt:string,bytes:int,sha256:string}
     */
    public static function receive(array $envelope, string $currentVersion, string $channel, string $root, callable $transfer): array
    {
        TrustedReleaseFeed::verifyEnvelope($envelope, $currentVersion, $channel);
        $m = $envelope['manifest'];
        $size = $m['package_bytes'] ?? null;
        $sha = $m['package_sha256'] ?? null;
        $id = $m['release_id'] ?? null;
        if (!is_int($size) || $size < 1 || $size > self::MAX
            || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
            || !is_string($id) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,100}$/D', $id) !== 1) {
            throw new RuntimeException('SIGNED_ARTIFACT_METADATA_INVALID');
        }
        $base = realpath($root);
        if (!is_string($base) || !str_starts_with($base, '/root/') || is_link($root) || !is_dir($base)) {
            throw new RuntimeException('PRIVATE_STAGE_NOT_ROOT_CONFINED');
        }
        $st = stat($base);
        if (!is_array($st) || ($st['mode'] & 0077) !== 0) {
            throw new RuntimeException('PRIVATE_STAGE_PERMISSIONS_UNSAFE');
        }
        $archive = $base . '/' . $id . '-' . substr($sha, 0, 16) . '.zip';
        $receipt = $archive . '.receipt.json';
        if (file_exists($archive) || is_link($archive) || file_exists($receipt) || is_link($receipt)) {
            throw new RuntimeException('PRIVATE_STAGE_ALREADY_EXISTS');
        }
        $part = $base . '/.incoming-' . bin2hex(random_bytes(16));
        $handle = @fopen($part, 'xb');
        if ($handle === false) throw new RuntimeException('PRIVATE_STAGE_OPEN_FAILED');
        chmod($part, 0600);
        $total = 0;
        $ctx = hash_init('sha256');
        // Own only paths created by this transfer. A racing transfer or
        // owner may create the final name after the initial existence check.
        // Failure cleanup must never delete someone else's archive/receipt.
        $createdArchive = false;
        $createdReceipt = false;
        try {
            $transfer(static function (string $chunk) use (&$total, $ctx, $handle, $size): void {
                $n = strlen($chunk);
                if ($n < 1 || $total + $n > $size) throw new RuntimeException('TRANSFER_SIZE_EXCEEDED');
                if (fwrite($handle, $chunk) !== $n) throw new RuntimeException('TRANSFER_WRITE_FAILED');
                hash_update($ctx, $chunk);
                $total += $n;
            });
            if (!fflush($handle)) throw new RuntimeException('TRANSFER_FLUSH_FAILED');
            fclose($handle);
            $handle = null;
            if ($total !== $size || !hash_equals($sha, hash_final($ctx)) || filesize($part) !== $size) {
                throw new RuntimeException('TRANSFER_CHECKSUM_MISMATCH');
            }
            // Atomic no-clobber: never replace a staged archive, even during a race.
            if (!link($part, $archive)) throw new RuntimeException('TRANSFER_STAGE_CONFLICT');
            $createdArchive = true;
            unlink($part);
            // Keep receipt strictly non-sensitive: never store activation credentials.
            $record = ['schema'=>'barama-private-v2-artifact-v1','release_id'=>$id,
                'sha256'=>$sha,'bytes'=>$total,'status'=>'verified-not-installed'];
            $rh = @fopen($receipt, 'xb');
            if ($rh === false) throw new RuntimeException('TRANSFER_RECEIPT_CONFLICT');
            $createdReceipt = true;
            try {
                chmod($receipt, 0600);
                $json = json_encode($record, JSON_THROW_ON_ERROR) . "\n";
                if (fwrite($rh, $json) !== strlen($json) || !fflush($rh)) {
                    throw new RuntimeException('TRANSFER_RECEIPT_FAILED');
                }
            } finally { fclose($rh); }
            return ['archive'=>$archive,'receipt'=>$receipt,'bytes'=>$total,'sha256'=>$sha];
        } catch (\Throwable $e) {
            if (is_resource($handle)) fclose($handle);
            @unlink($part);
            if ($createdReceipt) @unlink($receipt);
            if ($createdArchive) @unlink($archive);
            throw $e;
        }
    }
}
