#!/usr/bin/env bash
set -Eeuo pipefail

PACKAGE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEBROOT="${WEBROOT:-/www/wwwroot/baramarealestate.tonsamith.com}"
BASE_URL="${BASE_URL:-https://baramarealestate.tonsamith.com}"

echo "============================================================"
echo " Barama Real Estate — About & Contact Acceptance Check"
echo "============================================================"
echo "Webroot:  ${WEBROOT}"
echo "Base URL: ${BASE_URL}"
echo

test -d "${WEBROOT}" || { echo "ERROR: Webroot not found."; exit 1; }

required_files=(
    "application/app/Http/Controllers/BuiltInPageAdminController.php"
    "application/app/Http/Controllers/BuiltInPageController.php"
    "application/app/Http/Controllers/ContactMessageController.php"
    "application/app/Repositories/ContactMessageRepository.php"
    "application/app/Security/ContactMessageThrottle.php"
    "application/app/Services/BuiltInPageManagementService.php"
    "application/app/Services/ContactMessageService.php"
    "application/database/migrations/0022_create_contact_messages.php"
    "application/resources/views/public/about.php"
    "application/resources/views/public/contact.php"
    "application/resources/views/workspace/builtin-page.php"
)

for relative in "${required_files[@]}"; do
    test -f "${WEBROOT}/${relative}" || {
        echo "ERROR: Missing deployed file: ${relative}"
        exit 1
    }
    echo "PASS file ${relative}"
done

echo
echo "Source contract checks..."
grep -q 'BARAMA_BUILTIN_ABOUT_CONTACT_V100' "${WEBROOT}/assets/css/app.css"
grep -q "'/dashboard/about'" "${WEBROOT}/application/routes/web.php"
grep -q "'/dashboard/contact'" "${WEBROOT}/application/routes/web.php"
grep -q "'/about'" "${WEBROOT}/application/routes/web.php"
grep -q "'/contact'" "${WEBROOT}/application/routes/web.php"
grep -q 'data-contact-form' "${WEBROOT}/application/resources/views/public/contact.php"
grep -q 'contact_messages' "${WEBROOT}/application/database/migrations/0022_create_contact_messages.php"
grep -q 'about-us.*contact.*contact-us' "${WEBROOT}/application/app/Services/PageManagementService.php"
grep -q 'LOWER(p.slug) NOT IN ("about", "about-us", "contact", "contact-us")' "${WEBROOT}/application/app/Repositories/WorkspaceReadRepository.php"
echo "SOURCE_CONTRACTS: PASS"

echo
echo "Focused PHP syntax checks..."
php_files=(
    "application/app/Http/Controllers/BuiltInPageAdminController.php"
    "application/app/Http/Controllers/BuiltInPageController.php"
    "application/app/Http/Controllers/CmsController.php"
    "application/app/Http/Controllers/ContactMessageController.php"
    "application/app/Http/Controllers/DashboardController.php"
    "application/app/Http/Controllers/SeoController.php"
    "application/app/Repositories/ContactMessageRepository.php"
    "application/app/Repositories/SeoRepository.php"
    "application/app/Repositories/SiteContentRepository.php"
    "application/app/Repositories/WorkspaceReadRepository.php"
    "application/app/Repositories/WorkspaceRecordRepository.php"
    "application/app/Security/ContactMessageThrottle.php"
    "application/app/Services/BuiltInPageManagementService.php"
    "application/app/Services/ContactMessageService.php"
    "application/app/Services/PageManagementService.php"
    "application/app/Services/SiteContentManagementService.php"
    "application/database/migrations/0022_create_contact_messages.php"
    "application/resources/views/public/about.php"
    "application/resources/views/public/contact.php"
    "application/resources/views/workspace/builtin-page.php"
    "application/routes/web.php"
    "tools/qa.php"
    "tools/schema-smoke.php"
)
for relative in "${php_files[@]}"; do
    php -l "${WEBROOT}/${relative}" >/dev/null
done
echo "PHP_SYNTAX: PASS"

echo
echo "Database acceptance..."
WEBROOT="${WEBROOT}" php "${PACKAGE}/verify_db.php"

check_200() {
    local path="$1"
    local code
    code="$(curl -sS --max-time 20 -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")"
    if [ "${code}" != "200" ]; then
        echo "ERROR: ${path} -> HTTP ${code}, expected 200"
        return 1
    fi
    echo " ${path} -> ${code} PASS"
}

check_redirect() {
    local path="$1"
    local code
    code="$(curl -sS --max-time 20 -o /dev/null -w '%{http_code}' "${BASE_URL}${path}")"
    case "${code}" in
        301|302|303|307|308)
            echo " ${path} -> ${code} PASS"
            ;;
        *)
            echo "ERROR: ${path} -> HTTP ${code}, expected redirect"
            return 1
            ;;
    esac
}

echo
echo "HTTP smoke checks..."
check_200 "/"
check_200 "/login"
check_200 "/about"
check_200 "/contact"
check_200 "/assets/css/app.css"
check_200 "/assets/js/app.js"
check_redirect "/pages/about"
check_redirect "/pages/contact"

echo
echo "============================================================"
echo " ABOUT & CONTACT ACCEPTANCE: PASS"
echo "============================================================"
