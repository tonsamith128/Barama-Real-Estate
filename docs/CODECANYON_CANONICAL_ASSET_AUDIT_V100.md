# CodeCanyon v1.0.0 — Canonical Asset Consolidation Audit

**Repository:** `tonsamith128/Barama-Real-Estate`  
**Baseline:** `12ba44d7db27f5d88f195a3f26bc97d1a0346cc3`  
**Scope:** first safe consolidation wave; source version remains **1.0.0**.

## Why this refactor

The customer PHP script accumulated many version-suffixed static files during rapid iteration. Stable feature filenames are easier for CodeCanyon buyers to maintain, and Git preserves history without distributing superseded copies. Asset URLs use query-string cache keys (`?v=1.0.0-clean1`) instead of a version suffix in the filename.

## Full package-reference scan

- **321** CSS and JS source assets in the customer runtime before changes.
- **26** asset families contain two or more `-v...` filenames (**89** files across those families).
- Whole-runtime scan included PHP, JS, CSS, HTML, JSON and available documentation. A missing direct filename reference does not on its own prove that an asset is unused: some templates load files dynamically.
- **15 families selected** for wave 1 because exactly one version per family was directly referenced from live PHP views; the selected current implementation is copied **byte-for-byte** to its canonical, unversioned name.
- **49 old versioned files removed**, **15 canonical assets added**, **9 loading templates changed**. The JS/CSS source content itself is not altered or minified by this change.
- The packaged runtime was checked after the rename: no references to the 49 removed filenames; all changed PHP templates and new canonical JavaScript files passed local syntax checks.
- No database migrations, application-version bump, or live-domain deployment is part of this PR.

## First-wave canonical asset paths

```text
assets/css/admin-email-template-builder.css
assets/js/admin-email-template-builder.js
assets/css/admin-email-templates.css
assets/js/admin-email-templates.js
assets/css/admin-global-locations.css
assets/js/admin-global-locations.js
assets/css/admin-settings-security-factors.css
assets/js/admin-settings-security-factors.js
assets/css/public-location-picker.css
assets/js/public-location-picker.js
assets/css/media-library-upload.css
assets/js/page-builder.js
assets/js/account-profile.js
assets/css/popular-location-marquee.css
assets/css/homepage-agent.css
```

## Intentionally deferred: multiple live layers

Do **not** delete older versions from the following families without first examining cascade order, selectors, event handlers and replacing every live loader with behaviorally equivalent canonical implementations:

- `hero-search`: 4 CSS layers referenced by `application/resources/views/layouts/app.php`.
- `public-header-inline`: 3 CSS layers referenced by the public layout.
- `security-center`: 6 CSS layers referenced by its admin view.
- `finance-payment-methods`: both v210/v230 CSS and v228/v230 JavaScript actively referenced; more historic scripts still present.
- `plans-packages`: v140 and v143 CSS both used.
- `finance-plans`: v139 CSS is referenced by typography CSS, while v140 is loaded separately.
- `change-confirmation`: current v133 is live; an older finance JS copy also names it. Must resolve related family together.
- `account-security-premium`: old versions had no direct literal filename references in the ZIP; verify dynamic imports before removal.

The test `tools/canonical-assets-smoke.php` protects the wave-1 canonical file set and asserts no wave-1 historical variant returns in the source or exact customer ZIP.

## Release acceptance

1. All repository, PHP, JavaScript, CodeCanyon staging, package installer, upgrade, browser/accessibility and Apache/Nginx workflows pass on the exact commit.
2. Inspect admin email/template builder, settings/security, public location picker, homepage, page builder and Media Library Upload on a disposable licensed customer install, with light/dark and mobile coverage.
3. Rebuild the **complete v1.0.0** CodeCanyon ZIP and record its *new* SHA-256 and source commit. Previous ZIPs are obsolete for this asset refactor.
4. Never delete files from an existing production install by guessing from these asset names; upgrade as an atomic verified package with backups/rollback.

## Naming rule for all future features

- One stable CSS/JS filename per implemented feature, unless deliberately split into separately maintained modules.
- Git commits/tags own history. Avoid shipping `-v123`, `-fix`, `-final` copies as legacy backups.
- Use a URL cache query or build fingerprint to invalidate browser caches.
- Retain contract-versioned **PHP classes** (for example `DemoImportPackageV1.php`) where the suffix identifies a real supported protocol or data schema, not a duplicate implementation.
- Refuse to consolidate a multi-layer family without proving equivalence on exact-package acceptance tests.
