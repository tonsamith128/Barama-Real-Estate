#!/usr/bin/env python3
"""Disposable staging digest QA. Never touches an actual website."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

TOOL=Path(__file__).resolve().with_name("oneclick-staging-baseline-comparison.py")
with tempfile.TemporaryDirectory(prefix="barama-release-baseline-fixture-") as tmp:
    # The tool allows a strict 16-hex random temporary clone path, never user-selected live roots.
    root=Path(tmp)
    if len(root.name.split("-")[-1]) != 16:
        raise RuntimeError("FIXTURE_ROOT_FORMAT_CHANGED")
    site=root/"site"
    site.mkdir()
    (site/"VERSION").write_text("0.9.22-dev\n",encoding="utf-8")
    file=site/"application/app/Services/Update.php"
    file.parent.mkdir(parents=True)
    file.write_text("<?php\n// existing\n",encoding="utf-8")
    goodhash=hashlib.sha256(file.read_bytes()).hexdigest()
    plan={
      "schema":"barama-v2-release-source-inventory-v1",
      "product_slug":"barama-real-estate",
      "source_commit":"c"*40,"source_version":"0.9.22-dev",
      "proposed_target_version":"0.9.23","status":"REVIEW_ONLY_NOT_INSTALLABLE",
      "source_files":[{"path":"application/app/Services/Update.php",
                       "source_sha256":"a"*64,"bytes":22,
                       "installed_sha256":None,
                       "installed_baseline":"NOT_CHECKED_AGAINST_STAGING"}],
      "baseline_hashes_verified":False,
      "publisher_signature_created":False,
      "owner_consent_created":False,
      "recovery_checkpoint_verified":False,
      "release_ready":False
    }
    source=root/"source-plan.json"
    source.write_text(json.dumps(plan),encoding="utf-8")
    env=os.environ.copy()
    env["BARAA_RELEASE_BASELINE_FIXTURE"]="DISPOSABLE_SITE_ONLY"
    def check(*more,envinput=env):
        return subprocess.run([sys.executable,str(TOOL),"--inventory",str(source),
                    "--site-root",str(site),*more],env=envinput,capture_output=True,text=True)
    result=check()
    assert result.returncode==0,result.stderr
    out=json.loads(result.stdout)
    assert out["files"][0]["installed_sha256"]==goodhash
    assert out["files"][0]["staging_status"]=="DIFFERENT_SOURCE_CONTENT_REQUIRES_OWNER_REVIEW"
    assert out["status"]=="HASHES_READ_ONLY_NOT_SIGNED_OR_APPROVED"
    assert not out["database_accessed"] and not out["website_writes"] and not out["install_enabled"]
    print("REAL_DISPOSABLE_INSTALLED_FILE_SHA256=PASS")
    assert check(envinput={k:v for k,v in env.items() if k!="BARAA_RELEASE_BASELINE_FIXTURE"}).returncode!=0
    print("EXPLICIT_FIXTURE_BOUNDARY=PASS")
    plan["source_files"][0]["path"]="application/config/secret.php"
    source.write_text(json.dumps(plan),encoding="utf-8")
    assert check().returncode!=0
    print("PROTECTED_CONFIG_DENIED=PASS")
    plan["source_files"][0]["path"]="../etc/passwd"
    source.write_text(json.dumps(plan),encoding="utf-8")
    assert check().returncode!=0
    print("PATH_TRAVERSAL_DENIED=PASS")
    plan["source_files"][0]["path"]="application/app/Services/Update.php"
    source.write_text(json.dumps(plan),encoding="utf-8")
    (site/"VERSION").write_text("0.9.24\n",encoding="utf-8")
    assert check().returncode!=0
    print("STAGING_VERSION_MISMATCH_DENIED=PASS")
    (site/"VERSION").write_text("0.9.22-dev\n",encoding="utf-8")
    file.unlink()
    file.symlink_to("/etc/passwd")
    assert check().returncode!=0
    print("SYMLINKED_INSTALLED_FILE_DENIED=PASS")
print("ONECLICK_STAGING_BASELINE_COMPARISON_SMOKE=PASS")
