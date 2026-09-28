#!/usr/bin/env bash
set -Eeuo pipefail

BACKUP="$(cd "$(dirname "\${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$BACKUP/rollback.env"
BASELINE="$BACKUP/baseline.tsv"
HELPERS="$BACKUP/helpers"

[ -f "$ENV_FILE" ] || {
    echo "ERROR: Rollback environment is missing."
    exit 1
}

# shellcheck disable=SC1090
. "$ENV_FILE"

[ "$SITE" = "/www/wwwroot/baramarealestate.tonsamith.com" ] || {
    echo "ERROR: Unsafe rollback site path."
    exit 1
}

[ -x "$PHP" ] || {
    echo "ERROR: Expected aaPanel PHP 8.4 CLI is unavailable: $PHP"
    exit 1
}

[ -f "$BASELINE" ] || {
    echo "ERROR: Rollback baseline is missing."
    exit 1
}

echo "============================================================"
echo " Barama Communications Phase 7 v1.19.2 — Rollback"
echo "============================================================"

echo
echo "[1/4] Verifying current source/schema still match v1.19.2..."
"$PHP" "$HELPERS/postdeploy.php" \
    --root="$SITE" \
    --manifest="$HELPERS/manifest.json" \
    --json

echo
echo "[2/4] Rolling back additive database migration..."
"$PHP" "$HELPERS/rollback-db.php" --root="$SITE"

echo
echo "[3/4] Restoring accepted v1.19.1 files..."
while IFS=$'\t' read -r state rel; do
    [ -n "$rel" ] || continue

    if [ "$state" = "present" ]; then
        mkdir -p "$SITE/$(dirname "$rel")"
        cp -a "$BACKUP/files/$rel" "$SITE/$rel"
    elif [ "$state" = "absent" ]; then
        rm -f "$SITE/$rel"
    else
        echo "ERROR: Unknown backup state for $rel: $state"
        exit 1
    fi
done < "$BASELINE"

echo
echo "[4/4] Verifying accepted v1.19.1 baseline..."
"$PHP" "$HELPERS/predeploy.php" \
    --root="$SITE" \
    --manifest="$HELPERS/manifest.json"

echo
echo "Rollback complete."
echo "No Nginx change performed."
echo "No service restart/reload performed."
