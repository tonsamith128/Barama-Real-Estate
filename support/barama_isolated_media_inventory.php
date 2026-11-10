<?php

declare(strict_types=1);

/**
 * Read-only private-media evidence on an authorized disposable source clone.
 * The DB path must resolve beneath one explicit cloned-media root; there is
 * deliberately no fallback to the live aaPanel public or application roots.
 */
$root = (string) getenv('BARAMA_MEDIA_CLONE_ROOT');
$realRoot = $root !== '' ? realpath($root) : false;
$dbName = (string) getenv('BARAMA_SOURCE_DB');
if (PHP_SAPI !== 'cli'
    || getenv('BARAMA_REHEARSAL_APPROVED') !== 'TWO_DISPOSABLE_CLONES_VERIFIED'
    || preg_match('/^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$/D', $dbName) !== 1
    || !in_array(getenv('BARAMA_SOURCE_HOST'), ['127.0.0.1','localhost'], true)
    || $realRoot === false || !is_dir($realRoot)
    || !str_contains($realRoot, 'barama-legacy-media-rehearsal')
    || is_link($realRoot)
    || $realRoot === '/' || str_starts_with($realRoot, '/www/wwwroot/')) {
    fwrite(STDERR, "MEDIA_AUDIT_DENIED=ISOLATED_CLONE_REQUIRED\n");
    exit(3);
}
$sourceUser = (string) getenv('BARAMA_SOURCE_USER');
$sourcePassword = getenv('BARAMA_SOURCE_PASSWORD');
if ($sourceUser === '' || $sourcePassword === false) {
    fwrite(STDERR, "MEDIA_AUDIT_DENIED=READ_ONLY_DB_CREDENTIALS_REQUIRED\n");
    exit(3);
}
$stats = [
    'total'=>0,'verified'=>0,'no_source_hash'=>0,
    'missing_file'=>0,'unsafe_path'=>0,'file_symlink'=>0,
    'size_mismatch'=>0,'hash_mismatch'=>0,'hash_invalid'=>0,
    'files_over_safe_hash_limit'=>0,'unsupported_disk'=>0,
];
try {
    $db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('BARAMA_SOURCE_HOST'),(int)(getenv('BARAMA_SOURCE_PORT') ?: 3306),$dbName),
        $sourceUser,(string)$sourcePassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $dbName) {
        throw new RuntimeException('Wrong database');
    }

    $rows = $db->query('SELECT disk,path,size_bytes,checksum_sha256 FROM media_assets ORDER BY id');
    while (($media = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
        $stats['total']++;
        if ($stats['total'] > 100000) {
            $stats['files_over_safe_hash_limit']++;
            break;
        }
        $disk = (string) $media['disk'];
        if (!in_array($disk, ['public','local','private'], true)) {
            $stats['unsupported_disk']++;
            continue;
        }
        $path = str_replace('\\','/', (string) $media['path']);
        $segments = explode('/',$path);
        if ($path === '' || strlen($path) > 500 || str_starts_with($path,'/')
            || preg_match('/^[a-zA-Z]:/', $path)
            || count(array_intersect($segments,['','.','..']))>0) {
            $stats['unsafe_path']++;
            continue;
        }

        $filename = $realRoot.'/'.$path;
        $resolved = realpath($filename);
        if ($resolved === false || !is_file($resolved)) {
            $stats['missing_file']++;
            continue;
        }
        if (!str_starts_with($resolved, $realRoot.'/')) {
            $stats['unsafe_path']++;
            continue;
        }
        $cursor = $realRoot;
        $symlink = false;
        foreach ($segments as $segment) {
            $cursor .= '/'.$segment;
            if (is_link($cursor)) {
                $symlink = true;
                break;
            }
        }
        if ($symlink) {
            $stats['file_symlink']++;
            continue;
        }

        $size = filesize($resolved);
        if ($size === false || $size !== (int) $media['size_bytes']) {
            $stats['size_mismatch']++;
            continue;
        }
        if ($size > 2*1024*1024*1024) {
            $stats['files_over_safe_hash_limit']++;
            continue;
        }
        $expected = trim((string)($media['checksum_sha256'] ?? ''));
        if ($expected === '') {
            $stats['no_source_hash']++;
            continue;
        }
        if (preg_match('/^[0-9a-fA-F]{64}$/D', $expected)!==1) {
            $stats['hash_invalid']++;
            continue;
        }
        if (!hash_equals(strtolower($expected),hash_file('sha256',$resolved))) {
            $stats['hash_mismatch']++;
            continue;
        }
        $stats['verified']++;
    }
    $blocking = array_sum($stats)-$stats['total']-$stats['verified'] > 0
        || $stats['verified']!==$stats['total'];
    echo json_encode([
        'audit'=>'legacy-private-media-v1','mode'=>'READ_ONLY',
        'source'=>'isolated_legacy_media_copy','media_asset_counts'=>$stats,
        'media_original_paths_printed'=>false,'media_copied'=>false,
        'gate'=>$blocking?'BLOCKED':'HASH_AND_SIZE_VERIFIED',
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    exit($blocking?2:0);
} catch(Throwable $e) {
    fwrite(STDERR,"MEDIA_AUDIT_FAILED=PRIVATE_DB_OR_FILESYSTEM_CHECK\n");
    exit(4);
}
