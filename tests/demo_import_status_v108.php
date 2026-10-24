<?php
declare(strict_types=1);
$base=dirname(__DIR__);
$controller=file_get_contents($base.'/application/app/Http/Controllers/DemoImporterController.php');
$view=file_get_contents($base.'/application/resources/views/workspace/demo-importer.php');
$js=file_get_contents($base.'/assets/js/barama-demo-premium-v103.js');
function checkStatus(bool $ok,string $key):void {
    if (!$ok) {fwrite(STDERR,"FAIL=$key\n");exit(1);}
    echo "PASS=$key\n";
}
checkStatus(str_contains($controller,"\$this->stagingInstallAccepted()")
    && str_contains($controller,'Staging installation is available below after package review')
    && !str_contains($controller,"'Official signed demo downloaded and staged. Installation remains disabled pending staging.'"),
    'flash_uses_actual_server_gate');
checkStatus(str_contains($controller,"public const RELEASE_GATE = 'download_only_staging_pending';")
    && str_contains($controller,'demo-import-native-v107.acceptance')
    && str_contains($controller,"if (!\$this->stagingInstallAccepted())"),
    'production_fails_closed');
checkStatus(str_contains($view,'data-demo-install-ready')
    && str_contains($view,'id="bdemo-verified-package"')
    && str_contains($view,'!empty($demoReleaseAccepted)'),
    'render_gate_and_review_identity');
checkStatus(str_contains($view,"asset('js/barama-demo-premium-v103.js') . '?v=108'"),
    'cache_bust_real_php_asset');
checkStatus(str_contains($view,'Staging pilot accepted.')
    && str_contains($view,'Installation remains disabled until staging acceptance.')
    && str_contains($view,'Install verified Live Demo'),
    'both_gate_states_described');
checkStatus(str_contains($js,"installReady=root.dataset.demoInstallReady==='1'")
    && str_contains($js,"reviewLink.href='#bdemo-verified-package'")
    && str_contains($js,"grid.after(packageReview)"),
    'action_scoped_and_review_above_diagnostics');
checkStatus(str_contains($view,'data-btool-protected')
    && str_contains($view,'name="confirmation"')
    && str_contains($controller,"DEMO_INSTALL_CONFIRMATION_REQUIRED"),
    'explicit_protected_install_confirmation');
echo "DEMO_IMPORT_STAGING_READY_UI_V108=PASS\n";
