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
9. exact customer ZIP build
10. clean installation from the extracted customer ZIP
11. production runtime state verification
12. installer lock and migration replay verification
13. validated artifact upload

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

The current 0.9.4 development line has automated coverage for:

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

## Open gates before CodeCanyon v1.0.0

These remain intentionally open:

- customer-facing upgrade workflow and upgrade-from-previous-release QA
- live Ton Samith License Manager success using a Barama-specific valid test credential
- supported Apache shared-hosting installation validation
- supported Nginx installation validation
- browser/device QA on desktop, tablet, and mobile
- Light/Dark Mode visual QA
- keyboard/focus accessibility QA
- production email delivery using a real supported mail transport
- localization QA for configured languages
- browser compatibility QA
- final third-party notices and CodeCanyon package review
- online documentation review against the exact v1.0.0 package
- final demo/sample-data decision and clean-production-default verification

No open gate should be represented as complete until it has been exercised on the applicable environment.
