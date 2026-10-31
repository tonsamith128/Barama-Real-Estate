# Barama Laravel 13 — integrated QA acceptance

**Current status:** Development test build, NOT a CodeCanyon/customer production release.

## Current integrated modules

- Laravel 13 clean five-step installer (preflight, empty MySQL, app key, first admin, lock).
- Marketplace companies/projects/properties/listings and strict published-only queries.
- Developer company membership isolation, private property/listing creation, review submission.
- Admin company onboarding (verified owner) and marketplace moderation/publishing.
- Public property inquiries and tenant-private developer/admin inquiry inboxes.
- Original Barama demo content compatibility reader, preview, and **staging-only** Ed25519-verified import.
- 5,056 locations, 21 companies, 12 projects, 20 properties, 20 listings, 53 media assets/73 attachments, 20 draft policy templates and three inactive appearance settings (source snapshot).
- Original demo images delivered by access-controlled route; no web access to private uploads.

## Original demo archive (owner-provided, independent of application ZIP)

- Filename: Barama-Official-Signed-Catalog-Matched-v0.9.26.zip
- SHA256: b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e
- Original format: `manifest.json`, `content.json`, `review.json`, 53 `assets/<sha256>.<ext>` entries.
- The **Ed25519 publisher signature is DETACHED and NOT inside the archive.** ZIP integrity alone is never authorization to import.
- Publisher public key fingerprint pinned independently:
  `be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287`.

## One combined QA procedure

1. Use GitHub Actions on PR #160. All jobs must pass.
2. Download the **development-only** `barama-laravel-integrated-qa` artifact; verify its SHA256. It includes the complete Laravel source, bundled Composer dependencies, compiled frontend assets and independent Installer.
3. Upload to a NEW isolated staging subdomain; set HTTPS and the document root to the archive's `public/` folder. Never overwrite a running customer installation.
4. Install on a dedicated empty MySQL database at `/installer/`. Create the first administrator, verify lock and remove Installer directories.
5. Verify login, Admin Dashboard, marketplace moderation and public browse routes.
6. Register a verified developer account. Admin creates and approves a company with that email as owner. Developer creates a draft listing, submits for review, Admin approves, then listing appears publicly.
7. Send an inquiry from a public property; verify admin inbox and that only an active member of the listing's company can see it in Developer inbox.
8. Open Admin > System > Demo Importer. Preview the **original, unmodified** v0.9.26 demo ZIP. Do NOT import on a live or nonempty database.
9. To test import, use a SECOND clean staging installation with **no manually created catalog records**, then configure explicitly on the server:
   - `APP_ENV=staging`
   - `BARAMA_ENABLE_STAGING_DEMO_IMPORT=true`
   - `BARAMA_PUBLISHER_PUBLIC_KEY_B64` set to the independently validated public key corresponding to the pinned fingerprint.
   - Clear Laravel config cache if already built.
   - Enter the original detached publisher signature from the official published-package metadata (not from the ZIP).
   - The importer verifies the archive digest, the pinned Ed25519 signature, every image hash, and content relationships; it refuses any existing marketplace records, prevents duplicates, and inserts inside a database transaction with private-file cleanup.
10. Verify counts, public images and policy drafts. Imported policies and appearance settings are intentionally inactive and require operator review.

## Gates not yet satisfied

- Authentic aaPanel/cPanel browser installation on user infrastructure.
- Real publisher entitlement integration into Laravel. **Production demo import is disabled.**
- Existing v1 production users, passwords, MFA, roles, transactions and database conversion.
- Full media upload management, Appearance builder, Policy Center publishing, billing, payments/invoices, license validation, updater, backups, demo publisher API, translations, final legal review.
- CodeCanyon exact customer ZIP, source/asset-rights audit, failure/rollback drills and accessibility/security penetration testing.

**Important:** A green GitHub CI result proves only the implemented modules and tested contracts, not full original Barama feature parity. Never run Laravel schema migrations on the existing v1 production database.
