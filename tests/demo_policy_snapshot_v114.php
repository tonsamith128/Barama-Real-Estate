<?php
declare(strict_types=1);
require dirname(__DIR__) . '/application/app/Services/DemoPolicySnapshotV1.php';
use Barama\Services\DemoPolicySnapshotV1 as Snap;
function ok(bool $v,string $label):void {
    if(!$v){fwrite(STDERR,"FAIL=$label\n");exit(1);}
    echo "PASS=$label\n";
}
function denied(callable $f,string $label):void {
    try{$f();ok(false,$label);}catch(\RuntimeException $e){ok(true,$label);}
}
$dir=sys_get_temp_dir().'/barama-policy-signed-'.bin2hex(random_bytes(8));
mkdir($dir,0700);
$source=$dir.'/author-public-policies.json';
$published=[
'id'=>'policy-1','title'=>'Sample marketplace rules',
'slug'=>'marketplace-rules','summary'=>'Sample only','content_html'=>'<p>Demo content.</p>',
'category_id'=>'pcat-a','category'=>'Operations','status'=>'published','visibility'=>'public',
'effective_date'=>'2026-01-01','sort_order'=>0,'featured'=>true,'seo_title'=>'Sample rules',
'meta_description'=>'Public sample','faqs'=>[['question'=>'Who?','answer'=>'Demo visitors.']],
'version_number'=>1,'created_at'=>'2026-01-01','updated_at'=>'2026-01-02',
'compliance_notes'=>'SECRET_DO_NOT_EXPORT','versions'=>[['private'=>'SECRET_DO_NOT_EXPORT']]
];
file_put_contents($source,json_encode([
    'version'=>1,'policies'=>[$published, array_merge($published,[
        'id'=>'private','slug'=>'private','visibility'=>'private'
    ])],
    'categories'=>[['id'=>'pcat-a','name'=>'Operations','slug'=>'operations',
        'description'=>'Public','sort_order'=>1,'internal_notes'=>'SECRET_DO_NOT_EXPORT'],
       ['id'=>'pcat-private','name'=>'Private Category','slug'=>'private',
        'description'=>'SECRET_DO_NOT_EXPORT','sort_order'=>2]],
    'faqs'=>[['question'=>'Where?','answer'=>'Policy Center']],
    'feedback'=>['policy-1'=>['private'=>'SECRET_DO_NOT_EXPORT']],
],JSON_THROW_ON_ERROR));
$s=Snap::exportPublic($source);
ok(count($s['policies'])===1,'export_public_only');
ok(count($s['categories'])===1,'unreferenced_private_categories_not_exported');
ok(!str_contains(json_encode($s),'SECRET_DO_NOT_EXPORT'),'private_feedback_and_internal_notes_filtered');
Snap::validate($s); ok(true,'snapshot_contract_valid');
$invalid=$s;
$invalid['policies'][0]['content_html']='<script>alert(1)</script>';
denied(static fn()=>Snap::validate($invalid),'active_html_rejected');
$invalid=$s;
$invalid['feedback']=['secret'=>'leak'];
denied(static fn()=>Snap::validate($invalid),'extra_feedback_rejected');
$invalid=$s;
$invalid['policies'][0]['versions']=[['private'=>'leak']];
denied(static fn()=>Snap::validate($invalid),'policy_versions_rejected');
$customer=$dir.'/customer';mkdir($customer,0700);
$path=Snap::installFresh($s,$customer);
ok(is_file($path),'fresh_store_created');
$stored=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
ok(count($stored['policies'])===1 && $stored['feedback']===[],'policy_store_public_only');
denied(static fn()=>Snap::installFresh($s,$customer),'existing_store_protected');
ok(file_get_contents($path)!=='','existing_store_unchanged');
unlink($path);rmdir(dirname($path));rmdir(dirname(dirname($path)));rmdir($customer);
unlink($source);rmdir($dir);
echo "SIGNED_POLICY_SNAPSHOT_V114=PASS\n";