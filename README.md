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

Run architecture preflight: php scripts/verify-structure.php
Run Laravel tests after installing dependencies: php artisan test

## Conventions
- Follow the normal Laravel root structure.
- Feature classes: app/Modules/Area/Feature/
- Route files: routes/web.php includes frontend, auth, account, developer, admin and webhook files.
- Blade templates: resources/views/area/feature/
- CSS source entries: resources/css/frontend/app.css and resources/css/admin/app.css
- CSS sections: one-line comments naming Area / Feature / Component.
- Vite generates one CSS bundle per area with cache-busted filenames. Never edit compiled files.
- No production routes for unimplemented modules; no dummy admin access.
- For original functionality consult the preserved main branch. Never migrate live customer data without schema mapping, backups, and rehearsal.

See docs/ARCHITECTURE.md and docs/MIGRATION.md.

