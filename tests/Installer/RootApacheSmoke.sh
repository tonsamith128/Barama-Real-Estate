#!/usr/bin/env bash
set -Eeuo pipefail

# Apache 2.4 root-document-root fallback acceptance on an isolated QA copy.
# This intentionally does not contact or modify any real customer host.
ZIP="$PWD/dist/Barama-Laravel-Integrated-QA.zip"
test -r "$ZIP"

TEMP="$(mktemp -d)"
cleanup() {
    sudo apachectl -k stop >/dev/null 2>&1 || true
    sudo chown -R "$(id -u):$(id -g)" "$TEMP" 2>/dev/null || true
    rm -rf "$TEMP"
}
trap cleanup EXIT

chmod 755 "$TEMP"
unzip -q "$ZIP" -d "$TEMP"
APP="$TEMP/Barama-Laravel-Integrated-QA"

test -f "$APP/.htaccess"
test -f "$APP/public/installer/index.php"
test ! -f "$APP/.env"
test ! -f "$APP/storage/app/installed.lock"

sudo apt-get update -qq
# Hosted runners change their distro PHP packages. Apache's PHP module may
# differ from setup-php's CLI version; both must satisfy Laravel PHP >= 8.3.
if apt-cache show libapache2-mod-php8.3 >/dev/null 2>&1; then
    php_package="libapache2-mod-php8.3"
else
    php_package="libapache2-mod-php"
fi
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    apache2 "$php_package"

mapfile -t php_modules < <(find /etc/apache2/mods-available \
    -maxdepth 1 -type f -name 'php*.load' -printf '%f\n' | LC_ALL=C sort -V)
test "${#php_modules[@]}" -gt 0 || {
    echo "APACHE_PHP_MODULE_UNAVAILABLE" >&2
    exit 1
}
php_module="${php_modules[$((${#php_modules[@]}-1))]%.load}"
if [[ ! "$php_module" =~ ^php([0-9]+)\.([0-9]+)$ ]] ||
   (( BASH_REMATCH[1] < 8 )) ||
   (( BASH_REMATCH[1] == 8 && BASH_REMATCH[2] < 3 )); then
    echo "APACHE_PHP_MODULE_UNSUPPORTED=$php_module" >&2
    exit 1
fi

# Verify Apache's own PHP runtime has the required Laravel extensions.
php_version="${php_module#php}"
required_php_packages=()
for extension in mysql mbstring xml curl zip; do
    package="php${php_version}-${extension}"
    if ! apt-cache show "$package" >/dev/null 2>&1; then
        echo "APACHE_PHP_REQUIRED_EXTENSION_PACKAGE_MISSING=$package" >&2
        exit 1
    fi
    required_php_packages+=("$package")
done
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    "${required_php_packages[@]}"

sudo a2dismod mpm_event >/dev/null 2>&1 || true
sudo a2dismod mpm_worker >/dev/null 2>&1 || true
sudo a2enmod mpm_prefork rewrite "$php_module" >/dev/null
echo "APACHE_PHP_MODULE=$php_module"
sudo chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"

# Runner port 80 can be occupied by unrelated preinstalled services.
# Limit this isolated smoke server to one nonprivileged localhost port.
printf 'Listen 127.0.0.1:18888\n' |
    sudo tee /etc/apache2/ports.conf >/dev/null
sudo a2dissite 000-default >/dev/null 2>&1 || true

sudo tee /etc/apache2/sites-available/barama-root-smoke.conf >/dev/null <<CONF
<VirtualHost 127.0.0.1:18888>
    ServerName 127.0.0.1
    DocumentRoot "$APP"
    <Directory "$APP">
        Require all granted
        Options FollowSymLinks
        AllowOverride All
    </Directory>
    ErrorLog "$TEMP/apache-error.log"
</VirtualHost>
CONF

sudo a2ensite barama-root-smoke >/dev/null
sudo apachectl configtest
sudo apachectl -k stop >/dev/null 2>&1 || true
if ! sudo apachectl -k start; then
    echo "Apache isolated startup failed:" >&2
    sudo tail -n 100 /var/log/apache2/error.log >&2 || true
    sudo tail -n 100 "$TEMP/apache-error.log" >&2 || true
    exit 1
fi

BASE="http://127.0.0.1:18888"
headers="$(curl --retry 5 --retry-delay 1 --silent --show-error --include \
    --max-time 15 "$BASE/")"
grep -Eq '^HTTP/[0-9.]+ 302' <<<"$headers"
grep -qi '^Location: /installer/' <<<"$headers"

# The installer must execute as PHP, not be downloaded as source code.
screen="$(curl --silent --show-error --max-time 15 "$BASE/installer/")"
grep -q 'Barama Real Estate' <<<"$screen"
grep -q '1. Requirements' <<<"$screen"
grep -q 'Local QA setup token' <<<"$screen"
test -f "$APP/storage/app/installer.token"

# /installer also works without a trailing slash.
test "$(curl --silent -o /dev/null -w '%{http_code}' "$BASE/installer")" = 200

# /public/ can be denied at rewrite time, or redirected to the
# installer by the early Laravel entrypoint on some Apache versions.
# Both are safe; never permit a 200 response exposing directory content.
public_status="$(curl --silent -o /dev/null -w '%{http_code}' "$BASE/public/")"
if [ "$public_status" = 302 ]; then
    curl --silent --show-error --include "$BASE/public/" |
        grep -qi '^Location: /installer/'
else
    test "$public_status" = 403 || {
        echo "SECURITY_ERROR: /public/ returned $public_status" >&2
        exit 1
    }
fi

# Root private directories and application source files may not be served.
for blocked in \
    /public/installer/ \
    /Installer/ \
    /Installer/Setup.php \
    /vendor/autoload.php \
    /storage/app/installer.token \
    /composer.json \
    /.env.example \
    /docs/INTEGRATED_ACCEPTANCE.md; do
    status="$(curl --silent -o /dev/null -w '%{http_code}' "$BASE$blocked")"
    test "$status" = 403 || {
        echo "SECURITY_ERROR: $blocked returned $status, expected 403" >&2
        exit 1
    }
done

echo "APACHE_ROOT_INSTALLER_REDIRECT_AND_PRIVATE_FILE_GUARDS_PASS"
