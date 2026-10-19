#!/usr/bin/env python3
"""Read-only exact staging source baseline gate for Barama PR #107.

This utility does NOT patch, provision, deploy, sign, install, or execute PHP.
It requires all three known staging v0.9.23 source bytes to remain unchanged.
"""
from __future__ import annotations
import hashlib
import os
from pathlib import Path
import stat
import sys

SITE = Path("/www/wwwroot/baramastaging.tonsamith.com")
EXPECTED = {
    "application/routes/web.php": "44ed9f7bb7e5ae23613d06fc2fa0fb9dec4f24b373bd715efbb93838d3c4d007",
    "application/app/Http/Controllers/SystemCenterController.php": "958640db990fa633be031704cfed4bccfba590c00d591bbfabe8944e809e95f2",
    "application/resources/views/workspace/system-center.php": "ad8fa98223fb32e5beff8a936b905e4cd0ac2411aafc3024324b9c55f7baa1d3",
}

def reject(code: str) -> None:
    print("BASELINE=BLOCKED")
    print("REASON=" + code)
    raise SystemExit(3)

def safe_regular(path: Path) -> os.stat_result:
    try:
        # Refuse symlinks anywhere below SITE, including directory components.
        rel = path.relative_to(SITE)
        for part in (SITE, *(SITE / Path(*rel.parts[:i]) for i in range(1, len(rel.parts)))):
            if part.is_symlink():
                reject("SYMLINK_COMPONENT")
        meta = path.lstat()
    except (OSError, ValueError):
        reject("MISSING_OR_UNSAFE_PATH")
    if not stat.S_ISREG(meta.st_mode) or stat.S_ISLNK(meta.st_mode):
        reject("NOT_REGULAR_FILE")
    return meta

def main() -> None:
    if SITE.is_symlink() or not SITE.is_dir() or SITE.resolve(strict=True) != SITE:
        reject("STAGING_ROOT_INVALID")
    version = SITE / "VERSION"
    safe_regular(version)
    if version.read_bytes().strip() != b"0.9.23":
        reject("VERSION_CHANGED")
    try:
        import pwd
        uid = pwd.getpwnam("www").pw_uid
        gid = pwd.getpwnam("www").pw_gid
    except (ImportError, KeyError):
        reject("WEB_USER_NOT_FOUND")
    for relative, expected in EXPECTED.items():
        file = SITE / relative
        metadata = safe_regular(file)
        if (metadata.st_uid != uid or metadata.st_gid != gid or
                stat.S_IMODE(metadata.st_mode) != 0o644):
            reject("OWNER_OR_MODE_CHANGED:" + relative)
        # Hash the opened file descriptor with O_NOFOLLOW (Linux); verify inode before/after.
        try:
            fd = os.open(file, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
            with os.fdopen(fd, "rb") as handle:
                opened = os.fstat(handle.fileno())
                if (opened.st_dev, opened.st_ino) != (metadata.st_dev, metadata.st_ino):
                    reject("SOURCE_CHANGED_DURING_OPEN")
                h = hashlib.sha256()
                while True:
                    chunk = handle.read(1024 * 1024)
                    if not chunk:
                        break
                    h.update(chunk)
                after = os.fstat(handle.fileno())
                if (opened.st_size, opened.st_mtime_ns, opened.st_ctime_ns) != (after.st_size, after.st_mtime_ns, after.st_ctime_ns):
                    reject("SOURCE_CHANGED_DURING_HASH")
        except OSError:
            reject("SOURCE_READ_FAILED")
        if h.hexdigest() != expected:
            reject("SHA256_MISMATCH:" + relative)
        print("SOURCE=PASS " + relative)
    print("BASELINE=PASS")
    print("STAGING_VERSION=0.9.23")
    print("WEBSITE_WRITES=NO")
    print("DATABASE_WRITES=NO")
    print("INBOX_PROVISIONING=NO")
    print("ROOT_WORKER_EXECUTION=NO")
    print("INSTALLATION=NO")

if __name__ == "__main__":
    main()
