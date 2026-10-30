# Barama Real Estate v2 — Laravel Foundation

Foundation branch for a clean Laravel 13 rebuild of Barama Real Estate.

**NOT a complete migration or customer ZIP.** The existing Pure PHP application is preserved on main. Do not deploy this branch over existing installations.

## Development setup
Requirements: PHP 8.3+, Composer 2, Node.js 22+.

    composer install
    cp .env.example .env
    php artisan key:generate
    npm install
    npm run build
    php artisan serve

Run architecture preflight: `php scripts/verify-structure.php`
Run Laravel-only guard: `php scripts/verify-no-legacy.php`
Run Laravel tests after installing dependencies: `php artisan test`

## Conventions
- Follow the normal Laravel root structure.
- Feature classes: `app/Modules/Area/Feature/` (subject to the native-structure architecture review).
- Route files: `routes/web.php` includes frontend, auth, account, developer, admin and webhook files.
- Blade templates: `resources/views/area/feature/`.
- Final CSS standard: **one first-party frontend `app.css` and one first-party admin `app.css`**, each with named, grouped feature sections. The current foundation imports small CSS files and still requires consolidation.
- Two area JavaScript entries with feature-specific modules.
- Vite generates cache-busted bundles; never hand-edit compiled assets.
- No production routes for unimplemented modules; no dummy admin access.
- For original functionality consult the preserved Main branch. Never migrate live customer data without schema mapping, backups, and rehearsal.

## Legacy source retirement (mandatory)

**The final migrated Barama application must be Laravel-only.** After full feature/data parity and successful release/rollback acceptance, remove all old custom PHP framework code, standalone legacy installer/updater, legacy CSS/JS bundles and temporary shims from the active source, default branch, deployed application and customer ZIP. The source and final extracted customer package must both pass `php scripts/verify-no-legacy.php`.

Retain secure **offline** recovery backups; never keep legacy application code inside the active Laravel product merely for convenience. Main and live deployments are preserved until cutover is separately approved.

See `docs/ARCHITECTURE.md`, `docs/MIGRATION.md`, `docs/QUALITY_GATES.md`, and `docs/LEGACY_RETIREMENT.md`.
