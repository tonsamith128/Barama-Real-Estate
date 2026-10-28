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
checkStatus(str_contains($controller,'DemoCustomerReleasePolicyV1::assertInstallable')
    && str_contains($controller,'publishedPackageForHash((string)$stage')
    && str_contains($controller,'DEMO_PUBLISHED_PACKAGE_CHANGED'),
    'fresh_entitlement_and_signature_before_install');
checkStatus(str_contains($controller,"public const RELEASE_GATE = 'customer_signed_demo_v1';")
    && !str_contains($controller,'demo-import-native-v107.acceptance')
    && str_contains($controller,"'demoReleaseAccepted' => $installReady"),
    'customer_release_gate_no_host_bypass');
checkStatus(str_contains($view,'data-demo-install-ready')
    && str_contains($view,'id="bdemo-verified-package"')
    && str_contains($view,'!empty($demoReleaseAccepted)'),
    'render_gate_and_review_identity');
checkStatus(str_contains($view,"asset('js/barama-demo-premium-v103.js') . '?v=108'"),
    'cache_bust_real_php_asset');
checkStatus(str_contains($view,'Customer release verified.')
    && str_contains($view,'The signed ZIP needs demo v0.9.26 or newer')
    && str_contains($view,'destination database')
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
