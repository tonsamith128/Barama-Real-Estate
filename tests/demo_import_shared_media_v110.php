<?php
declare(strict_types=1);

/**
 * BARAMA_DEMO_SHARED_MEDIA_REGRESSION_V110
 * Runs without bootstrap, DB connection, publisher credentials or web writes.
 */
require dirname(__DIR__) . '/application/app/Services/DemoImportPackageV1.php';
$engine = new \Barama\Services\DemoImportPackageV1();
$method = new ReflectionMethod($engine, 'writeVerifiedDemoMediaOnce');
function checkMedia(bool $valid, string $label): void {
    if (!$valid) { fwrite(STDERR, "FAIL=$label\n"); exit(2); }
    echo "PASS=$label\n";
}
function expectedFailure(callable $cb, string $expected, string $label): void {
    try { $cb(); }
    catch (RuntimeException $e) {
        checkMedia($e->getMessage() === $expected, $label);
        return;
    }
    checkMedia(false, $label);
}
$dir = sys_get_temp_dir() . '/barama-media-v110-' . bin2hex(random_bytes(8));
if (!mkdir($dir,0700)) exit(3);
$owned=[];
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jrS8AAAAASUVORK5CYII=',true);
checkMedia(is_string($bytes) && @getimagesizefromstring($bytes)!==false,'fixture_png_valid');
$sha=hash('sha256',$bytes);
$path=$dir.'/'.$sha.'.png';
$run=static function(string $file,string $payload,string $hash,array &$paths)use($engine,$method):void {
    $method->invokeArgs($engine,[$file,$payload,$hash,&$paths]);
};
try {
    $run($path,$bytes,$sha,$owned);
    checkMedia(is_file($path) && hash_file('sha256',$path)===$sha,'first_image_exclusively_created');
    $statBefore=stat($path);
    $run($path,$bytes,$sha,$owned);
    $statAfter=stat($path);
    checkMedia(count($owned)===1 && $statBefore['ino']===$statAfter['ino'],
        'second_signed_record_reuses_same_file_without_overwrite');

    file_put_contents($path, 'CHANGED_BY_OUTSIDE_WRITER');
    expectedFailure(fn()=>$run($path,$bytes,$sha,$owned),
        'DEMO_MEDIA_SHARED_ASSET_CHANGED','tampered_owned_file_rejected');
    // Simulated rollback removes only the file created during this attempt.
    foreach (array_keys($owned) as $ownedPath) { unlink($ownedPath); }
    $owned=[];
    checkMedia(!is_file($path),'rollback_removes_only_newly_created_file');

    file_put_contents($path,'UNRELATED_PREEXISTING_CUSTOMER_FILE');
    expectedFailure(fn()=>$run($path,$bytes,$sha,$owned),
        'DEMO_MEDIA_ALREADY_EXISTS','preexisting_customer_file_denied');
    checkMedia(file_get_contents($path)==='UNRELATED_PREEXISTING_CUSTOMER_FILE'
        && count($owned)===0,'preexisting_file_unmodified_untracked');
    unlink($path);

    $sentinel=$dir.'/sentinel.txt';file_put_contents($sentinel,'SENTINEL_SAFE');
    symlink($sentinel,$path);
    expectedFailure(fn()=>$run($path,$bytes,$sha,$owned),
        'DEMO_MEDIA_ALREADY_EXISTS','symlink_destination_denied');
    checkMedia(file_get_contents($sentinel)==='SENTINEL_SAFE','symlink_target_unchanged');
    unlink($path);unlink($sentinel);

    $invalid=$dir.'/missing/nested/asset.png';
    expectedFailure(fn()=>$run($invalid,$bytes,$sha,$owned),
        'DEMO_MEDIA_EXCLUSIVE_CREATE_FAILED','missing_parent_fails_closed');
    checkMedia(count($owned)===0,'failed_create_not_owned');
    echo "BARAMA_SHARED_MEDIA_V110_RUNTIME=PASS\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) { if (is_file($file)||is_link($file)) unlink($file); }
    rmdir($dir);
}
