# Barama v1.3.0 — Read-only Release Discovery

## Verified local artifact

- `Barama-Release-Discovery-Audit-v1.3.0.zip`
- SHA-256: `4026511e237a0e57d68cb9b26ffdd30f9c4c128eb80f220be16c35664e990622`
- 40/40 tests pass in the original source and 40/40 pass after clean ZIP extraction.
- All internal `SHA256SUMS.txt` entries and ZIP CRC checks pass.
- Target: installed Barama `v0.9.22-dev` with accepted Auto Updater Premium Polish v1.2.1.
- Full executable source resides in the standalone ZIP; this PR records the contract and safety tests, not a production deployment.

## Existing publisher integration verified in connected repository

The current Ton Samith publisher offers POST `/license/v1/update`, routed to `LicenseApiController::update`, then `LicenseUpdateService::manifest` with token, domain, installation ID, product slug, Envato item ID, current version, and stable/beta channel. It can return no newer release or a release metadata object. It does not require the client to download or install anything to perform this check.

**Security boundary:** The service currently computes `HMAC-SHA256` over canonical release metadata keyed by the *activation token*. An installed client knows that key, so this MAC is not an independent publisher-controlled signature. Do **not** claim strong publisher authenticity solely from a token-MAC match. A separate publisher-controlled asymmetric signing key with pinned public verifier is a later critical requirement.

The service checks the active licensed product/domain/installation but does not implement a separate support/update entitlement policy; **license active != update entitled**.

## Package interface

- `python3 -B audit.py --check`: local, network-free source and route contract preflight. No license token or private state loaded, no product file writes.
- `python3 -B audit.py --probe`: one explicitly requested HTTPS POST to the pinned `https://tonsamith.com/license/v1/update` endpoint using current private activation token. Emits only safe category labels; no raw body, secrets, domain, installation ID, release notes, or arbitrary error strings.
- Publisher may record normal API audit events. Neither command activates/deactivates a license, downloads packages, updates a site, or mutates local state.

## Release gates

1. Verify ZIP SHA, internal file checksums and 40 offline tests.
2. Run `--check` and resolve any source drift without bypass.
3. Run `--probe` only with authorized user request and inspect safe results.
4. Define independent publisher release signature and separate entitlement contract.
5. Later: validate downloaded ZIP SHA/signature and path rules, compatibility, private backup, rollback rehearsal, and explicit administrator confirmation before any installation.

**Production execution pending.** Visual baseline v1.2.1 and existing CSRF-guarded Check License behavior remain unchanged.
