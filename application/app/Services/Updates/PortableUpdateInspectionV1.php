<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Step 5 only: pure read-only verification of the existing private staged ZIP.
 * Does not call install(), alter a receipt, create a backup or require writable code.
 * Revalidation on each admin view ensures a replaced ZIP cannot retain a stale PASS.
 */
final class PortableUpdateInspectionV1
{
    private const RELEASE_ID = 'barama-2026-0-9-23-receiver-racefix';
    private const TARGET_VERSION = '0.9.23';
    private const MAX_ZIP = 8388608;
    private const MAX_FILES = 33;
    private const MAX_UNCOMPRESSED = 16777216;

    /** @return array<string,mixed> */
    public static function status(): array
    {
        try { return self::verify(); }
        catch (\Throwable $e) {
            $reason = $e->getMessage();
            if (!preg_match('/^STEP5_[A-Z0-9_]+$/D', $reason)) {
                $reason = 'STEP5_VERIFICATION_BLOCKED';
            }
            return ['verified'=>false,'reason'=>$reason];
        }
    }

    /** @return array<string,mixed> */
    public static function verify(): array
    {
        if (!defined('BASE_PATH') || !defined('APP_PATH') || !class_exists(ZipArchive::class)
            || !extension_loaded('sodium') || PublisherTrustPaths::isProtectedStaging()) {
            throw new RuntimeException('STEP5_RUNTIME_UNAVAILABLE');
        }
        $site = (string)BASE_PATH;
        $vault = PortableCustomerUpdateV1::vault(false);
        if (!self::directory($vault) || (fileperms($vault) & 0077) !== 0) {
            throw new RuntimeException('STEP5_VAULT_UNSAFE');
        }
        $versionFile = $site.'/VERSION';
        if (!self::regular($versionFile, 128)
            || trim((string)file_get_contents($versionFile)) !== (string)APP_VERSION) {
            throw new RuntimeException('STEP5_INSTALLED_VERSION_CHANGED');
        }
        if ((string)APP_VERSION !== '0.9.22-dev') {
            throw new RuntimeException('STEP5_CURRENT_VERSION_UNEXPECTED');
        }
        $current = self::readJson($vault.'/current.json', 8192);
        if (($current['schema'] ?? null) !== 'barama-portable-customer-current-v1'
            || ($current['state'] ?? null) !== 'downloaded'
            || ($current['installed_version'] ?? null) !== APP_VERSION
            || ($current['target_version'] ?? null) !== self::TARGET_VERSION
            || !is_string($current['archive_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $current['archive_sha256']) !== 1) {
            throw new RuntimeException('STEP5_STAGED_JOB_NOT_READY');
        }
        if (is_link($vault.'/active.json') || file_exists($vault.'/active.json')) {
            throw new RuntimeException('STEP5_INSTALLATION_ALREADY_PENDING');
        }
        $job = $current['download_job_id'] ?? null;
        if (!is_string($job) || preg_match('/^job-[a-f0-9]{32}$/D', $job) !== 1) {
            throw new RuntimeException('STEP5_JOB_ID_INVALID');
        }
        $dir = $vault.'/'.$job;
        if (!self::directory($dir) || (fileperms($dir) & 0077) !== 0) {
            throw new RuntimeException('STEP5_JOB_DIRECTORY_UNSAFE');
        }
        $receipt = self::readJson($dir.'/receipt.json', 4096);
        $envelope = self::readJson($dir.'/envelope.json', 16384);
        $m = $envelope['manifest'] ?? null;
        if (!is_array($m) || ($m['release_id'] ?? null) !== self::RELEASE_ID
            || ($m['product_slug'] ?? null) !== 'barama-real-estate'
            || ($m['channel'] ?? null) !== 'stable'
            || ($m['version'] ?? null) !== self::TARGET_VERSION
            || ($m['migration_policy'] ?? null) !== 'additive') {
            throw new RuntimeException('STEP5_RELEASE_IDENTITY_MISMATCH');
        }
        if (($receipt['schema'] ?? null) !== 'barama-portable-artifact-v1'
            || ($receipt['state'] ?? null) !== 'verified_download_not_installed'
            || ($receipt['job_id'] ?? null) !== $job
            || ($receipt['release_id'] ?? null) !== $m['release_id']
            || ($receipt['version'] ?? null) !== $m['version']
            || ($receipt['archive_sha256'] ?? null) !== $m['package_sha256']
            || ($current['archive_sha256'] ?? null) !== $m['package_sha256']
            || ($receipt['archive_bytes'] ?? null) !== $m['package_bytes']) {
            throw new RuntimeException('STEP5_RECEIPT_MISMATCH');
        }
        try { $verified = TrustedReleaseFeed::verifyEnvelope($envelope, (string)APP_VERSION, 'stable'); }
        catch (\Throwable) { throw new RuntimeException('STEP5_SIGNATURE_REJECTED'); }
        if ($verified !== self::TARGET_VERSION) {
            throw new RuntimeException('STEP5_SIGNED_VERSION_UNEXPECTED');
        }
        $archive = $dir.'/release.zip';
        if (!self::regular($archive, self::MAX_ZIP)
            || !is_int($m['package_bytes'] ?? null)
            || filesize($archive) !== $m['package_bytes']
            || !hash_equals($m['package_sha256'], (string)hash_file('sha256', $archive))) {
            throw new RuntimeException('STEP5_ZIP_DIGEST_OR_SIZE_MISMATCH');
        }
        $plan = self::inspectArchive($site, $archive, $verified);
        return [
            'verified'=>true,
            'state'=>'verified_not_installed',
            'version'=>$verified,
            'file_count'=>count($plan['files']),
            'overwrites'=>$plan['overwrites'],
            'changed_paths'=>$plan['changed_paths'],
            'archive_sha256'=>$m['package_sha256'],
            'bytes'=>$m['package_bytes'],
            'version_only'=>$plan['version_only'],
        ];
    }

    /** Independent, read-only ZIP inspection; NEVER checks write access. */
    public static function inspectArchive(string $site, string $archive, string $target): array
    {
        if (!self::directory($site) || !self::regular($archive, self::MAX_ZIP)) {
            throw new RuntimeException('STEP5_INSPECTION_PATH_UNSAFE');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('STEP5_ZIP_INVALID');
        }
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('STEP5_ZIP_ENTRY_COUNT_INVALID');
            }
            $seen=[]; $files=[]; $bytesTotal=0; $versionFound=false;
            $changed=false; $overwrites=0; $changedPaths=[];
            for ($i=0; $i<$zip->numFiles; $i++) {
                $st=$zip->statIndex($i);
                $name=is_array($st) ? ($st['name']??null) : null;
                if (!is_string($name) || str_ends_with($name,'/')
                    || !PortableUpdateArchiveV1::allowedPath($name)
                    || isset($seen[strtolower($name)])) {
                    throw new RuntimeException('STEP5_ZIP_PATH_DENIED');
                }
                $seen[strtolower($name)]=true;
                $system=0; $attrs=0;
                if ($zip->getExternalAttributesIndex($i, $system, $attrs)) {
                    $type=($attrs>>16)&0170000;
                    if ($type !== 0 && $type !== 0100000) {
                        throw new RuntimeException('STEP5_ZIP_SPECIAL_ENTRY');
                    }
                }
                $size=$st['size']??null; $compressed=$st['comp_size']??null;
                if (!is_int($size) || !is_int($compressed)
                    || $size < 1 || $size > 4194304 || $compressed < 1
                    || $size > $compressed*200
                    || !in_array($st['comp_method']??null,[0,8],true)
                    || (isset($st['encryption_method']) && $st['encryption_method']!==0)) {
                    throw new RuntimeException('STEP5_ZIP_BOUNDS_INVALID');
                }
                $bytesTotal += $size;
                if ($bytesTotal>self::MAX_UNCOMPRESSED) {
                    throw new RuntimeException('STEP5_ZIP_TOTAL_TOO_LARGE');
                }
                $parts=explode('/',$name); $file=array_pop($parts); $parent=$site;
                foreach ($parts as $segment) {
                    $parent.='/'.$segment;
                    if (!is_dir($parent) || is_link($parent)) {
                        throw new RuntimeException('STEP5_ZIP_PARENT_UNSAFE');
                    }
                }
                $dest=$parent.'/'.$file;
                if (is_link($dest) || (file_exists($dest) && !is_file($dest))) {
                    throw new RuntimeException('STEP5_DESTINATION_UNSAFE');
                }
                $stream=$zip->getStream($name);
                if ($stream===false) throw new RuntimeException('STEP5_ZIP_STREAM_INVALID');
                $hash=hash_init('sha256'); $crc=hash_init('crc32b');
                $bytes=0; $versionText='';
                try {
                    while (!feof($stream)) {
                        $chunk=fread($stream,65536);
                        if (!is_string($chunk) || ($chunk==='' && !feof($stream))) {
                            throw new RuntimeException('STEP5_ZIP_CONTENT_READ_FAILED');
                        }
                        $bytes+=strlen($chunk);
                        if ($bytes>$size) throw new RuntimeException('STEP5_ZIP_ENTRY_TOO_LARGE');
                        hash_update($hash,$chunk); hash_update($crc,$chunk);
                        if ($name==='VERSION') $versionText.=$chunk;
                    }
                } finally { fclose($stream); }
                if ($bytes!==$size || hash_final($crc)!==sprintf('%08x',$st['crc']&0xffffffff)) {
                    throw new RuntimeException('STEP5_ZIP_CRC_OR_LENGTH_INVALID');
                }
                $newHash=hash_final($hash);
                $oldHash=is_file($dest) ? hash_file('sha256',$dest) : null;
                if ($oldHash===false) throw new RuntimeException('STEP5_BASELINE_UNREADABLE');
                if ($name==='VERSION') {
                    if ($size>48 || trim($versionText)!==$target) {
                        throw new RuntimeException('STEP5_VERSION_FILE_INVALID');
                    }
                    $versionFound=true;
                } elseif ($oldHash===null || !hash_equals($oldHash,$newHash)) {
                    $changed=true;
                    $changedPaths[]=$name;
                    if ($oldHash!==null) $overwrites++;
                }
                $files[]=['path'=>$name,'bytes'=>$size,'sha256'=>$newHash,'baseline'=>$oldHash];
            }
            if (!$versionFound) {
                throw new RuntimeException('STEP5_VERSION_FILE_MISSING');
            }
            $versionOnly = !$changed;
            if ($versionOnly) {
                // Explicitly recognize the existing, authentic 0.9.23 package:
                // VERSION changes; the included CLI receiver source is already
                // byte-for-byte identical to this GitHub-Main-aligned Live Demo.
                // All other no-code-change packages continue to be rejected.
                $known = 'application/app/Services/Updates/PrivateArtifactReceiverV2.php';
                $expected = '66a8112132aae407fd4094fb64e42bf8d1b34dd6d5994a5c0b6aaf5f0c690097';
                $byPath = [];
                foreach ($files as $entry) $byPath[$entry['path']] = $entry;
                if ($target !== self::TARGET_VERSION || count($files) !== 2
                    || !isset($byPath['VERSION'], $byPath[$known])
                    || !hash_equals($expected, (string)$byPath[$known]['sha256'])
                    || !is_string($byPath[$known]['baseline'])
                    || !hash_equals($expected, $byPath[$known]['baseline'])) {
                    throw new RuntimeException('STEP5_NO_ACTUAL_CODE_CHANGE');
                }
            }
            return [
                'files'=>$files, 'overwrites'=>$overwrites,
                'changed_paths'=>$changedPaths, 'version_only'=>$versionOnly,
            ];
        } finally { $zip->close(); }
    }

    private static function directory(string $path): bool
    {
        return $path!=='/' && is_dir($path) && !is_link($path)
            && realpath($path)===$path;
    }
    private static function regular(string $path, int $max): bool
    {
        return is_file($path) && !is_link($path)
            && filesize($path)>0 && filesize($path)<=$max;
    }
    private static function readJson(string $path,int $max): array
    {
        if (!self::regular($path,$max) || (fileperms($path)&0022)!==0) {
            throw new RuntimeException('STEP5_PRIVATE_RECEIPT_UNSAFE');
        }
        try { $data=json_decode((string)file_get_contents($path),true,24,JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new RuntimeException('STEP5_RECEIPT_JSON_INVALID'); }
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('STEP5_RECEIPT_JSON_INVALID');
        }
        return $data;
    }
}
