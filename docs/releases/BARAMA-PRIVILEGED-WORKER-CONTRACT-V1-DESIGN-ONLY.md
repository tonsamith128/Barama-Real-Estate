# Barama v0.9.24 — privileged worker contract (DESIGN ONLY)

**Status:** REVIEW REQUIRED / NO PRIVILEGED EXECUTOR. 2026-10-20.
**Scope:** `/www/wwwroot/baramastaging.tonsamith.com` only. The verified staging baseline is `0.9.23`. PR #107's deployed handoff is a **review-only** request writer plus independently inspectable **untrusted** inbox. Its existence does not authorize any installation.

## Verified existing interfaces and integration blockers

- `application/app/Services/UpgradeService.php` has `UpgradeService::upgrade(array $input)`. It requires `backup_confirmed=1`, `admin_email`, and `admin_password`; it verifies an active administrator using `password_verify()` and locks `application/storage/framework/upgrade.lock`.
- `UpgradeService::validateLicenseForUpgrade()` contains an explicit `BARAMA_CLI === true` branch that returns early when no `LicenseManagerClient` is injected. A new worker MUST NOT use this path to bypass independent publisher entitlement and license verification. A separate scoped native-authorizer design must be reviewed and tested before worker implementation.
- `application/app/Services/Updates/TrustedReleaseFeed.php` authenticates publisher discovery and verifies the signed envelope. `SignedReleaseInspector.php` verifies a private staged ZIP. `PrivateArtifactReceiverV2.php` receives bytes into a root-private directory and is NOT an installer.
- `VerifiedUpdateBackupV2.php` is explicitly **file-only**; it does not prove a consistent database backup or recovery rehearsal.
- `DashboardUpdateRequestV1.php` tickets carry an ID, site, versions, digest, expiry and `action=review_only`; no passwords or permission for root execution. The independent reviewer validates structure, not authentic operator consent. Even valid tickets can be fabricated by a compromised web user.

## Nonnegotiable trust boundaries

1. The PHP web process can only write untrusted review tickets in a restricted inbox. It cannot sudo, spawn privileged jobs, alter worker executable/config, or install archives.
2. A privileged process MUST NOT interpret a ticket (even if structurally valid) as install consent. Operator approval must be independent of any web-writable data, strictly bound to a single immutable release digest + site + expected current version + expiry + nonce, and recorded in a root-owned audit/replay register. Design of how to validate an administrator's identity without collecting credentials in a web-written ticket is a blocker.
3. Before mutating, worker independently verifies current installed version *in application metadata and DB*, signed Ed25519 publisher envelope under pinned immutable key, publisher entitlement and domain/installation binding, actual archive byte length and SHA-256, archive safe paths, migrations, and expected file baselines. Reject downgrades, key substitutions, symlinks, and mismatched staged archives.
4. Filesystem privilege, worker units, schedule, sockets, and sudo policy must remain uninstalled until a separate owner approval after full testing. Do not call remote publisher endpoints or materialize private token contents in logs during local smoke tests.
5. Exclusive update lock must coordinate with native `upgrade.lock` and journal. Check atomically on the same filesystem; prevent duplicate concurrent requests and time-of-check/time-of-use updates.
6. Before the first mutation, verify independently restorable **full** code + consistent SQL database backups, integrity manifests, protected storage, sufficient capacity, and a tested restoration plan (including migration/transaction effects). `VerifiedUpdateBackupV2` alone is insufficient.
7. Every mutating step must be journaled durably (write temp, fsync file/dir, atomic replace); use explicit states: `REVIEWED`, `APPROVED`, `ARTIFACT_VERIFIED`, `BACKUPS_VERIFIED`, `PREPARED`, `FILES_APPLYING`, `DB_MIGRATING`, `VERIFYING`, `COMPLETED`, `RECOVERY_REVIEW_REQUIRED`.
8. Prior to DB mutation, a file rollback may be permitted ONLY after exact written-file hashes and journal state are verified. After any ambiguous DB migration/commit, do not silently restore only PHP code. Quarantine in `RECOVERY_REVIEW_REQUIRED` until an operator verifies a coherent complete restore.
9. Preserve current installer authentication, license manager constraints, RBAC, CSRF, audit logs, and migration semantics. Do not call native `UpgradeService` with fabricated administrator credentials or bypass its checks to make a successful test.
10. Final success requires HTTP health, v0.9.24 `VERSION`, installation metadata and SQL `installation.version` consistency, zero pending migrations, authorized audit event, backup recovery verification, and a crash-restart rehearsal. None is evidenced merely by PR CI.

## Required tests before install enablement

- Unauthorized/forged/replayed/expired/stale tickets and independent approval mismatch must NOT trigger root work.
- Spoofed publisher entitlement, wrong signature/key, mutated ZIP, malicious ZIP path/symlink/hardlink, incompatible PHP, downgrade, changed source, missing DB backup, and missing capacity must all fail closed.
- Crash injection *before first file*, *after each changed file*, *before DB*, *during migrations*, *after DB commit*, *after metadata write*, and *during health verification*; journal must classify recovery correctly after restart.
- Concurrent worker runs, competing native upgrade, compromised web inbox and changed server permissions must be denied.
- A single staging-only newer signed test release (0.9.24) can be signed **on the owner's iMac** and registered as a publisher draft ONLY after all above gates pass. Never sign using server-side keys; do not publish/merge/deploy as a production installer from draft PR #107.

## Current handoff acceptance

- Staging review code: deployed; version remains 0.9.23.
- Private inbox under `/var/lib/barama-oneclick/staging-inbox`: provisioned; root-side read-only reviewer PASS. Empty inbox (0 accepted / 0 rejected) is expected.
- Real signed newer release: NOT available, candidate is unsigned/unpublished.
- Browser review button: source added but upper header visibility has not yet been confirmed from a screenshot of the top of the page.
- Privileged update worker: **NOT IMPLEMENTED**. No root worker, systemd/cron, sudo policy, installer job, SQL migration or release-signing authorization is granted by these checks.

**Decision:** implement only a dry-run/inert plan & crash-recovery state-machine harness next. Require independent review and explicit authorization before introducing any root-capable executor or live installer.
