<?php
declare(strict_types=1);

use ZipArchive;

$archive=$argv[1]??null;
if (PHP_SAPI!=='cli' || !is_string($archive) || is_link($archive)
    || !is_file($archive)) throw new RuntimeException('ZIP_REQUIRED');
$zip=new ZipArchive();
if ($zip->open($archive, ZipArchive::RDONLY)!==true) {
    throw new RuntimeException('CUSTOMER_ZIP_INVALID');
}
try {
    $required=[
        'VERSION',
        'application/app/Services/Updates/TrustedReleaseFeed.php',
        'application/app/Services/Updates/PortableUpdateArchiveV1.php',
        'application/app/Services/Updates/PortableUpdateBackupV1.php',
        'application/app/Services/Updates/PortableUpdateDownloadV1.php',
        'application/app/Services/Updates/PortableUpdateFileTransactionV1.php',
        'application/app/Services/Updates/PortableCustomerUpdateV1.php',
        'application/app/Http/Controllers/SystemCenterController.php',
        'application/routes/web.php',
        'application/resources/views/workspace/auto-updater-unified-v100.php',
        'assets/js/barama-portable-auto-updater-v1.js',
        'assets/css/barama-qa-busy-arrow-v105.css',
        'application/config/update-feed-v2.json',
        'application/config/update-publisher-v2-keys.json',
    ];
    foreach ($required as $entry) {
        if ($zip->locateName($entry)===false) {
            throw new RuntimeException('CUSTOMER_ZIP_MISSING_PORTABLE_COMPONENT='.$entry);
        }
        echo 'PASS='.$entry."\n";
    }
    $view=(string)$zip->getFromName('application/resources/views/workspace/auto-updater-unified-v100.php');
    $busyCss=(string)$zip->getFromName('assets/css/barama-qa-busy-arrow-v105.css');
    if (!str_contains($view, 'barama-qa-busy-arrow-v105.css')
        || !str_contains($view, 'hash_equals($uVersion, $uPortableTarget)')
        || !str_contains($busyCss, 'bau-qa-amber-arrow-orbit-v105')) {
        throw new RuntimeException('CUSTOMER_UI_OR_TARGET_VERSION_GUARD_MISSING');
    }
    $feed=$zip->getFromName('application/config/update-feed-v2.json');
    $keys=$zip->getFromName('application/config/update-publisher-v2-keys.json');
    if (!is_string($feed)||!is_string($keys)) throw new RuntimeException('TRUST_FILES_MISSING');
    $f=json_decode($feed,true,8,JSON_THROW_ON_ERROR);
    $k=json_decode($keys,true,12,JSON_THROW_ON_ERROR);
    if ($f!==['enabled'=>true,'contract'=>'tonsamith-signed-discovery-v2','channel'=>'stable']
        || ($k['format']??'')!=='tonsamith-publisher-keys-v2'
        || !is_array($k['keys']??null)
        || count($k['keys'])!==2
        || ($k['keys']['barama-prod-2026-10']['status']??'')!=='retired'
        || ($k['keys']['barama-prod-2026-10-r2']['status']??'')!=='active') {
        throw new RuntimeException('CUSTOMER_TRUST_CONFIG_NOT_PINNED');
    }
    foreach ($k['keys'] as $record) {
        if (!is_array($record) || array_keys($record)!==['public','status']) {
            throw new RuntimeException('CUSTOMER_CATALOG_CONTAINS_NONPUBLIC_DATA');
        }
    }
    foreach (['tests/portable_customer_e2e_v1.php','.github/workflows/portable-auto-updater-v1.yml',
        'application/storage/app/license/license.php','application/storage/app/installation/environment.php'] as $forbidden) {
        if ($zip->locateName($forbidden)!==false) {
            throw new RuntimeException('CUSTOMER_ZIP_TEST_OR_SECRET_PRESENT='.$forbidden);
        }
    }
    echo "PORTABLE_CUSTOMER_EXACT_ZIP=PASS\n";
} finally { $zip->close(); }
