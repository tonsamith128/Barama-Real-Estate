# Barama Release Discovery v1.3.0 — live audit acceptance

## User-supplied aaPanel result (2026-10-12)

- Outer ZIP SHA-256 and internal checksums: PASS
- 40 / 40 local tests: PASS
- Source-contract and PHP syntax preflight: PASS
- Publisher API HTTP: 200
- RELEASE_DISCOVERY=NO_RELEASE_LISTED
- RELEASE_METADATA=NOT_RETURNED
- TOKEN_MAC=NOT_APPLICABLE
- ENTITLEMENT_CHECK=NOT_IMPLEMENTED
- PUBLISHER_ASYMMETRIC_SIGNATURE=NOT_IMPLEMENTED
- DOWNLOAD_AND_INSTALL=DISABLED
- LOCAL_LICENSE_OR_SITE_FILES_MODIFIED=NO
- BARAMA_RELEASE_DISCOVERY_V130_COMPLETED

This is a successful **read-only connectivity and release-discovery** acceptance, not an update-installation acceptance. `NO_RELEASE_LISTED` is consistent with no newer release for the requested product/version/channel and does not prove there are no drafts or older releases. No publisher-signed manifest was tested because none was returned.

## Verified current publisher source

- `app/CommercialUpdates/CommercialUpdateRepository.php` selects only published releases; it is already used by `LicenseUpdateService::manifest()`.
- `app/CommercialUpdates/CommercialUpdateService.php` provides draft/published/disabled release registration and package inspection.
- `app/Licensing/LicenseUpdateService.php` checks current domain-bound activation but currently uses `hmac-sha256-activation-v1` for the release metadata MAC. As the client knows its activation token, this is **not independent publisher authenticity**.
- `app/Http/Controllers/Api/V1/LicenseApiController.php` exposes existing `/license/v1/update` POST and separate download route, but no explicit separate update entitlement decision is established by the inspected code.

## Next gated build

1. Specify publisher-owned **Ed25519** signing key and versioned canonical manifest signature; keep the private key only on publisher and pin versioned public key(s) inside clients.
2. Specify separate update-entitlement policy (eligibility is not necessarily the same as active license or paid support); agree on CodeCanyon/test/direct licensing rules before implementing.
3. Add read-only signed manifest QA tests using published and synthetic fixtures: reject tampering, incorrect product/domain binding, invalid key id, replay/downgrade and revoked release.
4. Keep update download and install disabled until independent signature verification, zip integrity, zip-slip prevention, PHP/runtime/migration compatibility, verified private file+DB backups, and demonstrated rollback pass.
5. Preserve the visually accepted Auto Updater v1.2.1 hero/table and existing CSRF/auth without app layout changes.

No new production application files have been modified by this audit. Browser UI acceptance of live v1.2.1 is separate.
