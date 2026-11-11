#!/usr/bin/env bash
# CI-only historical schema acceptance. Do not point at a customer database.
set -Eeuo pipefail
set +x
umask 077

if [[ "${GITHUB_ACTIONS:-}" != "true" ]] ||
   [[ "${CI:-}" != "true" ]] ||
   [[ "${DB_HOST:-}" != "127.0.0.1" ]] ||
   [[ "${DB_DATABASE:-}" != "barama_legacy_qa" ]] ||
   [[ "${DB_USERNAME:-}" != "barama_legacy_ci" ]] ||
   [[ "${APP_ENV:-}" != "testing" ]]; then
    echo "DENIED: disposable GitHub CI legacy database only."
    exit 3
fi

REPO="archive/barama-v1-main-before-laravel-20261030"
EXPECTED="a0af1e5d4d29f38441e3357ae6001b263f1c79cd"

git fetch --quiet --depth=1 origin "$REPO"
test "$(git rev-parse FETCH_HEAD)" = "$EXPECTED" || {
    echo "ARCHIVE_COMMIT_MISMATCH"; exit 5;
}

QA="$(mktemp -d /tmp/barama-archived-schema.XXXXXXXX)"
trap 'rm -rf -- "$QA"' EXIT
export LEGACY_QA_ROOT="$QA"

for version in 0001 0003 0006 0008 0028 0029; do
    path="$(git ls-tree -r --name-only "$EXPECTED" application/database/migrations \
        | grep -E "/${version}_[a-z0-9_]+\\.php$" | head -n 1)"
    test -n "$path" || { echo "MISSING_PINNED_LEGACY_MIGRATION=$version"; exit 4; }
    git show "$EXPECTED:$path" > "$QA/${version}.php"
    php -l "$QA/${version}.php" >/dev/null
done

php <<'PHP'
<?php
declare(strict_types=1);

$dir = getenv('LEGACY_QA_ROOT');
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'barama_legacy_qa') {
    throw new RuntimeException('Incorrect legacy QA database');
}
foreach (['0001', '0003', '0006', '0008', '0028', '0029'] as $version) {
    $migration = require $dir.'/'.$version.'.php';
    if (!is_callable($migration)) {
        throw new RuntimeException("Invalid historical schema migration ".$version);
    }
    $migration($db);
}
echo "ARCHIVED_PURE_PHP_SCHEMA_REPLICA=CREATED\n";

$db->exec("INSERT INTO users
(uuid,name,email,password_hash,status,email_verified_at,created_at,updated_at)
VALUES ('00000000-0000-4000-8000-000000000001','Synthetic Owner',
'legacy-fixture@barama.invalid','test-hash-not-a-customer-secret','active',NOW(),NOW(),NOW())");
$owner = (int) $db->lastInsertId();
$db->exec("INSERT INTO locations
(uuid,type,country_code,name,slug,status,sort_order)
VALUES ('00000000-0000-4000-8000-000000000002','country','KH','Cambodia','qa-cambodia','active',0)");
$root = (int) $db->lastInsertId();
$db->prepare("INSERT INTO locations
(uuid,parent_id,type,country_code,name,slug,status)
VALUES ('00000000-0000-4000-8000-000000000003',:parent,'province','KH','QA Battambang','qa-battambang','active')")
    ->execute(['parent' => $root]);
$province = (int) $db->lastInsertId();

$db->prepare("INSERT INTO companies
(uuid,location_id,name,slug,company_type,status,published_at)
VALUES ('00000000-0000-4000-8000-000000000004',:location,'Synthetic Realty','qa-synthetic-realty','developer','approved',NOW())")
    ->execute(['location' => $province]);
$company = (int) $db->lastInsertId();

// A real legacy owner and legacy administrative permission must be reviewed,
// not promoted into privileged Laravel access during conversion.
$db->prepare("INSERT INTO company_members
(company_id,user_id,member_role,status,created_at,updated_at)
VALUES (:company,:owner,'owner','active',NOW(),NOW())")
    ->execute(['company' => $company, 'owner' => $owner]);
$db->exec("INSERT INTO roles(name,slug,is_system,created_at,updated_at)
VALUES ('Legacy Administrator','admin',1,NOW(),NOW())");
$legacyAdminRole=(int)$db->lastInsertId();
$db->prepare("INSERT INTO user_roles(user_id,role_id,created_at)
VALUES (:owner,:role,NOW())")->execute(['owner'=>$owner,'role'=>$legacyAdminRole]);

$db->prepare("INSERT INTO projects
(uuid,company_id,location_id,name,slug,project_type,status,completion_status)
VALUES ('00000000-0000-4000-8000-000000000005',:company,:location,'Synthetic Riverside','qa-synthetic-riverside','residential','published','completed')")
    ->execute(['company' => $company, 'location' => $province]);
$project = (int) $db->lastInsertId();

$db->prepare("INSERT INTO properties
(uuid,company_id,project_id,location_id,reference_code,title,slug,property_type,status,bedrooms,bathrooms)
VALUES ('00000000-0000-4000-8000-000000000006',:company,:project,:location,
'QA-PROPERTY-1','Synthetic Duplex','qa-synthetic-duplex','house','approved',2.5,1.5)")
    ->execute(['company' => $company, 'project' => $project, 'location' => $province]);
$property = (int) $db->lastInsertId();

foreach ([
 ['00000000-0000-4000-8000-000000000007','sale','published','250000'],
 ['00000000-0000-4000-8000-000000000008','rent','draft','1200'],
] as [$uuid, $transaction, $status, $price]) {
    $db->prepare("INSERT INTO listings
(uuid,property_id,slug,transaction_type,status,price,currency,published_at)
VALUES (:uuid,:property,:slug,:transaction,:status,:price,'USD',:published)")
        ->execute([
            'uuid' => $uuid, 'property' => $property,
            'slug' => 'qa-offer-'.$transaction, 'transaction' => $transaction,
            'status' => $status, 'price' => $price,
            'published' => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
}

$sale=(int)$db->query("SELECT id FROM listings WHERE slug='qa-offer-sale'")->fetchColumn();
$db->prepare("INSERT INTO saved_listings(user_id,listing_id,created_at)
VALUES (:owner,:sale,NOW())")->execute(['owner'=>$owner,'sale'=>$sale]);
$db->prepare("INSERT INTO comparison_items(user_id,listing_id,position,created_at)
VALUES (:owner,:sale,0,NOW())")->execute(['owner'=>$owner,'sale'=>$sale]);
$db->prepare("INSERT INTO recent_listing_views
(user_id,listing_id,first_viewed_at,last_viewed_at,view_count)
VALUES (:owner,:sale,'2026-10-01 10:00:00','2026-10-10 10:00:00',4)")
    ->execute(['owner'=>$owner,'sale'=>$sale]);
$db->prepare("INSERT INTO saved_searches
(uuid,user_id,name,query_text,transaction_type,property_type,location_id,
 bedrooms_min,price_min,price_max,currency,alert_frequency,alerts_enabled,last_checked_at,
 created_at,updated_at)
VALUES ('00000000-0000-4000-8000-000000000012',:owner,
'Legacy Battambang Search','Duplex','sale','house',:location,
2.5,150000,300000,'USD','daily',1,'2026-10-15 09:00:00',NOW(),NOW())")
    ->execute(['owner'=>$owner,'location'=>$province]);
$db->prepare("INSERT INTO viewing_requests
(uuid,user_id,listing_id,company_id,preferred_date,preferred_time_window,
alternate_date,alternate_time_window,message,status,company_note,created_at,updated_at)
VALUES ('00000000-0000-4000-8000-000000000013',:owner,:sale,:company,
'2026-10-18','morning','2026-10-20','afternoon','Private synthetic tour request',
'confirmed','Legacy confirmed by company',NOW(),NOW())")
    ->execute(['owner'=>$owner,'sale'=>$sale,'company'=>$company]);

$db->prepare("INSERT INTO leads
(uuid,company_id,contact_name,source,stage,created_at,updated_at)
VALUES ('00000000-0000-4000-8000-000000000011',:company,'Private Synthetic Lead',
'inquiry','new',NOW(),NOW())")->execute(['company'=>$company]);

$db->exec("INSERT INTO amenities
(uuid,name,slug,category,status)
VALUES ('00000000-0000-4000-8000-000000000009','Parking','qa-parking','general','active')");
$amenity = (int) $db->lastInsertId();
$db->prepare("INSERT INTO property_amenities(property_id,amenity_id,created_at)
VALUES (:property,:amenity,NOW())")->execute(['property' => $property, 'amenity' => $amenity]);
$db->prepare("INSERT INTO project_amenities(project_id,amenity_id,created_at)
VALUES (:project,:amenity,NOW())")->execute(['project' => $project, 'amenity' => $amenity]);

$db->prepare("INSERT INTO location_hierarchy_registry
(external_code,country_code,parent_external_code,location_id,source,created_location,created_at)
VALUES ('QA-KH-01','KH',NULL,:location,'isolated-archive',0,NOW())")
    ->execute(['location' => $province]);
$db->exec("INSERT INTO location_data_packs
(country_code,source,source_version,status,record_count,checksum_sha256,installed_at)
VALUES ('KH','isolated-archive','1.0','installed',2,
'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',NOW())");

$checks = [
    'old_country_governance' => "SELECT COUNT(*) FROM location_countries WHERE country_code='KH'",
    'old_geographic_ancestry' => "SELECT COUNT(*) FROM locations WHERE id=$province AND parent_id=$root",
    'old_company_ownership' => "SELECT COUNT(*) FROM companies WHERE id=$company AND location_id=$province",
    'old_project_location' => "SELECT COUNT(*) FROM projects WHERE id=$project AND company_id=$company AND location_id=$province",
    'old_fractional_rooms' => "SELECT COUNT(*) FROM properties WHERE id=$property AND bedrooms=2.5 AND bathrooms=1.5",
    'old_two_offers' => "SELECT COUNT(*) FROM listings WHERE property_id=$property",
    'old_published_sale' => "SELECT COUNT(*) FROM listings WHERE property_id=$property AND transaction_type='sale' AND status='published'",
    'old_draft_rent' => "SELECT COUNT(*) FROM listings WHERE property_id=$property AND transaction_type='rent' AND status='draft'",
    'old_project_amenities' => "SELECT COUNT(*) FROM project_amenities WHERE project_id=$project",
    'old_property_amenities' => "SELECT COUNT(*) FROM property_amenities WHERE property_id=$property",
    'old_external_location_mapping' => "SELECT COUNT(*) FROM location_hierarchy_registry WHERE external_code='QA-KH-01'",
    'old_country_pack' => "SELECT COUNT(*) FROM location_data_packs WHERE country_code='KH'",
    'old_saved_listing' => "SELECT COUNT(*) FROM saved_listings WHERE user_id=$owner AND listing_id=$sale",
    'old_comparison' => "SELECT COUNT(*) FROM comparison_items WHERE user_id=$owner AND listing_id=$sale",
    'old_recent_view' => "SELECT COUNT(*) FROM recent_listing_views WHERE user_id=$owner AND listing_id=$sale AND view_count=4",
    'old_saved_search' => "SELECT COUNT(*) FROM saved_searches WHERE user_id=$owner AND location_id=$province AND bedrooms_min=2.5 AND alerts_enabled=1",
    'old_viewing_request' => "SELECT COUNT(*) FROM viewing_requests WHERE user_id=$owner AND listing_id=$sale AND company_id=$company AND status='confirmed'",
    'old_company_owner_membership' => "SELECT COUNT(*) FROM company_members WHERE company_id=$company AND user_id=$owner AND member_role='owner'",
    'old_admin_role_assignment' => "SELECT COUNT(*) FROM user_roles WHERE user_id=$owner",
    'old_customer_lead' => "SELECT COUNT(*) FROM leads WHERE company_id=$company",
];
foreach ($checks as $name => $query) {
    $count = (int) $db->query($query)->fetchColumn();
    if ($count !== ($name === 'old_two_offers' ? 2 : 1)) {
        throw new RuntimeException("ARCHIVED_SCHEMA_FIXTURE_FAIL=".$name);
    }
    echo strtoupper($name)."=PASS\n";
}
echo "ARCHIVED_PURE_PHP_SCHEMA_SYNTHETIC_ACCEPTANCE=PASS\n";
PHP
