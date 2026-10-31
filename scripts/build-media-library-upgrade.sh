#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
cd "$(dirname "$0")/.."
NAME=Barama-Laravel-MediaLibrary-QA-Upgrade
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
TARGET="$TMP/$NAME"
mkdir -p "$TARGET/public" dist
for path in \
 app/Services/Media/PrivateImageLocator.php \
 app/Services/Media/ListingGalleryManager.php \
 app/Http/Controllers/Developer/Media/ListingGalleryController.php \
 app/Http/Controllers/Admin/Media/MediaLibraryController.php \
 app/Http/Controllers/Frontend/Media/PublicDemoMediaController.php \
 app/Http/Controllers/Developer/Properties/DeveloperPropertyController.php \
 routes/admin.php routes/developer.php \
 resources/views/admin/dashboard/index.blade.php \
 resources/views/admin/media/index.blade.php \
 resources/views/developer/properties/index.blade.php \
 resources/views/frontend/properties/show.blade.php \
 resources/css/admin/app.css resources/css/frontend/app.css; do
  test -f "$path"
  mkdir -p "$TARGET/$(dirname "$path")"
  cp "$path" "$TARGET/$path"
done
cp -a public/build "$TARGET/public/build"
test -r "$TARGET/public/build/manifest.json"
test ! -e "$TARGET/public/manifest.json"
cat > "$TARGET/README.txt" <<'NOTE'
BARAMA MEDIA LIBRARY - STAGING LARAVEL QA ONLY
Back up the application and database before overlay.
Extract into a temporary folder and copy contents into the existing
Laravel root, preserving directory structure.
Keep all .env, storage, vendor, database and installer lock files.
Run php artisan optimize:clear if the host offers Terminal.
Use on isolated staging, not a production Barama installation.
NOTE
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
( cd "$TMP" && zip -q -r -9 "$OLDPWD/dist/$NAME.zip" "$NAME" )
unzip -tq "dist/$NAME.zip"
python3 - "dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import json,sys
with ZipFile(sys.argv[1]) as z:
 p="Barama-Laravel-MediaLibrary-QA-Upgrade/"
 m=json.loads(z.read(p+"public/build/manifest.json"))
 for k,css in {"resources/css/admin/app.css":".listing-gallery","resources/css/frontend/app.css":".property-photo-gallery"}.items():
  f=p+"public/build/"+m[k]["file"]
  assert css.encode() in z.read(f)
 for e in m.values():
  assert p+"public/build/"+e["file"] in z.namelist()
print("MEDIA_UPGRADE_VITE_PASS")
PY
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "MEDIA_LIBRARY_QA_OVERLAY_PACKAGE_PASS"
