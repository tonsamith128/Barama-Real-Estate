#!/usr/bin/env python3
"""Negative QA: real publisher source inventory must NEVER imply a signed release."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile

SCRIPT=Path(__file__).resolve().with_name("oneclick-release-source-inventory.py")
with tempfile.TemporaryDirectory(prefix="barama-publisher-source-inventory-") as work:
    root=Path(work)/"source"
    root.mkdir()
    code=root/"application/app/Services/ReleaseReview.php"
    code.parent.mkdir(parents=True)
    code.write_text("<?php\n// reviewed release code\n",encoding="utf-8")
    (root/"VERSION").write_text("0.9.22-dev\n",encoding="utf-8")
    def git(*a):
        subprocess.run(["git",*a],cwd=root,check=True,stdout=subprocess.DEVNULL,
                       stderr=subprocess.DEVNULL)
    git("init","-q")
    git("add","VERSION","application/app/Services/ReleaseReview.php")
    git("-c","user.name=Fixture","-c","user.email=fixture@example.invalid",
        "commit","-qm","fixture")
    def run(*a):
        return subprocess.run([sys.executable,str(SCRIPT),"--source-root",str(root),
             "--target-version","0.9.23",*a],capture_output=True,text=True)
    good=run("--file","application/app/Services/ReleaseReview.php")
    assert good.returncode==0,good.stderr
    obj=json.loads(good.stdout)
    assert obj["status"]=="REVIEW_ONLY_NOT_INSTALLABLE"
    assert obj["source_version"]=="0.9.22-dev"
    assert obj["proposed_target_version"]=="0.9.23"
    assert len(obj["source_files"])==1
    assert obj["source_files"][0]["source_sha256"]==hashlib.sha256(code.read_bytes()).hexdigest()
    assert obj["source_files"][0]["installed_sha256"] is None
    assert not obj["baseline_hashes_verified"] and not obj["publisher_signature_created"]
    assert not obj["recovery_checkpoint_verified"] and not obj["release_ready"]
    print("READ_ONLY_COMMITTED_SOURCE_DIGEST=PASS")
    assert run("--file","application/config/secrets.php").returncode!=0
    assert run("--file","../etc/passwd").returncode!=0
    assert run("--file","VERSION").returncode!=0
    print("PROTECTED_PATHS_DENIED=PASS")
    assert run("--file","application/app/Services/ReleaseReview.php",
               "--file","application/app/Services/ReleaseReview.php").returncode!=0
    print("DUPLICATES_DENIED=PASS")
    assert subprocess.run([sys.executable,str(SCRIPT),"--source-root",str(root),
            "--target-version","0.9.22-dev","--file","application/app/Services/ReleaseReview.php"],
            capture_output=True).returncode!=0
    print("VERSION_DOWNGRADE_DENIED=PASS")
    code.write_text("<?php\n// uncommitted change\n",encoding="utf-8")
    assert run("--file","application/app/Services/ReleaseReview.php").returncode!=0
    print("UNCOMMITTED_SOURCE_DENIED=PASS")
    git("checkout","--","application/app/Services/ReleaseReview.php")
    code.unlink()
    code.symlink_to("/etc/passwd")
    assert run("--file","application/app/Services/ReleaseReview.php").returncode!=0
    print("SYMLINK_SOURCE_DENIED=PASS")
print("ONECLICK_RELEASE_SOURCE_INVENTORY_SMOKE=PASS")
