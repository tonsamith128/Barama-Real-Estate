#!/usr/bin/env bash
# Barama one-click: root-private directory bootstrap only.
# NO customer-site writes, SQL, publisher keys, backup or install.
set -Eeuo pipefail
set +x
umask 077
ROOT=/root/Barama-OneClick-Recovery
stop(){ echo "STOP=$1" >&2; exit 1; }
secure(){
  local path="$1" owner mode
  [[ ! -L "$path" && -d "$path" ]] || stop PRIVATE_DIRECTORY_UNTRUSTED
  owner="$(stat -c %u -- "$path")" || stop PRIVATE_DIRECTORY_STAT_FAILED
  mode="$(stat -c %a -- "$path")" || stop PRIVATE_DIRECTORY_STAT_FAILED
  [[ "$owner" == 0 && "$mode" == 700 ]] || stop PRIVATE_DIRECTORY_MODE_UNSAFE
}
preflight(){
  [[ ! -L /root && -d /root && "$(stat -c %u -- /root)" == 0 ]] || stop ROOT_HOME_UNTRUSTED
  [[ "$(( 8#$(stat -c %a -- /root) & 0077 ))" == 0 ]] || stop ROOT_HOME_MODE_UNSAFE
  local path
  for path in "$ROOT" "$ROOT/full-site" "$ROOT/owner-approvals" "$ROOT/owner-approvals/used-nonces"; do
    if [[ -e "$path" || -L "$path" ]]; then
      secure "$path"
      echo "VAULT=READY PATH=$path"
    else
      echo "VAULT=MISSING PATH=$path"
    fi
  done
}
case "$1" in
 --check)
   [[ $# == 1 ]] || stop INVALID_ARGUMENTS
   echo MODE=READ_ONLY_VAULT_PREFLIGHT
   preflight
   key="$ROOT/owner-approvals/owner-trust.json"
   if [[ -L "$key" ]]; then stop OWNER_TRUST_CATALOG_SYMLINK; fi
   if [[ -f "$key" ]]; then
     echo OWNER_KEY_CATALOG=PRESENT_NOT_VERIFIED
   else
     echo OWNER_KEY_CATALOG=NOT_PROVISIONED
   fi
   ;;
 --guard)
   [[ $# == 1 ]] || stop INVALID_ARGUMENTS
   echo UNAPPROVED_PROVISIONING=DENIED
   ;;
 --provision)
   [[ $# == 2 && "$2" == APPROVE_ROOT_PRIVATE_RECOVERY_DIRECTORIES_ONLY ]] || stop EXPLICIT_APPROVAL_REQUIRED
   [[ "$(id -u)" == 0 ]] || stop ROOT_REQUIRED
   preflight
   for path in "$ROOT" "$ROOT/full-site" "$ROOT/owner-approvals" "$ROOT/owner-approvals/used-nonces"; do
     if [[ ! -d "$path" ]]; then
       [[ -d "$(dirname "$path")" && ! -L "$(dirname "$path")" ]] || stop PRIVATE_PARENT_MISSING
       mkdir -m 0700 -- "$path" || stop PRIVATE_DIRECTORY_CREATE_FAILED
     fi
     secure "$path"
   done
   echo ROOT_PRIVATE_RECOVERY_DIRECTORIES=READY
   echo OWNER_PUBLIC_KEY_CREATED=NO
   echo STAGED_RELEASE_CREATED=NO
   ;;
 *) stop INVALID_MODE ;;
esac
echo LIVE_SITE_WRITES=NO
echo MYSQL_ACCESSED=NO
echo BACKUPS_CREATED=NO
echo INSTALLATION_ENABLED=NO
