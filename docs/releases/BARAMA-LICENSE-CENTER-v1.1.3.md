# Barama License Center v1.1.3 — green/gold verification and publisher workflow

## Verified ZIP (not yet production deployed)

- `Barama-License-Center-and-Domain-Workflow-v1.1.3.zip`
- SHA-256: `124bd26a861ee0fc338a0f767a842fa1e8457d2210af3114e354b0189cdddacd`
- QA: **29/29 tests passed**, including full install/rollback fixture; same 29 tests passed after a fresh ZIP extraction, with all internal SHA-256 checks passing.
- Full runnable source is inside the separately attached ZIP, not committed as application-source replacements on this branch. This document is a durable specification and release identity record.

## Scoped Barama changes

- A large centered license hero at the top of `System → Auto Updater`, with one primary `Check license` action, strong state feedback and a green/gold shield check **only when the current administrator session has a successful publisher validation**.
- Preserve the trusted admin POST route with Auth, `dashboard.access`, CSRF, a 90-second per-session cooldown, native form fallback, async results, and dismissible top-right toast.
- Animate only real browser-observable steps: preparing, sending request, processing received response and result. No fake progress percentages.
- Add read-only local record, bound/current domains, publisher-check result and link to request assistance/domain replacement from the publisher.
- Classify explicit temporary publisher transport failures using existing `LicenseManagerClient::isTemporaryFailure()`. Map active/revoked/suspended/expired only from a **successful authenticated publisher response** with matching product/domain/installation. Invalid, missing and unsuccessful responses remain unconfirmed; never invent a cause.
- Preserve sensitive activation tokens and private license files; no new update entitlement is implied.

## Code scope, deployment and backups

Exact v1.1.2 baseline required for five files:
1. `application/app/Http/Controllers/SystemCenterController.php`
2. `application/app/Services/PublisherLicenseStatus.php`
3. `application/resources/views/workspace/system-center.php`
4. `assets/css/barama-publisher-license-ux-v112.css`
5. `assets/js/barama-publisher-license-ux-v112.js`

Installer makes a private integrity-verified backup, validates PHP 8.5 syntax, and refuses unknown sources, symlinks, missing protected routes and post-install file changes during rollback. No DB, nginx, Turnstile, main license client or backend publisher files are modified.

## Publisher authoritative management — next phase, not implemented

Known publisher endpoints: `/license/v1/activate`, `/license/v1/validate`, `/license/v1/deactivate`. Do **not** invent edit/revoke/change-domain/delete API paths.

Customer self-service and publisher administration require review of existing Ton Samith License Manager controller, repository, routes and RBAC prior to implementation. Customer should be able to request/perform authorized license edits and domain replacement; only publisher admins can revoke or permanently remove licenses. Require identity proof, cooldowns, ownership, anti-CSRF, audit trails, explicit confirmation, idempotency, atomic binding update and rollback or recovery.

The v1.1.3 kit contains `publisher/IMPLEMENTATION-CONTRACT.md` and optional `publisher/capture-source-readonly.py`. The capture utility only archives an allowlist of PHP source from `/www/wwwroot/tonsamith.com` to a private local ZIP and **does not upload or access runtime secrets or DB**. Operator must review that private ZIP before intentionally sharing for code integration.

## Release gate

After successful local QA, operator must run `--check` on exact live v1.1.2, then `--install` only if preflight passes. Preserve actual rollback ID. Verify centered UI light/dark and real publisher result; verified status may remain unavailable pending separate server-side License Manager investigation.

Draft only. No production deployment from GitHub.


## v1.1.3a portable QA correction — 2026-10-11

- Original v1.1.3 was **not installed**: aaPanel test 06 failed because Node.js was absent. The guarded bash command stopped before live preflight/install.
- Corrected full ZIP: `Barama-License-Center-and-Domain-Workflow-v1.1.3a.zip`.
- ZIP SHA-256: `4acd1b3458b85c8a6651cef19835f9f551e4a5b91b9f03a716a8d7883708d801`.
- **31/31 tests PASS** on build host and 31/31 PASS after a fresh ZIP extraction with `BARAMA_QA_SIMULATE_NO_NODE=1`.
- Node.js `--check` validated the exact bundled payload JS during release build; SHA-256 `b38352cb415f9e7d2760293cfda31a74ea2be764e873a71dbfcb437044e7e993` is independently pinned in portable QA. aaPanel without Node checks that immutable pin; it **does not execute a JS parser on the target server**. Tampered JS is rejected.
- Compared to original v1.1.3, only `tests.py`, `README.md` and generated `SHA256SUMS.txt` differ. **All five application payloads, source fixtures, publisher utilities and `installer.py` are byte-identical**.
- Command order: verify ZIP checksum, extract, check internal SHA256 manifest, run portable tests, run `installer.py --check`, then `--install` only on preflight PASS. Production acceptance still pending.
- No need to install Node.js on the PHP aaPanel host, and no rollback was necessary following the original test failure.
