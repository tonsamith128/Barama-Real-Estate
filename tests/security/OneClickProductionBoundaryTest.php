<?php
declare(strict_types=1);
define('BASE_PATH','/www/wwwroot/baramarealestate.tonsamith.com');
define('APP_PATH',BASE_PATH.'/application');
require_once dirname(__DIR__,2).'/application/app/Services/Updates/PublisherTrustPaths.php';
require_once dirname(__DIR__,2).'/application/app/Services/Updates/OneClickUpdateQueueV1.php';

use Barama\Services\Updates\PublisherTrustPaths as T;
use Barama\Services\Updates\OneClickUpdateQueueV1 as Q;

function expect(bool $ok, string $label): void {
    if (!$ok) {fwrite(STDERR,'PRODUCTION_BOUNDARY_FAIL='.$label."\n");exit(1);}
}
expect(T::isProtectedProduction(),'production_flag');
expect(!T::isProtectedStaging(),'staging_not_selected');
expect(T::isProtectedInstallation(),'protected_installation');
expect(T::directory()==='/etc/barama/production/publisher-trust','production_private_trust');
expect(T::keys()==='/etc/barama/production/publisher-trust/update-publisher-v2-keys.json','keys_path');
expect(T::feed()==='/etc/barama/production/publisher-trust/update-feed-v2.json','feed_path');
// Integration in CI has no production trust directory. NO readiness or update
// request may succeed merely because the production domain string matches.
expect(!T::protectedDirectoryReady(),'missing_production_trust_denied');
expect(!Q::workerReady(),'missing_worker_denied');
try {
    Q::status(BASE_PATH,'bad-job');
    expect(false,'cli_status_rejected');
} catch (RuntimeException $e) {
    expect($e->getMessage()==='ONECLICK_STAGING_WEB_ONLY','cli_guard_denied');
}
$queue=file_get_contents(dirname(__DIR__,2).'/application/app/Services/Updates/OneClickUpdateQueueV1.php');
expect(str_contains($queue,'if ($site===self::SITE) return \'STAGING_ONLY\';'),'staging_scope_preserved');
expect(str_contains($queue,'if ($site===self::PRODUCTION_SITE) return \'PRODUCTION_ONLY\';'),'prod_scope_pinned');
expect(str_contains($queue,"'authorized_to_install'=>false"),'queue_does_not_authorize_installation');
echo "PRODUCTION_BOUNDARY_CI=PASS_FAIL_CLOSED\n";
