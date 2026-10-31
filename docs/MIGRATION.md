# Barama Real Estate — Laravel 13 Transition and Customer Data Upgrade

**Canonical runtime:** Laravel 13 / PHP 8.3+.
**Current release:** development QA only; the commercial customer release is blocked.
**Production data:** existing installations have not been converted by this project.

## Current Laravel implementation

The Main source tree has native Laravel authentication, a company/project/
property marketplace, account and company workspaces, administrator approval
workflows, protected media, Policy Center, appearance/homepage management,
purchase-code activation and a clean-install wizard. The hardening work adds
publication-aware sitemap coverage and an enforceable runtime source guard.

## Data migration boundary

Existing installations can contain unique users, password hashes, MFA data,
developer/team roles, companies, projects, listings, inquiries, media,
licenses, legal pages and settings. The fresh installer intentionally rejects
populated databases; it is not an upgrade tool.

Any conversion must:
1. Use verified offline copies/backups, never the live database.
2. Map old and new schemas and preserve account identities and password flows.
3. Run a non-destructive dry run with consistency and count checks.
4. Check URLs, media accessibility and company ownership associations.
5. Exercise actual backup, restore and application rollback on each hosting type.
6. Include dual-mode fresh-install/upgrade compatibility tests.

No conversion is authorized until these acceptance gates are met.

## Development and release governance

- Use scripts/verify-laravel-only.php for source and extracted ZIP checks.
- Keep only the Laravel application runtime and the narrow setup bootstrap.
- Use feature-parity and product completion registers for outstanding modules.
- CI is development evidence, not customer hosting or financial acceptance.
- Do not overwrite an installed site with a fresh setup archive.
- Main changes do not deploy automatically to any customer installation.

See PRODUCT_COMPLETION_REGISTER.md and LARAVEL_SOURCE_POLICY.md.
