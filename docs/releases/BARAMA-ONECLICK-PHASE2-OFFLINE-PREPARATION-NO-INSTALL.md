# Barama One-click Auto Updater — Phase 2: Offline Preparation (DESIGN / INSPECTION ONLY)

**Date:** 2026-10-20  
**Branch:** `feat/oneclick-dashboard-gates-v1-20261020` / PR #108  
**Stage:** source + negative tests; **no live deployment or installation authorization**.

## Current customer workflow

- Fresh One-click Installer acceptance at `baramastaging.tonsamith.com`: five steps visually confirmed by the owner, installed package `0.9.22-dev`.
- `/dashboard/system/auto-updater`: publisher discovery, signed-package inspection, release readiness, read-only six-step One-click workflow proposed in PR #108.
- `/updater/`: separate **manual** guarded package upgrader; it is **not** a secure automatic release downloader or privileged update worker.
- Exact installed version and database state must be reverified independently before any future install. A matching version alone does not prove package source or database consistency.

## Phase 2 inspected evidence

`OneClickOfflinePreparationV1::inspect()` is a CLI-only, hard-coded staging-path **read-only** helper. It requires a bootstrapped site and compares:

1. Installed metadata version, package `VERSION`, and `APP_VERSION` for equality.
2. Signed local `envelope.json` and `release.zip`, invoking `VerifiedReleaseFilePlanV2::inspect()`, which delegates cryptographic verification to `SignedReleaseInspector::inspectPaths()`.
3. Root-private **code** backup receipt reverified by `VerifiedUpdateBackupV2::verify()`; entire receipt target inventory, original baselines, new file digests, installed version, target version, and immutable release archive digest must all match the freshly signed plan.
4. Root-private **SQL** backup receipt reverified by `PrivateDatabaseBackupV2::verify()`; fixtures are refused and the recorded database-name hash is compared with a separately supplied expected hash.
5. The caller-supplied database-name hash does **not** independently authenticate the active database identity. SQL restoration has **not** been proven merely by verifying a dump hash.

The report contains only bounded statuses, SHA-256, target version and hard blockers. **No secrets, SQL contents or private file paths need be returned**.

## Fail-closed results

Regardless of successful byte/receipt checks:

- `installation_enabled=false`
- `root_worker_started=false`
- `restore_rehearsal_verified=false`
- `db_identity_independently_verified=false`

Unconditionally blocked: `LIVE_PUBLISHER_ENTITLEMENT_RECHECK_REQUIRED`, `INDEPENDENT_OPERATOR_APPROVAL_REQUIRED`, `PRIVILEGED_INSTALLER_NOT_IMPLEMENTED`, `DATABASE_RESTORE_REHEARSAL_NOT_PROVEN`, `CODE_AND_DATABASE_CRASH_RECOVERY_NOT_PROVEN`.

Source/build fixtures never authorize a real update. No UI component may treat these read-only results as a privileged request or enable an install button.

## Security and operational caveats

- This class has **no web route** and no executable CLI command in the customer package. Do not call it as the `www` web user or wire it into GET/POST endpoints. Future independent inspector invocation needs a root-reviewed, no-secrets contract.
- Existing signed file plan explicitly excludes migration paths, so DB migration and compatibility policy must be reviewed separately.
- No recovery receipt is proof of isolated SQL *import and restoration*. An external storage inventory, consistent snapshot boundary, restoration test, and file+database crash recovery journal remain required.
- A future worker must use an out-of-band administrator/operator authorization source, independent of web-writable tickets. It must enforce publisher license entitlement and immutable signing-key trust, not a CLI license-validation shortcut.
- No customer release should be called CodeCanyon `1.0.0` until automated CI and live/manual acceptance gates pass.

## Tests and acceptance

`php tools/oneclick-offline-preparation-smoke.php` exercises unsafe site roots, forged receipts, bad hashes, path traversal, unsafe receipt mode, symlink receipt, and no install/worker entrypoints. It does not prove the positive signed-release verification path; that requires a real newer signed release and protected backups in an isolated staging acceptance workflow.

Current PR remains **draft**, with no staging/production deployment and no ZIP signing/publishing. GitHub runner-allocation failures are not CI passes.

## Next gate

1. Confirm GitHub CI actually runs its jobs; resolve any true test failures.
2. Prepare a real, independently signed newer test artifact *only after* isolated migration/recovery tests are finished, using offline publisher signing under owner control.
3. Rehearse complete SQL restore with independent DB identity verification, crash recovery and concurrent-update locking; keep it isolated from the active staging database.
4. Separately review/authorize a privileged installer design. Do not introduce a worker or install route into this PR.
