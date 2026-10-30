# Laravel v2 architectural standard

Target: Laravel 13, PHP 8.3+, native framework defaults, typed PSR-4 code.

- Use Laravel's standard root `app/`, `bootstrap/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`.
- Use clearly nested Laravel feature folders. The initial foundation uses `app/Modules/Area/Feature`; migration planning may adopt native `app/Http/Controllers/Area/Feature`, matching requests/services/views. Do not create parallel implementations of the same feature.
- Admin feature examples: Admin/Dashboard, Admin/Developer/Companies, Admin/Appearance/Header, Admin/Appearance/Footer, Admin/Finance, Admin/PolicyCenter.
- Shared domain rules must not be duplicated across Public, Account, Developer and Admin.
- Route modules belong under `routes/` and use Laravel web sessions, authorization, and CSRF.
- Use Form Requests, Policies/Gates, Eloquent, and transactions instead of custom framework replacements.
- Preserve v1 behavior, URLs, company scope, license state and customer data through tested migration adapters only. Delete temporary adapters after migration.
- **Final CSS:** only `resources/css/frontend/app.css` and `resources/css/admin/app.css`, grouped internally with one-line `/* ===== AREA / FEATURE / COMPONENT ===== */` headers. This consolidation is pending; the foundation currently imports smaller files.
- Two JS area entry points; use smaller feature modules for maintainability.
- Vite emits cache-busted assets. Customers receive built assets and vendor; no Node/Composer runtime command required.
- Web server document root must point to Laravel's `public/`.
- Payment and updater operations require security and rollback acceptance; never copy unreviewed v1 code into the new tree.
- **Laravel-only final product:** remove legacy Pure PHP root folders, old patch files, duplicate CSS/JS, unused compatibility code, and old installer/updater after tested parity. Enforce through `scripts/verify-no-legacy.php` on source and extracted release.
- Main and live sites stay unchanged until approved production cutover; secure backups are kept separately from the active codebase.

See `docs/LEGACY_RETIREMENT.md` for the complete cleanup gate.
