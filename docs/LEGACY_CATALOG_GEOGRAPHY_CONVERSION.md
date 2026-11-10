# Barama legacy catalog and geography reconciliation

**Date:** 2026-11-10
**Source authority:** Pure PHP archived commit
`a0af1e5d4d29f38441e3357ae6001b263f1c79cd`
(`archive/barama-v1-main-before-laravel-20261030`).

**Current development chain:** Laravel 13 PRs #184 and #185–#189.
The live aaPanel site is **Pure PHP**. None of the steps in this file
authorizes replacing the live files or running Laravel migrations against the
legacy production database.

## Completed structure reconciliation (source compatibility, not full data import)

| Legacy source field/table | Laravel 13 target | Current behavior |
|---|---|---|
| `locations` numeric ID, UUID, parent, country, coordinates, status | Original Laravel `locations`, additive geographic migration from PR #184 | Canonical tree, existing IDs retained in Laravel installations; UUID generated for new locations and available for future source mapping |
| `location_countries` | Same table name | Admin-managed ISO codes, availability/default; no automatic external country dataset download |
| `location_data_packs` | Same table name | Source/version/checksum/record-count provenance metadata, currently read-only |
| `location_hierarchy_registry` | Same table name | External-code links to canonical locations; no bulk source overwrite |
| `amenities`, `property_amenities`, `project_amenities` | Same names | Active-only selections, bounded assignments, scoped draft editing, revision snapshots |
| `properties.bedrooms/bathrooms` DECIMAL(5,1) | DECIMAL(5,1) | Preserves 2.5 / 1.5 values; rollback refuses integer truncation |
| `projects` location, completion status, dates, gallery | Existing `projects` and `media_attachments` | Draft editing, moderation, private media, published gallery, revision log |
| `project_milestones` (new post-legacy capability) | New table | Ordered draft-only milestone CRUD and change history |
| `properties` address/floors/land/furnishing/media | Existing `properties` and related `listings` | Existing draft/edit/approval workflows extended without recreating project/property tables |
| Multiple `listings` per property | Existing `listings` | Individual sale/rent offers; independent moderation so one rejection does not unpublish a sibling |
| Legacy source record identity / UUID | Unconverted source ID + UUID mapping required | **Not implemented; do not reuse old numeric primary keys** |
| Legacy media, account credentials and role grants | Laravel accounts, media storage and policies | **Not imported.** Protect privacy; credentials require independent compatibility and security validation |

## Acceptance gates already implemented

1. `app/Services/Geography/LocationHierarchy.php` and
   `CatalogLocationPolicy`: active paths/descendants, country guard,
   cycle/orphan fail-closed; shared PR #184 service, not two independent trees.
2. `database/migrations/2026_11_10_000001` and `000002` through
   `000007`: new schema changes are additive to the Laravel baseline,
   with a guarded rollback for fractional room values.
3. Laravel feature suite exercises country permissions, catalog location
   checks, amenities, milestone transitions, multi-offer publication,
   snapshots and tenant isolation.
4. `scripts/acceptance/company-workflow-mysql-recovery.sh` verifies
   a synthetic pre-feature Laravel schema -> 7 migrations -> private SQL
   snapshot -> rollback -> reapply -> restore on restricted GitHub MySQL 8.
5. `scripts/acceptance/legacy-php-schema-replica.sh` uses **the actual
   archived PHP migration closures** `0001`, `0003`, `0008`, `0028`,
   `0029` in a second isolated MySQL 8 job. It builds synthetic source
   rows with UUID, country hierarchy, a company, a project, a fractional-room
   property, two offers and amenity/registry relations. This is an independent
   old-schema validity check, not a clone of production customer records.

## Not yet complete: real legacy-to-Laravel customer-data conversion

Do not describe the synthetic CI as a successful real migration. These
remaining gates require independently verified copies of customer data:

- Record a private, encrypted export of source MySQL **with no credentials or
  row contents in chat or CI logs**; include media and database version;
  independently test backup restoration.
- Identify source schema drift, unknown live-only worker files and installed
  migrations. Never run archived updater apply functions as a shortcut.
- Build a deterministic source `(table, legacy_id, legacy_uuid)` to target
  `(table, laravel_id)` identity map. Block duplicate UUIDs and mismatched
  parent/child, company/project/property, listing and media references.
- Map accounts and role permissions separately; avoid reusing incompatible
  password hashes or granting accidental super-administrator privileges.
- Preserve published URL slugs, current approved attachments, location names,
  native names, media SHA, gallery order, consent, tax/inquiry histories and
  moderation timestamps. Do not invent history for old data.
- Run a dry-run conversion on disposable hosts containing authorized cloned
  database and private media, assert row-level counts and relationship
  integrity, then back up, upgrade and roll back on isolated aaPanel and
  cPanel/LiteSpeed installations.
- Test browser/device UX, MySQL concurrency, signed installer and upgrade
  receipts before owner approval of any cutover.

**No live deployment, real-data import or historical-ID reassignment has
occurred in this development milestone.**
