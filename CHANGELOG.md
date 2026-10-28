# Changelog

All customer-facing changes to Barama Real Estate will be recorded in this file.

Development-only 0.9.x milestones are intentionally not presented as separate CodeCanyon customer releases. Git history remains the source of truth for pre-release engineering changes.

## 0.9.25 — 2026-10-23 (Live Demo source release)

- Promote the GitHub Main Live Demo source to a consistent pre-1.0 release version.
- Include the verified Auto Updater code merged via PR #126 and its PHP/browser QA acceptance.
- Align deployed source `VERSION` and installed runtime metadata as one guarded deployment, without changing the database schema, runtime secrets, or uploaded media.
- Portable signed update installation still requires host-appropriate private vault and controlled write permissions; this version promotion alone does not certify that `Update Now` can install a future release.


## 1.0.0 — 2026-10-28 (CodeCanyon package candidate; not yet published)

- Fix first-install Invoices Admin page: add missing invoice-template migration, gracefully handle old un-migrated installations with clear editor guidance, and protect template writes until schema upgrade.
- Correct Cloudflare Turnstile feature row's title, description, status and Configure button contrast in light mode; preserve dark-mode behavior. See `docs/CODECANYON_V1_0_0_INVOICES_TURNSTILE_FIX.md`.

Initial public-release source and customer-package documentation prepared for CodeCanyon. This is a versioned candidate, not proof of Envato approval, live installation certification, or marketplace publication.

- Updated packaged offline help with v1.0.0 version identity, signed Demo Importer format/database compatibility safety guidance, Media Library Upload instructions and all four official product resource links.
- Consolidate 49 superseded historical CSS/JS files from 15 single-active-version asset families into stable feature filenames with asset URL cache keys; retain genuinely layered legacy assets pending a separate dependency/cascade review. See `docs/CODECANYON_CANONICAL_ASSET_AUDIT_V100.md`.
- Second cleanup wave consolidates 47 additional layered/unused CSS and JS files into 12 stable canonical feature assets, preserving original stylesheet cascade and independent JavaScript module order; removes the broken Footer editor stylesheet request, and adds automated checks for 246 literal asset references. A fresh-install account-by-account acceptance checklist is included in `docs/RELEASE_V1_0_0_FRESH_SITE_ACCEPTANCE.md`.
- Replace persistent Policy Center legal-governance and imported-template banners with a premium, accessible, first-visit dismissible dialog; provide a small permanent reopening link while keeping real Publish validation errors visible.
- Fix Policy Center imported legal-template publishing: show actionable checklist and editorial placeholders; do not write or advance version history on denied publication; publish action makes an approved template public.
- Reuse unchanged signed demo-content releases across compatible application version updates; historic app version labels are advisory, while immutable SHA-256/Ed25519 signature, licensed publisher catalog, required policies, read-only destination schema preflight, empty-site and backup protections remain enforced.
- Updated future-import image path and verified size handling, with optional public-only signed Policy Center snapshots (subject to release acceptance). Existing signed v0.9.25 demo packages remain unchanged.

### Marketplace
- property sale/rent marketplace
- project/development discovery
- public company/developer directory
- responsive property/project/company detail experiences
- save, compare, viewing request, inquiry, and saved-search customer workflows

### Accounts
- secure customer registration, login, logout, email verification, and password reset
- customer profile, privacy, security, notifications, saved items, comparisons, alerts, recent views, and company onboarding
- company/vendor workspace with company membership boundaries
- administrator dashboard with moderation, catalogs, user controls, and security audit visibility

### Real estate management
- locations and amenities
- companies/developers and team membership
- projects
- properties
- sale/rent listings
- private Media Library with drag-and-drop uploads, MIME validation, storage provider selection, authenticated previews, permission/CSRF checks and shared-hosting upload-size handling
- inquiries and leads
- controlled lifecycle/status transitions

### Installation and operations
- five-step one-click installer
- Ton Samith direct and CodeCanyon/Envato license verification methods
- MySQL/MariaDB installation and ordered migrations
- first-administrator provisioning
- PHP-mail defaults during installation with optional authenticated SMTP configuration after installation
- secure browser updater with administrator re-authentication and pending-migration execution
- notification/outbox command for scheduled hosting jobs

### Release quality
- Light/Dark Mode and responsive layouts
- English, Khmer, and Chinese localization foundation
- Apache/Nginx routing compatibility preflight
- exact-package browser/accessibility and upgrade QA
- third-party notices and CodeCanyon package compliance gates
- customer HTML help documentation

This entry records the v1.0.0 source/package-candidate preparation date. The CodeCanyon public publishing date and final acceptance evidence must be recorded separately after all required live-server, human visual/content, online-documentation, signed demo compatibility and exact-package acceptance gates pass.
