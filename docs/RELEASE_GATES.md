# Release Gates

Barama Real Estate uses explicit release gates so development does not accumulate unfinished, duplicated, or unverified implementations.

## Automated gates

GitHub Actions currently runs the following against MySQL 8 and the exact generated customer ZIP:

1. repository structure and PHP syntax QA
2. ordered database migration install
3. migrated schema verification
4. clean migration replay
5. domain write and company-scope smoke tests
6. account and company-onboarding smoke tests
7. release security hardening smoke tests
8. notification delivery smoke tests
9. source upgrade-from-previous-release smoke test
10. exact customer ZIP build
11. clean installation from the extracted customer ZIP
12. production runtime state verification
13. installer lock and migration replay verification
14. exact customer ZIP upgrade-from-previous-release smoke test
15. validated artifact upload
16. exact-package Apache root/subdirectory HTTP routing QA
17. exact-package Nginx root/subdirectory HTTP routing QA
18. exact-package Chromium mobile/tablet/desktop browser QA
19. exact-package keyboard/focus and serious/critical accessibility QA

The automated gates also enforce one canonical first-party stylesheet and one canonical first-party JavaScript file.

## Development rules

1. Extend the canonical implementation; do not create versioned source copies.
2. Keep page styling in assets/css/app.css unless a future split has an explicit architectural reason.
3. Keep browser behavior in assets/js/app.js unless a future split has an explicit architectural reason.
4. Add database changes through ordered, idempotent migrations rather than editing production schemas manually.
5. Do not commit secrets, runtime logs, cache files, uploads, generated packages, or local editor state.
6. Do not advertise a feature as complete until routes, permissions, validation, UI states, failure states, and persistence are implemented.
7. Do not mark v1.0.0 ready until every remaining manual release gate below is completed.

## Closed hardening gates

The current 0.9.9 development line has automated coverage for:

- strong shared password policy
- email verification
- verification-gated company onboarding and invitation acceptance
- server-side auth-version session invalidation
- idle and absolute browser-session lifetime enforcement
- administrator user status management
- administrator-role management safeguards
- final-active-administrator protection
- immutable security audit events with hashed origin metadata
- installer-created verified administrator
- exact-package security schema installation
- root version-mismatch redirect to the canonical `/updater/` path
- administrator re-authentication and server-side backup confirmation before upgrade
- installed-license validation and offline-grace behavior
- downgrade blocking and concurrent-upgrade locking
- ordered pending-migration execution with clean replay
- protected installation-version metadata updates
- upgrade security-audit events
- source and exact-customer-ZIP `0.9.3-dev` upgrade compatibility
- deterministic Ton Samith License Manager contract QA for Direct and Envato methods
- product, domain, installation-ID, and active-status license binding
- inactive/revoked contract rejection
- canonical license deactivation support
- exact-package presence of the hardened License Manager client
- customer-package exclusion of internal live-license QA scripts
- canonical root and subdirectory request-path normalization
- exact-package Apache 2.4 + mod_rewrite routing preflight
- exact-package Apache subdirectory routing preflight
- exact-package Nginx + PHP-FPM front-controller routing preflight
- protected-path denial and static-asset delivery under both tested web-server contracts
- deterministic SMTP AUTH LOGIN multipart delivery
- installer mail configuration validation and fingerprint invalidation
- Step 4 server-side test-email enforcement without an extra visible installer step
- secure SMTP TLS peer/hostname verification configuration
- protected runtime persistence of mail delivery settings
- Step 5 email-delivery configuration health check
- customer-package exclusion of internal SMTP QA fixtures
- exact-package Chromium QA at mobile, tablet, and desktop widths
- Light/Dark toggle and persistence checks
- mobile global-navigation and workspace-drawer keyboard behavior
- canonical visible focus indicators and focus restoration
- serious/critical WCAG 2.0/2.1 A/AA automated accessibility checks
- horizontal-overflow checks across core public/authenticated surfaces
- short-lived browser screenshot artifacts for visual review

## Open gates before CodeCanyon v1.0.0

These remain intentionally open:

- live Ton Samith License Manager success using Barama-specific Direct and Envato test credentials through the manual `Live License Manager QA` workflow
- final live-server Apache/shared-hosting installer acceptance test
- final live-server Nginx installer acceptance test
- final human browser/device visual review on representative desktop, tablet, and mobile browsers
- final human Light/Dark Mode visual review
- final live-server production email delivery using a real supported SMTP/PHP-mail provider
- localization QA for configured languages
- browser compatibility QA
- final third-party notices and CodeCanyon package review
- online documentation review against the exact v1.0.0 package
- final demo/sample-data decision and clean-production-default verification

No open gate should be represented as complete until it has been exercised on the applicable environment.
