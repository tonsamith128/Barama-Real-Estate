<?php
declare(strict_types=1);
namespace Barama\Core {
 final class Installation {
  public static function runtimeConfig(): array { return ['APP_URL'=>'https://customer.example.test']; }
 }
}
namespace {
 if (getenv('BARAMA_DEMO_IT') !== 'isolated-ci') {echo "STOP=ISOLATED_CI_ONLY\n";exit(1);}
 $root=dirname(__DIR__);
 $base=sys_get_temp_dir().'/native-demo-it-'.bin2hex(random_bytes(8));
 mkdir($base,0700); mkdir($base.'/site',0700); mkdir($base.'/site/application',0700);
 define('BASE_PATH',$base.'/site'); define('APP_PATH',BASE_PATH.'/application');
 define('APP_VERSION','0.9.25');
 function config(string $key,mixed $default=null): mixed {
  return ['database.driver'=>'mysql','database.host'=>'127.0.0.1',
    'database.port'=>3306,'database.database'=>'barama_demo_test',
    'database.username'=>'root','database.password'=>'demo_test_password',
    'database.charset'=>'utf8mb4'][$key]??$default;
 }
 foreach (['Core/Database.php','Services/SystemToolService.php','Services/DemoPublisherApiV1.php',
 'Services/DemoPublisherNativeArchiveV1.php','Services/DemoImportPackageV1.php'] as $file) {
   require $root.'/application/app/'.$file;
 }
 function passNative(bool $yes,string $label):void {
   if (!$yes) {fwrite(STDERR,"FAIL=$label\n");exit(2);} echo "PASS=$label\n";
 }
 $db=\Barama\Core\Database::connection();
 passNative($db->query('SELECT DATABASE()')->fetchColumn()==='barama_demo_test','isolated_database');
 $tables=['locations','amenities','companies','projects','properties','listings',
   'cms_pages','media_assets','project_amenities','property_amenities','media_attachments'];
 foreach (array_merge($tables,['application_settings']) as $table) {
  $db->exec('DROP TABLE IF EXISTS '.$table);
 }
 foreach ($tables as $table) {
  $columns=match($table) {
   'locations'=>'id BIGINT PRIMARY KEY,name VARCHAR(120)',
   'companies'=>'id BIGINT PRIMARY KEY,name VARCHAR(120),location_id BIGINT',
   'media_assets'=>'id BIGINT PRIMARY KEY,disk VARCHAR(20),path VARCHAR(255),checksum_sha256 CHAR(64),mime_type VARCHAR(60)',
   'media_attachments'=>'id BIGINT PRIMARY KEY,entity_type VARCHAR(40),entity_id BIGINT,media_asset_id BIGINT',
   default=>'id BIGINT PRIMARY KEY'
  };
  $db->exec('CREATE TABLE '.$table.' ('.$columns.') ENGINE=InnoDB');
 }
 $db->exec('CREATE TABLE application_settings (setting_key VARCHAR(191) PRIMARY KEY,setting_value LONGTEXT NOT NULL,value_type VARCHAR(24) NOT NULL) ENGINE=InnoDB');
 $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jrS8AAAAASUVORK5CYII=',true);
 passNative(is_string($image)&&@getimagesizefromstring($image)!==false,'fixture_image_valid');
 $hash=hash('sha256',$image); $asset='demo/'.$hash.'.png';
 $assetDest=APP_PATH.'/storage/uploads/private/'.$asset;
 $pair=sodium_crypto_sign_keypair();
 $pub=sodium_crypto_sign_publickey($pair);
 $fp=hash('sha256',$pub);
 $identity=['fingerprint'=>$fp,'public_key'=>base64_encode($pub),'key_id'=>substr($fp,0,24)];
 $make=function(bool $fail) use($base,$tables,$image,$hash,$asset,$pair,$identity):array {
  $contentTables=array_fill_keys($tables,[]);
  $contentTables['locations']=[['id'=>12,'name'=>'Demo City']];
  $contentTables['companies']=[['id'=>14,'name'=>'Demo Co','location_id'=>12]];
  $contentTables['media_assets']=[['id'=>20,'disk'=>'private','path'=>$asset,
    'checksum_sha256'=>$hash,'mime_type'=>'image/png']];
  $attachment=['id'=>22,'media_asset_id'=>20,'entity_type'=>'company','entity_id'=>14];
  if($fail)$attachment['unknown_database_column']='invalid';
  $contentTables['media_attachments']=[$attachment];
  $payload=['schema'=>'barama-demo-content-v1',
   'components'=>['Homepage','Companies','Images'],
   'settings'=>['cms.site_content'=>['home'=>'{{APP_URL}}/hello'],
    'cms.footer_content'=>['brand'=>'Demo'],'appearance.frontend'=>['theme'=>'light']],
   'tables'=>$contentTables,'padding'=>bin2hex(random_bytes(800))];
  $path=$base.($fail?'/bad.zip':'/good.zip');
  $zip=new \ZipArchive();
  if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true)exit(3);
  $zip->addFromString('manifest.json',json_encode(['product'=>'barama-real-estate',
    'demo_slug'=>'live','version'=>'0.9.25','schema_version'=>1,
    'compatible_app_versions'=>['0.9.25']],JSON_THROW_ON_ERROR));
  $zip->addFromString('content.json',json_encode($payload,JSON_THROW_ON_ERROR));
  $zip->addFromString('review.json','{"source":"test"}');
  $zip->addFromString('assets/'.$hash.'.png',$image);
  $zip->close();
  $sha=hash_file('sha256',$path);
  return [$path,['id'=>$fail?'DCP-20261024-TEST-FAIL':'DCP-20261024-TEST-GOOD',
    'version'=>'0.9.25','sha256'=>$sha,'key_id'=>$identity['key_id'],
    'signature'=>base64_encode(sodium_crypto_sign_detached(hex2bin($sha),
     sodium_crypto_sign_secretkey($pair)))]];
 };
 $engine=new \Barama\Services\DemoImportPackageV1();
 [$bad,$badMeta]=$make(true);
 try {$engine->installNative($bad,$badMeta,$identity,$fp,42);passNative(false,'late_failure');}
 catch(\RuntimeException $e) {passNative($e->getMessage()==='DEMO_SCHEMA_COLUMN_MISMATCH','late_failure');}
 passNative((int)$db->query('SELECT COUNT(*) FROM locations')->fetchColumn()===0,'transaction_rollback');
 passNative(!is_file($assetDest),'failed_media_cleaned');
 passNative(count((new \Barama\Services\SystemToolService())->backups())===1,'backup_before_failed_import');
 [$good,$meta]=$make(false);
 try {$engine->installNative($good,$meta,$identity,str_repeat('0',64),42);passNative(false,'pin_denied');}
 catch(\RuntimeException $e) {passNative(true,'pin_denied');}
 $result=$engine->installNative($good,$meta,$identity,$fp,42);
 passNative($result['records']===4&&$result['assets']===1,'signed_native_zip_installed');
 passNative(is_file($assetDest),'media_installed');
 passNative((int)$db->query('SELECT COUNT(*) FROM media_attachments')->fetchColumn()===1,'relationships_installed');
 $setting=$db->query("SELECT setting_value FROM application_settings WHERE setting_key='cms.site_content'")->fetchColumn();
 passNative(str_contains((string)$setting,'https://customer.example.test/hello'),'recipient_url_rewritten');
 passNative(count((new \Barama\Services\SystemToolService())->backups())===2,'backup_before_success');
 try {$engine->installNative($good,$meta,$identity,$fp,42);passNative(false,'existing_content_guard');}
 catch(\RuntimeException $e) {
   passNative($e->getMessage()==='DEMO_REQUIRES_EMPTY_CONTENT_TABLES','existing_content_guard');
 }
 echo "NATIVE_PUBLISHER_MYSQL_ROLLBACK_ACCEPTANCE=PASS\n";
}
