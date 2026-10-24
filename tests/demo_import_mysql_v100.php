<?php
declare(strict_types=1);
namespace Barama\Services\Updates {
    final class PublisherTrustPaths {
        public static function keys(): string { return $GLOBALS['demo_it_keys']; }
    }
}
namespace Barama\Core {
    final class Installation {
        public static function runtimeConfig(): array {
            return ['APP_URL'=>'https://customer.example.test'];
        }
    }
}
namespace {
    if (getenv('BARAMA_DEMO_IT') !== 'isolated-ci') {
        fwrite(STDERR, "STOP=ONLY_ISOLATED_CI\n"); exit(1);
    }
    $base = sys_get_temp_dir() . '/barama-demo-it-' . bin2hex(random_bytes(8));
    mkdir($base, 0700);
    mkdir($base . '/site', 0700);
    mkdir($base . '/site/application', 0700);
    define('BASE_PATH', $base . '/site');
    define('APP_PATH', BASE_PATH . '/application');
    define('APP_VERSION', '0.9.25');
    function config(string $key, mixed $fallback = null): mixed {
        $data = [
            'database.driver'=>'mysql','database.host'=>'127.0.0.1',
            'database.port'=>3306,'database.database'=>'barama_demo_test',
            'database.username'=>'root','database.password'=>'demo_test_password',
            'database.charset'=>'utf8mb4',
        ];
        return $data[$key] ?? $fallback;
    }
    require dirname(__DIR__) . '/application/app/Core/Database.php';
    require dirname(__DIR__) . '/application/app/Services/SystemToolService.php';
    require dirname(__DIR__) . '/application/app/Services/DemoImportPackageV1.php';
    function checkDemo(bool $check, string $label): void {
        if (!$check) { fwrite(STDERR, "FAIL=$label\n"); exit(2); }
        echo "PASS=$label\n";
    }
    $db = \Barama\Core\Database::connection();
    $db->exec('CREATE TABLE locations (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(160) NOT NULL UNIQUE) ENGINE=InnoDB');
    $db->exec('CREATE TABLE application_settings (setting_key VARCHAR(191) PRIMARY KEY, setting_value LONGTEXT NOT NULL, value_type VARCHAR(24) NOT NULL) ENGINE=InnoDB');

    $keys = sodium_crypto_sign_keypair();
    $id = 'barama-demo-it';
    $GLOBALS['demo_it_keys'] = $base . '/keys.json';
    file_put_contents($GLOBALS['demo_it_keys'], json_encode([
        'format'=>'tonsamith-publisher-keys-v2',
        'keys'=>[$id=>['public'=>base64_encode(sodium_crypto_sign_publickey($keys)),'status'=>'active']]
    ], JSON_THROW_ON_ERROR));
    $doc = json_encode([
        'schema'=>'barama-demo-content-v1','components'=>['Homepage','Footer','Locations'],
        'settings'=>['cms.footer_content'=>['copyright'=>'Visit {{APP_URL}} for more information']],
        'tables'=>['locations'=>[['id'=>299,'name'=>'Signed Sample City']]],
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $manifest = [
        'schema'=>'tonsamith-barama-demo-v1','publisher'=>'tonsamith.com',
        'product_slug'=>'barama-real-estate','package_id'=>'barama-live-demo-it',
        'key_id'=>$id,'minimum_version'=>'0.9.24',
        'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+86400),
        'files'=>['content.json'=>hash('sha256',$doc)],
    ];
    ksort($manifest,SORT_STRING);
    $sig=sodium_crypto_sign_detached("TONSAMITH-BARAMA-DEMO-V1\n".
        json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        sodium_crypto_sign_secretkey($keys));
    $archive = $base.'/demo.zip';
    $zip = new \ZipArchive();
    if($zip->open($archive,\ZipArchive::CREATE)!==true) exit(3);
    $zip->addFromString('envelope.json',json_encode([
        'manifest'=>$manifest,'signature'=>['algorithm'=>'Ed25519','key_id'=>$id,'value'=>base64_encode($sig)]
    ],JSON_THROW_ON_ERROR));
    $zip->addFromString('content.json',$doc);
    $zip->close();
    $service = new \Barama\Services\DemoImportPackageV1();
    checkDemo($service->inspect($archive)['records']===1,'valid_signed_zip');
    $installed=$service->install($archive,42);
    checkDemo($installed['records']===1,'transaction_committed');
    checkDemo((int)$db->query('SELECT COUNT(*) FROM locations')->fetchColumn()===1,'data_row_visible');
    checkDemo($db->query('SELECT name FROM locations WHERE id=299')->fetchColumn()==='Signed Sample City','exact_content_row');
    checkDemo(str_contains((string)$db->query("SELECT setting_value FROM application_settings WHERE setting_key='cms.footer_content'")->fetchColumn(), 'https://customer.example.test'), 'signed_footer_recipient_domain_mapped');
    checkDemo(count((new \Barama\Services\SystemToolService())->backups())===1,'preimport_safety_backup_created');
    $blocked=false;
    try{$service->install($archive,42);}catch(\RuntimeException $e){$blocked=$e->getMessage()==='DEMO_REQUIRES_EMPTY_CONTENT_TABLES';}
    checkDemo($blocked,'repeated_import_denied_without_changes');
    echo "DEMO_IMPORT_ISOLATED_MYSQL_ACCEPTANCE=PASS\n";
}
