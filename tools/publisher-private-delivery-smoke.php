<?php
declare(strict_types=1);

namespace Barama\Core {
    final class Installation {
        public static array $license=[];
        public static array $runtime=[];
        public static function installationIdPath():string {return APP_PATH.'/id.php';}
        public static function licensePath():string {return APP_PATH.'/license.php';}
        public static function runtimeConfig():array {return self::$runtime;}
        public static function licenseState():array {return self::$license;}
    }
}
namespace Barama\Services {
    final class LicenseManagerClient {
        public static function canonicalDomain(string $s):string {
            $host=parse_url($s,PHP_URL_HOST);
            return strtolower(is_string($host)?$host:$s);
        }
    }
}
namespace Barama\Services\Updates {
    final class TrustedReleaseFeed {
        public const URL='https://tonsamith.com/license/v2/update/manifest';
        public static string $status='available';
        public static string $signed=''; 
        public static function readiness():array {return ['can_check'=>true];}
        public static function check(string $version,array $license,array $runtime):array {
            return ['status'=>self::$status,'version'=>'0.9.23','archive_sha256'=>self::$signed];
        }
        public static function verifyEnvelope(array $env,string $version,string $channel):string {
            if (($env['manifest']['package_sha256']??'')!==self::$signed
                || ($env['manifest']['version']??null)!=='0.9.23'
                || $version!=='0.9.22-dev' || $channel!=='stable')
                throw new \RuntimeException('UPDATE_SIGNATURE_INVALID');
            return '0.9.23';
        }
    }
    final class PrivateArtifactHttpClientV2 {
        public static int $calls=0;
        public static function download(
            array $auth,array $env,string $version,string $channel,string $vault,?callable $transport=null
        ):array {
            self::$calls++;
            if ($auth['product_slug']!=='barama-real-estate'
                || $channel!=='stable' || $version!=='0.9.22-dev'
                || $auth['activation_token']!=='fixture-secret-only'
                || !str_contains($vault,'/root-private')) {
                throw new \RuntimeException('TEST_AUTH_BINDING_INVALID');
            }
            return [
                'sha256'=>$env['manifest']['package_sha256'],
                'archive'=>$vault.'/fixture-release.zip',
                'receipt'=>$vault.'/fixture-release.receipt.json',
                'bytes'=>10,
            ];
        }
    }
}
namespace {
    use Barama\Core\Installation;
    use Barama\Services\Updates\TrustedReleaseFeed;
    use Barama\Services\Updates\PrivateArtifactHttpClientV2;
    use Barama\Services\Updates\PublisherPrivateDeliveryV1;

    if (getenv('GITHUB_ACTIONS')!=='true'
        || getenv('GITHUB_REPOSITORY')!=='tonsamith128/Barama-Real-Estate'
        || !function_exists('posix_geteuid') || posix_geteuid()!==0) {
        throw new \RuntimeException('TEST_REQUIRES_ROOT_CI');
    }
    $parent='/tmp/barama-publisher-delivery-'.bin2hex(random_bytes(8));
    $root=$parent.'/site';
    $remove=static function(string $p)use(&$remove):void {
        if(is_link($p)||is_file($p)){@unlink($p);return;}
        if(!is_dir($p))return;
        foreach(scandir($p)?:[] as $n)if($n!=='.'&&$n!=='..')$remove($p.'/'.$n);
        @rmdir($p);
    };
    $n=0;
    $test=static function(bool $pass,string $msg)use(&$n):void {
        if(!$pass)throw new \RuntimeException('TEST_FAIL:'.$msg);
        ++$n;
    };
    $reject=static function(callable $fn,string $expected)use($test):void {
        try{$fn();}catch(\RuntimeException $e){
            $test($e->getMessage()===$expected,$expected);return;
        }
        throw new \RuntimeException('TEST_FAIL_OPEN:'.$expected);
    };
    try{
        mkdir($parent,0700);
        mkdir($root,0700);
        mkdir($root.'/application',0700);
        mkdir($root.'/application/config',0700);
        file_put_contents($root.'/VERSION',"0.9.22-dev\n");
        file_put_contents($root.'/application/id.php','test-id');
        file_put_contents($root.'/application/license.php','test-license');
        file_put_contents($root.'/application/config/update-feed-v2.json',
            json_encode(['enabled'=>true,'contract'=>'tonsamith-signed-discovery-v2','channel'=>'stable']));
        file_put_contents($root.'/application/config/update-publisher-v2-keys.json','test-pinned');
        define('BASE_PATH',$root);
        define('APP_PATH',$root.'/application');
        require dirname(__DIR__).'/application/app/Services/Updates/PublisherPrivateDeliveryV1.php';
        Installation::$license=[
            'activation_token'=>'fixture-secret-only',
            'domain'=>'staging.example.test',
            'installation_id'=>str_repeat('a',32),
            'status'=>'active',
        ];
        Installation::$runtime=['APP_URL'=>'https://staging.example.test'];
        TrustedReleaseFeed::$signed=str_repeat('a',64);
        $snapshot=hash_file('sha256',$root.'/VERSION');
        $r=PublisherPrivateDeliveryV1::readiness($root);
        $test($r['required_files_present']===true,'Readiness files');
        $test($r['publisher_can_check']===true,'Readiness publisher');
        $test(!$r['authenticated_download_attempted']&&!$r['auto_install_enabled'],'No background transfer');
        $reject(static fn()=>PublisherPrivateDeliveryV1::readiness('/www/wwwroot/baramarealestate.tonsamith.com'),
            'PUBLISHER_DELIVERY_STAGING_ONLY');
        $reject(static fn()=>PublisherPrivateDeliveryV1::download($root,'NOT_CONFIRMED'),
            'PUBLISHER_DELIVERY_OWNER_CONFIRMATION_REQUIRED');
        TrustedReleaseFeed::$status='ineligible';
        $res=PublisherPrivateDeliveryV1::download($root,
            'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY');
        $test($res['status']==='PUBLISHER_ENTITLEMENT_DENIED','Denied entitlement');
        $test(PrivateArtifactHttpClientV2::$calls===0,'Denied: no transfer');
        TrustedReleaseFeed::$status='eligible';
        $res=PublisherPrivateDeliveryV1::download($root,
            'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY');
        $test($res['status']==='NO_NEW_PUBLISHER_RELEASE','No release');
        $test(PrivateArtifactHttpClientV2::$calls===0,'No release: no transfer');
        TrustedReleaseFeed::$status='available';
        $manifest=['manifest'=>['package_sha256'=>TrustedReleaseFeed::$signed,'version'=>'0.9.23']];
        $good=static fn(array $request):array=>[
            'schema'=>'tonsamith-signed-discovery-v2','success'=>true,
            'entitled'=>true,'update_available'=>true,'envelope'=>$manifest,
        ];
        $reject(static fn()=>PublisherPrivateDeliveryV1::download($root,
            'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY',
            static fn(array $r):array=>[
                'schema'=>'tonsamith-signed-discovery-v2',
                'success'=>false,'entitled'=>false,'update_available'=>false]),
            'PUBLISHER_DELIVERY_ENTITLEMENT_NOT_CONFIRMED');
        $reject(static fn()=>PublisherPrivateDeliveryV1::download($root,
            'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY',
            static fn(array $r):array=>[
                'schema'=>'tonsamith-signed-discovery-v2',
                'success'=>true,'entitled'=>true,'update_available'=>true,
                'envelope'=>['manifest'=>['package_sha256'=>str_repeat('b',64),'version'=>'0.9.23']]]),
            'UPDATE_SIGNATURE_INVALID');
        $test(PrivateArtifactHttpClientV2::$calls===0,'Changed manifest: no transfer');
        $done=PublisherPrivateDeliveryV1::download($root,
            'DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY',$good,
            static function(array $params,callable $sink):void{});
        $test($done['status']==='AUTHENTICATED_ROOT_PRIVATE_ARTIFACT_RECEIVED','Auth delivery');
        $test($done['sha256']===TrustedReleaseFeed::$signed,'Matching checksum');
        $test($done['website_release_staged']===false&&$done['auto_install_enabled']===false,'No install');
        $test($done['application_writes']===false&&$done['database_connections']===false,'No website DB');
        $test(PrivateArtifactHttpClientV2::$calls===1,'Single artifact call');
        $test(is_dir($parent.'/root-private'),'Private vault');
        $test((fileperms($parent.'/root-private') & 0077)===0,'Private vault mode');
        $test(hash_file('sha256',$root.'/VERSION')===$snapshot,'Version unchanged');
        $test(!is_dir($root.'/application/storage'),'No staged release created');
        echo "AUTHENTICATED_PRIVATE_DELIVERY_CONTRACT=PASS\n";
        echo "UNENTITLED_AND_CHANGED_RELEASES=REJECTED\n";
        echo "PRODUCTION_DELIVERY=BLOCKED\n";
        echo "CUSTOMER_DATA_WRITES=NO\n";
        echo "AUTO_INSTALL=DISABLED\n";
        echo "TEST_ASSERTIONS=$n\n";
    }finally{$remove($parent);}
}
