#!/usr/bin/env bash
set -Eeuo pipefail

ZIP="${1:-}"

if [[ -z "$ZIP" || ! -f "$ZIP" ]]; then
    echo "ERROR: exact customer ZIP is required." >&2
    exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAMP="$(date +%s)-$$"
TMP="${RUNNER_TEMP:-/tmp}/barama-browser-qa-$STAMP"
FRESH="$TMP/fresh"
INSTALLED="$TMP/installed"
NODE_DIR="$TMP/node"
ARTIFACTS="$ROOT/browser-qa-artifacts"
FRESH_PORT=8091
INSTALLED_PORT=8092
DB_NAME="barama_browser_qa_${GITHUB_RUN_ID:-local}_${GITHUB_RUN_ATTEMPT:-1}"

cleanup() {
    if [[ -n "${FRESH_PID:-}" ]]; then kill "$FRESH_PID" 2>/dev/null || true; fi
    if [[ -n "${INSTALLED_PID:-}" ]]; then kill "$INSTALLED_PID" 2>/dev/null || true; fi
}
trap cleanup EXIT

mkdir -p "$FRESH" "$INSTALLED" "$NODE_DIR" "$ARTIFACTS"
unzip -q "$ZIP" -d "$FRESH"
unzip -q "$ZIP" -d "$INSTALLED"

cat > "$FRESH/.browser-router.php" <<'PHP'
<?php
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}
require __DIR__ . '/index.php';
PHP

cp "$FRESH/.browser-router.php" "$INSTALLED/.browser-router.php"

php "$ROOT/tools/browser-package-prepare.php"     "$INSTALLED"     "$DB_NAME"     "http://127.0.0.1:$INSTALLED_PORT"

php -S "127.0.0.1:$FRESH_PORT"     -t "$FRESH" "$FRESH/.browser-router.php"     >"$TMP/fresh-server.log" 2>&1 &
FRESH_PID=$!

php -S "127.0.0.1:$INSTALLED_PORT"     -t "$INSTALLED" "$INSTALLED/.browser-router.php"     >"$TMP/installed-server.log" 2>&1 &
INSTALLED_PID=$!

for URL in     "http://127.0.0.1:$FRESH_PORT/"     "http://127.0.0.1:$INSTALLED_PORT/login"
do
    READY=0
    for _ in $(seq 1 40); do
        if curl -fsS -o /dev/null "$URL"; then
            READY=1
            break
        fi
        sleep 0.25
    done

    if [[ "$READY" -ne 1 ]]; then
        echo "ERROR: browser QA server did not become ready: $URL" >&2
        exit 1
    fi
done

cd "$NODE_DIR"
npm init -y >/dev/null 2>&1
npm install --silent --no-save playwright@1.55.0 @axe-core/playwright@4.10.2
npx playwright install chromium >/dev/null

cat > browser-qa.mjs <<'JS'
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const freshBase = 'http://127.0.0.1:8091';
const installedBase = 'http://127.0.0.1:8092';
const artifacts = process.env.BARAMA_BROWSER_ARTIFACTS;

const viewports = [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1440, height: 900 },
];

const assert = (condition, message) => {
    if (!condition) {
        throw new Error(message);
    }
};

const noOverflow = async (page, label) => {
    const dimensions = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        innerWidth: window.innerWidth,
    }));

    assert(
        dimensions.scrollWidth <= dimensions.innerWidth + 2,
        label + ' has horizontal overflow: '
            + dimensions.scrollWidth + ' > ' + dimensions.innerWidth
    );
};

const seriousAccessibility = async (page, label) => {
    const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const blocking = result.violations.filter((item) =>
        item.impact === 'critical' || item.impact === 'serious'
    );

    assert(
        blocking.length === 0,
        label + ' accessibility violations: '
            + blocking.map((item) => item.id + ' (' + item.nodes.length + ')').join(', ')
    );
};

const browser = await chromium.launch({ headless: true });

try {
    for (const viewport of viewports) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();

        await page.goto(freshBase + '/', { waitUntil: 'networkidle' });
        assert(
            page.url().includes('/installer/'),
            'Fresh root did not redirect to /installer/ at ' + viewport.name
        );
        await noOverflow(page, 'installer ' + viewport.name);

        if (viewport.name === 'desktop') {
            const opener = page.locator('[data-open-terms]');
            await opener.click();
            const modal = page.locator('[data-installer-terms-modal]');
            assert(await modal.isVisible(), 'Installer terms modal did not open.');
            await page.keyboard.press('Escape');
            assert(await modal.isHidden(), 'Installer terms modal did not close with Escape.');
            assert(
                await opener.evaluate((node) => node === document.activeElement),
                'Installer terms focus was not restored to opener.'
            );

            await page.evaluate(() => localStorage.removeItem('barama-theme'));
            await page.reload({ waitUntil: 'networkidle' });
            const before = await page.locator('html').getAttribute('data-bs-theme');
            await page.locator('[data-theme-toggle]').click();
            const after = await page.locator('html').getAttribute('data-bs-theme');
            assert(before !== after, 'Installer theme toggle did not change theme.');
            await page.reload({ waitUntil: 'networkidle' });
            assert(
                await page.locator('html').getAttribute('data-bs-theme') === after,
                'Installer theme preference did not persist.'
            );

            await page.screenshot({
                path: artifacts + '/installer-desktop-' + after + '.png',
                fullPage: true,
            });
        }

        await context.close();
    }

    const publicPaths = ['/', '/properties', '/projects', '/company', '/login', '/register'];

    for (const viewport of viewports) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();

        for (const path of publicPaths) {
            const response = await page.goto(installedBase + path, {
                waitUntil: 'networkidle',
            });
            assert(response && response.ok(), path + ' failed at ' + viewport.name);
            await noOverflow(page, path + ' ' + viewport.name);
        }

        if (viewport.name === 'mobile') {
            await page.goto(installedBase + '/', { waitUntil: 'networkidle' });
            const menu = page.locator('[data-site-menu-toggle]');
            await menu.click();
            assert(
                await menu.getAttribute('aria-expanded') === 'true',
                'Mobile navigation did not expose expanded state.'
            );
            await page.keyboard.press('Escape');
            assert(
                await menu.getAttribute('aria-expanded') === 'false',
                'Mobile navigation did not close with Escape.'
            );
        }

        await context.close();
    }

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
    });
    const page = await context.newPage();

    await page.goto(installedBase + '/', { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');

    const focusState = await page.evaluate(() => {
        const active = document.activeElement;
        const style = active ? getComputedStyle(active) : null;

        return {
            tag: active?.tagName || '',
            outlineStyle: style?.outlineStyle || '',
            outlineWidth: style?.outlineWidth || '',
        };
    });

    assert(focusState.tag !== 'BODY', 'Keyboard focus did not enter an interactive control.');
    assert(
        focusState.outlineStyle !== 'none' && focusState.outlineWidth !== '0px',
        'Keyboard focus does not have a visible focus indicator.'
    );

    await page.goto(installedBase + '/login', { waitUntil: 'networkidle' });
    await seriousAccessibility(page, 'login');

    const password = page.locator('#login-password');
    const passwordToggle = page.locator('[data-auth-password-toggle]');
    await password.fill('Barama-Browser-QA-123!');
    await passwordToggle.click();
    assert(await password.getAttribute('type') === 'text', 'Password visibility toggle failed.');
    await passwordToggle.click();
    assert(await password.getAttribute('type') === 'password', 'Password hide toggle failed.');

    await page.locator('#login-email').fill('browser-admin@example.test');
    await password.fill('Barama-Browser-QA-123!');
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    assert(
        !page.url().includes('/login'),
        'Browser QA administrator login did not complete.'
    );

    for (const path of ['/account', '/developer', '/dashboard']) {
        const response = await page.goto(installedBase + path, {
            waitUntil: 'networkidle',
        });
        assert(response && response.ok(), 'Authenticated page failed: ' + path);
        await noOverflow(page, path + ' desktop');
        await seriousAccessibility(page, path);

        await page.screenshot({
            path: artifacts + path.replaceAll('/', '-').replace(/^-/, '') + '-desktop.png',
            fullPage: true,
        });
    }

    await page.goto(installedBase + '/dashboard', { waitUntil: 'networkidle' });
    await page.setViewportSize({ width: 390, height: 844 });
    const workspaceMenu = page.locator('[data-workspace-open]');
    await workspaceMenu.click();
    assert(
        await workspaceMenu.getAttribute('aria-expanded') === 'true',
        'Workspace drawer did not expose expanded state.'
    );
    await page.keyboard.press('Escape');
    assert(
        await workspaceMenu.getAttribute('aria-expanded') === 'false',
        'Workspace drawer did not close with Escape.'
    );
    assert(
        await workspaceMenu.evaluate((node) => node === document.activeElement),
        'Workspace drawer did not restore focus to the opener.'
    );
    await noOverflow(page, 'dashboard mobile');

    await page.screenshot({
        path: artifacts + '/dashboard-mobile.png',
        fullPage: true,
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(installedBase + '/', { waitUntil: 'networkidle' });
    await page.evaluate(() => localStorage.setItem('barama-theme', 'light'));
    await page.reload({ waitUntil: 'networkidle' });
    await page.screenshot({
        path: artifacts + '/home-desktop-light.png',
        fullPage: true,
    });

    await page.locator('[data-theme-toggle]').click();
    assert(
        await page.locator('html').getAttribute('data-bs-theme') === 'dark',
        'Installed theme toggle did not activate dark mode.'
    );
    await page.reload({ waitUntil: 'networkidle' });
    assert(
        await page.locator('html').getAttribute('data-bs-theme') === 'dark',
        'Installed dark theme did not persist.'
    );
    await seriousAccessibility(page, 'home dark');

    await page.screenshot({
        path: artifacts + '/home-desktop-dark.png',
        fullPage: true,
    });

    await context.close();

    console.log(
        '[PASS] Exact customer ZIP browser QA: installer, public, auth, account, '
        + 'developer, admin, mobile/tablet/desktop, Light/Dark persistence, '
        + 'visible keyboard focus, drawer/menu Escape behavior, accessibility '
        + 'and horizontal-overflow checks verified.'
    );
} finally {
    await browser.close();
}
JS

BARAMA_BROWSER_ARTIFACTS="$ARTIFACTS" node browser-qa.mjs
