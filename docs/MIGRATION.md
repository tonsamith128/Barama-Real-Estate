# Barama Real Estate — Laravel 13 migration record

**GitHub Main:** Laravel foundation (not production-ready).

**Legacy source branch:** archive/barama-v1-main-before-laravel-20261030.

**Installer development:** feature/laravel-installer-readiness-20261030, PR #159.

**Production deployment:** not performed; old databases remain untouched.

## Implemented
- Laravel 13 native authentication, new User schema and tests.
- Company, Project, Property and Listing models and migrations.
- Public read-only marketplace with approved/publication filters.
- Read-only company-scoped Developer workspace.
- Two consolidated grouped CSS sources, separate frontend/admin JS.
- Independent fresh-install five-step wizard authored on installer branch: requirements, database, application, administrator and finish; mandatory private token and one-time lock.

## Work not finished
- Original production database, user password, role and MFA migration.
- Complete profile, saved properties, inquiries, leads, management and admin permissions.
- Media Library, Finance, Appearance, Policy Center, SEO and public UX parity.
- Licensing, signed updater, demo importer, rollback and customer ZIP.
- Actual aaPanel/cPanel fresh-install verification and full deployment acceptance.

## Release gates
1. Installer automated MySQL HTTP acceptance green on the exact development commit.
2. Fresh independent aaPanel and cPanel install tests.
3. Legacy schema mapping and user migration dry runs on backups only.
4. Administrator permissions and remaining feature parity.
5. Commercial packaging, license audit and release rollback proof.

**Never run Laravel schema migrations against the current v1 production database.**

See docs/INSTALLER_ACCEPTANCE.md and docs/REAL_ESTATE_DOMAIN.md.
