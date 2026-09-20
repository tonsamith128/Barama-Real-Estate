# Barama Real Estate

Barama Real Estate is a PHP, Bootstrap, and JavaScript real-estate marketplace and property-management platform by Ton Samith.

## Release target

- Current development line: `0.9.11-dev`
- CodeCanyon target: `v1.0.0`
- Author: Ton Samith
- Author URL: https://tonsamith.com
- Demo target: https://baramarealestate.tonsamith.com
- Marketplace: CodeCanyon
- Online Documentation: https://docs.tonsamith.com/docs?=228711-barama-real-estate-property-marketplace-developer-management-php-script
- Support: https://tonsamith.com/contact

## Account areas

- Admin Dashboard: `/dashboard`
- User Account: `/account`
- Public Developer / Company: `/company`
- Company / Vendor Workspace: `/developer`

## Architecture rules

This repository intentionally starts with a small canonical foundation.

- One application front controller: `index.php`
- One custom stylesheet: `assets/css/app.css`
- One custom JavaScript entry point: `assets/js/app.js`
- No duplicate versions of application files
- No abandoned legacy implementations
- No generated build artifacts committed to source
- Controllers contain request orchestration, not database/query logic
- Core framework primitives live in `application/app/Core`
- Public, user, developer, company, and admin concerns stay route/controller separated
- Database changes will be additive, ordered migrations
- Production errors must never expose stack traces to visitors
- The customer-facing release must not require Node.js

## Runtime requirement

- PHP 8.2+
- PDO with a MySQL/MariaDB driver
- Apache or Nginx
- Bootstrap 5 is currently loaded from the official CDN during the foundation phase

## Real estate domain foundation

The current development line defines the canonical Location, Amenity, Company/Developer, Project, Property, Listing, Media, Inquiry, and Lead database model.

A Property is the physical asset. A Listing is a sale/rent market offer for that property, so pricing and publication history do not overwrite the underlying asset.

Lifecycle rules and allowed status transitions are centralized in `application/app/Domain/RealEstate/DomainRules.php`. Repository boundaries are defined for the first public/read paths, and company-scoped developer access requires active company membership in addition to global permission. See `docs/DOMAIN_MODEL.md` for the relationship and delete policies.

## Authentication foundation

The current development line includes ordered database migrations, customer registration, secure login/logout, password reset links, login throttling, role/permission records, CSRF protection, and middleware-enforced access boundaries for \`/account\`, \`/developer\`, and \`/dashboard\`.

The installer will create the first administrator account from customer-supplied credentials. Administrator credentials are never hard-coded in the repository.

The final CodeCanyon package will include installer, upgrade, licensing, documentation, and release QA layers before \`v1.0.0\`.


## Domain write infrastructure

The current development line includes service-layer mutations for company onboarding and membership, location/amenity catalogs, project/property/listing create and edit flows, controlled approval/publication transitions with history, public inquiry capture with throttling, inquiry-to-lead conversion, and secure private media storage.

Management HTTP endpoints are protected by authentication, permissions, company scope, and CSRF. See `docs/WRITE_SERVICES.md`.

## Automated database QA

GitHub Actions runs repository lint/structure QA and a MySQL 8 smoke installation. The workflow applies all ordered migrations, verifies the expected schema/role/permission seeds and lifecycle rules, then reruns the migration command to confirm a clean no-op replay. It also executes an end-to-end domain write smoke test against MySQL.


## One-click installer

The v0.9 development line includes the canonical five-step browser installer at `/installer/`: License & Terms, Requirements, Database, Website, and System Health / Finish. An uninstalled root request redirects to the installer automatically. Runtime configuration, license state, and the installation lock are stored under protected application runtime storage; buyers do not create or edit `.env` manually.

Step 1 supports both a Ton Samith license key and a CodeCanyon/Envato purchase code through one shared license field with a Validate action. Verification is handled through the Ton Samith License Manager and the original credential is not retained after activation.

GitHub Actions also builds the customer ZIP, extracts that exact ZIP, performs a clean MySQL installation from the packaged code, validates production runtime state, migration replay, administrator provisioning, subdirectory URLs, and installation locking, then uploads the validated ZIP plus SHA256 checksum as a workflow artifact. See `docs/INSTALLER.md` for the installer and package-validation contract.


## Unified customer interface

The 0.9.1 development line consolidates the public marketplace, customer account, company/vendor workspace, administrator dashboard, global header/footer, authentication screens, and featured inventory cards into the canonical shared interface system.

- role-aware responsive global navigation
- customer account shortcuts backed by existing saved-property, alert, inquiry, viewing, and notification actions
- company/vendor workspace backed by the existing developer permissions and company-membership boundaries
- administrator dashboard backed by existing moderation and catalog actions
- premium authentication with SVG field icons, password visibility controls, secure password generation for registration/reset, and existing CSRF/login-throttle/reset protections
- modern translucent featured cards using the existing property/project/company data
- one first-party stylesheet and one first-party JavaScript file remain canonical


## Public marketplace experience

The 0.9.2 development line upgrades the public discovery experience while preserving the existing marketplace and account service layer.

- search-first homepage with live sale, rent, project, and company counts
- concise buy, rent, development, and company discovery cards
- advanced property filters with active filter context and cleaner empty states
- focused project and company directory search
- property details retain functional save, compare, viewing request, and inquiry actions
- related approved properties are suggested without exposing unpublished inventory
- project and company inquiry forms prefill signed-in customer identity
- shared interactive image preview for property, project, and company media
- search submission/loading behavior remains inside the canonical JavaScript entry point
- all marketplace styling remains inside the canonical stylesheet


## Account security and company onboarding

The 0.9.3 development line completes the first production-grade account onboarding layer without creating a parallel vendor identity system.

- customer profile settings with phone, timezone, and preferred language including Khmer
- privacy control for signed-in recently viewed property history
- current-password-protected password changes
- verified email-change flow using one-time expiring tokens
- protected account deactivation with company-owner orphan prevention
- first-company onboarding from the customer account
- onboarding creates owner membership but does not grant Developer permissions before review
- administrator company approval atomically activates Developer access for active company members
- company team management now sends invitations instead of immediately granting roles
- invited accounts receive Developer access only after accepting an unexpired invitation matching their login email
- pending company invitations are visible in the Company / Vendor team workspace and can be revoked
- account/onboarding lifecycle is covered by a dedicated MySQL smoke test and exact-package contract checks


## Release security hardening

The 0.9.4 development line closes the core authentication and administrator-security gaps required before final CodeCanyon release testing.

- public registration queues one-time email verification
- verification is required before company onboarding or company invitation acceptance
- administrator promotion requires a verified user email
- registration, password reset, and account password change share one strong PasswordPolicy
- server-side auth_version invalidates stale sessions after credential or administrator security changes
- browser sessions enforce configured idle and absolute lifetimes
- security_audit_events stores append-only security events with hashed IP/user-agent origin data
- Admin Dashboard adds Users and Security Audit sections
- administrators cannot remove their own admin role or disable their own account
- the final active administrator cannot be disabled or stripped of administrator access
- account, onboarding, notification, domain, release-security, and exact-package installer smoke tests remain mandatory CI gates

See `docs/SECURITY_HARDENING.md` and `docs/RELEASE_GATES.md` for the current release contract and the remaining open v1.0.0 gates.


## Secure upgrade workflow

The 0.9.5 development line adds the canonical customer-facing updater at `/updater/`.

- the main domain redirects to the updater before normal application boot when the installed version does not match the uploaded package
- updater POST requests use the canonical trailing-slash path
- customers confirm a current database/files backup before migration
- an active administrator must re-authenticate before changes begin
- the installed activation token, installation ID, product, domain, and status are validated without asking for the original purchase code again
- a concurrency lock prevents parallel upgrade runs
- downgrades are rejected
- only pending ordered migrations are executed
- protected installation metadata and `installation.version` advance only after migrations succeed
- successful upgrades create a security audit event
- replaying the updater on the current version is a clean no-op
- CI reconstructs a `0.9.3-dev` database and verifies the upgrade once from source and again from the exact generated customer ZIP

See `docs/UPGRADE.md` for the supported workflow and validation contract.


## License Manager release contract

The 0.9.6 development line hardens the canonical Ton Samith License Manager integration used by the installer and updater.

- one `LicenseManagerClient` supports both Direct and Envato verification methods
- activation validates Barama product, domain, installation ID, item binding when configured, and active status
- `validateActive()` centralizes runtime/updater validation rules
- deactivation is implemented through the configured Ton Samith License Manager endpoint
- HTTPS certificate and host verification remain mandatory for production requests
- normal Quality CI runs a deterministic contract smoke test without credentials or external service dependency
- exact customer ZIP QA verifies the hardened client while excluding internal licensing QA scripts
- a separate manual `Live License Manager QA` GitHub Actions workflow consumes Barama-specific repository secrets without printing credentials or activation tokens
- live QA tests Direct and Envato activation, validation, wrong-domain/wrong-installation rejection, deactivation, deactivated-token rejection, and invalid credentials

The live production-service release gate remains open until that manual workflow passes with Barama-specific test credentials. See `docs/LICENSE_MANAGER_QA.md`.


## Hosting compatibility preflight

The 0.9.7 development line hardens web-server routing before live-server installer acceptance.

- request paths are normalized against the active application base path, so canonical routes work at the domain root and in supported subdirectories
- the exact generated customer ZIP is tested behind Apache 2.4 with `mod_rewrite`
- Apache QA covers both domain-root and subdirectory installation paths
- the exact generated customer ZIP is also tested behind Nginx + PHP-FPM using the standard front-controller contract
- protected application paths are denied while public assets remain accessible
- internal hosting QA scripts remain excluded from the customer package
- the release still requires final installer acceptance on the intended live hosting environment before v1.0.0

Nginx does not read `.htaccess`; the hosting environment or panel must already provide safe front-controller routing. Barama does not attempt to rewrite an arbitrary Nginx vhost from PHP. See `docs/HOSTING_COMPATIBILITY.md`.


## Installer email delivery

The 0.9.8 development line completes customer-facing mail configuration without adding a sixth installer step.

- Step 4 Website includes SMTP (recommended) and PHP mail delivery settings
- all mail fields use the shared installer form/icon system
- the administrator email is the test recipient
- installation is blocked server-side until the exact submitted mail configuration passes a test
- only a SHA-256 fingerprint of the tested configuration is retained in installer session state
- SMTP passwords are never repopulated after validation errors
- production runtime configuration stores the selected mail driver and credentials under protected application storage
- SMTP sockets require peer and hostname certificate verification for TLS/SSL
- Step 5 reports email-delivery configuration health
- Quality CI uses a local PHP SMTP fixture to verify AUTH LOGIN and multipart delivery without external credentials
- exact customer ZIP QA verifies the installer mail contract while excluding internal SMTP QA scripts

Real provider delivery remains a final live-server acceptance gate.


## Browser and accessibility release QA

The 0.9.9 development line adds exact-customer-ZIP browser validation without adding a Node.js production requirement.

- Chromium tests the installer and installed application at 390px, 768px, and 1440px widths
- Light/Dark preferences are toggled and verified after reload
- mobile global navigation and workspace drawers expose aria-expanded state and close with Escape
- workspace focus returns to the opener after keyboard dismissal
- auth password visibility controls are exercised
- canonical :focus-visible styling is enforced for interactive controls
- serious/critical axe-core WCAG A/AA violations fail CI
- core pages are checked for horizontal overflow
- CI stores short-lived screenshots for visual inspection
- Playwright, axe-core, npm metadata, and browser QA scripts are excluded from the customer ZIP

See `docs/BROWSER_QA.md`.


## Cross-browser compatibility QA

The 0.9.10 development line extends exact-customer-ZIP browser validation beyond Chromium.

- Chromium remains the deep responsive, keyboard, axe accessibility, authenticated-workspace, and screenshot gate
- Firefox and WebKit validate the fresh installer, core public routes, mobile menu behavior, Light/Dark persistence, administrator login, and authenticated account/developer/admin routes
- mobile and desktop overflow checks run in all three browser engines
- Playwright browsers and npm dependencies exist only in temporary GitHub Actions storage and are never shipped to customers
- final Safari/iOS and Edge/Chrome visual acceptance remains a human pre-release gate

See `docs/BROWSER_QA.md`.


## Localization foundation

The 0.9.11 development line introduces one canonical localization layer for English, Khmer, and Chinese without duplicating views or customer assets.

- the installer default-language selector and account preferred-language setting use one shared supported-locale definition
- guests can switch language through a CSRF-protected session preference
- signed-in customers persist language choice in the existing `user_profiles.preferred_language` field
- the document `lang` attribute follows the active locale
- shared site navigation, account shell, footer, login, and registration surfaces use the canonical translation helper
- English remains the fallback when a translation key is unavailable
- CI verifies catalog parity, locale normalization, unsupported-locale rejection, exact-package language files, and rendered Khmer/Chinese switching
- no locale-specific view copies, extra first-party stylesheets, or extra first-party JavaScript files are introduced

Final human review of page-specific English/Khmer/Chinese copy remains a v1.0.0 release gate. See `docs/LOCALIZATION.md`.
