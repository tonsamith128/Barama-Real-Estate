# Accepted Live Source Reconciliation — 0.9.22-dev

This branch is reserved for reconciling the accepted Barama Real Estate live/customer-package source back into GitHub before any v1.0.0 promotion.

## Source of truth

- Accepted artifact: `Barama-Real-Estate-0.9.22-dev-live-dry-run.zip`
- Artifact SHA-256: `a814d694921a76c8a86014fd2ccea2634f30b29b06221c26df3f93d88144881b`
- Accepted by exact package dry-run gate: v1.8.52
- Accepted by exact ZIP static gate: v1.8.53
- Packaged files: 557
- ZIP entries: 616
- Packaged PHP files linted: 301
- CSS files: 140
- JavaScript files: 77
- Migrations: 30
- Runtime-state files shipped: 0
- Stale .orig files shipped: 0
- Hard package debris: 0
- Inline style attributes: 0
- Inline event handlers: 0

## GitHub base

- Repository: `tonsamith128/Barama-Real-Estate`
- Base branch: `main`
- Base/head at reconciliation start: `fc617c12790f7075ccfd0e418a1bba3443450941`
- Base commit message: `Remove duplicate homepage hero quick links`

## Reconciliation rule

The accepted customer artifact is authoritative for these product surfaces:

- root customer files: `.htaccess`, `index.php`, `LICENSE.md`, `THIRD_PARTY_NOTICES.md`, `CUSTOMER_PACKAGE.md`, `CHANGELOG.md`, `README.md`, `VERSION`
- `installer/`
- `updater/`
- `application/`
- `assets/`
- `documentation/`
- `tools/notifications.php`

Development-only repository content outside those customer surfaces (for example `.github/`, `docs/`, and internal `tools/` other than `notifications.php`) must be preserved.

## Safety

- Do not merge this branch to `main` until exact artifact parity and release QA pass.
- Do not change `VERSION`; it remains `0.9.22-dev`.
- Do not generate or execute migrations during reconciliation.
- Do not copy live runtime state from `application/storage/app`.
- Do not reintroduce stale `.orig` files.
- Do not collapse page-scoped CSS or JavaScript into global assets.

## Initial drift evidence

A direct comparison against `main` already confirms that the repository is behind the accepted artifact. Examples include missing or changed controllers, services, repositories, migrations 0023–0031, account/security components, finance views, policy/security views, and page-scoped CSS assets.

This file records provenance only. The full source overlay must be applied and verified separately.
