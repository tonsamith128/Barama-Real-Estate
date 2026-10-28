# Barama Real Estate Customer Package — v1.0.0

This file describes the versioned Barama Real Estate v1.0.0 CodeCanyon customer-package candidate. The package version does not certify a public marketplace release; exact ZIP and live acceptance must be recorded before publishing.

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
- one first-party stylesheet: `assets/css/app.css`
- one first-party JavaScript file: `assets/js/app.js`
- runtime storage directories without customer data
- notification worker CLI entry point
- product license notice
- third-party notices
- packaged HTML help file: `documentation/index.html`
- customer-facing `CHANGELOG.md`
- package README and version metadata

## Media Library Upload

Administrators with the `media.manage` permission can use `/dashboard/media` and `/dashboard/media/upload` to add images, PDF/documents, video or audio with the protected uploader. The Media Library provides access-controlled previews and image attachments. Maximum 20 files per upload batch and 50 MB per file are application limits; PHP and hosting limits may be lower. Files are inspected using MIME validation. The default destination is private local server storage; S3/R2 is available only when a supported provider has been configured, tested and activated.

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


## Official customer resources

All four resources below are maintained separately from the customer ZIP:

- **Support:** https://tonsamith.com/contact
- **Live Demo:** https://baramarealestate.tonsamith.com
- **Online Documentation:** https://docs.tonsamith.com/docs/?=228711-barama-real-estate-property-marketplace-developer-management-php-script
- **Product Description:** https://demo.tonsamith.com/demo/257404-barama-real-estate-property-marketplace-developer-management-php-script

The public demo is a showcase and is **not** automatically installed with a fresh customer database. Demo-content releases are separate from Barama application updates. Official signed demo download is subject to licensing; installation is further controlled by the installed release's security gates. Demo Content v0.9.26 previously accepted for application v0.9.25 is not assumed compatible with application v1.0.0: require an explicit signed manifest matching the exact installed application version. Never bypass signature, backup or empty-marketplace checks.

## Customer documentation

Open `documentation/index.html` after extraction for the packaged help file covering system requirements, installation, licensing, account/workspace URLs, email delivery, scheduled notification jobs, upgrades, third-party credits, and support.

The public online documentation remains the extended reference; the packaged HTML file is the customer-safe offline starting point. It also explains the signed Demo Importer, its release-controlled installation gate, and the four official resource links.

## Package integrity

Each validated customer ZIP is published with a SHA-256 checksum file generated from the exact archive that passed release QA. Verify the checksum before deployment when transferring the package between systems.
