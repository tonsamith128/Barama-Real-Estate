# Phase 4E3 — Restore the actual migrated Barama application schema (disposable CI only)

## Scope

This test uses the **actual Barama application migrations** (already applied by GitHub Actions in the exact `foundation-qa` job to test database `barama_test`) as its recovery source, and **only synthetic QA data**. It does not read the production or staging database, connect to aaPanel, export customer records, or install an updater.

Two tools are added:

- `tools/ci-barama-schema-restore.sh` runs genuine MySQL 8 `mysqldump` and `mysql` import in the GitHub MySQL service and controls the random disposable restore schema.
- `tools/ci-barama-schema-restore-compare.php` checks that the schema restored from the dump matches the CI source: tables, columns, indexes, foreign keys, trigger definitions, stored routines, scheduled events, views, and **every data row** across base tables. Rows are compared by deterministic, sorted binary-safe SHA-256 fingerprints, and no data values are printed. A maximum test-data bound prevents accidental large dumps.

The script generates a `0700` private temporary directory and `0600` SQL dump; validates the dump SHA-256 before and after restoring into a randomly generated database matching `barama_ci4e_real_<16 hex chars>`, rejects tampering, and destroys this new test schema and SQL dumps on exit. **It never drops, modifies, or recreates its `barama_test` source.**

## Guard and validation

Both tools have `--guard` checks safe outside CI (no SQL connection); non-CI executions are refused. The restore operation requires exact GitHub repository, exact CI job, specific ephemeral test DB environment, a dedicated opt-in CI flag, and the GitHub MySQL 8 service container ID matching the actual docker-inspected `mysql:8.0` image. Password comes only from CI test environment variables. The logic is not called by any web route or installed into either Barama website.

The GitHub Actions job runs after all migrations and schema QA and before other mutation smoke tests. All app files and data are from the isolated CI checkout.

## Evidence and limits

Passing this test shows that the **migration-built CI application's MySQL schema and synthetic records** can be exported and restored using actual MySQL 8 tooling, with schema metadata and data equality checked.

Passing **does not prove**:

1. Recovery of a representative, real-world populated installation including diverse customer workflow data, media storage and history.
2. Safe import under aaPanel's local MariaDB/MySQL version/configuration.
3. Complete, coherent database + website source restoration following a partial signed update; no crash durability, transactional migrations or power-failure rollback is implemented here.
4. Independent authentication of the publisher's Ed25519 public key, publisher entitlement, or an actual signed release.

These are separate acceptance gates before enabling `Update Now`. Production/customer/staging files and databases remain outside this CI-only phase.

## Local safe guard

From the GitHub repository checkout (no database connection):

```bash
bash -n tools/ci-barama-schema-restore.sh
php -l tools/ci-barama-schema-restore-compare.php
bash tools/ci-barama-schema-restore.sh --guard
php tools/ci-barama-schema-restore-compare.php --guard
```

**Never invoke `--ci` on aaPanel**, inject CI environment variables manually, or copy customer SQL dumps into GitHub. This source-only CI change requires **no aaPanel deployment**.
