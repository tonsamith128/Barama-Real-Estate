# Barama Laravel v2 architecture standard

**Target:** Laravel 13, PHP 8.3+, native Laravel project conventions, typed PSR-4 code.

## PHP conventions

- `app/Http/Controllers/<Area>/<Feature>/<Feature>Controller.php` for HTTP orchestration.
- `app/Http/Requests/<Area>/<Feature>Request.php` for validated transport input.
- `app/Models/`, `app/Policies/`, `app/Services/`, `app/Actions/` for domain and application boundaries when needed.
- `resources/views/<area>/<feature>/index.blade.php` for presentation.
- `routes/web.php` loads the area route files; Laravel supplies cookies, session and CSRF.
- Use Laravel's password broker, authentication guard, signed email verification, native middleware and password hashing rather than copying v1 security primitives.
- Shared business rules belong to shared services, not duplicated Admin and Developer implementations.

## Planned functional folders

- Auth: Login, Register, ForgotPassword, ResetPassword, ConfirmEmail, TwoFactor, Logout.
- Frontend: Home, Properties, Projects, Companies, Contact and Search.
- Account: Dashboard, Profile, SavedProperties, Inquiries and Notifications.
- Developer: Dashboard, Companies, Properties, Listings, Projects, Leads and Team.
- Admin: Dashboard, Developer, Appearance (Header, Homepage, Footer, Menu), Finance, MediaLibrary, PolicyCenter, Users and System.

## Exactly two CSS source files

- Frontend: `resources/css/frontend/app.css`.
- Admin and Developer workspace: `resources/css/admin/app.css`.
- Both are **self-contained** and use single-line `/* ===== AREA / NUMBER / FUNCTION ===== */` comments. Do not create additional first-party feature CSS source files.
- Maintain consistent component namespacing; avoid version suffixes and old patch markers.
- Keep JavaScript in two area entry points with separate feature modules as complexity grows.
- Vite bundles/version-hashes distribution assets. Hash-named compiled artifacts are not versioned source patches.

## Security and release boundaries

- Web root must be `public/`; keep framework code and secrets outside it.
- Customer setup cannot require running Composer/Node once the distribution is built.
- Preserve v1 passwords, users, roles, data, media, license state and public URLs only through verified migration adapters and rehearsed recovery.
- Production cutover and Main merge require explicit approval and regression proof.
- The final product must contain no legacy Pure PHP framework, routes, standalone installer/updater or old CSS/JS.
- Enforce `scripts/verify-no-legacy.php` and `scripts/verify-structure.php` on source, and no-legacy on the exact release ZIP.

See `docs/MIGRATION.md`, `docs/QUALITY_GATES.md`, and `docs/LEGACY_RETIREMENT.md`.
