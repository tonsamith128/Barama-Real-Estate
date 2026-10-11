# Barama v1.1.4 — Read-only publisher validation diagnostics

**Production status: NOT RUN.** The accepted v1.1.3a Barama License Center remains unchanged.

## Artifact and test identity

- ZIP: `Barama-License-Publisher-ReadOnly-Diagnostics-v1.1.4.zip`
- ZIP SHA-256: `d719db435636af75f32012cc21002fae4fba075fa97cef1688775146a608ee13`
- Files: `diagnose.py`, `bin/publisher-probe.php`, `tests.py`, `README.md`, `SHA256SUMS.txt`
- **20/20 local tests pass**, and 20/20 pass again after a clean ZIP extraction, all internal checksums match.
- PHP syntax checked on local PHP 8.4; PHP 8.5 lint is performed on the target aaPanel server before diagnosis.
- No Node.js or ZIP installer.

## Why this is the next gate

The License Center UI now renders and returns an explicit `Unable to verify` result. That proves the application received an unconfirmed result; it **does not** establish the root cause. Do not revoke a license or change its domain based on that result alone.

The existing trusted Barama client contract is:
- POST `https://tonsamith.com/license/v1/validate`
- JSON fields `activation_token`, `domain`, `installation_id`
- Expected successful response: `success === true`, `license` object; `license.product_slug === barama-real-estate`, normalized domain match, optional installation binding match, and `status === active`
- Separate publisher support or update entitlement is **not** implied.

## Diagnostic behavior

`python3 -B diagnose.py --check`: offline package/path/version/PHP syntax preflight, no network and no token read.

`python3 -B diagnose.py --diagnose`: one explicitly authorized HTTPS POST using Barama's existing stored activation token loaded into a short-lived PHP CLI process. The token and private installation ID are never printed. The raw response/body/headers/error strings never leave the process. Only coarse fields are emitted: `DIAGNOSTIC_RESULT`, `HTTP_STATUS`, `PUBLISHER_SUCCESS_FLAG`, `LICENSE_OBJECT`, `ERROR_CODE_CATEGORY`.

Result categories include publisher non-success status, non-JSON/contract mismatch, local mismatch, verified remote active, remote product/domain/installation mismatch, transport/DNS/TLS/timeout.

The PHP script does not load the Barama HTTP bootstrap/session, touch the database, edit private license records or application files, or call activation/deactivation/revocation routes. The single publisher validation may still be recorded in the normal publisher request logs.

## Safety / acceptance

- Strict fixed Barama host/product/version, pinned trusted publisher HTTPS endpoint and no redirects.
- Confirm exact internal checksum and PHP 8.5 syntax.
- Do not display tokens, raw publisher content, private paths, hashes of tokens, or sensitive metadata.
- **No production deployment**; this kit remains isolated under `/root` and does not replace the v1.1.3a package.
- Production diagnostic outcome is pending and determines whether to fix the Barama response parser, publisher payload, publisher API/authentication, or infrastructure.

## Next phase after diagnosis

Review Ton Samith License Manager source and confirmed customer authentication/authorization before implementing domain unbind/replacement, license revoke/remove/restore and audited lifecycle. Do not invent publisher mutation endpoints; keep signed update delivery disabled until validated.
