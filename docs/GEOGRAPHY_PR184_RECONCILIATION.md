# PR #184 — Geography foundation reconciliation into the release stack

**Compared:** draft PR #184 `feature/laravel-location-hierarchy-foundation-20261110` versus draft PR #192 `feature/viewing-approval-reschedule-customer-recovery-20261111`.

**Result:** CORE GEOGRAPHY CONTENT RECONCILED, BRANCH MERGE STILL PENDING.

## Exact-match implementation already present in the newer stack

These Git blobs were confirmed identical in both branches:

| File | Identical Git blob |
|---|---|
| `app/Models/Location.php` | `55fdfacbf0129989272a4e14712787920887e192` |
| `app/Services/Geography/LocationHierarchy.php` | `b6fa658f47881137395fe11b5192b9107dc92dc7` |
| `database/migrations/2026_11_10_000001_create_geographic_location_foundation.php` | `486ebc3250b22237efeee0fa01bef9e04f1cfb30` |
| `tests/Feature/Geography/LocationFoundationTest.php` | `68a080c1c30e32abb1cb326e2b965914ae33eaaf` |

## Older models have evolved; never overwrite them

The newer `Company`, `Project` and `Property` models retain the
`location()` relation and geo fields. They also add listing/gallery
relationships, company contact fields, country governance, project
milestones, amenity relations and decimal fractional room support.

Do **not** wholesale merge the older three model implementations over
the modern implementations. Compare any new changes independently.
In particular, restoring integer bedroom/bathroom casts from PR #184
would lose historical fractional-room support.

## PR #184 audit-only artifacts copied without code changes

The following original files were missing from the stacked release
branch and were restored byte-for-byte:

| Path | Expected original Git blob |
|---|---|
| `docs/LEGACY_GEOGRAPHY_MIGRATION.md` | `8a5c15f12ec63a42b83586931534b3fd64fa8f45` |
| `support/barama_aapanel_extras_audit.sh` | `e7d6de4bfcef130b1675d2b26c5e9319f3655053` |
| `support/barama_aapanel_full_source_compare.sh` | `2cedd25c5c003e4e8e25667f51d9bb7ad9a9d2e2` |
| `support/barama_live_update_workers_metadata_audit.sh` | `de245f304ddd86d271ae525ce30264c663e8071a` |

All source audit scripts are **read-only**; their prior metadata results
cannot verify production database or media contents. Do not run staging
or updater actions against the live Pure PHP application.

## Integration gates still required

1. Preserve PR #184 as draft pending maintainer review.
2. Rebase/merge the stacked Laravel branches in dependency order,
   reconciling #182/#183 and subsequent catalog functionality
   without dropping changes; do not merge an old geography tree over new files.
3. Run **all** Laravel tests, isolated legacy-import MySQL checks, backup/
   restore tests and packaged installer tests on the exact integration SHA.
4. Independently check location ancestry, global country governance,
   import UUID mapping and fractional rooms on authorized private data.
5. Check genuine aaPanel/cPanel hosts and physical devices before any release.

**Do not merge PR #184 or any stacked PR into main automatically.**
The comparison established content parity, not full production acceptance.
