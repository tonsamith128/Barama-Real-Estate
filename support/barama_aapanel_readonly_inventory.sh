#!/usr/bin/env bash
# Read-only inventory of the installed legacy Pure PHP Barama source.
# Usage: bash support/barama_aapanel_readonly_inventory.sh /www/wwwroot/baramarealestate.tonsamith.com
set -Eeuo pipefail
set +x
umask 077

ROOT="${1:-/www/wwwroot/baramarealestate.tonsamith.com}"
REFERENCE_INDEX_BLOB="fb871fb0343a0a5dd00cb5bcd845b3c8b51eb430"

echo "========== BARAMA READ-ONLY LEGACY INVENTORY =========="
printf 'AUDIT_MODE=READ_ONLY\nTARGET=%s\n' "$ROOT"
if [ ! -d "$ROOT" ] || [ -L "$ROOT" ] || [ ! -r "$ROOT" ]; then
    echo "TARGET_ACCESS=UNAVAILABLE_OR_SYMLINK"
    exit 2
fi
echo "TARGET_ACCESS=READABLE_DIRECTORY"

if command -v php >/dev/null 2>&1; then
    php -v | sed -n '1p'
else
    echo "PHP_CLI=NOT_ON_PATH"
fi

check_file() {
    local relative="$1" path="$ROOT/$1" git_blob=""
    if [ -L "$path" ]; then
        printf 'FILE %s: SYMLINK_SKIPPED\n' "$relative"
    elif [ -f "$path" ] && [ -r "$path" ]; then
        printf 'FILE %s: PRESENT SIZE_BYTES=%s SHA256=%s\n' \
            "$relative" "$(stat -c '%s' "$path")" "$(sha256sum "$path" | cut -d ' ' -f1)"
        if [ "$relative" = "index.php" ] && command -v git >/dev/null 2>&1; then
            git_blob="$(git hash-object -- "$path" 2>/dev/null || true)"
            if [ "$git_blob" = "$REFERENCE_INDEX_BLOB" ]; then
                echo "ARCHIVED_20261030_INDEX_MATCH=YES"
            else
                echo "ARCHIVED_20261030_INDEX_MATCH=NO_OR_UPDATED"
            fi
        fi
    else
        printf 'FILE %s: NOT_PRESENT_OR_UNREADABLE\n' "$relative"
    fi
}

for file in \
    index.php \
    application/bootstrap/app.php \
    application/bootstrap/runtime.php \
    application/routes/web.php \
    application/app/Domain/RealEstate/DomainRules.php \
    application/app/Http/Controllers/MarketplaceController.php \
    application/app/Http/Controllers/CompanyController.php \
    artisan \
    public/index.php; do
    check_file "$file"
done

for item in \
    application/storage/app/installation/installed.php \
    application/database/migrations \
    database/migrations \
    application/storage/uploads/private \
    .env; do
    path="$ROOT/$item"
    if [ -e "$path" ] && [ ! -L "$path" ]; then
        printf 'RESOURCE %s: EXISTS\n' "$item"
    elif [ -L "$path" ]; then
        printf 'RESOURCE %s: SYMLINK_SKIPPED\n' "$item"
    else
        printf 'RESOURCE %s: ABSENT\n' "$item"
    fi
done

if [ -d "$ROOT/application" ] && [ ! -L "$ROOT/application" ]; then
    printf 'PURE_PHP_APPLICATION_FILE_COUNT=%s\n' \
        "$(find "$ROOT/application" -type f -name '*.php' -not -path '*/vendor/*' -not -path '*/storage/*' | wc -l | tr -d ' ')"
fi

for dir in application/database/migrations application/app/Http/Controllers; do
    if [ -d "$ROOT/$dir" ] && [ ! -L "$ROOT/$dir" ]; then
        printf 'DIRECTORY %s PHP_FILE_COUNT=%s\n' "$dir" \
            "$(find "$ROOT/$dir" -maxdepth 3 -type f -name '*.php' | wc -l | tr -d ' ')"
    fi
done

echo "CONFIDENTIAL_CONTENT=NOT_READ_OR_PRINTED"
echo "DATABASE=NOT_CONNECTED"
echo "PRODUCTION_FILES=UNCHANGED"
echo "========== INVENTORY COMPLETE =========="
