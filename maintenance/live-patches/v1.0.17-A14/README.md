# Barama v1.0.17-A14 — Desktop Admin Sidebar Icon Rail

Target: `/dashboard/*` Admin workspace, especially `/dashboard/appearance` and `/dashboard/homepage`.

## Root cause

The existing `workspace-sidebar-is-collapsed` CSS did **`display:none!important`** on the entire sidebar and turned the workspace shell into a single-column grid. The existing JS toggle and localStorage were already functional but hid all clickable navigation icons.

## Scoped changes

1. `application/resources/views/layouts/app.php`: load the dedicated new Admin-only stylesheet *after* the existing workspace scroll sheet, using `$workspaceHeaderArea === 'dashboard'`. Add HTML `title` and `aria-label` attributes to existing sidebar links using the real server-rendered label.
2. `assets/css/admin-sidebar-icon-rail-v117a14.css`: override the desktop (min-width 992px) collapsed CSS so the left sidebar remains a **76px-wide** flex icon rail; hide only text labels, convert section headings to separators, preserve active link state, scrolling, and focus rings. Existing JS and hamburger control are unchanged.

The desktop state persists using the existing `barama.workspace.sidebar.collapsed` localStorage key. On mobile (<992px), the original overlay drawer behavior is left unchanged. All menu hrefs, permissions, account roles, databases, Mapbox, Appearance and passcode protections stay unchanged.

## Guarded installer release
File: `Barama-Admin-Sidebar-Icon-Rail-v1.0.17A14-Complete-Kit.zip`

SHA256: `4fda1222dc2dc4097cfe8dfc22473abc09c11e8e8de552037c6da0e22f8587d1`

Verified production source fingerprints:
- `application/resources/views/layouts/app.php` old `21ed53232a8cb27a2423a936996654ebc0f364dfddcb94c9dac961b0176910d2` → new `fef20b5a58ca224f0652ea30a41bcdc394b9a5513b8eb36cfe41a3d001499c3e`
- `assets/css/admin-sidebar-icon-rail-v117a14.css` new `a25a0bcb8da6abe2dae8ad45f0ffb3778ea19197d74611fb8992967f8e1c4731`

## QA
- PHP layout syntax PASS.
- ZIP and per-file SHA PASS.
- Simulated `--check`, deploy, re-deploy/idempotency, rollback, unexpected source conflict refusal PASS.
- Chromium CSS/layout at 1440px and 1024px: icon rail width exactly 76px; icons and active link accessible; expanded layout restored PASS.
- Chromium at 768px and 375px: desktop rail CSS does not apply PASS.
- Authenticated live-browser visual and navigation QA PENDING.

## Repository baseline constraint
The GitHub `main` application source predates many manually deployed production patches A1–A13. This PR records the exact source fingerprints and external package, not a replacement for live app files. Do not merge historical `main` application code over production without reconciliation. Deploy the verified ZIP as a scoped change.

Release does not require Node.js on the aaPanel server. No database migrations or writes by installer.
