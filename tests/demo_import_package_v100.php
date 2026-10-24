<?php
declare(strict_types=1);

namespace Barama\Services\Updates {
    final class PublisherTrustPaths
    {
        public static function keys(): string { return $GLOBALS['demo_test_key_catalog']; }
    }
}
namespace {
    use Barama\Services\DemoImportPackageV1;

    if (!defined('APP_VERSION')) define('APP_VERSION', '0.9.25');
    require dirname(__DIR__) . '/application/app/Services/DemoImportPackageV1.php';

    function pass(bool $yes, string $label): void {
        if (!$yes) { fwrite(STDERR, "FAIL=$label\n"); exit(1); }
        echo "PASS=$label\n";
    }
    $base = sys_get_temp_dir() . '/demo-import-contract-' . bin2hex(random_bytes(8));
    if (!mkdir($base, 0700)) exit(1);
    $keys = sodium_crypto_sign_keypair();
    $id = 'barama-publisher-test';
    $GLOBALS['demo_test_key_catalog'] = $base . '/keys.json';
    file_put_contents($GLOBALS['demo_test_key_catalog'], json_encode([
        'format' => 'tonsamith-publisher-keys-v2',
        'keys' => [
            $id => [
                'public' => base64_encode(sodium_crypto_sign_publickey($keys)),
                'status' => 'active'
            ]
        ]
    ], JSON_THROW_ON_ERROR));
    $content = [
        'schema' => 'barama-demo-content-v1',
        'components' => ['Homepage', 'Navigation', 'Footer', 'Pages', 'Images'],
        'settings' => [
            'cms.site_content' => ['homepage' => ['title' => 'Published Demo']],
            'appearance.frontend' => ['mode' => 'light'],
            'cms.footer_content' => ['copyright' => 'Demo footer'],
        ],
        'tables' => [
            'locations' => [['id' => 1000, 'name' => 'Demo City']]
        ],
    ];
    $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $manifest = [
        'schema' => 'tonsamith-barama-demo-v1',
        'product_slug' => 'barama-real-estate',
        'publisher' => 'tonsamith.com',
        'package_id' => 'barama-live-demo-test',
        'key_id' => $id,
        'minimum_version' => '0.9.24',
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
        'files' => ['content.json' => hash('sha256', $contentJson)],
    ];
    ksort($manifest, SORT_STRING);
    $sig = sodium_crypto_sign_detached(
        "TONSAMITH-BARAMA-DEMO-V1\n" .
            json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        sodium_crypto_sign_secretkey($keys)
    );
    $envelope = json_encode([
        'manifest' => $manifest,
        'signature' => ['algorithm' => 'Ed25519', 'key_id' => $id, 'value' => base64_encode($sig)]
    ], JSON_THROW_ON_ERROR);
    function pack(string $path, string $env, string $body): void {
        $z = new \ZipArchive();
        if ($z->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) exit(1);
        $z->addFromString('envelope.json', $env);
        $z->addFromString('content.json', $body);
        $z->close();
    }
    $good = $base . '/good.zip';
    pack($good, $envelope, $contentJson);
    $engine = new DemoImportPackageV1();
    $review = $engine->inspect($good);
    pass($review['package_id'] === 'barama-live-demo-test', 'correct_publisher_signed_package');
    pass($review['records'] === 1 && $review['settings'] === 3, 'signed_content_parsed');
    $tampered = $base . '/changed.zip';
    pack($tampered, $envelope, str_replace('Demo City', 'Evil City', $contentJson));
    $blocked = false;
    try { $engine->inspect($tampered); } catch (\Throwable) { $blocked = true; }
    pass($blocked, 'tampered_body_denied');
    $badEnvelope = str_replace('barama-real-estate','different-product', $envelope);
    $wrongProduct = $base . '/different-product.zip';
    pack($wrongProduct, $badEnvelope, $contentJson);
    $blocked = false;
    try { $engine->inspect($wrongProduct); } catch (\Throwable) { $blocked = true; }
    pass($blocked, 'wrong_product_denied');
    $extra = $base . '/extra.zip';
    pack($extra, $envelope, $contentJson);
    $z = new \ZipArchive();
    $z->open($extra);
    $z->addFromString('config.php', '<?php exit;');
    $z->close();
    $blocked = false;
    try { $engine->inspect($extra); } catch (\Throwable) { $blocked = true; }
    pass($blocked, 'arbitrary_php_entry_denied');
    $expired = $base . '/expired.zip';
    $expiredManifest = $manifest;
    $expiredManifest['expires_at'] = '2020-01-01T00:00:00Z';
    $expiredSignature = sodium_crypto_sign_detached("TONSAMITH-BARAMA-DEMO-V1\n" .
        json_encode($expiredManifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        sodium_crypto_sign_secretkey($keys));
    pack($expired, json_encode(['manifest'=>$expiredManifest, 'signature'=>[
        'algorithm'=>'Ed25519','key_id'=>$id,'value'=>base64_encode($expiredSignature)]], JSON_THROW_ON_ERROR),
        $contentJson);
    $blocked = false;
    try { $engine->inspect($expired); } catch (\Throwable) { $blocked = true; }
    pass($blocked, 'expired_signed_package_denied');
    foreach (glob($base . '/*') ?: [] as $f) unlink($f);
    rmdir($base);
    echo "DEMO_IMPORT_OFFLINE_SIGNATURE_CONTRACT=PASS\n";
}
