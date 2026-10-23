<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;
use Closure;
use Barama\Core\Installation;

/**
 * Shared-hosting compatible, bounded FILE-ONLY transaction.
 *
 * This is an internal service, not an HTTP endpoint. Only an authenticated
 * admin+CSRF controller may invoke it, after fresh publisher entitlement.
 * A file apply is NOT completed until separate HTTP/application health checks.
 * Power loss leaves "active.json" and a private journal for explicit recovery.
 */
final class PortableUpdateFileTransactionV1
{
    public static function apply(
        string $site, string $zipPath, string $vault,
        string $installedVersion, array $signedEnvelope, string $channel,
        ?Closure $testDiscovery = null
    ): array {
        if (PublisherTrustPaths::isProtectedStaging())
            throw new RuntimeException('PORTABLE_STAGING_ROOT_WORKER_ONLY');
        if ($testDiscovery !== null && (PHP_SAPI !== 'cli'
            || !defined('BARAMA_PORTABLE_FIXTURE_ONLY')
            || BARAMA_PORTABLE_FIXTURE_ONLY !== true))
            throw new RuntimeException('PORTABLE_TEST_DISCOVERY_FORBIDDEN');
        if (is_link($site.'/VERSION') || !is_file($site.'/VERSION')
            || trim((string)file_get_contents($site.'/VERSION'))!==$installedVersion)
            throw new RuntimeException('PORTABLE_INSTALLED_VERSION_CHANGED');
        // Do not equate possession of a signed manifest with ENTITLEMENT.
        // The live publisher must independently grant this installation the
        // exact release at the moment of applying files.
        $live = $testDiscovery !== null ? $testDiscovery($installedVersion)
            : TrustedReleaseFeed::check(
                $installedVersion, Installation::licenseState(),
                Installation::runtimeConfig(), null, true
            );
        if (!is_array($live)
            || ($live['status']??null)!=='available'
            || ($live['entitlement']??null)!=='Eligible'
            || ($live['feed']??null)!=='Signed release verified'
            || !is_array($live['signed_envelope']??null)
            || $live['signed_envelope'] !== $signedEnvelope)
            throw new RuntimeException('PORTABLE_LIVE_AUDIENCE_OR_RELEASE_DENIED');
        $plan = PortableUpdateArchiveV1::inspect(
            $site, $zipPath, $installedVersion, $live['signed_envelope'], $channel
        );
        $lock = self::lock($vault);
        try {
            self::noActive($vault);
            $backup = PortableUpdateBackupV1::prepare($site,$vault,$plan);
            $job=$backup['job_id'];
            $work=$vault.'/'.$job;
            $data=[
                'schema'=>'barama-portable-file-transaction-v1',
                'job_id'=>$job,
                'state'=>'prepared',
                'installed_version'=>$installedVersion,
                'target_version'=>$plan['target_version'],
                'archive_sha256'=>$plan['archive_sha256'],
                'files'=>$plan['files'],
                'database_changes'=>false
            ];
            self::writeJson($work.'/journal.json',$data);
            self::writeJson($vault.'/active.json',[
                'schema'=>'barama-portable-active-v1','job_id'=>$job
            ]);
            $zip=new ZipArchive();
            try {
                if ($zip->open($zipPath,ZipArchive::RDONLY)!==true)
                    throw new RuntimeException('PORTABLE_APPLY_ARCHIVE_UNAVAILABLE');
                if (!hash_equals($plan['archive_sha256'],(string)hash_file('sha256',$zipPath)))
                    throw new RuntimeException('PORTABLE_APPLY_ARCHIVE_CHANGED');
                $data['state']='applying';
                self::writeJson($work.'/journal.json',$data);
                // Update VERSION last, so other code is replaced first.
                $ordered=$plan['files'];
                usort($ordered,static fn($a,$b) =>
                    ($a['path']==='VERSION' ? 1 : 0) <=> ($b['path']==='VERSION' ? 1 : 0)
                );
                foreach ($ordered as $entry) {
                    self::replaceFromZip($site,$zip,$entry);
                    // The private journal was durably written BEFORE the first
                    // replacement; interrupted work is never silently resumed.
                }
                foreach ($plan['files'] as $entry) {
                    $dest=self::target($site,$entry['path']);
                    if (!is_file($dest) || is_link($dest)
                        || !hash_equals($entry['sha256'],(string)hash_file('sha256',$dest)))
                        throw new RuntimeException('PORTABLE_POST_APPLY_FILE_MISMATCH');
                }
                if (trim((string)file_get_contents($site.'/VERSION'))!==$plan['target_version'])
                    throw new RuntimeException('PORTABLE_INSTALLED_VERSION_MISMATCH');
                $data['state']='files_applied_health_pending';
                self::writeJson($work.'/journal.json',$data);
                return [
                    'job_id'=>$job,
                    'state'=>'files_applied_health_pending',
                    'target_version'=>$plan['target_version'],
                    'success'=>false,
                    'database_changed'=>false,
                    'health_verified'=>false,
                ];
            } catch (\Throwable $e) {
                // The original cause and all private job artifacts remain in
                // the vault; never show raw exception details in an admin UI.
                $data['state']='recovery_review_required';
                self::writeJson($work.'/journal.json',$data);
                try {
                    self::restore($site,$vault,$job,$plan);
                    $data['state']='rolled_back';
                    self::writeJson($work.'/journal.json',$data);
                    @unlink($vault.'/active.json');
                } catch (\Throwable) {
                    // Leave the active marker; fail closed on any future job.
                }
                throw new RuntimeException('PORTABLE_INSTALL_FAILED_CHECK_PRIVATE_RECOVERY');
            } finally {
                $zip->close();
            }
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    /** Manual, explicitly authenticated recovery entry point after hard crash. */
    public static function recover(string $site,string $vault,string $job): array
    {
        if (PublisherTrustPaths::isProtectedStaging())
            throw new RuntimeException('PORTABLE_STAGING_ROOT_WORKER_ONLY');
        if (preg_match('/^job-[a-f0-9]{32}$/D',$job)!==1)
            throw new RuntimeException('PORTABLE_RECOVERY_JOB_INVALID');
        $lock=self::lock($vault);
        try {
            self::active($vault,$job);
            $path=$vault.'/'.$job.'/journal.json';
            $j=self::readJson($path,65536);
            if (($j['schema']??'')!=='barama-portable-file-transaction-v1'
                || ($j['job_id']??'')!==$job
                || !in_array($j['state']??'',[
                    'prepared','applying','recovery_review_required',
                    'files_applied_health_pending'],true)
                || ($j['database_changes']??true)!==false)
                throw new RuntimeException('PORTABLE_RECOVERY_STATE_INVALID');
            $plan=[
                'archive_sha256'=>$j['archive_sha256']??null,
                'installed_version'=>$j['installed_version']??null,
                'target_version'=>$j['target_version']??null,
                'files'=>$j['files']??null,
            ];
            try {
                self::restore($site,$vault,$job,$plan);
                $j['state']='rolled_back';
                self::writeJson($path,$j);
                if (!unlink($vault.'/active.json'))
                    throw new RuntimeException('PORTABLE_RECOVERY_MARKER_UNAVAILABLE');
                return ['job_id'=>$job,'state'=>'rolled_back','database_changed'=>false];
            } catch (\Throwable) {
                $j['state']='recovery_review_required';
                self::writeJson($path,$j);
                throw new RuntimeException('PORTABLE_RECOVERY_NEEDS_MANUAL_REVIEW');
            }
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    private static function replaceFromZip(string $site,ZipArchive $zip,array $entry): void
    {
        $name=$entry['path'];
        $dest=self::target($site,$name);
        $baseline=$entry['baseline'];
        if ($baseline===null) {
            if (file_exists($dest) || is_link($dest))
                throw new RuntimeException('PORTABLE_INSTALL_CONCURRENT_MODIFICATION');
        } elseif (!is_file($dest) || is_link($dest) ||
            !hash_equals($baseline,(string)hash_file('sha256',$dest))) {
            throw new RuntimeException('PORTABLE_INSTALL_CONCURRENT_MODIFICATION');
        }
        $parent=dirname($dest);
        if (!is_dir($parent) || is_link($parent) || !is_writable($parent))
            throw new RuntimeException('PORTABLE_INSTALL_UNWRITABLE_PARENT');
        $source=$zip->getStream($name);
        if ($source===false) throw new RuntimeException('PORTABLE_INSTALL_STREAM_MISSING');
        $temp=$parent.'/.barama-replace-'.bin2hex(random_bytes(12));
        $handle=@fopen($temp,'xb');
        if ($handle===false) { fclose($source); throw new RuntimeException('PORTABLE_INSTALL_TEMP_UNAVAILABLE'); }
        try {
            $mode=$baseline!==null ? (fileperms($dest)&0777) : 0644;
            if (!chmod($temp,$mode)) throw new RuntimeException('PORTABLE_INSTALL_CHMOD_FAILED');
            $digest=hash_init('sha256'); $bytes=0;
            while (!feof($source)) {
                $chunk=fread($source,65536);
                if (!is_string($chunk) || ($chunk==='' && !feof($source)))
                    throw new RuntimeException('PORTABLE_INSTALL_READ_FAILED');
                $length=strlen($chunk);$bytes+=$length;
                if ($bytes>$entry['bytes'])
                    throw new RuntimeException('PORTABLE_INSTALL_ENTRY_TOO_LARGE');
                $offset=0;
                while ($offset<$length) {
                    $written=fwrite($handle,substr($chunk,$offset));
                    if (!is_int($written) || $written<1)
                        throw new RuntimeException('PORTABLE_INSTALL_WRITE_FAILED');
                    $offset+=$written;
                }
                hash_update($digest,$chunk);
            }
            if ($bytes!==$entry['bytes'] || !hash_equals($entry['sha256'],hash_final($digest))
                || !fflush($handle) || (function_exists('fsync')&&!fsync($handle)))
                throw new RuntimeException('PORTABLE_INSTALL_STAGE_FAILED');
            fclose($handle); $handle=null;
            // Recheck the target immediately before the atomic replacement.
            if (is_link($dest) || ($baseline===null
                ? file_exists($dest)
                : (!is_file($dest)||!hash_equals($baseline,(string)hash_file('sha256',$dest)))))
                throw new RuntimeException('PORTABLE_INSTALL_TARGET_CHANGED');
            if (!rename($temp,$dest))
                throw new RuntimeException('PORTABLE_INSTALL_ATOMIC_RENAME_FAILED');
        } finally {
            fclose($source);
            if (is_resource($handle)) fclose($handle);
            if (is_file($temp)) @unlink($temp);
        }
    }

    private static function restore(string $site,string $vault,string $job,array $plan): void
    {
        PortableUpdateBackupV1::verify($site,$vault,$job,$plan);
        // First check EVERY target before touching any: never overwrite
        // unrelated modifications introduced after the update started.
        foreach ($plan['files'] as $entry) {
            $dest=self::target($site,$entry['path']);
            $baseline=$entry['baseline']; $new=$entry['sha256'];
            if (is_link($dest)) throw new RuntimeException('PORTABLE_RECOVERY_SYMLINK');
            if (!file_exists($dest)) {
                if ($baseline!==null)
                    throw new RuntimeException('PORTABLE_RECOVERY_ORIGINAL_MISSING');
                continue;
            }
            if (!is_file($dest)) throw new RuntimeException('PORTABLE_RECOVERY_TARGET_INVALID');
            $actual=hash_file('sha256',$dest);
            if (!is_string($actual)
                || !hash_equals($new,$actual)
                && ($baseline===null || !hash_equals($baseline,$actual)))
                throw new RuntimeException('PORTABLE_RECOVERY_UNRELATED_CHANGE');
        }
        $ordered=$plan['files'];
        usort($ordered,static fn($a,$b) =>
            ($a['path']==='VERSION' ? 1 : 0) <=> ($b['path']==='VERSION' ? 1 : 0)
        );
        foreach ($ordered as $entry) {
            $dest=self::target($site,$entry['path']);
            $baseline=$entry['baseline'];
            if ($baseline===null) {
                if (is_file($dest) && !unlink($dest))
                    throw new RuntimeException('PORTABLE_RECOVERY_REMOVE_FAILED');
                continue;
            }
            if (is_file($dest) && hash_equals($baseline,(string)hash_file('sha256',$dest))) continue;
            $source=$vault.'/'.$job.'/files/'.hash('sha256',$entry['path']).'.bin';
            if (is_link($source) || !is_file($source)
                || !hash_equals($baseline,(string)hash_file('sha256',$source)))
                throw new RuntimeException('PORTABLE_RECOVERY_BACKUP_TAMPERED');
            $tmp=dirname($dest).'/.barama-restore-'.bin2hex(random_bytes(12));
            if (!copy($source,$tmp)) throw new RuntimeException('PORTABLE_RECOVERY_COPY_FAILED');
            try {
                if (!hash_equals($baseline,(string)hash_file('sha256',$tmp))
                    || !rename($tmp,$dest))
                    throw new RuntimeException('PORTABLE_RECOVERY_RENAME_FAILED');
            } finally { if (is_file($tmp)) @unlink($tmp); }
        }
        foreach ($plan['files'] as $entry) {
            $dest=self::target($site,$entry['path']);
            if ($entry['baseline']===null) {
                if (file_exists($dest)) throw new RuntimeException('PORTABLE_RECOVERY_NEW_FILE_REMAINS');
            } elseif (!is_file($dest) ||
                !hash_equals($entry['baseline'],(string)hash_file('sha256',$dest)))
                throw new RuntimeException('PORTABLE_RECOVERY_VERIFY_FAILED');
        }
    }

    private static function target(string $root,string $path): string
    {
        if (!PortableUpdateArchiveV1::allowedPath($path))
            throw new RuntimeException('PORTABLE_TRANSACTION_PATH_DENIED');
        $cursor=$root;
        foreach(explode('/',$path) as $p) {
            $cursor.='/'.$p;
            if (is_link($cursor)) throw new RuntimeException('PORTABLE_TRANSACTION_SYMLINK');
        }
        return $cursor;
    }

    private static function lock(string $vault)
    {
        if (is_link($vault) || !is_dir($vault) || (fileperms($vault)&0077)!==0)
            throw new RuntimeException('PORTABLE_TRANSACTION_VAULT_UNSAFE');
        $path=$vault.'/.portable-update.lock';
        if (is_link($path)) throw new RuntimeException('PORTABLE_TRANSACTION_LOCK_UNSAFE');
        $h=@fopen($path,'c+b');
        if (!$h) throw new RuntimeException('PORTABLE_TRANSACTION_LOCK_FAILED');
        if (!flock($h,LOCK_EX|LOCK_NB)) {
            fclose($h); throw new RuntimeException('PORTABLE_TRANSACTION_BUSY');
        }
        return $h;
    }

    private static function noActive(string $vault): void
    {
        if (is_link($vault.'/active.json') || file_exists($vault.'/active.json'))
            throw new RuntimeException('PORTABLE_UNFINISHED_UPDATE_REQUIRES_REVIEW');
    }

    private static function active(string $vault,string $job): void
    {
        $record=self::readJson($vault.'/active.json',2048);
        if (($record['schema']??null)!=='barama-portable-active-v1'
            || ($record['job_id']??null)!==$job)
            throw new RuntimeException('PORTABLE_RECOVERY_JOB_BINDING_INVALID');
    }

    private static function readJson(string $path,int $max): array
    {
        if (is_link($path) || !is_file($path) ||
            filesize($path)<1 || filesize($path)>$max)
            throw new RuntimeException('PORTABLE_JOURNAL_INVALID');
        try { $data=json_decode((string)file_get_contents($path),true,16,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('PORTABLE_JOURNAL_INVALID'); }
        if (!is_array($data) || array_is_list($data))
            throw new RuntimeException('PORTABLE_JOURNAL_INVALID');
        return $data;
    }

    private static function writeJson(string $path,array $value): void
    {
        if (is_link($path)) throw new RuntimeException('PORTABLE_JOURNAL_SYMLINK');
        $tmp=dirname($path).'/.journal-'.bin2hex(random_bytes(12));
        $h=@fopen($tmp,'xb');
        if (!$h) throw new RuntimeException('PORTABLE_JOURNAL_WRITE_FAILED');
        try {
            if (!chmod($tmp,0600)) throw new RuntimeException('PORTABLE_JOURNAL_CHMOD_FAILED');
            $json=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if (fwrite($h,$json)!==strlen($json)||!fflush($h)
                ||(function_exists('fsync')&&!fsync($h)))
                throw new RuntimeException('PORTABLE_JOURNAL_SYNC_FAILED');
        } finally { fclose($h); }
        if (!rename($tmp,$path)) {
            @unlink($tmp); throw new RuntimeException('PORTABLE_JOURNAL_RENAME_FAILED');
        }
    }
}
