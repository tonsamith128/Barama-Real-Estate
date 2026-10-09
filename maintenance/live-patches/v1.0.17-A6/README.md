# Barama Catalog v1.0.17-A6 — Real admin quick actions

Target: `/dashboard/catalog`. Tested production baseline: Workspace index from installed v1.0.17-A. Independent Apply-page releases through v1.0.17-A5 did not change this view.

## Executable code changes
- `catalog-workspace.patch` contains the **actual source diff** against the captured Workspace view (old hash `5349584289d2d0cce6d621a8c6464f41e9fdf95cf9098fe4a14ef1d968545c54`).
- `catalog-quick-actions-v117a6.css` and `catalog-quick-actions-v117a6.js` are the exact newly installed frontend code.
- Replaces static "Canonical real-estate platform data" with *functional* **Add Location** and **Add Amenity** buttons that open and focus existing real forms; no new backend routes or fake buttons.
- Removes unsupported location-type entries from the new-location form to match the existing `DomainRules::LOCATION_TYPES`.

## Package
Complete external package: `Barama-Catalog-Quick-Actions-v1.0.17A6-Complete-Kit.zip`

SHA-256 `fed7040c1d2c6111a18bdf16a85b87577e995ed280e239b9faa6edb509326f01`.

The installer uses exact production-source hash checks, no database migration, private backup and rollback. Includes a **separate read-only location hierarchy audit** for type counts, parent types and country-code mismatches, with no individual location or customer information.

QA: PHP lint PASS; Node syntax PASS; ZIP checksum PASS; simulated install/idempotency/rollback/conflict refusal PASS. Authenticated live UI QA PENDING.

## Location hierarchy and Mapbox — intentionally not claimed as deployed

Requested model: Country → Province → District → Commune → Village → Street Address.

Existing Barama has a separate `location_countries` table, `locations` parent links, and a country cascade endpoint, but the source accepts legacy global type aliases and only offers 300 active locations as manual parent choices on the Catalog page despite thousands of records. Adding `village`, canonical parent enforcement, permanent address lookup, and migration of old parent links must follow an **aggregate live hierarchy audit**; this release does not mutate or fix those records.

Mapbox cannot be bulk-exported as an unrestricted worldwide location database. Use **Mapbox Search Box API** on-demand with configured token and an approved permanent Geocoding plan only when storing provider results is permitted. No token or licensing configuration is in this release.

## Reconcile GitHub

Do not merge this branch into source on `main` without first reconciling it with production. GitHub `main` is behind the manually installed patches; the real source diff is stored under `maintenance/live-patches` to prevent silently reverting production features.
