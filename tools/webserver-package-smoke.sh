#!/usr/bin/env bash
set -Eeuo pipefail

ZIP="${1:-}"

if [[ -z "$ZIP" || ! -f "$ZIP" ]]; then
    echo "ERROR: exact customer ZIP is required." >&2
    exit 1
fi

WORK="$(mktemp -d)"
PACKAGE="$WORK/package"
mkdir -p "$PACKAGE"
unzip -q "$ZIP" -d "$PACKAGE"

APACHE_NAME="barama-apache-${GITHUB_RUN_ID:-qa}-$$"
FPM_NAME="barama-fpm-${GITHUB_RUN_ID:-qa}-$$"
NGINX_NAME="barama-nginx-${GITHUB_RUN_ID:-qa}-$$"
NETWORK_NAME="barama-net-${GITHUB_RUN_ID:-qa}-$$"

cleanup() {
    docker stop "$APACHE_NAME" >/dev/null 2>&1 || true
    docker stop "$NGINX_NAME" >/dev/null 2>&1 || true
    docker stop "$FPM_NAME" >/dev/null 2>&1 || true
    docker network rm "$NETWORK_NAME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

wait_http() {
    local url="$1"

    for _ in $(seq 1 30); do
        if curl -sS -o /dev/null "$url"; then
            return 0
        fi
        sleep 1
    done

    echo "ERROR: HTTP service did not become ready: $url" >&2
    return 1
}

assert_status() {
    local expected="$1"
    local url="$2"
    local actual

    actual="$(curl -sS -o /dev/null -w '%{http_code}' "$url")"

    if [[ "$actual" != "$expected" ]]; then
        echo "ERROR: $url expected HTTP $expected, received $actual." >&2
        return 1
    fi
}

assert_location() {
    local expected="$1"
    local url="$2"
    local actual

    actual="$(
        curl -sS -D - -o /dev/null "$url" \
        | tr -d '\r' \
        | awk 'tolower($1) == "location:" {print $2; exit}'
    )"

    if [[ "$actual" != "$expected" ]]; then
        echo "ERROR: $url expected Location $expected, received $actual." >&2
        return 1
    fi
}

assert_header_contains() {
    local header="$1"
    local expected="$2"
    local url="$3"
    local actual

    actual="$(
        curl -sS -D - -o /dev/null "$url" \
        | tr -d '\r' \
        | awk -F': *' -v wanted="$header" '
            tolower($1) == tolower(wanted) {
                value = $0
                sub(/^[^:]+:[[:space:]]*/, "", value)
                print value
                exit
            }
        '
    )"

    if [[ "$actual" != *"$expected"* ]]; then
        echo "ERROR: $url expected $header to contain '$expected', received '$actual'." >&2
        return 1
    fi
}

echo "[INFO] Testing exact customer ZIP with Apache 2.4 + mod_rewrite..."

docker run --rm -d     --name "$APACHE_NAME"     -p 18080:80     -v "$PACKAGE:/var/www/html"     php:8.2-apache     sh -lc '
        a2enmod rewrite >/dev/null
        cat > /etc/apache2/conf-available/barama.conf <<EOF
<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
EOF
        a2enconf barama >/dev/null
        exec apache2-foreground
    ' >/dev/null

wait_http "http://127.0.0.1:18080/"
assert_status "302" "http://127.0.0.1:18080/"
assert_location "/installer/" "http://127.0.0.1:18080/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18080/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18080/"
assert_status "302" "http://127.0.0.1:18080/properties"
assert_location "/installer/" "http://127.0.0.1:18080/properties"
assert_status "200" "http://127.0.0.1:18080/installer/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18080/installer/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18080/installer/"
assert_header_contains "X-Content-Type-Options" "nosniff" "http://127.0.0.1:18080/installer/"
assert_header_contains "X-Frame-Options" "SAMEORIGIN" "http://127.0.0.1:18080/installer/"
assert_header_contains "Content-Security-Policy" "default-src 'self'" "http://127.0.0.1:18080/installer/"
assert_header_contains "Set-Cookie" "HttpOnly" "http://127.0.0.1:18080/installer/"
assert_header_contains "Set-Cookie" "SameSite=Lax" "http://127.0.0.1:18080/installer/"
assert_status "200" "http://127.0.0.1:18080/assets/css/app.css"
assert_status "403" "http://127.0.0.1:18080/application/config/app.php"

docker stop "$APACHE_NAME" >/dev/null

echo "[PASS] Apache exact-package routing, installer entry, assets and protected paths verified."

echo "[INFO] Testing exact customer ZIP in an Apache subdirectory..."

APACHE_SUBROOT="$WORK/apache-subroot"
mkdir -p "$APACHE_SUBROOT/barama"
cp -a "$PACKAGE/." "$APACHE_SUBROOT/barama/"

docker run --rm -d     --name "$APACHE_NAME"     -p 18081:80     -v "$APACHE_SUBROOT:/var/www/html"     php:8.2-apache     sh -lc '
        a2enmod rewrite >/dev/null
        cat > /etc/apache2/conf-available/barama.conf <<EOF
<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
EOF
        a2enconf barama >/dev/null
        exec apache2-foreground
    ' >/dev/null

wait_http "http://127.0.0.1:18081/barama/"
assert_status "302" "http://127.0.0.1:18081/barama/"
assert_location "/barama/installer/" "http://127.0.0.1:18081/barama/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18081/barama/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18081/barama/"
assert_status "302" "http://127.0.0.1:18081/barama/properties"
assert_location "/barama/installer/" "http://127.0.0.1:18081/barama/properties"
assert_status "200" "http://127.0.0.1:18081/barama/installer/"

docker stop "$APACHE_NAME" >/dev/null

echo "[PASS] Apache subdirectory rewrite and canonical installer redirect verified."

echo "[INFO] Testing exact customer ZIP with Nginx + PHP-FPM front-controller routing..."

cat > "$WORK/nginx.conf" <<'EOF'
events {}

http {
    server {
        listen 80;
        server_name _;
        root /var/www/html;
        index index.php;

        location ~ ^/(?:application|tools|docs|\.github)(?:/|$) {
            deny all;
        }

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            try_files $uri =404;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param SCRIPT_NAME $fastcgi_script_name;
            fastcgi_pass barama-fpm:9000;
        }
    }
}
EOF

docker network create "$NETWORK_NAME" >/dev/null

docker run --rm -d     --name "$FPM_NAME"     --network "$NETWORK_NAME"     --network-alias barama-fpm     -v "$PACKAGE:/var/www/html"     php:8.2-fpm-alpine >/dev/null

docker run --rm -d     --name "$NGINX_NAME"     --network "$NETWORK_NAME"     -p 18082:80     -v "$PACKAGE:/var/www/html:ro"     -v "$WORK/nginx.conf:/etc/nginx/nginx.conf:ro"     nginx:1.27-alpine >/dev/null

wait_http "http://127.0.0.1:18082/"
assert_status "302" "http://127.0.0.1:18082/"
assert_location "/installer/" "http://127.0.0.1:18082/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18082/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18082/"
assert_status "302" "http://127.0.0.1:18082/properties"
assert_location "/installer/" "http://127.0.0.1:18082/properties"
assert_status "200" "http://127.0.0.1:18082/installer/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18082/installer/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18082/installer/"
assert_header_contains "X-Content-Type-Options" "nosniff" "http://127.0.0.1:18082/installer/"
assert_header_contains "X-Frame-Options" "SAMEORIGIN" "http://127.0.0.1:18082/installer/"
assert_header_contains "Content-Security-Policy" "default-src 'self'" "http://127.0.0.1:18082/installer/"
assert_header_contains "Set-Cookie" "HttpOnly" "http://127.0.0.1:18082/installer/"
assert_header_contains "Set-Cookie" "SameSite=Lax" "http://127.0.0.1:18082/installer/"
assert_status "200" "http://127.0.0.1:18082/assets/css/app.css"
assert_status "403" "http://127.0.0.1:18082/application/config/app.php"

echo "[PASS] Nginx exact-package front-controller routing, installer entry, assets and protected paths verified."

docker stop "$NGINX_NAME" >/dev/null
docker stop "$FPM_NAME" >/dev/null

echo "[INFO] Testing exact customer ZIP in an Nginx subdirectory..."

NGINX_SUBROOT="$WORK/nginx-subroot"
mkdir -p "$NGINX_SUBROOT/barama"
cp -a "$PACKAGE/." "$NGINX_SUBROOT/barama/"

cat > "$WORK/nginx-subdir.conf" <<'EOF'
events {}

http {
    server {
        listen 80;
        server_name _;
        root /var/www/html;
        index index.php;

        location ~ ^/barama/(?:application|tools|docs|\.github)(?:/|$) {
            deny all;
        }

        location /barama/ {
            try_files $uri $uri/ /barama/index.php?$query_string;
        }

        location ~ \.php$ {
            try_files $uri =404;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param SCRIPT_NAME $fastcgi_script_name;
            fastcgi_pass barama-fpm:9000;
        }
    }
}
EOF

docker run --rm -d     --name "$FPM_NAME"     --network "$NETWORK_NAME"     --network-alias barama-fpm     -v "$NGINX_SUBROOT:/var/www/html"     php:8.2-fpm-alpine >/dev/null

docker run --rm -d     --name "$NGINX_NAME"     --network "$NETWORK_NAME"     -p 18083:80     -v "$NGINX_SUBROOT:/var/www/html:ro"     -v "$WORK/nginx-subdir.conf:/etc/nginx/nginx.conf:ro"     nginx:1.27-alpine >/dev/null

wait_http "http://127.0.0.1:18083/barama/"
assert_status "302" "http://127.0.0.1:18083/barama/"
assert_location "/barama/installer/" "http://127.0.0.1:18083/barama/"
assert_header_contains "Cache-Control" "no-store" "http://127.0.0.1:18083/barama/"
assert_header_contains "X-Robots-Tag" "noindex" "http://127.0.0.1:18083/barama/"
assert_status "302" "http://127.0.0.1:18083/barama/properties"
assert_location "/barama/installer/" "http://127.0.0.1:18083/barama/properties"
assert_status "200" "http://127.0.0.1:18083/barama/installer/"
assert_status "403" "http://127.0.0.1:18083/barama/application/config/app.php"

echo "[PASS] Nginx subdirectory front-controller routing and canonical installer redirect verified."
