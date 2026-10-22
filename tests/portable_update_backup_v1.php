<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateArchiveV1.php';
require_once dirname(__DIR__).'/application/app/Services/Updates/PortableUpdateBackupV1.php';

use Barama\Services\Updates\PortableUpdateBackupV1 as Backup;

function must(bool $value,string $label): void {
    if (!$value) throw new RuntimeException('FAIL='.$label);
    echo 'PASS='.$label."\n";
}
function denied(callable $f,string $label): void {
    try { $f(); } catch (RuntimeException) {
        echo 'PASS='.$label."\n"; return;
    }
    throw new RuntimeException('FAIL='.$label);
}
function cleanup(string $path): void {
    if (is_link($path)||is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path,FilesystemIterator::SKIP_DOTS) as $x)
        cleanup($x->getPathname());
    rmdir($path);
}
$base=sys_get_temp_dir().'/barama-backup-test-'.bin2hex(random_bytes(7));
$site=$base.'/site';$vault=$base.'/vault';
mkdir($site.'/assets/js',0700,true);mkdir($vault,0700,true);chmod($vault,0700);
file_put_contents($site.'/VERSION',"0.9.23\n");
file_put_contents($site.'/assets/js/app.js',"before\n");
$plan=[
    'archive_sha256'=>str_repeat('a',64),
    'installed_version'=>'0.9.23','target_version'=>'0.9.24',
    'files'=>[
        ['path'=>'VERSION','baseline'=>hash('sha256',"0.9.23\n"),
         'sha256'=>hash('sha256',"0.9.24\n"),'bytes'=>7],
        ['path'=>'assets/js/app.js','baseline'=>hash('sha256',"before\n"),
         'sha256'=>hash('sha256',"after\n"),'bytes'=>6],
    ]
];
try {
    $report=Backup::prepare($site,$vault,$plan);
    $id=$report['job_id'];
    must($report['status']==='FILE_BACKUP_VERIFIED_NOT_INSTALLED','private_file_backup_prepared');
    Backup::verify($site,$vault,$id,$plan);
    must(is_file($site.'/VERSION') && file_get_contents($site.'/VERSION')==="0.9.23\n",'live_version_unchanged');
    must(!is_file($vault.'/'.$id.'/database.sql'),'never_claim_sql_backup');
    $backup=$vault.'/'.$id.'/files/'.hash('sha256','VERSION').'.bin';
    file_put_contents($backup,"tampered\n");
    denied(fn()=>Backup::verify($site,$vault,$id,$plan),'tampered_backup_denied');
    denied(fn()=>Backup::prepare($site,$site,$plan),'public_backup_dir_denied');
    file_put_contents($site.'/assets/js/app.js',"unexpected\n");
    denied(fn()=>Backup::prepare($site,$vault,$plan),'changed_baseline_denied');
    echo "PORTABLE_FILE_BACKUP_TESTS=PASS\n";
} finally { cleanup($base); }
