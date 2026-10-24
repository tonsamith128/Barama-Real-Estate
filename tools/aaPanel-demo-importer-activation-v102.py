#!/usr/bin/env python3
"""BARAMA_DEMO_API_ACTIVATION_V102: scoped aaPanel v0.9.24 deployment.

Only enables licensed signed demo discovery/download/staging. Never imports
database records, never publishes demo package, never changes signed updater.
The existing animated ring must be present and is kept intact.

python3 -B tools/aaPanel-demo-importer-activation-v102.py --check
python3 -B tools/aaPanel-demo-importer-activation-v102.py --deploy \
  --confirm ENABLE_VERIFIED_DEMO_DOWNLOAD_ONLY
"""
from __future__ import annotations
import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

PKG = Path(__file__).resolve().parents[1]
SITE = Path("/www/wwwroot/baramarealestate.tonsamith.com")
RECEIPTS = Path("/root/Barama-Demo-API-Activation-Backups")
CONFIRM = "ENABLE_VERIFIED_DEMO_DOWNLOAD_ONLY"
CONTROLLER = "application/app/Http/Controllers/DemoImporterController.php"
VIEW = "application/resources/views/workspace/demo-importer.php"
ROUTES = "application/routes/web.php"
JS = "assets/js/barama-portable-auto-updater-v1.js"
NEW = (
    "application/app/Services/DemoPublisherApiV1.php",
    "application/app/Services/DemoPublisherDownloadV1.php",
    "application/app/Services/DemoPublisherNativeArchiveV1.php",
)
SOURCE = (CONTROLLER,) + NEW
EXPECTED_CONTROLLER = "db905a373531435e73aaa34548a991a93dc7d17b2d1dbd2f9fb1986baf1c4f62"
EXPECTED_RING_VIEW = "9f0682b16711c91dc447ece24e23a516a4b271c32ba176a33be4a841eb6e7e73"
EXPECTED_SIGNED_JS = "8385ba6f626a966d53e067089ce101195c401f6dc006ea0a03e8abdf1e48c7f5"
PIN = "be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287"

ROUTE_ANCHOR = "$router->get('/dashboard/system/demo-importer', [DemoImporterController::class, 'index'], $demoAuth);"
ROUTE_EXTRAS = "\n".join((
    "$router->get('/dashboard/system/demo-importer/connection', [DemoImporterController::class, 'connection'], $demoAuth);",
    "$router->get('/dashboard/system/demo-importer/catalog', [DemoImporterController::class, 'catalog'], $demoAuth);",
))
OLD_NOTE = "Publisher integration pending: Manual ZIP verification may be tested, but automatic download and live installation are disabled. No Barama demo package is confirmed as published on TonSamith.com."
NEW_NOTE = ("Official Barama Live Demo is published. Signed ZIP download and private package verification "
            "are enabled; database, media and frontend installation remain disabled until staging acceptance.")
OLD_AUTO = """<button type="submit" class="btool-btn btool-btn-primary" disabled>Check &amp; download Live Demo</button>"""
# Dynamic disabled expression is used in the original view, not static.
OLD_AUTO_EXPR = """<button type="submit" class="btool-btn btool-btn-primary" <?= empty($demoReleaseAccepted) ? 'disabled' : '' ?>>Check &amp; download Live Demo</button>"""
NEW_AUTO_EXPR = """<button type="submit" class="btool-btn btool-btn-primary" <?= empty($demoDownloadEnabled) ? 'disabled' : '' ?>>Download &amp; verify official ZIP</button>"""

def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def require_file(path: Path) -> bytes:
    if path.is_symlink() or not path.is_file() or path.stat().st_size > 3_000_000:
        raise RuntimeError("FILE_UNSAFE_OR_MISSING:" + str(path))
    return path.read_bytes()

def prepare_source() -> dict[str, bytes]:
    result = {rel: require_file(PKG / rel) for rel in SOURCE}
    controller = result[CONTROLLER].decode()
    api = result[NEW[0]].decode()
    if ("public const RELEASE_GATE = 'download_only_staging_pending';" not in controller or
        "DemoPublisherNativeArchiveV1" not in controller or
        "DEMO_NO_PUBLISHED_LIVE_PACKAGE" not in controller or
        "public function install(Request $request)" not in controller or
        "Database and media installation is disabled" not in controller or
        "DemoImportPackageV1" in controller or
        PIN not in api or
        "public function pinnedSigningIdentity()" not in api):
        raise RuntimeError("UNSAFE_OR_INCOMPLETE_SOURCE_GATES")
    return result

def patch_routes(body: str) -> str:
    if body.count(ROUTE_ANCHOR) != 1:
        raise RuntimeError("ROUTES_ANCHOR_CHANGED")
    if '/dashboard/system/demo-importer/catalog' in body:
        raise RuntimeError("ROUTES_ALREADY_MODIFIED")
    return body.replace(ROUTE_ANCHOR, ROUTE_ANCHOR + "\n" + ROUTE_EXTRAS)

def patch_view(body: str) -> str:
    if "BARAMA_DEMO_RING_V101" not in body:
        raise RuntimeError("ANIMATED_RING_MISSING")
    replacements = [
        (OLD_NOTE, NEW_NOTE),
        (OLD_AUTO_EXPR, NEW_AUTO_EXPR),
        ("<?= !empty($demoReleaseAccepted) ? 'Publisher connected' : 'Integration preview' ?>",
         "<?= !empty($demoDownloadEnabled) ? 'Verified download available' : 'Integration preview' ?>"),
        ("$demoRingMode === 'auto' && empty($demoReleaseAccepted) ? 'pending' : 'ready'",
         "$demoRingMode === 'auto' && empty($demoDownloadEnabled) ? 'pending' : 'ready'"),
        ("'pending' => 'Automatic retrieval is unavailable until the Barama demo release is published.'",
         "'pending' => 'Publisher retrieval is temporarily unavailable.'"),
        ("<span>Package expires</span>", "<span>Signature status</span>"),
        ("Imported sections:", "Packaged sections:"),
    ]
    for old, new in replacements:
        if body.count(old) != 1:
            raise RuntimeError("RING_VIEW_ANCHOR_CHANGED:" + old[:45])
        body = body.replace(old, new)
    return body

def check_site(src: dict[str,bytes]):
    if SITE.is_symlink() or not SITE.is_dir():
        raise RuntimeError("SITE_UNAVAILABLE")
    if require_file(SITE / "VERSION").decode().strip() != "0.9.24":
        raise RuntimeError("INSTALLED_VERSION_MISMATCH")
    if sha(require_file(SITE / JS)) != EXPECTED_SIGNED_JS:
        raise RuntimeError("SIGNED_UPDATER_DRIFT_STOP")
    before_controller = require_file(SITE / CONTROLLER)
    before_view = require_file(SITE / VIEW)
    before_routes = require_file(SITE / ROUTES)
    new_controller = src[CONTROLLER]
    live_added = [(SITE / x) for x in NEW]
    ready = (
        sha(before_controller) == EXPECTED_CONTROLLER
        and sha(before_view) == EXPECTED_RING_VIEW
        and all(not x.exists() and not x.is_symlink() for x in live_added)
    )
    if ready:
        patches = {CONTROLLER:new_controller,
                   VIEW:patch_view(before_view.decode()).encode(),
                   ROUTES:patch_routes(before_routes.decode()).encode()}
        patches.update({x:src[x] for x in NEW})
        return patches,"READY"
    # After a first install, exact new service bytes and controller are required.
    installed = (
        before_controller == new_controller
        and all(x.is_file() and not x.is_symlink() and x.read_bytes()==src[x.relative_to(SITE).as_posix()]
                for x in live_added)
        and '/dashboard/system/demo-importer/catalog' in before_routes.decode()
        and "Download &amp; verify official ZIP" in before_view.decode()
        and "BARAMA_DEMO_RING_V101" in before_view.decode()
    )
    if installed:
        return {},"ALREADY_INSTALLED"
    raise RuntimeError("SITE_DRIFT_OR_PARTIAL_INSTALL_STOP")

def lint() -> None:
    php = next((x for x in ("/www/server/php/84/bin/php","/usr/bin/php") if Path(x).is_file()), None)
    if not php: raise RuntimeError("PHP_CLI_UNAVAILABLE")
    for rel in SOURCE:
        proc=subprocess.run([php,"-l",str(PKG/rel)],capture_output=True,text=True,timeout=20)
        if proc.returncode:raise RuntimeError("SOURCE_PHP_LINT_FAIL:"+rel)
    print("PHP_SOURCE_LINT=PASS")

def preflight():
    src=prepare_source()
    lint()
    patch,state=check_site(src)
    print("INSTALLED_VERSION=0.9.24")
    print("SIGNED_AUTO_UPDATER=UNCHANGED_VERIFIED")
    print("ANIMATED_RING=PRESERVED")
    print("PINNED_PUBLISHER_IDENTITY=ENFORCED")
    print("AUTO_PUBLISHER_DISCOVERY=READY")
    print("AUTO_VERIFIED_DOWNLOAD=READY")
    print("LIVE_DATABASE_IMPORT=DISABLED")
    print("MYSQL_WRITE=NO; PUBLISHER_PUBLICATION=NO")
    print("INSTALL_STATE="+state)
    return patch,state

def replace(path: Path,data:bytes):
    if path.is_symlink():raise RuntimeError("TARGET_SYMLINK")
    tmp=path.with_name("." + path.name + ".demo-activation-tmp")
    if tmp.exists() or tmp.is_symlink():raise RuntimeError("TEMP_FILE_COLLISION")
    current=path.stat() if path.exists() else path.parent.stat()
    try:
        with tmp.open("xb") as stream:
            stream.write(data);stream.flush();os.fsync(stream.fileno())
        os.chown(tmp,current.st_uid,current.st_gid)
        os.chmod(tmp,0o644)
        os.replace(tmp,path)
    finally:
        if tmp.exists():tmp.unlink()

def deploy():
    patch,state=preflight()
    if state=="ALREADY_INSTALLED":
        print("DEMO_API_ACTIVATION=ALREADY_INSTALLED")
        return
    RECEIPTS.mkdir(parents=True,mode=0o700,exist_ok=True)
    if RECEIPTS.is_symlink() or stat.S_IMODE(RECEIPTS.stat().st_mode)!=0o700:
        raise RuntimeError("BACKUP_LOCATION_UNSAFE")
    bid=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ-")+os.urandom(4).hex()
    location=RECEIPTS/bid
    location.mkdir(mode=0o700)
    original={}
    for rel in (CONTROLLER,VIEW,ROUTES):
        original[rel]=require_file(SITE/rel)
        target=location/rel
        target.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(SITE/rel,target)
    manifest={"id":bid,"site":str(SITE),"new_sha256":{x:sha(y) for x,y in patch.items()},
              "before_sha256":{x:sha(y) for x,y in original.items()},
              "signed_js":EXPECTED_SIGNED_JS}
    (location/"receipt.json").write_text(json.dumps(manifest,indent=2))
    (location/"receipt.json").chmod(0o600)
    touched=[]
    try:
        for rel,data in patch.items():
            replace(SITE/rel,data)
            touched.append(rel)
        _,state=preflight()
        if state!="ALREADY_INSTALLED":raise RuntimeError("POST_DEPLOY_VERIFICATION_FAILED")
    except Exception:
        for rel in reversed(touched):
            if rel in original:replace(SITE/rel,original[rel])
            else:(SITE/rel).unlink(missing_ok=True)
        print("AUTO_ROLLBACK=ATTEMPTED")
        raise
    print("DEMO_API_ACTIVATION=DEPLOYED")
    print("PRIVATE_BACKUP="+str(location))
    print("ROLLBACK_ID="+bid)
    print("DATABASE_CHANGED=NO")
    print("PACKAGE_DOWNLOAD_PERFORMED=NO")

def main():
    p=argparse.ArgumentParser()
    group=p.add_mutually_exclusive_group(required=True)
    group.add_argument("--check",action="store_true")
    group.add_argument("--deploy",action="store_true")
    p.add_argument("--confirm",default="")
    a=p.parse_args()
    if os.geteuid()!=0:raise RuntimeError("ROOT_REQUIRED")
    if a.check:preflight()
    elif a.deploy:
        if a.confirm != CONFIRM:raise RuntimeError("EXPLICIT_CONFIRMATION_REQUIRED")
        deploy()

if __name__=="__main__":
    try:main()
    except Exception as e:
        print("STOP="+str(e))
        raise SystemExit(1)
