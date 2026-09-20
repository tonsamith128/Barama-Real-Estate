# Barama Real Estate

Barama Real Estate is a PHP, Bootstrap, and JavaScript real-estate marketplace and property-management platform by Ton Samith.

## Release target

- Current development line: `0.9.0-dev`
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
- Developer Account: `/developer`

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
