<?php

declare(strict_types=1);

/**
 * Stage historical Barama records ONLY in a fresh, private Laravel QA clone.
 * Use --plan first (no writes), then --stage with explicit safety approval.
 * NEVER run against an installed Pure PHP database or public Laravel host.
 */
if (PHP_SAPI !== 'cli'
    || !in_array($argv[1]??'', ['--plan','--stage'], true)
    || count($argv)!==2) {
    fwrite(STDERR,"REHEARSAL_DENIED=USE_PLAN_OR_STAGE_ON_CLI\n");
    exit(3);
}
$mode=$argv[1];
$required=[
    'SOURCE_HOST','SOURCE_DB','SOURCE_USER','SOURCE_PASSWORD',
    'TARGET_HOST','TARGET_DB','TARGET_USER','TARGET_PASSWORD',
];
foreach ($required as $env) {
    if (getenv('BARAMA_'.$env)===false || getenv('BARAMA_'.$env)==='') {
        fwrite(STDERR,"REHEARSAL_DENIED=MISSING_PRIVATE_CONFIG\n");
        exit(3);
    }
}
$srcDb=(string)getenv('BARAMA_SOURCE_DB');
$dstDb=(string)getenv('BARAMA_TARGET_DB');
$srcHost=(string)getenv('BARAMA_SOURCE_HOST');
$dstHost=(string)getenv('BARAMA_TARGET_HOST');
if (getenv('BARAMA_REHEARSAL_APPROVED')!=='TWO_DISPOSABLE_CLONES_VERIFIED'
    || !in_array($srcHost,['127.0.0.1','localhost'],true)
    || !in_array($dstHost,['127.0.0.1','localhost'],true)
    || preg_match('/^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$/D',$srcDb)!==1
    || preg_match('/^barama_laravel_(qa|rehearsal)_[a-z0-9_]{4,32}$/D',$dstDb)!==1
    || $srcDb===$dstDb
    || getenv('APP_ENV')!=='testing') {
    fwrite(STDERR,"REHEARSAL_DENIED=DATABASE_OR_ENVIRONMENT_SAFETY\n");
    exit(3);
}
if ($mode==='--stage' && (
    getenv('BARAMA_STAGE_APPROVED')!=='WRITE_ONLY_FRESH_DISPOSABLE_LARAVEL_CLONE'
    || getenv('BARAMA_MAIL_DISABLED')!=='yes'
    || getenv('BARAMA_TARGET_OFFLINE_VERIFIED')!=='yes')) {
    fwrite(STDERR,"REHEARSAL_DENIED=STAGING_APPROVAL_OR_OFFLINE_GATE\n");
    exit(3);
}

/** @return PDO */
function cloneConnection(string $role): PDO
{
    return new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('BARAMA_'.$role.'_HOST'),
        (int)(getenv('BARAMA_'.$role.'_PORT') ?: 3306),
        getenv('BARAMA_'.$role.'_DB')),
        (string)getenv('BARAMA_'.$role.'_USER'),
        (string)getenv('BARAMA_'.$role.'_PASSWORD'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>5,
        ]);
}
function cloneMediaRoot(string $key, string $requiredPart): string
{
    $value=(string)getenv($key);
    $root=$value!=='' ? realpath($value):false;
    if ($root===false || !is_dir($root) || is_link($root)
        || !str_contains($root,$requiredPart)
        || str_starts_with($root,'/www/wwwroot/')
        || $root==='/' || !str_starts_with($root,'/')) {
        throw new RuntimeException('Private isolated media root is unavailable.');
    }
    return $root;
}
try {
    $source=cloneConnection('SOURCE');
    $target=cloneConnection('TARGET');
    $source->exec('SET SESSION TRANSACTION READ ONLY');
    if ($source->query('SELECT DATABASE()')->fetchColumn()!==$srcDb
        || $target->query('SELECT DATABASE()')->fetchColumn()!==$dstDb) {
        throw new RuntimeException('Database identity mismatch.');
    }
    $mediaSource=cloneMediaRoot(
        'BARAMA_MEDIA_CLONE_ROOT','barama-legacy-media-rehearsal');
    $mediaTarget=cloneMediaRoot(
        'BARAMA_TARGET_MEDIA_ROOT','barama-laravel-media-rehearsal');
    if ($mediaSource===$mediaTarget) {
        throw new RuntimeException('Source and target media paths may not overlap.');
    }

    $sourceTables=$source->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $targetTables=$target->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $supported=[
        'users','locations','amenities','companies','company_members','projects',
        'properties','listings','project_amenities','property_amenities',
        'location_countries','location_data_packs','location_hierarchy_registry',
        'media_assets','media_attachments','inquiries','saved_listings',
    ];
    $destTables=[
        'users','locations','amenities','companies','company_user','projects',
        'properties','listings','project_amenities','property_amenities',
        'location_countries','location_data_packs','location_hierarchy_registry',
        'media_assets','media_attachments','inquiries','saved_listings','legacy_import_batches',
        'legacy_identity_maps','legacy_company_membership_reviews',
    ];
    foreach ($supported as $table) {
        if (!in_array($table,$sourceTables,true)) {
            throw new RuntimeException('Required source schema is incomplete.');
        }
    }
    foreach ($destTables as $table) {
        if (!in_array($table,$targetTables,true)) {
            throw new RuntimeException('Required target schema is incomplete.');
        }
    }

    $legacyCounts=$unmapped=[];
    foreach ($supported as $table) {
        $legacyCounts[$table]=(int)$source->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    }
    // Report EVERY source table without an adapter, even if a future source
    // migration introduces an unfamiliar table. This is an inventory, not
    // permission to omit records. Never rely on a handwritten short list.
    foreach ($sourceTables as $table) {
        if (in_array($table,$supported,true)) {
            continue;
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/D',$table)!==1) {
            throw new RuntimeException('Unsafe or unrecognized source table name.');
        }
        $unmapped[$table]=(int)$source->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    }
    ksort($unmapped);
    if ($mode==='--plan') {
        echo json_encode([
            'mode'=>'READ_ONLY_PLAN','legacy_record_counts'=>$legacyCounts,
            'unmapped_legacy_data_counts'=>$unmapped,
            'account_password_reenrollment_required'=>true,
            'company_roles_need_manual_approval'=>true,
            'source_data_printed'=>false,'source_changed'=>false,'target_changed'=>false,
            'release_gate'=>'BLOCKED_UNTIL_INDEPENDENT_RECONCILIATION',
        ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
        exit(0);
    }

    require_once __DIR__.'/BaramaLegacyCloneStager.php';
    $stage=new BaramaLegacyCloneStager($source,$target,$mediaSource,$mediaTarget);
    $result=$stage->stage();
    $result['unmapped_legacy_data_counts']=$unmapped;
    $result['unmapped_nonempty_table_count']=count(array_filter(
        $unmapped,static fn(int $count):bool=>$count>0
    ));
    $result['source_changed']=false;
    $result['release_gate']='BLOCKED_UNTIL_ALL_HISTORICAL_DATA_AND_PERMISSIONS_RECONCILED';
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    exit(0);
} catch(Throwable $e) {
    // Source data, secrets, SQL errors and media paths must not enter logs.
    fwrite(STDERR,"REHEARSAL_FAILED=PRIVATE_CONVERSION_GATE_OR_MAPPING_ERROR\n");
    exit(4);
}
