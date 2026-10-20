#!/usr/bin/env python3
"""Read-only publisher release SOURCE inventory. Never creates an update ZIP or signs."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import stat
import subprocess
import sys

VERSION = re.compile(r"[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?\Z")
PATH = re.compile(r"[A-Za-z0-9_./-]{1,512}\Z")
FORBIDDEN = {"storage","config","vendor","node_modules","uploads","private",".git",".env",".htaccess",".user.ini"}
MAX_ITEMS = 8000
MAX_FILE = 10000000
MAX_TOTAL = 2147483648

class Refused(Exception):
    pass

def git(root, *argv):
    try:
        r = subprocess.run(["git", *argv], cwd=root, stdout=subprocess.PIPE,
                           stderr=subprocess.DEVNULL, text=True, timeout=20, check=False)
    except (OSError, subprocess.TimeoutExpired) as err:
        raise Refused("GIT_UNAVAILABLE") from err
    if r.returncode != 0:
        raise Refused("CLEAN_COMMITTED_GIT_REQUIRED")
    return r.stdout.strip()

def allowed(path):
    if path == "VERSION" or not PATH.fullmatch(path):
        return False
    parts = path.split("/")
    if any(not x or x in (".","..") or x.startswith(".") or x.lower() in FORBIDDEN for x in parts):
        return False
    return (path in ("index.php", "updater/index.php", "installer/index.php")
        or (path.startswith(("application/app/","application/routes/","application/resources/views/"))
            and path.endswith(".php"))
        or (path.startswith("assets/css/") and path.endswith(".css"))
        or (path.startswith("assets/js/") and path.endswith(".js")))

def main():
    p=argparse.ArgumentParser(description="Review-only file digest inventory, no signed release")
    p.add_argument("--source-root", required=True)
    p.add_argument("--target-version", required=True)
    p.add_argument("--file", action="append", required=True)
    a=p.parse_args()
    base=Path(a.source_root)
    if not base.is_absolute() or base.is_symlink() or not base.is_dir():
        raise Refused("SOURCE_ROOT_UNTRUSTED")
    root=base.resolve(strict=True)
    if root != base or str(root).startswith(("/www/wwwroot/","/root/Barama-OneClick-Recovery/")):
        raise Refused("SOURCE_SITE_OR_VAULT_NOT_ALLOWED")
    if git(root,"rev-parse","--show-toplevel") != str(root):
        raise Refused("SOURCE_NOT_REPOSITORY_ROOT")
    if git(root,"status","--porcelain","--untracked-files=all"):
        raise Refused("SOURCE_TREE_NOT_CLEAN")
    sha=git(root,"rev-parse","HEAD")
    if not re.fullmatch(r"[a-f0-9]{40}",sha):
        raise Refused("SOURCE_COMMIT_UNTRUSTED")
    version=(root/"VERSION")
    if version.is_symlink() or not version.is_file():
        raise Refused("SOURCE_VERSION_NOT_VERIFIED")
    baseline=version.read_text(encoding="utf-8").strip()
    if not VERSION.fullmatch(baseline) or not VERSION.fullmatch(a.target_version):
        raise Refused("VERSION_INVALID")
    def parts(v):
        return tuple(int(s) for s in v.split("-",1)[0].split("+",1)[0].split("."))
    if parts(a.target_version) <= parts(baseline):
        raise Refused("TARGET_VERSION_NOT_NEWER")
    if len(a.file) < 1 or len(a.file) > MAX_ITEMS:
        raise Refused("REVIEWED_PATH_COUNT_INVALID")
    entries=[]; seen=set(); total=0
    for rel in a.file:
        if not allowed(rel) or rel.lower() in seen:
            raise Refused("UNSAFE_OR_DUPLICATE_RELEASE_PATH")
        seen.add(rel.lower())
        item=root
        for part in rel.split("/"):
            item=item/part
            if item.is_symlink():
                raise Refused("RELEASE_PATH_SYMLINK")
        if not item.is_file() or not stat.S_ISREG(item.stat().st_mode):
            raise Refused("RELEASE_SOURCE_MISSING")
        if git(root,"ls-files","--error-unmatch","--",rel) != rel:
            raise Refused("RELEASE_SOURCE_NOT_COMMITTED")
        size=item.stat().st_size
        if size<1 or size>MAX_FILE or total+size>MAX_TOTAL:
            raise Refused("RELEASE_SOURCE_SIZE_UNSAFE")
        digest=hashlib.sha256(item.read_bytes()).hexdigest()
        entries.append({"path":rel,"source_sha256":digest,"bytes":size,
                        "installed_sha256":None,
                        "installed_baseline":"NOT_CHECKED_AGAINST_STAGING"})
        total+=size
    entries.sort(key=lambda x:x["path"])
    report={"schema":"barama-v2-release-source-inventory-v1",
            "product_slug":"barama-real-estate",
            "source_commit":sha,"source_version":baseline,
            "proposed_target_version":a.target_version,
            "status":"REVIEW_ONLY_NOT_INSTALLABLE",
            "source_files":entries,
            "baseline_hashes_verified":False,
            "publisher_signature_created":False,
            "owner_consent_created":False,
            "recovery_checkpoint_verified":False,
            "release_ready":False}
    print(json.dumps(report,indent=2))
if __name__=="__main__":
    try:
        main()
    except (Refused,OSError,ValueError,UnicodeError) as e:
        print("STOP="+str(e),file=sys.stderr)
        sys.exit(1)
