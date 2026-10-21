<?php
declare(strict_types=1);
namespace Barama\Services\Updates;
use RuntimeException;
/**
 * Admin-intent queue, not update authorization. The separately provisioned
 * trusted worker must reverify the published release and entitlement.
 */
final class OneClickUpdateQueueV1
{
    private const SITE='/www/wwwroot/baramastaging.tonsamith.com';
    private const PATH='/application/storage/app/updates/one-click-queue';

    public static function enqueue(string $site,string $installed,array $feed): array
    {
        self::guard($site);
        if (!self::workerReady()) throw new RuntimeException('ONECLICK_TRUSTED_WORKER_NOT_READY');
        if (($feed['status']??null)!=='available'
            ||($feed['entitlement']??null)!=='Eligible'
            ||($feed['feed']??null)!=='Signed release verified'
            ||!is_string($feed['version']??null)
            ||version_compare($feed['version'],$installed,'<=')
            ||!is_string($feed['archive_sha256']??null)
            ||preg_match('/^[a-f0-9]{64}$/D',$feed['archive_sha256'])!==1) {
            throw new RuntimeException('ONECLICK_PUBLISHER_APPROVAL_MISSING');
        }
        // Signed ZIP is fetched by the independently verified privileged worker.
        // A web-user-staged ZIP cannot authorize an update.
        $dir=self::directory($site,true);
        $lock=$dir.'/.submit.lock';
        if(is_link($lock))throw new RuntimeException('ONECLICK_LOCK_UNSAFE');
        $h=@fopen($lock,'c+b');
        if(!$h)throw new RuntimeException('ONECLICK_LOCK_UNAVAILABLE');
        try {
            if(!flock($h,LOCK_EX|LOCK_NB))throw new RuntimeException('ONECLICK_BUSY');
            foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $item){
                if(preg_match('/^job-[a-f0-9]{32}\.json$/D',$item->getFilename())){
                    throw new RuntimeException('ONECLICK_PENDING_JOB_REQUIRES_REVIEW');
                }
            }
            // A writable queue file is never proof of a real admin click.
            // Persist a separate DB audit record bound to the authenticated
            // administrator, signed release digest, versions, and job nonce.
            $auth=new \Barama\Services\AuthService();
            $actor=$auth->user();
            $actorId=is_array($actor) ? (int)($actor['id']??0) : 0;
            if ($actorId<1 || (!$auth->hasRole('administrator')
                && !$auth->hasRole('super-administrator'))) {
                throw new RuntimeException('ONECLICK_ADMINISTRATOR_REQUIRED');
            }
            $id='job-'.bin2hex(random_bytes(16));
            $json=json_encode([
              'schema'=>'barama-oneclick-untrusted-intent-v1',
              'id'=>$id,'site'=>'STAGING_ONLY',
              'created_at'=>time(),'expires_at'=>time()+900,
              'installed_version'=>$installed,'target_version'=>$feed['version'],
              'package_sha256'=>$feed['archive_sha256'],
              'authorized_to_install'=>false
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
            $audit=json_encode([
                'schema'=>'barama-oneclick-admin-intent-v1',
                'job_id'=>$id,'package_sha256'=>$feed['archive_sha256'],
                'installed_version'=>$installed,'target_version'=>$feed['version'],
                'created_at'=>time(),'expires_at'=>time()+900,
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $pdo=\Barama\Core\Database::connection();
            $statement=$pdo->prepare(
                'INSERT INTO security_audit_events
                 (actor_user_id,subject_user_id,event_type,metadata_json)
                 VALUES (:actor,:subject,:event,:metadata)'
            );
            $statement->execute([
                'actor'=>$actorId,'subject'=>$actorId,
                'event'=>'system.oneclick_update_requested','metadata'=>$audit,
            ]);
            if($statement->rowCount()!==1)
                throw new RuntimeException('ONECLICK_ADMIN_AUDIT_NOT_SAVED');
            $path=$dir.'/'.$id.'.json';
            $file=@fopen($path,'xb');
            if(!$file)throw new RuntimeException('ONECLICK_QUEUE_WRITE_FAILED');
            try {
                if(!chmod($path,0600)||fwrite($file,$json)!==strlen($json)
                   ||!fflush($file)||(function_exists('fsync')&&!fsync($file))){
                    @unlink($path);throw new RuntimeException('ONECLICK_QUEUE_WRITE_FAILED');
                }
            } finally {fclose($file);}
            return ['id'=>$id,'status'=>'pending_independent_worker'];
        } finally {flock($h,LOCK_UN);fclose($h);}
    }

    public static function pending(string $site): bool
    {
        self::guard($site);
        $dir=self::directory($site,false);
        if($dir==='')return false;
        foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $item) {
            if(preg_match('/^job-[a-f0-9]{32}\.json$/D',$item->getFilename()))return true;
        }
        return false;
    }

    /** Read root-owned signals from the existing protected publisher trust directory. */
    private static function protectedSignal(string $name, int $max): ?array
    {
        if (!defined('BASE_PATH') || BASE_PATH!==self::SITE
            || !PublisherTrustPaths::protectedDirectoryReady()) return null;
        if (!in_array($name,['oneclick-worker-readiness.json','oneclick-worker-result.json'],true)) return null;
        $path=PublisherTrustPaths::directory().'/'.$name;
        if (is_link($path)||!is_file($path)||!is_readable($path)
            ||filesize($path)<1||filesize($path)>$max) return null;
        $st=@lstat($path);
        if (!is_array($st)||$st['uid']!==0||($st['mode'] & 0022)!==0) return null;
        try { $v=json_decode((string)file_get_contents($path),true,12,JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return null; }
        return is_array($v) && !array_is_list($v) ? $v : null;
    }

    public static function workerReady(): bool
    {
        $r=self::protectedSignal('oneclick-worker-readiness.json',512);
        return is_array($r)
            &&($r['schema']??null)==='barama-oneclick-root-worker-v1'
            &&($r['site']??null)==='STAGING_ONLY'
            &&($r['status']??null)==='accepting_jobs'
            &&($r['delivery']??null)==='publisher_authenticated_v2'
            &&is_int($r['expires_at']??null)
            &&$r['expires_at']>time()
            &&$r['expires_at']<=time()+120;
    }

    /** Reports only root-owned, allowlisted progress without exposing credentials. */
    public static function status(string $site, ?string $jobId = null): array
    {
        self::guard($site);
        // A previous worker result must never be displayed as this session's update.
        if (!is_string($jobId) || preg_match('/^job-[a-f0-9]{32}$/D', $jobId) !== 1) {
            return ['state'=>'not_started','updated_at'=>null];
        }
        $r=self::protectedSignal('oneclick-worker-result.json',1024);
        $state=$r['state']??null;
        if (!is_string($state)||!in_array($state,[
            'checking','backing_up','installing','verifying',
            'completed','blocked','failed','recovery_review_required'],true)
            ||($r['schema']??null)!=='barama-oneclick-root-result-v1'
            ||!is_string($r['job_id']??null)
            ||preg_match('/^job-[a-f0-9]{32}$/D',$r['job_id'])!==1
            ||!hash_equals($jobId, $r['job_id'])
            ||!is_int($r['updated_at']??null)
            ||$r['updated_at']>time()+60
            ||$r['updated_at']<time()-604800) {
            return ['state'=>'not_started','updated_at'=>null];
        }
        return ['state'=>$state,'updated_at'=>$r['updated_at']];
    }

    private static function guard(string $site):void
    {
        if($site!==self::SITE||is_link($site)||!is_dir($site)
          ||realpath($site)!==$site||!defined('BASE_PATH')||BASE_PATH!==$site
          ||PHP_SAPI==='cli')throw new RuntimeException('ONECLICK_STAGING_WEB_ONLY');
    }
    private static function directory(string $site,bool $create):string
    {
        $parent=$site.'/application/storage/app/updates';
        $cursor=$site;
        foreach (['application','storage','app','updates'] as $part) {
            $cursor.='/'.$part;
            if(is_link($cursor)||!is_dir($cursor))
                throw new RuntimeException('ONECLICK_PARENT_COMPONENT_UNSAFE');
        }
        if($parent!==$cursor)throw new RuntimeException('ONECLICK_PARENT_CHANGED');
        $dir=$site.self::PATH;
        if(!is_dir($dir)){
            if(!$create)return '';
            if(is_link($dir)||file_exists($dir)||!@mkdir($dir,0700))throw new RuntimeException('ONECLICK_DIRECTORY_FAILED');
        }
        if(is_link($dir)||!is_dir($dir)||(fileperms($dir)&0077)!==0)
            throw new RuntimeException('ONECLICK_DIRECTORY_UNSAFE');
        return $dir;
    }
}
