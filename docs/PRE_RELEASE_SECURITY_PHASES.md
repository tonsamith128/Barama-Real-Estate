# Barama Pre-CodeCanyon Security Workstream

Branch: feature/pre-codecanyon-turnstile-signed-updates-r1p13

## Scope and current safety boundary

The production backup 20261011T064358Z-25403005 passed R1p9 filesystem/database isolated restoration and R1p10 offline PHP syntax checks. R1p12b passed CLI/FPM 8.5 extension parity, but explicitly DID NOT establish safe HTTP boot, sandbox config replacement, or external-service isolation. These are release blockers, not optional tests.

This feature branch adds **unwired** security primitives first. It is NOT a production deployment bundle. Neither Turnstile nor automatic update installation is enabled by these files.

## R1p13 isolated runtime requirements

Before running archived PHP code:
1. Copy the pinned backup into private disposable storage and verify its SHA-256 baseline.
2. Exclude/replace the production environment.php and installation/license state BEFORE the first PHP include. Do not execute or print archived secrets.
3. Use disposable, separately scoped database credentials and restore the SQL dump into a temporary instance.
4. Prove network isolation from SMTP, payments, license endpoints, webhooks, external APIs and live MySQL; disable email, queues, cron, and background jobs at both application and network layers.
5. Run PHP/app runtime under an unprivileged sandbox and verify no writable host mounts or public bound ports.
6. Only then exercise GET /, GET /login, unauthenticated /dashboard redirect and sandbox test-user auth; do not use real accounts.
7. Capture only redacted results; remove containers, data volumes and temp files even on failure.

## R1p14 Turnstile integration plan

Canonical security setting at /dashboard/settings/security (no duplicate page).
- Disabled until site and secret keys are configured and a test Siteverify succeeds.
- Secrets must reside encrypted/protected on the server, never in HTML or release packages.
- Per-action enforcement after CSRF and before any registration, login, password-reset, inquiry or contact mutation.
- Render the widget with a public site key; include the form-specific action.
- Always validate tokens server-side using TurnstileVerifier and fail closed when enabled. Preserve throttling and CSRF; never bypass based on widget appearance.
- Verify exact expected hostname and action. Token expiry/replay are enforced by Cloudflare Siteverify.
- Test all listed routes, replay, timeout, invalid hostname/action and network outage, using official test keys in staging only.

### R1p14 encrypted settings foundation (unwired)

The draft branch now includes `application/app/Security/TurnstileSettingsStore.php`.
It stores the Turnstile secret in private `application/storage/settings/turnstile.json`
using AES-256-GCM. Its key is derived from the existing installation `APP_KEY`
with HKDF-SHA256; public metadata (enabled state, site key and protected forms)
is also authenticated. Reads reject damaged or incorrectly keyed records. The
status method returns configuration flags and the public site key, never the
secret. A missing config defaults to disabled.

This class is **not yet wired to any admin UI or public form middleware**. No
customer-visible switch must be offered before widget rendering, per-route
server-side validation, and test coverage are complete. Confirm private
storage-deny rules work for both Nginx and Apache before any deployment.

## R1p15 signed update plan

Barama already has /updater/ and UpgradeService; do NOT add a second updater.
- Add update availability discovery as a separate service from the existing migration workflow.
- Verify detached author signature with a built-in pinned RSA 3072+ public key (private key never ships).
- Enforce exact product slug, a strictly newer version, pinned HTTPS package host, file size and SHA-256.
- Download to private temporary storage with DNS/IP protections and redirect rejection.
- Require administrator re-authentication, CSRF, authorization and license entitlement before staging or installation.
- Require storage/preflight checks, backup/restore tests, update lock and atomic staging before migration.
- No unattended installation by default. Auto-checks may be scheduled; manual verified ZIP fallback.
- Database migration rollback must not be promised without a tested restore strategy; report honest recovery status.

## Source divergence caution

Repository VERSION reads 0.9.22-dev while live backup was made on 2026-10-11. Do NOT push this branch directly to the production webroot until code parity and existing live feature changes are reconciled.

## Tests and release gates

- PHP syntax and unit tests for Turnstile response parsing and signed manifest validation.
- R1p13 isolated runtime acceptance, followed by R1p14 forms/auth and R1p15 guarded update workflow.
- Production and CodeCanyon release remain blocked until site-specific end-to-end and rollback testing pass.
