<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Owner-approved, first-time Publisher V2 PUBLIC-key bootstrap.
 *
 * No remote key fetching, no private signing material, no network or license
 * access. Initial trust requires an independently verified owner fingerprint.
 * CLI root, staging-only, no replacement of existing site configuration.
 */
final class PublisherTrustBootstrapV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const APPROVAL = 'APPROVE_INDEPENDENTLY_VERIFIED_PUBLISHER_PUBLIC_KEYS';
    private const FEED = ['enabled'=>true,'contract'=>'tonsamith-signed-discovery-v2','channel'=>'stable'];

    /** @return array<string,mixed> */
    public static function check(string $site): array
    {
        self::guard($site);
        $keys = APP_PATH . '/config/update-publisher-v2-keys.json';
        $feed = APP_PATH . '/config/update-feed-v2.json';
        $status = [
            'status'=>'PUBLISHER_TRUST_NOT_PROVISIONED',
            'missing'=>[],
            'key_catalog_valid'=>false,
            'feed_contract_valid'=>false,
            'active_public_key_count'=>0,
            'writes'=>false,
            'network_requests'=>false,
            'database_connections'=>false,
            'automatic_install'=>false,
        ];
        foreach (['publisher_keys'=>$keys,'publisher_feed'=>$feed] as $name=>$path) {
            if (!self::safeFile($path, 65536)) $status['missing'][]=$name;
        }
        if (!$status['missing']) {
            try {
                $pub = self::decodeKeys((string)file_get_contents($keys));
                $config = json_decode((string)file_get_contents($feed), true, 8, JSON_THROW_ON_ERROR);
                $status['key_catalog_valid'] = true;
                $status['active_public_key_count'] = $pub;
                $status['feed_contract_valid'] = $config === self::FEED;
                if ($status['feed_contract_valid']) $status['status']='PUBLISHER_TRUST_CONFIG_PRESENT';
            } catch (\Throwable) {
                $status['status']='PUBLISHER_TRUST_CONFIG_INVALID';
            }
        }
        return $status;
    }

    /** @return array<string,mixed> */
    public static function provision(
        string $site, string $catalogPath, string $expectedSha256,
        string $approval, bool $install
    ): array {
        self::guard($site);
        if (!preg_match('/^[a-f0-9]{64}$/D',$expectedSha256)) {
            throw new RuntimeException('PUBLISHER_TRUST_INDEPENDENT_FINGERPRINT_REQUIRED');
        }
        $fixture=self::fixture($site);
        $prefix=$fixture ? dirname($site).'/' : '/root/';
        if (!str_starts_with($catalogPath,$prefix)
            || $catalogPath === $prefix || is_link($catalogPath)
            || !is_file($catalogPath) || realpath($catalogPath)!==$catalogPath) {
            throw new RuntimeException('PUBLISHER_TRUST_CATALOG_PATH_UNTRUSTED');
        }
        self::noSymlinkAncestors($catalogPath);
        $st=@stat($catalogPath);
        if (!is_array($st) || $st['uid']!==0 || ($st['mode'] & 0022)!==0
            || $st['size'] < 10 || $st['size'] > 65536
            || !is_readable($catalogPath)) {
            throw new RuntimeException('PUBLISHER_TRUST_CATALOG_UNSAFE');
        }
        $raw=(string)file_get_contents($catalogPath);
        if (!hash_equals($expectedSha256,hash('sha256',$raw))) {
            throw new RuntimeException('PUBLISHER_TRUST_INDEPENDENT_FINGERPRINT_MISMATCH');
        }
        $active=self::decodeKeys($raw);
        $result=[
            'status'=>'PUBLISHER_TRUST_CATALOG_VALIDATED_READ_ONLY',
            'active_public_key_count'=>$active,
            'catalog_sha256'=>hash('sha256',$raw),
            'written'=>false,
            'network_requests'=>false,
            'database_connections'=>false,
            'automatic_install'=>false,
        ];
        if (!$install) return $result;
        if ($approval !== self::APPROVAL) {
            throw new RuntimeException('PUBLISHER_TRUST_EXPLICIT_OWNER_APPROVAL_REQUIRED');
        }
        $directory=APP_PATH . '/config';
        if (!is_dir($directory) || is_link($directory)
            || realpath($directory)!==$directory || is_link(APP_PATH)) {
            throw new RuntimeException('PUBLISHER_TRUST_CONFIG_DIRECTORY_UNTRUSTED');
        }
        $keys=$directory.'/update-publisher-v2-keys.json';
        $feed=$directory.'/update-feed-v2.json';
        // No overwrites, even if the existing file has matching contents.
        foreach ([$keys,$feed] as $target) {
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException('PUBLISHER_TRUST_EXISTING_CONFIG_REQUIRES_REVIEW');
            }
        }
        $created=[];
        try {
            // Install public catalog FIRST, then the enabled feed contract.
            // An interrupted run cannot enable publisher discovery without keys.
            self::createNoClobber($keys,$raw);
            $created[]=$keys;
            self::createNoClobber($feed,json_encode(self::FEED,
                JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
            $created[]=$feed;
            $checked=self::check($site);
            if ($checked['status']!=='PUBLISHER_TRUST_CONFIG_PRESENT'
                || $checked['active_public_key_count']!==$active) {
                throw new RuntimeException('PUBLISHER_TRUST_POST_INSTALL_FAILED');
            }
            $result['status']='PUBLISHER_TRUST_STAGING_CONFIG_INSTALLED';
            $result['written']=true;
            return $result;
        } catch (\Throwable $exception) {
            // Only files created by this invocation may be removed.
            foreach (array_reverse($created) as $target) @unlink($target);
            throw $exception;
        }
    }

    private static function createNoClobber(string $destination,string $bytes): void
    {
        $dir=dirname($destination);
        $temporary=$dir.'/.publisher-trust-'.bin2hex(random_bytes(16));
        $handle=@fopen($temporary,'xb');
        if ($handle===false) throw new RuntimeException('PUBLISHER_TRUST_TEMP_OPEN_FAILED');
        try {
            if (!chmod($temporary,0644)
                || fwrite($handle,$bytes)!==strlen($bytes)
                || !fflush($handle)) {
                throw new RuntimeException('PUBLISHER_TRUST_TEMP_WRITE_FAILED');
            }
            if (function_exists('fsync') && !fsync($handle)) {
                throw new RuntimeException('PUBLISHER_TRUST_TEMP_SYNC_FAILED');
            }
        } finally {
            fclose($handle);
        }
        try {
            // Atomic, no-clobber hard link; never rename over customer config.
            if (file_exists($destination) || is_link($destination)
                || !@link($temporary,$destination)) {
                throw new RuntimeException('PUBLISHER_TRUST_CONCURRENT_CONFIG_CONFLICT');
            }
        } finally {
            @unlink($temporary);
        }
    }

    private static function decodeKeys(string $raw): int
    {
        if (!extension_loaded('sodium')) throw new RuntimeException('PUBLISHER_TRUST_SODIUM_MISSING');
        try {$json=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}
        catch (\JsonException) {throw new RuntimeException('PUBLISHER_TRUST_CATALOG_INVALID');}
        if (!is_array($json) || array_keys($json)!==['format','keys']
            || $json['format']!=='tonsamith-publisher-keys-v2'
            || !is_array($json['keys']) || count($json['keys'])<1
            || count($json['keys'])>32 || array_is_list($json['keys'])) {
            throw new RuntimeException('PUBLISHER_TRUST_CATALOG_INVALID');
        }
        $active=0;
        foreach ($json['keys'] as $id=>$row) {
            if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9-]{2,90}$/D',$id)
                || !is_array($row) || array_keys($row)!==['public','status']
                || !is_string($row['public'])
                || !in_array($row['status'],['active','retired','revoked'],true)) {
                throw new RuntimeException('PUBLISHER_TRUST_CATALOG_INVALID');
            }
            $decoded=base64_decode($row['public'],true);
            if (!is_string($decoded) || strlen($decoded)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || base64_encode($decoded)!==$row['public']) {
                throw new RuntimeException('PUBLISHER_TRUST_CATALOG_INVALID');
            }
            if ($row['status']==='active')$active++;
        }
        if ($active<1) throw new RuntimeException('PUBLISHER_TRUST_NO_ACTIVE_KEY');
        return $active;
    }

    private static function safeFile(string $path,int $max):bool
    {
        if (is_link($path) || !is_file($path) || !is_readable($path))return false;
        $stat=@stat($path);
        return is_array($stat) && $stat['size']>0 && $stat['size']<=$max
            && (($stat['mode'] & 0022)===0);
    }

    private static function noSymlinkAncestors(string $path):void
    {
        $parents=explode('/',trim(dirname($path),'/'));
        $cursor='';
        foreach ($parents as $part) {
            $cursor.='/'.$part;
            if (is_link($cursor)) {
                throw new RuntimeException('PUBLISHER_TRUST_CATALOG_PATH_UNTRUSTED');
            }
        }
    }

    private static function fixture(string $site):bool
    {
        return getenv('GITHUB_ACTIONS')==='true'
            && getenv('GITHUB_REPOSITORY')==='tonsamith128/Barama-Real-Estate'
            && preg_match('~^/tmp/barama-trust-bootstrap-[a-f0-9]{16}/site$~D',$site)===1;
    }

    private static function guard(string $site):void
    {
        if (PHP_SAPI!=='cli' || !function_exists('posix_geteuid')
            || posix_geteuid()!==0) {
            throw new RuntimeException('PUBLISHER_TRUST_ROOT_CLI_REQUIRED');
        }
        if ($site!==self::STAGING && !self::fixture($site)) {
            throw new RuntimeException('PUBLISHER_TRUST_STAGING_ONLY');
        }
        if (is_link($site) || !is_dir($site) || realpath($site)!==$site
            || !defined('BASE_PATH') || BASE_PATH!==$site
            || !defined('APP_PATH') || APP_PATH!==$site.'/application'
            || is_link(APP_PATH)) {
            throw new RuntimeException('PUBLISHER_TRUST_ROOT_UNTRUSTED');
        }
    }
}
