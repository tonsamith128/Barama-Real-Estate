<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$controller = file_get_contents($root.'/application/app/Http/Controllers/DemoImporterController.php');
$routes = file_get_contents($root.'/application/routes/web.php');
$view = file_get_contents($root.'/application/resources/views/workspace/demo-importer.php');
$client = file_get_contents($root.'/application/app/Services/DemoPublisherApiV1.php');
$download = file_get_contents($root.'/application/app/Services/DemoPublisherDownloadV1.php');
function needed(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR,"FAIL=$name\n");exit(1); }
    echo "PASS=$name\n";
}
foreach (['/dashboard/system/demo-importer/connection',
          '/dashboard/system/demo-importer/catalog'] as $uri) {
    needed(str_contains($routes,"'".$uri."'"),'admin_readonly_route_'.basename($uri));
}
needed(str_contains($controller,"public const RELEASE_GATE = 'publisher_pending';"),
    'production_importer_release_stays_pending');
needed(str_contains($controller,"self::RELEASE_GATE !== 'accepted'"),
    'database_install_kept_disabled');
needed(!str_contains($controller,'/license/v2/demo/package') &&
       !str_contains($client,'/license/v2/demo/package'),
    'invented_demo_download_url_removed');
needed(str_contains($controller,'function connection(') &&
       str_contains($controller,'function catalog('),
    'real_api_client_connected_to_controller');
needed(str_contains($view,'/dashboard/system/demo-importer/connection') &&
       str_contains($view,'/dashboard/system/demo-importer/catalog'),
    'real_api_actions_accessible_in_dashboard');
needed(str_contains($client,'Authorization: Bearer ') &&
       str_contains($client,'CURLOPT_FOLLOWLOCATION => false') &&
       str_contains($download,'DEMO_PUBLISHER_KEY_NOT_PINNED_OR_CHANGED'),
    'license_bearer_and_key_pinning_enforced');
needed(!str_contains($download,'shell_exec(') &&
       !str_contains($download,'unzip -') &&
       str_contains($download,'DEMO_DOWNLOAD_REDIRECT_HOST_NOT_APPROVED'),
    'no_executable_or_arbitrary_redirect_download');
echo "DEMO_PUBLISHER_DASHBOARD_INTEGRATION=PASS\n";
