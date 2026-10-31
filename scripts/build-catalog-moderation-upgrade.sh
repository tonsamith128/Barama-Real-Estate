#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
NAME="Barama-Laravel-Catalog-Moderation-QA-Upgrade"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT
TARGET="$TEMP/$NAME"
mkdir -p "$TARGET/public" "$ROOT/dist"
FILES=( database/migrations/2026_10_31_000003_create_moderation_decisions.php app/Services/Developer/CompanyTeamManager.php app/Http/Controllers/Developer/Team/DeveloperTeamController.php resources/views/developer/team/index.blade.php app/Services/Developer/ProjectManager.php app/Services/Developer/CompanyCatalogWriter.php app/Services/Moderation/CatalogReviewService.php app/Http/Controllers/Developer/Projects/DeveloperProjectController.php app/Http/Controllers/Developer/Properties/DeveloperPropertyController.php app/Http/Controllers/Admin/Catalog/CatalogModerationController.php resources/views/developer/projects/index.blade.php resources/views/developer/properties/index.blade.php resources/views/developer/properties/edit.blade.php resources/views/developer/dashboard/index.blade.php resources/views/admin/catalog/index.blade.php resources/css/admin/app.css routes/developer.php routes/admin.php )
for file in "${FILES[@]}"; do
  test -f "$file"
  mkdir -p "$TARGET/$(dirname "$file")"
  cp "$file" "$TARGET/$file"
done
cp -a public/build "$TARGET/public/build"
cat > "$TARGET/README.txt" <<'EOF'
Barama Laravel Catalog Moderation: existing Laravel QA installation only.
1. Back up the entire application and MySQL database first.
2. Copy INNER folder contents into the same Laravel application root.
3. Preserve .env, vendor/, storage/ and installer lock.
4. Run php artisan migrate:status then php artisan migrate --force.
5. Run php artisan optimize:clear and test /dashboard/catalog and /developer.
Never reset the customer database; use storage/logs privately for errors.
EOF
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
( cd "$TEMP" && zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME" )
unzip -tq "$ROOT/dist/$NAME.zip"
python3 - "$ROOT/dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import json,sys
with ZipFile(sys.argv[1]) as z:
 p="Barama-Laravel-Catalog-Moderation-QA-Upgrade/"
 names=set(z.namelist())
 m=json.loads(z.read(p+"public/build/manifest.json"))
 css=p+"public/build/"+m["resources/css/admin/app.css"]["file"]
 assert css in names and b".catalog-review-card" in z.read(css)
 assert p+"database/migrations/2026_10_31_000003_create_moderation_decisions.php" in names
 for entry in m.values():
  assert p+"public/build/"+entry["file"] in names
 assert not any(x.startswith(p+"storage/") or x.startswith(p+"vendor/") or x==p+".env" for x in names)
print('CATALOG_MODERATION_PACKAGE_PASS')
PY
( cd "$ROOT/dist" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" && sha256sum -c "$NAME.zip.sha256" )
echo "CATALOG_MODERATION_QA_READY"
