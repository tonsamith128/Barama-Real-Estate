# CodeCanyon Package Compliance

The Barama Real Estate release process validates the exact generated customer ZIP rather than only the source repository.

## Dependency declaration

The customer-facing runtime currently has one declared third-party UI dependency:

- Bootstrap 5.3.3, MIT License, loaded from jsDelivr

The release package ships `THIRD_PARTY_NOTICES.md` and `CUSTOMER_PACKAGE.md`.

External service hosts are constrained to the declared Ton Samith and jsDelivr hosts unless a future release explicitly updates this contract.

## Clean production default

Production migrations may seed only system data required for the application to function, such as:

- roles
- permissions
- role-permission mappings
- system settings/preferences required by the product

Production migrations must not seed:

- users or administrator credentials
- companies
- projects
- properties
- listings
- inquiries
- leads
- demo marketplace content

The first administrator is installer-created from customer input.

## Exact-package compliance gate

`tools/package-compliance-smoke.php` validates the generated customer ZIP for:

- required package/license/third-party notice files
- absence of development manifests and dependency directories
- absence of internal QA scripts
- absence of runtime state, logs, dumps, or backups
- absence of known test credentials/fixtures
- absence of demo marketplace data in migrations
- declared external-host allowlist
- canonical first-party CSS/JS policy
- no duplicate historical-version directories/files

This automated gate does not replace the final human CodeCanyon submission review or the final live-server installer acceptance test.
