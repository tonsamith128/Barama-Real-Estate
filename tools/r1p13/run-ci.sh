#!/usr/bin/env bash
set -Eeuo pipefail

# Explicitly RESTRICTED to ephemeral GitHub Actions runners.
# Never invoke on an aaPanel production server or archived production backup.
ROOT="$(cd "$(dirname "$0")/../.." && pwd -P)"
if [[ "$GITHUB_ACTIONS" != "true" || "$GITHUB_WORKSPACE" != "$ROOT" || -z "$GITHUB_RUN_ID" ]]; then
  echo 'STOP=R1P13_NOT_EPHEMERAL_CI' >&2
  exit 1
fi
if [[ -e "$ROOT/application/storage/app/installation/environment.php" || -e "$ROOT/application/storage/app/installation/installed.php" ]]; then
  echo 'STOP=R1P13_SOURCE_HAS_CUSTOMER_RUNTIME_STATE' >&2
  exit 1
fi

RUN_NAME="br13-$GITHUB_RUN_ID-$GITHUB_RUN_ATTEMPT"
NET="barama-r1p13-net-$RUN_NAME"
DB="barama-r1p13-db-$RUN_NAME"
APP="barama-r1p13-app-$RUN_NAME"
IMAGE="barama-r1p13-app:$GITHUB_SHA"
PASS="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"

cleanup() {
  docker rm -fv "$APP" "$DB" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo '========== BUILD TEST-ONLY PHP CONTAINER =========='
docker build -q -f "$ROOT/tools/r1p13/Dockerfile" -t "$IMAGE" "$ROOT" >/dev/null
docker network create --internal --driver bridge "$NET" >/dev/null
[[ "$(docker network inspect "$NET" --format '{{.Internal}}')" == "true" ]] || {
  echo 'STOP=R1P13_INTERNAL_NETWORK_UNVERIFIED' >&2; exit 1;
}
echo 'R1P13_DOCKER_INTERNAL_NETWORK=PASS'

echo '========== START DISPOSABLE DATABASE =========='
docker run -d --name "$DB" --network "$NET" \
  --security-opt no-new-privileges \
  --memory 1536m --cpus 2 --pids-limit 256 \
  --tmpfs /var/lib/mysql:rw,nosuid,nodev,size=1024m \
  -e MYSQL_ROOT_PASSWORD="$PASS" -e MYSQL_ROOT_HOST='%' \
  mysql:8.0 --skip-log-bin >/dev/null

echo '========== START DISPOSABLE PHP RUNTIME =========='
docker run -d --name "$APP" --network "$NET" \
  --read-only --cap-drop ALL --security-opt no-new-privileges \
  --memory 768m --cpus 2 --pids-limit 128 \
  --tmpfs /srv/barama/application/storage:rw,nosuid,nodev,size=128m,uid=65534,gid=65534 \
  --tmpfs /tmp:rw,nosuid,nodev,size=64m,uid=65534,gid=65534 \
  -e BARAMA_R1P13_ISOLATED=1 \
  -e APP_ENV=testing -e MAIL_DRIVER=log \
  -e PACKAGE_DB_HOST="$DB" -e PACKAGE_DB_PORT=3306 \
  -e PACKAGE_DB_USERNAME=root -e PACKAGE_DB_PASSWORD="$PASS" \
  "$IMAGE" >/dev/null

for container in "$APP" "$DB"; do
  [[ -z "$(docker port "$container" 2>/dev/null)" ]] || {
    echo 'STOP=R1P13_PUBLIC_PORT_DETECTED' >&2; exit 1;
  }
  [[ "$(docker inspect "$container" --format '{{json .HostConfig.Binds}}')" == "null" ]] || {
    echo 'STOP=R1P13_HOST_MOUNT_DETECTED' >&2; exit 1;
  }
  [[ "$(docker inspect "$container" --format '{{.HostConfig.NetworkMode}}')" == "$NET" ]] || {
    echo 'STOP=R1P13_WRONG_CONTAINER_NETWORK' >&2; exit 1;
  }
done
echo 'R1P13_NO_PUBLISHED_PORTS=PASS'
echo 'R1P13_NO_HOST_BIND_MOUNTS=PASS'

echo '========== CHECK FOR PRODUCTION STATE BEFORE EXECUTION =========='
docker exec "$APP" php tools/r1p13/guard.php

echo '========== WAIT FOR SANDBOX DATABASE =========='
READY=0
for _ in $(seq 1 90); do
  if docker exec "$DB" mysqladmin ping -h127.0.0.1 -uroot "-p$PASS" --silent >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 1
done
[[ "$READY" == 1 ]] || { echo 'STOP=R1P13_SANDBOX_DATABASE_NOT_READY' >&2; exit 1; }
echo 'R1P13_DISPOSABLE_DATABASE=PASS'

echo '========== INSTALL TEST-ONLY SOURCE INTO DISPOSABLE DATABASE =========='
docker exec "$APP" php tools/browser-package-prepare.php \
  /srv/barama barama_r1p13_sandbox http://127.0.0.1:8092 >/dev/null
echo 'R1P13_TEST_INSTALLATION=PASS'

echo '========== START LOOPBACK-ONLY HTTP SERVER =========='
docker exec -d "$APP" php -S 127.0.0.1:8092 \
  -t /srv/barama /srv/barama/tools/r1p13/router.php

echo '========== VERIFY PUBLIC ROUTES, CSRF AND TEST ADMIN =========='
docker exec "$APP" php tools/r1p13/http-auth-smoke.php

echo 'R1P13_REPOSITORY_SOURCE_HTTP_AUTH_ACCEPTANCE=PASS'
echo 'R1P13_ARCHIVED_PRODUCTION_BACKUP_EXECUTED=NO'
echo 'R1P13_LIVE_SERVER_MODIFIED=NO'
