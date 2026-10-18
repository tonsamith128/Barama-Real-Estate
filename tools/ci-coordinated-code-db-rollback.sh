#!/usr/bin/env bash
# Phase 4E4: single-process failure-injected code + MySQL rollback.
# Only GitHub CI's disposable MySQL 8 service, no website update.
set -Eeo pipefail
set +x
umask 077
stop() { echo "STOP=$1" >&2; exit 1; }
scope() {
  [[ "$GITHUB_ACTIONS" == true ]] &&
  [[ "$GITHUB_REPOSITORY" == "tonsamith128/Barama-Real-Estate" ]] &&
  [[ "$GITHUB_JOB" == foundation-qa ]] &&
  [[ "$APP_ENV" == testing ]] &&
  [[ "$DB_DATABASE" == barama_test ]] &&
  [[ "$DB_HOST" == 127.0.0.1 ]] &&
  [[ "$DB_PORT" == 3306 ]] &&
  [[ "$PACKAGE_DB_DATABASE" == barama_package_test ]] &&
  [[ "$PACKAGE_DB_USERNAME" == root ]] &&
  [[ "$PACKAGE_DB_HOST" == 127.0.0.1 ]] &&
  [[ "$PACKAGE_DB_PORT" == 3306 ]] &&
  [[ "$BARAMA_CI_COORDINATED_ROLLBACK" == ALLOW_DISPOSABLE_COORDINATED_CI_ONLY ]] &&
  [[ "$BARAMA_CI_REAL_SCHEMA_RECOVERY" == ALLOW_MIGRATED_CI_DATABASE_COPY_ONLY ]] &&
  [[ -n "$PACKAGE_DB_PASSWORD" ]] &&
  [[ "$MYSQL_SERVICE_CONTAINER_ID" =~ ^[a-f0-9]{12,64}$ ]]
}
case "$1" in
  --guard)
    if scope; then stop CI_SCOPE_ACTIVE_USE_CI_MODE; fi
    echo "NON_CI_RUNTIME=REJECTED"
    echo "DATABASE_CONNECTIONS=NO"
    echo "DATABASE_WRITES=NO"
    echo "WEBSITE_WRITES=NO"
    echo "AUTO_INSTALL=DISABLED"
    exit 0 ;;
  --ci) scope || stop CI_DISPOSABLE_SCOPE_REJECTED ;;
  *) stop USAGE_GUARD_OR_CI ;;
esac
command -v docker >/dev/null || stop CI_DOCKER_REQUIRED
command -v php >/dev/null || stop CI_PHP_REQUIRED
[[ "$(docker inspect --format '{{.Config.Image}}' "$MYSQL_SERVICE_CONTAINER_ID" 2>/dev/null)" == "mysql:8.0" ]] ||
  stop CI_MYSQL8_SERVICE_UNTRUSTED
mysql_cli() {
  docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
    mysql --no-defaults -h127.0.0.1 -uroot --batch --skip-column-names \
    --default-character-set=utf8mb4 "$@"
}
mysql_dump() {
  docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
    mysqldump --no-defaults -h127.0.0.1 -uroot \
    --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
    --hex-blob --triggers --routines --events --skip-lock-tables \
    --default-character-set=utf8mb4 "$1"
}
suffix="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
[[ "$suffix" =~ ^[a-f0-9]{16}$ ]] || stop RANDOM_SUFFIX_INVALID
source_db="barama_test"
target_db="barama_ci4e_real_$suffix"
[[ "$target_db" =~ ^barama_ci4e_real_[a-f0-9]{16}$ ]] || stop TARGET_SCHEMA_INVALID
fixture_root="$(mktemp -d /tmp/barama-coordinated-ci-XXXXXXXXXX)" || stop FIXTURE_DIR_UNAVAILABLE
chmod 0700 "$fixture_root"
dump="$fixture_root/baseline.sql"
backup="$fixture_root/code-backup.php"
code="$fixture_root/fixture.php"
newcode="$fixture_root/fixture-new.php"
restored="$fixture_root/fixture-restore.php"
journal="$fixture_root/journal.txt"
tampered="$fixture_root/tampered.sql"
target_created=0
cleanup() {
  rc=$?
  trap - EXIT
  set +e
  failed=0
  if [[ "$target_created" == 1 ]]; then
    mysql_cli -e "DROP DATABASE IF EXISTS $target_db" >/dev/null 2>&1 || failed=1
  fi
  for f in "$dump" "$backup" "$code" "$newcode" "$restored" "$journal" "$tampered"; do
    if [[ -e "$f" || -L "$f" ]]; then rm -f -- "$f" || failed=1; fi
  done
  rmdir -- "$fixture_root" || failed=1
  if [[ "$failed" == 1 ]]; then echo "STOP=DISPOSABLE_CLEANUP_FAILED" >&2; exit 1; fi
  if [[ "$rc" == 0 ]]; then echo "DISPOSABLE_FIXTURES_CLEANED=PASS"; fi
  exit "$rc"
}
trap cleanup EXIT

# All code operations use a private temporary fixture, never a website path.
printf '%s\n' '<?php /* synthetic baseline */ return "baseline";' > "$code"
chmod 0600 "$code"
cp -- "$code" "$backup"
chmod 0600 "$backup"
base_code_sha="$(sha256sum "$backup" | awk '{print $1}')"
[[ "$(sha256sum "$code" | awk '{print $1}')" == "$base_code_sha" ]] ||
  stop CODE_BASELINE_INVALID
# Export authentic application migrations with synthetic CI records only.
[[ "$(mysql_cli -D "$source_db" -e 'SELECT COUNT(*) FROM schema_migrations')" -ge 10 ]] ||
  stop MIGRATED_CI_SCHEMA_MISSING
mysql_dump "$source_db" > "$dump"
chmod 0600 "$dump"
[[ -s "$dump" && ! -L "$dump" ]] || stop DUMP_INVALID
[[ "$(stat -c %a "$dump")" == 600 ]] || stop DUMP_PERMISSIONS_INVALID
[[ "$(stat -c %s "$dump")" -le 16777216 ]] || stop DUMP_TOO_LARGE
base_sql_sha="$(sha256sum "$dump" | awk '{print $1}')"
check_backups() {
 [[ -f "$dump" && ! -L "$dump" && -f "$backup" && ! -L "$backup" ]] &&
 [[ "$(sha256sum "$dump" | awk '{print $1}')" == "$base_sql_sha" ]] &&
 [[ "$(sha256sum "$backup" | awk '{print $1}')" == "$base_code_sha" ]]
}
check_backups || stop BACKUP_INTEGRITY_FAILED
printf 'prepared %s %s\n' "$base_code_sha" "$base_sql_sha" > "$journal"
chmod 0600 "$journal"
mysql_cli -e "CREATE DATABASE $target_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
target_created=1
check_backups || stop BASELINE_BACKUP_CHANGED
mysql_cli -D "$target_db" < "$dump"
php tools/ci-barama-schema-restore-compare.php --compare "$target_db" >/dev/null ||
  stop BASELINE_DATABASE_MISMATCH
echo "INITIAL_CODE_AND_DATABASE=VERIFIED"

# Apply synthetic update to private code fixture, then disposable DB.
printf '%s\n' '<?php /* synthetic update */ return "updated";' > "$newcode"
chmod 0600 "$newcode"
changed_code_sha="$(sha256sum "$newcode" | awk '{print $1}')"
[[ "$changed_code_sha" != "$base_code_sha" ]] || stop CODE_NOT_CHANGED
mv -T -- "$newcode" "$code"
[[ "$(sha256sum "$code" | awk '{print $1}')" == "$changed_code_sha" ]] ||
  stop CODE_UPDATE_MISMATCH
echo "code_updated" >> "$journal"
mysql_cli -D "$target_db" -e "
CREATE TABLE ci_update_fault (id INT NOT NULL PRIMARY KEY, note VARCHAR(64) NOT NULL) ENGINE=InnoDB;
INSERT INTO ci_update_fault(id,note) VALUES(1,'synthetic update');"
[[ "$(mysql_cli -D "$target_db" -e 'SELECT COUNT(*) FROM ci_update_fault')" == 1 ]] ||
  stop DATABASE_UPDATE_MISMATCH
echo "database_updated" >> "$journal"

# Inject failure only AFTER both components demonstrably changed.
[[ "$(sha256sum "$code" | awk '{print $1}')" == "$changed_code_sha" ]] ||
  stop FAULT_CODE_STATE_WRONG
[[ "$(mysql_cli -D "$target_db" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ci_update_fault'")" == 1 ]] ||
  stop FAULT_DATABASE_STATE_WRONG
echo "failure_injected" >> "$journal"
echo "FAILURE_INJECTION=AFTER_CODE_AND_DATABASE_CHANGE"

# Roll back both to pinned baseline; NEVER issue SQL on barama_test.
check_backups || stop BACKUP_CHANGED_BEFORE_ROLLBACK
mysql_cli -e "DROP DATABASE $target_db"
mysql_cli -e "CREATE DATABASE $target_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
check_backups || stop BACKUP_CHANGED_BEFORE_RESTORE
mysql_cli -D "$target_db" < "$dump"
check_backups || stop BACKUP_CHANGED_AFTER_RESTORE
echo "database_restored" >> "$journal"
cp -- "$backup" "$restored"
chmod 0600 "$restored"
[[ "$(sha256sum "$restored" | awk '{print $1}')" == "$base_code_sha" ]] ||
  stop CODE_RESTORE_COPY_MISMATCH
mv -T -- "$restored" "$code"
[[ "$(sha256sum "$code" | awk '{print $1}')" == "$base_code_sha" ]] ||
  stop CODE_RESTORATION_FAILED
echo "code_restored" >> "$journal"
php tools/ci-barama-schema-restore-compare.php --compare "$target_db"
[[ "$(mysql_cli -D "$target_db" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ci_update_fault'")" == 0 ]] ||
  stop FAKE_MIGRATION_STILL_PRESENT
echo "verified_rolled_back" >> "$journal"
cp -- "$dump" "$tampered"
printf '\n-- corrupted\n' >> "$tampered"
[[ "$(sha256sum "$tampered" | awk '{print $1}')" != "$base_sql_sha" ]] ||
  stop TAMPERED_SQL_ACCEPTED
[[ "$(tail -n 1 "$journal")" == verified_rolled_back ]] ||
  stop JOURNAL_TERMINAL_STATE_MISSING
echo "COORDINATED_CODE_ROLLBACK=PASS"
echo "COORDINATED_DATABASE_ROLLBACK=PASS"
echo "REAL_MIGRATED_CI_SCHEMA_RECOVERED=PASS"
echo "CORRUPTED_SQL_COPY_REJECTED=PASS"
echo "RECOVERY_JOURNAL=PASS"
echo "REAL_WEBSITE_ROLLBACK=NOT_TESTED"
echo "CRASH_SAFE_RECOVERY=NOT_TESTED"
echo "PRODUCTION_DATABASE_ACCESSED=NO"
echo "WEBSITE_WRITES=NO"
echo "AUTO_INSTALL=DISABLED"
echo "RESULT=PASS"
