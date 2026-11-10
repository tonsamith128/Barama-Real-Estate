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

for version in 0001 0003 0008 0028 0029; do
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
foreach (['0001', '0003', '0008', '0028', '0029'] as $version) {
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
