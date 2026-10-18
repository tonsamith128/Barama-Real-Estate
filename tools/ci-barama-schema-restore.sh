#!/usr/bin/env bash
# Phase 4E3: CI-only real Barama migration schema backup/restore.
# Source barama_test is the synthetic GitHub QA database, NEVER customer data.
# Never DROP or mutate source barama_test; target is a fresh disposable schema.
set -Eeo pipefail
set +x
umask 077
stop() { echo "STOP=$1" >&2; exit 1; }
guard() {
 [[ "$GITHUB_ACTIONS" == "true" ]] &&
 [[ "$GITHUB_REPOSITORY" == "tonsamith128/Barama-Real-Estate" ]] &&
 [[ "$GITHUB_JOB" == "foundation-qa" ]] &&
 [[ "$APP_ENV" == "testing" ]] &&
 [[ "$DB_DATABASE" == "barama_test" ]] &&
 [[ "$DB_HOST" == "127.0.0.1" ]] &&
 [[ "$DB_PORT" == "3306" ]] &&
 [[ "$PACKAGE_DB_DATABASE" == "barama_package_test" ]] &&
 [[ "$PACKAGE_DB_USERNAME" == "root" ]] &&
 [[ "$PACKAGE_DB_HOST" == "127.0.0.1" ]] &&
 [[ "$PACKAGE_DB_PORT" == "3306" ]] &&
 [[ "$BARAMA_CI_REAL_SCHEMA_RECOVERY" == "ALLOW_MIGRATED_CI_DATABASE_COPY_ONLY" ]] &&
 [[ -n "$PACKAGE_DB_PASSWORD" ]] &&
 [[ "$MYSQL_SERVICE_CONTAINER_ID" =~ ^[a-f0-9]{12,64}$ ]]
}
case "$1" in
 --guard)
  if guard; then stop CI_GUARD_ACTIVE_USE_CI_MODE; fi
  echo "NON_CI_RUNTIME=REJECTED"
  echo "DATABASE_CONNECTIONS=NO"
  echo "DATABASE_WRITES=NO"
  echo "AUTO_INSTALL=DISABLED"
  exit 0 ;;
 --ci) guard || stop CI_DISPOSABLE_MYSQL_ONLY ;;
 *) stop USAGE_GUARD_OR_CI ;;
esac
command -v docker >/dev/null || stop CI_DOCKER_MISSING
command -v php >/dev/null || stop CI_PHP_MISSING
image="$(docker inspect --format '{{.Config.Image}}' "$MYSQL_SERVICE_CONTAINER_ID" 2>/dev/null)" ||
 stop CI_MYSQL_CONTAINER_MISSING
[[ "$image" == "mysql:8.0" ]] || stop CI_MYSQL_CONTAINER_IMAGE_MISMATCH
mysql_cli() {
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
 mysql --no-defaults -uroot -h127.0.0.1 --batch --skip-column-names \
 --default-character-set=utf8mb4 "$@"
}
mysql_dump() {
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
 mysqldump --no-defaults -uroot -h127.0.0.1 \
 --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
 --hex-blob --triggers --routines --events --skip-lock-tables \
 --default-character-set=utf8mb4 "$1"
}
suffix="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
[[ "$suffix" =~ ^[a-f0-9]{16}$ ]] || stop RANDOM_SUFFIX_INVALID
source_db="barama_test"
target_db="barama_ci4e_real_$suffix"
[[ "$target_db" =~ ^barama_ci4e_real_[a-f0-9]{16}$ ]] || stop TARGET_SCOPE_INVALID
private_dir="$(mktemp -d /tmp/barama-real-schema-ci-XXXXXXXXXX)" ||
 stop CI_PRIVATE_DIR_UNAVAILABLE
chmod 0700 "$private_dir"
dump="$private_dir/migrated-app.sql"
tamper="$private_dir/altered-app.sql"
target_created=0
cleanup() {
 rc=$?
 trap - EXIT
 set +e
 failed=0
 if [[ "$target_created" == 1 ]]; then
  mysql_cli -e "DROP DATABASE $target_db" >/dev/null 2>&1 || failed=1
 fi
 rm -f -- "$dump" "$tamper" || failed=1
 rmdir -- "$private_dir" || failed=1
 if [[ "$failed" == 1 ]]; then
  echo "STOP=DISPOSABLE_MYSQL_CLEANUP_FAILED" >&2
  exit 1
 fi
 if [[ "$rc" == 0 ]]; then echo "DISPOSABLE_RESTORE_SCHEMA_CLEANUP=PASS"; fi
 exit "$rc"
}
trap cleanup EXIT

# Source was populated by the real Barama migrations earlier in this CI job.
migration_count="$(mysql_cli -D "$source_db" -e 'SELECT COUNT(*) FROM schema_migrations')"
[[ "$migration_count" =~ ^[0-9]+$ ]] || stop SOURCE_NOT_MIGRATED
[[ "$migration_count" -ge 10 ]] || stop TOO_FEW_REAL_MIGRATIONS
[[ "$(mysql_cli -D "$source_db" -e 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')" -ge 35 ]] ||
 stop APPLICATION_SCHEMA_MISSING

mysql_dump "$source_db" > "$dump"
chmod 0600 "$dump"
[[ -s "$dump" && ! -L "$dump" ]] || stop MIGRATED_SQL_DUMP_INVALID
[[ "$(stat -c %a "$dump")" == 600 ]] || stop PRIVATE_SQL_MODE_INVALID
[[ "$(stat -c %s "$dump")" -le 16777216 ]] || stop SYNTHETIC_SQL_DUMP_TOO_LARGE
expected_sha="$(sha256sum "$dump" | awk '{print $1}')"
match_sha() {
 [[ -f "$1" && ! -L "$1" ]] &&
 [[ "$(sha256sum "$1" | awk '{print $1}')" == "$expected_sha" ]]
}
match_sha "$dump" || stop MIGRATED_SQL_DIGEST_INVALID
# CREATE only randomly named test target; source 'barama_test' is never dropped.
mysql_cli -e "CREATE DATABASE $target_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
target_created=1
match_sha "$dump" || stop MIGRATED_SQL_CHANGED_BEFORE_IMPORT
mysql_cli -D "$target_db" < "$dump"
match_sha "$dump" || stop MIGRATED_SQL_CHANGED_AFTER_IMPORT

php tools/ci-barama-schema-restore-compare.php --compare "$target_db"
cp -- "$dump" "$tamper"
printf '\n-- modification\n' >> "$tamper"
if match_sha "$tamper"; then stop TAMPERED_MYSQL_DUMP_ACCEPTED; fi
echo "MODIFIED_SQL_COPY_REJECTED=PASS"
echo "REAL_MYSQLDUMP_AND_REIMPORT=PASS"
echo "REAL_BARAMA_MIGRATIONS_RESTORED=PASS"
echo "SOURCE_CI_DATABASE_MUTATED=NO"
echo "CUSTOMER_DATABASE_ACCESSED=NO"
echo "BARAMA_STAGING_PRODUCTION_WRITES=NO"
echo "COORDINATED_CODE_DATABASE_ROLLBACK=NOT_PROVEN"
echo "AUTO_INSTALL=DISABLED"
echo "RESULT=PASS"
