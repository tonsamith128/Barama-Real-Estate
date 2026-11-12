#!/usr/bin/env bash
# CI-only, synthetic two-restore test. No production DB or user data.
set -Eeuo pipefail
set +x
umask 077

if [[ "$GITHUB_ACTIONS" != true || "$CI" != true ||
      "$APP_ENV" != testing ||
      "$DB_HOST" != 127.0.0.1 ||
      "$DB_DATABASE" != barama_legacy_qa ||
      "$DB_USERNAME" != barama_legacy_ci ||
      -z "$DB_PASSWORD" ||
      -z "$CI_MYSQL_ROOT_PASSWORD" ]]; then
    echo "DENIED=GITHUB_DISPOSABLE_ARCHIVED_SCHEMA_ONLY"
    exit 3
fi
for tool in mysql mysqldump php; do command -v "$tool" >/dev/null; done

A="barama_legacy_rehearsal_qafirst24"
B="barama_legacy_rehearsal_qasecond24"
QA="$(mktemp -d /tmp/barama-sensitive-restore.XXXXXXXX)"
root_mysql() { MYSQL_PWD="$CI_MYSQL_ROOT_PASSWORD" mysql -h 127.0.0.1 -uroot "$@"; }
cleanup() {
    root_mysql -e "DROP DATABASE IF EXISTS $A; DROP DATABASE IF EXISTS $B" >/dev/null 2>&1 || :
    rm -rf -- "$QA"
}
trap cleanup EXIT

echo "========== TWO SYNTHETIC BACKUP RESTORES =========="
root_mysql -e "CREATE DATABASE $A CHARACTER SET utf8mb4; CREATE DATABASE $B CHARACTER SET utf8mb4;"
MYSQL_PWD="$DB_PASSWORD" mysqldump -h 127.0.0.1 -u "$DB_USERNAME" \
    --single-transaction --skip-lock-tables --no-tablespaces \
    --set-gtid-purged=OFF "$DB_DATABASE" > "$QA/legacy-fixture.sql"
test -s "$QA/legacy-fixture.sql"
root_mysql "$A" < "$QA/legacy-fixture.sql"
root_mysql "$B" < "$QA/legacy-fixture.sql"

# Synthetic tables exercise archived finance/security comparison without
# inventing real customer records, secrets or active payment credentials.
for schema in "$A" "$B"; do
    root_mysql "$schema" <<'SQL'
CREATE TABLE billing_invoices (id BIGINT UNSIGNED PRIMARY KEY, status VARCHAR(24));
CREATE TABLE billing_transactions (id BIGINT UNSIGNED PRIMARY KEY, amount DECIMAL(18,2));
CREATE TABLE user_security_factors (user_id BIGINT UNSIGNED PRIMARY KEY, mfa_enabled TINYINT);
CREATE TABLE security_audit_events (id BIGINT UNSIGNED PRIMARY KEY, category VARCHAR(24));
INSERT INTO billing_invoices VALUES (1,'draft');
INSERT INTO billing_transactions VALUES (1,49.00);
INSERT INTO user_security_factors VALUES (1,0);
INSERT INTO security_audit_events VALUES (1,'synthetic');
SQL
done

# Synthetic ledger fields match pinned historical invoice and security schema.
for schema in "$A" "$B"; do
    root_mysql "$schema" <<'SQL'
ALTER TABLE billing_invoices
  ADD invoice_number VARCHAR(64) NOT NULL DEFAULT 'QA-INVOICE-1',
  ADD company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  ADD subscription_id BIGINT UNSIGNED NULL,
  ADD currency CHAR(3) NOT NULL DEFAULT 'USD',
  ADD subtotal DECIMAL(18,2) NOT NULL DEFAULT 49.00,
  ADD discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ADD tax_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ADD total DECIMAL(18,2) NOT NULL DEFAULT 49.00;
CREATE TABLE billing_invoice_items (
  id BIGINT UNSIGNED PRIMARY KEY, invoice_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,2), unit_amount DECIMAL(18,2), line_total DECIMAL(18,2)
);
INSERT INTO billing_invoice_items VALUES (1,1,1.00,49.00,49.00);
ALTER TABLE billing_transactions
  ADD invoice_id BIGINT UNSIGNED NULL DEFAULT 1,
  ADD company_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  ADD currency CHAR(3) NOT NULL DEFAULT 'USD',
  ADD status VARCHAR(24) NOT NULL DEFAULT 'completed';
ALTER TABLE user_security_factors
  ADD change_passcode_enabled TINYINT NOT NULL DEFAULT 0;
CREATE TABLE subscription_plans (id BIGINT UNSIGNED PRIMARY KEY);
CREATE TABLE company_subscriptions (
  id BIGINT UNSIGNED PRIMARY KEY, company_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL, status VARCHAR(24) NOT NULL
);
INSERT INTO subscription_plans VALUES (1);
INSERT INTO company_subscriptions VALUES (1,1,1,'inactive');
SQL
done

root_mysql -e "GRANT SELECT ON $A.* TO 'barama_legacy_ci'@'%';
 GRANT SELECT ON $B.* TO 'barama_legacy_ci'@'%';"

export BARAMA_REHEARSAL_APPROVED="TWO_DISPOSABLE_CLONES_VERIFIED"
export BARAMA_RESTORED_OFFLINE_VERIFIED=yes
export BARAMA_SOURCE_HOST=127.0.0.1 BARAMA_SOURCE_DB="$A"
export BARAMA_SOURCE_USER="$DB_USERNAME" BARAMA_SOURCE_PASSWORD="$DB_PASSWORD"
export BARAMA_RESTORED_HOST=127.0.0.1 BARAMA_RESTORED_DB="$B"
export BARAMA_RESTORED_USER="$DB_USERNAME" BARAMA_RESTORED_PASSWORD="$DB_PASSWORD"

php support/barama_sensitive_restore_compare.php --compare-restores > "$QA/match.json"
php -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(($j["backup_consistency"]??"")!=="TWO_RESTORES_MATCH"
 || ($j["categories"]["financial"]["billing_transactions"]["original_rows"]??0)!==1
 || ($j["categories"]["accounts_and_security"]["user_security_factors"]["state"]??"")!=="MATCH"
 || ($j["secrets_or_financial_records_migrated"]??null)!==false
 || ($j["production_release"]??"")!=="BLOCKED_PENDING_OPERATIONAL_DATA_ADAPTERS")
 {exit(1);}
echo "SENSITIVE_TWO_RESTORE_BASELINE=PASS\n";' "$QA/match.json"


export BARAMA_SENSITIVE_RESTORE_VERIFIED=TWO_RESTORES_MATCH
export BARAMA_RELEASE_REVIEW_APPROVED=PRIVATE_READ_ONLY_ONLY

php support/barama_legacy_finance_security_audit.php --audit > "$QA/finsec-baseline.json"
php -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(($j["semantic_integrity"]??"")!=="CHECKED_NO_KNOWN_ANOMALIES"
  || ($j["two_restore_aggregate_consistency"]??"")!=="MATCH"
  || ($j["historical_review_queue_counts"]["historical_privileged_role_assignments"]??0)<1
  || ($j["sensitive_values_or_amounts_printed"]??null)!==false
  || ($j["database_or_account_mutated"]??null)!==false
  || ($j["legacy_credentials_or_roles_activated"]??null)!==false
  || ($j["payments_or_notifications_activated"]??null)!==false
  || ($j["release"]??null)!=="BLOCKED") { exit(1); }
echo "FINSEC_READONLY_SEMANTIC_BASELINE=PASS\n";
' "$QA/finsec-baseline.json"

# Amount tampering in one restore must block arithmetic and aggregated sums.
root_mysql "$B" -e "UPDATE billing_invoices SET total=48.00 WHERE id=1"
if php support/barama_legacy_finance_security_audit.php --audit \
  > "$QA/finsec-tamper.json"; then
    echo "FAILED=FINANCIAL_AMOUNT_TAMPER_ACCEPTED"; exit 9
fi
php -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(($j["two_restore_aggregate_consistency"]??"")!=="BLOCKED"
 || ($j["release"]??"")!=="BLOCKED") {exit(1);}
echo "FINSEC_INVOICE_TAMPER_BLOCKED=PASS\n";
' "$QA/finsec-tamper.json"
root_mysql "$B" -e "UPDATE billing_invoices SET total=49.00 WHERE id=1"

# Divergence in MFA history must also block; nothing is enrolled in Laravel.
root_mysql "$B" -e "UPDATE user_security_factors SET mfa_enabled=1 WHERE user_id=1"
if php support/barama_legacy_finance_security_audit.php --audit \
  > "$QA/finsec-mfa-tamper.json"; then
    echo "FAILED=FINSEC_PRIVILEGE_DRIFT_ACCEPTED"; exit 9
fi
php -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(($j["two_restore_aggregate_consistency"]??"")!=="BLOCKED"
 || ($j["release"]??"")!=="BLOCKED") {exit(1);}
echo "FINSEC_MFA_DRIFT_BLOCKED=PASS\n";
' "$QA/finsec-mfa-tamper.json"
root_mysql "$B" -e "UPDATE user_security_factors SET mfa_enabled=0 WHERE user_id=1"

if APP_ENV=production php support/barama_legacy_finance_security_audit.php \
    --audit > "$QA/finsec-denied.log" 2>&1; then
    echo "FAILED=PRODUCTION_FINSEC_REVIEW_ACCEPTED"; exit 9
fi
grep -q 'FINSEC_AUDIT_DENIED=OFFLINE_TWO_RESTORE_APPROVAL_REQUIRED' \
    "$QA/finsec-denied.log"
echo "FINSEC_PRODUCTION_GUARD=PASS"

root_mysql "$B" -e "UPDATE users SET name=CONCAT(name,' ALTERED') WHERE id=1"
if php support/barama_sensitive_restore_compare.php --compare-restores > "$QA/tamper.json" 2>&1; then
    echo "FAILED=MODIFIED_FINANCIAL_SECURITY_RESTORE_ACCEPTED"; exit 9
fi
php -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!in_array("users",$j["mismatch_table_names"]??[],true)
 || ($j["backup_consistency"]??"")!=="BLOCKED")
 {exit(1);}
echo "SENSITIVE_TWO_RESTORE_TAMPER_DENIAL=PASS\n";' "$QA/tamper.json"

if APP_ENV=production php support/barama_sensitive_restore_compare.php \
  --compare-restores > "$QA/prod-denied.txt" 2>&1; then
    echo "FAILED=PRODUCTION_ACCEPTED"; exit 9
fi
grep -q 'RESTORE_DENIED=PRIVATE_TESTING_ONLY' "$QA/prod-denied.txt"
echo "SENSITIVE_TWO_RESTORE_PRODUCTION_DENIAL=PASS"
echo "ALL_TESTS_USE_SYNTHETIC_CUSTOMER_DATA=YES"
