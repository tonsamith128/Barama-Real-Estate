#!/usr/bin/env python3
"""Isolated HTTP acceptance for the five-step standalone Laravel installer.

Run only on an ephemeral CI checkout with a dedicated EMPTY MySQL database.
Never run against production or a database with user data.
"""
import http.cookiejar
import os
import pathlib
import re
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASE = "http://127.0.0.1:8765"
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(path, values=None):
    data = urllib.parse.urlencode(values).encode() if values is not None else None
    try:
        with opener.open(BASE + path, data=data, timeout=35) as response:
            return response.status, response.read().decode("utf-8")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8")

def csrf(document):
    match = re.search(r'name="csrf" value="([0-9a-f]{64})"', document)
    assert match, "CSRF token not found"
    return match.group(1)

def post(step, document, fields):
    return request("/installer/?step=" + str(step), {
        "step": str(step),
        "csrf": csrf(document),
        **fields,
    })

assert not (ROOT / ".env").exists(), "Existing .env detected; refusing installer acceptance"
assert not (ROOT / "storage/app/installed.lock").exists(), "Existing installation lock detected"
# The customer enters the domain, not /public or /installer.
# Inspect HTTP 302 without auto-following it.
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None

no_redirects = urllib.request.build_opener(NoRedirect())
try:
    no_redirects.open(BASE + "/", timeout=15)
    raise AssertionError("Unconfigured home did not redirect to installer")
except urllib.error.HTTPError as redirect:
    assert redirect.code == 302, ("Expected 302 from bare domain", redirect.code)
    assert redirect.headers.get("Location") == "/installer/", "Invalid installer redirect"
code, page = request("/installer/?step=1")
assert code == 200 and "1. Requirements" in page, (code, page[:150])
token = (ROOT / "storage/app/installer.token").read_text().strip()
assert len(token) == 64, "Private setup token was not generated"
code, page = request("/installer/?step=4")
assert code == 200 and "1. Requirements" in page, "Unauthenticated step skipping accepted"
code, page = post(1, page, {"token": "0" * 64})
assert code == 200 and "Invalid setup token" in page, "Invalid installer access token accepted"
code, page = post(1, page, {"token": token})
assert code == 200 and "2. Database" in page, (code, page[-800:])
code, page = post(2, page, {
    "db_host": "127.0.0.1",
    "db_port": "3306",
    "db_name": os.environ["INSTALLER_TEST_DB"],
    "db_user": os.environ["INSTALLER_TEST_USER"],
    "db_password": os.environ["INSTALLER_TEST_PASSWORD"],
})
assert code == 200 and "3. Application" in page, (code, page[-800:])
code, page = post(3, page, {"app_url": BASE})
assert code == 200 and "4. Administrator" in page, (code, page[-800:])
code, page = post(4, page, {
    "admin_name": "CI System Administrator",
    "admin_email": "installer-test@example.invalid",
    "admin_password": "Long!IndependentInstallerPassphrase2026",
    "admin_confirm": "Long!IndependentInstallerPassphrase2026",
})
assert code == 200 and "Installation completed" in page, (code, page[-1000:])
assert (ROOT / ".env").exists(), ".env was not created"
assert (ROOT / "storage/app/installed.lock").exists(), "Installation lock missing"
assert not (ROOT / "storage/app/installer.token").exists(), "Installer token was not removed"
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
code, page = request("/installer/?step=1")
assert code == 410 and "already installed" in page, "New browser session can reopen an installed application"
code, page = request("/login")
assert code == 200, (code, page[-500:])
code, home = request("/")
assert code == 200 and "Barama Real Estate" in home, "Installed homepage still redirects to installer"
print("INSTALLER_HTTP_MYSQL_FRESH_INSTALL_PASS")
