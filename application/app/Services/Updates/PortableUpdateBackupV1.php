<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Web-SAPI-compatible, FILE ONLY, pre-install backup. No database, CLI, root,
 * shell, cron or remote I/O. Must receive a validated PortableUpdateArchiveV1
 * plan and a private directory OUTSIDE the served application/site tree.
 *
 * Backup completion does not authorize an install or certify SQL recovery.
 */
final class PortableUpdateBackupV1
{
    public static function prepare(string $siteRoot, string $privateVault, array $plan): array
    {
        self::directories($siteRoot, $privateVault);
        self::plan($plan);
        $id = 'job-'.bin2hex(random_bytes(16));
        $work = $privateVault.'/'.$id;
        if (file_exists($work) || is_link($work) || !mkdir($work, 0700))
            throw new RuntimeException('PORTABLE_BACKUP_JOB_CONFLICT');
        $files = $work.'/files';
        if (!mkdir($files, 0700)) throw new RuntimeException('PORTABLE_BACKUP_PRIVATE_DIR_FAILED');
        $entries = [];
        try {
            foreach ($plan['files'] as $entry) {
                $path = $entry['path'];
                $original = self::target($siteRoot, $path);
                $baseline = $entry['baseline'];
                if ($baseline === null) {
                    if (file_exists($original) || is_link($original))
                        throw new RuntimeException('PORTABLE_BACKUP_SOURCE_CHANGED');
                    $entries[] = ['path'=>$path,'baseline'=>null,'backup'=>null];
                    continue;
                }
                if (!is_file($original) || is_link($original)
                    || !hash_equals($baseline, (string)hash_file('sha256', $original)))
                    throw new RuntimeException('PORTABLE_BACKUP_SOURCE_CHANGED');
                $copy = hash('sha256', $path).'.bin';
                $out = $files.'/'.$copy;
                $inStream = fopen($original, 'rb');
                $outStream = fopen($out, 'xb');
                if (!$inStream || !$outStream)
                    throw new RuntimeException('PORTABLE_BACKUP_COPY_FAILED');
                try {
                    if (!chmod($out,0600)) throw new RuntimeException('PORTABLE_BACKUP_PERMISSION');
                    $hash = hash_init('sha256');
                    while (!feof($inStream)) {
                        $chunk = fread($inStream, 65536);
                        if (!is_string($chunk) || ($chunk === '' && !feof($inStream)))
                            throw new RuntimeException('PORTABLE_BACKUP_READ_FAILED');
                        $pos=0; $len=strlen($chunk);
                        while ($pos<$len) {
                            $written=fwrite($outStream,substr($chunk,$pos));
                            if (!is_int($written) || $written<1)
                                throw new RuntimeException('PORTABLE_BACKUP_WRITE_FAILED');
                            $pos += $written;
                        }
                        hash_update($hash,$chunk);
                    }
                    if (!fflush($outStream)) throw new RuntimeException('PORTABLE_BACKUP_FLUSH_FAILED');
                    if (function_exists('fsync') && !fsync($outStream))
                        throw new RuntimeException('PORTABLE_BACKUP_SYNC_FAILED');
                    if (!hash_equals($baseline, hash_final($hash)))
                        throw new RuntimeException('PORTABLE_BACKUP_CHANGED_DURING_COPY');
                } finally {
                    fclose($inStream); fclose($outStream);
                }
                if (!hash_equals($baseline,(string)hash_file('sha256',$out))
                    || !hash_equals($baseline,(string)hash_file('sha256',$original)))
                    throw new RuntimeException('PORTABLE_BACKUP_REVERIFY_FAILED');
                $entries[]=['path'=>$path,'baseline'=>$baseline,'backup'=>$copy];
            }
            // Confirm every original still matches immediately after the backup.
            foreach ($plan['files'] as $entry) {
                $p = self::target($siteRoot,$entry['path']);
                if ($entry['baseline'] === null) {
                    if (file_exists($p) || is_link($p))
                        throw new RuntimeException('PORTABLE_BACKUP_SOURCE_CHANGED');
                } elseif (!is_file($p) || is_link($p) ||
                    !hash_equals($entry['baseline'],(string)hash_file('sha256',$p))) {
                    throw new RuntimeException('PORTABLE_BACKUP_SOURCE_CHANGED');
                }
            }
            $receipt=[
                'schema'=>'barama-portable-file-backup-v1',
                'state'=>'verified_not_installed',
                'job_id'=>$id,
                'release_archive_sha256'=>$plan['archive_sha256'],
                'installed_version'=>$plan['installed_version'],
                'target_version'=>$plan['target_version'],
                'database_backup'=>'NOT_INCLUDED_FILE_ONLY',
                'files'=>$entries
            ];
            self::exclusiveJson($work.'/receipt.json',$receipt);
            self::verify($siteRoot,$privateVault,$id,$plan);
            return ['job_id'=>$id,'files'=>count($entries),'status'=>'FILE_BACKUP_VERIFIED_NOT_INSTALLED'];
        } catch (\Throwable $e) {
            // Preserve private, partial evidence instead of deleting a backup
            // that may be needed for investigation.
            throw $e;
        }
    }

    public static function verify(string $siteRoot, string $privateVault, string $id, array $plan): void
    {
        self::directories($siteRoot, $privateVault);
        self::plan($plan);
        if (preg_match('/^job-[a-f0-9]{32}$/D',$id)!==1)
            throw new RuntimeException('PORTABLE_BACKUP_JOB_ID_INVALID');
        $work=$privateVault.'/'.$id;
        if (is_link($work) || !is_dir($work) || (fileperms($work)&0077)!==0)
            throw new RuntimeException('PORTABLE_BACKUP_JOB_UNSAFE');
        $receipt=$work.'/receipt.json';
        if (is_link($receipt) || !is_file($receipt) || filesize($receipt)>32768)
            throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_INVALID');
        try { $r=json_decode((string)file_get_contents($receipt),true,16,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_INVALID'); }
        if (!is_array($r) || ($r['schema']??null)!=='barama-portable-file-backup-v1'
            || ($r['state']??null)!=='verified_not_installed'
            || ($r['job_id']??null)!==$id
            || ($r['release_archive_sha256']??null)!==$plan['archive_sha256']
            || ($r['installed_version']??null)!==$plan['installed_version']
            || ($r['target_version']??null)!==$plan['target_version']
            || ($r['database_backup']??null)!=='NOT_INCLUDED_FILE_ONLY'
            || !is_array($r['files']??null)
            || count($r['files'])!==count($plan['files']))
            throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_MISMATCH');
        foreach ($plan['files'] as $index=>$item) {
            $entry=$r['files'][$index]??null;
            if (!is_array($entry) || ($entry['path']??null)!==$item['path']
                || ($entry['baseline']??false)!==$item['baseline'])
                throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_MISMATCH');
            if ($item['baseline']===null) {
                if (($entry['backup']??'UNDEFINED')!==null)
                    throw new RuntimeException('PORTABLE_BACKUP_NEW_FILE_INVALID');
                continue;
            }
            $copy=hash('sha256',$item['path']).'.bin';
            $file=$work.'/files/'.$copy;
            if (($entry['backup']??null)!==$copy || is_link($file)
                || !is_file($file) || (fileperms($file)&0077)!==0
                || !hash_equals($item['baseline'],(string)hash_file('sha256',$file)))
                throw new RuntimeException('PORTABLE_BACKUP_COPY_TAMPERED');
        }
    }

    private static function directories(string $site, string $vault): void
    {
        if (is_link($site) || !is_dir($site) || realpath($site)!==$site || $site==='/'
            || is_link($vault) || !is_dir($vault) || realpath($vault)!==$vault
            || (fileperms($vault)&0077)!==0 || !is_writable($vault))
            throw new RuntimeException('PORTABLE_BACKUP_ROOT_UNSAFE');
        // A customer backup must never live under the web-installation root.
        if (str_starts_with($vault.'/', $site.'/') ||
            str_starts_with($site.'/', $vault.'/') || $site===$vault)
            throw new RuntimeException('PORTABLE_BACKUP_PUBLIC_OR_OVERLAPPING');
    }

    private static function plan(array $plan): void
    {
        if (!is_string($plan['archive_sha256']??null)
            || preg_match('/^[a-f0-9]{64}$/D',$plan['archive_sha256'])!==1
            || !is_string($plan['installed_version']??null)
            || !is_string($plan['target_version']??null)
            || !is_array($plan['files']??null)
            || count($plan['files'])<2 || count($plan['files'])>33)
            throw new RuntimeException('PORTABLE_BACKUP_PLAN_INVALID');
        $seen=[];
        foreach ($plan['files'] as $item) {
            $p=$item['path']??null; $baseline=$item['baseline']??null;
            if (!is_string($p) || !PortableUpdateArchiveV1::allowedPath($p)
                || isset($seen[strtolower($p)])
                || !is_string($item['sha256']??null)
                || preg_match('/^[a-f0-9]{64}$/D',$item['sha256'])!==1
                || ($baseline!==null && (!is_string($baseline)
                    || preg_match('/^[a-f0-9]{64}$/D',$baseline)!==1)))
                throw new RuntimeException('PORTABLE_BACKUP_PLAN_INVALID');
            $seen[strtolower($p)]=true;
        }
    }

    private static function target(string $root, string $path): string
    {
        if (!PortableUpdateArchiveV1::allowedPath($path))
            throw new RuntimeException('PORTABLE_BACKUP_TARGET_DENIED');
        $cursor=$root;
        foreach (explode('/',$path) as $p) {
            $cursor.='/'.$p;
            if (is_link($cursor)) throw new RuntimeException('PORTABLE_BACKUP_SYMLINK');
        }
        return $cursor;
    }

    private static function exclusiveJson(string $path, array $data): void
    {
        $h=@fopen($path,'xb');
        if (!$h) throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_EXISTS');
        try {
            if (!chmod($path,0600)) throw new RuntimeException('PORTABLE_BACKUP_PERMISSION');
            $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if (fwrite($h,$json)!==strlen($json) || !fflush($h)
                || (function_exists('fsync') && !fsync($h)))
                throw new RuntimeException('PORTABLE_BACKUP_RECEIPT_WRITE_FAILED');
        } finally { fclose($h); }
    }
}
