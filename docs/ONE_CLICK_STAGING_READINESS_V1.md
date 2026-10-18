# Barama One-Click Updater — Phase 4A staging readiness

Phase 4A adds a **read-only, CLI-only, fail-closed readiness gate** to prepare for eventual end-to-end staging upgrade rehearsals.

It checks the structure-only staging site's application version and presence/hashes of seven existing updater components. It reports PHP sodium, ZIP and PDO MySQL capability, minimum free disk indication, and the *presence only* of separately staged manifest/ZIP files. Presence **does not constitute publisher signature or entitlement verification**. The gate also lists mandatory reasons why real installation remains blocked: verified signed release and entitlement, full database recovery and real transaction rollback are not implemented.

The gate always returns `ready_to_install=false`, `automatic_update_enabled=false` and zero website/SQL writes. It never connects to MySQL or invokes any previous transaction class. It rejects `baramarealestate.tonsamith.com` outright; production full customer data remains outside the workflow. For testing it also accepts one specifically named disposable `/tmp` fixture path.

## Deployment and verification

The v1.7.0 aaPanel ZIP adds only `StagingUpdateReadinessV1.php` on staging first, then production without executing it on production. It produces private previous-file snapshots and verifies the exact GitHub main source. The separate `preflight` installer action runs this class read-only **on staging only** and prints safety gates. It may correctly report blockers while returning success as a diagnostic check. No live upgrades are authorized.

Local PHPUnit-style smoke: `php tools/staging-update-readiness-smoke.php`.

## What is still required

1. Real publisher-signed release acquisition and entitlement check on the staging installation.
2. Verified private file and complete MySQL backup and safe restore on a disposable full-installation copy, *without production customer records*.
3. Crash-safe transactional migration/rollback, release locking and health checks.
4. A controlled real staging installation and failure-injection test before enabling `Update Now`.
5. Owner-approved rollout safeguards before production can update its real data.

This is **not** completion of the end-to-end staging updater. No code may claim otherwise.
