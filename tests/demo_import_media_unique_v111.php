<?php
declare(strict_types=1);

/** BARAMA_DEMO_MEDIA_PATH_UNIQUE_V111 — deterministic read-only runtime test. */
require dirname(__DIR__) . '/application/app/Services/DemoImportPackageV1.php';
$engine = new \Barama\Services\DemoImportPackageV1();
$normalize = new ReflectionMethod($engine, 'normalizeSignedNativeMedia');
function assertReconcile(bool $condition, string $name): void {
    if (!$condition) { fwrite(STDERR, "FAIL=$name\n"); exit(1); }
    echo "PASS=$name\n";
}
function expectReconcile(callable $action, string $expected, string $name): void {
    try { $action(); }
    catch (\RuntimeException $e) {
        assertReconcile($e->getMessage()===$expected, $name);
        return;
    }
    assertReconcile(false, $name);
}
$sha=str_repeat('a',64);
$path='demo/'.$sha.'.png';
$asset1=['id'=>20, 'uuid'=>'media-20', 'path'=>$path, 'disk'=>'private',
    'checksum_sha256'=>$sha, 'mime_type'=>'image/png', 'alt_text'=>'Front image'];
$asset2=$asset1;
$asset2['id']=21;
$asset2['uuid']='media-21';
$data=['tables'=>[
    'media_assets'=>[$asset1,$asset2],
    'media_attachments'=>[
       ['id'=>31,'media_asset_id'=>20,'entity_type'=>'company',
        'entity_id'=>10,'collection'=>'cover'],
       ['id'=>32,'media_asset_id'=>21,'entity_type'=>'company',
        'entity_id'=>10,'collection'=>'gallery']
    ],
    'companies'=>[['id'=>10,'featured_media_asset_id'=>21]]
]];
$original=$data;
$result=$normalize->invoke($engine,$data);
assertReconcile($data===$original,'signed_source_not_mutated');
assertReconcile($result['collapsed']===1,'one_redundant_media_row_removed');
assertReconcile(count($result['content']['tables']['media_assets'])===1,
    'one_unique_media_path_for_db_unique_index');
assertReconcile($result['content']['tables']['media_assets'][0]['id']===20,
    'canonical_media_id_stable');
assertReconcile($result['content']['tables']['media_attachments'][1]['media_asset_id']===20 &&
    $result['content']['tables']['companies'][0]['featured_media_asset_id']===20,
    'all_matching_media_asset_id_relationships_remapped');
$distinct=$data;
$distinct['tables']['media_assets'][1]['alt_text']='Different signed caption';
expectReconcile(fn()=>$normalize->invoke($engine,$distinct),
    'DEMO_SHARED_MEDIA_METADATA_CONFLICT','conflicting_signed_metadata_fails_closed');
$collision=$data;
$collision['tables']['media_attachments'][1]['collection']='cover';
expectReconcile(fn()=>$normalize->invoke($engine,$collision),
    'DEMO_SHARED_MEDIA_ATTACHMENT_CONFLICT','new_attachment_unique_collision_fails_closed');
$nulls=$data;
$nulls['tables']['media_attachments'][0]['collection']=null;
$nulls['tables']['media_attachments'][1]['collection']=null;
assertReconcile($normalize->invoke($engine,$nulls)['collapsed']===1,
    'mysql_null_collection_uniqueness_preserved');
$single=$data;
array_pop($single['tables']['media_assets']);
assertReconcile($normalize->invoke($engine,$single)['collapsed']===0,
    'no_duplicate_paths_preserved_without_remap');
echo "BARAMA_DEMO_MEDIA_UNIQUE_V111=PASS\n";
