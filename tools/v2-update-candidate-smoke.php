<?php
declare(strict_types=1);

/** Disposable Git/ZIP builder test; never signs a real release or touches websites. */
if (PHP_SAPI !== 'cli' || !class_exists(ZipArchive::class)) {
    fwrite(STDERR, "TEST_RUNTIME_MISSING\n"); exit(1);
}
$tool = __DIR__ . '/build-v2-update-candidate.php';
$base = sys_get_temp_dir() . '/barama-v2-candidate-' . bin2hex(random_bytes(8));
$source = $base . '/source';
if (!mkdir($base, 0700) || !mkdir($source, 0700)) exit(1);
$run = static function (array $command, ?string $cwd = null): array {
    $pipes = [];
    $p = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $cwd);
    if (!is_resource($p)) throw new RuntimeException('PROCESS_UNAVAILABLE');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($p), (string)$out, (string)$err];
};
$expect = static function (array $command, bool $success, string $label, ?string $cwd = null) use ($run): string {
    [$code, $stdout, $stderr] = $run($command, $cwd);
    if (($code === 0) !== $success) {
        throw new RuntimeException($label . ': unexpected ' . $code . ' ' . $stderr . ' ' . $stdout);
    }
    echo $label . "=PASS\n";
    return $stdout;
};
$cleanup = static function () use ($base): void {
    if (!is_dir($base)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        if ($item->isDir() && !$item->isLink()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($base);
};
try {
    mkdir($source . '/application/app/Services/Updates', 0700, true);
    file_put_contents($source . '/VERSION', "0.9.22-dev\n");
    $path = 'application/app/Services/Updates/FixturePatch.php';
    $old = "<?php\n// prior genuine installation version\n";
    $new = "<?php\n// reviewed newer code\n";
    file_put_contents($source . '/' . $path, $new);
    $expect(['git','init','-q'], true, 'DISPOSABLE_GIT_INIT', $source);
    $expect(['git','-c','user.email=ci@example.test','-c','user.name=Barama CI',
        'add','VERSION',$path], true, 'DISPOSABLE_GIT_ADD', $source);
    $expect(['git','-c','user.email=ci@example.test','-c','user.name=Barama CI',
        'commit','-qm','fixture baseline'], true, 'DISPOSABLE_GIT_COMMIT', $source);
    [$rc,$commit] = $run(['git','rev-parse','HEAD'], $source);
    if ($rc !== 0 || !preg_match('/^[a-f0-9]{40}$/D',trim($commit))) {
        throw new RuntimeException('BAD_FIXTURE_COMMIT');
    }
    $recipe = [
        'schema'=>'barama-reviewed-v2-update-recipe-v1',
        'product_slug'=>'barama-real-estate',
        'source_commit'=>trim($commit),
        'source_version'=>'0.9.22-dev',
        'target_version'=>'0.9.23',
        'files'=>[[
            'path'=>$path,
            'source_sha256'=>hash('sha256',$new),
            'installed_sha256'=>hash('sha256',$old),
            'bytes'=>strlen($new),
        ]],
    ];
    $recipePath = $base . '/recipe.json';
    $write = static function (array $data) use ($recipePath): void {
        file_put_contents($recipePath, json_encode($data, JSON_THROW_ON_ERROR));
    };
    $cmd = static function (array $extra = []) use ($tool,$recipePath,$source): array {
        return [PHP_BINARY,$tool,'--recipe='.$recipePath,'--source-root='.$source,...$extra];
    };
    $write($recipe);
    $expect($cmd(), true, 'SOURCE_AND_RECIPE_READ_ONLY_CHECK');
    $out1 = $base . '/candidate-1.zip';
    $out2 = $base . '/candidate-2.zip';
    $expect($cmd(['--build','--output='.$out1]), true, 'REVIEWED_CODE_ONLY_ARCHIVE_BUILD');
    $expect($cmd(['--build','--output='.$out2]), true, 'DETERMINISTIC_SECOND_ARCHIVE_BUILD');
    if (!hash_equals((string)hash_file('sha256',$out1),(string)hash_file('sha256',$out2))) {
        throw new RuntimeException('ZIP_NOT_DETERMINISTIC');
    }
    echo "BYTE_IDENTICAL_SECOND_ARCHIVE=PASS\n";
    $zip = new ZipArchive();
    if ($zip->open($out1) !== true || $zip->numFiles !== 2
        || trim((string)$zip->getFromName('VERSION')) !== '0.9.23'
        || $zip->getFromName($path) !== $new) {
        throw new RuntimeException('ARCHIVE_CONTENT_INCORRECT');
    }
    $zip->close();
    echo "ONLY_VERSION_AND_REVIEWED_CODE_FILE=PASS\n";
    $expect($cmd(['--build','--output='.$out1]), false, 'ARCHIVE_OVERWRITE_REJECTED');
    $bad = $recipe;
    $bad['files'][0]['source_sha256'] = str_repeat('0',64);
    $write($bad);
    $expect($cmd(), false, 'MODIFIED_SOURCE_DIGEST_REJECTED');
    $bad = $recipe;
    $bad['files'][0]['installed_sha256'] = hash('sha256',$new);
    $write($bad);
    $expect($cmd(), false, 'NO_BASELINE_CHANGE_REJECTED');
    $bad = $recipe;
    $bad['files'][0]['path'] = 'application/database/migrations/2026_delete.php';
    $write($bad);
    $expect($cmd(), false, 'MIGRATIONS_REJECTED');
    $bad = $recipe;
    $bad['files'][0]['path'] = 'application/config/license.php';
    $write($bad);
    $expect($cmd(), false, 'PROTECTED_CONFIGURATION_REJECTED');
    $write($recipe);
    file_put_contents($source . '/' . $path, $new . "\n// uncommitted change");
    $expect($cmd(), false, 'DIRTY_SOURCE_TREE_REJECTED');
    echo "LIVE_WEBSITE_MODIFIED=NO\nREAL_SIGNING_KEY_ACCESSED=NO\nRELEASE_PUBLISHED=NO\nAUTO_INSTALL=DISABLED\n";
} finally {
    $cleanup();
}
