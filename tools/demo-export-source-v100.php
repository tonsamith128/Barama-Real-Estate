<?php
/**
 * BARAMA_DEMO_EXPORT_SOURCE_V100 — local author-only read-only source preparation.
 *
 * Run on the author's actual Barama Live Demo host, never customer websites:
 * BARAMA_DEMO_SOURCE_REVIEW=CONFIRM_PUBLIC_DEMO_ONLY php tools/demo-export-source-v100.php
 *
 * Exports a PRIVATE, UNSIGNED preparation folder. It does not publish to
 * TonSamith.com; source owner must inspect output, sign offline and upload.
 * Private keys, user tables, passwords, payment, CRM and licenses excluded.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || (string)getenv('BARAMA_DEMO_SOURCE_REVIEW') !== 'CONFIRM_PUBLIC_DEMO_ONLY') {
    fwrite(STDERR, "STOP=PUBLIC_DEMO_EXPORT_OWNER_APPROVAL_REQUIRED\n"); exit(1);
}
require dirname(__DIR__) . '/application/bootstrap/runtime.php';
use Barama\Core\Database;
use Barama\Services\CloudObjectStorageService;

$source = (string)(\Barama\Core\Installation::runtimeConfig()['APP_URL'] ?? '');
$host = strtolower((string)parse_url($source, PHP_URL_HOST));
if ($host !== 'baramarealestate.tonsamith.com'
    || (string)getenv('BARAMA_DEMO_EXPORT_UNLOCK') !== 'ALLOW_AUTHOR_DEMO_DATABASE') {
    fwrite(STDERR, "STOP=SOURCE_NOT_OFFICIAL_BARAMA_DEMO\n"); exit(1);
}
$db = Database::connection();
$target = '/root/Barama-Live-Demo-Source-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
if (!mkdir($target, 0700) || !mkdir($target . '/assets', 0700)) {
    throw new RuntimeException('DEMO_PRIVATE_EXPORT_DIRECTORY_FAILED');
}
$tables = [
    'locations', 'amenities', 'companies', 'projects', 'properties',
    'listings', 'cms_pages', 'media_assets', 'project_amenities',
    'property_amenities', 'media_attachments',
];
$export = [
    'schema' => 'barama-demo-content-v1',
    'components' => ['Homepage','Navigation','Footer','Appearance','CMS Pages','Companies',
        'Projects','Properties','Listings','Amenities','Locations','Images'],
    'settings' => [],
    'tables' => [],
];
$settings = ['cms.site_content','cms.footer_content','appearance.frontend'];
$select = $db->prepare('SELECT setting_value FROM application_settings WHERE setting_key=? LIMIT 1');
foreach ($settings as $key) {
    $select->execute([$key]);
    $raw = $select->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') throw new RuntimeException('DEMO_REQUIRED_CONFIGURATION_MISSING:' . $key);
    $config = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($config)) throw new RuntimeException('DEMO_CONFIGURATION_INVALID:' . $key);
    $export['settings'][$key] = $config;
}
$limits = ['locations'=>10000,'amenities'=>2000,'companies'=>500,
    'projects'=>2000,'properties'=>5000,'listings'=>5000,'cms_pages'=>1000,
    'media_assets'=>1000,'project_amenities'=>10000,'property_amenities'=>10000,
    'media_attachments'=>10000];
$where = [
    'companies' => "WHERE status = 'approved' AND published_at IS NOT NULL AND deleted_at IS NULL",
    'projects' => "WHERE status = 'published' AND published_at IS NOT NULL AND deleted_at IS NULL",
    'properties' => "WHERE status = 'approved' AND deleted_at IS NULL",
    'listings' => "WHERE status = 'published' AND published_at IS NOT NULL AND deleted_at IS NULL",
    'cms_pages' => "WHERE status = 'published' AND published_at IS NOT NULL",
];
foreach ($tables as $table) {
    $sql = 'SELECT * FROM ' . chr(96) . $table . chr(96) . ' ' . ($where[$table] ?? '');
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > $limits[$table]) throw new RuntimeException('DEMO_CONTENT_ROW_LIMIT:' . $table);
    foreach ($rows as &$row) {
        // Operator fields are never portable. Installer remaps created_by to the customer's administrator.
        foreach (['created_by_user_id','updated_by_user_id','owner_user_id'] as $field) {
            if (array_key_exists($field, $row)) $row[$field] = null;
        }
        // Do not ship author registration/tax numbers as public demo content.
        if ($table === 'companies') {
            foreach (['registration_number','tax_number'] as $field) {
                if (array_key_exists($field, $row)) $row[$field] = null;
            }
        }
    }
    unset($row);
    $export['tables'][$table] = $rows;
}
$referenced = [];
foreach (['locations','amenities','companies','projects','properties','listings','cms_pages','media_assets'] as $table) {
    $referenced[$table] = array_fill_keys(
        array_map(static fn(array $row): int => (int)$row['id'], $export['tables'][$table]), true
    );
}
// Enforce public-object foreign key closure; do not ship unpublished references.
$export['tables']['projects'] = array_values(array_filter(
    $export['tables']['projects'],
    static fn(array $row): bool => isset($referenced['companies'][(int)$row['company_id']])
));
$referenced['projects'] = array_fill_keys(array_map(
    static fn(array $row): int => (int)$row['id'], $export['tables']['projects']), true);
$export['tables']['properties'] = array_values(array_filter(
    $export['tables']['properties'],
    static fn(array $row): bool =>
        ((!isset($row['company_id']) || isset($referenced['companies'][(int)$row['company_id']])) &&
         (!isset($row['project_id']) || isset($referenced['projects'][(int)$row['project_id']])))
));
$referenced['properties'] = array_fill_keys(array_map(
    static fn(array $row): int => (int)$row['id'], $export['tables']['properties']), true);
$export['tables']['listings'] = array_values(array_filter(
    $export['tables']['listings'],
    static fn(array $row): bool => isset($referenced['properties'][(int)$row['property_id']])
));
$referenced['listings'] = array_fill_keys(array_map(
    static fn(array $row): int => (int)$row['id'], $export['tables']['listings']), true);
foreach ([
    'project_amenities' => ['project_id','projects'],
    'property_amenities' => ['property_id','properties']
] as $table => $parent) {
    $export['tables'][$table] = array_values(array_filter(
        $export['tables'][$table],
        static fn(array $row): bool =>
            isset($referenced[$parent[1]][(int)$row[$parent[0]]]) &&
            isset($referenced['amenities'][(int)$row['amenity_id']])
    ));
}

// Remove attachments whose associated marketplace object is not in this public snapshot.
$relation = [
    'property'=>'properties','project'=>'projects','company'=>'companies',
    'listing'=>'listings','page'=>'cms_pages',
];
$export['tables']['media_attachments'] = array_values(array_filter(
    $export['tables']['media_attachments'],
    static function(array $row) use ($relation,$referenced): bool {
        $kind = (string)($row['entity_type'] ?? '');
        if ($kind === 'site' && (int)$row['entity_id'] === 1) return true;
        $table = $relation[$kind] ?? null;
        return is_string($table) && isset($referenced[$table][(int)$row['entity_id']]);
    }
));
$mediaIds = array_fill_keys(
    array_map(static fn(array $row): int => (int)$row['media_asset_id'], $export['tables']['media_attachments']), true
);
$export['tables']['media_assets'] = array_values(array_filter(
    $export['tables']['media_assets'],
    static fn(array $row): bool => isset($mediaIds[(int)$row['id']])
));
$total = 0;
$mediaPaths = [];
foreach ($export['tables']['media_assets'] as &$row) {
    $mime = strtolower((string)($row['mime_type'] ?? ''));
    $ext = match ($mime) {
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',
        default=>throw new RuntimeException('DEMO_UNSUPPORTED_MEDIA_REQUIRES_REVIEW')
    };
    $disk = (string)($row['disk'] ?? '');
    $original = (string)($row['path'] ?? '');
    if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9/_\.-]{1,250}$~D',$original) ||
        str_contains($original,'..') || str_starts_with($original,'/')) {
        throw new RuntimeException('DEMO_MEDIA_SOURCE_PATH_UNSAFE');
    }
    if ($disk === 'private') {
        $path = APP_PATH . '/storage/uploads/private/' . $original;
        if (!is_file($path) || is_link($path) || filesize($path) > 8388608) {
            throw new RuntimeException('DEMO_MEDIA_SOURCE_MISSING');
        }
        $bytes = file_get_contents($path);
    } else {
        $cloud = new CloudObjectStorageService($disk);
        $result = $cloud->get($cloud->objectKey($original));
        if ((int)($result['status'] ?? 0) !== 200) {
            throw new RuntimeException('DEMO_R2_MEDIA_UNAVAILABLE');
        }
        $bytes = $result['body'] ?? null;
    }
    if (!is_string($bytes) || strlen($bytes) > 8388608 || strlen($bytes) < 100) {
        throw new RuntimeException('DEMO_MEDIA_BYTES_INVALID');
    }
    $info = new finfo(FILEINFO_MIME_TYPE);
    if ($info->buffer($bytes) !== $mime || @getimagesizefromstring($bytes) === false) {
        throw new RuntimeException('DEMO_IMAGE_TYPE_INVALID');
    }
    $sha = hash('sha256',$bytes);
    $new = 'demo/' . $sha . '.' . $ext;
    $asset = $target . '/assets/' . $sha . '.' . $ext;
    if (!is_file($asset)) {
        if (file_put_contents($asset,$bytes,LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('DEMO_ASSET_EXPORT_FAILED');
        }
        chmod($asset,0600);
        $total += strlen($bytes);
    }
    if ($total > 50331648) throw new RuntimeException('DEMO_IMAGES_48MB_LIMIT');
    $row['disk'] = 'private';
    $row['path'] = $new;
    $mediaPaths[$original] = $new;
    $row['checksum_sha256'] = $sha;
    $row['size_bytes'] = strlen($bytes);
    $row['extension'] = $ext;
}
unset($row);

// BARAMA_SIGNED_DEMO_POLICY_CENTER_V114: public-only legal demo preview.
// The publisher owner must review these texts and their jurisdiction before
// signing a commercial starter package. Operator feedback and internal audit
// notes are deliberately excluded from the source export.
$export['policy_center'] = \Barama\Services\DemoPolicySnapshotV1::exportPublic(
    BASE_PATH . '/storage/policy-center/data.json'
);
$export['components'][] = 'Policy Center';
echo "DEMO_PUBLIC_POLICIES=" . count($export['policy_center']['policies']) . "\n";
echo "POLICY_CONTENT_REQUIRES_AUTHOR_LEGAL_REVIEW=YES\n";

// Replace known demo-host absolute URLs with an installation-local placeholder.
// Do not encode publisher/proprietor domains into commercial customer websites.
$replace = static function(mixed $input) use (&$replace, $mediaPaths): mixed {
    if (is_array($input)) {
        foreach ($input as $key => $value) $input[$key] = $replace($value);
        return $input;
    }
    if (!is_string($input)) return $input;
    foreach ($mediaPaths as $oldPath => $newPath) {
        $input = str_replace($oldPath, $newPath, $input);
    }
    return str_replace('https://baramarealestate.tonsamith.com', '{{APP_URL}}', $input);
};
$export['settings'] = $replace($export['settings']);
$export['policy_center'] = $replace($export['policy_center']);
foreach ($export['tables'] as $table => &$rows) {
    foreach ($rows as &$row) {
        if ($table === 'media_assets') continue;
        $row = $replace($row);
    }
    unset($row);
}
unset($rows);

$body = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (strlen($body) > 8388608) throw new RuntimeException('DEMO_CONTENT_8MB_LIMIT');
file_put_contents($target . '/content.json', $body, LOCK_EX);
chmod($target . '/content.json', 0600);
echo "DEMO_AUTHOR_SOURCE_EXPORT=UNSIGNED_REVIEW_ONLY\n";
echo "CONTENT_FILE=" . $target . "/content.json\n";
echo "ASSET_FOLDER=" . $target . "/assets\n";
echo "RECORDS=" . array_sum(array_map('count',$export['tables'])) . "\n";
echo "IMAGE_COUNT=" . count(glob($target.'/assets/*') ?: []) . "\n";
echo "DATABASE_MUTATIONS=NO\n";
echo "PUBLISHER_UPLOAD=NOT_PERFORMED\n";
echo "IMPORTANT=Inspect all data, relationships, image references, and source licenses before signing.\n";
