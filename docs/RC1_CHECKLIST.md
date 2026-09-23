# v1.0.0-RC1 Readiness Checklist

Barama Real Estate must not be labeled v1.0.0-RC1-ready until every required repository, automated, live-server, and human acceptance gate below has a recorded result.

## Repository-controlled gates

The repository must retain:

- one canonical first-party stylesheet: `assets/css/app.css`
- one canonical first-party JavaScript file: `assets/js/app.js`
- no duplicate historical source-version trees
- no old/backup/copy/legacy implementation files
- deterministic ordered migrations
- complete route/controller contracts with no exact or semantic method/path collisions, duplicate path parameters, or non-public controller actions
- fresh-install and supported-upgrade paths
- five-step installer only
- canonical updater
- CodeCanyon customer documentation and third-party notices
- clean production defaults with no demo marketplace inventory or hard-coded administrator account
- one canonical RC1 acceptance policy plus controlled evidence writer and smoke test
- one canonical read-only live-server preflight covering both pre-install and installed states
- machine-verifiable RC candidate artifact binding for exact ZIP filename, SHA-256, source version and source commit

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
- route/controller contract, including semantic dynamic-route collision and public-action validation
- source upgrade compatibility, including revoked-license rejection versus temporary-outage grace
- exact customer ZIP build
- RC candidate artifact manifest generation and verification against the workflow commit
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

## RC1 acceptance record

Non-secret acceptance evidence is recorded in `docs/RC1_ACCEPTANCE.json`.

Each mandatory automated, live-server, and human gate has exactly one status:

- `pending`
- `pass`

A `pass` entry must include concise non-secret evidence. Never put passwords, purchase codes, license keys, SMTP credentials, database credentials, activation tokens, or other secrets in the record.

Run:

```bash
php tools/rc1-readiness.php --strict
```

before promoting the version. During normal `0.9.x-dev` work, the validator checks the record structure and reports pending gates without failing. If `VERSION` is ever set to `1.0.0-RC1`, strict mode is automatic and every mandatory gate must be passed.

## Controlled evidence workflow

Use tools/rc1-evidence.php instead of editing passed gates casually.

A proposed change is dry-run only unless --apply is supplied. The writer accepts only the 23 canonical GROUP.GATE names, requires concise non-secret evidence for pass, forbids retained evidence for pending, rejects common credential/secret patterns, updates updated_at in UTC, checks for concurrent file changes, and replaces the JSON record atomically.

Example dry-run:

    php tools/rc1-evidence.php --gate=live_server.nginx_installer_acceptance --status=pass --evidence="Nginx installer acceptance passed on the validated package and commit."

After reviewing the dry-run result, add --apply to persist it. To reopen a gate, set --status=pending with no evidence.

The validator independently rejects unknown groups/gates/entry keys, unsafe evidence, and pending gates that retain stale evidence. The isolated smoke test must pass without mutating docs/RC1_ACCEPTANCE.json.

## RC1 promotion rule

Only after the repository-controlled gates are intact and the automated/live/human gates above have recorded successful results in `docs/RC1_ACCEPTANCE.json` should `VERSION` be promoted to `1.0.0-RC1`.

RC1 must then be built from that exact commit, and the generated ZIP plus SHA-256 checksum must pass the full exact-package pipeline again.

Final `v1.0.0` requires one additional release acceptance pass from the exact RC-approved source and completion of the dated changelog entry.
