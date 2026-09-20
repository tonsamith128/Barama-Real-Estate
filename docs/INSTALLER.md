# Barama Real Estate One-click Installer

## Customer flow

The supported first-run flow is:

Upload files → extract → open the main domain → /installer/ → five steps → website.

The customer-facing installer exposes exactly five stages:

1. License & Terms
2. Requirements
3. Database
4. Website
5. System Health / Finish

Do not add Welcome, Permissions, Administrator, Installation, Verification, or Finished stages outside these five visible steps.

## Canonical URL

/installer/ is a physical directory entry point. GET and POST both target the trailing-slash URL so a web-server directory redirect cannot discard POST fields.

Before installation, the root index redirects to the base-path-aware installer URL using no-cache headers.

## Runtime state

Protected runtime files are stored under application/storage/app:

- installation/environment.php
- installation/installed.php
- license/installation-id.php
- license/license.php

State files are guarded PHP files, use atomic writes, and are chmod 0640 when supported. Apache also denies the entire application tree from public access.

The runtime environment loader reads persisted configuration before application config files are evaluated. Customers do not create or edit .env manually.

## Licensing

Permanent product slug:

barama-real-estate

The installer submits a purchase code or provided license key only to the Ton Samith License Manager activation endpoint. The License Manager may resolve the value directly or validate it with Envato. Barama does not contact Envato directly.

The activation request binds:

- product slug
- canonical domain
- persistent installation ID

The original purchase code is not stored in persistent runtime state. Successful activation stores only normalized license metadata and the activation token.

The Envato item ID remains zero before marketplace publication and is omitted from activation requests until a real item ID is assigned.

## Requirements

Required checks include:

- PHP 8.2+
- PDO and PDO MySQL
- JSON
- Mbstring
- Ctype
- Session
- OpenSSL
- Fileinfo
- cURL
- required application files
- writable runtime directories
- one canonical first-party CSS file
- one canonical first-party JavaScript file

HTTPS is reported as a warning during installation when absent, not as a separate permissions stage.

## Database

Supported minimum database versions:

- MySQL 8.0+
- MariaDB 10.6+

The installer can optionally create the named database when the supplied database account has permission. It otherwise connects to an existing empty database.

An interrupted Barama installation can be retried when schema_migrations exists and no user account has been provisioned.

## Website finalization

Step 4 validates the website URL, timezone, language, administrator identity and password before finalization.

The administrator password must be at least 12 characters and include uppercase, lowercase, number and symbol characters. Browser password generation uses Web Crypto and fills the password and confirmation fields together.

Finalization performs ordered migrations, creates the first administrator, stores website settings, writes protected runtime/license state, validates final database state, and creates the installation lock.

If finalization fails after migrations, database account/settings work is rolled back and incomplete runtime state is removed so installation can be retried.

## Installation lock

After installation, fresh requests to /installer/ redirect to the website. Only the session that completed installation can see Step 5 immediately after finalization.

There is no browser reset/uninstall action. Recovery must be an explicit guarded release operation rather than deleting the installation lock from a public UI.

## Release validation

The release package must be validated from the exact generated customer ZIP, not only from the repository working tree.

The exact-package QA must cover at minimum:

- uninstalled root redirect
- canonical /installer/ GET and POST
- exactly five visible steps
- CSRF and terms enforcement
- invalid purchase/license input
- requirements states
- database failure and retry
- clean schema migration
- administrator creation
- production runtime configuration
- subdirectory base URL
- installation lock
- sealed installer after completion
- canonical CSS and JavaScript
- no secret/runtime state shipped in the ZIP

A live License Manager success remains a release gate until a Barama-specific valid test purchase/license value is available for end-to-end verification.
