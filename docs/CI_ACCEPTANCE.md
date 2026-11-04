# Laravel 13 — current CI and release acceptance

## Automated quality (every push and pull request)

Use the GitHub Actions run for the exact commit being reviewed, not a
historical pull-request number or stale test count.

- PHP syntax for source and installer files.
- Native Laravel structure and grouped frontend/admin styles.
- Laravel-only runtime guard and production UI/obsolete-overlay guard.
- Composer manifest validation and dependency installation.
- Route discovery and complete current Laravel test suite.
- Standalone installer and root-domain routing checks.
- Vite build for the current frontend and administration assets.

A passing workflow applies to its tested SHA only.

## Manually dispatched integrated acceptance

The Barama Manual Release Acceptance workflow packages the complete
development QA archive with scripts/build-qa-package.sh. It exercises
Apache root rewrite/security, a five-step MySQL 8 fresh install from
the extracted archive and application tests on that installed copy.
No obsolete per-module upgrade or installer hotfix overlay ZIP is built.

## Independent release gates still outstanding

Passing CI is not proof of production completeness, existing customer
data conversion, safe upgrades, provider eligibility, language review,
accessibility, performance, rollback or independent aaPanel/cPanel and
real-device testing. Keep production deployment blocked until accepted.
