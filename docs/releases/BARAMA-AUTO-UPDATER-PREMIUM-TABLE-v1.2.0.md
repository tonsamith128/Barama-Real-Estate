# Barama Auto Updater Premium Table v1.2.0

## Release identity

- Deployable self-contained aaPanel package: `Barama-Auto-Updater-Premium-Table-v1.2.0.zip`
- SHA-256: `8146f93ea0f51b026d51bd42c133798b405d446728cb87425cde50efef817f69`
- QA: **36/36 tests passed** in the build folder and a separate freshly extracted ZIP.
- PHP view syntax PASS; JS syntax validated during build; on aaPanel no Node.js required.
- State: ZIP **prepared but not yet installed** on the user's production aaPanel host.
- Full runnable PHP/CSS/JS sources are bundled in the separately available ZIP. This PR documents the release contract; it does not embed or merge the actual application source files.

## Design

Approved visual direction: compact premium license hero with SVG security shield, verified status, domain, version, last check and existing Verify again action. Below: one clean eight-item table (`Item | Status | Summary | Detail`), attention and setup required at top, inline accessible Detail expansion per row, Escape to close, light/dark themes, mobile horizontal scroll, fewer words and no unrelated demo content.

## Exact file changes

Modify only:
- `application/resources/views/workspace/system-center.php`

Add only:
- `assets/css/barama-auto-updater-premium-v120.css`
- `assets/js/barama-auto-updater-premium-v120.js`

Source pinned to live Barama v0.9.22-dev with v1.1.3a verified License Center. Refuse mismatched source. Existing CSRF-protected validation route, controller, publisher client, status session, browser network script, private activation token and domain binding remain unchanged. Publisher-verified state and domain match derive from actual runtime data; unsigned release, update entitlement, automated installation, backup/rollback readiness remain unverified or disabled as appropriate.

## Installer contract

- `python3 -B installer.py --check`: no credentials, database reads or network; checks exact source fingerprints, backend route, PHP 8.5 syntax, new path availability.
- `python3 -B installer.py --install`: rechecks source under lock, creates root-private SHA-verified backup of the previous view; writes assets before view atomically; stores rollback ID.
- `python3 -B installer.py --rollback BACKUP_ID`: restores exact view and removes new assets if and only if all installed hashes are unchanged.
- No schema or nginx change. Installation does not reissue a license token.

## Deployment gate

Operator uploads ZIP to `/root`, verifies outer checksum, extracts, runs `sha256sum -c SHA256SUMS.txt`, then all 36 portable tests before `--check` and `--install`. On a `STOP=...` signal, preserve live platform and investigate without bypassing. User must inspect live light/dark and responsive design plus Verify again and all Detail controls before acceptance.

Existing automatic signed release installation stays disabled and remains a separate feature milestone.
