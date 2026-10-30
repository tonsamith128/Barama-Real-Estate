# Barama Laravel 13 real-estate domain migration

**Status:** Isolated **new-schema implementation**, not a data migration of existing v1 customers. The running v1 product is not modified.

## Domain boundaries

| Concept | Model | Schema table | Responsibilities |
|---|---|---|---|
| Company / real estate operator | `App\Models\Company` | `companies` | Operator identity, approval and public publication |
| Project / development | `App\Models\Project` | `projects` | A collection of company-owned real estate |
| Property / physical asset | `App\Models\Property` | `properties` | Asset identity, location text, specifications and approval |
| Listing / sale or rental offer | `App\Models\Listing` | `listings` | Price, transaction type, publication, expiry |
| Company membership | `App\Models\CompanyMembership` | `company_user` | Active user–company scope and role data |

The public marketplace deliberately separates Property and Listing. Multiple sale/rental offers may reference the same physical asset. A Project may have multiple Properties, and a Property has exactly one owning Company.

## Public visibility contract

- Companies: approved, published at or before the current time, and not soft-deleted.
- Projects: published, published at or before the current time, with a public Company.
- Properties: status approved and not soft-deleted.
- Listings: published, published at or before the current time, not expired and not soft-deleted, with an approved Property belonging to a public Company.
- Hidden resources are **not included in directory pages and return 404 on direct URLs**.
- Controllers do not contain SQL; Eloquent scopes and small query services enforce visibility in one place.
- User filters and sort keys are validated via `BrowsePropertiesRequest` and the query builder (no interpolated raw SQL).
- Public details do not expose contact information or private media until authorized media/contact modules are migrated.

## Current endpoints

- `GET /properties` and `GET /properties/{slug}`
- `GET /projects` and `GET /projects/{slug}`
- `GET /company` and `GET /company/{slug}`
- `GET /developer` — authenticated and verified, **read-only** companies for active members

## Company isolation

The `AccessibleCompanies` service authorizes companies only through active `company_user` memberships. Even a public Company does not automatically grant its members access to edit data; the next milestone requires roles, explicit policies, owner/manager rules and transaction-safe mutations.

## Critical legacy compatibility work still required

The existing Pure PHP application has many more schema columns and relationships than this isolated Laravel slice: locations, media, payment/billing, permission matrices, inquiry/lead/viewing history, custom page-builder content, user profile fields, licensing and updater state.

Before importing or pointing Laravel at any v1 database:

1. Inventory exact v1 table DDL, indices, nullable columns, defaults, unique keys and foreign keys.
2. Document the v1-to-v2 field mapping and data transformations for every table and every role.
3. Handle existing password hashes, MFA state, license bindings, company ownership, project ownership and media rights.
4. Rehearse import with sanitized but production-shaped fixtures; retain exact backups and rollback capability.
5. Verify legacy public URLs, listing slugs and canonical SEO behavior; identify redirects.
6. Preserve status transitions and audit history; add authorization to every developer/admin write.
7. Require positive CI, browser QA, cPanel/aaPanel installer/upgrade acceptance and written cutover approval.

These new migrations must **never** run against a live v1 database without the separately approved conversion procedure.

## Scope/testing

Feature tests are authored at `tests/Feature/Marketplace/` and `tests/Feature/Developer/`. They cover hidden listings, expired offers, company/project visibility, filtering, route 404 behavior and tenant membership boundaries. **They are not yet confirmed to have passed Composer/Laravel runtime tests.**

Legacy framework code stays in Main until a fully validated Laravel cutover; final Laravel distribution remains legacy-free.
