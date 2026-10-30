#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Only the five routing/bootstrap files needed for an ALREADY extracted
# development QA installation. No database, .env, vendor or asset changes.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
NAME="Barama-Installer-Routing-Hotfix"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT

TARGET="$TMP/$NAME"
mkdir -p "$TARGET/public/installer" "$TARGET/Installer" "$ROOT/dist"
for file in \
    .htaccess \
    public/index.php \
    public/installer/index.php \
    Installer/Setup.php \
    Installer/index.php; do
    test -f "$file"
    cp "$file" "$TARGET/$file"
done

cat > "$TARGET/README.txt" <<'INFO'
BARAMA LARAVEL — INSTALLER ROUTING REPAIR (DEVELOPMENT QA ONLY)

Use only if the Laravel Integrated QA app has ALREADY been extracted.
This repair changes no database data, .env, Composer packages, or assets.

1. Back up the existing application root .htaccess, public/index.php,
   public/installer/index.php, Installer/Setup.php and Installer/index.php.
2. Open cPanel File Manager at the application root, for example:
     /home/salalsxn/codecanyon.salabandet.com
3. Extract this repair ZIP into a temporary directory, then COPY ITS
   CONTENTS into the existing Laravel application root, preserving paths.
   Overwrite the five listed files. Do not create a nested app directory.
4. Set directories to 755 and public PHP files to 644; never expose .env.
5. Visit https://YOUR-CONFIGURED-DOMAIN/
   An UNCONFIGURED fresh install redirects to /installer/.
   https://YOUR-CONFIGURED-DOMAIN/installer/ also works directly.
6. If an existing .env or installation lock exists, the redirect is skipped
   intentionally. Do NOT delete .env, lock files or any database tables.
7. Security check: /vendor/autoload.php, /Installer/Setup.php,
   /public/installer/, /storage/app/installer.token must NOT be publicly
   downloadable. If they are readable, immediately disable directory
   listing and make the host document root Laravel public/ instead.

Root-level protected .htaccess is needed only on cPanel hosts that CANNOT
point the domain document root to Laravel public/. A correctly configured
public/ document root remains the recommended production setup.

This is a staging hotfix; full commercial module parity is unfinished.
INFO

find "$TARGET" -type d -exec chmod 0755 {} +
find "$TARGET" -type f -exec chmod 0644 {} +
(
    cd "$TMP"
    zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME"
)
unzip -tq "$ROOT/dist/$NAME.zip"
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "INSTALLER_ROUTING_HOTFIX_ARCHIVE_PASS"
