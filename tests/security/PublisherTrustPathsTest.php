<?php
declare(strict_types=1);

// Read-only path-policy regression test; does not require a live site.
define('BASE_PATH', '/www/wwwroot/baramastaging.tonsamith.com');
define('APP_PATH', BASE_PATH . '/application');
require dirname(__DIR__, 2) . '/application/app/Services/Updates/PublisherTrustPaths.php';

use Barama\Services\Updates\PublisherTrustPaths;

function test(bool $condition, string $name): void {
    if (!$condition) { fwrite(STDERR, "FAIL={$name}\n"); exit(1); }
    echo "PASS={$name}\n";
}
test(PublisherTrustPaths::isProtectedStaging(), 'staging_scoped');
test(PublisherTrustPaths::directory() === '/etc/barama/staging/publisher-trust', 'fixed_directory');
test(PublisherTrustPaths::keys() === '/etc/barama/staging/publisher-trust/update-publisher-v2-keys.json', 'fixed_keys');
test(PublisherTrustPaths::feed() === '/etc/barama/staging/publisher-trust/update-feed-v2.json', 'fixed_feed');
test(PublisherTrustPaths::rootControlledDirectory('/etc'), 'root_controlled_etc');
test(!PublisherTrustPaths::rootControlledDirectory('/tmp'), 'deny_world_writable_ancestor');
test(!PublisherTrustPaths::rootControlledDirectory('/etc/../tmp'), 'deny_traversal');
test(!PublisherTrustPaths::rootControlledDirectory('/etc//barama'), 'deny_ambiguous_path');
test(!PublisherTrustPaths::rootControlledDirectory('/etc/barama-missing-standalone-test'), 'deny_missing_directory');
test(!PublisherTrustPaths::safeTrustFile('/etc/passwd', 65536), 'deny_unpinned_file');
test(!PublisherTrustPaths::safeTrustFile(PublisherTrustPaths::keys(), 0), 'deny_invalid_limit');
echo "NETWORK_ACCESSED=NO\nDATABASE_ACCESSED=NO\nFILES_MODIFIED=NO\n";
