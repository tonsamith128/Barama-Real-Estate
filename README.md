# Barama Real Estate v2 — Laravel Modernization

**Current status:** New Laravel 13 foundation with native authentication code. NOT a finished migration, deployable CodeCanyon/Codester script, or a replacement for the functioning v1 application.

**Branch:** `feature/laravel-v2-foundation-20261030`. The existing Pure PHP product remains on Main and on live sites.

## Source conventions

- Laravel 13 / PHP 8.3+, native `app/Http/Controllers/Area/Feature/` and `app/Http/Requests/Area/` hierarchy.
- `app/Models/` for Eloquent models, `database/migrations/` for schema changes, `resources/views/area/feature/` for Blade presentation.
- `routes/web.php` loads focused `frontend.php`, `auth.php`, `account.php`, `developer.php`, `admin.php`, `webhooks.php`.
- **Only two first-party CSS source files**: `resources/css/frontend/app.css` and `resources/css/admin/app.css`. Both have single-line area/feature section comments. Vite builds cache-busted assets.
- Two JavaScript entry points: `resources/js/frontend/app.js` and `resources/js/admin/app.js`, with feature modules added as necessary.
- No legacy v1 app, duplicate CSS implementations or versioned patch copies in the final product.

## Implemented v2 code

The foundation route, login, registration, logout, forgot/reset password, signed email verification, and protected /account foundation page are authored. There is a new isolated `users` / `password_reset_tokens` schema and Laravel Form Requests.

**Existing Barama users, admin permissions, MFA, real estate modules, licensing, billing, importer, installer, updater and data are NOT migrated.** Never execute this branch against a legacy database.

## Development checks

Requirements: PHP 8.3+, Composer, Node.js 22+, local PHP extensions including mbstring and sqlite (for tests).

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php scripts/verify-structure.php
php scripts/verify-no-legacy.php
php artisan route:list
php artisan test
php artisan serve
```

Production/customer packaging must bundle Composer dependencies and compiled frontend assets, without requiring customers to use Composer or Node. Set the web document root to `public/`.

Read `docs/ARCHITECTURE.md`, `docs/MIGRATION.md`, `docs/AUTHENTICATION.md`, `docs/QUALITY_GATES.md` and `docs/LEGACY_RETIREMENT.md`.

## Deployment and cleanup policy

No merge to Main or deployment to existing sites before feature parity, secure database migration, complete QA, customer ZIP installation and verified rollback. Final Laravel-only releases must pass the source and exact-package legacy checks. Keep legacy recovery backups outside active product files.
