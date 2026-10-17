<?php
declare(strict_types=1);

namespace Barama\Core {
    final class Installation {
        public static function installationId(): string { return str_repeat('a', 32); }
    }
}
namespace Barama\Services {
    final class LicenseManagerClient {
        public static function canonicalDomain(string $domain): string {
            $host = parse_url($domain, PHP_URL_HOST);
            return strtolower(is_string($host) ? $host : trim($domain));
        }
    }
}
namespace {
    use Barama\Services\Updates\TrustedReleaseFeed;
    function config(string $name, mixed $default = null): mixed {
        return match ($name) {
            'license.server_url' => 'https://tonsamith.com',
            'license.product_slug' => 'barama-real-estate',
            default => $default,
        };
    }
    $dir = sys_get_temp_dir().'/barama-update-test-'.bin2hex(random_bytes(8));
    mkdir($dir, 0700);
    mkdir($dir.'/config', 0700);
    define('APP_PATH', $dir);
    file_put_contents($dir.'/config/update-feed-v2.json', json_encode([
        'enabled'=>true,'contract'=>'tonsamith-signed-discovery-v2','channel'=>'stable',
    ]));
    $key = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
    file_put_contents($dir.'/config/update-publisher-v2-keys.json', json_encode([
        'format'=>'tonsamith-publisher-keys-v2',
        'keys'=>['test-signing'=>['public'=>base64_encode($key),'status'=>'active']],
    ]));
    require_once dirname(__DIR__).'/application/app/Services/Updates/TrustedReleaseFeed.php';
    $license=[
        'installation_id'=>str_repeat('a', 32),
        'activation_token'=>str_repeat('q', 32),
        'domain'=>'barama.example.test',
        'status'=>'active',
        'product_slug'=>'barama-real-estate',
    ];
    $runtime=['APP_URL'=>'https://barama.example.test'];
    $assert=static function(bool $p,string $label):void {
        if (!$p) {fwrite(STDERR,'FAIL '.$label.PHP_EOL);exit(1);}
        echo 'PASS '.$label.PHP_EOL;
    };
    foreach ([
        'entitlement_not_configured' => ['Not configured','not_configured'],
        'entitlement_denied' => ['Not entitled','denied'],
    ] as $err=>$expected) {
        $reply=static function(array $payload)use($err):array {
            if (($payload['installation_id']??null)!==str_repeat('a',32)) {
                throw new \RuntimeException('Invalid fixture binding');
            }
            return [
                'http_status'=>403,
                'body'=>['success'=>false,'schema'=>'tonsamith-signed-discovery-v2','error'=>$err],
            ];
        };
        $result=TrustedReleaseFeed::check('0.9.22-dev',$license,$runtime,$reply);
        $assert($result['status']==='ineligible','Status remains blocked: '.$err);
        $assert($result['entitlement']===$expected[0],'Safe entitlement message: '.$err);
        $assert(($result['reason']??null)===$expected[1],'Safe allowlisted reason: '.$err);
        $assert($result['feed']==='Not available','No package available: '.$err);
    }
    $result=TrustedReleaseFeed::check('0.9.22-dev',$license,$runtime,
        static fn(array $payload):array => [
            'http_status'=>200,
            'body'=>[
                'success'=>true,'schema'=>'tonsamith-signed-discovery-v2',
                'entitled'=>true,'update_available'=>false,
            ],
        ]);
    $assert($result['status']==='eligible' && $result['entitlement']==='Eligible',
        'Authoritative entitlement success remains unchanged');
    unlink($dir.'/config/update-publisher-v2-keys.json');
    unlink($dir.'/config/update-feed-v2.json');
    rmdir($dir.'/config');rmdir($dir);
    echo 'ENTITLEMENT_RESULT_CODES_SMOKE=PASS'.PHP_EOL;
}
