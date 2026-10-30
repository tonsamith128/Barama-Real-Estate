# Laravel v2 architectural standard

Target: Laravel 13, PHP 8.3+, native framework defaults, typed PSR-4 code.

- Use Laravel's standard root app/, bootstrap/, database/, public/, resources/, routes/, storage/, tests/.
- Implement real features under app/Modules/Area/Feature, e.g. app/Modules/Auth/Login/LoginController.php.
- Admin feature examples: app/Modules/Admin/Dashboard, Admin/Developer/Companies, Admin/Appearance/Header, Admin/Appearance/Footer, Admin/Finance, Admin/PolicyCenter.
- Shared domain rules must not be duplicated across Public, Account, Developer and Admin.
- Each route module is included through routes/web.php, hence Laravel web sessions and CSRF.
- Use Form Requests for validation, Policies/Gates for authorization, Eloquent for durable data, transactions for state changes.
- Preserve v1 behavior, URLs, company scoping, license state and business data only through tested migration adapters.
- Vite inputs are resources/css/frontend/app.css and resources/css/admin/app.css. Each imports smaller files with one-line feature comments.
- Default Vite emits cache-busted filenames. Customers receive built assets and vendor; no Node/Composer requirement at install time.
- The web server document root must point to Laravel's public/ folder.
- Payment and updater operations require security and rollback acceptance, never copied unreviewed from v1.
- No production rollout or main branch merge without all documented gates passing.

