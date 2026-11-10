# Barama Laravel 13 — company submissions, media governance and acceptance

**Scope:** development-only Laravel 13 release candidate, stacked on
`feature/laravel-company-review-notifications-20261110` (PR #185).
**Never deploy over the live Pure PHP site.** Do not migrate real customer
data until independent schema mapping, encrypted backup and rollback approval.

## End-to-end company application

1. An authenticated, email-verified user opens `/developer` and creates
   a private company draft (name, type and description).
2. A company row and active owner membership are created in one database
   transaction, with an audit event. The public marketplace never lists it.
3. An active owner may revise a draft and submit it for review. This advances
   `draft -> pending` with an audit event and in-app notifications to verified
   system administrators. A repeated submission returns conflict.
4. An administrator reviews the pending company at
   `/dashboard/catalog`, recording approval or rejection with a reason.
   Only approval publishes the company; owner alerts are sent transactionally
   and deduplicated (implemented in parent PR #185).
5. A rejected company displays the rejection reason to its active owner only.
   Saving changes returns it to draft; owner may submit again. The rejection
   history is retained.

**Permissions:** Authenticated users cannot modify another company's draft;
active owner membership is required on every write. Approved companies use
separate catalog/team policies. Team managers cannot submit applications
on behalf of the original owner.

## Granular, deny-by-default company-media capabilities

Stored in `user_capability_grants`, with `user_id`, capability,
grantor and timestamps. Only verified system administrators can hold grants.

| Operation | Required capabilities |
|---|---|
| Request correction of approved company logo/cover | `company.manage`, `media.manage`, `company.media.correct` |
| Independently approve/reject pending correction | `company.manage`, `media.manage`, `company.media.review` |
| Grant/revoke capability | Verified root administrator, with audited change |

Super administrator status **alone does not authorize a correction**; all
three capabilities must be explicitly assigned. A requestor can never
approve their own media revision, even if granted reviewer rights.

## Revision safety invariants

- Administrators first upload a file to the existing secure Media Library.
  Only a PNG/JPEG/WebP private asset with a reviewed `owned` or `licensed`
  rights declaration is eligible for a proposed correction; the declaration
  does not independently establish copyright ownership.
- A proposed company `logo` or `cover` replacement is recorded as a
  **pending** `company_media_revisions` row. No active attachment changes.
- Only one pending revision for the same company and media collection may
  exist at a time; company rows are locked during requests.
- Another authorized administrator reviews it. The review refuses to apply
  if the live primary attachment differs from the request's recorded base,
  if the asset rights changed or if the company is no longer published.
- Approval archives the previous attachment as `history_logo` or
  `history_cover` without deleting its physical image or media record.
  It creates a new primary active attachment. Rejection changes no active
  image.
- Only current primary attachments in the active `logo` and `cover`
  collections are allowed through public-company media access. Archived and
  pending assets do not become public merely because the company is approved.
- Every change produces an audit entry and final decision notifies the
  requester. The public company remains approved and published throughout.

## Automated acceptance

- PHP 8.3, Laravel 13 syntax/structure, tests, installer checks and Vite
  via `.github/workflows/quality.yml`.
- `.github/workflows/company-mysql-recovery.yml`: isolated MySQL 8
  pre-feature schema fixture, migration to new notification/capability/revision
  tables, original-data preservation, duplicate-alert uniqueness, SQL dump,
  rollback of three additive migrations, reapply and full SQL restoration.
- Separate package job creates a source-guarded QA ZIP, verifies checksums
  and host-compatible file permissions, checks Apache root-domain rewrites
  and private-source denial on localhost, then runs a five-step installer
  over HTTP against a fresh MySQL 8 database. Installed package features
  are retested on an isolated SQLite test connection.

The legacy source reconciliation was owner-run and read-only: **389/389**
archived PHP files match deployed source; five additional live PHP files
remain preserved. Three updater-related extras have **zero detected literal
references** in the corrected scanner but have not been proved unused.
Neither these workflows nor the Laravel package modify the installed
Pure PHP site.

## Gates still pending for a commercial release

- Independent real aaPanel, cPanel/LiteSpeed and Nginx/PHP-FPM acceptance,
  including actual domain routing, backups and restore of cloned data.
- Historical MySQL schema/data conversion mapping for users, roles,
  country/location hierarchy, properties/listings, license records,
  inquiries, private media and URL redirects.
- Concurrency tests on a real multi-worker hosting environment for
  permissions, media review and company submission.
- Mobile/browser acceptance for owner/admin interfaces and review feedback.
- Approval of media rights, release terms and commercial upgrade policies.

**No live deployment or legacy DB migration is authorized by passing CI.**
