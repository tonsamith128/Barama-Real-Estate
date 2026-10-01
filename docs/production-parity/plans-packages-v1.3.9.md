# Barama Plans & Packages — Production Parity v1.3.9

## Purpose

Track the accepted production baseline for `/dashboard/plans` and provide the review contract for syncing live billing source back into GitHub without regressing unrelated production work.

## Accepted production deployment

Production deployment completed successfully on 2026-10-01.

Backup:

`/root/barama-real-estate-backups/plans-packages-premium-v139-20261001T084838Z`

Rollback:

`/root/barama-real-estate-backups/plans-packages-premium-v139-20261001T084838Z/rollback.sh`

## Accepted functionality

- Basic starter plan: USD 29/month, inactive on creation.
- Pro starter plan: USD 79/month, inactive on creation.
- Premium starter plan: USD 149/month, inactive on creation.
- Starter plan seed is insert-only and idempotent; existing matching slugs are not overwritten.
- Header `Create Plan` button with premium configuration modal.
- Editable preset/custom pricing and entitlements.
- Edit Plan and Duplicate actions.
- Existing Activate / Deactivate / Archive workflows preserved.
- Archived plans are read-only.
- `billing.manage` permission and CSRF protection preserved.
- Security Audit event for plan updates: `admin.billing_plan_updated`.
- Compact professional summary cards.
- Search and status filters.
- `No.` numbering.
- Exactly 10 plans per page with Previous / numbered pages / Next / Go To.

## Accepted regression boundary

The production deployment validated that the following remained intact:

- SEO Center Premium v1.3.8.
- Media Library preview/upload v1.3.7.
- Media Upload canonical layout v1.3.5.
- Security Center Phase 6.
- Public marketplace routes.
- `/robots.txt` and `/sitemap.xml`.
- No database schema migration.
- No Nginx change.
- No service restart.
- No cron change.

## Production QA checklist

Before merging parity changes, verify:

- [ ] `/dashboard/plans` renders the premium v1.3.9 UI.
- [ ] Basic / Pro / Premium appear as configured plans.
- [ ] All three seeded plans remain inactive until explicitly activated.
- [ ] Create Plan opens the configuration modal.
- [ ] Basic / Pro / Premium presets populate the modal correctly.
- [ ] Custom plan creation submits through the existing billing workflow.
- [ ] Edit Plan preserves current activation status and subscriptions.
- [ ] Duplicate creates a new inactive plan with a unique slug.
- [ ] Activate / Deactivate / Archive actions remain protected by `billing.manage` + CSRF.
- [ ] Archived plans cannot be edited.
- [ ] `No.` numbering is sequential across pagination pages.
- [ ] Table page size is exactly 10.
- [ ] Previous / numbered pages / Next / Go To preserve filters.
- [ ] Search and status filters work together.
- [ ] Light/dark layouts remain readable.
- [ ] Desktop and responsive modal layouts remain usable.

## Source files expected from accepted production

Exact live copies should be used for parity rather than recreating them from an older repo snapshot:

- `application/app/Repositories/FinanceRepository.php`
- `application/app/Services/FinanceManagementService.php`
- `application/app/Http/Controllers/AdminManagementController.php`
- `application/routes/web.php`
- `application/resources/views/workspace/finance-plans.php`
- `assets/css/finance-plans-v139.css`
- `assets/js/finance-plans-v139.js`

## Merge policy

Do not merge reconstructed or guessed source over the accepted production baseline. Import the exact live files above, review the diff against `feat/marketplace-page-configuration-control`, run syntax checks, and verify the accepted production contract before merging.
