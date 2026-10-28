<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/app/Services/DemoCustomerReleasePolicyV1.php';
use Barama\Services\DemoCustomerReleasePolicyV1 as Gate;
function checkGate(bool $ok, string $label): void {
    if (!$ok) {fwrite(STDERR, "FAIL=$label\n"); exit(1);}
    echo "PASS=$label\n";
}
$p=[
 'signature_verified'=>true,'package_version'=>'0.9.26',
 'format'=>'tonsamith-manifest-json','manifest_schema_version'=>1,
 'content_schema'=>'barama-demo-content-v1',
 'compatible_app_versions'=>['0.9.25'],'policy_records'=>20,
 'record_count'=>5255,'media_images'=>53,
];
checkGate(Gate::canInstall($p,'0.9.25'),'correct_customer_release');
foreach ([
 'legacy_version'=>['package_version','0.9.25'],
 'missing_policies'=>['policy_records',0],
 'wrong_policy_count'=>['policy_records',19],
 'unsigned'=>['signature_verified',false],
 'empty_media'=>['media_images',0],
 'unsupported_manifest'=>['manifest_schema_version',2],
 'missing_manifest'=>['manifest_schema_version',null],
 'unsupported_content'=>['content_schema','barama-demo-content-v2'],
 'wrong_format'=>['format','unsigned-json'],
 'malformed_compat'=>['compatible_app_versions',['0.9.25','../bad']],
] as $name=>$change){
 $bad=$p;$bad[$change[0]]=$change[1];
 checkGate(!Gate::canInstall($bad,'0.9.25'),$name.'_denied');
}
// v1.0.0 changes the application release label, not the signed demo
// schema. Keep the ORIGINAL immutable 0.9.26 manifest with 0.9.25 labels.
checkGate(Gate::canInstall($p,'1.0.0'),'same_signed_demo_on_v100');
checkGate(Gate::canInstall($p,'1.1.0'),'compatible_future_app_version');
$oldLabel=$p;
$oldLabel['compatible_app_versions']=['0.9.24'];
checkGate(Gate::canInstall($oldLabel,'1.0.0'),'legacy_version_labels_advisory');
$withoutLabels=$p;
unset($withoutLabels['compatible_app_versions']);
checkGate(Gate::canInstall($withoutLabels,'1.0.0'),'new_schema_only_manifest_accepted');
checkGate(!Gate::canInstall($p,'../invalid'),'invalid_app_version_rejected');
checkGate(!Gate::canInstall($p,'0.0.0/unsafe'),'unsafe_app_version_rejected');
$controller=file_get_contents(dirname(__DIR__).'/application/app/Http/Controllers/DemoImporterController.php');
checkGate(is_string($controller)&&str_contains($controller,'DEMO_PUBLISHED_PACKAGE_CHANGED')&&
  str_contains($controller,'DemoCustomerReleasePolicyV1::assertInstallable')&&
  str_contains($controller,'assertNativeTargetSchemaCompatible($proof)')&&
  str_contains($controller,'publishedPackageForHash((string)$stage')&&
  !str_contains($controller,'demo-import-native-v107.acceptance'),'revalidated_publisher_at_install');
echo "CUSTOMER_DEMO_RELEASE_GATE_V115=PASS\n";
