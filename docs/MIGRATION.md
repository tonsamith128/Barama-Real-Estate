# Barama Real Estate — Laravel v2 migration record

**Branch:** feature/laravel-v2-foundation-20261030  
**Legacy baseline:** Pure PHP Barama v1.0.0, preserved on main.  
**Current milestone:** Native Laravel authentication, public marketplace read slices, and tenant-isolated Developer foundation.  
**Release:** Not approved. No live deployment or legacy database migration.

## Implemented

- Laravel 13-compatible bootstrapping and standard configuration files for authentication, database, sessions, cache, mail, filesystem, hashing and logging.
- Native Laravel controller structure: `app/Http/Controllers/Area/Feature/`.
- Isolated v2 `User` Eloquent model and migration for `users` and `password_reset_tokens`.
- Login, registration, logout, password reset, signed email verification and protected Account route.
- Form Request validation and login/register/reset throttling.
- Blade views for the new authentication workflows.
- **Exactly two first-party CSS source files:** `resources/css/frontend/app.css` and `resources/css/admin/app.css`, with named area/feature sections.
- User and authentication-focused feature tests authored, not yet executed against the Laravel runtime.
- New Laravel Eloquent models and isolated v2 schema for Company, Project, Property, Listing and active company memberships.
- Public Companies, Projects and Properties index/detail controllers, query services, Blade pages and visibility rules.
- Company-scoped, authenticated, verified Developer read-only dashboard (editing and role policies not yet enabled).
- Visibility and company-scope feature tests authored, but not yet confirmed green in CI.
- Legacy Pure PHP remains absent from this new branch; retirement guard remains active.

## Pending

| Module | Status |
|---|---|
| Frontend/Home | Development navigation only; original premium homepage not migrated |
| Auth/Login, Register, Reset, Email | Implemented in source; CI/runtime verification pending |
| Auth/MFA and legacy sessions | Not migrated |
| Account dashboard | Protected placeholder; user features not migrated |
| Developer Workspace, company membership | Read-only scoped dashboard and new membership table; legacy roles and writes pending |
| Admin Dashboard / Developer / Appearance | Not migrated |
| Finance, Media Library, Policy Center | Not migrated |
| Public real-estate marketplace | New-schema Companies, Projects and Properties public read-only slice; v1 feature parity pending |
| Legacy MySQL data/schema migration | Not started |
| Installer, License Manager, updater, backup, demo importer | Not migrated |
| Browser QA, staging deployment, customer ZIP and rollback | Not started |

## Mandatory next gates

1. Install Composer dependencies, build Vite assets, execute PHP and feature tests.
2. Build a full legacy routes/database/features parity inventory.
3. Extend the auth model to legacy user states, roles, password hash compatibility and MFA where required; do not import old users before this mapping.
4. Map the full legacy real estate schema and complete company-scoped write authorization, history and other domain modules.
5. Migrate account/developer/admin workflows, admin Blade layouts, and corresponding JS modules.
6. Prove fresh shared-hosting installation, signed update/recovery, data conversion and release packaging.
7. Enforce `scripts/verify-no-legacy.php` on the **exact extracted customer ZIP** before any public release.

See `docs/REAL_ESTATE_DOMAIN.md` for the implemented entity relationships and security boundaries.

**Do not deploy the current development branch as a replacement for the existing live application.**
