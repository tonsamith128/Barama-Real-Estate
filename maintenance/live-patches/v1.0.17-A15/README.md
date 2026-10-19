# Barama v1.0.17-A15 — Shared icon rail and hover/focus menu labels

## Production request

The accepted A14 release made **Admin** sidebar collapse into a 76px icon rail, but its CSS was only included on `/dashboard`. Customer `/account` and Developer `/developer` still lost their entire sidebar when collapsed; even Admin had no immediate visible menu name because the icons sit inside a clipped scrolling navigation container.

## Actual code changes in guarded aaPanel kit

- `application/resources/views/layouts/app.php` — replace Admin-only A14 stylesheet reference with common desktop workspace rail CSS for all three areas; load independent tooltip JS on workspace pages; retain actual server-rendered navigation labels, `title`, and `aria-label`.
- `assets/css/workspace-sidebar-icon-rail-v117a15.css` (new) — same A14 76px clickable icon rail for all three workspaces; focused/active icon styling; overflow-safe, high-contrast floating tooltip styling.
- `assets/js/workspace-sidebar-tooltips-v117a15.js` (new) — hover and keyboard-focus name tooltips, rendered outside the scrolling sidebar to prevent clipping. Takes text only from existing aria-label (using `textContent`; no HTML injection); restores native title after tooltip closes; hides tooltip on pointer leave, Escape, scrolling, resize, expansion. Existing sidebar collapse JS/localStorage is unchanged.

The old A14 CSS remains on disk (unused) to avoid needlessly deleting historical assets. Mobile (< 992px) retains its existing full drawer. No changes to DB, roles, permissions, route paths, Mapbox, Appearance settings, passcode, or customer/developer account records.

## Release artifact

`Barama-Workspace-Icon-Rail-Tooltips-v1.0.17A15-Complete-Kit.zip`

SHA-256: `1d03bd274a2b9e5095a791203c5b42592d5469bcf30a22ab1ef8a821ce204a7d`.

- Exact source-match guard: A14 layout SHA `fef20b5a58ca224f0652ea30a41bcdc394b9a5513b8eb36cfe41a3d001499c3e`.
- 3 files, backup and guarded rollback.
- No Node requirement for aaPanel runtime.
- Expected source status `MATCHES_V117A14_PRODUCTION`, conflicts 0.

## QA

PHP lint: PASS; JavaScript syntax: PASS; ZIP and checksum integrity: PASS.
Installer source check, simulated deploy, idempotency, rollback, conflict refusal: PASS.
Chromium 1440px and 1024px: Admin/Customer/Developer (all three) collapsed 76px, icons clickable, hover and focus tooltip visible with correct labels, restored expanded sidebar: PASS.
Chromium mobile 768px and 375px: existing drawer unaffected: PASS.

**Authenticated live browser QA remains pending** after the user installs on aaPanel.

## GitHub baseline warning

GitHub main predates manually deployed v1.0.17 A1–A14. This draft PR is a **release record**, not a direct overwrite of main's historical PHP layout. The three real updated source files and deployer are distributed in the separately supplied ZIP. Reconcile the accepted production source before merging application source.
