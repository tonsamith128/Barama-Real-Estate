<?php

declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/DemoPublisherApiV1.php';
require dirname(__DIR__) . '/application/app/Services/DemoPublisherDownloadV1.php';

use Barama\Services\DemoPublisherDownloadV1;

function checkTicket(bool $okay, string $label): void {
    if (!$okay) { fwrite(STDERR, "FAIL=$label\n"); exit(1); }
    echo "PASS=$label\n";
}
function denyTicket(callable $run, string $label): void {
    try { $run(); } catch (RuntimeException) { checkTicket(true, $label); return; }
    checkTicket(false, $label);
}

$pair = sodium_crypto_sign_keypair();
$pub = sodium_crypto_sign_publickey($pair);
$secret = sodium_crypto_sign_secretkey($pair);
$fp = hash('sha256', $pub);
$keyId = substr($fp, 0, 24);
$dir = sys_get_temp_dir() . '/barama-demo-ticket-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
$fixture = $dir . '/signed-fixture.zip';
$zip = new ZipArchive();
if ($zip->open($fixture, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) exit(1);
$zip->addFromString('manifest.json', json_encode(['product'=>'barama-real-estate',
    'demo_slug'=>'live','version'=>'0.9.24','schema_version'=>1], JSON_THROW_ON_ERROR));
$zip->addFromString('content.json', json_encode(['schema'=>'barama-demo-content-v1',
    'description'=>bin2hex(random_bytes(800))], JSON_THROW_ON_ERROR));
$zip->close();
$contents = file_get_contents($fixture);
$sha = hash('sha256', $contents);
$signature = base64_encode(sodium_crypto_sign_detached(hex2bin($sha), $secret));
$id = 'DCP-20261024-03A1BCBA';
$ticket = str_repeat('A', 43);
$metadata = [
    'id'=>$id, 'sha256'=>$sha, 'signature'=>$signature,
    'key_id'=>$keyId, 'bytes'=>strlen($contents)
];
$claim = ['metadata'=>$metadata,'ticket'=>$ticket,
    'download_path'=>'/api/demo-import/v1/packages/'.$id.'/download?token='.$ticket];
$key = ['public_key'=>base64_encode($pub),'fingerprint'=>$fp,'key_id'=>$keyId];
$calls=[];
$mock = static function (string $url, $out, bool $cloud) use ($contents, &$calls): array {
    $calls[] = [$url,$cloud];
    if (!$cloud) {
        return ['status'=>303,
            'location'=>'https://'.str_repeat('a', 32).'.r2.cloudflarestorage.com/demo.zip?X-Amz-Signature=abc'];
    }
    fwrite($out, $contents);
    return ['status'=>200];
};
$client = new DemoPublisherDownloadV1($mock);
$downloaded = $client->receive($claim, $key, $fp, $dir);
checkTicket($downloaded['signature_verified'] === true &&
    hash_file('sha256', $downloaded['path']) === $sha, 'verified_pinned_signed_package');
checkTicket(count($calls) === 2 &&
    $calls[0][1] === false && $calls[1][1] === true, 'single_cloud_redirect_only');
checkTicket(($downloaded['bytes'] ?? -1) === strlen($contents), 'download_size_bounded');
denyTicket(fn() => $client->receive($claim, $key, str_repeat('b', 64), $dir),
    'key_not_independently_pinned_denied');
$badId = $claim;
$badId['metadata']['id'] = 'DCP-OTHER';
denyTicket(fn() => $client->receive($badId, $key, $fp, $dir),
    'authorization_package_mismatch_denied');
$redirect = new DemoPublisherDownloadV1(static fn(): array => [
    'status'=>303,'location'=>'http://127.0.0.1/private'
]);
denyTicket(fn() => $redirect->receive($claim,$key,$fp,$dir),
    'private_network_redirect_denied');
$spoof = new DemoPublisherDownloadV1(static fn(): array => [
    'status'=>303,'location'=>'https://example.com/private.zip'
]);
denyTicket(fn() => $spoof->receive($claim,$key,$fp,$dir),
    'arbitrary_external_redirect_denied');
$changed = new DemoPublisherDownloadV1(static function ($url,$out,$cloud) use ($contents): array {
    fwrite($out, $contents . 'tampered');
    return ['status'=>200];
});
denyTicket(fn() => $changed->receive($claim,$key,$fp,$dir),
    'changed_signed_zip_rejected');
$files = glob($dir . '/demo-receive-*') ?: [];
checkTicket(count($files) === 0, 'failed_downloads_clean_private_temps');
foreach (glob($dir . '/*') ?: [] as $file) unlink($file);
rmdir($dir);
echo "DEMO_SIGNED_DOWNLOAD_CONTRACT=PASS\n";
