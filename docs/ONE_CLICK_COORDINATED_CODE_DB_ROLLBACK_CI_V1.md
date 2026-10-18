# Phase 4E4 — Coordinated code + database rollback (failure-injected CI fixture)

## Scope and authority

This phase runs only in GitHub Actions' **foundation-qa** job, inside a verified `mysql:8.0` service container. It does not install any release, contact the publisher, modify staging or production, or read customer data. It does **not** authorize `Update Now`.

The new CLI-only test is `tools/ci-coordinated-code-db-rollback.sh`. Before running, it validates the exact GitHub repository, CI job, predetermined synthetic database environment, exact disposable MySQL 8 service ID, and dedicated flags `BARAMA_CI_COORDINATED_ROLLBACK` and `BARAMA_CI_REAL_SCHEMA_RECOVERY`. It refuses --ci on aaPanel and provides an isolated no-DB `--guard` mode.

## Actual rehearsal

1. Reuse the actual migration-built **CI-only** Barama database `barama_test`. No customer data and no writes to the source.
2. Use real MySQL 8 mysqldump with triggers, procedures, events, binary-safe values, and a protected `0600` SQL file in a newly created `0700` private temporary directory. Record and verify SHA-256.
3. Generate a private synthetic PHP code file alongside a protected, SHA-pinned copy, not a live website file.
4. Restore the complete CI application schema into a new random disposable database `barama_ci4e_real_<16 hex>`. Use the Phase 4E3 comparator to validate its metadata and all data rows match the original.
5. Atomically replace only the synthetic PHP fixture and then create a new test migration table/row **only in the disposable restored schema**. Assert both have changed.
6. Inject an intentional failure at this exact point; record step transitions in a private test journal.
7. Refuse rollback if either SQL or code backup SHA-256 differs from the original receipt. Drop/re-create **only the random disposable target**, import the pinned original SQL and restore the private code file through a verified atomic replacement.
8. Verify exact original PHP code SHA, complete restored Barama schema metadata, every test row, absence of the injected migration table, and terminal journal state. Prove a modified dump copy is rejected by checksum before importing it.
9. Always delete the disposable database, temporary code fixture, backup, journal, and SQL dump, and fail the job if cleanup fails.

The CI source database `barama_test`, application checkout, website files and production databases are never modified by this script. All test records are invented.

## Evidence limits

This is a **single-process, in-band simulated failure recovery**. It does not prove safe recovery across a process kill, host power outage, disk crash, interrupted DDL, stale file locks, interrupted SQL imports, or concurrent real website writes. Those require separate crash-fault tests and acceptance on a disposable copy of a representative complete application installation.

The original publisher key remains **not independently authenticated**, signed releases remain unverified, and automatic installation stays disabled.

## Safe operator check (no connection)

```bash
bash -n tools/ci-coordinated-code-db-rollback.sh
bash tools/ci-coordinated-code-db-rollback.sh --guard
```

Do not manually run `--ci` on aaPanel, add web routes to this test script, inject CI credentials into a live host, deploy it to Barama staging/production, or treat a green CI run as authorization for a live update.
