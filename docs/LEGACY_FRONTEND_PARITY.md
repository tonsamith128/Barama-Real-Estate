# Legacy Barama frontend parity — development roadmap

**Reference:** https://baramarealestate.tonsamith.com/
**Target:** Laravel 13 / Blade / Vite, feature parity without copying legacy framework code
**Status:** Incremental development QA, not a commercial release or live deployment

The legacy site is a behavioral reference, not the canonical data store or authorization boundary.
Do not scrape private account content, import production credentials, or copy licensed media
without verified rights. Preserve approved design conventions and use existing grouped CSS/JS.

## Verified public reference routes

| Legacy surface | Observed behavior | Laravel 13 state / work needed |
|---|---|---|
| Homepage | Keyword and advanced-location discovery, featured approved listings, projects and companies | Existing home foundation; hierarchical geography and visual parity remain |
| /properties | Keyword, geography, offer, property type, bedrooms, price and sorting | Search service exists; this milestone adds free-text location and minimum-bedroom filters; geography taxonomy, geocoding and additional facets remain |
| /properties/{slug} | Photos, price, reference, attributes, project, company and inquiry | Existing public visibility, gallery and inquiries; structured attribute parity and compare remain |
| /projects | Keyword/company, location, type and construction stage | This milestone adds keyword/company, type, stage and sorting; location and advanced project media remain |
| Company directory/detail | Approved publisher profiles and links to inventory | Directory/detail foundations exist; verification evidence, contacts and filtering need review |
| /plans | Plan limits and purchase requests with pending activation | Billing, tax, entitlements and provider callbacks not release-accepted |
| /policies | Searchable published policy center with versions and effective dates | Laravel legal publication exists; content parity, consent evidence and legal review pending |
| /contact | Rate-limited support and partnership inquiry | Public form exists; owner notifications and admin follow-up acceptance pending |

## Milestone: public discovery filters

- Validate all user-supplied filters with Laravel Form Requests.
- Query **only publicly visible** listings and projects using existing model scopes.
- Search property address text without inventing normalized geographic records.
- Support optional minimum bedroom selection (1–5+ UI; accepted 1–20 for URL/API queries).
- Keep location and bedroom selections in private saved searches.
- Support project keyword, type, construction stage and deterministic sorting.
- Keep GET query filters through pagination; do not add tables or change public URL formats.
- Preserve current Blade layout and existing CSS entry points; use existing filter classes.
- Add feature tests for public visibility, filters and invalid requests.

## Remaining prioritized implementation

1. Geographic normalization: country/province/city/district IDs, human-readable names,
   input suggestions, controlled import/mapping and indexed location queries.
   Must include historical address migration and duplicate reconciliation.
2. Advanced listing data: floor area, furniture, parking, map coordinates,
   full metadata, verified gallery/rights, project-unit linkage and compare.
3. Project discovery: optional geographic relation, completion stages reconciliation,
   curated images, project details and approved related listings.
4. Company profile parity: verified owner/contact details, approval evidence,
   linked published inventory and secured inquiry flow.
5. Search SEO: canonical URL, record-specific metadata and schema.org JSON-LD,
   with strict public visibility and no private URLs.
6. Administrative functionality: reusable data tables, review queues,
   team permissions, notification delivery and tested CRUD.
7. Commercial release: localization, signed license entitlement,
   payments/receipts, upgrade adapters, backups/rollback, secure installer ZIP,
   aaPanel/cPanel and real-device acceptance.

## Acceptance and release rules

- Run: `php scripts/verify-structure.php`,
  `php scripts/verify-laravel-only.php`, `composer test`, `npm run build`.
- Repeat browser acceptance on anonymous, authenticated and unpublished records.
- Inspect results for 0, 1, many and expired listings, and non-ASCII location text.
- Verify saved search filters survive the round trip and stay owner-isolated.
- Source changes in this feature branch do **not** deploy to the legacy website.
- Never merge to main or deploy to production without review and explicit release approval.
