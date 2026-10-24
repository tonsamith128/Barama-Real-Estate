#!/usr/bin/env python3
"""BARAMA_SYSTEM_TOOL_AAPANEL_V100: additive, guarded v0.9.24 live deployment.

Run from an extracted GitHub repository archive on aaPanel as root:
  python3 -B tools/aaPanel-system-tool-deploy.py --check
  python3 -B tools/aaPanel-system-tool-deploy.py --deploy \
     --confirm INSTALL_BARAMA_SYSTEM_TOOL_PRODUCTION_ONLY

Never overwrites the signed updater, license manager, databases, or .env.
"""
from __future__ import annotations
import argparse, datetime as dt, hashlib, json, os, pwd, shutil, stat, subprocess, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SITE_DEFAULT = "/www/wwwroot/baramarealestate.tonsamith.com"
RECEIPTS = Path("/root/Barama-System-Tool-Deploy-Backups")
CONFIRM = "INSTALL_BARAMA_SYSTEM_TOOL_PRODUCTION_ONLY"
ROLLBACK = "ROLLBACK_BARAMA_SYSTEM_TOOL_PRODUCTION_ONLY"
ADDITIONS = (
    "application/app/Services/SystemToolService.php",
    "application/app/Http/Controllers/SystemToolController.php",
    "application/resources/views/workspace/system-tool.php",
    "assets/css/barama-system-tool-v100.css",
    "assets/js/barama-system-tool-v100.js",
)
ROUTE = "application/routes/web.php"
SIDEBAR = "application/resources/views/layouts/app.php"
UPDATER_JS = "assets/js/barama-portable-auto-updater-v1.js"
EXPECTED_UPDATER_SHA = "8385ba6f626a966d53e067089ce101195c401f6dc006ea0a03e8abdf1e48c7f5"

ROUTE_IMPORT = "use Barama\\Http\\Controllers\\SystemCenterController;"
ROUTE_MARKER = "/* BARAMA_SYSTEM_CENTER_V100_ADMIN_ROUTES */"
ROUTES = """/* BARAMA_SYSTEM_TOOL_V100_ADMIN_ROUTES — Router also enforces ChangeConfirmation on mutations. */
$toolAuth = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access')];
$toolWrite = [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new CsrfMiddleware()];
$router->get('/dashboard/system/tool', [SystemToolController::class, 'index'], $toolAuth);
$router->get('/dashboard/system/tool/export', [SystemToolController::class, 'export'], $toolAuth);
$router->get('/dashboard/system/tool/download', [SystemToolController::class, 'download'], $toolAuth);
$router->post('/dashboard/system/tool/import', [SystemToolController::class, 'import'], $toolWrite);
$router->post('/dashboard/system/tool/backup', [SystemToolController::class, 'backup'], $toolWrite);
$router->post('/dashboard/system/tool/restore', [SystemToolController::class, 'restore'], $toolWrite);

"""
SIDEBAR_ANCHOR = "                        ['System Health', '/dashboard/system/health', 'check'],"
SIDEBAR_ENTRY = "                        ['Tool', '/dashboard/system/tool', 'settings'], // BARAMA_SYSTEM_TOOL_V100"
ROUTE_ENTRY_IMPORT = "use Barama\\Http\\Controllers\\SystemToolController;"


def check_file(path: Path) -> None:
    if path.is_symlink() or not path.is_file() or path.stat().st_size > 2_000_000:
        raise RuntimeError("UNSAFE_OR_MISSING_FILE:" + str(path))


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def replacement(path: Path, data: bytes) -> None:
    check_file(path) if path.exists() or path.is_symlink() else None
    if path.exists():
        st = path.stat()
    else:
        st = path.parent.stat()
    tmp = path.with_name("." + path.name + ".btool-tmp")
    if tmp.exists() or tmp.is_symlink():
        raise RuntimeError("TEMP_ALREADY_EXISTS:" + str(tmp))
    try:
        with tmp.open("xb") as fp:
            fp.write(data)
            fp.flush()
            os.fsync(fp.fileno())
        os.chmod(tmp, 0o644)
        os.chown(tmp, st.st_uid, st.st_gid)
        os.replace(tmp, path)
    finally:
        if tmp.exists(): tmp.unlink()


def source_checks() -> dict[str, bytes]:
    out = {}
    for relative in ADDITIONS:
        p = ROOT / relative
        check_file(p)
        data = p.read_bytes()
        if not data or b"BARAMA_SYSTEM_TOOL_V100" not in data:
            raise RuntimeError("SOURCE_MARKER_MISSING:" + relative)
        out[relative] = data
    return out


def prepare(site: Path):
    if site.is_symlink() or not site.is_dir():
        raise RuntimeError("SITE_UNSAFE")
    if (site / "VERSION").read_text().strip() != "0.9.24":
        raise RuntimeError("STOP_PRODUCTION_VERSION_MUST_BE_0_9_24")
    check_file(site / UPDATER_JS)
    if sha((site / UPDATER_JS).read_bytes()) != EXPECTED_UPDATER_SHA:
        raise RuntimeError("STOP_SIGNED_UPDATER_BASELINE_MISMATCH")
    check_file(site / ROUTE)
    check_file(site / SIDEBAR)
    source = source_checks()
    web = (site / ROUTE).read_text()
    nav = (site / SIDEBAR).read_text()
    if ROUTES.strip() in web and SIDEBAR_ENTRY in nav:
        if all((site / n).is_file() and not (site / n).is_symlink() and
               (site / n).read_bytes() == content for n, content in source.items()):
            return source, web, nav, "ALREADY_INSTALLED"
        raise RuntimeError("PARTIALLY_INSTALLED_OR_DRIFTED")
    if ("BARAMA_SYSTEM_TOOL_V100" in web or SIDEBAR_ENTRY in nav
            or ROUTE_ENTRY_IMPORT in web or
            any((site / n).exists() or (site / n).is_symlink() for n in ADDITIONS)):
        raise RuntimeError("PARTIALLY_INSTALLED_OR_DRIFTED")
    if (web.count(ROUTE_IMPORT) != 1 or web.count(ROUTE_MARKER) != 1 or
            nav.count(SIDEBAR_ANCHOR) != 1):
        raise RuntimeError("ROUTE_OR_SIDEBAR_ANCHOR_CHANGED")
    for relative in ADDITIONS:
        if not (site / relative).parent.is_dir():
            raise RuntimeError("MISSING_DEPLOYMENT_PARENT:" + relative)
    if not (site / "application/app/Http/Middleware/ChangeConfirmationMiddleware.php").is_file():
        raise RuntimeError("CHANGE_CONFIRMATION_MISSING")
    return source, web, nav, "READY"


def verify_syntax(source: dict[str, bytes]) -> None:
    php = next((p for p in ("/www/server/php/84/bin/php", "/usr/bin/php") if Path(p).is_file()), None)
    if php is None:
        raise RuntimeError("PHP_CLI_NOT_FOUND")
    for relative in ADDITIONS:
        if relative.endswith(".php"):
            proc = subprocess.run([php, "-l", str(ROOT / relative)], capture_output=True, text=True, timeout=15)
            if proc.returncode:
                raise RuntimeError("PHP_SYNTAX_FAILED:" + relative)
    print("PHP_SYNTAX=PASS")


def private_vault(site: Path, create: bool) -> Path:
    real = site.resolve(strict=True)
    root = real.parent / (".barama-system-tools-" + sha(str(real).encode())[:18])
    if root.is_symlink():
        raise RuntimeError("VAULT_SYMLINK_DENIED")
    acct = pwd.getpwnam("www")
    if root.exists():
        st = root.stat()
        if not stat.S_ISDIR(st.st_mode) or stat.S_IMODE(st.st_mode) != 0o700:
            raise RuntimeError("VAULT_PERMISSIONS_UNSAFE")
        if st.st_uid != acct.pw_uid or st.st_gid != acct.pw_gid:
            raise RuntimeError("VAULT_OWNER_MISMATCH")
        return root
    if create:
        root.mkdir(mode=0o700)
        os.chown(root, acct.pw_uid, acct.pw_gid)
        os.chmod(root, 0o700)
    return root


def audit(site: Path) -> tuple[dict[str, bytes], str, str, str]:
    result = prepare(site)
    verify_syntax(result[0])
    vault = private_vault(site, False)
    print("LIVE_INSTALLED_VERSION=0.9.24")
    print("SIGNED_JS_BASELINE=PASS")
    print("CHANGE_CONFIRMATION=REQUIRED_BY_ROUTER")
    print("TOOL_SOURCE_FILES=" + str(len(ADDITIONS)))
    print("PRIVATE_VAULT=" + str(vault))
    print("TOOL_INSTALL_STATE=" + result[3])
    print("DATABASE_WRITE=NO")
    print("UPDATER_MODIFIED=NO")
    return result


def deploy(site: Path) -> None:
    source, web, nav, state = audit(site)
    if state == "ALREADY_INSTALLED":
        print("INSTALL_STATE=ALREADY_INSTALLED")
        return
    RECEIPTS.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(RECEIPTS, 0o700)
    key = dt.datetime.utcnow().strftime("%Y%m%dT%H%M%SZ-") + os.urandom(4).hex()
    backup = RECEIPTS / key
    backup.mkdir(mode=0o700)
    for relative in (ROUTE, SIDEBAR):
        copy = backup / relative
        copy.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(site / relative, copy)
    receipt = {
        "site": str(site), "version": "0.9.24",
        "original_route_sha": sha(web.encode()),
        "original_sidebar_sha": sha(nav.encode()),
        "source_shas": {k: sha(v) for k, v in source.items()},
    }
    (backup / "receipt.json").write_text(json.dumps(receipt, indent=2))
    os.chmod(backup / "receipt.json", 0o600)
    touched = []
    try:
        for relative, data in source.items():
            replacement(site / relative, data)
            touched.append(relative)
        new_web = web.replace(ROUTE_IMPORT, ROUTE_IMPORT + "\n" + ROUTE_ENTRY_IMPORT)
        new_web = new_web.replace(ROUTE_MARKER, ROUTES + ROUTE_MARKER)
        replacement(site / ROUTE, new_web.encode())
        touched.append(ROUTE)
        new_nav = nav.replace(SIDEBAR_ANCHOR, SIDEBAR_ANCHOR + "\n" + SIDEBAR_ENTRY)
        replacement(site / SIDEBAR, new_nav.encode())
        touched.append(SIDEBAR)
        private_vault(site, True)
        source2, _, _, state2 = audit(site)
        if state2 != "ALREADY_INSTALLED":
            raise RuntimeError("POST_DEPLOY_AUDIT_FAILED")
        print("SYSTEM_TOOL_DEPLOYED=YES")
        print("BACKUP_ID=" + key)
        print("PRIVATE_ROLLBACK=" + str(backup))
        print("PUBLISHER_OR_LICENSE_CHANGED=NO")
        print("DATABASE_CHANGED=NO")
    except Exception:
        for relative in reversed(touched):
            if relative in (ROUTE, SIDEBAR):
                replacement(site / relative, (backup / relative).read_bytes())
            else:
                (site / relative).unlink(missing_ok=True)
        print("AUTO_ROLLBACK_ATTEMPTED=YES")
        raise


def rollback(site: Path, key: str) -> None:
    if not key or len(key) > 50 or any(c not in "0123456789TtZz-abcdef" for c in key):
        raise RuntimeError("ROLLBACK_ID_INVALID")
    folder = RECEIPTS / key
    check_file(folder / "receipt.json")
    receipt = json.loads((folder / "receipt.json").read_text())
    if receipt.get("site") != str(site):
        raise RuntimeError("ROLLBACK_SITE_MISMATCH")
    _, web, nav, state = prepare(site)
    if state != "ALREADY_INSTALLED":
        raise RuntimeError("ROLLBACK_REQUIRES_EXACT_INSTALLED_STATE")
    for relative in (ROUTE, SIDEBAR):
        original = (folder / relative).read_bytes()
        expected = receipt["original_route_sha"] if relative == ROUTE else receipt["original_sidebar_sha"]
        if sha(original) != expected:
            raise RuntimeError("BACKUP_HASH_MISMATCH")
    for relative in ADDITIONS:
        if sha((site / relative).read_bytes()) != receipt["source_shas"][relative]:
            raise RuntimeError("SOURCE_DRIFT_REVIEW_REQUIRED")
    for relative in (ROUTE, SIDEBAR):
        replacement(site / relative, (folder / relative).read_bytes())
    for relative in ADDITIONS:
        (site / relative).unlink()
    # Intentionally retain private snapshots and backups.
    print("SYSTEM_TOOL_CODE_ROLLED_BACK=YES")
    print("PRIVATE_SNAPSHOTS_PRESERVED=YES")


def main() -> None:
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--check", action="store_true")
    group.add_argument("--deploy", action="store_true")
    group.add_argument("--rollback", metavar="ROLLBACK_ID")
    parser.add_argument("--site", default=SITE_DEFAULT)
    parser.add_argument("--confirm", default="")
    args = parser.parse_args()
    if os.geteuid() != 0:
        raise RuntimeError("ROOT_ADMIN_REQUIRED")
    site = Path(args.site)
    if args.check: audit(site)
    elif args.deploy:
        if args.confirm != CONFIRM: raise RuntimeError("OWNER_CONFIRMATION_REQUIRED")
        deploy(site)
    else:
        if args.confirm != ROLLBACK: raise RuntimeError("ROLLBACK_CONFIRMATION_REQUIRED")
        rollback(site, args.rollback)


if __name__ == "__main__":
    try: main()
    except Exception as exc:
        print("STOP_CODE=" + str(exc))
        sys.exit(1)
