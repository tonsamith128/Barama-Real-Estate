<?php
declare(strict_types=1);

/**
 * INTERNAL RELEASE PIPELINE ONLY — builds a deterministic, restricted
 * Barama V2 update ZIP from a clean, committed source tree and a reviewed
 * file-level baseline recipe. No signing, publishing, website, or DB writes.
 *
 * php tools/build-v2-update-candidate.php --recipe=/absolute/recipe.json
 * php tools/build-v2-update-candidate.php --recipe=/absolute/recipe.json --build --output=/absolute/candidate.zip
 *
 * --source-root=... exists ONLY for isolated fixture tests and requires a
 * committed clean Git checkout with an exact matching recipe source_commit.
 */
$fail = static function (string $code): never {
    fwrite(STDERR, 'STOP=' . $code . PHP_EOL);
    exit(1);
};
if (PHP_SAPI !== 'cli') $fail('CLI_ONLY');
if (!class_exists(ZipArchive::class)) $fail('ZIP_EXTENSION_REQUIRED');

$args = ['build' => false, 'source-root' => dirname(__DIR__)];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--build') {
        if ($args['build']) $fail('DUPLICATE_ARGUMENT');
        $args['build'] = true;
        continue;
    }
    if (!preg_match('/^--(recipe|output|source-root)=(.+)$/D', $arg, $m)
        || array_key_exists($m[1], $args) && $m[1] !== 'source-root') {
        $fail('INVALID_ARGUMENT');
    }
    $args[$m[1]] = $m[2];
}
if (!isset($args['recipe']) || !str_starts_with($args['recipe'], '/')
    || !is_file($args['recipe']) || is_link($args['recipe'])) $fail('RECIPE_REQUIRED');
if ($args['build'] && (!isset($args['output']) || !str_starts_with($args['output'], '/'))) {
    $fail('OUTPUT_REQUIRED');
}
if (!$args['build'] && isset($args['output'])) $fail('CHECK_MODE_NEVER_WRITES');
$root = realpath((string)$args['source-root']);
if (!is_string($root) || $root === '/' || !is_dir($root) || is_link($args['source-root'])) {
    $fail('SOURCE_ROOT_INVALID');
}
$sourceVersion = $root . '/VERSION';
if (!is_file($sourceVersion) || is_link($sourceVersion)) $fail('SOURCE_VERSION_MISSING');
$versionPattern = '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/D';
$shaPattern = '/^[a-f0-9]{64}$/D';
$git = static function (array $cmd, string $cwd) use ($fail): string {
    $pipes = [];
    $process = proc_open($cmd, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $cwd);
    if (!is_resource($process)) $fail('SOURCE_GIT_UNAVAILABLE');
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_string($result)) $fail('SOURCE_GIT_INVALID');
    return trim($result);
};
$gitRoot = $git(['git','rev-parse','--show-toplevel'], $root);
if (!hash_equals($root, $gitRoot) || $git(['git','status','--porcelain','--untracked-files=all'], $root) !== '') {
    $fail('SOURCE_TREE_NOT_CLEAN_OR_NOT_ROOT');
}
$head = strtolower($git(['git','rev-parse','HEAD'], $root));
if (!preg_match('/^[a-f0-9]{40}$/D', $head)) $fail('SOURCE_COMMIT_INVALID');

$raw = file_get_contents($args['recipe']);
if (!is_string($raw) || strlen($raw) > 131072 || $raw === '') $fail('RECIPE_SIZE_INVALID');
try {
    $recipe = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $fail('RECIPE_INVALID_JSON');
}
if (!is_array($recipe) || array_keys($recipe) !== [
    'schema','product_slug','source_commit','source_version','target_version','files'
] || $recipe['schema'] !== 'barama-reviewed-v2-update-recipe-v1'
    || $recipe['product_slug'] !== 'barama-real-estate'
    || !is_string($recipe['source_commit']) || !hash_equals($head, strtolower($recipe['source_commit']))
    || !is_string($recipe['source_version']) || !preg_match($versionPattern, $recipe['source_version'])
    || !is_string($recipe['target_version']) || !preg_match($versionPattern, $recipe['target_version'])
    || trim((string)file_get_contents($sourceVersion)) !== $recipe['source_version']
    || version_compare($recipe['target_version'], $recipe['source_version'], '<=')
    || !is_array($recipe['files']) || !array_is_list($recipe['files'])
    || count($recipe['files']) < 1 || count($recipe['files']) > 8000) {
    $fail('RECIPE_METADATA_INVALID');
}

$files = [];
$seen = ['version' => true];
$total = strlen($recipe['target_version']) + 1;
foreach ($recipe['files'] as $entry) {
    if (!is_array($entry) || array_keys($entry) !== [
        'path','source_sha256','installed_sha256','bytes'
    ]) $fail('RECIPE_FILE_ENTRY_INVALID');
    [$path, $sha, $before, $size] = [
        $entry['path'], $entry['source_sha256'], $entry['installed_sha256'], $entry['bytes'],
    ];
    if (!is_string($path) || strlen($path) > 512
        || !preg_match('~^[A-Za-z0-9_./-]+$~D', $path)
        || !is_string($sha) || !preg_match($shaPattern, $sha)
        || !is_string($before) || !preg_match($shaPattern, $before)
        || !is_int($size) || $size < 1 || $size > 10000000
        || hash_equals($sha, $before)) $fail('RECIPE_FILE_ENTRY_INVALID');
    $segments = explode('/', $path);
    foreach ($segments as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.')) {
            $fail('UPDATE_PATH_UNSAFE');
        }
    }
    if (!(
        $path === 'index.php' || $path === 'updater/index.php' || $path === 'installer/index.php'
        || (str_starts_with($path, 'application/app/') && str_ends_with($path, '.php'))
        || (str_starts_with($path, 'application/routes/') && str_ends_with($path, '.php'))
        || (str_starts_with($path, 'application/resources/views/') && str_ends_with($path, '.php'))
        || (str_starts_with($path, 'assets/css/') && str_ends_with($path, '.css'))
        || (str_starts_with($path, 'assets/js/') && str_ends_with($path, '.js'))
    ) || preg_match('~(^|/)(storage|config|vendor|node_modules|uploads|private|\.git|\.env|\.htaccess|\.user\.ini)(/|$)~i', $path)) {
        $fail('UPDATE_PATH_PROTECTED');
    }
    $fold = strtolower($path);
    if (isset($seen[$fold])) $fail('UPDATE_PATH_DUPLICATE');
    $seen[$fold] = true;
    $full = $root;
    foreach ($segments as $part) {
        $full .= '/' . $part;
        if (is_link($full)) $fail('UPDATE_PATH_SYMLINK');
    }
    if (!is_file($full) || !is_readable($full) || filesize($full) !== $size
        || !hash_equals($sha, (string)hash_file('sha256', $full))) {
        $fail('SOURCE_FILE_NOT_REVIEWED');
    }
    $tracked = $git(['git','ls-files','--error-unmatch','--', $path], $root);
    if (!hash_equals($path, $tracked)) $fail('SOURCE_FILE_NOT_COMMITTED');
    $total += $size;
    if ($total > 2147483648) $fail('SOURCE_SIZE_LIMIT');
    $files[$path] = $full;
}
ksort($files, SORT_STRING);
echo 'MODE=' . ($args['build'] ? 'GUARDED_LOCAL_CANDIDATE_BUILD' : 'READ_ONLY_REVIEW') . PHP_EOL;
echo 'PRODUCT=barama-real-estate' . PHP_EOL;
echo 'SOURCE_COMMIT=' . $head . PHP_EOL;
echo 'SOURCE_VERSION=' . $recipe['source_version'] . PHP_EOL;
echo 'TARGET_VERSION=' . $recipe['target_version'] . PHP_EOL;
echo 'REVIEWED_CODE_FILES=' . count($files) . PHP_EOL;
echo 'BASELINE_DIGESTS=DECLARED_NOT_YET_VERIFIED_AGAINST_STAGING' . PHP_EOL;
echo 'MAC_OFFLINE_SIGNATURE=NOT_YET_CREATED' . PHP_EOL;
echo 'PUBLISHER_RELEASE_REGISTERED=NO' . PHP_EOL;
echo 'AUTOMATIC_INSTALL=DISABLED' . PHP_EOL;
if (!$args['build']) {
    echo 'REVIEW_STATUS=SOURCE_AND_RECIPE_VALIDATED_READ_ONLY' . PHP_EOL;
    exit(0);
}
$out = $args['output'];
$directory = realpath(dirname($out));
if (!is_string($directory) || $directory === '/'
    || is_link($args['output']) || file_exists($args['output'])
    || file_exists($args['output'] . '.sha256')
    || str_starts_with($directory . '/', $root . '/')
    || $directory === $root
    || !preg_match('/^[A-Za-z0-9_.-]+\.zip$/D', basename($out))
    || !is_writable($directory)) {
    $fail('OUTPUT_NOT_NEW_OR_UNSAFE');
}
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::EXCL) !== true) $fail('OUTPUT_ZIP_CREATE_FAILED');
$ok = false;
try {
    $payload = ['VERSION' => $recipe['target_version'] . "\n"];
    foreach ($files as $path => $absolute) {
        $body = file_get_contents($absolute);
        if (!is_string($body) || strlen($body) > 10000000) $fail('SOURCE_READ_FAILED');
        $payload[$path] = $body;
    }
    foreach ($payload as $path => $body) {
        if (!$zip->addFromString($path, $body)
            || !$zip->setMtimeName($path, 315532800)
            || !$zip->setCompressionName($path, ZipArchive::CM_DEFLATE, 9)
            || !$zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            $fail('OUTPUT_ZIP_ENTRY_FAILED');
        }
    }
    if (!$zip->close()) $fail('OUTPUT_ZIP_CLOSE_FAILED');
    $ok = true;
} finally {
    if (!$ok) {
        @$zip->close();
        @unlink($out);
    }
}
if (filesize($out) < 1 || filesize($out) > 536870912) {
    unlink($out); $fail('OUTPUT_ZIP_SIZE_INVALID');
}
$sha = hash_file('sha256', $out);
$handle = @fopen($out . '.sha256', 'x');
if (!is_resource($handle)) {
    unlink($out); $fail('OUTPUT_CHECKSUM_NOT_NEW');
}
fwrite($handle, $sha . '  ' . basename($out) . "\n");
fclose($handle);
@chmod($out, 0600); @chmod($out . '.sha256', 0600);
echo 'UPDATE_CANDIDATE_ZIP=' . $out . PHP_EOL;
echo 'UPDATE_CANDIDATE_SHA256=' . $sha . PHP_EOL;
echo 'UPDATE_CANDIDATE_STATUS=UNSIGNED_REVIEW_ONLY' . PHP_EOL;
echo 'WEBSITE_WRITES=NO' . PHP_EOL;
echo 'DATABASE_CONNECTIONS=NO' . PHP_EOL;
