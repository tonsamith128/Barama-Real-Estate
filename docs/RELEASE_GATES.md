# Release Gates

Barama Real Estate uses explicit release gates so development does not accumulate unfinished, duplicated, or unverified implementations.

## Automated gate design

The Quality workflow is designed to validate the source repository, MySQL schema, exact generated customer ZIP, upgrade path, Apache/Nginx routing, browser/accessibility behavior, localization, and CodeCanyon package preparation.

The automated gates enforce one canonical first-party stylesheet and one canonical first-party JavaScript file.

## Last fully executed automated baseline

The last fully executed green baseline is the 0.9.10 development line at commit `fa77adcf6b6fe388e9a3b8cca6cd310bbd2829b6` (Quality run 35529203163).

That run completed with assigned GitHub-hosted runners and passed:

- repository structure and PHP syntax QA
- ordered MySQL migration install and replay
- domain/account/security/notification smoke tests
- deterministic SMTP and License Manager contract QA
- source upgrade compatibility
- exact customer ZIP build/install/upgrade validation
- Apache root/subdirectory routing QA
- Nginx + PHP-FPM front-controller QA
- Chromium responsive/accessibility QA
- Firefox and WebKit critical-flow compatibility QA

## Implemented gates pending runner execution

Beginning with the localization milestone, GitHub Actions jobs have been created but GitHub has not assigned a hosted runner. The raw Actions job records show `runner_id: 0` and `steps: []`, so these runs are infrastructure/startup failures rather than executed Barama test failures.

The following gates are implemented in the repository but must be rerun before they can be marked closed:

- English/Khmer/Chinese locale catalog parity, normalization, persistence, and exact-package rendering
- customer-package third-party notices for Bootstrap 5.3.3 / MIT
- exact-package dependency and external-host allowlist inspection
- clean production default with no demo companies, projects, properties, listings, inquiries, leads, or customer accounts
- exact-package rejection of development manifests, dependency directories, internal QA fixtures, logs, dumps, backups, and known test credentials
- strengthened route/controller contract validation for exact and semantic route collisions, duplicate path parameters, missing/non-public controller actions, invalid middleware contracts, and critical route presence
- exact-package release metadata/checksum consistency across source VERSION, ZIP filename, packaged VERSION, README marker, archive comment, and SHA-256 sidecar
- installer security-header assertions on exact-package Apache/Nginx root and subdirectory hosting
- updater offline grace restricted to classified transport/service outages, never explicit license rejection
- installer requires explicit active product/domain/status bindings; installation ID is always sent and any echoed installation ID must match exactly
- installer revalidates the activation token immediately before final database/runtime mutation
- live-license rejection QA hardened against outage false positives and concurrent test runs
- packaged customer manifest documenting installation, runtime contents, external services, checksum integrity, and upgrade behavior
- packaged HTML customer help file at `documentation/index.html`
- CodeCanyon preparation scan for inline CSS attributes, inline JavaScript event handlers, runtime `eval()`, unsafe PHP short tags, and runtime debug dumps

These gates remain pending until a Quality run receives a real runner and completes all workflow jobs successfully.

## Development rules

1. Extend the canonical implementation; do not create versioned source copies.
2. Keep page styling in `assets/css/app.css` unless a future split has an explicit architectural reason.
3. Keep browser behavior in `assets/js/app.js` unless a future split has an explicit architectural reason.
4. Add database changes through ordered, idempotent migrations rather than editing production schemas manually.
5. Do not commit secrets, runtime logs, cache files, uploads, generated packages, or local editor state.
6. Do not advertise a feature as complete until routes, permissions, validation, UI states, failure states, and persistence are implemented.
7. Do not mark v1.0.0 ready until every remaining automated and manual release gate is completed.

## Validated hardening coverage through 0.9.10

- strong shared password policy
- email verification
- verification-gated company onboarding and invitation acceptance
- server-side auth-version session invalidation
- idle and absolute browser-session lifetime enforcement
- administrator user status and role safeguards
- final-active-administrator protection
- immutable security audit events with hashed origin metadata
- installer-created verified administrator
- exact-package security schema installation
- canonical secure updater with backup confirmation and administrator re-authentication
- installed-license validation and offline-grace behavior
- downgrade blocking and concurrent-upgrade locking
- ordered pending-migration execution with clean replay
- source and exact-customer-ZIP upgrade compatibility
- deterministic Ton Samith License Manager contract QA for Direct and Envato methods
- canonical license deactivation support
- root/subdirectory request-path normalization
- exact-package Apache routing and Nginx front-controller preflight
- deterministic SMTP AUTH LOGIN multipart delivery
- installer initializes safe PHP-mail defaults without requiring SMTP knowledge or a test-email gate
- secure SMTP TLS peer/hostname verification configuration for post-install SMTP settings
- exact-package Chromium mobile/tablet/desktop QA
- Light/Dark persistence checks
- keyboard/focus behavior and serious/critical automated accessibility checks
- exact-package Firefox and WebKit installer/public/authenticated critical-flow compatibility

## Open gates before CodeCanyon v1.0.0

These remain intentionally open:

- successful rerun of all implemented 0.9.11–0.9.13 automated gates after GitHub hosted-runner execution resumes
- live Ton Samith License Manager success using Barama-specific Direct and Envato test credentials through the installer/live-server test
- final live-server Apache/shared-hosting installer acceptance test
- final live-server Nginx installer acceptance test
- final live-server verification of clean-route detection and no-configuration query-routing fallback
- live administrator authentication and full dashboard/account navigation acceptance
- disposable live customer registration/email-verification and administrator user-write acceptance
- final human browser/device visual review on representative desktop, tablet, and mobile browsers
- final human Light/Dark Mode visual review
- final live-server production email delivery using a real supported SMTP/PHP-mail provider
- final human English/Khmer/Chinese translation and page-specific content review
- final human Safari/iOS and Edge/Chrome compatibility review
- final human CodeCanyon package/submission review
- online documentation review against the exact v1.0.0 package
- successful execution of the strengthened 0.9.15 route/controller contract gate before RC1 promotion

No open or pending gate should be represented as complete until it has been exercised on the applicable environment.


## RC1 promotion

The authoritative RC1 promotion checklist is `docs/RC1_CHECKLIST.md`.

Do not change `VERSION` to `1.0.0-RC1` until the checklist's automated, live-server, and human acceptance gates have recorded successful results.


## RC1 promotion guard

The 0.9.16 development line adds a machine-readable RC1 acceptance record at `docs/RC1_ACCEPTANCE.json` and the canonical validator `tools/rc1-readiness.php`.

The guard covers all mandatory automated, live-server, and human acceptance gates listed in `docs/RC1_CHECKLIST.md`.

During development, pending gates are allowed and reported. A manual `--strict` run fails while any required gate is pending. If `VERSION` is changed to `1.0.0-RC1`, strict validation becomes automatic, so RC1 promotion cannot pass repository QA with incomplete acceptance evidence.

Only non-secret evidence belongs in the record. Secrets remain outside Git history.

This guard is implemented but remains pending real GitHub Actions execution while hosted-runner allocation is unavailable.


## Controlled RC1 evidence workflow

The 0.9.17 development line adds a repository-only evidence policy and writer for docs/RC1_ACCEPTANCE.json.

- tools/rc1-acceptance-lib.php is the canonical definition of the 23 gates and evidence hygiene rules.
- tools/rc1-evidence.php is dry-run by default and requires --apply for a write.
- only canonical gates and pending/pass states are accepted.
- pass requires concise non-secret evidence; pending must contain no evidence.
- common credential/secret patterns, multiline evidence, oversized evidence, unknown keys, and stale pending evidence are rejected.
- writes use a same-directory temporary file plus atomic rename and refuse to apply if the record changed after it was read.
- tools/rc1-evidence-smoke.php validates the workflow against an isolated temporary copy and verifies the canonical record is untouched.
- the customer ZIP is unchanged because all evidence tooling remains development-only.

This hardening improves the future live-server acceptance session but does not close any automated, live-server, or human RC1 gate by itself.
