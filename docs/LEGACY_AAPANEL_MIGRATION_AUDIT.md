# Legacy Pure PHP to Laravel 13 — aaPanel source inventory and parity contract

**Reference branch:** `archive/barama-v1-main-before-laravel-20261030`
**Development branch:** Laravel `main` (commercial release still blocked)
**Legacy deployed site:** `https://baramarealestate.tonsamith.com/`
**Source verification:** GitHub archived snapshot inspected. Direct aaPanel filesystem **not** verified in this assessment.

## Known v1 runtime components

- `index.php` is the legacy custom PHP application front controller.
- `application/bootstrap/runtime.php` loads its custom autoloader and configuration.
- `application/bootstrap/app.php` instantiates the custom `Barama\Core\Router`.
- `application/routes/web.php` defines public/account/developer/admin routes.
- `application/app/Domain/RealEstate/DomainRules.php` owns status vocabularies and transitions.
- Legacy location types: `country`, `province`, `state`, `city`, `district`,
  `commune`, `neighborhood`. Preserve these levels and their ancestry.
- Legacy project types: `residential`, `commercial`, `mixed_use`,
  `land_development`, `industrial`, `hospitality`, `other`.
- Legacy completion states: `planned`, `pre_launch`, `under_construction`,
  `completed`, `on_hold`. The Laravel foundation must not silently discard
  or relabel unknown historic state values during imports.

## Laravel 13 mapping

| Domain | Laravel implementation | Remaining acceptance |
|---|---|---|
| Users/auth | Laravel auth and verified email | v1 password, MFA, role migration |
| Company | `App\Models\Company`; `publiclyVisible` scope | Company address, contact, approvals, owner identity migration |
| Property | `App\Models\Property` | Geographic relation, full attributes and historical owners |
| Listing | `App\Models\Listing`; `visibleToPublic` | Offer histories, deposit, expiry, price/currency validation |
| Project | `App\Models\Project`; `publiclyVisible` | Project type/stage vocabulary parity, media and units |
| Location | `App\Models\Location` with parent reference | Confirm live schema and FK usage; normalized location filtering |
| Media | Laravel protected media | Stored file remapping, private access, rights and duplicates |
| Inquiries | Validated Laravel public form and threads | Legacy target mapping, deduplication, notifications |
| Company directory | Search service, Form Request and public portfolio | Real browser parity, geographic filtering and company verification |

## Safe aaPanel verification procedure

1. **Do not copy, overwrite, or upgrade** the existing site; first identify its true document root in aaPanel.
2. Run `barama_aapanel_readonly_inventory.sh` on the live root. It prints only file
   presence, sizes, hashes and counts; it neither prints secrets nor connects to MySQL.
3. Compare the archived legacy Git blob of `index.php`
   (`fb871fb0343a0a5dd00cb5bcd845b3c8b51eb430`) with the installed file.
   A mismatch only means the live file may be a later or customized version;
   investigate before selecting an upgrade adapter.
4. Record the root layout and installed lock status; export an independently
   encrypted off-server backup using the owner's existing administrative process.
5. On a **separate disposable environment**, inventory tables, FK relationships,
   published record counts, media, users, licenses, and old canonical URLs.
   Compare with the Laravel schema; do not read or output production passwords.
6. Implement a versioned migration adapter with dry-run, validation and
   rollback—not a fresh-install overwrite. Do not run `migrate:fresh` on live data.

## Current milestone (company directory)

- Validated keyword, company-type and sort filters under `/company`.
- Reusable `PublishedCompanySearch` with query-string pagination and company visibility scope.
- Reusable `PublishedCompanyPortfolio` exposing at most six live projects and six live offers.
- Canonical `Company::listings()` relationship via properties: a listing is a market
  offer, distinct from the physical asset.
- Published-only company cards with project and listing counts.
- Tests cover discoverability, public totals, draft exclusion, profile isolation and bad input.
- Uses existing frontend CSS/Blade conventions. No new migrations or production writes.

## Release gates

- GitHub Quality: source-policy guards, PHP syntax, route tests, installer tests, Vite build.
- Browser QA for company search, profile listings, responsive layouts and pagination.
- GitHub PR review and explicit approval before any `main` merge or deployment.
- Independent live aaPanel source match, backups and data migration acceptance **still pending**.
