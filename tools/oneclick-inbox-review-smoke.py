#!/usr/bin/env python3
"""Disposable zero-install reviewer test: no privileged commands or live paths."""
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import time

source = Path(__file__).with_name("oneclick-inbox-review.py")
spec = importlib.util.spec_from_file_location("barama_inbox_review", source)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

now = 1_800_000_000

def ticket(id_char="a"):
    id_ = id_char * 32
    return {
        "schema": "barama-dashboard-review-ticket-v1",
        "request_id": id_, "site": str(module.STAGING),
        "from_version": "0.9.23", "to_version": "0.9.24",
        "archive_sha256": "a" * 64,
        "created_at": now - 10, "expires_at": now + 110,
        "action": "review_only",
    }

def write(path, data):
    path.write_text(json.dumps(data, separators=(",", ":")) + "\n")
    path.chmod(0o600)

with tempfile.TemporaryDirectory(prefix="barama-ticket-fixture-") as dirname:
    parent = Path(dirname)
    parent.chmod(0o711)
    inbox = parent / "inbox"
    inbox.mkdir(mode=0o700)
    p = inbox / ("a" * 32 + ".json")
    write(p, ticket())
    a = module.inspect(parent, inbox, "0.9.23", fixture=True, now=now)
    assert a == (1, 0), a
    print("PASS=legitimate_untrusted_ticket_review")

    forged = ticket()
    forged["action"] = "install_now"
    write(p, forged)
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=forged_install_action_denied")

    forged = ticket()
    forged["archive_sha256"] = "invalid"
    write(p, forged)
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=invalid_archive_hash_denied")

    write(p, ticket())
    assert module.inspect(parent,inbox,"0.9.24",fixture=True,now=now)==(0,1)
    print("PASS=changed_installed_version_denied")

    forged = ticket()
    forged["expires_at"] = now - 1
    write(p, forged)
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=expired_ticket_denied")

    write(p, ticket())
    p.chmod(0o644)
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=insecure_file_mode_denied")

    p.unlink()
    p.symlink_to("/etc/passwd")
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=symlink_ticket_denied")

    p.unlink()
    p.write_bytes(b"x" * 801)
    p.chmod(0o600)
    assert module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)==(0,1)
    print("PASS=oversized_ticket_denied")

    p.unlink()
    inbox.chmod(0o777)
    try:
        module.inspect(parent,inbox,"0.9.23",fixture=True,now=now)
        raise AssertionError("world-writable spool accepted")
    except module.ReviewStop:
        print("PASS=world_writable_inbox_denied")
    inbox.chmod(0o700)

print("ROOT_SIDE_READ_ONLY_REVIEW_SMOKE=PASS")
