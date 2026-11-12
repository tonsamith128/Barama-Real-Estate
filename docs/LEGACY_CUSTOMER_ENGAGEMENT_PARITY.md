# Barama customer-engagement parity — Laravel 13 draft

**Date:** 11 November 2026
**Dependency:** draft PR #190, which in turn stacks on #189.
**Production:** original Pure PHP application unchanged.

## Added reusable account features

1. `CustomerActivityManager` and `CustomerActivityController` provide an
   authenticated, tenant-isolated comparison list (maximum five published
   listings), explicitly requested recent-property memory, individual removal,
   and clear recent history.
2. The existing account sidebar, public property detail and account layouts
   carry the new controls. No automatic browsing surveillance is introduced.
3. Imported saved searches retain original parameters in private
   `legacy_filter_evidence`. Their alert delivery is disabled. Saved searches
   with `legacy_review_required` cannot launch matches until the actual
   customer explicitly accepts the subset of filters implemented in Laravel.
   Accepting supported filters never automatically turns email alerts on.
4. Original viewing-request UUIDs, company links, preferred and alternate
   dates/windows, status, notes and historical timestamps are retained.
   Imported requests use `legacy_review`, **not** live pending/confirmed
   scheduling status. Original private company notes are not disclosed in
   the customer UI.
5. The new `comparison_items` and `recent_listing_views` tables use
   ownership keys and bounded activity fields compatible with the archived
   Pure PHP schema.

## Historical conversion and safety

The isolated stage adapter now covers `saved_searches`, `viewing_requests`,
`comparison_items`, and `recent_listing_views` in addition to previously
mapped users, companies, properties, projects, listings, saved listings,
geographic hierarchies, approved-media copies and associated inquiries.

The source schema must match the real restored copy. Incompatible source data
aborts an import rather than discarding fields or reassigning customer
ownership. Saved searches with historical filters unsupported by the
new frontend are held in review state. Old viewing requests remain
historical evidence, not fresh appointment confirmations.

`support/barama_isolated_clone_reconcile.php` independently checks mapped
UUIDs, original statuses, user/listing relationship pairs, saved-search
evidence, viewing-request company relationships and the private
not-yet-approved status of imported records. Both source and target
connections remain read-only during this audit.

`scripts/acceptance/legacy-php-schema-replica.sh` creates synthetic
historical source rows for searches, viewing requests, comparison
items, recent views, account roles and unmapped leads.
`scripts/acceptance/legacy-clone-stage-synthetic.sh` converts these rows
into a separate disposable Laravel MySQL database and confirms the
privacy/relationship constraints.

The MySQL recovery workflow now upgrades and reverses **nine additive
Laravel migrations**, followed by SQL restoration. The newest migration
blocks destructive rollback when any converted customer activity is stored.

## Known remaining gates

- Real, privately restored production MySQL/media clone dry-run and
  independently signed reconciliation evidence.
- Legacy saved-search location/bedroom filters are not yet implemented
  end-to-end in the public Laravel search engine; customer review is
  required before using transferred searches.
- Some legacy billing, security, roles and permissions, company-only
  inquiries, leads and customer histories still require separate adapters.
- Real aaPanel Nginx/PHP-FPM and cPanel/LiteSpeed tests.
- Physical iPhone/Android/tablet acceptance.
- #182/#183/#184 collision review; independent stacked PR merge/rebase plan.
- Final package, installer, upgrade, rollback and legal review.

**No production deployment, live database migration, customer mail,
real private-media copy or PR merge has occurred.**
