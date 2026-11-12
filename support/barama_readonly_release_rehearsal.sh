#!/usr/bin/env bash
# Operator-only; NEVER opens the original production database or imports rows.
# All three database names must denote separately verified local disposable copies.
set -Eeuo pipefail
set +x
umask 077

if [[ $# -ne 1 || "$1" != --run-readonly ||
      "${APP_ENV:-}" != testing ||
      "${BARAMA_REHEARSAL_APPROVED:-}" != TWO_DISPOSABLE_CLONES_VERIFIED ||
      "${BARAMA_RESTORED_OFFLINE_VERIFIED:-}" != yes ||
      "${BARAMA_WORKFLOW_RUN_APPROVED:-}" != READ_ONLY_INSPECTION ]]; then
    echo "REHEARSAL_DENIED=EXPLICIT_OFFLINE_READ_ONLY_APPROVAL_REQUIRED"
    exit 3
fi
if [[ "${BARAMA_SOURCE_HOST:-}" != 127.0.0.1 ||
      "${BARAMA_RESTORED_HOST:-}" != 127.0.0.1 ||
      "${BARAMA_TARGET_HOST:-}" != 127.0.0.1 ||
      "${BARAMA_SOURCE_DB:-}" == "${BARAMA_RESTORED_DB:-}" ||
      ! "${BARAMA_SOURCE_DB:-}" =~ ^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$ ||
      ! "${BARAMA_RESTORED_DB:-}" =~ ^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$ ||
      ! "${BARAMA_TARGET_DB:-}" =~ ^barama_laravel_(qa|rehearsal)_[a-z0-9_]{4,32}$ ]]; then
    echo "REHEARSAL_DENIED=THREE_DISTINCT_LOCAL_CLONES_REQUIRED"
    exit 3
fi
RAW="${BARAMA_PRIVATE_EVIDENCE_DIR:-}"
if [[ -z "$RAW" || -L "$RAW" || ! -d "$RAW" ]]; then
    echo "REHEARSAL_DENIED=PRIVATE_EVIDENCE_DIRECTORY_REQUIRED"
    exit 3
fi
DIR="$(realpath -- "$RAW")"
if [[ "$DIR" != *barama-private-rehearsal-evidence ||
      "$DIR" == /www/wwwroot/* ||
      "$(stat -c '%a' "$DIR")" != 700 ||
      "$(stat -c '%u' "$DIR")" != "$(id -u)" ]]; then
    echo "REHEARSAL_DENIED=PRIVATE_OWNER_ONLY_VAULT_REQUIRED"
    exit 3
fi
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
PHP="${BARAMA_QA_PHP:-$(command -v php)}"
if [[ ! -x "$PHP" || ! -d "$ROOT/.git" ||
      ! "${BARAMA_EXPECTED_GIT_SHA:-}" =~ ^[a-f0-9]{40}$ ||
      "$(git -C "$ROOT" rev-parse HEAD)" != "$BARAMA_EXPECTED_GIT_SHA" ||
      -n "$(git -C "$ROOT" status --porcelain)" ]]; then
    echo "REHEARSAL_DENIED=REVIEWED_PINNED_CLEAN_SOURCE_REQUIRED"
    exit 3
fi

WORK="$(mktemp -d "$DIR/readonly-rehearsal.XXXXXXXX")"
cleanup() { rm -rf -- "$WORK"; }
trap cleanup ERR INT TERM

cd -- "$ROOT"
echo "REHEARSAL_MODE=READ_ONLY_LOCAL_CLONES"
echo "PRODUCTION_OR_ORIGINAL_SITE=NOT_ACCESSED"

"$PHP" support/barama_sensitive_restore_compare.php --compare-restores > "$WORK/restore.json"
"$PHP" -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if (($j["backup_consistency"]??"")!=="TWO_RESTORES_MATCH"
    || ($j["mode"]??"")!=="READ_ONLY"
    || ($j["secrets_or_financial_records_migrated"]??null)!==false) {exit(2);}
' "$WORK/restore.json"

BARAMA_SENSITIVE_RESTORE_VERIFIED=TWO_RESTORES_MATCH \
BARAMA_RELEASE_REVIEW_APPROVED=PRIVATE_READ_ONLY_ONLY \
"$PHP" support/barama_legacy_finance_security_audit.php --audit > "$WORK/finsec.json"
"$PHP" -r '
$j=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if (($j["semantic_integrity"]??"")!=="CHECKED_NO_KNOWN_ANOMALIES"
    || ($j["two_restore_aggregate_consistency"]??"")!=="MATCH"
    || ($j["database_or_account_mutated"]??null)!==false
    || ($j["payments_or_notifications_activated"]??null)!==false
    || ($j["release"]??"")!=="BLOCKED") {exit(2);}
' "$WORK/finsec.json"

# Runs --plan and reads clone/media inventory. Never runs --stage.
bash support/barama_real_clone_readonly_preflight.sh --preflight > "$WORK/clone-plan.log"
grep -Fx 'READ_ONLY_SOURCE_MEDIA_AND_MIGRATION_PREFLIGHT=PASS' "$WORK/clone-plan.log" >/dev/null
grep -Fx 'DATABASE_MUTATED=NO' "$WORK/clone-plan.log" >/dev/null
grep -Fx 'MEDIA_COPIED=NO' "$WORK/clone-plan.log" >/dev/null
grep -Fx 'PRODUCTION_RELEASE=BLOCKED' "$WORK/clone-plan.log" >/dev/null

(
    cd -- "$WORK"
    sha256sum restore.json finsec.json clone-plan.log > evidence.sha256
)
chmod 600 "$WORK"/*
chmod 700 "$WORK"
trap - ERR INT TERM
echo "ISOLATED_READ_ONLY_REHEARSAL=PASS"
echo "REAL_PHYSICAL_HOST_AND_DEVICE_RESULTS=NOT_TESTED"
echo "ACCOUNTING_SECURITY_PRIVACY_SIGNOFF=NOT_PERFORMED"
echo "PRODUCTION_RELEASE=BLOCKED"
