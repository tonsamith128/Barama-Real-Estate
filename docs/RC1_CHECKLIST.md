# v1.0.0-RC1 Readiness Checklist

Barama Real Estate must not be labeled v1.0.0-RC1-ready until every required repository, automated, live-server, and human acceptance gate below has a recorded result.

## Repository-controlled gates

The repository must retain:

- one canonical first-party stylesheet: `assets/css/app.css`
- one canonical first-party JavaScript file: `assets/js/app.js`
- no duplicate historical source-version trees
- no old/backup/copy/legacy implementation files
- deterministic ordered migrations
- complete route/controller contracts with no duplicate method/path registrations
- fresh-install and supported-upgrade paths
- five-step installer only
- canonical updater
- CodeCanyon customer documentation and third-party notices
- clean production defaults with no demo marketplace inventory or hard-coded administrator account

## Automated gates

A real GitHub Actions run must complete successfully for the RC candidate commit.

Required jobs:

1. Foundation QA
2. Exact Package Web Server QA
3. Exact Package Browser & Accessibility QA

Foundation QA must include:

- PHP syntax and repository structure
- localization contract
- MySQL migrations and clean replay
- domain/account/security/notification smoke tests
- SMTP contract
- License Manager deterministic contract, including temporary-vs-permanent failure classification
- HTTP root/subdirectory normalization
- route/controller contract
- source upgrade compatibility, including revoked-license rejection versus temporary-outage grace
- exact customer ZIP build
- exact-package metadata/checksum consistency
- exact-package compliance
- CodeCanyon preparation scan
- exact-package fresh installer
- exact-package upgrade

The web-server job must validate the exact ZIP behind Apache and Nginx, including root/subdirectory routing, protected paths, installer no-cache/noindex behavior, security headers, and secure session-cookie attributes.

The browser job must validate the exact ZIP with the configured Chromium/Firefox/WebKit compatibility and accessibility coverage.

A workflow attempt with no assigned runner, no steps, or skipped dependent jobs does not close a gate.

## Live-server gates

The detailed acceptance procedure is `docs/LIVE_SERVER_ACCEPTANCE.md`.

These are intentionally deferred to the real installer/server acceptance session:

- Ton Samith direct license activation
- CodeCanyon/Envato purchase-code activation
- production-domain/license binding
- installer on the intended Apache/shared-hosting environment
- installer on the intended Nginx environment where supported
- real SMTP or supported PHP-mail delivery
- HTTPS/cookie/session behavior
- runtime directory permissions
- updater against a backed-up installed site

## Human acceptance gates

Before RC1:

- desktop/tablet/mobile visual review
- Light Mode review
- Dark Mode review
- keyboard/focus review
- English content review
- Khmer content review
- Chinese content review
- Safari/iOS acceptance
- Edge/Chrome acceptance
- final customer HTML help review
- online documentation parity review
- final CodeCanyon package/upload-form review

## RC1 promotion rule

Only after the repository-controlled gates are intact and the automated/live/human gates above have recorded successful results should `VERSION` be promoted to `1.0.0-RC1`.

RC1 must then be built from that exact commit, and the generated ZIP plus SHA-256 checksum must pass the full exact-package pipeline again.

Final `v1.0.0` requires one additional release acceptance pass from the exact RC-approved source and completion of the dated changelog entry.
