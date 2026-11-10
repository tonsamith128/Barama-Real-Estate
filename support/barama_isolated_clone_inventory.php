<?php

declare(strict_types=1);

/**
 * READ-ONLY aggregate-only inventory of privately restored Pure PHP and
 * Laravel QA databases. Prints no names, emails, UUID values, media paths or
 * credentials. It does not migrate or modify either database.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "AUDIT_DENIED=CLI_ONLY\n");
    exit(3);
}
$variables = ['SOURCE_HOST','SOURCE_DB','SOURCE_USER','SOURCE_PASSWORD',
    'TARGET_HOST','TARGET_DB','TARGET_USER','TARGET_PASSWORD'];
foreach ($variables as $key) {
    if (!is_string(getenv('BARAMA_'.$key)) || getenv('BARAMA_'.$key) === '') {
        fwrite(STDERR, "AUDIT_DENIED=MISSING_CONFIG\n");
        exit(3);
    }
}
$sourceName = (string) getenv('BARAMA_SOURCE_DB');
$targetName = (string) getenv('BARAMA_TARGET_DB');
if (getenv('BARAMA_REHEARSAL_APPROVED') !== 'TWO_DISPOSABLE_CLONES_VERIFIED'
    || preg_match('/^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$/D', $sourceName) !== 1
    || preg_match('/^barama_laravel_(qa|rehearsal)_[a-z0-9_]{4,32}$/D', $targetName) !== 1
    || $sourceName === $targetName
    || !in_array(getenv('BARAMA_SOURCE_HOST'), ['127.0.0.1','localhost'], true)
    || !in_array(getenv('BARAMA_TARGET_HOST'), ['127.0.0.1','localhost'], true)) {
    fwrite(STDERR, "AUDIT_DENIED=CLONE_SAFETY_GATES\n");
    exit(3);
}
function connectClone(string $role): PDO
{
    $db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('BARAMA_'.$role.'_HOST'),
        (int) (getenv('BARAMA_'.$role.'_PORT') ?: 3306),
        getenv('BARAMA_'.$role.'_DB')),
        (string) getenv('BARAMA_'.$role.'_USER'),
        (string) getenv('BARAMA_'.$role.'_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    return $db;
}
function countQuery(PDO $db, string $sql): int
{
    return (int) $db->query($sql)->fetchColumn();
}
function tables(PDO $db): array
{
    return $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);
}
function safeCount(PDO $db, array $available, string $table): int|string
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $table)
        || !in_array($table, $available, true)) {
        return 'MISSING';
    }
    return countQuery($db, 'SELECT COUNT(*) FROM '.$table);
}
try {
    $source = connectClone('SOURCE');
    $target = connectClone('TARGET');
    if ($source->query('SELECT DATABASE()')->fetchColumn() !== $sourceName
        || $target->query('SELECT DATABASE()')->fetchColumn() !== $targetName) {
        throw new RuntimeException('Schema mismatch');
    }

    $sourceTables = tables($source);
    $targetTables = tables($target);
    $legacyTables = [
        'users','roles','permissions','user_roles','role_permissions',
        'locations','location_countries','location_data_packs',
        'location_hierarchy_registry','companies','company_members','projects',
        'properties','listings','amenities','property_amenities',
        'project_amenities','media_assets','media_attachments','inquiries',
        'leads','saved_listings','viewing_requests','entity_status_history',
    ];
    $targetTablesRequired = [
        'users','locations','companies','company_user','projects','properties',
        'listings','amenities','property_amenities','project_amenities',
        'media_assets','media_attachments','inquiries','saved_listings',
        'viewing_requests','moderation_decisions','catalog_revisions',
    ];
    $srcCounts = $dstCounts = [];
    foreach ($legacyTables as $table) {
        $srcCounts[$table] = safeCount($source, $sourceTables, $table);
    }
    foreach ($targetTablesRequired as $table) {
        $dstCounts[$table] = safeCount($target, $targetTables, $table);
    }

    $checks = [
        'duplicate_casefold_emails' => [
            ['users'],
            'SELECT COUNT(*) FROM (SELECT LOWER(TRIM(email)) FROM users GROUP BY LOWER(TRIM(email)) HAVING COUNT(*)>1) grouped',
        ],
        'invalid_emails' => [
            ['users'], "SELECT COUNT(*) FROM users WHERE email IS NULL OR TRIM(email)='' OR CHAR_LENGTH(email)>190",
        ],
        'orphan_member_user' => [
            ['company_members','users'],
            'SELECT COUNT(*) FROM company_members x LEFT JOIN users u ON x.user_id=u.id WHERE u.id IS NULL',
        ],
        'orphan_member_company' => [
            ['company_members','companies'],
            'SELECT COUNT(*) FROM company_members x LEFT JOIN companies c ON x.company_id=c.id WHERE c.id IS NULL',
        ],
        'orphan_project_company' => [
            ['projects','companies'],
            'SELECT COUNT(*) FROM projects x LEFT JOIN companies c ON x.company_id=c.id WHERE c.id IS NULL',
        ],
        'orphan_property_project' => [
            ['properties','projects'],
            'SELECT COUNT(*) FROM properties x LEFT JOIN projects p ON x.project_id=p.id WHERE x.project_id IS NOT NULL AND p.id IS NULL',
        ],
        'cross_company_property_project' => [
            ['properties','projects'],
            'SELECT COUNT(*) FROM properties x JOIN projects p ON x.project_id=p.id WHERE x.company_id IS NOT NULL AND x.company_id<>p.company_id',
        ],
        'orphan_listing_property' => [
            ['listings','properties'],
            'SELECT COUNT(*) FROM listings x LEFT JOIN properties p ON x.property_id=p.id WHERE p.id IS NULL',
        ],
        'orphan_media_asset' => [
            ['media_attachments','media_assets'],
            'SELECT COUNT(*) FROM media_attachments x LEFT JOIN media_assets m ON x.media_asset_id=m.id WHERE m.id IS NULL',
        ],
        'unrecognized_attachment_kind' => [
            ['media_attachments'],
            "SELECT COUNT(*) FROM media_attachments WHERE entity_type NOT IN ('company','project','property','listing')",
        ],
        'orphan_location_parent' => [
            ['locations'],
            'SELECT COUNT(*) FROM locations x LEFT JOIN locations p ON x.parent_id=p.id WHERE x.parent_id IS NOT NULL AND p.id IS NULL',
        ],
        'cross_country_location_parent' => [
            ['locations'],
            'SELECT COUNT(*) FROM locations x JOIN locations p ON x.parent_id=p.id WHERE x.country_code IS NOT NULL AND p.country_code IS NOT NULL AND x.country_code<>p.country_code',
        ],
    ];
    $violations = [];
    foreach ($checks as $name => [$requires,$sql]) {
        $violations[$name] = array_diff($requires, $sourceTables) === []
            ? countQuery($source,$sql) : -1;
    }
    foreach (['users','locations','amenities','companies','projects','properties',
        'listings','media_assets'] as $table) {
        if (!in_array($table,$sourceTables,true)) {
            $violations['uuid_invalid_'.$table] = -1;
            continue;
        }
        $violations['uuid_invalid_'.$table] = countQuery($source,
            "SELECT COUNT(*) FROM ".$table." WHERE uuid IS NULL OR uuid NOT REGEXP
             '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'");
        $violations['uuid_duplicate_'.$table] = countQuery($source,
            'SELECT COUNT(*) FROM (SELECT uuid FROM '.$table.' GROUP BY uuid HAVING COUNT(*)>1) duplicates');
    }
    $blockers = [];
    foreach ($violations as $name=>$value) {
        if ($value !== 0) {
            $blockers[] = $name;
        }
    }
    foreach (['users','locations','companies','company_members','projects',
        'properties','listings','amenities','media_assets','media_attachments'] as $table) {
        if ($srcCounts[$table] === 'MISSING') {
            $blockers[] = 'source_missing_'.$table;
        }
    }
    foreach ($dstCounts as $table=>$value) {
        if ($value === 'MISSING') {
            $blockers[] = 'target_missing_'.$table;
        }
    }

    echo json_encode([
        'audit'=>'barama-clone-inventory-v1','mode'=>'READ_ONLY',
        'source'=>'isolated_legacy_clone','target'=>'isolated_laravel_clone',
        'legacy_counts'=>$srcCounts,'target_counts'=>$dstCounts,
        'integrity_violation_counts'=>$violations,'blockers'=>$blockers,
        'gate'=>$blockers===[]?'STRUCTURAL_PREFLIGHT_PASS':'BLOCKED',
        'personal_data_printed'=>false,'database_changed'=>false,
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES), "\n";
    exit($blockers===[]?0:2);
} catch (Throwable $e) {
    fwrite(STDERR, "AUDIT_FAILED=CONNECTION_OR_SCHEMA_VALIDATION\n");
    exit(4);
}
