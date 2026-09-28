#!/usr/bin/env bash
set -Eeuo pipefail

PACKAGE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEBROOT="${WEBROOT:-/www/wwwroot/baramarealestate.tonsamith.com}"
BASE_URL="${BASE_URL:-https://baramarealestate.tonsamith.com}"
BACKUP_ROOT="${BACKUP_ROOT:-/root/barama-real-estate-backups}"
PATCH="${PACKAGE}/pr27.patch"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP="${BACKUP_ROOT}/builtin-about-contact-v100-${STAMP}"
TOUCHED="${BACKUP}/touched-files.txt"
NEWFILES="${BACKUP}/new-files.txt"

echo "============================================================"
echo " Barama Real Estate — Built-in About & Contact v1.0.0"
echo " PR #27 guarded aaPanel deployment"
echo "============================================================"
echo "Webroot:   ${WEBROOT}"
echo "Base URL:  ${BASE_URL}"
echo "Migration: 0022_create_contact_messages.php"
echo

test -d "${WEBROOT}" || { echo "ERROR: Webroot not found: ${WEBROOT}"; exit 1; }
test -f "${PATCH}" || { echo "ERROR: Patch not found: ${PATCH}"; exit 1; }

for command in php patch sha256sum curl awk grep cp mkdir chmod; do
    command -v "${command}" >/dev/null 2>&1 || {
        echo "ERROR: Required command not found: ${command}"
        exit 1
    }
done

cd "${PACKAGE}"
echo "Package integrity..."
sha256sum -c SHA256SUMS

if grep -q 'BARAMA_BUILTIN_ABOUT_CONTACT_V100' "${WEBROOT}/assets/css/app.css" 2>/dev/null; then
    echo
    echo "Deployment marker already exists. Running acceptance checks instead of patching again..."
    WEBROOT="${WEBROOT}" BASE_URL="${BASE_URL}" "${PACKAGE}/check.sh"
    exit 0
fi

echo
echo "Preflight patch verification..."
cd "${WEBROOT}"
patch --dry-run --batch --forward --fuzz=0 -p1 < "${PATCH}" >/tmp/barama-pr27-patch-dry-run.log 2>&1 || {
    cat /tmp/barama-pr27-patch-dry-run.log
    echo "ERROR: Live files do not match the PR #27 base expected by this package."
    echo "No live files were changed."
    exit 1
}
cat /tmp/barama-pr27-patch-dry-run.log
echo "PATCH_PREFLIGHT: PASS"

mkdir -p "${BACKUP}/files"
: > "${NEWFILES}"
awk '/^diff --git a\// {
    line=$0
    sub(/^diff --git a\//, "", line)
    split(line, parts, " b/")
    print parts[1]
}' "${PATCH}" > "${TOUCHED}"

echo
echo "Creating file backup..."
while IFS= read -r relative; do
    [ -n "${relative}" ] || continue
    if [ -e "${WEBROOT}/${relative}" ]; then
        mkdir -p "${BACKUP}/files/$(dirname "${relative}")"
        cp -a "${WEBROOT}/${relative}" "${BACKUP}/files/${relative}"
    else
        printf '%s\n' "${relative}" >> "${NEWFILES}"
    fi
done < "${TOUCHED}"

cat > "${BACKUP}/rollback.sh" <<'ROLLBACK'
#!/usr/bin/env bash
set -Eeuo pipefail
WEBROOT="${WEBROOT:-/www/wwwroot/baramarealestate.tonsamith.com}"
BACKUP="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MIGRATION="application/database/migrations/0022_create_contact_messages.php"

echo "============================================================"
echo " Barama About/Contact v1.0.0 — File Rollback"
echo "============================================================"
echo "Webroot: ${WEBROOT}"
echo "Backup:  ${BACKUP}"
echo
echo "NOTE: The additive contact_messages database table and its migration"
echo "      record are intentionally NOT removed by this rollback."

while IFS= read -r relative; do
    [ -n "${relative}" ] || continue

    if [ "${relative}" = "${MIGRATION}" ]; then
        echo "KEEP ${relative} (additive migration safety)"
        continue
    fi

    if [ -e "${BACKUP}/files/${relative}" ]; then
        mkdir -p "${WEBROOT}/$(dirname "${relative}")"
        cp -a "${BACKUP}/files/${relative}" "${WEBROOT}/${relative}"
        echo "RESTORE ${relative}"
    else
        rm -f "${WEBROOT}/${relative}"
        echo "REMOVE  ${relative}"
    fi
done < "${BACKUP}/touched-files.txt"

if [ ! -e "${WEBROOT}/${MIGRATION}" ] && [ -e "${BACKUP}/files/${MIGRATION}" ]; then
    mkdir -p "${WEBROOT}/$(dirname "${MIGRATION}")"
    cp -a "${BACKUP}/files/${MIGRATION}" "${WEBROOT}/${MIGRATION}"
fi

echo
echo "File rollback complete."
echo "The contact_messages table is retained to avoid destructive data loss."
ROLLBACK
chmod 700 "${BACKUP}/rollback.sh"

echo "Backup:   ${BACKUP}"
echo "Rollback: ${BACKUP}/rollback.sh"

echo
echo "Applying PR #27 patch..."
cd "${WEBROOT}"
patch --batch --forward --fuzz=0 -p1 < "${PATCH}"
echo "PATCH_APPLY: PASS"

echo
echo "PHP syntax checks for touched PHP files..."
while IFS= read -r relative; do
    case "${relative}" in
        *.php)
            php -l "${WEBROOT}/${relative}" >/dev/null
            echo "PASS ${relative}"
            ;;
    esac
done < "${TOUCHED}"

echo
echo "Running database migrations..."
(
    cd "${WEBROOT}"
    php tools/migrate.php
)

echo
echo "Running deployment acceptance..."
WEBROOT="${WEBROOT}" BASE_URL="${BASE_URL}" "${PACKAGE}/check.sh"

echo
echo "============================================================"
echo " BUILT-IN ABOUT & CONTACT DEPLOYMENT: PASS"
echo "============================================================"
echo "BACKUP=${BACKUP}"
echo "ROLLBACK=${BACKUP}/rollback.sh"
echo "PUBLIC_ABOUT=${BASE_URL}/about"
echo "PUBLIC_CONTACT=${BASE_URL}/contact"
echo "ADMIN_ABOUT=${BASE_URL}/dashboard/about"
echo "ADMIN_CONTACT=${BASE_URL}/dashboard/contact"
echo
echo "Hard-refresh the browser after deployment to bypass cached app.css/app.js."
