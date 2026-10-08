# Barama Real Estate Customer Package

This file describes the supported CodeCanyon customer ZIP.

## Install

1. Upload the ZIP contents to the intended website root.
2. Open the main domain.
3. Barama redirects automatically to `/installer/`.
4. Complete the five visible installer steps.
5. Use the website.

No Node.js production runtime, Composer install command, manual migration command, or customer-created `.env` file is required.

## Included

The customer ZIP contains only the runtime product and customer-facing support files required to install, run, and upgrade Barama Real Estate:

- root front controller and Apache rewrite rules
- five-step installer
- secure updater
- application PHP source
- canonical global stylesheet: `assets/css/app.css`, with page-scoped first-party stylesheets for feature-specific interfaces
- canonical global JavaScript entry point: `assets/js/app.js`, with page-scoped first-party scripts for feature-specific behavior
- runtime storage directories without customer data
- notification worker CLI entry point
- product license notice
- third-party notices
- packaged HTML help file: `documentation/index.html`
- customer-facing `CHANGELOG.md`
- package README and version metadata

## Not included

The customer ZIP intentionally excludes development-only material, including:

- GitHub workflow metadata
- repository documentation sources
- internal QA/smoke-test scripts
- browser automation dependencies
- Node.js packages
- Composer/vendor dependencies
- build output from previous versions
- logs, caches, SQL dumps, backups, or local editor files
- installation/license runtime state
- test credentials
- demo/sample marketplace inventory

## Production defaults

A clean install creates only the schema and required system records such as roles, permissions, and application settings.

It does not seed demo companies, projects, properties, listings, inquiries, leads, or customer accounts. The first administrator is created from credentials entered by the buyer during installation.

Demo content for the public demonstration website is maintained separately and is not part of the customer database migrations.

## External runtime services

Barama uses these declared external hosts:

- `cdn.jsdelivr.net` for the Bootstrap 5.3.3 stylesheet; outbound HTTPS access to this host is required for Bootstrap styling
- `tonsamith.com` for licensing and support
- `docs.tonsamith.com` for online documentation

Configured customer SMTP servers are supplied by the buyer and are not hard-coded in the package.

See `THIRD_PARTY_NOTICES.md` for dependency licensing information.

## Upgrade

When a newer supported package is uploaded over an installed copy, Barama detects the version mismatch and redirects to `/updater/`.

Create a current files/database backup before upgrading. The updater requires administrator re-authentication and applies only pending ordered migrations.


## Customer documentation

Open `documentation/index.html` after extraction for the packaged help file covering system requirements, installation, licensing, account/workspace URLs, email delivery, scheduled notification jobs, upgrades, third-party credits, and support.

The public online documentation remains the extended reference; the packaged HTML file is the customer-safe offline starting point.

## Package integrity

Each validated customer ZIP is published with a SHA-256 checksum file generated from the exact archive that passed release QA. Verify the checksum before deployment when transferring the package between systems.
