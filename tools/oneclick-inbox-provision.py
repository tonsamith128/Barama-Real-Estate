#!/usr/bin/env python3
"""Explicit owner-controlled staging REVIEW inbox provisioner (no updater execution).

--check is read-only. --provision requires a distinct owner approval phrase and
only creates /var/lib/barama-oneclick/{staging-inbox}; it never changes Barama
website files, databases, publisher releases, systemd/cron, or sudoers.
"""
from __future__ import annotations

import argparse
import os
from pathlib import Path
import pwd
import stat
import sys

STAGING = Path("/www/wwwroot/baramastaging.tonsamith.com")
PARENT = Path("/var/lib/barama-oneclick")
INBOX = PARENT / "staging-inbox"
APPROVAL = "APPROVE_CREATE_STAGING_REVIEW_INBOX_ONLY_NO_INSTALL"

class Stop(Exception):
    pass


def require(condition, code):
    if not condition:
        raise Stop(code)


def current_state():
    require(os.geteuid() == 0, "ROOT_REQUIRED")
    require(not STAGING.is_symlink() and STAGING.is_dir()
            and STAGING.resolve(strict=True) == STAGING, "STAGING_PATH_REJECTED")
    version_path = STAGING / "VERSION"
    require(version_path.is_file() and not version_path.is_symlink()
            and version_path.read_text().strip() == "0.9.23",
            "STAGING_VERSION_CHANGED")
    www = pwd.getpwnam("www").pw_uid
    require(Path("/var/lib").is_dir()
            and not Path("/var/lib").is_symlink()
            and Path("/var/lib").stat().st_uid == 0,
            "VAR_LIB_UNTRUSTED")
    if PARENT.exists() or PARENT.is_symlink():
        require(not PARENT.is_symlink() and PARENT.is_dir()
                and PARENT.resolve(strict=True) == PARENT
                and PARENT.stat().st_uid == 0
                and stat.S_IMODE(PARENT.stat().st_mode) == 0o711,
                "REVIEW_PARENT_UNTRUSTED")
    if INBOX.exists() or INBOX.is_symlink():
        require(PARENT.is_dir()
                and not INBOX.is_symlink()
                and INBOX.is_dir()
                and INBOX.resolve(strict=True) == INBOX
                and INBOX.stat().st_uid == www
                and stat.S_IMODE(INBOX.stat().st_mode) == 0o700,
                "REVIEW_INBOX_UNTRUSTED")
    return www


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--check", action="store_true")
    group.add_argument("--provision", metavar="OWNER_APPROVAL")
    args = parser.parse_args()
    try:
        uid = current_state()
        if args.check:
            print("MODE=READ_ONLY_STAGING_INBOX_PROVISION_PREFLIGHT")
            print("PARENT=" + ("TRUSTED" if PARENT.exists() else "NOT_YET_CREATED"))
            print("INBOX=" + ("TRUSTED" if INBOX.exists() else "NOT_YET_CREATED"))
            print("SITE_FILE_WRITES=NO")
            print("DATABASE_WRITES=NO")
            print("ROOT_WORKER_STARTED=NO")
            return
        require(args.provision == APPROVAL, "EXPLICIT_APPROVAL_REQUIRED")
        original_umask = os.umask(0o077)
        try:
            if not PARENT.exists():
                os.mkdir(PARENT, 0o711)
                os.chmod(PARENT, 0o711)
            require(PARENT.stat().st_uid == 0
                    and stat.S_IMODE(PARENT.stat().st_mode) == 0o711,
                    "PARENT_CONFLICT")
            if not INBOX.exists():
                os.mkdir(INBOX, 0o700)
                os.chown(INBOX, uid, pwd.getpwnam("www").pw_gid)
                os.chmod(INBOX, 0o700)
        finally:
            os.umask(original_umask)
        current_state()
        print("MODE=STAGING_UNTRUSTED_REVIEW_INBOX_PROVISIONED")
        print("STAGING_WEBSITE_FILES_CHANGED=NO")
        print("DATABASE_WRITES=NO")
        print("INSTALL_AUTHORIZATION=NO")
        print("ROOT_WORKER_STARTED=NO")
    except (Stop, OSError, KeyError, ValueError):
        print("STAGING_INBOX_PROVISION=BLOCKED")
        sys.exit(3)


if __name__ == "__main__":
    main()
