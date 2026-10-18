# Barama Phase 4E2 — Full-object synthetic MySQL recovery rehearsal (CI only)

## Purpose and limits

This is an **authentic MySQL 8 mysqldump and mysql import**, covering a **complete, synthetic multi-object fixture schema**. It does **not** restore a full Barama application database. It is not a customer restore utility and cannot be called from a web route. Its host-executable shell entry is `tools/ci-full-object-mysql-recovery.sh`.

Existing CI test `IsolatedMysqlRecoveryV1` remains unchanged. This additional test verifies more realistic MySQL object types and actual vendor export/import semantics without live server or customer data.

## Runtime guard

The `--ci` mode requires GitHub Actions, exact repository `tonsamith128/Barama-Real-Estate`, exact `foundation-qa` job, `APP_ENV=testing`, predetermined CI database environment, a separately enabled one-off scope flag, and an explicit ID for the GitHub MySQL 8 service container. It verifies the container's declared image is `mysql:8.0` and connects **only inside that container** with the ephemeral CI test password. aaPanel has none of those permissions or that service container. There is no website, staging, or production connection.

The `--guard` mode is safe to execute anywhere and does not query a database. For a normal non-CI environment it returns `NON_CI_RUNTIME=REJECTED`, `DATABASE_CONNECTIONS=NO`, `AUTO_INSTALL=DISABLED`.

## Exact rehearsal

1. Create two uniquely random and explicitly named disposable databases within GitHub's MySQL service: one temporary source and one independent expected baseline.
2. Insert invented non-customer property, media, transaction and audit rows, including Khmer/Greek Unicode, decimal values and binary blobs.
3. Create full fixture objects: primary keys, secondary/unique indexes, foreign keys, an insert trigger, a view, a stored procedure, and a disabled scheduled event.
4. Use **real MySQL 8 mysqldump** (`--single-transaction --quick --routines --events --triggers --hex-blob`) to produce a private `0600` SQL backup; verify size and SHA-256.
5. Drop and recreate **only the random source fixture database**, then restore the unchanged SQL dump to the exact same synthetic schema name (important for schema-qualified SQL view/procedure references).
6. Compare tables, columns, indexes, foreign keys, triggers, routines, events, all ordered rows, view query results and stored procedure results against the independently seeded baseline. Validate object counts and disabled event status.
7. Deliberately change a copy of the dump and prove the digest check rejects that copy before any SQL importer is run.
8. Drop both temporary databases and remove test SQL in a trap; fail the job on any cleanup failure.

A protected SQL checksum is only an integrity check, not proof of full recoverability. This test goes further by actually restoring fixture objects and checking their behavior.

## Commands

Safe on aaPanel (only a fail-closed guard and shell syntax):

```bash
bash -n tools/ci-full-object-mysql-recovery.sh
bash tools/ci-full-object-mysql-recovery.sh --guard
```

The `--ci` entry is **not** an aaPanel operation and must only be invoked by the repository workflow with the real GitHub MySQL 8 service ID and its synthetic test credentials.

## Still not validated

- Authentic independently attested publisher Ed25519 key identity, entitlement, or a genuine signed publisher release.
- Genuine **full Barama application schema** recovery (including entire production-like migration histories, views, routines, triggers, events and user data).
- Staging-site isolated full schema restore, filesystem and SQL backup coordination, transactional migrations, power-cut recovery and real code + database rollback.
- Production installation or permission to enable `Update Now`.

All remain separately gated. Do not deploy this CI-only script to staging or production and never bundle it in customer-facing update execution.
