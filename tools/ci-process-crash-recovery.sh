#!/usr/bin/env bash
# Phase 4E5: process SIGKILL and independent CLI recovery on disposable CI only.
set -Eeuo pipefail
set +x
umask 077
stop(){ echo "STOP=$1" >&2; exit 1; }
scope(){
 [[ "${GITHUB_ACTIONS:-}" == true && "${GITHUB_REPOSITORY:-}" == tonsamith128/Barama-Real-Estate &&
    "${GITHUB_JOB:-}" == foundation-qa && "${APP_ENV:-}" == testing &&
    "${DB_DATABASE:-}" == barama_test && "${DB_HOST:-}" == 127.0.0.1 &&
    "${DB_PORT:-}" == 3306 && "${PACKAGE_DB_DATABASE:-}" == barama_package_test &&
    "${PACKAGE_DB_USERNAME:-}" == root && "${PACKAGE_DB_HOST:-}" == 127.0.0.1 &&
    "${PACKAGE_DB_PORT:-}" == 3306 && -n "${PACKAGE_DB_PASSWORD:-}" &&
    "${MYSQL_SERVICE_CONTAINER_ID:-}" =~ ^[a-f0-9]{12,64}$ &&
    "${BARAMA_CI_REAL_SCHEMA_RECOVERY:-}" == ALLOW_MIGRATED_CI_DATABASE_COPY_ONLY &&
    "${BARAMA_CI_PROCESS_CRASH:-}" == ALLOW_DISPOSABLE_SIGKILL_CI_ONLY ]]
}
case "${1:-}" in
 --guard)
   if scope; then stop GUARD_IN_AUTHORIZED_CI; fi
   echo "NON_CI_RUNTIME=REJECTED"; echo "DATABASE_CONNECTIONS=NO"
   echo "WEBSITE_WRITES=NO"; echo "AUTO_INSTALL=DISABLED"; exit 0 ;;
 --ci|--worker|--recover) scope || stop CI_SCOPE_REJECTED ;;
 *) stop INVALID_MODE ;;
esac
command -v docker >/dev/null || stop DOCKER_REQUIRED
command -v php >/dev/null || stop PHP_REQUIRED
[[ "$(docker inspect --format '{{.Config.Image}}' "$MYSQL_SERVICE_CONTAINER_ID" 2>/dev/null)" == mysql:8.0 ]] || stop MYSQL_IMAGE_REJECTED
mysql_cli(){
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
   mysql --no-defaults -h127.0.0.1 -uroot --batch --skip-column-names --default-character-set=utf8mb4 "$@"
}
mysql_dump(){
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
   mysqldump --no-defaults -h127.0.0.1 -uroot --single-transaction --quick \
   --no-tablespaces --set-gtid-purged=OFF --hex-blob --triggers --routines \
   --events --skip-lock-tables --default-character-set=utf8mb4 barama_test
}
valid_root(){
 [[ -n "${2:-}" && "$2" =~ ^/tmp/barama-crash-ci-[A-Za-z0-9]{10}$ &&
    -d "$2" && ! -L "$2" && "$(stat -c %a "$2")" == 700 ]]
}
read_manifest(){
 valid_root "${1:-}" "${2:-}" || stop INVALID_PRIVATE_ROOT
 root="$2"; [[ -f "$root/manifest" && ! -L "$root/manifest" &&
   "$(stat -c %a "$root/manifest")" == 600 ]] || stop INVALID_MANIFEST
 mapfile -t meta < "$root/manifest"
 [[ "${#meta[@]}" == 3 ]] || stop BAD_MANIFEST
 target="${meta[0]}"; sql_sha="${meta[1]}"; code_sha="${meta[2]}"
 [[ "$target" =~ ^barama_ci4e_real_[a-f0-9]{16}$ &&
    "$sql_sha" =~ ^[a-f0-9]{64}$ && "$code_sha" =~ ^[a-f0-9]{64}$ ]] || stop BAD_MANIFEST_VALUES
 [[ -f "$root/baseline.sql" && ! -L "$root/baseline.sql" &&
    -f "$root/baseline.php" && ! -L "$root/baseline.php" &&
    "$(stat -c %a "$root/baseline.sql")" == 600 &&
    "$(stat -c %a "$root/baseline.php")" == 600 ]] || stop BACKUP_FILES_INVALID
 [[ "$(sha256sum "$root/baseline.sql" | cut -d' ' -f1)" == "$sql_sha" &&
    "$(sha256sum "$root/baseline.php" | cut -d' ' -f1)" == "$code_sha" ]] || stop BACKUP_CHECKSUM_REJECTED
}
journal(){
 printf '%s\n' "$1" >> "$root/journal"
 sync -f "$root/journal"
}
if [[ "$1" == --worker ]]; then
 read_manifest "$@"
 [[ -f "$root/journal" && "$(tail -n 1 "$root/journal")" == prepared ]] || stop WORKER_STATE_REJECTED
 printf '%s\n' '<?php /* disposable changed fixture */ return "changed";' > "$root/next.php"
 chmod 600 "$root/next.php"
 mv -T "$root/next.php" "$root/live.php"
 journal code_changed
 mysql_cli -D "$target" -e "CREATE TABLE ci_update_fault (id INT PRIMARY KEY, note VARCHAR(64)); INSERT INTO ci_update_fault VALUES (1,'synthetic');"
 journal db_changed
 [[ "$(sha256sum "$root/live.php" | cut -d' ' -f1)" != "$code_sha" ]] || stop CODE_NOT_CHANGED
 [[ "$(mysql_cli -D "$target" -e 'SELECT COUNT(*) FROM ci_update_fault')" == 1 ]] || stop DB_NOT_CHANGED
 journal fault_checkpoint
 echo "WORKER_AT_FAULT_CHECKPOINT=YES"
 kill -KILL "$BASHPID"
 stop SIGKILL_NOT_DELIVERED
fi
if [[ "$1" == --recover ]]; then
 read_manifest "$@"
 [[ -f "$root/journal" && ! -L "$root/journal" &&
    "$(tail -n 1 "$root/journal")" == fault_checkpoint ]] || stop RECOVERY_CHECKPOINT_MISSING
 # Fail closed on tampering BEFORE touching the disposable target.
 journal recovery_started
 mysql_cli -e "DROP DATABASE IF EXISTS $target; CREATE DATABASE $target CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
 mysql_cli -D "$target" < "$root/baseline.sql"
 journal database_restored
 cp -- "$root/baseline.php" "$root/replacement.php"
 chmod 600 "$root/replacement.php"
 mv -T "$root/replacement.php" "$root/live.php"
 journal code_restored
 [[ "$(sha256sum "$root/live.php" | cut -d' ' -f1)" == "$code_sha" ]] || stop CODE_RESTORE_MISMATCH
 php tools/ci-barama-schema-restore-compare.php --compare "$target"
 [[ "$(mysql_cli -D "$target" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ci_update_fault'")" == 0 ]] || stop INJECTED_TABLE_PERSISTED
 journal verified_rolled_back
 echo "INDEPENDENT_RECOVERY_PROCESS=PASS"
 echo "CODE_RESTORED=PASS"; echo "MIGRATED_DATABASE_RESTORED=PASS"
 echo "JOURNAL_TERMINAL_STATE=verified_rolled_back"
 exit 0
fi
# Supervisor: private fixtures are never under the checkout or an application webroot.
suffix="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
[[ "$suffix" =~ ^[a-f0-9]{16}$ ]] || stop RANDOM_INVALID
target="barama_ci4e_real_$suffix"
root="$(mktemp -d /tmp/barama-crash-ci-XXXXXXXXXX)"
chmod 700 "$root"
created=0
cleanup(){
 rc=$?; trap - EXIT; set +e
 failure=0
 if [[ "$created" == 1 ]]; then mysql_cli -e "DROP DATABASE IF EXISTS $target" >/dev/null 2>&1 || failure=1; fi
 rm -f -- "$root"/baseline.sql "$root"/baseline.php "$root"/live.php "$root"/next.php "$root"/replacement.php "$root"/manifest "$root"/journal || failure=1
 rmdir -- "$root" || failure=1
 [[ "$failure" == 0 ]] || { echo STOP=CLEANUP_FAILED >&2; exit 1; }
 exit "$rc"
}
trap cleanup EXIT
[[ "$(mysql_cli -D barama_test -e 'SELECT COUNT(*) FROM schema_migrations')" -ge 10 ]] || stop MIGRATIONS_MISSING
mysql_dump > "$root/baseline.sql"
[[ -s "$root/baseline.sql" && "$(stat -c %s "$root/baseline.sql")" -le 16777216 ]] || stop DUMP_INVALID
printf '%s\n' '<?php /* disposable baseline fixture */ return "baseline";' > "$root/baseline.php"
cp -- "$root/baseline.php" "$root/live.php"
chmod 600 "$root"/*
sql_sha="$(sha256sum "$root/baseline.sql" | cut -d' ' -f1)"
code_sha="$(sha256sum "$root/baseline.php" | cut -d' ' -f1)"
printf '%s\n' "$target" "$sql_sha" "$code_sha" > "$root/manifest"
printf '%s\n' prepared > "$root/journal"
sync -f "$root/manifest"; sync -f "$root/journal"; sync -f "$root/baseline.sql"
mysql_cli -e "CREATE DATABASE $target CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created=1
mysql_cli -D "$target" < "$root/baseline.sql"
php tools/ci-barama-schema-restore-compare.php --compare "$target" >/dev/null
# SIGKILL worker process, not the Actions runner or supervisor.
set +e
bash "$0" --worker "$root"
worker_rc=$?
set -e
[[ "$worker_rc" == 137 ]] || stop WORKER_DID_NOT_EXIT_FROM_SIGKILL
[[ "$(tail -n 1 "$root/journal")" == fault_checkpoint ]] || stop KILL_CHECKPOINT_MISSING
[[ "$(mysql_cli -D "$target" -e 'SELECT COUNT(*) FROM ci_update_fault')" == 1 ]] || stop DB_CHANGE_NOT_PERSISTED
[[ "$(sha256sum "$root/live.php" | cut -d' ' -f1)" != "$code_sha" ]] || stop CODE_CHANGE_NOT_PERSISTED
echo "UPDATER_PROCESS_SIGKILL=PASS"
# An independently started shell recovers from the persisted journal + backups.
bash "$0" --recover "$root"
[[ "$(tail -n 1 "$root/journal")" == verified_rolled_back ]] || stop RECOVERY_INCOMPLETE
echo "PROCESS_CRASH_RECOVERY_REHEARSAL=PASS"
echo "POWER_LOSS_DURABILITY=NOT_TESTED"
echo "PRODUCTION_ACCESSED=NO"; echo "AUTO_INSTALL=DISABLED"
