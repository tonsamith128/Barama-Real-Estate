#!/usr/bin/env bash
set -Eeuo pipefail

# CI-only disposable schema. Never invoke this with production credentials.
if [[ "$CI" != "true" || "$APP_ENV" != "testing" ||
      "$DB_CONNECTION" != "mysql" || "$DB_HOST" != "127.0.0.1" ||
      "$DB_DATABASE" != "barama_billing_upgrade" || "$DB_USERNAME" != "barama" ||
      -z "$MYSQL_ROOT_PASSWORD" ]]; then
    echo "REFUSED: isolated MySQL upgrade test constraints not met"
    exit 3
fi

php -r '
  $pdo=new PDO("mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
               "root", getenv("MYSQL_ROOT_PASSWORD"), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->exec("CREATE DATABASE barama_billing_upgrade CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("GRANT ALL PRIVILEGES ON barama_billing_upgrade.* TO ".$pdo->quote("barama")."@".$pdo->quote("%"));
'

migration="database/migrations/2026_10_31_000010_create_company_billing_profiles.php"
test -f "$migration"
saved="$(mktemp /tmp/barama-billing-migration.XXXXXXXX.php)"
mv "$migration" "$saved"
restore() {
    if [[ ! -e "$migration" && -f "$saved" ]]; then cp "$saved" "$migration"; fi
    rm -f "$saved"
}
trap restore EXIT

php artisan migrate --force --no-interaction
php -r '
  $pdo=new PDO("mysql:host=127.0.0.1;port=3306;dbname=barama_billing_upgrade;charset=utf8mb4",
               getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $stmt=$pdo->prepare("INSERT INTO companies (name, slug, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
  $stmt->execute(["Pre-upgrade company preserved", "qa-preservation-check", "approved"]);
  if ($pdo->query("SHOW TABLES LIKE ".$pdo->quote("company_billing_profiles"))->fetch()) {
      throw new RuntimeException("Forward migration unexpectedly applied early");
  }
'
cp "$saved" "$migration"
php artisan migrate --force --no-interaction

php -r '
  $pdo=new PDO("mysql:host=127.0.0.1;port=3306;dbname=barama_billing_upgrade;charset=utf8mb4",
               getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $count=$pdo->query("SELECT COUNT(*) FROM companies WHERE slug = ".$pdo->quote("qa-preservation-check"))->fetchColumn();
  if ((int)$count!==1 ||
      !$pdo->query("SHOW TABLES LIKE ".$pdo->quote("company_billing_profiles"))->fetch()) {
      throw new RuntimeException("Existing data or new table missing after forward upgrade");
  }
  echo "BILLING_MYSQL_FORWARD_UPGRADE_PRESERVATION_PASS\n";
'
