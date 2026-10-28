#!/usr/bin/env python3
"""Build an immutable source-pinned salabandet.com code-only safety overlay.

Runs on GitHub Actions branch, not on customer's server.
No credentials, database, signing keys, customer files or images in kit.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASE_COMMIT = "e91210461865b46b267b6a1f868fe94a12bb4314"
FILES = (
    "application/app/Http/Controllers/DemoImporterController.php",
    "application/app/Repositories/PolicyCenterRepository.php",
    "application/app/Services/DemoCustomerReleasePolicyV1.php",
    "application/app/Services/DemoPolicySnapshotV1.php",
    "application/app/Services/DemoPublisherNativeArchiveV1.php",
    "application/resources/views/workspace/demo-importer.php",
    "application/resources/views/workspace/policy-builder.php",
)
INSTALLER = "tools/salabandet-demo-cpanel-code-only-apply-v100.php"

def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def git_bytes(commit: str, path: str) -> bytes | None:
    result = subprocess.run(["git", "show", f"{commit}:{path}"], cwd=ROOT, capture_output=True, check=False)
    if result.returncode != 0:
        if b"does not exist" in result.stderr or b"exists on disk" in result.stderr:
            return None
        raise RuntimeError(f"Git baseline unavailable for {path}: {result.stderr.decode(errors='replace')}")
    return result.stdout

def main() -> None:
    head = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    subprocess.run(["git", "cat-file", "-e", f"{BASE_COMMIT}^{{commit}}"], cwd=ROOT, check=True)
    manifest = {
        "kind": "barama-salabandet-demo-policy-code-only-v1",
        "target_domain": "salabandet.com",
        "target_application_version": "0.9.25",
        "baseline_commit": BASE_COMMIT,
        "target_commit": head,
        "files": [],
    }
    entries: dict[str, bytes] = {}
    for path in FILES:
        source = (ROOT / path).read_bytes()
        old = git_bytes(BASE_COMMIT, path)
        manifest["files"].append({
            "path": path,
            "old_sha256": sha(old) if old is not None else None,
            "new_sha256": sha(source),
        })
        entries["payload/" + path] = source
        if not source.startswith(b"<?php") and path.endswith(".php"):
            # View files contain HTML markup and may start with '<section', etc.
            if "/views/" not in path:
                raise RuntimeError(f"Expected PHP source: {path}")
        print(f"{path}: old={(sha(old) if old is not None else 'ABSENT')} new={sha(source)}")
    snapshot = entries["payload/application/app/Services/DemoPolicySnapshotV1.php"]
    policies = entries["payload/application/app/Repositories/PolicyCenterRepository.php"]
    gate = entries["payload/application/app/Services/DemoCustomerReleasePolicyV1.php"]
    if b"recipientTemplates(" not in snapshot or b"imported_demo_template" not in snapshot:
        raise RuntimeError("Private draft policy transform not present")
    if b"demo_template_approved" not in policies or b"imported_demo_template" not in policies:
        raise RuntimeError("Policy publication deny gate not present")
    if b"DEMO_CUSTOMER_SIGNATURE_REQUIRED" not in gate:
        raise RuntimeError("Publisher gate not present")
    entries["manifest.json"] = (json.dumps(manifest, indent=2) + "\n").encode()
    entries["install.php"] = (ROOT / INSTALLER).read_bytes()
    entries["README.txt"] = f"""BARAMA salabandet.com — TARGETED cPanel DEMO IMPORTER SAFETY OVERLAY v1.0.0
GitHub source branch: fix/customer-signed-demo-release-gate-v115-20261025
Release commit: {head}
Baseline commit: {BASE_COMMIT}

This is NOT a full Barama installation or a demo content ZIP.
This is NOT the signed publisher ZIP. Seven code-only changes.
No DB, license, user account, site settings, data import, or publisher change.

INSTALL ONLY ON /home/salalsxn/public_html, VERSION=0.9.25.
The installer refuses any unexpected installed baseline.

1) Upload ZIP to /home/salalsxn/ (NOT public_html) using cPanel File Manager.
2) Extract ZIP in that private directory.
3) In cPanel Terminal or SSH use the *actual folder* after extraction:
     php /home/salalsxn/Barama-Salabandet-Demo-Policy-Safety-CPanel-v1.0.0/install.php --check --root=/home/salalsxn/public_html
   You MUST see PATCH_STATUS=READY, SOURCE_FILES_MATCH_BASELINE=7.
4) If and only if --check is READY, run:
     php /home/salalsxn/Barama-Salabandet-Demo-Policy-Safety-CPanel-v1.0.0/install.php --apply --root=/home/salalsxn/public_html --confirm=INSTALL_SALABANDET_DEMO_SAFETY_CODE_ONLY_V100
   You MUST see CODE_ONLY_PATCH=PASS, DATABASE_CHANGES=NONE.
   The tool automatically creates small private rollback copies of only
   the overwritten code files and prints PRIVATE_ROLLBACK_DIR.
5) If an issue is observed, revert with the saved PRIVATE_ROLLBACK_DIR:
     php <same-kit-dir>/install.php --rollback --root=/home/salalsxn/public_html --backup=<PRIVATE_ROLLBACK_DIR> --confirm=ROLLBACK_SALABANDET_DEMO_SAFETY_CODE_ONLY_V100

No full site or DB backup is necessary for these seven code changes on a
disposable clean customer QA installation. These seven file snapshots are
required for precise automated rollback.

ACCEPTANCE NOT YET AUTHORIZED: Publisher DCP-20261028-BB4A3B29 is still
a signed DRAFT and must be explicitly published/entitled before the importer
can stage or install it. NEVER bypass publisher catalog or license checks.
Also verify the root Policy Center data.json is ABSENT (not application/storage).
Before real import, a database backup is still created automatically by the
native Demo Importer; never disable that required importer safety step.

If cPanel Terminal/SSH is unavailable, do not extract the payload over
existing files manually. Use an authorized deployment mechanism.
""".encode()
    checks = "".join(f"{sha(data)}  {path}\n" for path, data in sorted(entries.items()))
    entries["SHA256SUMS.txt"] = checks.encode()
    out = ROOT / "dist"
    out.mkdir(exist_ok=True)
    dest = out / "Barama-Salabandet-Demo-Policy-Safety-CPanel-v1.0.0.zip"
    prefix = "Barama-Salabandet-Demo-Policy-Safety-CPanel-v1.0.0/"
    with zipfile.ZipFile(dest, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(entries):
            info = zipfile.ZipInfo(prefix + path)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o600 << 16)
            archive.writestr(info, entries[path], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(dest) as z:
        if z.testzip() is not None:
            raise RuntimeError("ZIP CRC failed")
        if len(z.namelist()) != len(entries):
            raise RuntimeError("ZIP entry count mismatch")
    print("KIT_ZIP_SHA256=" + sha(dest.read_bytes()))
    print("KIT_BYTES=" + str(dest.stat().st_size))
    print("KIT_PATH=" + str(dest.relative_to(ROOT)))
    print("CODE_ONLY_CPANEL_PACKAGE_BUILT=PASS")

if __name__ == "__main__":
    main()
