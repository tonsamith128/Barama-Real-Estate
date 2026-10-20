<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/Updates/OneClickSignedPlanPreviewV1.php';

use Barama\Services\Updates\OneClickSignedPlanPreviewV1 as Preview;

if (!defined('APP_VERSION')) define('APP_VERSION', '0.9.22-dev');
$now = 1781844000;
$sha = str_repeat('a', 64);
$good = [
    'status' => 'SIGNED_RELEASE_PLAN_VERIFIED_NOT_INSTALLED',
    'verified_at' => $now - 30,
    'site_version' => '0.9.22-dev',
    'archive_sha256' => $sha,
    'installed_version' => '0.9.22-dev',
    'target_version' => '0.9.23',
    'file_count' => 2,
    'changed_files' => 1,
    'new_files' => 1,
    'unchanged_files' => 0,
    'bytes' => 42,
    'listed_paths' => [
        ['path'=>'VERSION', 'change'=>'Replace'],
        ['path'=>'assets/css/app.css', 'change'=>'New'],
    ],
    'paths_truncated' => false,
    'installation_enabled' => false,
    'db_migrations_verified' => false,
    'rollback_verified' => false,
];
$test = static function (bool $v, string $name): void {
    if (!$v) { fwrite(STDERR, 'FAIL=' . $name . PHP_EOL); exit(1); }
    echo 'PASS=' . $name . PHP_EOL;
};
$ok = Preview::display($good, $now, $sha);
$test($ok !== null && $ok['file_count'] === 2
    && $ok['listed_paths'][1]['change'] === 'New'
    && $ok['installation_enabled'] === false, 'verified_display_still_noninstalling');
$mutants = [
    'forged_install_yes' => array_replace($good, ['installation_enabled'=>true]),
    'forged_sql_ready' => array_replace($good, ['db_migrations_verified'=>true]),
    'forged_rollback_ready' => array_replace($good, ['rollback_verified'=>true]),
    'expired' => array_replace($good, ['verified_at'=>$now-601]),
    'future' => array_replace($good, ['verified_at'=>$now+1]),
    'old_site' => array_replace($good, ['site_version'=>'0.9.21']),
    'fake_count' => array_replace($good, ['file_count'=>'2']),
    'missing_digest' => array_replace($good, ['archive_sha256'=>'']),
    'foreign_path' => array_replace($good, ['listed_paths'=>[['path'=>'../storage/app/license/license.php', 'change'=>'New']]]),
    'bad_change_kind' => array_replace($good, ['listed_paths'=>[['path'=>'VERSION','change'=>'Write']]]),
    'too_many_listed' => array_replace($good, ['listed_paths'=>array_fill(0,41,['path'=>'VERSION','change'=>'Replace'])]),
];
foreach ($mutants as $name=>$m) $test(Preview::display($m,$now,$sha)===null,$name.'_rejected');
$test(Preview::display($good,$now,str_repeat('b',64))===null,'changed_archive_rejected');
$test(Preview::display(null,$now,$sha)===null,'missing_preview_rejected');

$source = file_get_contents(dirname(__DIR__).'/application/app/Services/Updates/OneClickSignedPlanPreviewV1.php');
$test(is_string($source)
    && str_contains($source, 'VerifiedReleaseFilePlanV2::inspect(')
    && str_contains($source, 'installation_enabled')
    && !preg_match('/\b(?:exec|shell_exec|proc_open|system|passthru|eval|file_put_contents|unlink|rename|mkdir|curl_exec)\s*\(/i',$source),
    'preview_uses_existing_verified_plan_without_execution');
echo 'ONECLICK_SIGNED_CHANGE_PREVIEW_SMOKE=PASS' . PHP_EOL;
