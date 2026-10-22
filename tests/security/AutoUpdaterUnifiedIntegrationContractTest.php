<?php
declare(strict_types=1);

/**
 * Offline contract test for the active unified Auto Updater integration.
 * Never boots a website, contacts Publisher, changes SQL or queues a job.
 */
$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    if (!is_file($file) || is_link($file)) throw new RuntimeException('FILE_UNAVAILABLE:' . $path);
    $data = file_get_contents($file);
    if (!is_string($data)) throw new RuntimeException('FILE_UNREADABLE:' . $path);
    return $data;
};
$must = static function (bool $ok, string $reason): void {
    if (!$ok) throw new RuntimeException('FAIL:' . $reason);
};
try {
    $wrapper = $read('application/resources/views/workspace/system-center.php');
    $view = $read('application/resources/views/workspace/auto-updater-unified-v100.php');
    $controller = $read('application/app/Http/Controllers/SystemCenterController.php');
    $routes = $read('application/routes/web.php');
    $queue = $read('application/app/Services/Updates/OneClickUpdateQueueV1.php');
    $trust = $read('application/app/Services/Updates/PublisherTrustPaths.php');
    $feed = $read('application/app/Services/Updates/TrustedReleaseFeed.php');
    $download = $read('application/bin/oneclick-unprivileged-delivery.php');
    $js = $read('assets/js/barama-oneclick-status-v100.js');
    $main = $read('docs/AUTO_UPDATER_MASTER_PLAN.md');

    $must(str_contains($wrapper, "require __DIR__ . '/auto-updater-unified-v100.php';"), 'ACTIVE_VIEW');
    $must(str_contains($view, "barama-auto-updater-theme-v101.css"), 'ACCEPTED_THEME_PRESERVED');
    $must(str_contains($view, "data-oneclick-status"), 'UNIFIED_STATUS_PRESENT');
    $must(str_contains($view, "data-oneclick-stage-message"), 'UNIFIED_STAGE_MESSAGE_PRESENT');
    $must(str_contains($view, "data-oneclick-initial-state"), 'SESSION_STATE_BINDING');
    $must(str_contains($view, "data-oneclick-initial-worker-ready"), 'WORKER_READY_BINDING');
    $must(str_contains($view, "data-oneclick-initial-pending"), 'PENDING_BINDING');
    $must(str_contains($view, "barama-oneclick-status-v100.js"), 'STAGING_JS_LOADED');

    $must(str_contains($view, 'if ($uStagingQueueSupported)'), 'QUEUE_GUARD');
    $must(str_contains($view, '&& $uStagingAdministrator'), 'ADMINISTRATOR_GUARD');
    $must(str_contains($view, '&& $uVersionAvailable'), 'SIGNED_RELEASE_GUARD');
    $must(str_contains($view, '&& $systemOneClickWorkerReady && !$systemOneClickPending'), 'WORKER_AND_PENDING_GUARD');
    $must(str_contains($view, 'preg_match(\'/^[a-f0-9]{64}$/D\', $uFeed[\'archive_sha256\'])'), 'DIGEST_GUARD');
    $must(str_contains($view, 'Update Now unavailable'), 'DISABLED_CLOSED_UI');
    $must(str_contains($view, "method=\"post\" action=\"<?= e(url('/dashboard/system/auto-updater/update-now')) ?>\""), 'POST_INTENT');
    $must(str_contains($view, "\\Barama\\Core\\Csrf::token()"), 'CSRF_FORM');
    $must(str_contains($view, 'elseif ($uReadyInstall)'), 'NON_STAGING_UI_UNCHANGED');

    $postLines = preg_grep('/\$router->post\(.+auto-updater\/update-now/', explode("\n", $routes));
    $must(count($postLines) === 1, 'UNIQUE_POST_ROUTE');
    $must(str_contains((string) current($postLines), 'new CsrfMiddleware()'), 'CSRF_MIDDLEWARE');
    $statusLines = preg_grep('/\$router->get\(.+auto-updater\/status/', explode("\n", $routes));
    $must(count($statusLines) === 1, 'UNIQUE_STATUS_ROUTE');
    $must(str_contains($controller, "function requestOneClickUpdate"), 'CONTROLLER_POST');
    $must(str_contains($controller, "function oneClickStatus"), 'CONTROLLER_STATUS');
    $must(substr_count($controller, 'PublisherTrustPaths::isProtectedStaging()') >= 3, 'STAGING_ONLY_CONTROLLER');
    $must(substr_count($controller, "hasRole('administrator')") >= 2, 'ADMINISTRATOR_ONLY_CONTROLLER');
    $must(str_contains($controller, "TrustedReleaseFeed::check("), 'FRESH_PUBLISHER_RECHECK');
    $must(str_contains($controller, "'Cache-Control' => 'no-store, no-cache, must-revalidate'"), 'NO_CACHED_STATUS');
    $must(str_contains($controller, 'is_string($sessionJob) ? $sessionJob : null'), 'SESSION_EXACT_JOB');
    $must(str_contains($queue, "return " . '$site===self::SITE ? \'STAGING_ONLY\' : null;'), 'PRODUCTION_QUEUE_DISABLED');
    $must(!str_contains($queue, "PRODUCTION_ONLY"), 'NO_PRODUCTION_SCOPE');
    $must(str_contains($queue, "'authorized_to_install'=>false"), 'WEB_INTENT_NEVER_AUTHORIZES');
    $must(str_contains($queue, "ONECLICK_PENDING_JOB_REQUIRES_REVIEW"), 'DUPLICATE_JOB_REFUSED');
    $must(str_contains($queue, "ONECLICK_ADMIN_AUDIT_NOT_SAVED"), 'PERSISTENT_AUDIT_GUARD');
    $must(str_contains($queue, 'hash_equals($jobId, $r[\'job_id\'])'), 'RESULT_BOUND_TO_JOB');
    $must(str_contains($trust, "/etc/barama/staging/publisher-trust"), 'ROOT_SIGNAL_PATH');
    $must(str_contains($feed, 'bool $includeSignedEnvelope = false'), 'NO_DEFAULT_ENVELOPE_EXPOSURE');
    $must(str_contains($feed, 'PublisherTrustPaths::isProtectedStaging()'), 'PINNED_STAGING_TRUST_SOURCE');
    $must(str_contains($feed, 'PublisherTrustPaths::feed() : APP_PATH . self::CONFIG_FILE'), 'PRODUCTION_FEED_FILE_PRESERVED');
    $must(str_contains($feed, 'PublisherTrustPaths::keys() : APP_PATH . self::KEYS_FILE'), 'PRODUCTION_PUBLIC_KEYS_PRESERVED');
    $must(str_contains($download, "posix_geteuid() === 0"), 'ROOT_DOWNLOADER_REJECTED');
    $must(str_contains($download, "CURLOPT_SSL_VERIFYPEER => true"), 'TLS_MANDATORY');
    $must(str_contains($download, "DOWNLOAD_UNPRIVILEGED_CLI_REQUIRED"), 'CLI_ONLY');
    $must(str_contains($js, "credentials: 'same-origin'"), 'SAME_ORIGIN_STATUS');
    $must(str_contains($js, "data.worker_ready !== (initialWorker === '1')"), 'SERVER_RENDERED_READINESS');
    $must(str_contains($js, "Never enable/submit an update"), 'NO_CLIENT_ENABLE');
    $must(str_contains($main, 'AUTO_UPDATER_MASTER_PLAN') === false || str_contains($main, 'Staging'), 'CANONICAL_PLAN_PRESENT');

    echo "UNIFIED_ONECLICK_STAGING_INTEGRATION=PASS\n";
    echo "PRODUCTION_WORKER_QUEUE=DISABLED\n";
    echo "CUSTOMER_ROOT_WORKER=NOT_PROVISIONED\n";
    echo "INSTALLATION_EXECUTED=NO\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
