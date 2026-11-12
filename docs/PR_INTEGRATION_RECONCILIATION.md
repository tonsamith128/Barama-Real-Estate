# Barama stacked PR integration and collision review

**Branch:** feature/isolated-realdata-conversion-rehearsal-20261111
**Date:** November 11, 2026
**Status:** DRAFT; analysis only. No merge, production change, or rebase performed.

## Canonical candidate chain

- #185, based on main: company review, approval, notifications
- #186, based on #185: company self-service submissions and media corrections
- #187, based on #186: approved company media presentation
- #188, based on #187: project and property legacy fields, gallery moderation, revisions
- #189, based on #188: amenities, fractional rooms, offers, project milestones and geography
- #190, based on #189: private clone adapter, ID/UUID mapping and release gates

All remain draft. Review each branch before merging. Historical stand-alone
feature PRs #182 and #183 also remain independent from main.

## Geographic PR #184 overlap

PR #184 is separate from main and changes seven model/service/migration/test
paths overlapping the candidate chain. Its four canonical implementation
files have been compared using exact Git blobs against this PR branch:

| File from #184 | Exact bytes in current candidate |
|---|---|
| app/Models/Location.php | YES |
| app/Services/Geography/LocationHierarchy.php | YES |
| database/migrations/2026_11_10_000001_create_geographic_location_foundation.php | YES |
| tests/Feature/Geography/LocationFoundationTest.php | YES |

The other overlapping files are Company, Project and Property models. They
contain further later features and must be reviewed as a **union**, not
replaced by a historical file. #184 also includes separate archived-source
audit documentation and utilities; preserve those when integrating or close
#184 only after ensuring they remain accessible in the release history.
Do not add a second geographic migration/table merely to resolve a conflict.

## Independent frontend PR #182

GitHub file-list comparison identifies four overlapping paths:

- app/Http/Controllers/Developer/Projects/DeveloperProjectController.php
- app/Http/Controllers/Frontend/Projects/ProjectController.php
- app/Services/Marketplace/PublishedListingSearch.php
- app/Services/Marketplace/PublishedProjectSearch.php

Review its search/filter/sort and saved-search behavior against the later
geographic, gallery, amenity and milestone changes. Do not overwrite the
later controllers/search services with older snapshots; transplant only
validated missing filters and tests.

## Independent company-directory PR #183

Five overlapping paths:

- app/Http/Controllers/Frontend/Companies/CompanyController.php
- app/Models/Company.php
- app/Models/Listing.php
- resources/views/frontend/companies/index.blade.php
- resources/views/frontend/companies/show.blade.php

Its company portfolio/sorting must coexist with current location relations,
approved media guards, independent sale/rental listings and revised company
moderation. Preserve the current frontend layout and public-image policy.

## Required review order and merge acceptance

1. Freeze a reviewed commit for each draft; compare patches and source Git
   blobs rather than relying only on the GitHub green mergeability flag.
2. Reconcile #184 first (or document its supersession and preserve audits).
3. Reconcile #182 and #183 with the integrated branch as reviewed feature
   patches with targeted regression tests.
4. Rebase the #185→#190 chain in accepted dependency order, with no force
   overwrite of original main, no dropped migration and no silent file loss.
5. Run Laravel quality, actual archived PHP schema staging and media tests,
   MySQL 8 backup/rollback/restore, private install security, and real host
   and physical-device acceptance on the same final immutable SHA.
6. Require independent reviewer and owner approval. Only then consider
   merging a non-draft release candidate and separately approving cutover.

**NO-GO now:** customer-data clone and media, many financial/customer tables,
account permissions, physical aaPanel/cPanel hosts, device acceptance and the
live-only PHP updater workers remain unverified in production.

File-list overlap is a **review signal**, not proof of a textual conflict or
that a feature is missing. No PR has been merged by this assessment.
