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

php "$ROOT/tools/browser-package-prepare.php" \
    "$INSTALLED" \
    "$DB_NAME" \
    "http://127.0.0.1:$INSTALLED_PORT"

php -S "127.0.0.1:$FRESH_PORT" \
    -t "$FRESH" "$FRESH/.browser-router.php" \
    >"$TMP/fresh-server.log" 2>&1 &
FRESH_PID=$!

php -S "127.0.0.1:$INSTALLED_PORT" \
    -t "$INSTALLED" "$INSTALLED/.browser-router.php" \
    >"$TMP/installed-server.log" 2>&1 &
INSTALLED_PID=$!

for URL in \
    "http://127.0.0.1:$FRESH_PORT/" \
    "http://127.0.0.1:$INSTALLED_PORT/login"
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
npx playwright install --with-deps chromium firefox webkit >/dev/null

cat > browser-qa.mjs <<'JS'
import { chromium, firefox, webkit } from 'playwright';
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

    const details = blocking.map((item) => {
        const nodes = item.nodes.slice(0, 5).map((node) => {
            const target = Array.isArray(node.target)
                ? node.target.join(' ')
                : String(node.target || '');
            const summary = String(node.failureSummary || '')
                .replace(/\s+/g, ' ')
                .trim();
            return target + (summary ? ' — ' + summary : '');
        }).join(' | ');

        return item.id + ' (' + item.nodes.length + '): ' + nodes;
    }).join(' || ');

    assert(
        blocking.length === 0,
        label + ' accessibility violations: ' + details
    );
};

// Compact Barama headers intentionally show inline icon navigation rather
// than a hamburger. Validate whichever accessible interaction is visible.
const verifyMobileNavigation = async (page, label) => {
    const menu = page.locator('[data-site-menu-toggle]');

    if (await menu.isVisible()) {
        await menu.click();
        assert(
            await menu.getAttribute('aria-expanded') === 'true',
            label + ' mobile navigation did not open.'
        );
        await page.keyboard.press('Escape');
        assert(
            await menu.getAttribute('aria-expanded') === 'false',
            label + ' mobile navigation did not close with Escape.'
        );
        return;
    }

    const navigation = page.locator('.site-header [data-site-navigation]');
    assert(
        await navigation.isVisible(),
        label + ' compact navigation is hidden while hamburger is unavailable.'
    );

    const links = navigation.locator('.main-nav a');
    const count = await links.count();
    assert(count >= 4, label + ' compact navigation has fewer than four links.');

    for (let i = 0; i < 4; i++) {
        const link = links.nth(i);
        assert(await link.isVisible(), label + ' compact navigation link ' + i + ' is hidden.');
        const accessibleName = (
            (await link.getAttribute('aria-label')) || await link.textContent() || ''
        ).trim();
        assert(accessibleName.length > 0, label + ' compact icon link ' + i + ' has no name.');
    }

    const properties = navigation.locator('.main-nav a[href$="/properties"]').first();
    assert(
        await properties.isVisible(),
        label + ' compact Properties navigation is not visible.'
    );
    await properties.click();
    await page.waitForLoadState('networkidle');
    assert(
        new URL(page.url()).pathname === '/properties',
        label + ' compact Properties navigation did not navigate.'
    );
    await noOverflow(page, label + ' compact Properties navigation');
};

// The public theme control is intentionally relocated into the Account
// dropdown. Use real visible interactions; never force-click hidden controls.
const clickPublicThemeToggle = async (page, label) => {
    const toggle = page.locator('[data-theme-toggle]').first();
    assert(await toggle.count() === 1, label + ' theme toggle is missing.');

    if (await toggle.isVisible()) {
        await toggle.click();
        return;
    }

    const panel = page.locator('[data-public-user-panel]').first();
    const accountTrigger = page.locator('[data-public-user-trigger]').first();
    const relocated = panel.locator('[data-theme-toggle]');

    assert(
        await relocated.count() === 1,
        label + ' hidden theme toggle was not relocated into the Account dropdown.'
    );
    assert(
        await accountTrigger.isVisible(),
        label + ' Account dropdown trigger is not visible.'
    );

    await accountTrigger.click();
    assert(
        await accountTrigger.getAttribute('aria-expanded') === 'true',
        label + ' Account dropdown did not expose its expanded state.'
    );
    assert(
        await panel.isVisible() && await relocated.isVisible(),
        label + ' relocated theme toggle was not visible in the opened dropdown.'
    );

    await relocated.click();
    await page.keyboard.press('Escape');
    assert(
        await accountTrigger.getAttribute('aria-expanded') === 'false',
        label + ' Account dropdown did not close with Escape.'
    );
};

const runCrossBrowserCritical = async (browserType, browserName) => {
    const browser = await browserType.launch({ headless: true });

    try {
        for (const viewport of [
            { name: 'mobile', width: 390, height: 844 },
            { name: 'desktop', width: 1440, height: 900 },
        ]) {
            const context = await browser.newContext({ viewport });
            const page = await context.newPage();

            await page.goto(freshBase + '/', { waitUntil: 'networkidle' });
            assert(
                page.url().includes('/installer/'),
                browserName + ' fresh root did not redirect to /installer/ at '
                    + viewport.name
            );
            await noOverflow(
                page,
                browserName + ' installer ' + viewport.name
            );

            for (const path of [
                '/',
                '/properties',
                '/projects',
                '/company',
                '/login',
                '/register',
            ]) {
                const response = await page.goto(installedBase + path, {
                    waitUntil: 'networkidle',
                });

                assert(
                    response && response.ok(),
                    browserName + ' ' + path + ' failed at ' + viewport.name
                );
                await noOverflow(
                    page,
                    browserName + ' ' + path + ' ' + viewport.name
                );
            }

            if (viewport.name === 'mobile') {
                await page.goto(installedBase + '/', {
                    waitUntil: 'networkidle',
                });
                await verifyMobileNavigation(page, browserName);
            } else {
                await page.goto(installedBase + '/', {
                    waitUntil: 'networkidle',
                });
                await page.evaluate(() => {
                    localStorage.setItem('barama-theme', 'light');
                });
                await page.reload({ waitUntil: 'networkidle' });
                await clickPublicThemeToggle(page, browserName);

                assert(
                    await page.locator('html').getAttribute('data-bs-theme')
                        === 'dark',
                    browserName + ' theme toggle did not activate dark mode.'
                );

                await page.reload({ waitUntil: 'networkidle' });
                assert(
                    await page.locator('html').getAttribute('data-bs-theme')
                        === 'dark',
                    browserName + ' dark theme did not persist.'
                );

                await page.goto(installedBase + '/login', {
                    waitUntil: 'networkidle',
                });
                await page.locator('#login-email')
                    .fill('browser-admin@example.test');
                await page.locator('#login-password')
                    .fill('Barama-Browser-QA-123!');
                await page.locator('button[type="submit"]').click();
                await page.waitForLoadState('networkidle');

                assert(
                    !page.url().includes('/login'),
                    browserName + ' administrator login did not complete.'
                );

                for (const path of ['/account', '/developer', '/dashboard', '/dashboard/settings/mail']) {
                    const response = await page.goto(installedBase + path, {
                        waitUntil: 'networkidle',
                    });
                    assert(
                        response && response.ok(),
                        browserName + ' authenticated page failed: ' + path
                    );
                    await noOverflow(
                        page,
                        browserName + ' ' + path + ' desktop'
                    );
                }
            }

            await context.close();
        }

        console.log(
            '[PASS] ' + browserName
            + ' exact-package compatibility: installer, public pages, '
            + 'mobile navigation, theme persistence and authenticated '
            + 'account/developer/admin routes verified.'
        );
    } finally {
        await browser.close();
    }
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
            const agreement = page.locator('[data-terms-agreement]');
            const modal = page.locator('[data-installer-terms-modal]');
            const acceptTerms = page.locator('[data-accept-terms]');
            const installer = page.locator('[data-installer]');

            await agreement.click();
            assert(await modal.isVisible(), 'Installer terms modal did not open from the agreement.');
            await page.keyboard.press('Escape');
            assert(await modal.isHidden(), 'Installer terms modal did not close with Escape.');
            assert(
                await agreement.evaluate((node) => node === document.activeElement),
                'Installer terms focus was not restored to the agreement checkbox.'
            );

            await agreement.click();
            assert(await modal.isVisible(), 'Installer terms modal did not reopen from the agreement.');
            await acceptTerms.click();
            await modal.waitFor({ state: 'hidden' });
            assert(await agreement.isChecked(), 'Accepting terms did not check the agreement.');
            assert(
                await installer.getAttribute('data-terms-reviewed') === '1',
                'Accepting terms did not persist the reviewed state.'
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
            await verifyMobileNavigation(page, 'Chromium');
        }

        await context.close();
    }

    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
    });
    const page = await context.newPage();

    await page.goto(installedBase + '/', { waitUntil: 'networkidle' });

    const localeSelect = page.locator('[data-locale-select]');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        localeSelect.selectOption('km'),
    ]);
    assert(
        await page.locator('html').getAttribute('lang') === 'km',
        'Khmer locale did not update the document language.'
    );
    assert(
        await page.locator('.main-nav').getByText('ទំព័រដើម', { exact: true }).count() === 1,
        'Khmer locale did not render the translated Home label.'
    );

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.locator('[data-locale-select]').selectOption('zh'),
    ]);
    assert(
        await page.locator('html').getAttribute('lang') === 'zh',
        'Chinese locale did not update the document language.'
    );
    assert(
        await page.locator('.main-nav').getByText('首页', { exact: true }).count() === 1,
        'Chinese locale did not render the translated Home label.'
    );

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.locator('[data-locale-select]').selectOption('en'),
    ]);
    assert(
        await page.locator('html').getAttribute('lang') === 'en',
        'English locale was not restored after locale QA.'
    );

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

    // Mail Setup is a customer-facing administrator configuration surface.
    // Exercise the exact package, not just isolated repository unit tests.
    const mailSetupResponse = await page.goto(
        installedBase + '/dashboard/settings/mail',
        { waitUntil: 'networkidle' }
    );
    assert(
        mailSetupResponse && mailSetupResponse.ok(),
        'Authenticated administrator Mail Setup is unavailable.'
    );
    await seriousAccessibility(page, 'admin mail setup');
    assert(
        await page.locator('select[name="mail_driver"]').count() === 1,
        'Mail Setup delivery method selector is missing.'
    );
    await page.locator('select[name="mail_driver"]').selectOption('smtp');
    await page.locator('input[name="mail_from_name"]').fill('Browser Test Mailer');
    await page.locator('input[name="mail_from_address"]').fill('noreply@example.test');
    await page.locator('input[name="mail_host"]').fill('smtp.example.test');
    await page.locator('input[name="mail_port"]').fill('587');
    await page.locator('select[name="mail_encryption"]').selectOption('tls');
    await page.locator('input[name="mail_username"]').fill('browser.qa.private@gmail.com');
    const qaMailPassword = 'Browser-QA-Encrypted-SMTP-Only-123!';
    await page.locator('input[name="mail_password"]').fill(qaMailPassword);
    await page.locator('button[type="submit"]').filter({ hasText: 'Save Mail Setup' }).click();
    await page.waitForLoadState('networkidle');
    assert(
        page.url().includes('/dashboard/settings/mail')
            && page.url().includes('saved=1'),
        'Admin Mail Setup did not persist and redirect successfully.'
    );
    const mailHtml = await page.content();
    assert(
        !mailHtml.includes(qaMailPassword),
        'Saved SMTP secret was exposed in the returned HTML.'
    );
    assert(
        await page.locator('input[name="mail_password"]').inputValue() === '',
        'Saved SMTP password must remain masked/empty in the editor.'
    );
    assert(
        await page.locator('input[name="mail_host"]').inputValue() === 'smtp.example.test',
        'Saved SMTP host was not persisted.'
    );
    assert(
        await page.locator('input[name="mail_username"]').inputValue() === '••••@gmail.com'
            && !mailHtml.includes('browser.qa.private@gmail.com'),
        'Saved Gmail SMTP username was not privacy masked.'
    );
    await page.locator('button[type="submit"]').filter({ hasText: 'Save Mail Setup' }).click();
    await page.waitForLoadState('networkidle');
    assert(
        page.url().includes('saved=1'),
        'Unchanged masked Gmail SMTP identity did not save successfully.'
    );
    assert(
        await page.locator('input[name="mail_username"]').inputValue() === '••••@gmail.com',
        'Saved Gmail SMTP identity was altered by masked edit.'
    );
    // Custom test recipients are no longer tied to the administrator login,
    // which may intentionally use a non-deliverable demo identity. Do not
    // submit this form during QA; it can dispatch a real external email.
    const testRecipient = page.locator('input[name="mail_test_recipient"]');
    assert(
        await testRecipient.count() === 1
            && await testRecipient.getAttribute('type') === 'email'
            && await testRecipient.getAttribute('required') !== null,
        'Mail Setup must require a dedicated validated custom test recipient.'
    );
    assert(
        (await testRecipient.inputValue()) === '',
        'Custom test recipient must never be populated from the demo administrator address.'
    );
    await testRecipient.fill('test.recipient@example.org');
    assert(
        await testRecipient.evaluate((input) => input.checkValidity()),
        'Valid custom test recipient was not accepted in the form.'
    );
    await testRecipient.fill('not-a-real-email');
    assert(
        !(await testRecipient.evaluate((input) => input.checkValidity())),
        'Invalid custom test recipient passed browser validation.'
    );
    await testRecipient.fill('');

    await page.screenshot({
        path: artifacts + '/admin-mail-settings-desktop.png',
        fullPage: true,
    });
    console.log('[PASS] Exact customer Mail Setup: administrator save, reload and password redaction.');
    // Test recipient field must remain readable in both stored themes.
    // The native browser can apply unwanted dark color-scheme input styles.
    const recipientTheme = await page.evaluate(() => {
        const root = document.documentElement;
        const field = document.querySelector('input[name="mail_test_recipient"]');
        const button = document.querySelector('.barama-mail-settings__test-form button[type="submit"]');
        const original = root.getAttribute('data-bs-theme');
        const values = [];
        for (const theme of ['light', 'dark']) {
            root.setAttribute('data-bs-theme', theme);
            values.push({
                theme,
                fieldBackground: getComputedStyle(field).backgroundColor,
                fieldText: getComputedStyle(field).color,
                buttonBackground: getComputedStyle(button).backgroundColor,
                buttonText: getComputedStyle(button).color,
            });
        }
        root.setAttribute('data-bs-theme', original || 'light');
        return values;
    });
    assert(
        recipientTheme[0].fieldBackground === 'rgb(255, 255, 255)'
            && recipientTheme[1].fieldBackground !== recipientTheme[0].fieldBackground,
        'Test recipient field light/dark background treatment is incorrect.'
    );
    assert(
        recipientTheme.every((v) =>
            v.buttonBackground === 'rgb(25, 93, 244)' && v.buttonText === 'rgb(255, 255, 255)'
        ),
        'Send test email button must have the blue background in both themes.'
    );
    console.log('[PASS] Mail Setup custom test field and blue button light/dark contrast.');

    const footerEditor = await page.goto(
        installedBase + '/dashboard/footer',
        { waitUntil: 'networkidle' }
    );
    assert(footerEditor && footerEditor.ok(), 'Dedicated Footer editor is unavailable.');
    await seriousAccessibility(page, 'admin footer editor');
    assert(
        await page.locator('input[name="compliance_heading"]').inputValue() === 'Compliance',
        'Default Compliance heading missing from Footer editor.'
    );
    await page.locator('input[name="copyright"]').fill('Copyright {year} | Barama Real Estate QA');
    await page.locator('input[name="social_facebook"]').fill('https://www.facebook.com/barama-test');
    const [footerSaveResponse] = await Promise.all([
        page.waitForResponse((r) =>
            r.url().includes('/dashboard/footer')
                && r.request().method() === 'POST'
        ),
        page.locator('button[type="submit"]').filter({ hasText: 'Save Footer' }).click(),
    ]);
    const footerSaveResult = await footerSaveResponse.json();
    assert(
        footerSaveResponse.ok() && footerSaveResult?.ok === true,
        'Footer editor did not return a successful workspace JSON response.'
    );
    // The existing workspace form handler reloads after saving; a fresh GET
    // proves data persisted rather than merely remaining in the input DOM.
    await page.waitForTimeout(550);
    await page.goto(installedBase + '/dashboard/footer', { waitUntil: 'networkidle' });
    assert(
        await page.locator('input[name="copyright"]').inputValue()
            === 'Copyright {year} | Barama Real Estate QA',
        'Footer copyright content was not saved to application settings.'
    );
    await page.screenshot({
        path: artifacts + '/admin-footer-editor-desktop.png',
        fullPage: true,
    });
    await page.goto(installedBase + '/', { waitUntil: 'networkidle' });
    assert(
        await page.locator('.site-footer__grid').locator(':scope > *').count() === 4,
        'Public Footer must keep exactly four columns.'
    );
    assert(
        await page.locator('.site-footer').getByRole('navigation', { name: 'Compliance links' })
            .getByRole('link', { name: 'Policy Center' }).count() === 1,
        'Compliance Policy Center link is missing.'
    );
    assert(
        await page.locator('.site-footer a[href$="/policies?=privacy-policy"]').count() === 1
            && await page.locator('.site-footer a[href$="/policies?=terms-of-service"]').count() === 1,
        'Policy Center Compliance links do not follow canonical policy URL format.'
    );
    assert(
        await page.locator('.site-footer__meta').innerText().then((s) =>
            s.includes('Copyright') && s.includes('Barama Real Estate QA')),
        'Configured copyright was not reflected in the public footer.'
    );
    assert(
        await page.locator('.site-footer__social a[aria-label="Facebook"]').count() === 1,
        'Configured social icon link is missing.'
    );
    await page.screenshot({
        path: artifacts + '/public-footer-customized-desktop.png',
        fullPage: true,
    });
    console.log('[PASS] Admin Footer save and public 4-column Compliance/copyright/social parity.');


    const queueResponse = await page.goto(
        installedBase + '/dashboard/mail-queue',
        { waitUntil: 'networkidle' }
    );
    assert(queueResponse && queueResponse.ok(), 'Mail Queue history is unavailable.');
    assert(
        await page.locator('input[name="per_page"]').inputValue() === '10',
        'Mail Queue does not enforce the ten-row pagination contract.'
    );
    await seriousAccessibility(page, 'admin mail queue');
    await page.screenshot({
        path: artifacts + '/admin-mail-queue-desktop.png',
        fullPage: true,
    });

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

    await clickPublicThemeToggle(page, 'Chromium installed homepage');
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

await runCrossBrowserCritical(firefox, 'Firefox');
await runCrossBrowserCritical(webkit, 'WebKit');

console.log(
    '[PASS] Cross-browser exact-package QA: Chromium, Firefox and WebKit '
    + 'critical customer flows verified.'
);
JS

BARAMA_BROWSER_ARTIFACTS="$ARTIFACTS" node browser-qa.mjs