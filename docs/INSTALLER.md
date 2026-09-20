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

Step 1 offers two verification methods while keeping one shared credential field and one Validate button:

- Ton Samith License — a license key provided directly by Ton Samith
- CodeCanyon / Envato — a CodeCanyon purchase code

Both methods are resolved through the Ton Samith License Manager. For the CodeCanyon / Envato method, the License Manager may verify the purchase with Envato Market. Barama does not contact Envato directly and does not expose license-service implementation details in the customer interface.

The selected method is bound to the validated installer session. Changing the method requires validation again.

The activation request binds:

- product slug
- canonical domain
- persistent installation ID
- selected verification method

The original purchase code or license key is not stored in persistent runtime state. Successful activation stores only normalized license metadata and the activation token.

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

The release package is built by tools/build-package.php and validated from the exact generated customer ZIP, not only from the repository working tree. GitHub Actions uploads the ZIP and its SHA256 checksum only after exact-package QA passes.

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
- database failure followed by a clean retry
- production runtime mode
- migration replay after installation
- first administrator role assignment
- second-install attempt blocked by the installation lock

The package QA installs from the extracted ZIP into a fresh MySQL database. It does not ship or enable a licensing bypass; the successful remote License Manager path remains a separate release gate.

A live License Manager success remains a release gate until a Barama-specific valid test purchase/license value is available for end-to-end verification.


## Upgrade handoff

The one-click installer is only for fresh installations. After installation is locked, a newer customer package is handled by the canonical `/updater/` workflow.

When an installed package version differs from the uploaded `VERSION`, the main front controller redirects to `/updater/` before normal application boot. The updater reuses protected runtime/license state, requires administrator re-authentication and backup confirmation, validates the installed activation state, applies only pending ordered migrations, advances protected version metadata, and then returns the customer to the website.

See `docs/UPGRADE.md` for the upgrade contract.
