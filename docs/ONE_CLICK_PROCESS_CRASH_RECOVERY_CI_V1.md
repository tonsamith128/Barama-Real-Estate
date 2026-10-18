# Phase 4E5 — CI process termination and fresh-process recovery

This rehearsal extends Phase 4E4 with a separate `SIGKILL`ed updater child process and an independently invoked recovery shell. It uses Barama's migration-built synthetic `barama_test` database only as a **read-only source** and creates a random disposable database with the existing Phase 4E3 comparator. A disposable PHP fixture models the code change.

## Acceptance contract

- Fail closed outside the exact GitHub Actions Foundation QA/MySQL 8 scope. `--guard` makes no DB connections.
- Persist a 0600 SHA-256 manifest, SQL dump, PHP baseline and append-only checkpoint journal under a 0700 temporary directory before beginning modifications.
- Modify disposable PHP and disposable database; journal each completed phase and synchronize the journal.
- Force-kill the **worker process**, not the runner, after recording the fault checkpoint.
- Confirm exit code 137, persisted divergent file, and persisted injected database table.
- Start a **separate** `--recover` invocation using the persisted manifest and immutable verified backups; reject tampered inputs before any recovery SQL is issued.
- Rebuild only the allowlisted random target database and restore the fixture. Confirm complete schema+rows against unchanged CI source, absent injected table, original code checksum, terminal journal receipt.
- Delete test files and target schema and fail on cleanup failure.

This proves a *worker process termination and restart boundary*, not power-loss durability, full-site rollback, runtime DDL atomicity, or restart across machines. It intentionally does **not** deploy to aaPanel or install a publisher release. Publisher provenance, signed-release acceptance, concurrent-write handling, and staging rollback remain blockers. `Update Now` remains disabled.

Run in Actions only with `BARAMA_CI_PROCESS_CRASH=ALLOW_DISPOSABLE_SIGKILL_CI_ONLY` and `BARAMA_CI_REAL_SCHEMA_RECOVERY=ALLOW_MIGRATED_CI_DATABASE_COPY_ONLY`. No live host should set these flags or execute `--ci`.
