#!/usr/bin/env python3
"""Only fixture mock website is ever changed; no live server required."""
import hashlib
import importlib.util
import os
from pathlib import Path
import tempfile

root = Path(__file__).resolve().parents[1]
p = root / "tools/aaPanel-demo-importer-preview-v100.py"
spec = importlib.util.spec_from_file_location("demo_deploy", p)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

src = m.source()
assert len(src) == 4
print("SOURCE_MARKERS_AND_PUBLISHER_GATE=PASS")
assert "public const RELEASE_GATE = 'publisher_pending';" in src[m.ADDITIONS[1]].decode()
assert src[m.ADDITIONS[1]].decode().count("if (self::RELEASE_GATE !== 'accepted')") == 2
print("AUTOMATIC_DOWNLOAD_AND_IMPORT_BLOCKED=PASS")

with tempfile.TemporaryDirectory(prefix="barama-demo-aapanel-") as work:
    site = Path(work) / "baramarealestate.tonsamith.com"
    for rel in [m.WEB, m.NAV, m.JS,
                "application/app/Http/Controllers/SystemToolController.php",
                "assets/css/barama-system-tool-v100.css", *m.ADDITIONS]:
        (site / rel).parent.mkdir(parents=True, exist_ok=True)
    (site / "VERSION").write_text("0.9.24")
    js = b"fixture-signed-updater-file-not-a-production-asset"
    (site / m.JS).write_bytes(js)
    m.EXPECTED_JS = hashlib.sha256(js).hexdigest()
    (site / "application/app/Http/Controllers/SystemToolController.php").write_text("<?php // test only")
    (site / "assets/css/barama-system-tool-v100.css").write_text("/* tool installed */")
    route = m.IMPORT_ANCHOR + "\n" + m.ROUTE_MARKER + "\n"
    nav = m.NAV_ANCHOR + "\n"
    (site / m.WEB).write_text(route)
    (site / m.NAV).write_text(nav)
    result = m.compose(site, src)
    assert result[2] == "READY"
    assert result[0].decode().count("/dashboard/system/demo-importer") == 4
    assert "new CsrfMiddleware()" in result[0].decode()
    print("ROUTES_AND_CSRF=PASS")
    assert m.NAV_ADDITION in result[1].decode()
    print("SYSTEM_MENU_ADDITION=PASS")
    assert all(not (site / x).exists() for x in m.ADDITIONS)
    print("READ_ONLY_TEST_NO_SITE_FILES=PASS")
    for name, data in src.items():
        (site / name).write_bytes(data)
    (site / m.WEB).write_bytes(result[0])
    (site / m.NAV).write_bytes(result[1])
    assert m.compose(site, src)[2] == "ALREADY_INSTALLED"
    print("IDEMPOTENT_INSTALL=PASS")
    (site / m.ADDITIONS[1]).write_text("injected drift")
    try:
        m.compose(site, src)
        raise AssertionError("Drift unexpectedly accepted")
    except RuntimeError as ex:
        assert "PARTIAL_INSTALL_OR_DRIFT" in str(ex)
    print("PARTIAL_DRIFT_REJECTED=PASS")
print("DEMO_PREVIEW_AAPANEL_OFFLINE_TESTS=PASS")
