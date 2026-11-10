#!/usr/bin/env bash
# Read-only, full source comparison against the pinned archived Pure PHP tree.
# Reads source files to hash them; does not disclose contents or write to the site.
# Usage: bash support/barama_aapanel_full_source_compare.sh /www/wwwroot/baramarealestate.tonsamith.com
set -Eeuo pipefail
set +x
umask 077

ROOT="${1:-/www/wwwroot/baramarealestate.tonsamith.com}"
REPO="tonsamith128/Barama-Real-Estate"
ARCHIVE_COMMIT="a0af1e5d4d29f38441e3357ae6001b263f1c79cd"
TMP="$(mktemp -d /tmp/barama-source-compare.XXXXXXXX)"
trap 'rm -rf -- "$TMP"' EXIT

if [ ! -d "$ROOT" ] || [ -L "$ROOT" ]; then
    echo "INVALID_ROOT=NOT_DIRECTORY_OR_SYMLINK"
    exit 2
fi
command -v gh >/dev/null || { echo "GITHUB_CLI_REQUIRED=YES"; exit 3; }
command -v python3 >/dev/null || { echo "PYTHON3_REQUIRED=YES"; exit 3; }

echo "========== READ-ONLY BARAMA ARCHIVED SOURCE COMPARISON =========="
printf 'LEGACY_ROOT=%s\nARCHIVE_COMMIT=%s\n' "$ROOT" "$ARCHIVE_COMMIT"

# GitHub returns only source paths and Git blob hashes, never production secrets.
gh api "repos/$REPO/git/trees/$ARCHIVE_COMMIT?recursive=1" > "$TMP/tree.json"

python3 - "$ROOT" "$TMP/tree.json" <<'PY'
import hashlib
import json
import os
import sys
from pathlib import Path

root = Path(sys.argv[1])
tree = json.loads(Path(sys.argv[2]).read_text())
if tree.get('truncated'):
    raise SystemExit("ARCHIVE_TREE_TRUNCATED=YES")

# Compare the entry point plus first-party PHP source in application/.
# Exclude user/private storage and third-party packages.
def in_scope(p):
    return (
        p == 'index.php' or (
            p.startswith('application/')
            and p.endswith('.php')
            and not any(s in p for s in (
                '/storage/', '/vendor/', '/.git/', '/cache/'
            ))
        )
    )

expected = {
    r['path']: r['sha']
    for r in tree.get('tree', [])
    if r.get('type') == 'blob'
    and isinstance(r.get('path'), str)
    and isinstance(r.get('sha'), str)
    and in_scope(r['path'])
}
if not expected or 'index.php' not in expected:
    raise SystemExit("ARCHIVE_MANIFEST_INVALID=YES")

def local_blob(path):
    size = path.stat().st_size
    digest = hashlib.sha1(f'blob {size}\0'.encode())
    with path.open('rb') as fh:
        for chunk in iter(lambda: fh.read(65536), b''):
            digest.update(chunk)
    return digest.hexdigest()

match = mismatch = missing = symlink = extra = 0
changes = []
for rel, sha in sorted(expected.items()):
    path = root / rel
    if path.is_symlink():
        symlink += 1
        changes.append(f"SYMLINK_SKIPPED {rel}")
    elif not path.is_file():
        missing += 1
        changes.append(f"MISSING {rel}")
    elif local_blob(path) == sha:
        match += 1
    else:
        mismatch += 1
        changes.append(f"DIFFERENT {rel}")

seen = set(expected)
application = root / 'application'
if application.is_dir() and not application.is_symlink():
    for base, dirs, files in os.walk(application, followlinks=False):
        dirs[:] = [
            name for name in dirs
            if not Path(base, name).is_symlink()
            and name not in ('storage', 'vendor', '.git', 'cache')
        ]
        for name in files:
            p = Path(base, name)
            if p.is_symlink():
                continue
            rel = p.relative_to(root).as_posix()
            if in_scope(rel) and rel not in seen:
                extra += 1
                changes.append(f"EXTRA_LOCAL {rel}")

print(f"EXPECTED_ARCHIVED_PHP_SOURCE_FILES={len(expected)}")
print(f"EXACT_MATCH={match}")
print(f"DIFFERENT={mismatch}")
print(f"MISSING={missing}")
print(f"SYMLINK_SKIPPED={symlink}")
print(f"EXTRA_LOCAL={extra}")
for status in changes[:50]:
    print(status)
if len(changes) > 50:
    print(f"OTHER_DIFFERENCES_NOT_DISPLAYED={len(changes)-50}")
print("SECRET_CONTENTS=NOT_PRINTED")
print("DATABASE=NOT_CONNECTED")
print("LIVE_FILES=UNCHANGED")
print("FULL_SCOPED_SOURCE_MATCH=" + (
    "YES" if not (mismatch or missing or symlink or extra) else "NO"
))
PY
echo "========== ARCHIVE COMPARISON COMPLETE =========="
