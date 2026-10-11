# Barama License Validation UX v1.1.2 — Release Record

**Status:** self-contained aaPanel kit built and locally verified; server preflight and production acceptance pending.

## Verified artifact

- Filename: `Barama-Publisher-License-Feedback-and-Lifecycle-v1.1.2.zip`
- SHA-256: `6447b0fc76428961a82f0a07aa4a0fef9d87d03d2538a371eefb54d7c85131dd`
- 11 internal SHA-256-pinned files: README, tests, installer, 2 source fixtures, 2 existing-source replacements, 2 new frontend assets, plus route and existing service fixtures.
- 30/30 local tests passed; 30/30 passed again after fresh ZIP extraction, `sha256sum -c SHA256SUMS.txt` passed.
- PHP (local PHP 8.4) syntax and JS syntax tested; aaPanel PHP 8.5 lint required by production preflight.
- Full fixture installation and guarded hash-verified rollback passed.

## UI changes

- Place the existing, single **Check license** action at the top of `System → Auto Updater` (under the page header).
- Show a theme-aware circular spinner and real browser-side stages: preparing request, contacting publisher, waiting for HTTP response and processing result. No synthetic server-stage claims or fake percentages.
- Display a top-right dismissible toast for success, publisher not confirmed, incomplete local license, binding mismatch, untrusted publisher settings or retry cooldown.
- Native POST-redirect-GET fallback with a no-JavaScript notice; normal JS submissions use a same-origin JSON POST response, no page reload.
- Previously stored status and timestamp remain available. Upstream failure messages and private credentials are never exposed to browser.

## Nonnegotiable security boundaries

- Reuse existing POST route `/dashboard/system/auto-updater/check-license` with AuthMiddleware, dashboard.access permission, CsrfMiddleware.
- One server-side check per 90 seconds per session.
- AJAX detects `X-Requested-With: XMLHttpRequest` and `Accept: application/json`; server returns only an allowlisted state, a safe explanatory message, timestamp, outcome and retry delay with no-store headers.
- No background publisher checks, no new license API, no new sidebar/page, no database/NGINX/Turnstile changes.
- **Active license does not equal update entitlement**. Release download, auto-install and demo import remain disabled.
- `Unable to verify` can arise from a rejected response, remote outage or incompatible upstream response; the UI must not invent a specific root cause.

## Deployment and rollback contract

- Exact pinned v1.1.1 controller and view sources required; refuse changed source files and pre-existing new assets.
- Require Barama app `VERSION=0.9.22-dev`, existing v1.1.1 publisher service and precise protected POST router contract.
- Changed files: `application/app/Http/Controllers/SystemCenterController.php`, `application/resources/views/workspace/system-center.php`.
- New assets: `assets/css/barama-publisher-license-ux-v112.css`, `assets/js/barama-publisher-license-ux-v112.js`.
- Installer never reads persisted license records and makes no network calls; copies private backup before atomic replacement; checksum verification and changed-file rollback refusal.
- Production operator should run package integrity, `python3 -B tests.py`, `python3 -B installer.py --check` and only then `python3 -B installer.py --install`. Retain `ROLLBACK_BACKUP_ID`.

## Source control

This draft stores provenance and release-gate documentation, **not the exact deployed application-source changes**. Full runnable source and QA fixtures are bundled in the conversation ZIP; reconcile and separately check in the source before merging this PR into the product branch.
