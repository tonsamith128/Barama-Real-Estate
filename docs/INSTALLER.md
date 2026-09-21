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

Step 1 exposes one purchase-code/license field and one Validate button. The customer interface does not ask the buyer to choose a verification method.

The installer automatically routes a standard Envato purchase code or an authorized author-issued key through the existing License Manager. For Envato purchases, the License Manager may verify the purchase with Envato Market. Barama does not contact Envato directly and does not expose License Manager routing details in the customer interface.

The Step 1 order is:

1. enter the purchase code or license key
2. click Validate and confirm the license is valid
3. click the agreement checkbox; the Terms modal always opens before the agreement can be checked
4. review the Envato license terms, author disclaimer, and support terms
5. click Accept Terms; only then does the agreement checkbox become checked
6. click Verify & Continue

License validation is independent of terms acceptance. Terms acceptance is enforced only before continuation to Step 2.

The activation request binds:

- product slug
- canonical domain
- persistent installation ID
- internally resolved verification route

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

Step 2 presents these checks as a clean requirements table with Requirement, Detected / Details, and Status columns rather than individual boxed cards.

The installer header keeps the canonical language SVG control and shows flag + language options for English, Khmer, and Chinese. The preference is stored locally for the installer session and is synchronized with the Step 4 default-language field when available.

## Database

Step 3 exposes exactly two database setup modes:

### Existing Database

Use this when the hosting panel already provides database credentials. The installer requires:

- database host
- port
- database name
- database username
- database password

The connection must use MySQL 8.0+ or MariaDB 10.6+ and the target database must be empty or a safe interrupted Barama installation.

### Create Database

Use this only when the customer has MySQL/MariaDB administrative credentials. The installer requires:

Database Server Admin Access:
- admin host
- admin port
- admin username
- admin password

New Database Details:
- new database name
- new database username
- new database password
- password confirmation

The administrative credentials are used only during provisioning and are never stored in Barama runtime state. Barama creates a dedicated database and dedicated database user, grants that user privileges only on the new database, then verifies the new application connection. If provisioning fails after Barama creates resources, the installer attempts to remove only the database/user created by that attempt.

After successful provisioning, only the dedicated application database credentials continue to Step 4 and into the protected runtime configuration.


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


## Hosting compatibility preflight

Release QA runs the exact generated customer ZIP behind Apache and Nginx before artifact delivery.

Apache validation uses the packaged `.htaccess`, `mod_rewrite`, protected-path denial, canonical installer redirects, static assets, and both domain-root and subdirectory URLs.

Nginx validation uses PHP-FPM and the standard front-controller contract required by supported Nginx hosting. Nginx does not process `.htaccess`, so the hosting platform or control panel must provide this vhost capability; the application does not attempt to mutate arbitrary Nginx configuration from PHP.

These automated checks do not replace the final live-server five-step installer test.


## Step 4 email delivery

Step 4 remains the single Website step and now includes email delivery configuration. No additional installer stage is introduced.

The buyer configures:

- SMTP (recommended) or PHP mail
- From name and From email
- SMTP host and port
- TLS/SSL/none encryption
- SMTP username and password when required

Before installation can finish, the buyer must send a successful test email to the administrator address.

The server validates the exact mail configuration and stores only a SHA-256 fingerprint of the successfully tested values in the installer session. The SMTP password is not stored in the installer session and is never repopulated after a validation error.

If the mail configuration or administrator recipient changes after testing, the installer requires another successful test.

After installation, the validated mail settings are written to protected runtime configuration under application storage. Step 5 reports an Email delivery configuration health check.

Real external-provider delivery remains part of final live-server acceptance.
