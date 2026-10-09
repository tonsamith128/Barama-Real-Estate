# v1.0.17-A2 Admin Verify form-action hotfix

Production baseline: Barama v1.0.17-A1. Only `assets/js/app.js` is changed.

## Observed bug

A verified route `POST /dashboard/users/{id}/verify` exists and unauthenticated POSTs to it and to the existing status route both return 302, but the authenticated browser sees 404.

The shared AJAX submit handler chooses `submitter.formAction || form.action`. In Chromium, `formAction` can resolve to the page URL even if a submit button has no `formaction` attribute. The handler therefore POSTs to `/dashboard/users` instead of `/dashboard/users/{id}/verify`.

## Repair

Use `submitter.formAction` only if `formaction` is explicitly present. Likewise use `submitter.formMethod` only if `formmethod` is explicitly present. Update both occurrences in the shared JS. Form override support is preserved.

Production SHA-256 guard:
- Old `assets/js/app.js`: `ea69163ba2c5948307550cdb18af874778d77421822046e9f7eb0a6cb244932c`
- New `assets/js/app.js`: `5edd8676a61ad75f29c2789605727ebd56ebb1dd792cf624bc59f540129ba7b6`

Delivered independently as `Barama-Verify-Form-Action-v1.0.17A2-Complete-Kit.zip` with per-file checksums, guarded read-only check, deployment, rollback, and source-conflict rejection.

## QA

- Node.js syntax: PASS.
- Simulated installation, idempotency, rollback and conflict refusal: PASS.
- Chromium regression: the old expression resolves to the current document URL; the corrected expression resolves to the proper Verify endpoint; explicit button override still works.
- **Authenticated live user verification remains pending.**

## Important GitHub baseline

GitHub default `main` is behind the live manually deployed v1.0.17-A1 production source. This review branch intentionally records a source diff rather than overwriting `main`'s earlier `assets/js/app.js`. Do not merge blindly until production baseline is reconciled.
