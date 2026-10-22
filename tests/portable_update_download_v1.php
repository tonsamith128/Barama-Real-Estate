<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__) . '/application/app/Services/Updates/TrustedReleaseFeed.php';
require_once dirname(__DIR__) . '/application/app/Services/Updates/PortableUpdateDownloadV1.php';

use Barama\Services\Updates\PortableUpdateDownloadV1 as Download;

function must(bool $condition, string $test): void {
    if (!$condition) throw new RuntimeException('FAIL=' . $test);
    echo 'PASS=' . $test . "\n";
}
function denied(callable $cb, string $test): void {
    try { $cb(); } catch (RuntimeException) {
        echo 'PASS=' . $test . "\n";
        return;
    }
    throw new RuntimeException('FAIL=' . $test);
}
function removeTree(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $child) {
        removeTree($child->getPathname());
    }
    rmdir($path);
}
function signEnvelope(string $digest, int $size, string $secret): array {
    $m = [
        'schema'=>'tonsamith-commercial-release-v2',
        'publisher'=>'tonsamith.com',
        'key_id'=>'portable-download-fixture',
        'release_id'=>'portable-customer-2026',
        'product_slug'=>'barama-real-estate',
        'version'=>'0.9.24',
        'channel'=>'stable',
        'release_type'=>'update',
        'release_status'=>'published',
        'package_sha256'=>$digest,
        'package_bytes'=>$size,
        'min_php'=>'8.2.0',
        'minimum_app_version'=>'0.9.23',
        'migration_policy'=>'additive',
        'published_at'=>gmdate('Y-m-d\TH:i:s\Z', time()-90),
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z', time()+3600),
    ];
    $sorted = $m;
    ksort($sorted, SORT_STRING);
    $message = "TONSAMITH-RELEASE-MANIFEST-V2\n"
        . json_encode($sorted, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    return [
        'manifest'=>$m,
        'signature'=>[
            'algorithm'=>'Ed25519',
            'key_id'=>'portable-download-fixture',
            'value'=>base64_encode(sodium_crypto_sign_detached($message, $secret)),
        ],
    ];
}
if (!extension_loaded('sodium') || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('TEST_REQUIRES_SODIUM_AND_ZIP');
}
define('BARAMA_PORTABLE_FIXTURE_ONLY', true);
$home = sys_get_temp_dir() . '/barama-portable-delivery-' . bin2hex(random_bytes(7));
$site = $home . '/site';
$vault = $home . '/outside-private';
mkdir($site . '/application/config', 0700, true);
mkdir($vault, 0700, true);
chmod($vault, 0700);
define('BASE_PATH', $site);
define('APP_PATH', $site.'/application');
file_put_contents($site . '/VERSION', "0.9.23\n");
$kp=sodium_crypto_sign_keypair();
file_put_contents(APP_PATH . '/config/update-publisher-v2-keys.json', json_encode([
    'format'=>'tonsamith-publisher-keys-v2',
    'keys'=>['portable-download-fixture'=>[
        'public'=>base64_encode(sodium_crypto_sign_publickey($kp)),
        'status'=>'active',
    ]],
], JSON_THROW_ON_ERROR));
chmod(APP_PATH . '/config/update-publisher-v2-keys.json', 0600);

try {
    $fixtureZip = $home . '/release-fixture.zip';
    $zip = new ZipArchive();
    if ($zip->open($fixtureZip, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true)
        throw new RuntimeException('TEST_ZIP_CREATE_FAILED');
    $zip->addFromString('VERSION', "0.9.24\n");
    $zip->addFromString('assets/js/app.js', "new build\n");
    $zip->close();
    $bytes = file_get_contents($fixtureZip);
    if (!is_string($bytes)) throw new RuntimeException('TEST_ZIP_UNREADABLE');
    $env = signEnvelope(hash('sha256', $bytes), strlen($bytes),
        sodium_crypto_sign_secretkey($kp));
    $feed = static fn(string $v): array => [
        'status'=>'available', 'entitlement'=>'Eligible',
        'feed'=>'Signed release verified', 'version'=>'0.9.24',
        'archive_sha256'=>$env['manifest']['package_sha256'],
        'signed_envelope'=>$env,
    ];
    $good = static function(array $payload, callable $write) use ($bytes): array {
        if (($payload['release_id'] ?? null) !== 'portable-customer-2026')
            throw new RuntimeException('TEST_RELEASE_BINDING_FAILED');
        $write(substr($bytes,0,73));
        $write(substr($bytes,73));
        return ['status'=>200, 'mime'=>'application/zip'];
    };
    $result=Download::fetch($site,$vault,'0.9.23',$feed,$good);
    must($result['state']==='verified_download_not_installed'
        && $result['bytes']===strlen($bytes), 'exact_artifact_staged');
    must(hash_file('sha256',$result['archive'])===hash('sha256',$bytes),
        'download_sha256_verified');
    must(is_file($result['envelope']) && is_file($result['receipt'])
        && file_get_contents($site.'/VERSION')==="0.9.23\n",
        'only_private_files_created');
    must(str_starts_with($result['archive'],$vault.'/')
        && !str_starts_with($result['archive'],$site.'/'),
        'outside_web_root');
    denied(fn()=>Download::fetch($site,$site,'0.9.23',$feed,$good),
        'public_vault_denied');
    $blocked=static fn(string $v):array => [
        'status'=>'ineligible','entitlement'=>'Not entitled','feed'=>'Not available',
    ];
    denied(fn()=>Download::fetch($site,$vault,'0.9.23',$blocked,$good),
        'unentitled_never_downloaded');
    $truncated=static function(array $payload,callable $write)use($bytes):array {
        $write(substr($bytes,0,34)); return ['status'=>200,'mime'=>'application/zip'];
    };
    denied(fn()=>Download::fetch($site,$vault,'0.9.23',$feed,$truncated),
        'truncated_response_denied');
    $overflow=static function(array $payload,callable $write)use($bytes):array {
        $write($bytes . 'extra'); return ['status'=>200,'mime'=>'application/zip'];
    };
    denied(fn()=>Download::fetch($site,$vault,'0.9.23',$feed,$overflow),
        'size_overflow_denied');
    $wrongType=static function(array $payload,callable $write)use($bytes):array {
        $write($bytes); return ['status'=>200,'mime'=>'text/html'];
    };
    denied(fn()=>Download::fetch($site,$vault,'0.9.23',$feed,$wrongType),
        'wrong_content_type_denied');
    $wrongHash=static function(array $payload,callable $write)use($bytes):array {
        $write(str_repeat('X', strlen($bytes))); return ['status'=>200,'mime'=>'application/zip'];
    };
    denied(fn()=>Download::fetch($site,$vault,'0.9.23',$feed,$wrongHash),
        'tampered_archive_denied');
    must(file_get_contents($site.'/VERSION')==="0.9.23\n",
        'all_negative_tests_preserved_site');
    echo "PORTABLE_AUTHENTICATED_DOWNLOAD_FIXTURES=PASS\n";
} finally { removeTree($home); }
