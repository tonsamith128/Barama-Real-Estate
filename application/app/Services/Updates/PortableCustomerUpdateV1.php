<?php
declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Closure;

/**
 * Customer PHP shared-hosting file-only update coordinator.
 *
 * All mutations are owner/admin POST+CSRF controller actions. No shell/cron
 * or root worker. The vault MUST be an unsymlinked 0700 directory outside
 * both installation root and the configured document root. No SQL migrations.
 *
 * Step boundaries: begin (authorized private download), install (fresh
 * entitlement, signed archive, verified code backup), finish (independent
 * new HTTP request after booting the new code), recover (explicit rollback).
 */
final class PortableCustomerUpdateV1
{
    public static function ready(): bool
    {
        if (!defined('BASE_PATH') || PublisherTrustPaths::isProtectedStaging()
            || !extension_loaded('sodium') || !extension_loaded('curl')
            || !class_exists(\ZipArchive::class)) return false;
        try {
            $v=self::vault(false);
            return $v!=='' && is_file(BASE_PATH.'/VERSION')
                && is_writable(BASE_PATH);
        } catch (\Throwable) { return false; }
    }

    /**
     * Paths are derived from the already-installed application root only.
     * Never from requests, publisher manifests, host headers or session.
     * The directory is created only during an admin POST.
     */
    public static function vault(bool $create): string
    {
        if (!defined('BASE_PATH') || PublisherTrustPaths::isProtectedStaging())
            throw new RuntimeException('PORTABLE_VAULT_DISABLED');
        $root=(string) BASE_PATH;
        if ($root==='/' || is_link($root) || !is_dir($root)
            || realpath($root)!==$root) {
            throw new RuntimeException('PORTABLE_VAULT_SITE_UNSAFE');
        }
        $parent=dirname($root);
        if ($parent==='/' || is_link($parent) || !is_dir($parent)
            || realpath($parent)!==$parent) {
            throw new RuntimeException('PORTABLE_VAULT_PARENT_UNSAFE');
        }
        $path=$parent.'/.barama-private-updates-'.substr(hash('sha256',$root),0,18);
        // The configured web DOCUMENT_ROOT can differ from BASE_PATH in some
        // customer setups; do not rely on an .htaccess deny on Nginx.
        $document=isset($_SERVER['DOCUMENT_ROOT'])
            ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
        if ($document!==false && ($path===$document
            || str_starts_with($path.'/',$document.'/'))) {
            throw new RuntimeException('PORTABLE_VAULT_DOCUMENT_ROOT_CONFLICT');
        }
        if (is_link($path)) throw new RuntimeException('PORTABLE_VAULT_SYMLINK');
        if (!is_dir($path)) {
            if (!$create) {
                if (!is_writable($parent)) throw new RuntimeException('PORTABLE_VAULT_PARENT_READ_ONLY');
                return $path;
            }
            if (!is_writable($parent) || !@mkdir($path,0700)) {
                throw new RuntimeException('PORTABLE_VAULT_CREATE_FAILED');
            }
        }
        if (realpath($path)!==$path || (fileperms($path)&0077)!==0
            || !is_writable($path)
            || str_starts_with($path.'/',$root.'/')
            || str_starts_with($root.'/',$path.'/')) {
            throw new RuntimeException('PORTABLE_VAULT_PRIVATE_CHECK_FAILED');
        }
        return $path;
    }

    /** One POST = one verified download, NO website file replacements. */
    public static function begin(?Closure $fixtureFeed=null, ?Closure $fixtureTransfer=null): array
    {
        self::fixtureGate($fixtureFeed,$fixtureTransfer);
        self::gate();
        $vault=self::vault(true);
        $lock=self::lock($vault);
        try {
            self::assertNoCurrent($vault);
            if (file_exists($vault.'/active.json') || is_link($vault.'/active.json')) {
                throw new RuntimeException('PORTABLE_PREVIOUS_TRANSACTION_REQUIRES_REVIEW');
            }
            $download=PortableUpdateDownloadV1::fetch(
                (string)BASE_PATH,$vault,(string)APP_VERSION,$fixtureFeed,$fixtureTransfer
            );
            self::save($vault.'/current.json',[
                'schema'=>'barama-portable-customer-current-v1',
                'state'=>'downloaded',
                'download_job_id'=>$download['job_id'],
                'install_job_id'=>null,
                'installed_version'=>(string)APP_VERSION,
                'target_version'=>$download['version'],
                'archive_sha256'=>$download['sha256'],
                'updated_at'=>time(),
            ]);
            return ['state'=>'downloaded','version'=>$download['version']];
        } finally { self::unlock($lock); }
    }

    /** A second independent POST: revalidates entitlement and applies files. */
    public static function install(?Closure $fixtureFeed=null): array
    {
        self::fixtureGate($fixtureFeed);
        self::gate();
        $vault=self::vault(true);
        $lock=self::lock($vault);
        try {
            $current=self::current($vault);
            if (($current['state']??'')!=='downloaded'
                || ($current['installed_version']??'')!==(string)APP_VERSION) {
                throw new RuntimeException('PORTABLE_INSTALL_STAGE_INVALID');
            }
            if (is_link($vault.'/active.json') || file_exists($vault.'/active.json')) {
                throw new RuntimeException('PORTABLE_UNFINISHED_TRANSACTION');
            }
            $d=self::downloadPaths($vault,$current);
            $envelope=self::json($d['envelope'],16384);
            $receipt=self::json($d['receipt'],4096);
            if (($receipt['schema']??null)!=='barama-portable-artifact-v1'
                || ($receipt['job_id']??null)!==$current['download_job_id']
                || ($receipt['version']??null)!==$current['target_version']
                || ($receipt['archive_sha256']??null)!==$current['archive_sha256']
                || !is_file($d['archive']) || is_link($d['archive'])
                || !hash_equals($current['archive_sha256'],(string)hash_file('sha256',$d['archive']))) {
                throw new RuntimeException('PORTABLE_INSTALL_DOWNLOAD_RECEIPT_INVALID');
            }
            $result=PortableUpdateFileTransactionV1::apply(
                (string)BASE_PATH,$d['archive'],$vault,(string)APP_VERSION,
                $envelope,(string)($envelope['manifest']['channel']??''),$fixtureFeed
            );
            // If a process terminates before saving the new current state, the
            // transaction's independent active.json still blocks all restarts.
            $current['install_job_id']=$result['job_id'];
            $current['state']='health_pending';
            $current['updated_at']=time();
            self::save($vault.'/current.json',$current);
            return ['state'=>'health_pending','version'=>$current['target_version']];
        } finally { self::unlock($lock); }
    }

    /**
     * A third, independent authenticated POST (new PHP boot) proves at least
     * that the application bootstraps, then matches every installed file and
     * VERSION against the transaction journal. This is FILE-ONLY completion,
     * not a claim that database restore has been tested or all pages work.
     */
    public static function finish(): array
    {
        self::gate();
        $vault=self::vault(true);
        $lock=self::lock($vault);
        try {
            $current=self::current($vault);
            if (($current['state']??'')!=='health_pending'
                || !is_string($current['install_job_id']??null)
                || preg_match('/^job-[a-f0-9]{32}$/D',$current['install_job_id'])!==1
                || (string)APP_VERSION!==$current['target_version']) {
                throw new RuntimeException('PORTABLE_POSTINSTALL_BOOT_OR_VERSION_MISMATCH');
            }
            $active=self::json($vault.'/active.json',2048);
            if (($active['schema']??null)!=='barama-portable-active-v1'
                || ($active['job_id']??null)!==$current['install_job_id']) {
                throw new RuntimeException('PORTABLE_ACTIVE_JOB_MISMATCH');
            }
            $journal=self::json($vault.'/'.$current['install_job_id'].'/journal.json',65536);
            if (!in_array($journal['state']??null,['files_applied_health_pending','completed_file_only'],true)
                || ($journal['schema']??null)!=='barama-portable-file-transaction-v1'
                || ($journal['job_id']??null)!==$current['install_job_id']
                || ($journal['archive_sha256']??null)!==$current['archive_sha256']
                || ($journal['target_version']??null)!==$current['target_version']
                || ($journal['database_changes']??null)!==false
                || !is_array($journal['files']??null)
                || count($journal['files'])<2) {
                throw new RuntimeException('PORTABLE_POSTINSTALL_JOURNAL_UNTRUSTED');
            }
            foreach ($journal['files'] as $file) {
                $rel=$file['path']??null;
                if (!is_string($rel) || !PortableUpdateArchiveV1::allowedPath($rel)
                    || !is_string($file['sha256']??null)
                    || preg_match('/^[a-f0-9]{64}$/D',$file['sha256'])!==1) {
                    throw new RuntimeException('PORTABLE_POSTINSTALL_FILE_INVALID');
                }
                $dest=(string) BASE_PATH;
                foreach (explode('/',$rel) as $part) {
                    $dest.='/'.$part;
                    if (is_link($dest)) throw new RuntimeException('PORTABLE_POSTINSTALL_SYMLINK');
                }
                if (!is_file($dest) || !hash_equals($file['sha256'],(string)hash_file('sha256',$dest))) {
                    throw new RuntimeException('PORTABLE_POSTINSTALL_FILE_MISMATCH');
                }
            }
            // Persist "completed" before releasing the active job marker.
            // The next request can recover a missing cleanup without
            // misreporting the file integrity evidence.
            $journal['state']='completed_file_only';
            $journal['completed_at']=time();
            self::save($vault.'/'.$current['install_job_id'].'/journal.json',$journal);
            $current['state']='completed_file_only';
            $current['updated_at']=time();
            self::save($vault.'/last.json',$current);
            if (!@unlink($vault.'/active.json')) {
                throw new RuntimeException('PORTABLE_COMPLETED_CLEANUP_REVIEW');
            }
            if (!@unlink($vault.'/current.json')) {
                throw new RuntimeException('PORTABLE_COMPLETED_CLEANUP_REVIEW');
            }
            return ['state'=>'completed_file_only','version'=>$current['target_version']];
        } finally { self::unlock($lock); }
    }

    /**
     * Explicit admin recovery only. Job ID comes from protected flow receipt;
     * no request-supplied path/job ID and no direct filesystem traversal.
     */
    public static function recover(): array
    {
        self::gate();
        $vault=self::vault(true);
        $lock=self::lock($vault);
        try {
            $current=self::current($vault);
            $install=$current['install_job_id']??null;
            if (!is_string($install) || preg_match('/^job-[a-f0-9]{32}$/D',$install)!==1) {
                // A crash after apply but before flow persistence must not be
                // guessed or silently rolled back. Manual recovery review.
                throw new RuntimeException('PORTABLE_RECOVERY_JOB_NOT_BOUND');
            }
            if (($current['state']??'')==='completed_file_only') {
                throw new RuntimeException('PORTABLE_FINISHED_JOB_CANNOT_RECOVER');
            }
            $result=PortableUpdateFileTransactionV1::recover((string)BASE_PATH,$vault,$install);
            $current['state']='rolled_back';
            $current['updated_at']=time();
            self::save($vault.'/last.json',$current);
            if (!@unlink($vault.'/current.json')) {
                throw new RuntimeException('PORTABLE_RECOVERY_FLOW_CLEANUP_FAILED');
            }
            return $result;
        } finally { self::unlock($lock); }
    }

    /** No writes, no secret data; admin view can render after a page reload. */
    public static function status(): array
    {
        if (PublisherTrustPaths::isProtectedStaging())
            return ['state'=>'unsupported_staging','pending'=>false];
        try {
            $vault=self::vault(false);
            if (!is_dir($vault)) return ['state'=>'not_started','pending'=>false];
            $active=is_link($vault.'/active.json') || is_file($vault.'/active.json');
            $hasCurrent=!is_link($vault.'/current.json') && is_file($vault.'/current.json');
            if ($active && !$hasCurrent) {
                return ['state'=>'recovery_review_required','pending'=>true];
            }
            $path=$hasCurrent ? $vault.'/current.json' : $vault.'/last.json';
            if (is_link($path)) return ['state'=>'recovery_review_required','pending'=>true];
            if (!is_file($path)) return ['state'=>'not_started','pending'=>false];
            $rec=self::json($path,8192);
            $state=$rec['state']??'unavailable';
            if (($active && $state!=='health_pending')
                || (!$active && $state==='health_pending')) {
                return ['state'=>'recovery_review_required','pending'=>true];
            }
            $allowed=['downloaded','health_pending','completed_file_only','rolled_back'];
            if (!is_string($state) || !in_array($state,$allowed,true)) {
                return ['state'=>'recovery_review_required','pending'=>true];
            }
            return [
                'state'=>$state,
                'pending'=>in_array($state,['downloaded','health_pending'],true),
                'version'=>is_string($rec['target_version']??null)
                    ? $rec['target_version'] : '',
            ];
        } catch (\Throwable) {
            return ['state'=>'unavailable','pending'=>true];
        }
    }

    /** Test dependencies never permitted from web SAPI or configuration. */
    private static function fixtureGate(?Closure $first,?Closure $second=null): void
    {
        if (($first!==null || $second!==null)
            && (PHP_SAPI!=='cli' || !defined('BARAMA_PORTABLE_FIXTURE_ONLY')
                || BARAMA_PORTABLE_FIXTURE_ONLY!==true)) {
            throw new RuntimeException('PORTABLE_TEST_DEPENDENCY_FORBIDDEN');
        }
    }

    private static function gate(): void
    {
        if (!self::ready() || !defined('APP_VERSION')) {
            throw new RuntimeException('PORTABLE_CUSTOMER_HOST_UNSUPPORTED');
        }
    }

    private static function assertNoCurrent(string $vault): void
    {
        if (is_link($vault.'/current.json') || file_exists($vault.'/current.json')) {
            throw new RuntimeException('PORTABLE_EXISTING_JOB_REQUIRES_COMPLETION');
        }
    }

    private static function current(string $vault): array
    {
        $r=self::json($vault.'/current.json',8192);
        if (($r['schema']??null)!=='barama-portable-customer-current-v1'
            || !is_string($r['download_job_id']??null)
            || preg_match('/^job-[a-f0-9]{32}$/D',$r['download_job_id'])!==1
            || !is_string($r['archive_sha256']??null)
            || preg_match('/^[a-f0-9]{64}$/D',$r['archive_sha256'])!==1
            || !is_string($r['target_version']??null)) {
            throw new RuntimeException('PORTABLE_CURRENT_RECEIPT_UNTRUSTED');
        }
        return $r;
    }

    private static function downloadPaths(string $vault,array $current): array
    {
        $root=$vault.'/'.$current['download_job_id'];
        if (is_link($root) || !is_dir($root) || realpath($root)!==$root) {
            throw new RuntimeException('PORTABLE_DOWNLOAD_JOB_DIR_UNSAFE');
        }
        return [
            'archive'=>$root.'/release.zip',
            'envelope'=>$root.'/envelope.json',
            'receipt'=>$root.'/receipt.json',
        ];
    }

    private static function lock(string $vault)
    {
        if (is_link($vault) || !is_dir($vault) || (fileperms($vault)&0077)!==0) {
            throw new RuntimeException('PORTABLE_VAULT_INSECURE');
        }
        $path=$vault.'/.portable-flow.lock';
        if (is_link($path)) throw new RuntimeException('PORTABLE_LOCK_SYMLINK');
        $h=@fopen($path,'c+b');
        if ($h===false) throw new RuntimeException('PORTABLE_LOCK_CREATE_FAILED');
        if (!flock($h,LOCK_EX|LOCK_NB)) {
            fclose($h);throw new RuntimeException('PORTABLE_UPDATE_BUSY');
        }
        return $h;
    }

    private static function unlock($lock): void
    {
        flock($lock,LOCK_UN);fclose($lock);
    }

    private static function json(string $path,int $max): array
    {
        if (is_link($path) || !is_file($path) || filesize($path)<1
            || filesize($path)>$max || (fileperms($path)&0022)!==0) {
            throw new RuntimeException('PORTABLE_PRIVATE_RECEIPT_UNSAFE');
        }
        try {$data=json_decode((string)file_get_contents($path),true,20,JSON_THROW_ON_ERROR);}
        catch (\JsonException) { throw new RuntimeException('PORTABLE_PRIVATE_RECEIPT_INVALID'); }
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('PORTABLE_PRIVATE_RECEIPT_INVALID');
        }
        return $data;
    }

    private static function save(string $path,array $data): void
    {
        if (is_link($path)) throw new RuntimeException('PORTABLE_PRIVATE_SAVE_SYMLINK');
        $tmp=dirname($path).'/.write-'.bin2hex(random_bytes(12));
        $h=@fopen($tmp,'xb');
        if ($h===false) throw new RuntimeException('PORTABLE_PRIVATE_WRITE_FAILED');
        try {
            chmod($tmp,0600);
            $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if (fwrite($h,$json)!==strlen($json) || !fflush($h)
                || (function_exists('fsync') && !fsync($h))) {
                throw new RuntimeException('PORTABLE_PRIVATE_WRITE_FAILED');
            }
        } finally {fclose($h);}
        if (!rename($tmp,$path)) {
            @unlink($tmp);throw new RuntimeException('PORTABLE_PRIVATE_RENAME_FAILED');
        }
    }
}
