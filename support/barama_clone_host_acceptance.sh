#!/usr/bin/env bash
# READ-ONLY aaPanel/cPanel acceptance of an isolated Laravel QA installation.
set -Eeuo pipefail
set +x
umask 077
: "$BARAMA_HOST_TYPE" "$BARAMA_QA_ROOT" "$BARAMA_QA_URL" "$BARAMA_QA_PHP"

if [[ "$BARAMA_HOST_TYPE" != aaPanel && "$BARAMA_HOST_TYPE" != cPanel ]]; then
    echo "DENIED=UNSUPPORTED_HOST_TYPE"; exit 3
fi
case "$BARAMA_QA_ROOT" in
    *barama-laravel-rehearsal*) ;;
    *) echo "DENIED=QA_ROOT_MUST_BE_DISPOSABLE"; exit 3 ;;
esac
ROOT="$(readlink -f -- "$BARAMA_QA_ROOT")"
if [[ ! -d "$ROOT" || "$ROOT" == / ||
      "$ROOT" == /www/wwwroot/baramarealestate.tonsamith.com ||
      "$ROOT" == /www/wwwroot/baramarealestate.tonsamith.com/* ||
      "$ROOT" != *barama-laravel-rehearsal* ]]; then
    echo "DENIED=LIVE_OR_MISSING_QA_ROOT"; exit 3
fi
if [[ ! -f "$ROOT/.barama-disposable-rehearsal" ||
      "$(cat "$ROOT/.barama-disposable-rehearsal")" != BAR_REHEARSAL_ONLY ]]; then
    echo "DENIED=EXPLICIT_QA_MARKER_REQUIRED"; exit 3
fi
if [[ ! -x "$BARAMA_QA_PHP" ||
      ! -f "$ROOT/artisan" || ! -f "$ROOT/public/index.php" ||
      ! -f "$ROOT/vendor/autoload.php" ||
      ! -f "$ROOT/public/build/manifest.json" ]]; then
    echo "DENIED=QA_PACKAGE_NOT_INSTALLED"; exit 3
fi
if [[ ! "$BARAMA_QA_URL" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?/?$ ||
      "$BARAMA_QA_URL" == *baramarealestate.tonsamith.com* ||
      "$BARAMA_QA_URL" == *localhost* ||
      "$BARAMA_QA_URL" == *127.0.0.1* ]]; then
    echo "DENIED=SEPARATE_HTTPS_QA_HOST_REQUIRED"; exit 3
fi

echo "BARAMA_ACCEPTANCE_MODE=READ_ONLY"
echo "HOST_TYPE=$BARAMA_HOST_TYPE"
echo "LIVE_APPLICATION=NOT_TOUCHED"
echo "DATABASE=NOT_CONNECTED"
echo "PRIVATE_CONFIG=NOT_PRINTED"
echo "========== PHP / PACKAGE =========="
"$BARAMA_QA_PHP" -r '
if (PHP_VERSION_ID < 80300) {fwrite(STDERR,"PHP_TOO_OLD\n");exit(1);}
foreach (["pdo","pdo_mysql","openssl","mbstring","fileinfo","ctype","tokenizer"] as $ext) {
 if (!extension_loaded($ext)) {fwrite(STDERR,"MISSING_EXTENSION=".$ext."\n");exit(1);}
}
echo "PHP_RUNTIME_AND_EXTENSIONS=PASS\n";'
"$BARAMA_QA_PHP" -l "$ROOT/public/index.php" >/dev/null
"$BARAMA_QA_PHP" -l "$ROOT/bootstrap/app.php" >/dev/null
echo "PUBLIC_ENTRY_AND_BOOTSTRAP_SYNTAX=PASS"
if [[ "$BARAMA_HOST_TYPE" == aaPanel ]]; then
    if [[ -x /www/server/nginx/sbin/nginx ]]; then
        /www/server/nginx/sbin/nginx -t >/dev/null 2>&1
        echo "NGINX_CONFIG_SYNTAX=PASS"
    else
        echo "NGINX_CONFIG_SYNTAX=NOT_VERIFIED"
    fi
else
    if [[ -f "$ROOT/.htaccess" || -f "$ROOT/public/.htaccess" ]]; then
        echo "CPANEL_APACHE_HTACCESS_PRESENT=YES"
    else
        echo "CPANEL_APACHE_HTACCESS_PRESENT=NO"; exit 2
    fi
fi
base="$(printf '%s' "$BARAMA_QA_URL" | sed 's,/$,,')"
fetch_code() {
    curl --silent --show-error --max-time 12 --connect-timeout 5 \
      --path-as-is -o /dev/null -w '%{http_code}' -- "$base$1"
}
echo "========== QA HTTPS AND PRIVATE ROUTES =========="
home="$(fetch_code /)"
echo "HTTPS_HOME_HTTP=$home"
case "$home" in 200|301|302|307|308) : ;; *) echo "FAILED=QA_HOME"; exit 2 ;; esac
for protected in /.env /vendor/autoload.php /storage/app/installed.lock \
    /application/bootstrap/app.php /private/media/source-test.png; do
    code="$(fetch_code "$protected")"
    case "$code" in
        401|403|404|410) echo "PRIVATE_ROUTE_DENIAL=PASS" ;;
        *) echo "FAILED=PRIVATE_ROUTE_EXPOSURE_OR_REDIRECT"; exit 2 ;;
    esac
done
echo "TLS_AND_PRIVATE_ACCESS=PASS"
echo "DEVICE_AND_USER_JOURNEYS=REQUIRES_MANUAL_EVIDENCE"
echo "CUSTOMER_MIGRATION=NOT_PERFORMED"
echo "QA_HOST_READ_ONLY_ACCEPTANCE=PASS"
