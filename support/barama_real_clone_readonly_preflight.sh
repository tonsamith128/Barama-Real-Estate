#!/usr/bin/env bash
# Read-only real-data clone preflight. Never accesses the original live site.
# Source and target MUST already be isolated/approved offline database clones.
set -Eeuo pipefail
set +x
umask 077

if [[ "$#" != 1 || "$1" != "--preflight" ]]; then
    echo "DENIED=USE_EXPLICIT_READ_ONLY_PREFLIGHT"
    exit 3
fi
if [[ "$APP_ENV" != testing ||
      "$BARAMA_REHEARSAL_APPROVED" != TWO_DISPOSABLE_CLONES_VERIFIED ]]; then
    echo "DENIED=PRIVATE_TWO_CLONE_APPROVAL_REQUIRED"
    exit 3
fi

RAW="$BARAMA_PRIVATE_EVIDENCE_DIR"
DIR="$(realpath -- "$RAW")"
if [[ ! -d "$DIR" || -L "$RAW" ||
      "$DIR" != *barama-private-rehearsal-evidence ||
      "$DIR" == /www/wwwroot/* ||
      "$(stat -c '%a' "$DIR")" != 700 ||
      "$(stat -c '%u' "$DIR")" != "$(id -u)" ]]; then
    echo "DENIED=PRIVATE_OWNER_ONLY_EVIDENCE_DIRECTORY_REQUIRED"
    exit 3
fi

WORK="$(mktemp -d "$DIR/clone-readonly.XXXXXXXX")"
clean() {
    rm -rf -- "$WORK"
}
trap clean ERR

echo "========== VERIFY OFFLINE CLONE SOURCE / TARGET =========="
php support/barama_isolated_clone_inventory.php > "$WORK/inventory.json"
echo "========== VERIFY SOURCE MEDIA HASHES =========="
php support/barama_isolated_media_inventory.php > "$WORK/media.json"
echo "========== READ-ONLY HISTORICAL CONVERSION PLAN =========="
php support/barama_isolated_clone_stage.php --plan > "$WORK/plan.json"

php -r '
$dir=$argv[1];
$i=json_decode(file_get_contents($dir."/inventory.json"),true,512,JSON_THROW_ON_ERROR);
$m=json_decode(file_get_contents($dir."/media.json"),true,512,JSON_THROW_ON_ERROR);
$p=json_decode(file_get_contents($dir."/plan.json"),true,512,JSON_THROW_ON_ERROR);
if(($i["gate"]??"")!=="STRUCTURAL_PREFLIGHT_PASS"
    || ($m["gate"]??"")!=="HASH_AND_SIZE_VERIFIED"
    || ($p["mode"]??"")!=="READ_ONLY_PLAN"
    || ($p["release_gate"]??"")!=="BLOCKED_UNTIL_INDEPENDENT_RECONCILIATION"
    || ($p["source_changed"]??null)!==false
    || ($p["target_changed"]??null)!==false) {
    fwrite(STDERR,"PREFLIGHT_BLOCKED=CLONE_DATA_NEEDS_PRIVATE_REVIEW\n");
    exit(2);
}
echo "READ_ONLY_SOURCE_MEDIA_AND_MIGRATION_PREFLIGHT=PASS\n";
' "$WORK"

(
    cd "$WORK"
    sha256sum inventory.json media.json plan.json > evidence.sha256
    test -s evidence.sha256
)
chmod 600 "$WORK"/*
chmod 700 "$WORK"
trap - ERR
echo "PRIVATE_EVIDENCE_CREATED=YES"
echo "DATABASE_MUTATED=NO"
echo "MEDIA_COPIED=NO"
echo "PRODUCTION_RELEASE=BLOCKED"
echo "NEXT_ACTION=INDEPENDENT_OPERATOR_REVIEW_NOT_AUTOMATED_STAGE"
