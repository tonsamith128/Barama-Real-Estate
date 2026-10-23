<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/TrustedReleaseFeed.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateArchiveV1.php';

use Barama\Services\Updates\PortableUpdateArchiveV1 as Archive;

function assertion(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException("FAIL=".$name);
    echo "PASS=".$name."\n";
}
function rejects(callable $test, string $name): void {
    try { $test(); } catch (RuntimeException $e) {
        echo "PASS=".$name."\n"; return;
    }
    throw new RuntimeException("FAIL=".$name);
}
function recursivelyRemove(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
        recursivelyRemove($item->getPathname());
    }
    rmdir($path);
}
function zipFixture(string $file, array $entries): void {
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
        throw new RuntimeException('FIXTURE_ZIP_CREATION_FAILED');
    foreach ($entries as $name => $body) $zip->addFromString($name, $body);
    $zip->close();
}
function envelope(string $sha, int $bytes, string $pubKey, string $secretKey): array {
    $manifest=[
        'schema'=>'tonsamith-commercial-release-v2',
        'publisher'=>'tonsamith.com',
        'key_id'=>'portable-test-2026',
        'release_id'=>'barama-portable-test-2026',
        'product_slug'=>'barama-real-estate',
        'version'=>'0.9.24',
        'channel'=>'stable',
        'release_type'=>'update',
        'release_status'=>'published',
        'package_sha256'=>$sha,
        'package_bytes'=>$bytes,
        'min_php'=>'8.2.0',
        'minimum_app_version'=>'0.9.23',
        'migration_policy'=>'additive',
        'published_at'=>gmdate('Y-m-d\TH:i:s\Z',time()-30),
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600),
    ];
    $sorted=$manifest; ksort($sorted,SORT_STRING);
    $signed='TONSAMITH-RELEASE-MANIFEST-V2'."\n".json_encode(
        $sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    return [
        'manifest'=>$manifest,
        'signature'=>[
            'algorithm'=>'Ed25519',
            'key_id'=>'portable-test-2026',
            'value'=>base64_encode(sodium_crypto_sign_detached($signed,$secretKey))
        ]
    ];
}

$invalid=[
    '../VERSION','/etc/passwd','assets/../VERSION',
    'assets//hack.php','assets/.secret','assets/link/../../private',
    'application/storage/token.php','application/config/license.php',
    'application/database/migrations/001.php','application/app/.env',
    'application/app/secret.key','assets/.htaccess','assets/file\hidden',
];
foreach ($invalid as $i=>$path) {
    assertion(!Archive::allowedPath($path), 'prohibited_path_'.$i);
}
foreach (['VERSION','application/app/Services/Updates/Example.php','assets/js/app.js'] as $path) {
    assertion(Archive::allowedPath($path), 'allowed_'.$path);
}
if (!extension_loaded('sodium') || !class_exists(ZipArchive::class)) {
    throw new RuntimeException('TEST_REQUIRES_SODIUM_AND_ZIP');
}
$temp=sys_get_temp_dir().'/barama-portable-test-'.bin2hex(random_bytes(7));
$site=$temp.'/site';
mkdir($site.'/application/app/Services/Updates',0700,true);
mkdir($site.'/application/config',0700,true);
mkdir($site.'/assets/js',0700,true);
define('BASE_PATH',$site);
define('APP_PATH',$site.'/application');
$keys=sodium_crypto_sign_keypair();
file_put_contents(APP_PATH.'/config/update-publisher-v2-keys.json', json_encode([
    'format'=>'tonsamith-publisher-keys-v2',
    'keys'=>['portable-test-2026'=>[
        'public'=>base64_encode(sodium_crypto_sign_publickey($keys)),
        'status'=>'active'
    ]]
],JSON_THROW_ON_ERROR));
chmod(APP_PATH.'/config/update-publisher-v2-keys.json',0600);
file_put_contents($site.'/VERSION',"0.9.23\n");
file_put_contents($site.'/assets/js/app.js',"before\n");

try {
    $archive=$temp.'/release.zip';
    zipFixture($archive, ['VERSION'=>"0.9.24\n",'assets/js/app.js'=>"after\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    $plan=Archive::inspect($site,$archive,'0.9.23',$env,'stable');
    assertion($plan['target_version']==='0.9.24','signed_target');
    assertion(count($plan['files'])===2,'code_plus_version');
    assertion($plan['files'][1]['baseline']===hash('sha256',"before\n"),'baseline_bound');

    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',
        envelope(str_repeat('a',64),filesize($archive),'',sodium_crypto_sign_secretkey($keys)),'stable'),
        'tampered_digest_denied');

    zipFixture($archive,['VERSION'=>"0.9.24\n",'assets/../VERSION'=>"hacked\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',$env,'stable'),'traversal_denied');

    zipFixture($archive,['VERSION'=>"0.9.24\n",'application/config/license.php'=>"hacked\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',$env,'stable'),'config_overwrite_denied');

    zipFixture($archive,['VERSION'=>"0.9.25\n",'assets/js/app.js'=>"after\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',$env,'stable'),'target_mismatch_denied');

    zipFixture($archive,['VERSION'=>"0.9.24\n",'assets/js/app.js'=>"before\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',$env,'stable'),'version_only_update_denied');

    symlink($temp,$site.'/assets/js/link');
    zipFixture($archive,['VERSION'=>"0.9.24\n",'assets/js/link/x.php'=>"bad\n"]);
    $env=envelope(hash_file('sha256',$archive),filesize($archive),'',sodium_crypto_sign_secretkey($keys));
    rejects(fn()=>Archive::inspect($site,$archive,'0.9.23',$env,'stable'),'symlink_parent_denied');
    echo "PORTABLE_SIGNED_ARCHIVE_TESTS=PASS\n";
} finally {
    recursivelyRemove($temp);
}
