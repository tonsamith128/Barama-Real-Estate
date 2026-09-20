# Barama Real Estate Architecture

## Goal

Keep the product understandable to a buyer, maintainable by the author, and reviewable for CodeCanyon without accumulating duplicate implementations or asset fragmentation.

## Canonical layers

- index.php: the only web front controller.
- application/bootstrap/app.php: boot sequence, autoloading, configuration, sessions, and exception handling.
- application/app/Core: framework primitives only.
- application/app/Http/Controllers: request orchestration by product area.
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

Authentication, authorization, validation, upload security, installer hardening, rate limiting, and audit logging will be implemented as their respective modules are added.
