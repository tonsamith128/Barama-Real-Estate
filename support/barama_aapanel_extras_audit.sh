#!/usr/bin/env bash
# Secret-safe, read-only characterization of five deployed PHP files
# outside the pinned 2026-10-30 legacy Git archive.
# Usage: bash support/barama_aapanel_extras_audit.sh /www/wwwroot/baramarealestate.tonsamith.com
set -Eeuo pipefail
set +x
umask 077

ROOT="${1:-/www/wwwroot/baramarealestate.tonsamith.com}"

if [ ! -d "$ROOT/application" ] || [ -L "$ROOT" ] || [ -L "$ROOT/application" ]; then
    echo "AUDIT_BLOCKED=INVALID_OR_SYMLINK_APPLICATION_DIRECTORY"
    exit 2
fi
command -v python3 >/dev/null 2>&1 || {
    echo "AUDIT_BLOCKED=PYTHON3_REQUIRED"
    exit 3
}

echo "========== BARAMA EXTRA PHP FILES READ-ONLY AUDIT =========="
echo "AUDIT_SCOPE=FIVE_FILES_NOT_PRESENT_IN_PINNED_ARCHIVE"
echo "DATABASE=NOT_CONNECTED"
echo "SOURCE_CONTENTS=NOT_PRINTED"

python3 - "$ROOT" <<'PY'
from pathlib import Path
import hashlib
import os
import shutil
import subprocess
import sys
from datetime import datetime, timezone

root = Path(sys.argv[1])
application = root / "application"

targets = [
    ("application/app/Services/ApprovedCompanyMediaEditPolicy.php",
     ["ApprovedCompanyMediaEditPolicy"]),
    ("application/app/Services/CompanyWorkflowNotificationService.php",
     ["CompanyWorkflowNotificationService"]),
    ("application/app/Services/Updates/ProductionHostWorkerV1.php",
     ["ProductionHostWorkerV1"]),
    ("application/app/Services/Updates/PortableUpdateApplyV1.php",
     ["PortableUpdateApplyV1"]),
    ("application/resources/views/workspace/barama-updater-completed-receipt-v100.php",
     ["barama-updater-completed-receipt-v100", "barama_updater_completed_receipt_v100"]),
]

php = shutil.which("php")
source_files = []
for folder in (application / "app", application / "bootstrap",
               application / "routes", application / "resources" / "views"):
    if not folder.is_dir() or folder.is_symlink():
        continue
    for current, dirs, files in os.walk(folder, followlinks=False):
        dirs[:] = sorted(name for name in dirs if name not in {
            ".git", "vendor", "storage", "cache", "node_modules"
        } and not (Path(current) / name).is_symlink())
        for name in files:
            p = Path(current, name)
            if p.suffix == ".php" and p.is_file() and not p.is_symlink():
                source_files.append(p)
if (root / "index.php").is_file() and not (root / "index.php").is_symlink():
    source_files.append(root / "index.php")

print(f"REFERENCE_SCAN_PHP_FILE_COUNT={len(source_files)}")
for relative, tokens in targets:
    path = root / relative
    print(f"=== FILE {relative} ===")
    if path.is_symlink():
        print("STATE=SYMLINK_SKIPPED")
        continue
    if not path.is_file():
        print("STATE=MISSING")
        continue
    try:
        data = path.read_bytes()
        stat = path.stat()
    except OSError:
        print("STATE=UNREADABLE")
        continue

    digest = hashlib.sha1(f"blob {len(data)}\\0".encode("ascii") + data).hexdigest()
    # SHA-1 is only for Git blob identity; SHA-256 is included separately.
    print("STATE=PRESENT")
    print(f"SIZE_BYTES={len(data)}")
    print(f"SHA256={hashlib.sha256(data).hexdigest()}")
    print(f"GIT_BLOB_SHA1={digest}")
    print(f"FILE_MTIME_UTC={datetime.fromtimestamp(stat.st_mtime, timezone.utc).isoformat()}")
    if php is None:
        print("PHP_SYNTAX=NOT_CHECKED_PHP_CLI_UNAVAILABLE")
    else:
        try:
            result = subprocess.run([php, "-l", str(path)],
                                    capture_output=True, timeout=20, check=False)
            print("PHP_SYNTAX=" + ("PASS" if result.returncode == 0 else "FAIL"))
        except (OSError, subprocess.TimeoutExpired):
            print("PHP_SYNTAX=COULD_NOT_CHECK")

    references = []
    byte_tokens = [t.encode("ascii") for t in tokens]
    for source in source_files:
        if source == path:
            continue
        try:
            contents = source.read_bytes()
        except OSError:
            continue
        if any(token in contents for token in byte_tokens):
            references.append(source.relative_to(root).as_posix())

    print(f"EXPLICIT_REFERENCE_FILE_COUNT={len(references)}")
    for caller in sorted(references)[:25]:
        print(f"REFERENCED_BY={caller}")
    if len(references) > 25:
        print(f"REFERENCES_NOT_SHOWN={len(references) - 25}")

print("REFERENCE_SCAN_LIMITATION=Dynamic_includes_and_runtime_hooks_may_not_be_detected")
print("SECRET_CONTENTS=NOT_PRINTED")
print("LIVE_FILES=UNCHANGED")
print("========== EXTRAS AUDIT COMPLETE ==========")
PY
