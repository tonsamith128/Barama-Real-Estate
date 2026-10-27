<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/DemoPolicySnapshotV1.php';
require dirname(__DIR__) . '/application/app/Repositories/PolicyCenterRepository.php';

use Barama\Services\DemoPolicySnapshotV1 as Snapshot;
use Barama\Repositories\PolicyCenterRepository as Repository;

function check(bool $value, string $name): void {
    if (!$value) { fwrite(STDERR, "FAIL=$name\n"); exit(1); }
    echo "PASS=$name\n";
}
function rejects(callable $fn, string $name): void {
    try { $fn(); check(false, $name); }
    catch (\RuntimeException $e) { check(true, $name); }
}
$dir = sys_get_temp_dir().'/barama-demo-templates-v116-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
define('BASE_PATH', $dir);
$policy = [
    'id'=>'sample-terms','title'=>'Sample Terms','slug'=>'sample-terms',
    'summary'=>'Sample draft','content_html'=>'<blockquote>Administrator review required before publication.</blockquote><p>Change me.</p>',
    'category_id'=>'sample-category','category'=>'Legal','effective_date'=>'2026-10-05',
    'seo_title'=>'Sample Terms','meta_description'=>'Sample',
    'created_at'=>'2026-10-05','updated_at'=>'2026-10-05',
    'status'=>'published','visibility'=>'public','sort_order'=>10,'featured'=>true,
    'version_number'=>2,'faqs'=>[]
];
$signed = [
    'version'=>1,'policies'=>[$policy],
    'categories'=>[['id'=>'sample-category','name'=>'Legal','slug'=>'legal','description'=>'','sort_order'=>10]],
    'faqs'=>[]
];
Snapshot::validate($signed);
$path = Snapshot::installFresh($signed, $dir);
$store = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
$p = $store['policies'][0];
check($p['status']==='draft' && $p['visibility']==='private','recipient_starts_private');
check($p['effective_date']==='' && $p['featured']===false,'author_effective_date_removed');
check($p['imported_demo_template']===true && $p['demo_template_approved']===false,'approval_not_inherited');
check($p['compliance_gate_enabled']===true && $p['compliance_status']==='not_reviewed','compliance_mandatory');
check($p['content_html']===$policy['content_html'],'source_text_preserved');
check($store['feedback']===[] && $signed['policies'][0]['status']==='published','source_unchanged_no_feedback');
$repo = new Repository();
check(count($repo->published())===0,'recipient_not_public');
$store['policies'][0]['status']='published';
$store['policies'][0]['visibility']='public';
file_put_contents($path,json_encode($store,JSON_THROW_ON_ERROR));
check(count($repo->published())===0,'forged_published_status_denied');
if (extension_loaded('dom') && extension_loaded('mbstring')) {
    $input = [
        'title'=>'Sample Terms','slug'=>'sample-terms','content_html'=>$policy['content_html'],
        'category_id'=>'sample-category','status'=>'published','visibility'=>'public',
        'save_mode'=>'publish','compliance_gate_enabled'=>'0','compliance_status'=>'compliant',
        'reviewed_by'=>'Reviewer','last_reviewed_at'=>gmdate('Y-m-d'),
        'compliance_check'=>Repository::complianceCheckKeys()
    ];
    $saved = $repo->save($input,'sample-terms');
    check($saved['status']==='draft' && $saved['visibility']==='private','editorial_instruction_blocks_publish');
    check(count($repo->published())===0,'unreviewed_content_not_public');
    $input['content_html']='<p>Reviewed operator-specific sample policy.</p>';
    $saved = $repo->save($input,'sample-terms');
    check($saved['status']==='published' && $saved['visibility']==='public','explicit_compliance_allows_publication');
    check($saved['demo_template_approved']===true && count($repo->published())===1,'approved_only_public');
    $input['save_mode']='draft';
    $input['status']='published';
    $saved = $repo->save($input,'sample-terms');
    check($saved['status']==='draft' && $saved['visibility']==='private','status_field_no_bypass');
    $versions=$repo->findById('sample-terms')['versions'];
    $restored=$repo->restoreVersion('sample-terms',(string)$versions[0]['id']);
    check($restored['status']==='draft' && $restored['visibility']==='private' && !$restored['demo_template_approved'],'restore_requires_review');
    $copy=$repo->duplicate('sample-terms');
    check($copy['status']==='draft' && $copy['visibility']==='private' && !$copy['demo_template_approved'],'duplicate_requires_review');
    check(count($repo->published())===0,'restored_and_duplicate_remain_private');
} else {
    echo "SKIP=FORM_SAVE_TEST_REQUIRES_MBSTRING_AND_DOM\n";
}
rejects(static fn()=>Snapshot::installFresh($signed,$dir),'existing_policy_store_no_clobber');
echo "DEMO_CUSTOMER_POLICY_TEMPLATES_V116=PASS\n";
