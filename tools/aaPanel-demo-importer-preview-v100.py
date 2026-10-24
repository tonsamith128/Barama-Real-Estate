#!/usr/bin/env python3
"""Guarded aaPanel v0.9.24 Demo Importer preview deployment.

Preview shows Manual and Auto tabs and permits signature-verified manual
upload/inspection only; Auto retrieval and DB installation are explicitly
disabled until publisher + full staging acceptance. No license/DB changes.

  python3 -B tools/aaPanel-demo-importer-preview-v100.py --check
  python3 -B tools/aaPanel-demo-importer-preview-v100.py --deploy \
    --confirm INSTALL_BARAMA_DEMO_IMPORTER_PREVIEW_ONLY

Roll back with --rollback BACKUP_ID --confirm ROLLBACK_BARAMA_DEMO_IMPORTER_PREVIEW
"""
from __future__ import annotations
import argparse
import datetime
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_SITE = Path("/www/wwwroot/baramarealestate.tonsamith.com")
RECEIPTS = Path("/root/Barama-Demo-Importer-Preview-Backups")
CONFIRM = "INSTALL_BARAMA_DEMO_IMPORTER_PREVIEW_ONLY"
ROLLBACK = "ROLLBACK_BARAMA_DEMO_IMPORTER_PREVIEW"
ADDITIONS = (
    "application/app/Services/DemoImportPackageV1.php",
    "application/app/Http/Controllers/DemoImporterController.php",
    "application/resources/views/workspace/demo-importer.php",
    "assets/css/barama-demo-importer-v100.css",
)
WEB = "application/routes/web.php"
NAV = "application/resources/views/layouts/app.php"
JS = "assets/js/barama-portable-auto-updater-v1.js"
EXPECTED_JS = "8385ba6f626a966d53e067089ce101195c401f6dc006ea0a03e8abdf1e48c7f5"
IMPORT_ANCHOR = "use Barama\\Http\\Controllers\\SystemToolController;"
IMPORT_NEW = "use Barama\\Http\\Controllers\\DemoImporterController;"
ROUTE_MARKER = "/* BARAMA_SYSTEM_CENTER_V100_ADMIN_ROUTES */"
NAV_ANCHOR = "                        ['Tool', '/dashboard/system/tool', 'settings'], // BARAMA_SYSTEM_TOOL_V100"
NAV_ADDITION = "                        ['Demo Importer', '/dashboard/system/demo-importer', 'layers'], // BARAMA_DEMO_IMPORT_V100"
ROUTES = """/* BARAMA_DEMO_IMPORT_V100 — safe preview, enforced by authentication, role and CSRF. */
$demoAuth = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')];
$demoPost = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()];
$router->get('/dashboard/system/demo-importer', [DemoImporterController::class, 'index'], $demoAuth);
$router->post('/dashboard/system/demo-importer/manual', [DemoImporterController::class, 'manual'], $demoPost);
$router->post('/dashboard/system/demo-importer/auto', [DemoImporterController::class, 'automatic'], $demoPost);
$router->post('/dashboard/system/demo-importer/install', [DemoImporterController::class, 'install'], $demoPost);

"""
def digest(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()

def check_file(p: Path) -> None:
    if p.is_symlink() or not p.is_file() or p.stat().st_size > 2_000_000:
        raise RuntimeError("FILE_MISSING_OR_UNSAFE:" + str(p))

def source() -> dict[str, bytes]:
    result: dict[str, bytes] = {}
    for path in ADDITIONS:
        p = ROOT / path
        check_file(p)
        result[path] = p.read_bytes()
    controller = result[ADDITIONS[1]].decode()
    if "public const RELEASE_GATE = 'publisher_pending';" not in controller or \
       "if (self::RELEASE_GATE !== 'accepted')" not in controller or \
       controller.count("if (self::RELEASE_GATE !== 'accepted')") != 2:
        raise RuntimeError("BLOCKED_DEMO_MUTATION_GUARD_NOT_PRESENT")
    view = result[ADDITIONS[2]].decode()
    if "empty($demoReleaseAccepted)" not in view or "Integration preview" not in view:
        raise RuntimeError("DEMO_PREVIEW_WARNING_MISSING")
    return result

def php_lint() -> None:
    php = next((x for x in ["/www/server/php/84/bin/php", "/usr/bin/php"] if Path(x).is_file()), "")
    if not php:
        raise RuntimeError("PHP_CLI_NOT_AVAILABLE")
    for p in ADDITIONS:
        if p.endswith(".php"):
            v = subprocess.run([php,"-l",str(ROOT/p)],capture_output=True,text=True,timeout=20)
            if v.returncode != 0:
                raise RuntimeError("PHP_SYNTAX_INVALID:" + p)
    print("PHP_SYNTAX=PASS")

def ensure_site(site: Path) -> None:
    if site.is_symlink() or not site.is_dir():
        raise RuntimeError("SITE_PATH_UNSAFE")
    if (site/"VERSION").read_text().strip() != "0.9.24":
        raise RuntimeError("STOP_PRODUCTION_VERSION_NOT_0_9_24")
    check_file(site/JS)
    if digest((site/JS).read_bytes()) != EXPECTED_JS:
        raise RuntimeError("SIGNED_UPDATER_BASELINE_MISMATCH")
    for p in (WEB,NAV,"application/app/Http/Controllers/SystemToolController.php",
              "assets/css/barama-system-tool-v100.css"):
        check_file(site/p)
    if "BARAMA_SYSTEM_TOOL_V100" not in (site/NAV).read_text():
        raise RuntimeError("EXISTING_SYSTEM_TOOL_NOT_FOUND")
    for p in ADDITIONS:
        directory=(site/p).parent
        if not directory.is_dir() or directory.is_symlink():
            raise RuntimeError("ADDON_PARENT_UNSAFE:" + p)

def compose(site: Path, files: dict[str, bytes]):
    web=(site/WEB).read_text()
    nav=(site/NAV).read_text()
    if (IMPORT_NEW in web or "BARAMA_DEMO_IMPORT_V100" in web or
            NAV_ADDITION in nav or any((site/p).exists() or (site/p).is_symlink() for p in ADDITIONS)):
        if (IMPORT_NEW in web and ROUTES.strip() in web and NAV_ADDITION in nav and
            all((site/p).is_file() and not (site/p).is_symlink() and
                (site/p).read_bytes() == d for p,d in files.items())):
            return None,None,"ALREADY_INSTALLED"
        raise RuntimeError("PARTIAL_INSTALL_OR_DRIFT_REQUIRES_REVIEW")
    if (web.count(IMPORT_ANCHOR)!=1 or web.count(ROUTE_MARKER)!=1 or nav.count(NAV_ANCHOR)!=1):
        raise RuntimeError("ROUTE_OR_MENU_ANCHOR_CHANGED")
    new_web=web.replace(IMPORT_ANCHOR,IMPORT_ANCHOR+"\n"+IMPORT_NEW)
    new_web=new_web.replace(ROUTE_MARKER,ROUTES+ROUTE_MARKER)
    new_nav=nav.replace(NAV_ANCHOR,NAV_ANCHOR+"\n"+NAV_ADDITION)
    return new_web.encode(),new_nav.encode(),"READY"

def read_only(site:Path):
    files=source()
    php_lint()
    ensure_site(site)
    prepared=compose(site,files)
    print("PRODUCTION_VERSION=0.9.24")
    print("SIGNED_AUTO_UPDATER=VERIFIED_UNCHANGED")
    print("EXISTING_SYSTEM_TOOL=RETAINED")
    print("DEMO_MANUAL=SIGNED_ZIP_PREVIEW_ONLY")
    print("DEMO_AUTO=DISABLED_PUBLISHER_PENDING")
    print("DEMO_DATABASE_IMPORT=DISABLED")
    print("DEPLOY_STATUS="+prepared[2])
    print("DATABASE_WRITES=NO; PUBLISHER_WRITES=NO")
    return files,prepared

def atomic(path:Path,blob:bytes) -> None:
    if path.is_symlink():
        raise RuntimeError("DESTINATION_SYMLINK")
    original = path.stat() if path.is_file() else path.parent.stat()
    tmp=path.with_name("."+path.name+".barama-demo-temp")
    if tmp.exists() or tmp.is_symlink():
        raise RuntimeError("DEPLOY_TEMP_EXISTS")
    try:
        with tmp.open("xb") as f:
            f.write(blob)
            f.flush()
            os.fsync(f.fileno())
        os.chown(tmp,original.st_uid,original.st_gid)
        os.chmod(tmp,0o644)
        os.replace(tmp,path)
    finally:
        if tmp.exists(): tmp.unlink()

def deploy(site:Path):
    files,(web,nav,state)=read_only(site)
    if state=="ALREADY_INSTALLED":
        print("INSTALL_STATE=ALREADY_INSTALLED")
        return
    RECEIPTS.mkdir(parents=True,mode=0o700,exist_ok=True)
    if RECEIPTS.is_symlink() or stat.S_IMODE(RECEIPTS.stat().st_mode)!=0o700:
        raise RuntimeError("BACKUP_ROOT_PERMISSIONS_UNSAFE")
    key=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ-")+os.urandom(4).hex()
    backup=RECEIPTS/key
    backup.mkdir(mode=0o700)
    before={}
    for p in (WEB,NAV):
        before[p]=(site/p).read_bytes()
        target=backup/p
        target.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(site/p,target)
    receipt={"site":str(site),"files_sha256":{p:digest(d) for p,d in files.items()},
             "original_sha256":{p:digest(d) for p,d in before.items()},
             "version":"0.9.24","purpose":"demo-preview-only"}
    rp=backup/"receipt.json"
    rp.write_text(json.dumps(receipt,indent=2))
    rp.chmod(0o600)
    touched=[]
    try:
        for p,d in files.items():
            atomic(site/p,d)
            touched.append(p)
        atomic(site/WEB,web)
        touched.append(WEB)
        atomic(site/NAV,nav)
        touched.append(NAV)
        _,result=read_only(site)
        if result[2]!="ALREADY_INSTALLED":
            raise RuntimeError("POST_INSTALL_VERIFICATION_FAILED")
        print("DEMO_IMPORTER_PREVIEW=DEPLOYED")
        print("BACKUP_ID="+key)
        print("ROLLBACK_PATH="+str(backup))
        print("SIGNED_UPDATER_CHANGED=NO")
        print("CUSTOMER_DATABASE_CHANGED=NO")
    except Exception:
        for p in reversed(touched):
            if p in before:
                atomic(site/p,before[p])
            else:
                (site/p).unlink(missing_ok=True)
        print("DEPLOY_AUTO_ROLLBACK=ATTEMPTED")
        raise

def rollback(site:Path,key:str):
    if not re.fullmatch(r"\d{8}T\d{6}Z-[a-f0-9]{8}",key):
        raise RuntimeError("ROLLBACK_ID_INVALID")
    folder=RECEIPTS/key
    check_file(folder/"receipt.json")
    receipt=json.loads((folder/"receipt.json").read_text())
    if receipt.get("site")!=str(site) or receipt.get("purpose")!="demo-preview-only":
        raise RuntimeError("BACKUP_SITE_OR_SCOPE_MISMATCH")
    _,result=read_only(site)
    if result[2]!="ALREADY_INSTALLED":
        raise RuntimeError("REQUIRES_EXACT_INSTALLED_PREVIEW")
    for p in (WEB,NAV):
        check_file(folder/p)
        if digest((folder/p).read_bytes())!=receipt["original_sha256"][p]:
            raise RuntimeError("BACKUP_HASH_MISMATCH")
    for p in ADDITIONS:
        if digest((site/p).read_bytes())!=receipt["files_sha256"][p]:
            raise RuntimeError("LIVE_FILE_DRIFTED")
    for p in (WEB,NAV):
        atomic(site/p,(folder/p).read_bytes())
    for p in ADDITIONS:
        (site/p).unlink()
    print("DEMO_IMPORTER_PREVIEW=ROLLED_BACK")
    print("EXISTING_SYSTEM_TOOL_AND_UPDATER=PRESERVED")

def main():
    ap=argparse.ArgumentParser()
    action=ap.add_mutually_exclusive_group(required=True)
    action.add_argument("--check",action="store_true")
    action.add_argument("--deploy",action="store_true")
    action.add_argument("--rollback",metavar="BACKUP_ID")
    ap.add_argument("--site",default=str(DEFAULT_SITE))
    ap.add_argument("--confirm",default="")
    opts=ap.parse_args()
    if os.geteuid()!=0: raise RuntimeError("ROOT_REQUIRED")
    site=Path(opts.site)
    if opts.check: read_only(site)
    elif opts.deploy:
        if opts.confirm!=CONFIRM: raise RuntimeError("EXPLICIT_PREVIEW_CONFIRMATION_REQUIRED")
        deploy(site)
    else:
        if opts.confirm!=ROLLBACK: raise RuntimeError("EXPLICIT_ROLLBACK_CONFIRMATION_REQUIRED")
        rollback(site,opts.rollback)

if __name__=="__main__":
    try:main()
    except Exception as e:
        print("STOP="+str(e))
        raise SystemExit(1)
