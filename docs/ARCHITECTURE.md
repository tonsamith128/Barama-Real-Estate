# Barama Real Estate Architecture

## Goal

Keep the product understandable to a buyer, maintainable by the author, and reviewable for CodeCanyon without accumulating duplicate implementations or asset fragmentation.

## Canonical layers

- index.php: the only web front controller.
- application/bootstrap/runtime.php: shared runtime bootstrap, autoloading, persisted runtime configuration and sessions.
- application/bootstrap/app.php: installed-application exception handling and router boot sequence.
- application/app/Core: framework primitives only.
- application/app/Http/Controllers: request orchestration by product area.
- application/app/Domain: canonical business rules, status catalogs, and lifecycle rules.
- application/app/Repositories: persistence boundaries; controllers must not query directly.
- application/app/Services: application workflows that coordinate repositories and domain rules.
- application/config: runtime configuration definitions.
- application/routes: route declarations.
- application/resources/views: server-rendered views.
- application/storage: non-source runtime state.
- assets/css/app.css: the single first-party stylesheet.
- assets/js/app.js: the single first-party browser entry point.

## Account boundaries

/account is the customer/buyer/renter/investor workspace.

/company is the public real-estate developer/company directory and public profiles.

/developer is the private authenticated workspace for developers and authorized company representatives.

/dashboard is the private administration platform.

These areas may share Core services and domain services, but they must not share controllers or authorization assumptions.

## Dependency policy

The application foundation has no required PHP package-manager dependency. Composer may be introduced later only where it creates clear product value, but customers must not need SSH or Composer to complete the final installation.

Node.js is not a runtime requirement and must not become a customer installation requirement.

## Asset policy

Do not create page-specific first-party CSS or JavaScript files by default.

New styles belong in assets/css/app.css, organized by section. New browser behavior belongs in assets/js/app.js, organized by feature/module. A split requires a concrete performance or maintainability reason and should remain canonical rather than versioned copies.

## Version policy

Do not keep old source copies such as app-v1.css, app-old.js, ControllerBackup.php, or module-v0.9/.

Git history is the source-history mechanism. The repository tree should contain the current implementation only.

## Security baseline

- PDO prepared statements
- HTTP-only session cookies
- strict session mode
- CSRF token primitive
- contextual output escaping
- security response headers
- production-safe exception output
- no credentials committed to source

Authentication, authorization, login throttling, and CSRF enforcement are implemented in the current foundation. Domain validation is centralized as modules are added. Upload security, installer hardening, broader rate limiting, and audit logging remain required before the final v1.0.0 release.


## Installer boundary

`/installer/` is a physical installer entry point so both GET and POST use the same canonical trailing-slash URL without depending on front-controller rewrite redirects. The root `index.php` redirects uninstalled requests to that path with no-cache headers.

The customer sees exactly five installation steps. Installer state is server-side session data; purchase codes and form passwords are not written into persistent installer state. Successful installation stores protected runtime configuration, normalized license activation state, and an installation lock under `application/storage/app`.

The installed application and the installer share the canonical `assets/css/app.css` and `assets/js/app.js`; installer-specific first-party CSS/JavaScript files are prohibited.
