# Barama v1.0.17-A9 — Real Edit Existing Location

Target: `/dashboard/catalog` → three-dot menu → **Edit**.

This is an incremental **production source matched** release based on the accepted A8 Catalog source, A7 routes, and A Users Dashboard Controller. The old GitHub main branch does not yet contain these production patches.

## Functionality
- Adds a real **Edit** action for location records (not amenities) that opens `/dashboard/locations/{id}/edit` in the normal Admin workspace.
- Real authenticated, permission-guarded GET and CSRF-protected POST endpoint. New controller/service and premium responsive edit form.
- Allows editing name, latitude, longitude, and sort order. Keeps canonical ID, UUID, slug, country, type and parent unchanged to prevent damage to linked properties, child locations, and ISO hierarchy registry.
- Uses SQL row locking, a row revision check (detects stale edit pages), validated coordinates, audit logging, and rollback of failed database writes.
- No database migration, no live record modification by installer; A7 Mapbox remains unchanged.

## Source files
New: `CatalogLocationEditController.php`, `CatalogLocationEditService.php`, `location-edit-v117a9.php`, `catalog-location-edit-v117a9.css`.

Modified against accepted production: `DashboardController.php`, `workspace/index.php`, `routes/web.php`.

## Guarded aaPanel kit
`Barama-Catalog-Location-Edit-v1.0.17A9-Complete-Kit.zip`
SHA256: `32d555b2d433850e2039a2bb560b7913f8e60211309bd1bed173d506d1a0c423`

ZIP includes 7 actual source files, an exact-source hash installer, manifest, backup/rollback logic, and deployment README. The ZIP is provided separately in ChatGPT, **not contained in this PR**.

## Local QA
- Seven PHP files linted successfully under PHP 8.4.
- ZIP SHA256 and internal manifest PASS.
- Full simulated read-only check and full installer run PASS (Node not required).
- Repeat-install detection PASS.
- Simulated file restoration and source conflict rejection PASS.
- Coordinate validation 8/8 PASS.
- **Live authenticated browser and real database update acceptance PENDING.**

## Release constraint
Do **not** merge this release record as application source into GitHub main before the historic live source baseline has been reconciled. GitHub main remains behind the manually installed A1–A8 changes. Use the source-verified ZIP and refuse installation if live checks differ.
