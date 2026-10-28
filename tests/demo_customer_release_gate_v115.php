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
 'missing_compat'=>['compatible_app_versions',null],
 'other_compat'=>['compatible_app_versions',['0.9.24']],
 'malformed_compat'=>['compatible_app_versions',['0.9.25','../bad']],
] as $name=>$change){
 $bad=$p;$bad[$change[0]]=$change[1];
 checkGate(!Gate::canInstall($bad,'0.9.25'),$name.'_denied');
}
checkGate(!Gate::canInstall($p,'1.0.0'),'different_application_denied');
$controller=file_get_contents(dirname(__DIR__).'/application/app/Http/Controllers/DemoImporterController.php');
checkGate(is_string($controller)&&str_contains($controller,'DEMO_PUBLISHED_PACKAGE_CHANGED')&&
  str_contains($controller,'DemoCustomerReleasePolicyV1::assertInstallable')&&
  str_contains($controller,'publishedPackageForHash((string)$stage')&&
  !str_contains($controller,'demo-import-native-v107.acceptance'),'revalidated_publisher_at_install');
echo "CUSTOMER_DEMO_RELEASE_GATE_V115=PASS\n";
