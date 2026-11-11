#!/usr/bin/env bash
# Synthetic two-database import; denied outside a known GitHub CI database.
set -Eeuo pipefail
set +x
umask 077
if [[ "$GITHUB_ACTIONS" != true || "$CI" != true ||
      "$DB_DATABASE" != barama_legacy_qa ||
      "$DB_USERNAME" != barama_legacy_ci ||
      "$DB_HOST" != 127.0.0.1 ||
      "$APP_ENV" != testing ||
      "$CI_MYSQL_ROOT_PASSWORD" != ci_only_legacy_root ]]; then
    echo "DENIED=GITHUB_CI_FIXTURE_ONLY"
    exit 3
fi
for prog in php mysql mysqldump composer; do
    command -v "$prog" >/dev/null || { echo "CI_MISSING_DEPENDENCY=$prog"; exit 4; }
done

SOURCE=barama_legacy_qa_fixture
TARGET=barama_laravel_qa_fixture
QA="$(mktemp -d /tmp/barama-clone-stage-ci.XXXXXXXX)"
SRC_MEDIA="$(mktemp -d /tmp/barama-legacy-media-rehearsal-ci.XXXXXXXX)"
DST_MEDIA="$(mktemp -d /tmp/barama-laravel-media-rehearsal-ci.XXXXXXXX)"
trap 'rm -rf -- "$QA" "$SRC_MEDIA" "$DST_MEDIA"' EXIT

echo "========== CREATE TWO UNRELATED DISPOSABLE DATABASES =========="
MYSQL_PWD="$CI_MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot <<'SQL'
CREATE DATABASE barama_legacy_qa_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE barama_laravel_qa_fixture CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON barama_legacy_qa_fixture.* TO 'barama_legacy_ci'@'%';
GRANT ALL PRIVILEGES ON barama_laravel_qa_fixture.* TO 'barama_legacy_ci'@'%';
SQL
MYSQL_PWD="$DB_PASSWORD" mysqldump -h 127.0.0.1 -u "$DB_USERNAME" \
    --single-transaction --skip-lock-tables --no-tablespaces "$DB_DATABASE" > "$QA/legacy.sql"
test -s "$QA/legacy.sql"
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$SOURCE" < "$QA/legacy.sql"

echo "========== CREATE VERIFIED SYNTHETIC SOURCE PHOTO =========="
export BARAMA_MEDIA_CLONE_ROOT="$SRC_MEDIA"
mkdir -p "$SRC_MEDIA/uploads"
export BARAMA_SOURCE_HOST=127.0.0.1
export BARAMA_SOURCE_PORT=3306
export BARAMA_SOURCE_DB="$SOURCE"
export BARAMA_SOURCE_USER="$DB_USERNAME"
export BARAMA_SOURCE_PASSWORD="$DB_PASSWORD"
export BARAMA_TARGET_HOST=127.0.0.1
export BARAMA_TARGET_PORT=3306
export BARAMA_TARGET_DB="$TARGET"
export BARAMA_TARGET_USER="$DB_USERNAME"
export BARAMA_TARGET_PASSWORD="$DB_PASSWORD"
export BARAMA_TARGET_MEDIA_ROOT="$DST_MEDIA"
export BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED
export BARAMA_STAGE_APPROVED=WRITE_ONLY_FRESH_DISPOSABLE_LARAVEL_CLONE
export BARAMA_MAIL_DISABLED=yes
export BARAMA_TARGET_OFFLINE_VERIFIED=yes

php <<'PHP'
<?php
declare(strict_types=1);
$png=base64_decode(
 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=',
 true);
$path=getenv('BARAMA_MEDIA_CLONE_ROOT').'/uploads/qa-picture.png';
file_put_contents($path,$png);
$sha=hash_file('sha256',$path);
$db=new PDO('mysql:host=127.0.0.1;dbname='.getenv('BARAMA_SOURCE_DB').';charset=utf8mb4',
    getenv('BARAMA_SOURCE_USER'),getenv('BARAMA_SOURCE_PASSWORD'),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->prepare("INSERT INTO media_assets
(uuid,disk,path,original_name,mime_type,size_bytes,checksum_sha256)
VALUES ('00000000-0000-4000-8000-000000000010','public','uploads/qa-picture.png',
'qa-picture.png','image/png',:size,:sha)")
  ->execute(['size'=>filesize($path),'sha'=>$sha]);
$id=(int)$db->lastInsertId();
$listing=(int)$db->query("SELECT id FROM listings WHERE slug='qa-offer-sale'")->fetchColumn();
$db->prepare("INSERT INTO media_attachments
(media_asset_id,entity_type,entity_id,collection,position,is_primary)
VALUES (:media,'listing',:listing,'gallery',0,1)")
  ->execute(['media'=>$id,'listing'=>$listing]);
echo "SYNTHETIC_MEDIA_FIXTURE=PASS\n";
PHP

echo "========== INSTALL LARAVEL SCHEMA ON EMPTY TARGET =========="
composer install --no-interaction --prefer-dist --no-progress > "$QA/composer.log" 2>&1
export DB_DATABASE="$TARGET"
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export APP_DEBUG=false
export DB_CONNECTION=mysql
export CACHE_STORE=array
export SESSION_DRIVER=array
php artisan migrate --force --no-interaction --quiet
test "$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h 127.0.0.1 -u "$DB_USERNAME" \
    "$TARGET" -e "SELECT COUNT(*) FROM users")" = 0

echo "========== READ-ONLY SOURCE AND MEDIA INTEGRITY =========="
php -l support/barama_isolated_clone_inventory.php >/dev/null
php -l support/barama_isolated_media_inventory.php >/dev/null
php -l support/barama_isolated_clone_stage.php >/dev/null
php -l support/BaramaLegacyCloneStager.php >/dev/null
php support/barama_isolated_clone_inventory.php > "$QA/inventory.json"
php support/barama_isolated_media_inventory.php > "$QA/media.json"
php support/barama_isolated_clone_stage.php --plan > "$QA/plan.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
 if(($j["legacy_record_counts"]["saved_listings"]??0)!==1
 || ($j["unmapped_legacy_data_counts"]["user_roles"]??0)!==1
 || ($j["unmapped_legacy_data_counts"]["leads"]??0)!==1
 || array_key_exists("saved_listings",$j["unmapped_legacy_data_counts"])
 || ($j["release_gate"]??"")!=="BLOCKED_UNTIL_INDEPENDENT_RECONCILIATION")
 {exit(1);}
 echo "FULL_LEGACY_TABLE_INVENTORY_AND_CUSTOMER_BLOCKERS=PASS
";' "$QA/plan.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
 if($j["gate"]!=="STRUCTURAL_PREFLIGHT_PASS"){exit(1);}
 echo "TWO_CLONE_STRUCTURAL_INTEGRITY=PASS\n";' "$QA/inventory.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
 if($j["gate"]!=="HASH_AND_SIZE_VERIFIED"||$j["media_asset_counts"]["verified"]!==1){exit(1);}
 echo "SOURCE_PRIVATE_MEDIA_SHA256=PASS\n";' "$QA/media.json"

echo "========== NEGATIVE ENVIRONMENT AND PERMISSION GATES =========="
if APP_ENV=production php support/barama_isolated_clone_stage.php --stage > "$QA/not-testing.txt" 2>&1; then
    echo "FAILED=PRODUCTION_ENV_ACCEPTED"; exit 9
fi
if BARAMA_MAIL_DISABLED=no php support/barama_isolated_clone_stage.php --stage > "$QA/mail-enabled.txt" 2>&1; then
    echo "FAILED=EXTERNAL_EMAIL_RISK_ACCEPTED"; exit 9
fi
if BARAMA_SOURCE_DB=barama_live_production php support/barama_isolated_clone_stage.php --stage > "$QA/live-db-name.txt" 2>&1; then
    echo "FAILED=LIVE_DATABASE_NAME_ACCEPTED"; exit 9
fi
if BARAMA_TARGET_OFFLINE_VERIFIED=no php support/barama_isolated_clone_stage.php --stage > "$QA/online-target.txt" 2>&1; then
    echo "FAILED=ONLINE_TARGET_ACCEPTED"; exit 9
fi
echo "PRODUCTION_AND_EXTERNAL_SIDE_EFFECT_GATES=PASS"

echo "========== NEGATIVE SOURCE RELATIONSHIP AND MISSING PRIVATE FILE =========="
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$SOURCE"     -e "UPDATE properties SET company_id=NULL WHERE reference_code='QA-PROPERTY-1'"
if php support/barama_isolated_clone_stage.php --stage > "$QA/unassigned-property.txt" 2>&1; then
    echo "FAILED=UNASSIGNED_LEGACY_PROPERTY_ACCEPTED"; exit 9
fi
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$SOURCE"     -e "UPDATE properties SET company_id=(SELECT id FROM companies WHERE slug='qa-synthetic-realty') WHERE reference_code='QA-PROPERTY-1'"
echo "INVALID_OWNER_SOURCE_DENIED=PASS"

mv -- "$SRC_MEDIA/uploads/qa-picture.png" "$QA/temporarily-missing-picture.png"
if php support/barama_isolated_clone_stage.php --stage > "$QA/missing-picture.txt" 2>&1; then
    echo "FAILED=MISSING_PRIVATE_IMAGE_ACCEPTED"; exit 9
fi
mv -- "$QA/temporarily-missing-picture.png" "$SRC_MEDIA/uploads/qa-picture.png"
test "$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h 127.0.0.1 -u "$DB_USERNAME"     "$TARGET" -e "SELECT COUNT(*) FROM users")" = 0
test "$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h 127.0.0.1 -u "$DB_USERNAME"     "$TARGET" -e "SELECT COUNT(*) FROM legacy_import_batches")" = 0
echo "MISSING_MEDIA_ROLLBACK_KEEPS_TARGET_EMPTY=PASS"

echo "========== STAGE INTO DISPOSABLE TARGET ONLY =========="
php support/barama_isolated_clone_stage.php --stage > "$QA/staged.json"
php support/barama_isolated_clone_reconcile.php > "$QA/reconciled.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
 if(($j["integrity_status"]??"")!=="MAPPED_CORE_VERIFIED"
 || ($j["production_release_status"]??"")==="RELEASE_READY"
 || ($j["unconverted_nonempty_source_tables"]["leads"]??0)!==1
 || ($j["mapped_core_counts"]["saved_listings"]["target"]??0)!==1
 || ($j["mapped_core_counts"]["company_members"]["independent_role_reviews"]??0)!==1
 || ($j["integrity_blockers"]??[])!==[])
 {exit(1);}
 echo "INDEPENDENT_POST_IMPORT_UUID_RELATIONSHIP_AND_ROLE_AUDIT=PASS
";' "$QA/reconciled.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
 if($j["mode"]!=="DISPOSABLE_TARGET_STAGED"
 || ($j["unmapped_legacy_data_counts"]["leads"]??0)!==1
 || ($j["unmapped_nonempty_table_count"]??0)<2
 || $j["release_gate"]==="RELEASE_READY"){exit(1);}
 echo "LEGACY_UUID_AND_ID_STAGING=PASS\n";' "$QA/staged.json"

php <<'PHP'
<?php
declare(strict_types=1);
$db=new PDO('mysql:host=127.0.0.1;dbname='.getenv('BARAMA_TARGET_DB').';charset=utf8mb4',
 getenv('BARAMA_TARGET_USER'),getenv('BARAMA_TARGET_PASSWORD'),
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$checks=[
'unverified_user'=>"SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL AND system_role='user'",
'private_company'=>"SELECT COUNT(*) FROM companies WHERE status='draft' AND published_at IS NULL",
'private_project'=>"SELECT COUNT(*) FROM projects WHERE status='draft' AND published_at IS NULL",
'private_property'=>"SELECT COUNT(*) FROM properties WHERE status='draft'",
'private_sale_rental'=>"SELECT COUNT(*) FROM listings WHERE status='draft' AND published_at IS NULL",
'mapped_legacy_user'=>"SELECT COUNT(*) FROM legacy_identity_maps WHERE source_table='users'",
'mapped_media_asset'=>"SELECT COUNT(*) FROM legacy_identity_maps WHERE source_table='media_assets'",
'private_image'=>"SELECT COUNT(*) FROM media_assets WHERE usage_rights='unreviewed'",
'fractional_property'=>"SELECT COUNT(*) FROM properties WHERE bedrooms=2.5 AND bathrooms=1.5",
'original_published_status_preserved'=>"SELECT COUNT(*) FROM legacy_identity_maps WHERE source_table='listings' AND source_status='published'",
'saved_published_offer_privately_mapped'=>"SELECT COUNT(*) FROM saved_listings s JOIN legacy_identity_maps m ON m.target_id=s.listing_id AND m.source_table='listings' WHERE m.source_status='published'",
'gallery_attachment'=>"SELECT COUNT(*) FROM media_attachments WHERE entity_type='listing' AND collection='gallery' AND is_primary=1",
'saved_listing'=>"SELECT COUNT(*) FROM saved_listings",
'inactive_membership'=>"SELECT COUNT(*) FROM company_user WHERE status='inactive' AND role='member'",
'original_role_review'=>"SELECT COUNT(*) FROM legacy_company_membership_reviews WHERE source_role='owner' AND decision='needs_independent_approval'",
'wrong_membership'=>"SELECT COUNT(*) FROM company_user WHERE status<>'inactive' OR role<>'member'",
'unapproved_roles'=>"SELECT COUNT(*) FROM legacy_company_membership_reviews WHERE decision<>'needs_independent_approval'",
];
foreach($checks as $name=>$sql){
 $value=(int)$db->query($sql)->fetchColumn();
 $expected=$name==='private_sale_rental'?2:
    (in_array($name,['wrong_membership','unapproved_roles'],true)?0:1);
 if($value!==$expected){throw new RuntimeException('QA_FAILED='.$name);}
 echo strtoupper($name)."=PASS\n";
}
$media=$db->query("SELECT path,checksum_sha256 FROM media_assets LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$targetPath=getenv('BARAMA_TARGET_MEDIA_ROOT').'/'.substr($media['path'],8);
if(!is_file($targetPath)||hash_file('sha256',$targetPath)!==$media['checksum_sha256']){
 throw new RuntimeException('PRIVATE_MEDIA_RESTORATION_FAILED');
}
echo "PRIVATE_LARAVEL_MEDIA_COPY=PASS\n";
PHP

echo "========== PROVE INDEPENDENT AUDIT DETECTS TAMPERING =========="
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$TARGET"     -e "UPDATE company_user SET status='active' WHERE status='inactive'"
if php support/barama_isolated_clone_reconcile.php > "$QA/role-escalation.txt" 2>&1; then
    echo "FAILED=ESCALATED_MEMBERSHIP_NOT_DETECTED"; exit 9
fi
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$TARGET"     -e "UPDATE company_user SET status='inactive' WHERE status='active'"
echo "INDEPENDENT_ROLE_ESCALATION_DETECTION=PASS"

MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$TARGET"     -e "UPDATE legacy_identity_maps SET source_uuid='ffffffff-ffff-4fff-8fff-ffffffffffff' WHERE source_table='users'"
if php support/barama_isolated_clone_reconcile.php > "$QA/broken-uuid.txt" 2>&1; then
    echo "FAILED=LEGACY_UUID_TAMPER_NOT_DETECTED"; exit 9
fi
MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u "$DB_USERNAME" "$TARGET"     -e "UPDATE legacy_identity_maps SET source_uuid='00000000-0000-4000-8000-000000000001' WHERE source_table='users'"
php support/barama_isolated_clone_reconcile.php > "$QA/restored-reconciliation.json"
echo "INDEPENDENT_UUID_MAPPING_TAMPER_DETECTION=PASS"

echo "========== PROVE REPLAY AND TAMPERING ARE REJECTED =========="
if php support/barama_isolated_clone_stage.php --stage > "$QA/replay.txt" 2>&1; then
    echo "FAILED=TARGET_REPLAY_WAS_ACCEPTED"; exit 9
fi
echo "NONEMPTY_TARGET_REPLAY_DENIED=PASS"
printf X >> "$SRC_MEDIA/uploads/qa-picture.png"
if php support/barama_isolated_media_inventory.php > "$QA/tamper.txt" 2>&1; then
    echo "FAILED=MEDIA_TAMPER_WAS_ACCEPTED"; exit 9
fi
echo "MEDIA_TAMPERING_DENIED=PASS"
echo "BARAMA_DISPOSABLE_LEGACY_STAGING_ACCEPTANCE=PASS"
