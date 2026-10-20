<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Throwable;

/**
 * Restore private FULL-SITE FILE snapshots to disposable, independently
 * created /tmp clones. Never a live rollback tool. The real site/DB is not
 * modified, and this proves file fidelity only, NOT SQL checkpoint coherence.
 *
 * Requires a pre-existing authenticated receipt from
 * OneClickFullSiteSnapshotV1::verify; every copied byte and mode rechecked.
 */
final class OneClickFullSiteRestoreRehearsalV1
{
    private const SITE = '/www/wwwroot/baramastaging.tonsamith.com';
    private const CLONE = '~^/tmp/barama-fullsite-restore-fixture-[a-f0-9]{16}$~D';
    private const ID = '/^fullsite-[a-f0-9]{24}$/D';
    private const SHA = '/^[a-f0-9]{64}$/D';

    /** @return array<string,mixed> */
    public static function restoreToDisposableClone(
        string $sourceSite, string $vault, string $snapshotId,
        string $receiptSha, string $cloneSite
    ): array {
        if (PHP_SAPI !== 'cli' || !preg_match(self::ID, $snapshotId)
            || !preg_match(self::SHA, $receiptSha)) {
            throw new RuntimeException('FULLSITE_RESTORE_CLI_AND_RECEIPT_REQUIRED');
        }
        $root = dirname($cloneSite);
        if (!preg_match(self::CLONE, $root)
            || $cloneSite !== $root . '/site') {
            throw new RuntimeException('FULLSITE_RESTORE_DISPOSABLE_ONLY');
        }
        self::secureDir($root);
        self::secureDir($cloneSite);
        // If using real staging as a source, the clone also MUST be root-owned.
        if ($sourceSite === self::SITE && (!function_exists('posix_geteuid')
            || posix_geteuid() !== 0 || fileowner($root) !== 0
            || fileowner($cloneSite) !== 0)) {
            throw new RuntimeException('FULLSITE_RESTORE_ROOT_REQUIRED');
        }
        if ((scandir($cloneSite) ?: []) !== ['.', '..']) {
            throw new RuntimeException('FULLSITE_RESTORE_CLONE_MUST_BE_EMPTY');
        }
        if (!OneClickFullSiteSnapshotV1::verify(
            $sourceSite, $vault, $snapshotId, $receiptSha
        )) {
            throw new RuntimeException('FULLSITE_RESTORE_SNAPSHOT_INVALID');
        }
        $job = $vault . '/' . $snapshotId;
        try {
            $receipt = json_decode((string) file_get_contents($job . '/receipt.json'), true, 10, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('FULLSITE_RESTORE_SNAPSHOT_INVALID');
        }
        $dirs = $receipt['directories'];
        // Empty directories and original mode bits must be preserved.
        usort($dirs, static function(array $a,array $b): int {
            return substr_count($a['path'], '/') <=> substr_count($b['path'], '/')
                ?: strcmp($a['path'], $b['path']);
        });
        foreach ($dirs as $dir) {
            $target = $cloneSite . '/' . $dir['path'];
            self::safeParents($cloneSite, $dir['path']);
            if (!mkdir($target, 0700)) {
                throw new RuntimeException('FULLSITE_RESTORE_DIRECTORY_CREATE_FAILED');
            }
        }
        $n = 0; $bytes = 0;
        foreach ($receipt['files'] as $entry) {
            $rel = $entry['path'];
            self::safeParents($cloneSite, $rel);
            $source = $job . '/files/' . hash('sha256', $rel) . '.bin';
            $target = $cloneSite . '/' . $rel;
            if (is_link($source) || !is_file($source)
                || is_link($target) || file_exists($target)) {
                throw new RuntimeException('FULLSITE_RESTORE_UNTRUSTED_COPY');
            }
            $read = fopen($source,'rb');
            $write = fopen($target,'xb');
            if (!is_resource($read) || !is_resource($write)) {
                if (is_resource($read)) fclose($read);
                if (is_resource($write)) fclose($write);
                throw new RuntimeException('FULLSITE_RESTORE_IO_ERROR');
            }
            try {
                if (!chmod($target,0600)) {
                    throw new RuntimeException('FULLSITE_RESTORE_MODE_ERROR');
                }
                $hash=hash_init('sha256');$size=0;
                while (!feof($read)) {
                    $block=fread($read,65536);
                    if (!is_string($block) || ($block==='' && !feof($read))) {
                        throw new RuntimeException('FULLSITE_RESTORE_COPY_ERROR');
                    }
                    $size+=strlen($block);
                    if ($size > $entry['size']) {
                        throw new RuntimeException('FULLSITE_RESTORE_COPY_SIZE_MISMATCH');
                    }
                    hash_update($hash,$block);
                    for ($offset=0,$length=strlen($block);$offset<$length;) {
                        $written=fwrite($write,substr($block,$offset));
                        if (!is_int($written) || $written<1) {
                            throw new RuntimeException('FULLSITE_RESTORE_COPY_ERROR');
                        }
                        $offset+=$written;
                    }
                }
                if ($size!==$entry['size'] || !fflush($write)
                    || (function_exists('fsync') && !fsync($write))
                    || !hash_equals($entry['sha256'],hash_final($hash))) {
                    throw new RuntimeException('FULLSITE_RESTORE_COPY_DIGEST_MISMATCH');
                }
            } finally {
                fclose($read);fclose($write);
            }
            if (!hash_equals($entry['sha256'],(string)hash_file('sha256',$target))
                || !chmod($target,$entry['mode'])) {
                throw new RuntimeException('FULLSITE_RESTORE_COPY_REVERIFY_FAILED');
            }
            $n++;$bytes+=$size;
        }
        // Apply directory modes only after all descendants were created.
        foreach (array_reverse($dirs) as $dir) {
            if (!chmod($cloneSite.'/'.$dir['path'],$dir['mode'])) {
                throw new RuntimeException('FULLSITE_RESTORE_DIRECTORY_MODE_FAILED');
            }
        }
        if ($n !== $receipt['file_count'] || $bytes !== $receipt['total_bytes']) {
            throw new RuntimeException('FULLSITE_RESTORE_COUNT_MISMATCH');
        }
        return [
            'status'=>'FULL_SITE_FILES_RESTORED_IN_DISPOSABLE_CLONE',
            'site_modified'=>false,
            'database_accessed'=>false,
            'staging_restore_tested'=> $sourceSite===self::SITE,
            'original_site_restored'=>false,
            'sql_consistent_checkpoint_verified'=>false,
            'install_enabled'=>false,
            'files'=>$n,
            'directories'=>count($dirs),
            'bytes'=>$bytes,
        ];
    }

    private static function secureDir(string $path): void
    {
        clearstatcache(true,$path);
        if (is_link($path) || !is_dir($path) || realpath($path)!==$path
            || (fileperms($path)&0077)!==0) {
            throw new RuntimeException('FULLSITE_RESTORE_PRIVATE_DIR_REQUIRED');
        }
    }

    private static function safeParents(string $root, string $relative): void
    {
        if ($relative==='' || str_starts_with($relative,'/')
            || str_contains($relative,"\0")
            || preg_match('~(?:^|/)\.\.?(?:/|$)~',$relative)) {
            throw new RuntimeException('FULLSITE_RESTORE_PATH_INVALID');
        }
        $dir=$root;
        $parts=explode('/',$relative);
        array_pop($parts);
        foreach ($parts as $part) {
            if ($part==='') throw new RuntimeException('FULLSITE_RESTORE_PATH_INVALID');
            $dir.='/'.$part;
            if (is_link($dir) || !is_dir($dir)) {
                throw new RuntimeException('FULLSITE_RESTORE_PARENT_UNTRUSTED');
            }
        }
    }

    private function __construct()
    {
    }
}
