#!/usr/bin/env bash
# NEVER run against a customer or production database.
# This acceptance script is allowed only on GitHub Actions' isolated MySQL.
set -Eeuo pipefail
set +x
umask 077

if [[ "${GITHUB_ACTIONS:-}" != "true" ]] ||
   [[ "${CI:-}" != "true" ]] ||
   [[ "${APP_ENV:-}" != "testing" ]] ||
   [[ "${DB_CONNECTION:-}" != "mysql" ]] ||
   [[ "${DB_HOST:-}" != "127.0.0.1" ]] ||
   [[ "${DB_DATABASE:-}" != "barama_company_qa" ]] ||
   [[ "${DB_USERNAME:-}" != "barama_ci" ]]; then
    echo "DENIED: This recovery rehearsal is GitHub CI MySQL-only."
    exit 3
fi

for program in php mysql mysqldump; do
    command -v "$program" >/dev/null || { echo "REQUIRED_CI_TOOL_MISSING=$program"; exit 4; }
done

QA="$(mktemp -d /tmp/barama-company-mysql-rehearsal.XXXXXXXX)"
trap 'rm -rf -- "$QA"' EXIT
export MYSQL_PWD="$DB_PASSWORD"

mysqlargs=(--host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" "$DB_DATABASE")
echo "MYSQL_REHEARSAL_DATABASE=barama_company_qa"
echo "DATABASE_HOST=127.0.0.1"
mysql "${mysqlargs[@]}" --execute="SELECT VERSION() AS isolated_mysql_version"

echo "========== INITIALIZE PRE-FEATURE LARAVEL SCHEMA =========="
while IFS= read -r migration; do
    php artisan migrate --path="$migration" --force --no-interaction --quiet
done < <(find database/migrations -maxdepth 1 -type f -name '*.php' \
    ! -name '2026_11_10_*' | LC_ALL=C sort)

echo "========== SEED EXISTING CUSTOMER-LIKE RECORDS =========="
php <<'PHP'
<?php
declare(strict_types=1);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'barama_company_qa') { throw new RuntimeException('Wrong database'); }

$db->prepare("INSERT INTO users (name,email,email_verified_at,password,system_role,created_at,updated_at)
VALUES ('QA Owner','qa-owner@barama.invalid',NOW(),:password,'user',NOW(),NOW())")
   ->execute(['password' => password_hash('test-only-password', PASSWORD_BCRYPT)]);
$owner = (int) $db->lastInsertId();

$db->prepare("INSERT INTO account_notifications (user_id,title,message,type,created_at,updated_at)
VALUES (:id,'Existing notification','Must survive upgrade and rollback','info',NOW(),NOW())")
   ->execute(['id' => $owner]);

$db->exec("INSERT INTO companies (name,slug,company_type,status,published_at,created_at,updated_at)
VALUES ('Existing Approved Company','qa-existing-company','developer','approved',NOW(),NOW(),NOW())");
$company = (int) $db->lastInsertId();
$db->prepare("INSERT INTO media_assets (path,original_name,mime_type,size_bytes,checksum_sha256,usage_rights,created_at,updated_at)
VALUES ('private/media/qa-media.png','qa-media.png','image/png',512,:sha,'owned',NOW(),NOW())")
  ->execute(['sha' => str_repeat('f',64)]);
echo "PRE_FEATURE_DATA_CREATED=YES\n";
PHP

echo "========== APPLY ADDITIVE WORKFLOW MIGRATIONS =========="
php artisan migrate --force --no-interaction --quiet

php <<'PHP'
<?php
declare(strict_types=1);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = [
  'new_capability_grants' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='user_capability_grants'",
  'new_company_media_revisions' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='company_media_revisions'",
  'new_catalog_revisions' => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='catalog_revisions'",
  'project_completed_at' => "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='projects' AND column_name='completed_at'",
  'property_land_area' => "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='properties' AND column_name='land_area_value'",
  'listing_deposit' => "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='listings' AND column_name='deposit_amount'",
  'nullable_notification_dedupe' => "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='account_notifications' AND column_name='dedupe_key' AND is_nullable='YES'",
  'old_notifications_retained' => "SELECT COUNT(*) FROM account_notifications WHERE title='Existing notification'",
  'old_company_retained' => "SELECT COUNT(*) FROM companies WHERE slug='qa-existing-company'",
];
foreach ($checks as $name => $query) {
  if ((int) $db->query($query)->fetchColumn() !== 1) {
    throw new RuntimeException("FAILED_".$name);
  }
  echo strtoupper($name)."=PASS\n";
}

$owner = (int) $db->query("SELECT id FROM users WHERE email='qa-owner@barama.invalid'")->fetchColumn();
$company = (int) $db->query("SELECT id FROM companies WHERE slug='qa-existing-company'")->fetchColumn();
$asset = (int) $db->query("SELECT id FROM media_assets WHERE original_name='qa-media.png'")->fetchColumn();

$db->prepare("INSERT INTO user_capability_grants (user_id,capability,granted_by,created_at,updated_at)
VALUES (:uid,'company.media.review',:granted,NOW(),NOW())")
  ->execute(['uid'=>$owner,'granted'=>$owner]);
$db->prepare("INSERT INTO company_media_revisions
(company_id,media_asset_id,requested_by,collection,status,reason,created_at,updated_at)
VALUES (:company,:media,:owner,'logo','pending','Isolated workflow recovery fixture',NOW(),NOW())")
  ->execute(['company'=>$company,'media'=>$asset,'owner'=>$owner]);
$db->prepare("INSERT INTO account_notifications
(user_id,title,message,type,dedupe_key,created_at,updated_at)
VALUES (:uid,'New review notification','One action','company_pending','ci-transition:1',NOW(),NOW())")
  ->execute(['uid'=>$owner]);

try {
  $db->prepare("INSERT INTO account_notifications
  (user_id,title,message,type,dedupe_key,created_at,updated_at)
  VALUES (:uid,'Duplicate','Wrong','company_pending','ci-transition:1',NOW(),NOW())")
    ->execute(['uid'=>$owner]);
  throw new RuntimeException('DUPLICATE_NOTIFICATION_WAS_ACCEPTED');
} catch (PDOException $exception) {
  if ($exception->getCode() !== '23000') {
    throw $exception;
  }
}
echo "UNIQUE_NOTIFICATION_KEY=PASS\n";
PHP

php <<'PHP'
<?php
declare(strict_types=1);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$company = (int) $db->query("SELECT id FROM companies WHERE slug='qa-existing-company'")->fetchColumn();
$owner = (int) $db->query("SELECT id FROM users WHERE email='qa-owner@barama.invalid'")->fetchColumn();
$db->prepare("INSERT INTO projects
(company_id,name,slug,project_type,status,completed_at,created_at,updated_at)
VALUES (:company,'Existing Project','qa-project','residential','draft','2026-11-01',NOW(),NOW())")
    ->execute(['company'=>$company]);
$project = (int) $db->lastInsertId();
$db->prepare("INSERT INTO properties
(company_id,project_id,reference_code,title,property_type,status,land_area_value,created_at,updated_at)
VALUES (:company,:project,'QA-LEGACY-1','Existing Property','house','draft',150.25,NOW(),NOW())")
    ->execute(['company'=>$company,'project'=>$project]);
$property = (int) $db->lastInsertId();
$db->prepare("INSERT INTO listings
(property_id,slug,transaction_type,status,price,currency,deposit_amount,created_at,updated_at)
VALUES (:property,'qa-listing','rent','draft',500,'USD',1000,NOW(),NOW())")
    ->execute(['property'=>$property]);
$db->prepare("INSERT INTO catalog_revisions
(subject_type,subject_id,revision_number,actor_id,event,before_snapshot,after_snapshot,created_at,updated_at)
VALUES ('property',:property,1,:owner,'created',NULL,:after,NOW(),NOW())")
    ->execute(['property'=>$property,'owner'=>$owner,
      'after'=>json_encode(['reference_code'=>'QA-LEGACY-1','land_area_value'=>150.25], JSON_THROW_ON_ERROR)]);
echo "CATALOG_LEGACY_FIELDS_AND_HISTORY_FIXTURE=PASS\n";
PHP

echo "========== BACKUP UPGRADED ISOLATED DATABASE =========="
mysqldump --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
  --single-transaction --skip-lock-tables --no-tablespaces "$DB_DATABASE" > "$QA/upgrade.sql"
test -s "$QA/upgrade.sql"
echo "SQL_BACKUP=CREATED_PRIVATE_CI_ONLY"

echo "========== ROLLBACK FOUR ADDITIVE MIGRATIONS =========="
php artisan migrate:rollback --step=4 --force --no-interaction --quiet

php <<'PHP'
<?php
declare(strict_types=1);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
  getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
  getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$absent = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_name IN
 ('user_capability_grants','company_media_revisions')")->fetchColumn();
$dedupeAbsent = (int) $db->query("SELECT COUNT(*) FROM information_schema.columns
 WHERE table_schema=DATABASE() AND table_name='account_notifications' AND column_name='dedupe_key'")->fetchColumn();
$catalogAbsent = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_name='catalog_revisions'")->fetchColumn();
$legacyFieldAbsent = (int) $db->query("SELECT COUNT(*) FROM information_schema.columns
 WHERE table_schema=DATABASE() AND table_name='properties' AND column_name='land_area_value'")->fetchColumn();
$original = (int) $db->query("SELECT COUNT(*) FROM account_notifications WHERE title='Existing notification'")->fetchColumn();
if ($absent !== 0 || $dedupeAbsent !== 0 || $catalogAbsent !== 0
    || $legacyFieldAbsent !== 0 || $original !== 1) {
    throw new RuntimeException('MIGRATION_ROLLBACK_FAILED');
}
echo "ADDITIVE_MIGRATIONS_ROLLBACK=PASS\n";
echo "PREEXISTING_NOTIFICATION_AFTER_ROLLBACK=PASS\n";
PHP

echo "========== REAPPLY AND VERIFY SQL RESTORE =========="
php artisan migrate --force --no-interaction --quiet
mysql "${mysqlargs[@]}" --execute="UPDATE companies SET name='TEMP QA ALTERED' WHERE slug='qa-existing-company'"
mysql "${mysqlargs[@]}" < "$QA/upgrade.sql"

php <<'PHP'
<?php
declare(strict_types=1);
$db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = [
 'restored_company' => "SELECT COUNT(*) FROM companies WHERE slug='qa-existing-company' AND name='Existing Approved Company'",
 'restored_original_notice' => "SELECT COUNT(*) FROM account_notifications WHERE title='Existing notification'",
 'restored_deduped_notice' => "SELECT COUNT(*) FROM account_notifications WHERE dedupe_key='ci-transition:1'",
 'restored_pending_revision' => "SELECT COUNT(*) FROM company_media_revisions WHERE status='pending'",
 'restored_capability_grant' => "SELECT COUNT(*) FROM user_capability_grants WHERE capability='company.media.review'",
 'restored_catalog_revision' => "SELECT COUNT(*) FROM catalog_revisions WHERE subject_type='property' AND revision_number=1",
 'restored_project_date' => "SELECT COUNT(*) FROM projects WHERE slug='qa-project' AND completed_at='2026-11-01'",
 'restored_property_land_area' => "SELECT COUNT(*) FROM properties WHERE reference_code='QA-LEGACY-1' AND land_area_value=150.25",
 'restored_listing_deposit' => "SELECT COUNT(*) FROM listings WHERE slug='qa-listing' AND deposit_amount=1000",
];
foreach ($checks as $name => $query) {
 if ((int) $db->query($query)->fetchColumn() !== 1) {
   throw new RuntimeException('FAILED_'.$name);
 }
 echo strtoupper($name)."=PASS\n";
}
echo "ISOLATED_MYSQL_RECOVERY_REHEARSAL=PASS\n";
PHP
