<?php

declare(strict_types=1);

// Disposable fixture: no database, no network, no real site writes, no installer.
require dirname(__DIR__) . '/application/app/Services/Updates/PrivateUpdateFileSnapshotV1.php';

use Barama\Services\Updates\PrivateUpdateFileSnapshotV1 as Snap;

$prefix = rtrim(sys_get_temp_dir(), '/') . '/barama-updater-fixture-'.bin2hex(random_bytes(9));
$site = $prefix.'/site';
$vault = $prefix.'/vault';
$target = $prefix.'/restore';
$count = 0;
$assert = static function (bool $ok, string $name) use (&$count): void {
    if (!$ok) throw new RuntimeException('ASSERTION_FAILED: '.$name);
    $count++;
};
$refuse = static function (callable $fn, string $code) use ($assert): void {
    try { $fn(); } catch (RuntimeException $e) {
        $assert(str_starts_with($e->getMessage(),$code),'reject '.$code);
        return;
    }
    throw new RuntimeException('FAILED_TO_REJECT: '.$code);
};
$delete = static function (string $path) use (&$delete): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') $delete($path.'/'.$name);
    }
    @rmdir($path);
};
$write = static function (string $path, string $data): void {
    if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
    file_put_contents($path,$data);
};

try {
    mkdir($prefix,0700);
    foreach ([$site,$vault,$target] as $dir) mkdir($dir,0700);
    $files = [
        'VERSION'=>'0.9.22-dev'."\n",
        'assets/js/app.js'=>'window.Barama = { safe: true };'."\n",
    ];
    foreach ($files as $path=>$contents) $write($site.'/'.$path,$contents);
    $paths = ['VERSION','assets/js/app.js','assets/css/updater-new.css'];
    $result = Snap::capture($site,$vault,$paths,true);
    $assert($result['fixture_only'] === true && $result['restored'] === false,'fixture labeling');
    $assert($result['files']===3 && $result['existing']===2,'file counters');
    $assert($result['bytes']===strlen($files['VERSION'])+strlen($files['assets/js/app.js']),'backup sizes');
    $assert(Snap::verify($result['job'],$result['receipt_sha256']),'receipt and snapshot verify');
    $assert((fileperms($result['job']) & 0077) === 0,'private job mode');
    $assert((fileperms($result['job'].'/receipt.json') & 0077) === 0,'private receipt');
    $assert(!file_exists($site.'/assets/css/updater-new.css'),'missing file not created');
    $assert(file_get_contents($site.'/VERSION')===$files['VERSION'],'site file unchanged');
    $record=json_decode(file_get_contents($result['job'].'/receipt.json'),true,32,JSON_THROW_ON_ERROR);
    $assert($record['site_writes']===false && $record['restore_tested']===false,'accurate safety state');

    foreach ($files as $path=>$contents) $write($target.'/'.$path,"Modified ".$contents);
    $write($target.'/assets/css/updater-new.css','new installed fixture');
    $restore = Snap::rehearseRestore($result['job'],$result['receipt_sha256'],$target);
    $assert($restore['fixture_only']===true && $restore['restored']===2 && $restore['removed']===1,'fixture rollback');
    $assert(file_get_contents($target.'/VERSION')===$files['VERSION'],'version restored');
    $assert(file_get_contents($target.'/assets/js/app.js')===$files['assets/js/app.js'],'code restored');
    $assert(!file_exists($target.'/assets/css/updater-new.css'),'new file removed');
    $assert(file_get_contents($site.'/assets/js/app.js')===$files['assets/js/app.js'],'real fixture source untouched');
    $assert(Snap::verify($result['job'],$result['receipt_sha256']),'snapshot remains verified');

    $backup = $result['job'].'/files/VERSION';
    $initial = file_get_contents($backup);
    file_put_contents($backup,$initial.'TAMPER');
    $refuse(static fn()=>Snap::verify($result['job'],$result['receipt_sha256']),'FILE_SNAPSHOT_BYTES_TAMPERED');
    file_put_contents($backup,$initial);
    $assert(Snap::verify($result['job'],$result['receipt_sha256']),'repaired bytes reverify');

    $rfile=$result['job'].'/receipt.json';
    $receiptOrig=file_get_contents($rfile);
    file_put_contents($rfile,$receiptOrig."\n");
    $refuse(static fn()=>Snap::verify($result['job'],$result['receipt_sha256']),'FILE_SNAPSHOT_RECEIPT_TAMPERED');
    file_put_contents($rfile,$receiptOrig);

    $refuse(static fn()=>Snap::verify($result['job'],str_repeat('0',64)),'FILE_SNAPSHOT_RECEIPT_TAMPERED');
    $refuse(static fn()=>Snap::capture($site,$vault,['../VERSION'],true),'FILE_SNAPSHOT_PATH_INVALID');
    $refuse(static fn()=>Snap::capture($site,$vault,['application/storage/private.php'],true),'FILE_SNAPSHOT_PROTECTED_PATH');
    $refuse(static fn()=>Snap::capture($site,$vault,['config/database.php'],true),'FILE_SNAPSHOT_PROTECTED_PATH');
    $refuse(static fn()=>Snap::capture($site,$vault,['assets/x.php','assets/X.php'],true),'FILE_SNAPSHOT_DUPLICATE_PATH');
    $refuse(static fn()=>Snap::capture($site,$vault,[],true),'FILE_SNAPSHOT_PATH_COUNT_INVALID');
    $refuse(static fn()=>Snap::capture($site,$site,['VERSION'],true),'FILE_SNAPSHOT_ROOTS_OVERLAP');
    $refuse(static fn()=>Snap::capture($site,$vault,['VERSION'],false),'FILE_SNAPSHOT_STAGING_ONLY');
    $write($site.'/assets/real.txt','secret fixture'); symlink($site.'/assets/real.txt',$site.'/assets/link.php');
    $refuse(static fn()=>Snap::capture($site,$vault,['assets/link.php'],true),'FILE_SNAPSHOT_SYMLINK_DENIED');
    $refuse(static fn()=>Snap::capture($site,$vault,['assets/js'],true),'FILE_SNAPSHOT_SOURCE_NOT_REGULAR');
    $refuse(static fn()=>Snap::capture($site,$vault,['assets/real.txt/nested.php'],true),'FILE_SNAPSHOT_PARENT_NOT_DIRECTORY');
    $refuse(static fn()=>Snap::rehearseRestore($result['job'],$result['receipt_sha256'],$vault),'FILE_SNAPSHOT_RESTORE_FIXTURE_ONLY');
    mkdir($target.'/assets/css/updater-new.css',0700);
    $refuse(static fn()=>Snap::rehearseRestore($result['job'],$result['receipt_sha256'],$target),'FILE_SNAPSHOT_FIXTURE_TARGET_UNSAFE');
    rmdir($target.'/assets/css/updater-new.css');
    $assert(Snap::verify($result['job'],$result['receipt_sha256']),'last integrity check');

    echo "FIXTURE_ONLY=YES\n";
    echo "FILE_SNAPSHOT_CREATE_AND_VERIFY=PASS\n";
    echo "FIXTURE_ROLLBACK_REHEARSAL=PASS\n";
    echo "TRAVERSAL_SYMLINK_AND_TAMPER_REJECTION=PASS\n";
    echo "LIVE_DATABASE_CONNECTIONS=NONE\n";
    echo "LIVE_DATABASE_WRITES=NO\n";
    echo "STAGING_WEBSITE_WRITES=NO\n";
    echo "PRODUCTION_WEBSITE_WRITES=NO\n";
    echo "AUTO_INSTALL_ENABLED=NO\n";
    echo "TEST_ASSERTIONS={$count}\n";
    echo "FILE_SNAPSHOT_SAFETY_TESTS=PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FILE_SNAPSHOT_TEST_FAILED='.$e->getMessage()."\n");
    exit(1);
} finally {
    $delete($prefix);
}
