# Barama v1.0.17-A11 — Appearance notifications and verified save feedback

Production target: `/dashboard/appearance`.

## Identified issue

Barama's existing AJAX Appearance handler shows the save result within an in-flow `[data-workspace-notice]`, calls `scrollIntoView()`, and reloads the page after 350ms. The message cannot be read reliably. On failed toggles, the checkbox remains changed although the database update was rejected.

## Guarded production hotfix

The separate aaPanel kit contains the **actual modified files**:
- `assets/js/app.js`: fixed notification lifecycle and correct toggle recovery;
- `assets/css/app.css`: Appearance-only top-right fixed toast, matching site Light/Dark themes.

The toast is dismissible, pauses while hovered/focused, remains visible 10 seconds on success or 15 seconds on error, survives successful reload using guarded session storage, and compares saved toggle state with the newly rendered backend state. Failed toggles restore the original checked state. Existing CSRF, authorization, Appearance POST routes, database repository, 18 modules, and public CSS generation are unchanged.

**Actual deployment artifact**: `Barama-Appearance-Feedback-v1.0.17A11-Complete-Kit.zip`
**SHA256**: `b7724d630dafd696801544cb7112a35551b24b5b6c47b25f205c214d602d0193`

Source fingerprints:

| File | Original SHA256 (accepted live A10) | New SHA256 |
| --- | --- | --- |
| `assets/js/app.js` | `5edd8676a61ad75f29c2789605727ebd56ebb1dd792cf624bc59f540129ba7b6` | `3d60a85cd5d70d4c36b7ee2edd6a119b42fd55ef44d19ed4658a3c84538aca04` |
| `assets/css/app.css` | `eb12aa7cf25aabb5b5baa30d0c0e5066bdd45c5c12f51760f2fe2aaf75ee0244` | `081cb8c8a1aa82c9a86f4af9e32f466ecec5e0d7af2e93d168e259cbc1dbcdb5` |

## Real backend Appearance verification

The packaged read-only `barama-v117a11-appearance-status-audit.php` uses Barama's installed bootstrap, reads `appearance.frontend` in a read-only database transaction and generates CSS using the production service. It reports ON/OFF for Preset, Footer, and Motion, selected preset, CSS rule presence for Footer and Motion, stylesheet version, and CSS output bytes. No full user or settings data is exported.

Note: if the selected preset is **Barama default**, enabling Preset can produce little or no visible difference; other named presets apply overrides via `AppearanceManagementService::effective()`.

## QA and limitations

- JavaScript syntax and PHP audit syntax: PASS
- Payload and ZIP integrity and per-file hashes: PASS
- Simulated installer source check, deployment, repeat install, rollback and unexpected source rejection: PASS
- Direct Chromium local fixture navigation was restricted by the test environment: **live authenticated UI QA PENDING**.
- The actual public frontend effect for live Preset/Footer/Motion settings must be checked using read-only audit and browser after installation.

No database migration or database writes from installer.

## Repository note

GitHub `main` does not yet include the deployed A1–A10 production source, so this branch **records the release** but does not overwrite `main`'s older `app.js` or `app.css`. The deployable code is distributed in the guarded ZIP, not committed as app-source changes in this draft PR. Reconcile production first before merging.
