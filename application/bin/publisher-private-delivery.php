<?php

declare(strict_types=1);

/**
 * Offline-installable aaPanel root CLI entry point.
 *
 * --readiness  reports local readiness without network or site changes.
 * --download --confirm=DOWNLOAD_AUTHENTIC_PUBLISHER_RELEASE_TO_ROOT_ONLY
 *              checks live publisher entitlement and downloads authentic
 *              signed bytes to /root only; never installs into the website.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);

$base = dirname(__DIR__, 2);
define('BASE_PATH', $base);
define('APP_PATH', $base . '/application');
define('BARAMA_BOOTSTRAP', true);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Barama\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $path = APP_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) require $path;
});
require_once APP_PATH . '/app/Support/helpers.php';
\Barama\Core\RuntimeEnvironment::load();
\Barama\Core\Config::load(APP_PATH . '/config');

use Barama\Services\Updates\PublisherPrivateDeliveryV1;

try {
    $mode = $argv[1] ?? '';
    if ($mode === '--readiness' && count($argv) === 2) {
        $r = PublisherPrivateDeliveryV1::readiness(BASE_PATH);
        echo "MODE=ROOT_PRIVATE_PUBLISHER_DELIVERY_READINESS\n";
        echo "SITE=STAGING_ONLY\n";
        echo 'REQUIRED_FILES=' . ($r['required_files_present'] ? 'PRESENT' : 'MISSING') . "\n";
        echo 'TRUSTED_PUBLISHER_READY=' . ($r['publisher_can_check'] ? 'YES' : 'NO') . "\n";
        echo 'MISSING_LOCAL_ITEMS=' . ($r['missing'] ? implode(',', $r['missing']) : 'NONE') . "\n";
        echo "LIVE_NETWORK_REQUESTS=NO\n";
        echo "WEBSITE_WRITES=NO\n";
        echo "DATABASE_CONNECTIONS=NO\n";
        echo "AUTO_INSTALL=DISABLED\n";
        echo "READINESS=PASS_DIAGNOSTIC_ONLY\n";
        exit(0);
    }
    if ($mode === '--download' && count($argv) === 3) {
        $confirmation = $argv[2];
        $r = PublisherPrivateDeliveryV1::download(BASE_PATH, $confirmation);
        echo 'DELIVERY_STATUS=' . $r['status'] . "\n";
        if ($r['status'] === 'AUTHENTICATED_ROOT_PRIVATE_ARTIFACT_RECEIVED') {
            echo 'PRIVATE_ARCHIVE=' . $r['archive'] . "\n";
            echo 'PRIVATE_RECEIPT=' . $r['receipt'] . "\n";
            echo 'SIGNED_ARCHIVE_SHA256=' . $r['sha256'] . "\n";
            echo 'SIGNED_RELEASE_VERSION=' . $r['version'] . "\n";
        }
        echo "WEBSITE_RELEASE_STAGED=NO\n";
        echo "WEBSITE_WRITES=NO\n";
        echo "DATABASE_CONNECTIONS=NO\n";
        echo "AUTO_INSTALL=DISABLED\n";
        exit(0);
    }
    throw new RuntimeException('PUBLISHER_DELIVERY_USAGE_INVALID');
} catch (Throwable $e) {
    // Safe error codes only: no stack, token, HTTP payload or private license values.
    $code = $e->getMessage();
    if (!preg_match('/^[A-Z][A-Z0-9_]{3,90}$/D', $code)) $code = 'PUBLISHER_DELIVERY_REJECTED';
    fwrite(STDERR, 'STOP=' . $code . "\n");
    exit(1);
}
