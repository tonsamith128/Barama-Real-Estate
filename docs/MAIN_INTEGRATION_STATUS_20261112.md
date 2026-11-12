# Laravel 13 main integration reconciliation — 2026-11-12

## Scope

Only application source is being merged to GitHub Main. No aaPanel/cPanel installation, original PHP production modifications, customer data migration, payment activation, outbound customer notifications, or CodeCanyon release are authorized by this source merge.

## Canonical Laravel branch

The integrated candidate descends from draft PR #197, which stacks PRs #185 through #197 covering company review, customer views, media, property/project workflows, legacy read-only conversion, customer activity, calendar, finance/security integrity and independent release acceptance. These changes are a **development QA codebase**, not a saleable or deployable production release.

## Independent PR reconciliation

- PR #182: unique validated public property and project filters, search services, and feature tests integrated into the latest published-only query layer; retained newer project galleries, revisions, moderation and location governance. Original project index layout retained with added filter form.
- PR #183: unique company filtering, published project/listing counts and portfolio-preview service integrated; retained later approved company logo/cover media access and company moderation. Company index/detail layouts retained with added search and published portfolio sections.
- PR #184: canonical geography model/hierarchy/migration/tests are already included in later candidate, and independent read-only aaPanel source/extra/update-worker audit tools are preserved without activating Pure PHP runtime or updater.
- Do **not** merge old overlapping snapshots directly after this integration, as that would overwrite newer Laravel features and reintroduce obsolete paths. Evaluate old updater/commercial PRs separately before integration; they are NOT equivalent to operational signed update or finance migration.

## Release/no-go

Legacy invoices, payments, credential re-enrollment, signed auto-updater, translations, advanced maps/SEO, full Appearance/Media/Policy capabilities and commercial licensing are NOT complete; no real data or physical host/device acceptance has occurred. The codebase and the existing live website are distinct. Preserve draft branches for review history and do not install until separate owner authorization and QA.

## QA

Run both Laravel quality and isolated MySQL/installer workflows on the final integration commit, review exact build/test counts, then merge the single integrated branch to GitHub Main as a source-only step. Keep production release NO-GO.
