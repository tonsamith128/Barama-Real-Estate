#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
cd "$(dirname "$0")/.."
ROOT="$PWD"
NAME="Barama-Laravel-PolicyCenter-QA-Upgrade"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
TARGET="$TMP/$NAME"
mkdir -p "$TARGET/public" dist
for file in \
 app/Http/Controllers/Admin/Policy/LegalPolicyController.php \
 app/Http/Controllers/Frontend/Legal/LegalPageController.php \
 app/Services/Policy/LegalHtmlSanitizer.php \
 routes/admin.php routes/frontend.php \
 resources/views/admin/policies/index.blade.php \
 resources/views/admin/policies/edit.blade.php \
 resources/views/frontend/legal/index.blade.php \
 resources/views/frontend/legal/show.blade.php \
 resources/views/frontend/home/index.blade.php \
 resources/views/admin/dashboard/index.blade.php \
 resources/css/admin/app.css resources/css/frontend/app.css; do
  test -f "$file"
  mkdir -p "$TARGET/$(dirname "$file")"
  cp "$file" "$TARGET/$file"
done
cp -a public/build "$TARGET/public/build"
test -r "$TARGET/public/build/manifest.json"
test ! -e "$TARGET/public/manifest.json"
cat > "$TARGET/README.txt" <<'INFO'
BARAMA POLICY CENTER - LARAVEL STAGING QA UPGRADE

Install the completed Media Library QA update first, or use a full
integrated Laravel QA package containing both modules.

Back up your application and database; extract this ZIP into an
isolated folder then copy its inner contents into the existing Laravel
application root. Preserve every folder path.

This is an in-place code/CSS update only: it must NOT overwrite
.env, vendor, storage, media assets, the installer lock, or MySQL.

Run php artisan optimize:clear if supported, then check:
/dashboard/policies  (authenticated super administrator)
/legal               (public)
/dashboard           (navigation)
This is NOT a finished CodeCanyon commercial release.
INFO
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
(
  cd "$TMP"
  zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME"
)
unzip -tq "dist/$NAME.zip"
python3 - "dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import json,sys
with ZipFile(sys.argv[1]) as z:
 p="Barama-Laravel-PolicyCenter-QA-Upgrade/"
 m=json.loads(z.read(p+"public/build/manifest.json"))
 for k,required in {
   "resources/css/admin/app.css":".policy-actions",
   "resources/css/frontend/app.css":".legal-content__body"
 }.items():
   f=p+"public/build/"+m[k]["file"]
   assert required.encode() in z.read(f), k
 for e in m.values():
   assert p+"public/build/"+e["file"] in z.namelist()
 assert p+"app/Services/Policy/LegalHtmlSanitizer.php" in z.namelist()
 assert not any("/.env" in f or "/storage/" in f or "/vendor/" in f for f in z.namelist())
print("POLICY_CENTER_QA_OVERLAY_ASSETS_PASS")
PY
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "POLICY_CENTER_QA_OVERLAY_PACKAGE_PASS"
