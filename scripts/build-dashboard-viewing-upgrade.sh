#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
NAME="Barama-Laravel-Dashboard-Viewing-QA-Upgrade"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT
TARGET="$TEMP/$NAME"
mkdir -p "$TARGET/public" "$ROOT/dist"
for file in \
  app/Http/Controllers/Admin/Dashboard/DashboardController.php \
  app/Http/Controllers/Admin/Viewings/AdminViewingController.php \
  app/Http/Controllers/Developer/Viewings/DeveloperViewingController.php \
  app/Services/Viewings/ViewingDecisionService.php \
  app/Services/Inquiries/InquiryReviewService.php \
  app/Models/Inquiry.php \
  app/Http/Controllers/Admin/Inquiries/AdminInquiryController.php \
  app/Http/Controllers/Developer/Inquiries/DeveloperInquiryController.php \
  resources/views/admin/inquiries/index.blade.php \
  resources/views/developer/inquiries/index.blade.php \
  app/Models/ViewingRequest.php \
  resources/views/admin/dashboard/index.blade.php \
  resources/views/admin/viewings/index.blade.php \
  resources/views/developer/viewings/index.blade.php \
  resources/views/developer/dashboard/index.blade.php \
  routes/admin.php routes/developer.php resources/css/admin/app.css; do
    test -f "$file"
    mkdir -p "$TARGET/$(dirname "$file")"
    cp "$file" "$TARGET/$file"
done
cp -a public/build "$TARGET/public/build"
test -r "$TARGET/public/build/manifest.json"
cat > "$TARGET/README.txt" <<'EOF'
BARAMA DASHBOARD RECOVERY + VIEWINGS + INQUIRIES - STAGING ONLY

For the existing Laravel 13 QA app; not for the legacy PHP product.

1. Back up the full website directory AND the MySQL database.
2. Run /opt/alt/php85/usr/bin/php artisan migrate:status from the app root.
3. Extract this ZIP outside the website; copy its inner folder contents
   into the existing Laravel root preserving paths.
4. Keep .env, storage, vendor, and installation lock untouched.
5. Run /opt/alt/php85/usr/bin/php artisan migrate --force
6. Run /opt/alt/php85/usr/bin/php artisan optimize:clear
7. Test /dashboard, /dashboard/viewings, /developer/viewings,
   /dashboard/inquiries and /developer/inquiries.
   If still HTTP 500, inspect storage/logs/laravel.log privately.
Do not use migrate:fresh, db:wipe, or reset the current database.
Never share database credentials or unrestricted log files.
EOF
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
( cd "$TEMP" && zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME" )
unzip -tq "$ROOT/dist/$NAME.zip"
python3 - "$ROOT/dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import json,sys
with ZipFile(sys.argv[1]) as z:
 p="Barama-Laravel-Dashboard-Viewing-QA-Upgrade/"
 m=json.loads(z.read(p+"public/build/manifest.json"))
 names=set(z.namelist())
 css=p+"public/build/"+m["resources/css/admin/app.css"]["file"]
 assert css in names and b".viewing-tabs" in z.read(css)
 for e in m.values():
  assert p+"public/build/"+e["file"] in names
 assert p+"app/Services/Viewings/ViewingDecisionService.php" in names
 assert not any(n.startswith(p+"storage/") or n.startswith(p+"vendor/") or n==p+".env" for n in names)
print("BARAMA_DASHBOARD_VIEWING_QA_PACKAGE_PASS")
PY
( cd "$ROOT/dist" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" )
echo "BARAMA_DASHBOARD_VIEWING_QA_UPGRADE_READY"
