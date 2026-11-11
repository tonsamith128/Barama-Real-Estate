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
