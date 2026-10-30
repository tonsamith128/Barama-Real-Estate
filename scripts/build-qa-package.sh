#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Development QA only — NOT a production customer or CodeCanyon release.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
test -r vendor/autoload.php
test -r public/build/manifest.json
test ! -e .env
test ! -e storage/app/installed.lock
test -d Installer
test -f public/installer/index.php

NAME="Barama-Laravel-Integrated-QA"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT
TARGET="$TMP/$NAME"
mkdir -p "$TARGET" "$ROOT/dist"

while IFS= read -r -d '' file; do
    [[ -f "$file" ]] || continue
    mkdir -p "$TARGET/$(dirname "$file")"
    cp -- "$file" "$TARGET/$file"
done < <(git ls-files -z)

rm -rf -- "$TARGET/.github"
cp -a vendor "$TARGET/vendor"
mkdir -p "$TARGET/public/build"
cp -a public/build/. "$TARGET/public/build/"
printf 'DEVELOPMENT TEST PACKAGE ONLY\nGit SHA: %s\n' "$(git rev-parse HEAD)" > "$TARGET/QA_BUILD_INFO.txt"

test -f "$TARGET/Installer/index.php"
test -f "$TARGET/public/installer/index.php"
test -f "$TARGET/vendor/autoload.php"
test -f "$TARGET/public/build/manifest.json"
test ! -f "$TARGET/.env"
test ! -e "$TARGET/.git"
test ! -d "$TARGET/node_modules"
test ! -d "$TARGET/.github"

(
    cd "$TMP"
    zip -q -r -9 "$ROOT/dist/$NAME.zip" "$NAME"
)
unzip -tq "$ROOT/dist/$NAME.zip"
printf '%s\n' "QA_PACKAGE_ZIP_CRC_PASS"
(
    cd "$TARGET"
    php scripts/verify-structure.php
    php scripts/verify-no-legacy.php
)
sha256sum "dist/$NAME.zip" > "dist/$NAME.zip.sha256"
echo "QA_PACKAGE_CREATED: dist/$NAME.zip"
