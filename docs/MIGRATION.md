# Barama Real Estate — Laravel v2 migration record

**Branch:** feature/laravel-v2-foundation-20261030  
**Legacy baseline:** Pure PHP Barama v1.0.0, preserved on main.  
**Current milestone:** Native Laravel structure and authentication foundation.  
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
- Legacy Pure PHP remains absent from this new branch; retirement guard remains active.

## Pending

| Module | Status |
|---|---|
| Frontend/Home | Foundation only; legacy marketplace not migrated |
| Auth/Login, Register, Reset, Email | Implemented in source; CI/runtime verification pending |
| Auth/MFA and legacy sessions | Not migrated |
| Account dashboard | Protected placeholder; user features not migrated |
| Developer Workspace, company membership | Not migrated |
| Admin Dashboard / Developer / Appearance | Not migrated |
| Finance, Media Library, Policy Center | Not migrated |
| Legacy MySQL data/schema migration | Not started |
| Installer, License Manager, updater, backup, demo importer | Not migrated |
| Browser QA, staging deployment, customer ZIP and rollback | Not started |

## Mandatory next gates

1. Install Composer dependencies, build Vite assets, execute PHP and feature tests.
2. Build a full legacy routes/database/features parity inventory.
3. Extend the auth model to legacy user states, roles, password hash compatibility and MFA where required; do not import old users before this mapping.
4. Migrate real estate domain services and company-scoped permissions.
5. Migrate account/developer/admin workflows, admin Blade layouts, and corresponding JS modules.
6. Prove fresh shared-hosting installation, signed update/recovery, data conversion and release packaging.
7. Enforce `scripts/verify-no-legacy.php` on the **exact extracted customer ZIP** before any public release.

**Do not deploy the current development branch as a replacement for the existing live application.**
