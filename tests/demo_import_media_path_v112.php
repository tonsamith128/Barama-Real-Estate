<?php
declare(strict_types=1);
/**
 * BARAMA_DEMO_PRIVATE_MEDIA_PATH_V112
 * Detect regression where signed demo bytes are placed at
 * storage/uploads/private/demo but the DB stores demo/<hash>.
 */
$root = dirname(__DIR__);
$src = file_get_contents($root.'/application/app/Services/DemoImportPackageV1.php');
$public = file_get_contents($root.'/application/app/Http/Controllers/PublicMediaController.php');
if (!is_string($src) || !is_string($public)) exit(1);
$checks=[
 'signed_demo_path_still_strict' => str_contains($src, "~^demo/[a-f0-9]{64}"),
 'private_storage_directory' => str_contains($src, "/storage/uploads/private/' . \$old"),
 'verified_size_recorded' => str_contains($src, "\$row['size_bytes'] = strlen(\$bytes);"),
 'db_path_has_private_prefix' => str_contains($src, "\$row['path'] = 'private/' . \$old;"),
 'written_path_before_insert' => strpos($src, "\$row['path'] = 'private/' . \$old;") < strpos($src, "\$row = \$this->rewriteRecipientUrls(\$row, \$appUrl);"),
 'public_resolver_matches_storage' => str_contains($public, "storage/uploads/' . ltrim((string) \$media['path']"),
];
foreach ($checks as $name=>$ok) {
 echo ($ok?'PASS':'FAIL')."=$name\n";
 if (!$ok) exit(1);
}
echo "DEMO_MEDIA_PATH_V112=PASS\n";
