#!/usr/bin/env bash
# Phase 4E2: synthetic full-object MySQL restore in GitHub CI service ONLY.
set -Eeo pipefail
set +x
umask 077
fail() { echo "STOP=$1" >&2; exit 1; }
ci_guard() {
  [[ "$GITHUB_ACTIONS" == "true" ]] &&
  [[ "$GITHUB_REPOSITORY" == "tonsamith128/Barama-Real-Estate" ]] &&
  [[ "$GITHUB_JOB" == "foundation-qa" ]] &&
  [[ "$APP_ENV" == "testing" ]] &&
  [[ "$DB_DATABASE" == "barama_test" ]] &&
  [[ "$PACKAGE_DB_DATABASE" == "barama_package_test" ]] &&
  [[ "$PACKAGE_DB_USERNAME" == "root" ]] &&
  [[ "$PACKAGE_DB_HOST" == "127.0.0.1" ]] &&
  [[ "$PACKAGE_DB_PORT" == "3306" ]] &&
  [[ "$BARAMA_CI_FULL_OBJECT_MYSQL" == "ALLOW_DISPOSABLE_MYSQL8_SERVICE_ONLY" ]] &&
  [[ -n "$PACKAGE_DB_PASSWORD" ]] &&
  [[ "$MYSQL_SERVICE_CONTAINER_ID" =~ ^[a-f0-9]{12,64}$ ]]
}
case "$1" in
  --guard)
    if ci_guard; then fail CI_GUARD_ACTIVE_USE_CI_MODE; fi
    echo "NON_CI_RUNTIME=REJECTED"
    echo "DATABASE_CONNECTIONS=NO"
    echo "DATABASE_WRITES=NO"
    echo "WEBSITE_WRITES=NO"
    echo "AUTO_INSTALL=DISABLED"
    exit 0 ;;
  --ci) ci_guard || fail GITHUB_DISPOSABLE_MYSQL_ONLY ;;
  *) fail USAGE_GUARD_OR_CI ;;
esac
command -v docker >/dev/null || fail DOCKER_MISSING
command -v sha256sum >/dev/null || fail SHA256_MISSING
image="$(docker inspect --format '{{.Config.Image}}' "$MYSQL_SERVICE_CONTAINER_ID" 2>/dev/null)" || fail MYSQL_SERVICE_NOT_FOUND
[[ "$image" == mysql:8.0 ]] || fail MYSQL_SERVICE_IMAGE_UNTRUSTED
# Only the GitHub MySQL 8 service receives the ephemeral CI-only password.
mysql_cli() {
  docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
    mysql --no-defaults -uroot -h127.0.0.1 --batch --raw --skip-column-names \
    --default-character-set=utf8mb4 "$@"
}
mysql_dump() {
  docker exec -i -e "MYSQL_PWD=$PACKAGE_DB_PASSWORD" "$MYSQL_SERVICE_CONTAINER_ID" \
    mysqldump --no-defaults -uroot -h127.0.0.1 \
    --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
    --routines --triggers --events --hex-blob --skip-lock-tables \
    --default-character-set=utf8mb4 "$1"
}
suffix="$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
[[ "$suffix" =~ ^[a-f0-9]{16}$ ]] || fail RANDOM_SUFFIX_INVALID
source_db="barama_ci4e_source_$suffix"
baseline_db="barama_ci4e_baseline_$suffix"
[[ "$source_db" =~ ^barama_ci4e_source_[a-f0-9]{16}$ ]] || fail SOURCE_NAME_INVALID
[[ "$baseline_db" =~ ^barama_ci4e_baseline_[a-f0-9]{16}$ ]] || fail BASELINE_NAME_INVALID
private_dir="$(mktemp -d /tmp/barama-fullmysql-ci-XXXXXXXXXX)" || fail PRIVATE_TEMP_FAILED
chmod 0700 "$private_dir"
dump="$private_dir/mysql-full.sql"
tamper="$private_dir/mysql-tampered.sql"
created_source=0
created_baseline=0
cleanup() {
  rc=$?
  trap - EXIT
  set +e
  failed=0
  if [[ "$created_source" == 1 ]]; then
    mysql_cli -e "DROP DATABASE IF EXISTS $source_db" >/dev/null 2>&1 || failed=1
  fi
  if [[ "$created_baseline" == 1 ]]; then
    mysql_cli -e "DROP DATABASE IF EXISTS $baseline_db" >/dev/null 2>&1 || failed=1
  fi
  rm -f -- "$dump" "$tamper" || failed=1
  rmdir -- "$private_dir" || failed=1
  if [[ "$failed" == 1 ]]; then
    echo "STOP=DISPOSABLE_CLEANUP_FAILED" >&2
    exit 1
  fi
  if [[ "$rc" == 0 ]]; then echo "DISPOSABLE_CLEANUP=PASS"; fi
  exit "$rc"
}
trap cleanup EXIT
mysql_cli -e "CREATE DATABASE $source_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created_source=1
mysql_cli -e "CREATE DATABASE $baseline_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created_baseline=1
# Synthetic non-customer schema with real FK, index, trigger, procedure, event and view.
seed() {
  mysql_cli -D "$1" <<'SQL'
CREATE TABLE ci_properties (
  id INT UNSIGNED PRIMARY KEY, slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL, price DECIMAL(14,2) NOT NULL,
  payload LONGBLOB NOT NULL, status ENUM('published','draft') NOT NULL,
  INDEX idx_price(price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ci_media (
  id INT UNSIGNED PRIMARY KEY, property_id INT UNSIGNED NOT NULL,
  filename VARCHAR(190) NOT NULL, fingerprint VARBINARY(32) NOT NULL,
  INDEX idx_property(property_id),
  CONSTRAINT ci_media_fk FOREIGN KEY(property_id) REFERENCES ci_properties(id)
  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ci_audit (
  id INT UNSIGNED PRIMARY KEY, property_id INT UNSIGNED NOT NULL,
  action_name VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ci_transactions (
  id INT UNSIGNED PRIMARY KEY, property_id INT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL, INDEX idx_txn_property(property_id),
  CONSTRAINT ci_transactions_fk FOREIGN KEY(property_id) REFERENCES ci_properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DELIMITER //
CREATE TRIGGER ci_media_insert AFTER INSERT ON ci_media FOR EACH ROW
BEGIN
 INSERT INTO ci_audit(id,property_id,action_name) VALUES(NEW.id,NEW.property_id,'created');
END//
CREATE PROCEDURE ci_count_properties(OUT total INT)
BEGIN
 SELECT COUNT(*) INTO total FROM ci_properties;
END//
CREATE EVENT ci_disabled_event
 ON SCHEDULE EVERY 10 YEAR STARTS '2030-01-01 00:00:00'
 ON COMPLETION PRESERVE DISABLE
 DO SET @barama_ci_event_noop = 1//
DELIMITER ;
INSERT INTO ci_properties(id,slug,title,price,payload,status) VALUES
 (1,'urban-home','ផ្ទះល្វែង',250000.50,UNHEX('00FF01'),'published'),
 (2,'alpha','Apartment α',55000.00,UNHEX('FEFF00'),'published'),
 (3,'quote','Quote O''Neil',-11.25,UNHEX('DEADBEEF00'),'draft');
INSERT INTO ci_media(id,property_id,filename,fingerprint) VALUES
 (10,1,'ផ្ទះ.png',UNHEX(REPEAT('AB',32))),
 (11,2,'alpha.bin',UNHEX(REPEAT('03',32)));
INSERT INTO ci_transactions(id,property_id,amount) VALUES
 (100,1,250000.50),(101,2,55000.00);
CREATE VIEW ci_property_summary AS
 SELECT p.id AS property_id, p.slug, COUNT(m.id) AS media_count
 FROM ci_properties p LEFT JOIN ci_media m ON m.property_id=p.id
 GROUP BY p.id,p.slug;
SQL
}
seed "$source_db"
seed "$baseline_db"
[[ "$(mysql_cli -D "$source_db" -e 'SELECT COUNT(*) FROM ci_properties')" == 3 ]] || fail SOURCE_ROWS_INVALID
[[ "$(mysql_cli -D "$source_db" -e 'SELECT COUNT(*) FROM ci_audit')" == 2 ]] || fail SOURCE_TRIGGER_INVALID
mysql_dump "$source_db" > "$dump"
chmod 0600 "$dump"
[[ -s "$dump" && ! -L "$dump" ]] || fail DUMP_INVALID
[[ "$(stat -c %a "$dump")" == 600 ]] || fail DUMP_PERMISSIONS_UNSAFE
[[ "$(stat -c %s "$dump")" -le 4194304 ]] || fail DUMP_OVERSIZED
original_digest="$(sha256sum "$dump" | awk '{print $1}')"
digest_ok() {
 [[ -f "$1" && ! -L "$1" ]] &&
 [[ "$(sha256sum "$1" | awk '{print $1}')" == "$original_digest" ]]
}
digest_ok "$dump" || fail PRE_RESTORE_DIGEST_MISMATCH
# Restore into SAME generated disposable database name to preserve schema
# qualification in views/routines. Compare with independently seeded baseline.
mysql_cli -e "DROP DATABASE $source_db"
mysql_cli -e "CREATE DATABASE $source_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
digest_ok "$dump" || fail DUMP_CHANGED_BEFORE_IMPORT
mysql_cli -D "$source_db" < "$dump"
digest_ok "$dump" || fail DUMP_CHANGED_AFTER_IMPORT
same() {
 label="$1"
 query="$2"
 expected="$(mysql_cli -D "$baseline_db" -e "$query")" || fail "$label"_BASELINE_QUERY_FAILED
 actual="$(mysql_cli -D "$source_db" -e "$query")" || fail "$label"_RESTORED_QUERY_FAILED
 [[ "$expected" == "$actual" ]] || fail "$label"_MISMATCH
 echo "$label=PASS"
}
same TABLES "SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME"
same COLUMNS "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'<NULL>'),EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION"
same INDEXES "SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"
same FOREIGN_KEYS "SELECT CONSTRAINT_NAME,TABLE_NAME,REFERENCED_TABLE_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME"
same TRIGGERS "SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME"
same ROUTINES "SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME"
same EVENTS "SELECT EVENT_NAME,EVENT_DEFINITION,STATUS FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE() ORDER BY EVENT_NAME"
same PROPERTIES "SELECT id,slug,HEX(title),CAST(price AS CHAR),HEX(payload),status FROM ci_properties ORDER BY id"
same MEDIA "SELECT id,property_id,HEX(filename),HEX(fingerprint) FROM ci_media ORDER BY id"
same AUDIT "SELECT id,property_id,action_name FROM ci_audit ORDER BY id"
same TRANSACTIONS "SELECT id,property_id,CAST(amount AS CHAR) FROM ci_transactions ORDER BY id"
same VIEW_RESULTS "SELECT property_id,slug,media_count FROM ci_property_summary ORDER BY property_id"
same STORED_PROCEDURE "CALL ci_count_properties(@num); SELECT @num"
[[ "$(mysql_cli -D "$source_db" -e "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()")" == 2 ]] || fail FK_COUNT_MISMATCH
[[ "$(mysql_cli -D "$source_db" -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()")" == 1 ]] || fail TRIGGER_COUNT_MISMATCH
[[ "$(mysql_cli -D "$source_db" -e "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()")" == 1 ]] || fail ROUTINE_COUNT_MISMATCH
[[ "$(mysql_cli -D "$source_db" -e "SELECT STATUS FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()")" == DISABLED ]] || fail EVENT_NOT_DISABLED
cp "$dump" "$tamper"
printf '\n-- tampered\n' >> "$tamper"
if digest_ok "$tamper"; then fail TAMPERED_DUMP_ACCEPTED; fi
echo "DUMP_TAMPER_REJECTED=PASS"
echo "GENUINE_MYSQLDUMP_RESTORE=PASS"
echo "MULTI_OBJECT_SYNTHETIC_RECOVERY=PASS"
echo "ACTUAL_BARAMA_DATABASE_RESTORE=NOT_TESTED"
echo "REAL_CODE_DB_ROLLBACK=NOT_TESTED"
echo "PRODUCTION_DATABASE_ACCESSED=NO"
echo "WEBSITE_WRITES=NO"
echo "AUTO_INSTALL=DISABLED"
echo "RESULT=PASS"
