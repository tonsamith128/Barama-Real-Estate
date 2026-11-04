# Source and interface cleanup — 2026-11-04

## Scope

The application remains Laravel 13. Existing marketplace, moderation,
license, company and customer data modules are retained. Only demonstrably
obsolete distribution paths, misleading interface copy and inert entry-point
JavaScript were retired or corrected.

## Retired partial packages

Eight historical scripts generated module-by-module QA ZIP overlays rather
than a complete consistent Laravel application. These, and the associated
cPanel overlay checker, have been removed. The manually dispatched acceptance
workflow now tests and publishes only the integrated QA archive built by
scripts/build-qa-package.sh. No customer installation is overwritten.

## Production interface

- Removed the staging importer shortcut and speculative migration notice
  from the public homepage and administrator dashboard.
- Marketplace publication status now shows real approved/published listing
  counts; no fictitious health or customer statistics are displayed.
- The staging importer is retained as a verified-signature utility but
  is hidden and inaccessible unless a nonproduction environment, explicit
  opt-in flag and configured publisher key are all present.
- Replaced the old standalone importer HTML shell with the premium shared
  admin layout in enabled staging.
- Removed an obsolete appearance-module limitation card and linked its
  property-search preview action to the real property search.
- Replaced inert area JS with progressive sidebar navigation search and
  accessible mobile menu closing. Native links and details continue to work
  without JavaScript.

## Long-term guard

Run php scripts/verify-ui-hygiene.php to reject the old partial builders,
partial ZIP release publication and specified misleading production copy.
This guard is part of both CI and extracted integrated QA package checks.
Missing real features must remain clearly recorded in technical acceptance
documents, not described as working production functionality.

## Remaining gates

This cleanup is not proof of commercial feature parity. Existing customer
database migration, independent cPanel/aaPanel installation and upgrade,
provider onboarding, localization, physical-device testing, external
notifications and rollback acceptance still require separate evidence.
