#!/usr/bin/env python3
"""ROOT-SIDE READ-ONLY staging inbox inspection. NEVER runs an installer.

Tickets are untrusted even after PHP Auth+CSRF. Review does not authorize
release download, root execution, or any filesystem/database write.
"""
from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import pwd
import re
import stat
import sys
import time

STAGING = Path("/www/wwwroot/baramastaging.tonsamith.com")
PARENT = Path("/var/lib/barama-oneclick")
INBOX = PARENT / "staging-inbox"
VERSION_RE = re.compile(r"\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?\Z")
SHA_RE = re.compile(r"[a-f0-9]{64}\Z")
ID_RE = re.compile(r"[a-f0-9]{32}\.json\Z")
FIELDS = frozenset({
    "schema", "request_id", "site", "from_version", "to_version",
    "archive_sha256", "created_at", "expires_at", "action",
})

class ReviewStop(Exception):
    pass


def require(value, code):
    if not value:
        raise ReviewStop(code)


def version_tuple(value):
    if not isinstance(value, str) or not VERSION_RE.fullmatch(value):
        raise ReviewStop("TICKET_VERSION_INVALID")
    core = value.split("-", 1)[0]
    return tuple(map(int, core.split(".")))


def validate_ticket(data, filename, installed, now):
    require(isinstance(data, dict) and frozenset(data) == FIELDS, "TICKET_STRUCTURE_INVALID")
    require(data["schema"] == "barama-dashboard-review-ticket-v1", "TICKET_SCHEMA_INVALID")
    require(isinstance(data["request_id"], str) and
            filename == data["request_id"] + ".json" and ID_RE.fullmatch(filename),
            "TICKET_ID_INVALID")
    require(data["site"] == str(STAGING) and data["action"] == "review_only",
            "TICKET_ACTION_REJECTED")
    require(data["from_version"] == installed, "TICKET_BASE_VERSION_CHANGED")
    require(version_tuple(data["to_version"]) > version_tuple(installed),
            "TICKET_TARGET_NOT_NEWER")
    require(isinstance(data["archive_sha256"], str)
            and SHA_RE.fullmatch(data["archive_sha256"]), "TICKET_SHA_INVALID")
    created, expires = data["created_at"], data["expires_at"]
    require(type(created) is int and type(expires) is int, "TICKET_TIME_INVALID")
    require(created <= now + 30 and created >= now - 120
            and expires == created + 120 and expires > now,
            "TICKET_EXPIRED")
    return True


def checked_dirs(parent, inbox, expected_uid, *, fixture=False):
    require(not parent.is_symlink() and not inbox.is_symlink()
            and inbox.parent == parent, "INBOX_PATH_REJECTED")
    require(parent.resolve(strict=True) == parent and
            inbox.resolve(strict=True) == inbox, "INBOX_PATH_UNTRUSTED")
    p = parent.stat()
    i = inbox.stat()
    require(stat.S_ISDIR(p.st_mode) and stat.S_ISDIR(i.st_mode),
            "INBOX_DIRECTORY_INVALID")
    require(stat.S_IMODE(p.st_mode) == 0o711 and
            stat.S_IMODE(i.st_mode) == 0o700, "INBOX_PERMISSIONS_INVALID")
    if not fixture:
        require(p.st_uid == 0 and os.geteuid() == 0, "ROOT_REQUIRED")
    require(i.st_uid == expected_uid, "INBOX_OWNER_INVALID")


def inspect_file(dirfd, name, installed, now, expected_uid):
    require(ID_RE.fullmatch(name) is not None, "TICKET_FILENAME_INVALID")
    flags = os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0)
    flags |= getattr(os, "O_NONBLOCK", 0) | getattr(os, "O_CLOEXEC", 0)
    fd = os.open(name, flags, dir_fd=dirfd)
    try:
        metadata = os.fstat(fd)
        require(stat.S_ISREG(metadata.st_mode) and metadata.st_nlink == 1
                and stat.S_IMODE(metadata.st_mode) == 0o600
                and metadata.st_uid == expected_uid
                and 0 < metadata.st_size <= 800, "TICKET_FILE_UNTRUSTED")
        raw = os.read(fd, 801)
        require(0 < len(raw) <= 800 and len(raw) == metadata.st_size,
                "TICKET_SIZE_INVALID")
        try:
            ticket = json.loads(raw)
        except (ValueError, UnicodeDecodeError):
            raise ReviewStop("TICKET_JSON_INVALID") from None
        require(validate_ticket(ticket, name, installed, now),
                "TICKET_REVIEW_REJECTED")
        return True
    finally:
        os.close(fd)


def inspect(parent, inbox, installed, *, fixture=False, now=None):
    expected_uid = os.geteuid() if fixture else pwd.getpwnam("www").pw_uid
    checked_dirs(parent, inbox, expected_uid, fixture=fixture)
    flags = os.O_RDONLY | getattr(os, "O_DIRECTORY", 0)
    flags |= getattr(os, "O_NOFOLLOW", 0) | getattr(os, "O_CLOEXEC", 0)
    directory = os.open(inbox, flags)
    try:
        require(os.fstat(directory).st_ino == inbox.stat().st_ino,
                "INBOX_DIRECTORY_RACE")
        names = sorted(os.listdir(directory))
        require(len(names) <= 32, "INBOX_CAPACITY_REJECTED")
        good = bad = 0
        for name in names:
            try:
                inspect_file(directory, name, installed,
                             int(time.time()) if now is None else now, expected_uid)
                good += 1
            except (ReviewStop, OSError):
                bad += 1
        return good, bad
    finally:
        os.close(directory)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--review", action="store_true")
    args = parser.parse_args()
    if (args.check and args.review) or not (args.check or args.review):
        parser.error("select exactly one read-only operation")
    try:
        expected_uid = pwd.getpwnam("www").pw_uid
        checked_dirs(PARENT, INBOX, expected_uid)
        require(STAGING.is_dir() and not STAGING.is_symlink(), "STAGING_ROOT_NOT_FOUND")
        version_path = STAGING / "VERSION"
        require(version_path.is_file() and not version_path.is_symlink(),
                "VERSION_PATH_REJECTED")
        version = version_path.read_text(encoding="utf-8").strip()
        version_tuple(version)
        require(version == "0.9.23", "STAGING_BASE_CHANGED_REVIEW_REQUIRED")
        print("ROOT_REVIEW_INBOX_PREFLIGHT=PASS")
        if args.review:
            good, bad = inspect(PARENT, INBOX, version)
            print(f"UNTRUSTED_TICKETS_STRUCTURALLY_VALID={good}")
            print(f"UNTRUSTED_TICKETS_REJECTED={bad}")
        print("INSTALLATION_AUTHORIZED=NO")
        print("PRIVILEGED_WORKER_EXECUTED=NO")
        print("WEBSITE_AND_DATABASE_WRITES=NO")
        print("MODE=READ_ONLY_UNTRUSTED_INBOX_REVIEW")
    except (ReviewStop, OSError, ValueError, KeyError):
        print("ROOT_REVIEW_INBOX_PREFLIGHT=BLOCKED")
        print("INSTALLATION_AUTHORIZED=NO")
        sys.exit(3)


if __name__ == "__main__":
    main()
