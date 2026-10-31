#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
test -f public/build/manifest.json
NAME="Barama-Laravel-Customer-Admin-Frontend-QA-Upgrade"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT
TARGET="$TEMP/$NAME"
mkdir -p "$TARGET/public" "$ROOT/dist"
FILES=(
  database/migrations/2026_10_31_000001_create_customer_workspace_tables.php
  database/migrations/2026_10_31_000002_create_contact_messages.php
  app/Models/SavedListing.php
  app/Models/ViewingRequest.php
  app/Models/AccountNotification.php
  app/Models/ContactMessage.php
  app/Services/Marketplace/InquiryCapture.php
  app/Http/Controllers/Account/Dashboard/DashboardController.php
  app/Http/Controllers/Account/Profile/ProfileController.php
  app/Http/Controllers/Account/SavedListings/SavedListingsController.php
  app/Http/Controllers/Account/Inquiries/AccountInquiryController.php
  app/Http/Controllers/Account/Viewings/ViewingRequestController.php
  app/Http/Controllers/Account/Notifications/NotificationController.php
  app/Http/Controllers/Frontend/Pages/PublicPageController.php
  app/Http/Controllers/Admin/Dashboard/DashboardController.php
  app/Http/Controllers/Admin/Users/UserDirectoryController.php
  app/Http/Controllers/Admin/Contacts/ContactInboxController.php
  routes/frontend.php routes/admin.php routes/account.php
  resources/views/components/layouts/frontend.blade.php
  resources/views/components/layouts/account.blade.php
  resources/views/frontend/home/index.blade.php
  resources/views/frontend/properties/show.blade.php
  resources/views/frontend/pages/about.blade.php
  resources/views/frontend/pages/contact.blade.php
  resources/views/frontend/pages/faq.blade.php
  resources/views/account/dashboard/index.blade.php
  resources/views/account/saved/index.blade.php
  resources/views/account/inquiries/index.blade.php
  resources/views/account/viewings/index.blade.php
  resources/views/account/notifications/index.blade.php
  resources/views/account/profile/edit.blade.php
  resources/views/admin/dashboard/index.blade.php
  resources/views/admin/users/index.blade.php
  resources/views/admin/contacts/index.blade.php
  resources/css/frontend/app.css resources/css/admin/app.css
)
for file in "${FILES[@]}"; do
  test -f "$file"
  mkdir -p "$TARGET/$(dirname "$file")"
  cp "$file" "$TARGET/$file"
done
cp -a public/build "$TARGET/public/build"
test -f "$TARGET/public/build/manifest.json"
test ! -f "$TARGET/public/manifest.json"
cat > "$TARGET/README.txt" <<'EOF'
BARAMA LARAVEL FRONTEND + CUSTOMER + ADMIN QA UPGRADE

Development QA only. Not a finished CodeCanyon customer release.

Install after the Media Library and Policy Center modules on a compatible
existing Laravel integrated QA installation. Do not mix with legacy PHP Barama.

1. Back up MySQL and the entire application directory.
2. Extract ZIP to a temporary folder, then copy the INNER folder contents
   into the application's existing root, preserving every directory path.
3. Do not overwrite .env, storage/, vendor/, installed.lock, or the DB.
4. Run a verified PHP CLI with: php artisan migrate --force
   This upgrade adds saved_listings, viewing_requests, account_notifications
   and contact_messages tables; it does not drop existing tables.
5. Run php artisan optimize:clear
6. Confirm public/build/manifest.json and public/build/assets exist.
7. Test /, /about, /contact, /faq, /account, /dashboard,
   /account/saved, /account/viewings, /account/profile,
   /dashboard/users, /dashboard/contacts.
8. If migration fails, investigate WITHOUT deleting user data.

No changes to other roles, private installer lock or license state.
EOF
find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
( cd "$TEMP" && zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME" )
unzip -tq "dist/$NAME.zip"
python3 - "dist/$NAME.zip" <<'PY'
from zipfile import ZipFile
import sys,json
with ZipFile(sys.argv[1]) as z:
    p="Barama-Laravel-Customer-Admin-Frontend-QA-Upgrade/"
    entries=set(z.namelist())
    m=json.loads(z.read(p+"public/build/manifest.json"))
    for source,selector in {
        "resources/css/frontend/app.css":".customer-shell",
        "resources/css/admin/app.css":".dashboard-feed",
    }.items():
        f=p+"public/build/"+m[source]["file"]
        assert f in entries and selector.encode() in z.read(f), (source, f)
    for e in m.values():
        assert p+"public/build/"+e["file"] in entries
    for f in [
        "database/migrations/2026_10_31_000001_create_customer_workspace_tables.php",
        "database/migrations/2026_10_31_000002_create_contact_messages.php",
    ]:
        assert p+f in entries
    assert not any(x.startswith(p+"storage/") or x.startswith(p+"vendor/") or x==p+".env" for x in entries)
    assert p+"public/manifest.json" not in entries
print("BARAMA_CUSTOMER_FRONTEND_QA_PACKAGE_INTEGRITY_PASS")
PY
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "BARAMA_FRONTEND_ACCOUNT_ADMIN_QA_UPGRADE_PASS"
