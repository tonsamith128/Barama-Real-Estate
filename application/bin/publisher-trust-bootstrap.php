<?php

declare(strict_types=1);

/**
 * Owner-only, offline public-key bootstrap. NEVER generates signing keys.
 *
 * php application/bin/publisher-trust-bootstrap.php --check
 * php ... --verify /root/.../publisher-v2-keys.json <trusted-sha256>
 * php ... --install /root/.../publisher-v2-keys.json <trusted-sha256> APPROVE_INDEPENDENTLY_VERIFIED_PUBLISHER_PUBLIC_KEYS
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
umask(0077);
$base=dirname(__DIR__,2);
define('BASE_PATH',$base);
define('APP_PATH',$base.'/application');
define('APP_VERSION',is_file($base.'/VERSION') && !is_link($base.'/VERSION')
    ? trim((string)file_get_contents($base.'/VERSION')) : '0.0.0');
define('BARAMA_BOOTSTRAP',true);
spl_autoload_register(static function(string $class):void {
    $prefix='Barama\\';
    if (!str_starts_with($class,$prefix))return;
    $path=APP_PATH.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($path))require $path;
});
try {
    $args=$argv;
    array_shift($args);
    $mode=array_shift($args) ?? '';
    $trust=\Barama\Services\Updates\PublisherTrustBootstrapV1::class;
    if ($mode==='--check' && $args===[]) {
        $r=$trust::check(BASE_PATH);
        echo 'TRUST_STATUS='.$r['status']."\n";
        echo 'MISSING_ITEMS='.($r['missing']?implode(',',$r['missing']):'NONE')."\n";
        echo 'ACTIVE_PUBLIC_KEYS='.$r['active_public_key_count']."\n";
        echo 'FEED_CONTRACT_VALID='.($r['feed_contract_valid']?'YES':'NO')."\n";
    } elseif ($mode==='--verify' && count($args)===2) {
        $r=$trust::provision(BASE_PATH,$args[0],$args[1],'',false);
        echo 'TRUST_STATUS='.$r['status']."\n";
        echo 'ACTIVE_PUBLIC_KEYS='.$r['active_public_key_count']."\n";
        echo 'CATALOG_SHA256='.$r['catalog_sha256']."\n";
    } elseif ($mode==='--install' && count($args)===3) {
        $r=$trust::provision(BASE_PATH,$args[0],$args[1],$args[2],true);
        echo 'TRUST_STATUS='.$r['status']."\n";
        echo 'ACTIVE_PUBLIC_KEYS='.$r['active_public_key_count']."\n";
        echo 'CATALOG_SHA256='.$r['catalog_sha256']."\n";
    } else {
        throw new RuntimeException('PUBLISHER_TRUST_USAGE_INVALID');
    }
    echo "PRODUCTION_MODIFIED=NO\n";
    echo "DATABASE_CONNECTIONS=NO\n";
    echo "NETWORK_REQUESTS=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
} catch (Throwable $e) {
    $safe=$e->getMessage();
    if (!preg_match('/^PUBLISHER_TRUST_[A-Z0-9_]{3,96}$/D',$safe)) {
        $safe='PUBLISHER_TRUST_OPERATION_REJECTED';
    }
    fwrite(STDERR,"STOP=$safe\n");
    exit(1);
}
