# Laravel 13 — Property / Project Legacy Parity Milestone

**Development-only:** stacked on PR #187 → #186 → #185. The currently
installed aaPanel website remains Pure PHP. Do not deploy a Laravel
fresh-installer package over it or run these migrations against live data.

## Reuse, do not duplicate

The current Laravel `CompanyCatalogWriter`, `ProjectManager`,
`CatalogReviewService`, `ListingGalleryManager`, `MediaAsset`,
`MediaAttachment` and `Location` models remain the canonical implementation.
This milestone extends their behavior and uses existing Developer and Admin
Blade page shells; no new CSS bundle, public page redesign, or legacy PHP runtime.

## Schema and feature parity

An **additive** migration extends the already existing marketplace tables:

| Existing Laravel table | Additional legacy attributes |
|---|---|
| `projects` | Latitude, longitude, completed date (location, address and scheduled dates already existed) |
| `properties` | Floor number, total floors, land area/unit, latitude and longitude (parking, furnishing and year built already existed) |
| `listings` | Rental deposit and available-from date |
| `catalog_revisions` (new) | Immutable-style numbered records of before/after field state and gallery order, typed subject, actor, action and timestamps |

Existing primary keys, company ownership, project references, location IDs,
image files, published timestamps and customer-facing URL slugs are preserved.
Some legacy features are intentionally not yet fully mapped, including
property amenities, fractional bedroom/bathroom counts, project milestones,
multi-offer management, and specialized listing entitlements. Preserve and
reconcile them in the production-data migration mapping rather than silently
dropping fields.

## Geography and authorization

- Developer forms reference the *existing* `locations` table.
- Selected locations must be active, with all ancestors active and without
  cycles or conflicting country codes. Maximum traversal depth is bounded.
- Project IDs must belong to the same managing company. Company owner or
  manager membership is checked again inside each write transaction.
- Pending/published projects and properties are not editable through the
  private draft form. Rejected records must be revised to draft first.
- Company-scoped projects and properties have separate, recipient-only
  read-only history routes.

The separate global-geography hierarchy PR #184 is *not part of this branch*.
Its UUID and province/country normalization must be reconciled independently
before merging, rather than creating another competing locations table.

## Gallery editing

- Project and listing galleries share `PrivateGalleryImageInspector`
  (12 MB max, safe PNG/JPEG/WebP signature and 24 MP max), private
  `MediaAsset` storage and `GalleryOrderService`.
- Draft-only upload/preview/detach; property/listing or project must belong
  to the active company manager.
- Draft gallery allows **Make primary**, **Move up**, **Move down** and
  **Remove**, with serialized owner-row locks, scoped attachment IDs and
  normalized primary/positions.
- Original files remain in private storage when a link is removed; the same
  image can be shared safely.
- Image additions/removals and nontrivial reorders have typed history and
  actor-scoped audit events.
- Project images become publicly resolvable only when the project is
  published through existing `PublicMediaAccess` policies.

## Approval-state rules and revisions

| Subject | State transitions |
|---|---|
| Project | draft → pending → published/rejected; rejected → draft after edit |
| Property + listing | draft → pending → approved + published, or rejected; rejected → draft after edit |
| Published listing | published → draft by administrator unpublish with reason |

All state-changing operations are checked after acquiring row locks. Approved,
rejected and unpublished decisions now produce typed `catalog_revisions`
snapshots as well as the existing moderation decision / audit records.
`catalog_revisions` keeps versions per subject, not a shallow UI-only
changelog. No automatic public edit of already approved content is added.

## CI and hosting acceptance

- PHPUnit: legacy field persistence, safe project selection, active location
  ancestry, revision sequence/history access, moderation, tenant isolation,
  gallery uploads, privacy, primary ordering and retain-original behavior.
- Isolated MySQL 8: pre-feature schema with synthetic existing data, additive
  migration, integrity and unique constraints, SQL backup, rollback of **four**
  additive migrations, reapply, restore of new fields/history and pre-existing
  records. Never point the script at a production database.
- Existing CI package checks: customer-like QA ZIP, Apache private-source
  denial and root-domain installer, MySQL fresh installation, extracted-package
  PHP tests and frontend bundle.
- Real aaPanel Nginx/PHP-FPM and cPanel/LiteSpeed hosting, production-data
  conversion, media restoration, browser/device acceptance, and concurrent
  multi-worker audits remain separate blocking release requirements.

**Not a complete legacy data conversion or commercial release.**
