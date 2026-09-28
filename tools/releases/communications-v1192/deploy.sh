#!/usr/bin/env bash
set -Eeuo pipefail

BUNDLE_ROOT="$(cd "$(dirname "\${BASH_SOURCE[0]}")/../.." && pwd)"
SITE="/www/wwwroot/baramarealestate.tonsamith.com"
PHP="/www/server/php/84/bin/php"
URL="https://baramarealestate.tonsamith.com"
MANIFEST="$BUNDLE_ROOT/manifest/admin-communications-v1.19.2.json"
PREDEPLOY="$BUNDLE_ROOT/bin/predeploy.php"
POSTDEPLOY="$BUNDLE_ROOT/bin/postdeploy.php"
ROLLBACK_DB="$BUNDLE_ROOT/bin/rollback-db.php"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="/root/barama-real-estate-backups/admin-communications-phase7-v1.19.2-$STAMP"

BACKUP_READY=0
MIGRATION_ATTEMPTED=0

FILES=()

restore_files() {
    local state rel
    [ -f "$BACKUP/baseline.tsv" ] || return 0

    while IFS=$'\t' read -r state rel; do
        [ -n "$rel" ] || continue

        if [ "$state" = "present" ]; then
            mkdir -p "$SITE/$(dirname "$rel")"
            cp -a "$BACKUP/files/$rel" "$SITE/$rel"
        elif [ "$state" = "absent" ]; then
            rm -f "$SITE/$rel"
        else
            echo "ERROR: Unknown backup state for $rel: $state" >&2
            return 1
        fi
    done < "$BACKUP/baseline.tsv"
}

on_error() {
    local exit_code=$?
    trap - ERR
    set +e

    echo
    echo "============================================================"
    echo " Deployment failed — attempting bounded rollback"
    echo "============================================================"

    if [ "$BACKUP_READY" -eq 1 ]; then
        local db_code=0

        if [ "$MIGRATION_ATTEMPTED" -eq 1 ]; then
            "$PHP" "$ROLLBACK_DB" --root="$SITE"
            db_code=$?
        fi

        if [ "$db_code" -eq 0 ]; then
            restore_files
            "$PHP" "$PREDEPLOY" --root="$SITE" --manifest="$MANIFEST" --json || true
        else
            echo "ERROR: Database rollback was refused or failed."
            echo "Source files were left on the v1.19.2 target to avoid a code/schema mismatch."
            echo "Manual recovery is required."
        fi

        echo "Backup directory: $BACKUP"
    fi

    exit "$exit_code"
}

trap on_error ERR

echo "============================================================"
echo " Barama Real Estate — Communications Phase 7 v1.19.2"
echo " Announcements & Audience Delivery"
echo "============================================================"

[ "$SITE" = "/www/wwwroot/baramarealestate.tonsamith.com" ] || {
    echo "ERROR: Unsafe site path."
    exit 1
}

[ -d "$SITE" ] || {
    echo "ERROR: Barama site root is missing: $SITE"
    exit 1
}

[ -x "$PHP" ] || {
    echo "ERROR: Expected aaPanel PHP 8.4 CLI is unavailable: $PHP"
    exit 1
}

command -v curl >/dev/null 2>&1 || {
    echo "ERROR: curl is required for the HTTP acceptance check."
    exit 1
}

cd "$BUNDLE_ROOT"
sha256sum -c BUNDLE_CONTENTS.sha256

mapfile -t FILES < <(
    "$PHP" -r '
        $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
        foreach (($manifest["production_files"] ?? []) as $entry) {
            echo $entry["path"], PHP_EOL;
        }
    ' "$MANIFEST"
)

[ "${#FILES[@]}" -gt 0 ] || {
    echo "ERROR: Release manifest contains no production files."
    exit 1
}

echo
echo "[1/7] Accepted v1.19.1 drift guard..."
"$PHP" "$PREDEPLOY" --root="$SITE" --manifest="$MANIFEST"

echo
echo "[2/7] Creating exact-file rollback backup..."
mkdir -p "$BACKUP/files" "$BACKUP/helpers"
chmod 700 "$BACKUP" 2>/dev/null || true
: > "$BACKUP/baseline.tsv"

for rel in "\${FILES[@]}"; do
    if [ -f "$SITE/$rel" ]; then
        mkdir -p "$BACKUP/files/$(dirname "$rel")"
        cp -a "$SITE/$rel" "$BACKUP/files/$rel"
        printf 'present\t%s\n' "$rel" >> "$BACKUP/baseline.tsv"
    else
        printf 'absent\t%s\n' "$rel" >> "$BACKUP/baseline.tsv"
    fi
done

cp -a "$MANIFEST" "$BACKUP/helpers/manifest.json"
cp -a "$PREDEPLOY" "$BACKUP/helpers/predeploy.php"
cp -a "$POSTDEPLOY" "$BACKUP/helpers/postdeploy.php"
cp -a "$ROLLBACK_DB" "$BACKUP/helpers/rollback-db.php"
cp -a "$BUNDLE_ROOT/bin/rollback.sh" "$BACKUP/rollback.sh"

cat > "$BACKUP/rollback.env" <<EOF
SITE=$SITE
PHP=$PHP
URL=$URL
EOF
chmod 700 "$BACKUP/rollback.sh"
BACKUP_READY=1

echo
echo "[3/7] PHP syntax checks for candidate payload..."
for rel in "\${FILES[@]}"; do
    if [[ "$rel" == *.php ]]; then
        "$PHP" -l "$BUNDLE_ROOT/payload/$rel" >/dev/null
        echo "  OK $rel"
    fi
done

echo
echo "[4/7] Deploying exact manifest-bound source files..."
for rel in "\${FILES[@]}"; do
    mkdir -p "$SITE/$(dirname "$rel")"
    cp -a "$BUNDLE_ROOT/payload/$rel" "$SITE/$rel"
done

echo
echo "[5/7] Applying pending additive migration..."
MIGRATION_ATTEMPTED=1
"$PHP" "$SITE/tools/migrate.php" | tee "$BACKUP/migrate.log"

echo
echo "[6/7] Running read-only post-deploy acceptance..."
"$PHP" "$POSTDEPLOY" \
    --root="$SITE" \
    --manifest="$MANIFEST" \
    | tee "$BACKUP/postdeploy.log"

echo
echo "[7/7] HTTP route / PHP-FPM consistency check..."
sleep 3
HTTP_CODE="$(
    curl -k -sS -o /dev/null -w '%{http_code}' \
        "$URL/dashboard/announcements?acceptance=$STAMP"
)"
[ "$HTTP_CODE" = "302" ] || {
    echo "ERROR: Expected guest HTTP 302 from /dashboard/announcements, got $HTTP_CODE"
    exit 1
}
echo "  /dashboard/announcements -> HTTP $HTTP_CODE"

trap - ERR

echo
echo "============================================================"
echo " Communications Phase 7 v1.19.2 deployed and accepted"
echo "============================================================"
echo "No Nginx change performed."
echo "No service restart/reload performed."
echo "Backup:   $BACKUP"
echo "Rollback: $BACKUP/rollback.sh"
