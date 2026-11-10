# Legacy Barama locations — Laravel 13 schema and migration contract

**Source reference:** `archive/barama-v1-main-before-laravel-20261030`
**Legacy schema:** `application/database/migrations/0003_create_real_estate_domain.php`
**Legacy governance:** `application/database/migrations/0028_create_global_location_governance.php`
**Legacy location packs:** `application/database/migrations/0029_create_global_location_hierarchy.php`
**Existing Laravel schema:** `database/migrations/2026_10_30_000004_create_demo_content_tables.php` (already creates `locations` and nullable `location_id` relations)
**Additive Laravel extension:** `database/migrations/2026_11_10_000001_create_geographic_location_foundation.php`

## Source verification

The read-only aaPanel inventory run on 2026-11-10 reported:
- 393 Pure PHP application files, 31 old migrations, 42 controllers
- Installed PHP CLI 8.3.32; `artisan` and `public/index.php` absent as expected for v1
- Live `index.php` Git blob equals archived `fb871fb0343a0a5dd00cb5bcd845b3c8b51eb430`
- Installation lock and private uploads present, without reading any secrets
- **Only the front controller is an exact verified Git snapshot match so far.**
  Other source files require archive hash comparison before any customer-data adapter.

## Compatible geographic model

The old `locations` table has UUID, global slug, parent ID, country code,
name, type, coordinates, status, sort order and timestamps. The existing Laravel
`locations` table already has the hierarchy and links to company/project/property.
This migration **only adds nullable UUID and coordinate fields plus a query index**.
Nullable UUID avoids changing any prior QA/demo records; the later data adapter
must backfill unique UUIDs after reconciliation. No legacy IDs are reused.

- `Location::parent()` and `Location::children()` represent the hierarchy.
- `Location::companies()`, `projects()`, `properties()` connect these assets
  to one optional geographical location.
- `LocationHierarchy::activePath()`, `activeChildren()`,
  `activeDescendantIds()` expose safe read-only traversal.
- Inactive, orphaned, cross-country and cyclic paths are not usable as a public
  location selection. Query depth and result counts are bounded.
- A deleted location sets linked `location_id` to NULL rather than deleting
  commercial inventory.
- No changes to currently approved frontend layouts, public routes, CSS
  entrypoints, authentication or installer.

The legacy `company_members` and modern `company_user` tables, account
identities, location IDs, `location_hierarchy_registry` external codes and
`location_data_packs` are **not** interchangeable. Preserve source IDs and
UUIDs in a separate conversion map in an isolated migration workflow.

## Unmigrated legacy capability

- `location_countries` (ISO2/ISO3, enabled/default country, native name)
- `location_data_packs` and hierarchy registry with source checksum
- Selected-country/global mode and country administration UI
- Name localization and country/province/city/district cascading picker
- Maps/geocoding provider integration and geographic autocomplete
- Legacy property attributes and company contact/geolocation fields
- Country data import and code/UUID conflict reconciliation

These need separate additive migrations with independent MySQL and
historical-data acceptance. Do **not** bulk import a public country dataset
without verifying provenance and licensing.

## Required acceptance sequence

1. Compare the live PHP source hashes with the archived branch; review drift.
2. Back up live database, media, configuration, and installation lock privately.
3. Export **schema-only** SQL or sanitized metadata on an isolated copy.
4. Build a dry-run conversion for `locations` and `location_countries`,
   retaining legacy UUIDs and a deterministic old-ID → new-ID mapping.
5. Validate location ancestry, duplicate slugs, code/type mismatches, UTF-8
   names, location-referenced record counts and orphaned references.
6. Rehearse on an isolated MySQL 8 clone, then restore and verify rollback.
7. Run full Laravel CI, host installer/upgrade tests, browser/device QA and
   explicit release approval before deploying to any production installation.

**Do not run `php artisan migrate` on the existing Pure PHP site or replace
its files with the Laravel clean-install ZIP.**


## Live aaPanel source reconciliation — 2026-11-10

Owner-run `support/barama_aapanel_full_source_compare.sh` compared the installed
Pure PHP source with pinned archive commit
`a0af1e5d4d29f38441e3357ae6001b263f1c79cd`.

| Evidence | Verified result |
|---|---:|
| Archived PHP files in comparison scope | 389 |
| Exact Git blob matches | 389 |
| Different files | 0 |
| Missing files | 0 |
| Symlinks skipped | 0 |
| Extra live PHP files | 5 |

The **archived subset is an exact match**, but the deployed source as a whole
is not identical because of these five additions:

1. `application/app/Services/ApprovedCompanyMediaEditPolicy.php`
2. `application/app/Services/CompanyWorkflowNotificationService.php`
3. `application/app/Services/Updates/ProductionHostWorkerV1.php`
4. `application/app/Services/Updates/PortableUpdateApplyV1.php`
5. `application/resources/views/workspace/barama-updater-completed-receipt-v100.php`

These are **unclassified deployment additions**, not evidence of harmless files.
Names imply media/notification/update responsibilities, but function and caller
relationships remain unverified. **Do not delete, replace or merge their code**
without explicit assessment and a verified rollback path. The source audit did
not read the database, verify runtime calls or compare private media.

Next read-only check: run `support/barama_aapanel_extras_audit.sh` from this
development branch with integrity verification. It prints size, SHA-256 and
Git blob hashes, PHP syntax status, modification timestamps, and filenames of
literal references only (never source text, credentials, SQL rows or secrets).
Reference scanning is not runtime tracing and cannot prove a dynamic include is
unused.

**Source parity gate status:** Archive file matching PASSED; extra-file
classification PENDING; production-data, migration and rollback gates PENDING.


## Extra-files audit — 2026-11-10

An additional owner-run, read-only inspection of the **five** live-only PHP
files reported that all were readable, all passed `php -l`, and none had a
literal reference among the 348 first-party PHP files scanned. The original
SHA-256 fingerprints and Git blob hashes are retained in the audit output.

The exact Git blob identities were then checked against the connected GitHub
repository:

| Live extra file | Git blob result | Code classification |
|---|---|---|
| `ApprovedCompanyMediaEditPolicy.php` | Exact blob available (`7302b6c4...`) | Company-only, approved-status administrator media override requiring combined privileges |
| `CompanyWorkflowNotificationService.php` | Exact blob available (`894b3759...`) | In-app moderation transition alerts: pending to administrators, approved/rejected to active company owners; transition-key deduplication |
| `ProductionHostWorkerV1.php` | Exact blob not found | Unreviewed update worker |
| `PortableUpdateApplyV1.php` | Exact blob not found | Unreviewed update application code |
| `barama-updater-completed-receipt-v100.php` | Exact blob not found | Unreviewed completion receipt template |

Git blob presence means the first two **contents** can be independently
inspected. A blob 404 for the other three does *not* prove that their files are
malicious, unused or unrelated to a prior valid release; their exact bytes
cannot be reviewed from this connected GitHub repository.

Review boundaries:
- The company-media exception must not become a blanket developer edit
  permission or bypass company ownership checks in Laravel.
- Company status notifications should be emitted only from committed,
  authoritative transitions, deduplicated and recipient-scoped.
- Any updater worker, apply operation or receipt that can modify commercial
  installations requires independent signature, authorization, file-scope,
  backup, rollback and idempotency acceptance.
- `EXPLICIT_REFERENCE_FILE_COUNT=0` is only a lexical scan result. Composer
  autoloading, PHP string-built names, cron, shell scripts, settings stored in
  MySQL, and external workers can reference files without an exact literal
  being present in the scanned source.
- All five live files remain **untouched**; none are candidates for removal
  until their runtime roles have been verified.

A new read-only utility, `support/barama_live_update_workers_metadata_audit.sh`,
is available on this development branch. It verifies the three unreviewed
files against their recorded SHA-256 fingerprints and reports only class/function
names, source-reference filenames, and lexical risk-category counts. This is
not a substitute for authenticated code review or execution tracing and prints
no file bodies, SQL data or secrets.
