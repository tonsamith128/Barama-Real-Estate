# Barama Auto Updater Premium Polish v1.2.1

## Offline release acceptance (not yet installed)

- File: `Barama-Auto-Updater-Premium-Polish-v1.2.1.zip`
- SHA-256: `daa926b5b2825db57f7a234e22c6e60886c248a033bf1f520cda4190f47de98a`
- 44/44 portable tests PASS in original build and again in clean ZIP extraction.
- Internal SHA-256 integrity manifest PASS; ZIP CRC PASS.
- Browser interaction QA PASS in both simulated light/dark contexts: only one Detail row open, Escape closes, the row stays paired after reorder, verified status moves to lower priority, and request progress hides after completion.
- Target version: `v0.9.22-dev`; source-pinned to the **already deployed v1.2.0** Auto Updater view, CSS and JS, plus original publisher/route contracts.
- ZIP is standalone. Full PHP/CSS/JS payload and guarded installer are supplied as the conversation attachment; **this PR is a documentation record, not a full-source commit**.

## Exactly three scoped file updates

1. `application/resources/views/workspace/system-center.php` — unchanged non–Auto-Updater branches; adds initial priority annotations, restrained SVG shield gradient, cache-busting query `?v=121`.
2. `assets/css/barama-auto-updater-premium-v120.css` — compact spacing, refined theme-aware shield/icon, hide completed verification lifecycle; light/dark/mobile and reduced-motion refinement.
3. `assets/js/barama-auto-updater-premium-v120.js` — changes **presentation only**, no network calls or storage; sorts existing row+Detail pairs in response to verified publisher state and hides finished progress while retaining active progress; Detail controls are keyboard accessible.

The existing `LicenseManagerClient`, admin POST/CSRF, private license record, publisher activation token, domain, schema, nginx, Turnstile and signed release/entitlement gates are **not** changed. Verified state is never fabricated by browser caching. First-time checks in a new session can show `Not checked` until a real publisher verification.

## Installation and rollback gate

Operator uploads ZIP to `/root`, verifies SHA-256, unpacks, checks `SHA256SUMS.txt`, runs portable tests, and runs `python3 -B installer.py --check`. Only after `BARAMA_PREMIUM_POLISH_V121_PREFLIGHT=PASS` should `--install` be run.

Installer verifies exact live source checksums, guarded license route, staged PHP syntax in PHP 8.5, and creates a SHA-verified root-private backup of **all three existing files** before replacing them. Emits `ROLLBACK_BACKUP_ID`; `--rollback <id>` refuses if any installed file has since changed. No external network or database operation.

**Production execution and browser acceptance pending.** Stop rather than bypass any `STOP=...`.

## Remaining constraints

Update entitlement and release-signature verification remain unconnected. Download/install automatically remains disabled. Real production browser appearance may differ from isolated-browser fixtures; inspect with user's admin dashboard after deployment.
