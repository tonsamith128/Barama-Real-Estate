<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/app/Services/Updates/StagingUpdateReadinessV1.php';
require dirname(__DIR__) . '/application/app/Services/Updates/StagingSignedReleasePreflightV1.php';
require dirname(__DIR__) . '/application/app/Services/Updates/StagingReleaseAcceptanceV1.php';
use Barama\Services\Updates\StagingReleaseAcceptanceV1;
use Barama\Services\Updates\StagingUpdateReadinessV1;
use Barama\Services\Updates\StagingSignedReleasePreflightV1;
$root = '/tmp/barama-release-acceptance-' . bin2hex(random_bytes(8));
$site = $root . '/site';
try {
    mkdir($root, 0700);
    mkdir($site, 0700);
    mkdir($site.'/application', 0700);
    mkdir($site.'/application/config', 0700);
    mkdir($site.'/application/app', 0700);
    mkdir($site.'/application/app/Services', 0700);
    mkdir($site.'/application/app/Services/Updates', 0700);
    define('BASE_PATH', $site);
    define('APP_PATH', $site.'/application');
    file_put_contents($site.'/VERSION', "0.9.22-dev\n");
    foreach (['SignedReleaseInspector.php','TrustedReleaseFeed.php','VerifiedReleaseFilePlanV2.php',
        'VerifiedUpdateBackupV2.php','PrivateDatabaseBackupV2.php','IsolatedUpdateTransactionV1.php',
        'IsolatedMysqlRecoveryV1.php'] as $file) {
        file_put_contents($site.'/application/app/Services/Updates/'.$file, '<?php // synthetic');
    }
    $catalog = '{"format":"tonsamith-publisher-keys-v2","keys":{}}';
    $catalogPath=$site.'/application/config/update-publisher-v2-keys.json';
    file_put_contents($catalogPath, $catalog);
    $sha=hash('sha256', $catalog);
    $missing=StagingReleaseAcceptanceV1::inspect($site, null);
    if (!in_array('INDEPENDENT_PUBLISHER_FINGERPRINT_REQUIRED',$missing['blockers'],true)) throw new RuntimeException('MISSING_FINGERPRINT_ACCEPTED');
    $wrong=StagingReleaseAcceptanceV1::inspect($site, str_repeat('a',64));
    if (!in_array('INDEPENDENT_PUBLISHER_FINGERPRINT_MISMATCH',$wrong['blockers'],true)) throw new RuntimeException('MISMATCH_ACCEPTED');
    $matched=StagingReleaseAcceptanceV1::inspect($site, $sha);
    if (!$matched['catalog_matches_supplied_fingerprint'] || $matched['ready_to_install']
        || $matched['automatic_update_enabled'] || $matched['database_connections']
        || $matched['website_writes'] || $matched['network_requests']) throw new RuntimeException('FAIL_OPEN');
    if (!in_array('SIGNED_RELEASE_NOT_STAGED',$matched['blockers'],true)
        || !in_array('LIVE_PUBLISHER_ENTITLEMENT_RECHECK_REQUIRED',$matched['blockers'],true)
        || !in_array('STAGING_DATABASE_ROLLBACK_ACCEPTANCE_REQUIRED',$matched['blockers'],true))
        throw new RuntimeException('MISSING_RELEASE_BLOCKERS');
    echo "INDEPENDENT_FINGERPRINT_REQUIRED=PASS\n";
    echo "CATALOG_MISMATCH_REJECTED=PASS\n";
    echo "STAGING_ROLLBACK_AND_ENTITLEMENT_BLOCKERS=PASS\n";
    echo "READ_ONLY=PASS\nAUTO_INSTALL=DISABLED\n";
} finally {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $entry) {
        if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($root);
}
