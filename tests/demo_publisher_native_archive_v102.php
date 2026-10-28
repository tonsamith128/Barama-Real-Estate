<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/app/Services/DemoPublisherApiV1.php';
require dirname(__DIR__) . '/application/app/Services/DemoPublisherNativeArchiveV1.php';

use Barama\Services\DemoPublisherNativeArchiveV1;

function okNative(bool $yes, string $label): void {
    if (!$yes) { fwrite(STDERR, "FAIL=$label\n");exit(1); }
    echo "PASS=$label\n";
}
function rejectNative(callable $cb, string $label): void {
    try { $cb(); } catch (Throwable) {okNative(true,$label);return;}
    okNative(false,$label);
}
$dir=sys_get_temp_dir().'/native-demo-'.bin2hex(random_bytes(6));
mkdir($dir,0700);
$image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jrS8AAAAASUVORK5CYII=');
$hash=hash('sha256',$image);
$name='assets/'.$hash.'.png';
$tables=array_fill_keys([
 'locations','amenities','companies','projects','properties','listings',
 'cms_pages','project_amenities','property_amenities','media_assets','media_attachments'
], []);
$tables['locations']=[['id'=>2,'name'=>'Demo Province','parent_id'=>null]];
$tables['companies']=[['id'=>3,'name'=>'Public Demo','location_id'=>2]];
$tables['media_assets']=[['id'=>4,'disk'=>'private','path'=>'demo/'.$hash.'.png',
 'checksum_sha256'=>$hash,'mime_type'=>'image/png']];
$tables['media_attachments']=[['id'=>5,'media_asset_id'=>4,
 'entity_type'=>'company','entity_id'=>3]];
$settings=[
  'cms.site_content'=>['homepage'=>['title'=>'Official demo']],
  'cms.footer_content'=>['brand'=>'Demo'],
  'appearance.frontend'=>['skin'=>'light']
];
$manifest=[
    'product'=>'barama-real-estate','demo_slug'=>'live','version'=>'0.9.24',
    'schema_version'=>1,'compatible_app_versions'=>['0.9.24']
];
$make=function(string $path,array $tables,string $imageBytes,
               bool $extra=false,bool $omitVersions=false)use($manifest,$settings,$name,$hash):void {
    $z=new ZipArchive();
    if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)exit(1);
    $m=$manifest;
    if($omitVersions)unset($m['compatible_app_versions']);
    $z->addFromString('manifest.json',json_encode($m,JSON_THROW_ON_ERROR));
    $z->addFromString('content.json',json_encode([
        'schema'=>'barama-demo-content-v1',
        'components'=>['Homepage','Navigation','Footer','Appearance','Marketplace','Images'],
        'settings'=>$settings,'tables'=>$tables,
        'padding'=>bin2hex(random_bytes(600))
    ],JSON_THROW_ON_ERROR));
    $z->addFromString('review.json','{"mode":"private-review"}');
    $z->addFromString($name,$imageBytes);
    if($extra)$z->addFromString('config.php','<?php die();');
    $z->close();
};
$file=$dir.'/good.zip';
$make($file,$tables,$image,false);
$inspector=new DemoPublisherNativeArchiveV1();
$review=$inspector->inspect($file);
okNative($review['record_count']===4 && $review['media_images']===1,
    'real_native_manifest_and_public_relationships');
okNative(($review['manifest_schema_version']??null)===1 &&
    ($review['content_schema']??'')==='barama-demo-content-v1' &&
    ($review['columns_by_table']['companies']??[])===['id','name','location_id'],
    'signed_content_schema_and_table_column_contract');
okNative(!$review['signature_verified'] && !$review['install_authorized'],
    'unsigned_structure_never_claimed_as_trusted_or_installable');

$pair=sodium_crypto_sign_keypair();
$public=sodium_crypto_sign_publickey($pair);
$fingerprint=hash('sha256',$public);
$metadata=[
 'sha256'=>$review['zip_sha256'],
 'signature'=>base64_encode(sodium_crypto_sign_detached(
    hex2bin($review['zip_sha256']),sodium_crypto_sign_secretkey($pair))),
 'key_id'=>substr($fingerprint,0,24)
];
$identity=['public_key'=>base64_encode($public),
           'key_id'=>$metadata['key_id'],'fingerprint'=>$fingerprint];
$verified=$inspector->inspectSigned($file,$metadata,$identity,$fingerprint);
okNative($verified['signature_verified']===true && $verified['install_authorized']===false,
    'outside_zip_publisher_sha256_signature_verified_without_import');
rejectNative(fn()=>$inspector->inspectSigned($file,$metadata,$identity,str_repeat('0',64)),
    'unexpected_signing_key_denied');

$noLabels=$dir.'/schema-only.zip';
$make($noLabels,$tables,$image,false,true);
$schemaOnly=$inspector->inspect($noLabels);
okNative($schemaOnly['compatible_app_versions']===null &&
    $schemaOnly['manifest_schema_version']===1,'schema_only_without_app_version_list');
$wrong=$dir.'/wrong-reference.zip';
$broken=$tables;
$broken['media_attachments'][0]['entity_id']=999;
$make($wrong,$broken,$image,false);
rejectNative(fn()=>$inspector->inspect($wrong),'missing_related_company_denied');

$changed=$dir.'/changed-image.zip';
$make($changed,$tables,$image.'changed',false);
rejectNative(fn()=>$inspector->inspect($changed),'tampered_image_bytes_denied');

$extra=$dir.'/unexpected-php.zip';
$make($extra,$tables,$image,true);
rejectNative(fn()=>$inspector->inspect($extra),'executable_entry_denied');

foreach(glob($dir.'/*')?:[] as $f)unlink($f);
rmdir($dir);
echo "DEMO_NATIVE_PUBLISHER_ARCHIVE_READONLY=PASS\n";
