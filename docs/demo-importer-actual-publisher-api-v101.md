# Demo Importer — TonSamith.com API v1.0.1 (actual publisher contract)

**Branch:** Draft PR #130. **Production v0.9.24:** UI preview + spinning ring only. Do not enable automatic ZIP installation yet.

The real TonSamith.com Demo Content implementation (source `tonsamith168-png/tonsamith`, `routes/demo-content.php`, `DemoImportController`, `DemoPackageService`) has its own stable endpoints and detached ZIP signing. Prior proposed `/license/v2/demo/package` and embedded ZIP `envelope.json` signatures do **not** match it, and must never be used to authorize customer imports.

## Real publisher routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/demo-import/v1/public-key` | Public Ed25519 signing identity |
| GET | `/api/demo-import/v1/products/barama-real-estate/demos` | Active-license, published-only catalog |
| GET | `/api/demo-import/v1/packages/{packageId}` | Bound-license published package metadata |
| POST | `/api/demo-import/v1/authorize` | Short-lived (10-minute) one-time download ticket |
| GET | `/api/demo-import/v1/packages/{packageId}/download?token={ticket}` | Stream ZIP or 303 to publisher-generated temporary R2 URL |

**Authentication:** Bearer activation token in Authorization header. `domain` and `installation_id` must match the active installation and the publisher's license. No activation token is placed into a catalog URL, rendered HTML, or application logs. The publisher implementation currently uses activation validity; it has not been independently verified to enforce regular/extended commercial license eligibility against tester licenses. Enforce that policy at the publisher before commercialization.

**Catalog:** Draft, revoked, disabled or unpublished ZIPs are absent. Package #2 (`DCP-20261024-03A1BCBA`, Barama Live Demo v0.9.24) was uploaded and signed as Draft on October 24, 2026, and MUST remain absent from customer catalog until explicitly published. The new read-only UI tools can test public HTTPS/signing identity and then query the licensed published catalog.

## Independent publisher signing identity receipt — 2026-10-24

aaPanel checked the publisher-side `/www/wwwroot/tonsamith.com/storage/private/demo-content/keys/public.key` (public key ONLY), decoded its Ed25519 bytes and compared the full SHA-256 digest with the live `GET /api/demo-import/v1/public-key` response:

- Publisher API: HTTP 200, algorithm `Ed25519`
- Key ID: `be53fa7e3e531da5a7a21e9f`
- Independently matched full fingerprint:
  `be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287`
- Result: `INDEPENDENT_KEY_MATCH=PASS`, publisher Draft unchanged, private signing key not accessed, database writes NO.

`DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256` is now the approved exact fingerprint. The controller uses `pinnedSigningIdentity()` for both connection and licensed catalog, rejecting any unexpected key substitution/rotation. No dynamic trust-on-first-use is permitted. Commercial customer download/import remains blocked until separate acceptance.

## Client components (draft PR #130)

`DemoPublisherApiV1.php`
- Strict HTTPS to `tonsamith.com`, no redirects for JSON APIs
- Real public key self-consistency: `key_id` = first 24 hexadecimal characters of SHA256(public Ed25519 key bytes)
- GET licensed published catalog (token in Authorization header only)
- POST authorization for one exact published package, strict package ID and returned one-time ticket/download path
- Ed25519 verification on the **raw 32-byte binary SHA256 digest** of the ZIP, not signed manifest JSON
- Public key from a live API response is **observed**, not trusted until its full SHA256 fingerprint has been independently pinned.

`DemoPublisherDownloadV1.php`
- Takes an authorized ticket and publisher package metadata, including publisher-signed ZIP SHA256, base64 signature, and signer ID
- Requires explicit, independently approved SHA256(public key) fingerprint; rejects unknown or changed keys
- Streams up to 64 MB directly into private staging
- Permits at most one redirect to a narrowly allowlisted Cloudflare R2 S3 endpoint; does not forward bearer tokens on redirected requests; all requests require verified HTTPS certificates
- Verifies exact file SHA256 and Ed25519 detached signature before making the ZIP available as a staged file
- Does **not** execute SQL, replace frontend assets, install code, alter the database, publish a package or bypass the existing change-passcode middleware
- Fail-closed if the publisher uses a different R2 delivery hostname or the package is too large. Such a hostname must be reviewed and explicitly approved, not followed blindly.

`DemoImporterController.php` and view:
- New authenticated/admin-only read-only pages `/dashboard/system/demo-importer/connection` and `/dashboard/system/demo-importer/catalog`.
- Publisher signing key identity panel and published-only catalog panel.
- Real download is still server-side release-gated. Both Auto Import and DB installation stay blocked until actual package staging/backup/restore acceptance.

## Critical limitation — package data schema

TonSamith.com signs the **ZIP file's digest as publisher metadata**. The ZIP has root-level `manifest.json`, `content.json`, optional `review.json`, and 52 public media files under `assets/<sha256>.jpg`, not embedded `envelope.json`.

The earlier `DemoImportPackageV1` class on PR #130 uses an experimental embedded envelope and is **not compatible** with a publisher-signed Barama ZIP. It must be replaced or adapted before anyone enables manual/automatic installation. The dataset includes 5,056 locations, 21 companies, 12 projects, 20 properties, 20 listings, 53 image database rows, 73 media attachments and 52 unique image ZIP files. The site also references an installed homepage hero asset via `{{APP_URL}}`. Never describe the full customer import as production-ready until that exact source dataset and theme/file asset references pass isolated staging.

## Required remaining checks before commercial publication

1. Independently verify the Demo Content signing public-key fingerprint (publisher admin/key ceremony). Never trust-on-first-use.
2. Confirm the license API distinguishes actual commercial CodeCanyon licenses from tester licenses for entitlement.
3. Run the exact 33.10 MB publisher ZIP through an isolated staging importer with local/R2 media, homepage menu/footer, images, all published records and foreign keys.
4. Verify backup/rollback, idempotency, failed downloads, key rotation and repeat-import blocking on staging.
5. Produce an additive aaPanel patch for Production v0.9.24 that does not overwrite Auto Updater or the ring; verify through actual authenticated browser use.
6. Only after these gates, explicitly approve publishing package #2 and enabling customer Auto Import.

## Test evidence

- `tests/demo_publisher_api_v101.php`: published-only catalog, identity/key ID validation, safe auth, one-time download ticket, signature, draft-empty catalog, malformed input.
- `tests/demo_publisher_download_v101.php`: authenticated ZIP transfer, exact SHA and pinned signature verification, safe R2 redirect, private-network redirect denial, cleanup on tampering.
- `.github/workflows/demo-importer.yml`: matrix PHP 8.2 and PHP 8.4.

No production data installation or publisher status change has been performed by this PR.
