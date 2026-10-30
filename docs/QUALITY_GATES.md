# Acceptance gates

- PSR-4, PHP PSR-12, Laravel Pint, no duplicate versions or patch-marker source.
- **Only two first-party CSS source files**: frontend `app.css` and admin `app.css`, each with named, correctly grouped feature sections. This is a future consolidation gate; the foundation currently has smaller imported CSS sources.
- Two JavaScript entry points with organized feature modules; no inline behavior copy-pasted across views.
- `php scripts/verify-structure.php` succeeds.
- `php scripts/verify-no-legacy.php` succeeds; old root `application/`, `installer/`, `updater/`, `assets/` and `index.php` are forbidden in Laravel-only source and distribution.
- Composer install, Laravel feature tests, and npm build succeed.
- Authorization, company isolation, session, email reset and security regression tests pass.
- Real shared-hosting clean installer, license binding, signed update and recovery tested.
- Preserve customer data and public URLs during a verified migration.
- Human accessibility, light/dark, translations, and responsive acceptance.
- Run the no-legacy guard on **the extracted exact final customer ZIP**, not just the repository.
- No merge into Main or live deployment before full feature parity, signed release acceptance and independently verified rollback.

See [Legacy Retirement](LEGACY_RETIREMENT.md) for the complete release sign-off and removal policy.
