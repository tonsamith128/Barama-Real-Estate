# Barama Real Estate — Laravel 13 migration

**Status:** Laravel source foundation on Main; not yet a feature-complete production release.

**Old Pure PHP source:** archive/barama-v1-main-before-laravel-20261030.

## Implemented
- Native Laravel 13 / PHP 8.3+ architecture, authentication, Eloquent marketplace and isolated migrations.
- Public read-only company, project, property listings and tenant-scoped read-only Developer workspace.
- Only two first-party CSS sources (frontend/app.css and admin/app.css), with grouped functionality and JS entrypoints.
- On installer development branch: independent five-step fresh-install wizard with private setup token, MySQL empty-database validation, application config, administrator and installation lock.

## New installations
The installer supports fresh **empty** MySQL databases only. The website document root must be the Laravel public/ folder; HTTPS, Composer vendor and built Vite assets are required. Open /installer/ and retrieve the private setup token from storage/app/installer.token using File Manager or SSH.

See docs/INSTALLER_ACCEPTANCE.md for details and the deletable Installer/ and public/installer/ folders.

## Checks
- php scripts/verify-structure.php
- php scripts/verify-no-legacy.php
- composer test
- php tests/Installer/InstallerUnitTest.php
- GitHub Actions: real MySQL 8 HTTP five-step install acceptance

## Not yet migrated
Original Barama data, legacy role/MFA compatibility, complete admin/developer editing, media, payments, policies, licensing, updater, importer, backup, customer ZIP and staging cPanel/aaPanel acceptance.

**Do not install on or migrate a live v1 database. No live site has been changed.**


## Integrated migration (development)

The integrated branch adds signed legacy demo archive preview, staging-only verified demo import, 53-image-compatible media and location schema, admin/company moderation, developer private property drafts and review, property inquiries, and private developer/admin inboxes.

All of this is **under development**. Production demo imports are disabled until publisher entitlement and licensing are rebuilt. The exact Laravel QA ZIP is assembled and tested via GitHub Actions and is not a sellable customer release.

See [feature parity matrix](docs/FEATURE_PARITY_MATRIX.md) and [combined QA instructions](docs/INTEGRATED_ACCEPTANCE.md). No live application or existing Pure PHP database has been modified.
