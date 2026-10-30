#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
test -r public/build/manifest.json
NAME="Barama-Laravel-cPanel-PostInstall-Upgrade"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
TARGET="$TMP/$NAME"
mkdir -p "$TARGET" "$ROOT/dist"
FILES=(
 app/Http/Controllers/Auth/Login/LoginController.php
 app/Http/Controllers/Frontend/Home/HomeController.php
 app/Http/Controllers/Admin/Licensing/LicenseController.php
 app/Services/Licensing/PublisherLicenseClient.php
 routes/admin.php
 resources/views/account/dashboard/index.blade.php
 resources/views/frontend/home/index.blade.php
 resources/views/admin/dashboard/index.blade.php
 resources/views/admin/license/index.blade.php
 resources/css/frontend/app.css
 resources/css/admin/app.css
)
for file in "${FILES[@]}"; do
    test -f "$file"
    mkdir -p "$TARGET/$(dirname "$file")"
    cp "$file" "$TARGET/$file"
done
mkdir -p "$TARGET/public"
cp -a public/build "$TARGET/public/build"
test -f "$TARGET/public/build/manifest.json"
test ! -f "$TARGET/public/manifest.json"
test ! -d "$TARGET/public/assets"
cmp -s public/build/manifest.json "$TARGET/public/build/manifest.json"
cat > "$TARGET/README.txt" <<'INFO'
STAGING ONLY — BARAMA POSTINSTALL UPGRADE

This overlay is for an already installed Laravel QA application. It
does not touch database records, installer lock, .env or vendor code.

1. Back up the existing staging application and database.
2. Extract this ZIP in a temporary folder (NOT the public web folder).
3. Copy the CONTENTS of Barama-Laravel-cPanel-PostInstall-Upgrade
   over the existing app root, preserving directory structure.
4. NEVER remove .env, storage/app/installed.lock or the database.
5. Run php artisan optimize:clear as the hosting user if available.
6. Test /login, /dashboard, /dashboard/license and the homepage.
7. The marketplace will show no properties until approved catalog
   content has been created or verified demo content imported.

The full original Barama commercial product is NOT yet migrated.
INFO
find "$TARGET" -type d -exec chmod 755 {} +
find "$TARGET" -type f -exec chmod 644 {} +
test ! -e "$TARGET/.env"
test ! -e "$TARGET/storage"
test ! -e "$TARGET/vendor"
test ! -e "$TARGET/database"
(
 cd "$TMP"
 zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME"
)
unzip -tq "$ROOT/dist/$NAME.zip"
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "CPANEL_POSTINSTALL_OVERLAY_PACKAGE_PASS"
