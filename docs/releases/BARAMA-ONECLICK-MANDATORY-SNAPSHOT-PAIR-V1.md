# Barama Auto Updater — Mandatory Private Recovery Snapshots v1 (2026-10-20)

**PR:** #108 · **Status:** source only, no production/staging deployment, no snapshot taken, no install authorization.

## Owner approval and interpretation

The owner authorized development of **mandatory automatic recovery snapshots as part of a future Install Update operation** but explicitly requested **no backups of the current site now**.

This change adds `OneClickRecoverySnapshotPairV1` to the staging-only backend. The class is inert until a separately reviewed **root CLI** caller explicitly invokes `prepareStaging()`. There is no dashboard POST route, PHP web worker execution path, scheduled root unit, SQL restore, or update execution.

## What the coordinator actually does when independently invoked

1. Rejects non-root, non-CLI, non-staging roots, missing app/runtime constants, invalid owner-specified **backup-only** confirmation and an absent/insecure root-owned `0700` private vault.
2. Compares `APP_VERSION`, installed metadata, and `VERSION` file, and strictly binds the supplied SQL connection name, host, port, username **and password** to installed **private** runtime configuration. No credentials are logged or returned.
3. Re-verifies the staged newer Ed25519-signed publisher V2 envelope, actual ZIP digest, archive traversal safety, source version and complete read-only code file plan.
4. Invokes existing `VerifiedUpdateBackupV2::prepare` for **file-plan target files only** in an isolated root-private code folder; rehashes and verifies its receipt. This is **not a complete site snapshot**.
5. Invokes existing `PrivateDatabaseBackupV2::prepare` with the installed local SQL configuration; stores an SQL dump and verifies a private digest receipt. SQL is **not imported** or restored.
6. Invokes independent read-only `OneClickOfflinePreparationV1::inspect` to check that every code receipt path, baseline/target SHA, version and archive hash matches the *fresh* signed plan, and that the SQL receipt binds to the selected installed DB name.
7. Commits a `0600` receipt `pair.json` **only after both backup services and independent comparisons pass**; retains incomplete private folders without claiming success.
8. `verifyStagingPair()` independently rehashes the receipt, signed ZIP, code-target backups and SQL dump. It refuses changed source versions, changed signing artifacts, invalid root paths, bad permissions and tampered receipts.
9. `releaseGate()` returns **`ready_to_install=false` under every circumstance**.

## Nonnegotiable missing recovery requirements

The prepared code snapshot only includes signed-plan targets; user uploads, private runtime configuration, plugins, full website code and external R2/S3 media are **not** covered. A code-target snapshot and a consistent SQL dump are **not necessarily one coordinated site-wide restore point**.

A safe customer-facing Install Update still requires a full restorable site+database snapshot, exact online database identity and schema-migration compatibility checks, isolated SQL import and full restoration rehearsal, exclusive lock for the entire transaction, independent authenticated administrator consent, crash-safe privileged worker, persistence and recovery journal, and post-update application health checks.

The configured staging vault `/root/Barama-OneClick-Recovery` must not be created or used before a separate operator-approved test. The current installation at `0.9.22-dev` stays intact. Older `0.9.23` snapshots cannot establish recovery for the current installation.

**Security posture:** Future customer update attempts MUST fail closed if snapshots or recovery cannot be verified. The existing manual guarded `/updater/` remains the supported upgrade path. No unattended installations are enabled or represented as release-ready.

## Testing

`php tools/oneclick-mandatory-recovery-pair-smoke.php`: checks missing/untrusted pair receipts, forged installation flags, fake restoration claims, malformed SHA-256, production/path traversal targets, attempted root-less invocation, and no shell/installer path. Tests deliberately do not create backups or touch any database.

Full positive **real staging** backup+restore proof, signed test release acceptance, authenticated owner-consent proof, and runner-based CI remain OPEN.
