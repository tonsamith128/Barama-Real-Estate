# Barama v2 code quality gates

## Automated foundation gates

- `php scripts/verify-structure.php` — native Laravel directories; exactly 2 CSS source files.
- `php scripts/verify-laravel-only.php` — only canonical Laravel application runtime and source assets.
- `composer validate` and `composer install` — valid declared Laravel dependencies.
- `composer test` — authentication and signed email verification; public property/company/project listing visibility, filters, hidden 404 cases and Developer company membership isolation.
- `npm run build` — cache-busted Vite bundles for frontend and admin without additional first-party CSS source files.
- PHP PSR-12 formatting using Laravel Pint, verified after dependency installation.

## Before migration or marketplace release

- Full v1/v2 route and feature parity matrix, especially property/listing separation and public visibility; preserve business data and permission boundaries.
- Authentication: legacy password hash verification, verified states, admin role safety, MFA and session invalidation.
- Database schema conversion under recovery tests; company-scoped authorization on every developer/admin mutation.
- Payments, media uploads, licensing and auto updater tested under failure and replay scenarios.
- Accessibility, browser, mobile, dark-mode, English/Khmer/Chinese content and load testing.
- Exact clean-install customer ZIP verified on Apache/cPanel and Nginx/aaPanel.
- No production deployment or Main merge without separate acceptance decision.
