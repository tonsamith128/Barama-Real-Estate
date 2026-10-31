#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
NAME="Barama-Laravel-Appearance-QA-Upgrade"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT
TARGET="$TEMP/$NAME"
mkdir -p "$TARGET/public" "$ROOT/dist"
FILES=( database/migrations/2026_10_31_000004_create_site_appearances.php app/Models/SiteAppearance.php app/Services/Appearance/SiteAppearanceService.php app/Http/Controllers/Admin/Appearance/AppearanceController.php resources/views/admin/appearance/index.blade.php resources/views/components/layouts/frontend.blade.php resources/views/frontend/home/index.blade.php resources/views/admin/dashboard/index.blade.php routes/admin.php resources/css/admin/app.css )
for file in "${FILES[@]}"; do
  test -f "$file"
  mkdir -p "$TARGET/$(dirname "$file")"
  cp "$file" "$TARGET/$file"
done
cp -a public/build "$TARGET/public/build"
cat > "$TARGET/README.txt" <<'EOF'
Barama Laravel Appearance Manager: Development QA only.
Apply AFTER the Laravel catalog and dashboard QA upgrades.
1. Back up website files and MySQL database, verify backups.
2. Copy INNER ZIP folder contents into existing Laravel root.
3. Preserve .env, storage/, vendor/, uploads and installed.lock.
4. Run php artisan migrate:status then php artisan migrate --force.
5. Run php artisan optimize:clear.
6. Verify /dashboard/appearance and /; draft changes must not publish until Publish is clicked.
Do NOT reset or overwrite customer database and credentials.
EOF
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
( cd "$TEMP" && zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME" )
unzip -tq "$ROOT/dist/$NAME.zip"
python3 - "$ROOT/dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import json,sys
with ZipFile(sys.argv[1]) as z:
 p="Barama-Laravel-Appearance-QA-Upgrade/"
 files=set(z.namelist())
 m=json.loads(z.read(p+"public/build/manifest.json"))
 css=p+"public/build/"+m["resources/css/admin/app.css"]["file"]
 assert css in files and b".appearance-grid" in z.read(css)
 assert p+"database/migrations/2026_10_31_000004_create_site_appearances.php" in files
 for e in m.values():
  assert p+"public/build/"+e["file"] in files
 assert not any(x.startswith(p+"storage/") or x.startswith(p+"vendor/") or x==p+".env" for x in files)
print('BARAMA_APPEARANCE_QA_PACKAGE_PASS')
PY
( cd "$ROOT/dist" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" && sha256sum -c "$NAME.zip.sha256" )
echo "BARAMA_APPEARANCE_QA_READY"
