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
        $candidate=StagingPublisherCandidateV1::inspect($site,$installed,$feed);
        if (($candidate['publisher_release_matched_to_staged_zip']??false)!==true
            ||($candidate['signed_zip_verified']??false)!==true) {
            throw new RuntimeException('ONECLICK_STAGED_RELEASE_NOT_VERIFIED');
        }
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
            $id='job-'.bin2hex(random_bytes(16));
            $json=json_encode([
              'schema'=>'barama-oneclick-untrusted-intent-v1',
              'id'=>$id,'site'=>'STAGING_ONLY',
              'created_at'=>time(),'expires_at'=>time()+900,
              'installed_version'=>$installed,'target_version'=>$feed['version'],
              'package_sha256'=>$feed['archive_sha256'],
              'authorized_to_install'=>false
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
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

    /**
     * Root-owned service heartbeat (read-only). Never grants authorization;
     * the worker must independently authenticate every queued request.
     */
    public static function workerReady(): bool
    {
        $dir='/run/barama-oneclick-staging';
        $path=$dir.'/heartbeat.json';
        foreach ([$dir,$path] as $p) {
            if (is_link($p) || !file_exists($p)) return false;
            $st=@stat($p);
            if (!is_array($st) || $st['uid'] !== 0 || ($st['mode'] & 0022)!==0) return false;
        }
        if (!is_dir($dir) || !is_file($path) || filesize($path)>512) return false;
        $raw=@file_get_contents($path);
        if (!is_string($raw)) return false;
        try { $record=json_decode($raw,true,8,JSON_THROW_ON_ERROR); }
        catch (\Throwable) { return false; }
        return is_array($record)
            && ($record['schema']??null)==='barama-oneclick-root-worker-v1'
            && ($record['site']??null)==='STAGING_ONLY'
            && ($record['status']??null)==='accepting_jobs'
            && is_int($record['expires_at']??null)
            && $record['expires_at']>time()
            && $record['expires_at']<=time()+120;
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
