# Barama Real Estate

Barama Real Estate is a PHP, Bootstrap, and JavaScript real-estate marketplace and property-management platform by Ton Samith.

## Release target

- Current development line: `0.9.3-dev`
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
