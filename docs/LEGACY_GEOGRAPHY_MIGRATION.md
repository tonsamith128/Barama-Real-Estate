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
