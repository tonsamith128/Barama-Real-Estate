# Barama v1.0.17-A13 — Appearance passcode replay / toggle repair

**Production target:** `https://baramarealestate.tonsamith.com/dashboard/appearance`

## Confirmed conflict

Two independent handlers were processing the same Appearance form submit:

1. `change-confirmation-v133.js` intercepts a protected submission in the **document capture phase**, calls `preventDefault()`, and opens the six-digit passcode prompt. It issues a short-lived **single-use** grant and replays `requestSubmit()` after approval.
2. The A12 `app.js` Appearance `submit` listener did not check `event.defaultPrevented`, so it **also sent a premature AJAX POST without the grant**, correctly rejected by the middleware with HTTP 428. The approved replay could then race against that rejected request, causing conflicting notifications and switch display.

## A13 patch

- **`assets/js/app.js`**: ignore a submit already prevented in capture. Await the authorized replay; handle explicit custom cancellation event by restoring the last confirmed ON/OFF switch and hidden field.
- **`assets/js/change-confirmation-v133.js`**: send `barama:change-confirmation-cancelled` only on dialog cancellation (including Esc), not on approved replay. Clear stale, consumed grants before a new protected action. Preserve the existing password verification, CSRF, expiry, one-time approval, and passcode rate limits.
- **`application/resources/views/layouts/app.php`**: update passcode JS cache-buster `?v=117a13`. No other template change.

No database migrations or writes during installation; all 18 Appearance modules, source settings, public appearance CSS generation, Mapbox, and existing Catalog routes remain unchanged.

## Exact source SHA-256 fingerprints

| File | Pre-A13 | Post-A13 |
| --- | --- | --- |
| `assets/js/app.js` | `d5fe5ea5c2c9d6bf4767b5854a33af39356ea9c98c39ff55f1b861b3e6313cc2` | `d7e4f184626a0c95c746e36b3a9567ef548e88f34d808a58c0ed81e25d7c5da1` |
| `assets/js/change-confirmation-v133.js` | `0ce1d085a29fd96f6cbdac88e60019075e8b654109ebb448de2a474b696b00d5` | `c394a6cae8f98e859f2983aab07ad03038f33295da9018fd192a5572796b0bbb` |
| `application/resources/views/layouts/app.php` | `89b389c14000809a97038ddec8c1ec55854dc0837c9a16f7d1ac08cce92d3c48` | `21ed53232a8cb27a2423a936996654ebc0f364dfddcb94c9dac961b0176910d2` |

## Deployable release

`Barama-Appearance-Passcode-Replay-v1.0.17A13-Complete-Kit.zip`

SHA256: `0953e95f6469b77a220b7f3a495063ab372b5543fe390f42775e606638841a93`.

The complete modified files, source-hash-guarded `--check`, `--deploy`, `--rollback` installer, private source backup, and checksum manifest are delivered separately to the owner as an aaPanel ZIP.

## QA performed

- NodeJS syntax for both JavaScript files: PASS.
- PHP layout syntax: PASS.
- Chromium simulation with test-only origin shim: PASS for cancel/restore, approved OFF, approved ON, invalid passcode, authorized POST failure; no premature AJAX save; exactly one authorized save.
- Package checksum and archive integrity: PASS.
- Simulated install, idempotency, rollback, source-conflict refusal, tampered-payload refusal: PASS.
- **Production browser and real passcode/session/DB acceptance: PENDING.**

## Repository caution

GitHub `main` remains behind the manually accepted production A1–A12 baseline. **This is a release record, not a direct application-source merge.** Do not replace current live code with older `main` source until reconciliation.
