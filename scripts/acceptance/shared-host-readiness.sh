#!/usr/bin/env bash
set -Eeuo pipefail
# Read-only checks, never run migrations or mutate a customer installation.
if [[ "$#" -ne 3 || "$1" != "--check-only" ]]; then
  echo "Usage: bash shared-host-readiness.sh --check-only /absolute/test/app https://test.example.com" >&2
  exit 2
fi
APP_ROOT="$2"
BASE_URL="$3"
if [[ "$APP_ROOT" != /* || ! -f "$APP_ROOT/artisan" || "$BASE_URL" != https://* ]]; then
  echo "Refused invalid test app root or HTTPS origin." >&2
  exit 2
fi
cd "$APP_ROOT"
FAIL=0
record() { if [[ "$2" == 0 ]]; then echo "PASS $1"; else echo "FAIL $1"; FAIL=1; fi; }
if [[ -f ".env" && ! -f "public/.env" ]]; then record "ENV_PRIVATE" 0; else record "ENV_PRIVATE" 1; fi
if [[ -f "bootstrap/app.php" && -f "public/index.php" && ! -d "public/vendor" && ! -d "public/.git" ]]; then record "WEBROOT_ISOLATED" 0; else record "WEBROOT_ISOLATED" 1; fi
if [[ -w "storage" && -w "bootstrap/cache" ]]; then record "WRITABLE_RUNTIME" 0; else record "WRITABLE_RUNTIME" 1; fi
if command -v php >/dev/null 2>&1; then
  php -v | head -n 1
  php artisan billing:release-check --json || FAIL=1
  php artisan migrate:status || FAIL=1
else record "PHP_CLI_AVAILABLE" 1; fi
if command -v curl >/dev/null 2>&1; then
  code="$(curl --proto "=https" --tlsv1.2 --silent --show-error --output /dev/null --write-out "%{http_code}" --max-time 12 "$BASE_URL" || echo 000)"
  case "$code" in 200|301|302) record "HTTPS_HOME_STATUS" 0 ;; *) record "HTTPS_HOME_STATUS" 1 ;; esac
else record "CURL_AVAILABLE" 1; fi
if [[ "$FAIL" == 0 ]]; then echo "PASS_LOCAL_PREFLIGHT_ONLY"; else echo "FAIL_LOCAL_PREFLIGHT"; fi
echo "This does not verify Stripe, merchant eligibility, tax law, backup restores or upgrade success."
exit "$FAIL"
