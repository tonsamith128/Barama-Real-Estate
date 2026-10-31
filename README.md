# Barama Real Estate

Laravel 13 real estate marketplace — PHP 8.3+, MySQL 8, Blade and Vite.

**Status: Development QA only, not a CodeCanyon customer release.**
GitHub source changes do not deploy to live websites.

## Current application

- Laravel-native authentication, frontend, customer, developer and administrator workspaces.
- Company, project, property and listing management with publication controls.
- Customer inquiries, saved searches, saved properties, viewings and notifications.
- Protected media, legal content, appearance and homepage editor, licensing.
- Grouped frontend/admin CSS and JS source entry points:
  resources/css/frontend/app.css, resources/css/admin/app.css,
  resources/js/frontend/app.js and resources/js/admin/app.js.
- Installation wizard for a clean MySQL database. Its pre-installation PHP
  bootstrap is not a second application framework.

## Development checks

    composer install
    npm install
    npm run build
    php scripts/verify-structure.php
    php scripts/verify-laravel-only.php
    composer test

Configure .env using a disposable database. Do not apply development
migrations to an installed customer database.

## Release requirements

The development QA package is not sellable. Complete the feature-parity
register, historical-data upgrade adapters, signed updater, backup/restore,
licensing lifecycle, exact customer ZIP tests, independent aaPanel/cPanel
installations, external providers and security review before release.

See docs/ARCHITECTURE.md, docs/LARAVEL_SOURCE_POLICY.md,
docs/PRODUCT_COMPLETION_REGISTER.md and docs/INSTALLER_ACCEPTANCE.md.
