#!/usr/bin/env bash
# Genuine MySQL DDL crash-and-restore QA: GitHub's disposable mysql:8.0 ONLY.
# No live server MySQL or customer database credentials are accepted.
set -Eeo pipefail
set +x
umask 077
fail(){ echo "STOP=$1" >&2; exit 1; }
guard(){
  [[ "$GITHUB_ACTIONS" == true && "$GITHUB_REPOSITORY" == tonsamith128/Barama-Real-Estate ]] &&
  [[ "$GITHUB_JOB" == foundation-qa && "$APP_ENV" == testing ]] &&
  [[ "$DB_DATABASE" == barama_test && "$PACKAGE_DB_DATABASE" == barama_package_test ]] &&
  [[ "$PACKAGE_DB_USERNAME" == root && "$PACKAGE_DB_HOST" == 127.0.0.1 && "$PACKAGE_DB_PORT" == 3306 ]] &&
  [[ "$BARAMA_CI_ONECLICK_MIGRATION" == ALLOW_MYSQL8_TEMP_SCHEMAS_ONLY ]] &&
  [[ -n "$PACKAGE_DB_PASSWORD" && "$MYSQL_SERVICE_CONTAINER_ID" =~ ^[a-f0-9]{12,64}$ ]]
}
case "$1" in
 --guard) if guard; then fail GUARD_ACTIVE; fi
   echo NON_CI_RUNTIME=REJECTED
   echo DATABASE_CONNECTIONS=NO
   echo DATABASE_WRITES=NO
   echo LIVE_AUTO_INSTALL=DISABLED
   exit 0;;
 --ci) guard || fail DISPOSABLE_CI_ONLY;;
 *) fail USAGE_GUARD_OR_CI;;
esac
command -v docker >/dev/null || fail DOCKER_MISSING
image="$(docker inspect --format '{{.Config.Image}}' "$MYSQL_SERVICE_CONTAINER_ID" 2>/dev/null)" || fail MYSQL_SERVICE_NOT_FOUND
[[ "$image" == mysql:8.0 ]] || fail MYSQL_SERVICE_IMAGE_UNTRUSTED
sql(){
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
 mysql --no-defaults -uroot -h127.0.0.1 --batch --raw --skip-column-names --default-character-set=utf8mb4 "$@"
}
dump(){
 docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
 mysqldump --no-defaults -uroot -h127.0.0.1 --single-transaction --quick \
 --no-tablespaces --set-gtid-purged=OFF --routines --triggers --events --hex-blob \
 --skip-lock-tables --default-character-set=utf8mb4 "$1"
}
nonce="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
[[ "$nonce" =~ ^[a-f0-9]{16}$ ]] || fail ENTROPY_FAILURE
original="barama_ci_ocorig_$nonce"
active="barama_ci_ocactive_$nonce"
[[ "$original" =~ ^barama_ci_ocorig_[a-f0-9]{16}$ ]] || fail SCHEMA_NAME_REJECTED
[[ "$active" =~ ^barama_ci_ocactive_[a-f0-9]{16}$ ]] || fail SCHEMA_NAME_REJECTED
dir="$(mktemp -d /tmp/barama-ci-ocmysql-XXXXXXXXXX)" || fail TMP_DIR_FAILED
chmod 0700 "$dir"
backup="$dir/baseline.sql"
modified="$dir/tampered.sql"
have_orig=0
have_active=0
cleanup(){
 local rc=$?
 trap - EXIT
 set +e
 local failed=0
 if [[ "$have_active" == 1 ]]; then sql -e "DROP DATABASE IF EXISTS $active" >/dev/null 2>&1 || failed=1; fi
 if [[ "$have_orig" == 1 ]]; then sql -e "DROP DATABASE IF EXISTS $original" >/dev/null 2>&1 || failed=1; fi
 rm -f -- "$backup" "$modified" || failed=1
 rmdir -- "$dir" || failed=1
 if [[ "$failed" != 0 ]]; then echo STOP=DISPOSABLE_CLEANUP_FAILED >&2; exit 1; fi
 if [[ "$rc" == 0 ]]; then echo DISPOSABLE_MYSQL_CLEANUP=PASS; fi
 exit "$rc"
}
trap cleanup EXIT
sql -e "CREATE DATABASE $original CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
have_orig=1
sql -e "CREATE DATABASE $active CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
have_active=1
seed(){
 sql -D "$1" <<'SQL'
CREATE TABLE application_settings(
 setting_key VARCHAR(190) PRIMARY KEY,setting_value TEXT NOT NULL
) ENGINE=InnoDB;
CREATE TABLE schema_migrations(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 migration VARCHAR(190) NOT NULL UNIQUE,batch INT UNSIGNED NOT NULL
) ENGINE=InnoDB;
CREATE TABLE properties(
 id INT UNSIGNED PRIMARY KEY,title VARCHAR(190) NOT NULL,
 price DECIMAL(14,2) NOT NULL,payload LONGBLOB NOT NULL
) ENGINE=InnoDB;
INSERT INTO application_settings VALUES('installation.version','0.9.22-dev');
INSERT INTO schema_migrations(migration,batch) VALUES('0010_baseline',1);
INSERT INTO properties VALUES
 (1,'ផ្ទះល្វែង',350000.00,UNHEX('00FFA1')),
 (2,'Apartment α',210000.50,UNHEX('DEADBEEF00'));
SQL
}
seed "$original"
seed "$active"
[[ "$(sql -D "$active" -e 'SELECT COUNT(*) FROM properties')" == 2 ]] || fail BASELINE_MISSING
dump "$active" > "$backup"
chmod 0600 "$backup"
[[ -f "$backup" && ! -L "$backup" && -s "$backup" ]] || fail BACKUP_MISSING
[[ "$(stat -c %a "$backup")" == 600 ]] || fail BACKUP_NOT_PRIVATE
[[ "$(stat -c %s "$backup")" -le 8388608 ]] || fail BACKUP_TOO_LARGE
digest="$(sha256sum "$backup" | awk '{print $1}')"
[[ "$digest" =~ ^[a-f0-9]{64}$ ]] || fail DIGEST_INVALID
verified(){
 [[ -f "$1" && ! -L "$1" ]] &&
 [[ "$(sha256sum "$1" | awk '{print $1}')" == "$digest" ]]
}
verified "$backup" || fail BACKUP_CHANGED
# Real MySQL DDL; no MySQL transaction can undo this by rolling back PHP files.
sql -D "$active" <<'SQL'
ALTER TABLE properties ADD COLUMN priority SMALLINT UNSIGNED NOT NULL DEFAULT 0;
CREATE TABLE migration_audit(id INT PRIMARY KEY,details VARCHAR(190) NOT NULL) ENGINE=InnoDB;
INSERT INTO migration_audit VALUES(1,'interrupted migration after DDL');
SQL
[[ "$(sql -D "$active" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='properties' AND COLUMN_NAME='priority'")" == 1 ]] || fail DDL_NOT_APPLIED
[[ "$(sql -D "$active" -e "SELECT setting_value FROM application_settings WHERE setting_key='installation.version'")" == 0.9.22-dev ]] || fail VERSION_CHANGED_PREMATURELY
echo MYSQL_DDL_APPLIED_BEFORE_VERSION=PASS
echo DB_PARTIAL_COMMIT=RECOVERY_REVIEW_REQUIRED
echo FILE_ONLY_AUTO_ROLLBACK=DENIED
cp "$backup" "$modified"
printf '\n-- tamper\n' >> "$modified"
if verified "$modified"; then fail TAMPERED_BACKUP_ACCEPTED; fi
echo SQL_DIGEST_TAMPER_REJECTED=PASS
verified "$backup" || fail BACKUP_CHANGED_BEFORE_RESTORE
# Isolated recovery rehearsal ONLY: drop one randomly generated CI database.
sql -e "DROP DATABASE $active"
sql -e "CREATE DATABASE $active CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
verified "$backup" || fail BACKUP_CHANGED_BEFORE_IMPORT
sql -D "$active" < "$backup"
verified "$backup" || fail BACKUP_CHANGED_AFTER_IMPORT
compare(){
 local lhs rhs
 lhs="$(sql -D "$original" -e "$2")" || fail BASELINE_QUERY_FAILED
 rhs="$(sql -D "$active" -e "$2")" || fail RESTORE_QUERY_FAILED
 [[ "$lhs" == "$rhs" ]] || fail "RESTORED_$1_MISMATCH"
 echo "RESTORED_$1=PASS"
}
compare VERSION "SELECT setting_value FROM application_settings WHERE setting_key='installation.version'"
compare MIGRATIONS 'SELECT migration,batch FROM schema_migrations ORDER BY id'
compare TABLES 'SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME'
compare COLUMNS 'SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION'
compare INDEXES 'SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX'
compare ROWS 'SELECT id,HEX(title),CAST(price AS CHAR),HEX(payload) FROM properties ORDER BY id'
[[ "$(sql -D "$active" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='migration_audit'")" == 0 ]] || fail PARTIAL_DDL_SURVIVED
[[ "$(sql -D "$active" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='properties' AND COLUMN_NAME='priority'")" == 0 ]] || fail ALTER_SURVIVED
echo ACTUAL_MYSQL_DDL_RESTORED_IN_DISPOSABLE_SCHEMA=PASS
echo LIVE_DATABASE_WRITES=NO
echo LIVE_FULL_SITE_RESTORE=NOT_PROVEN
echo PRODUCTION_ONECLICK_INSTALL=DISABLED
echo CI_MYSQL_DDL_RECOVERY=PASS