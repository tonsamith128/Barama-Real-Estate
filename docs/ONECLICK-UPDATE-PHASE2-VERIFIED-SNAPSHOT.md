# Barama One-Click Update — Verified File Backup (Phase 2)

This phase integrates the **exact v1.0.1 aaPanel source** of `VerifiedUpdateBackupV2.php` tested and added on the isolated Barama staging site `baramastaging.tonsamith.com`. The PHP source SHA-256 is:

`04999e1c99381301106455284f4a34bcb04b8722949dfe6e0b30c8c4723243ae`

It is proposed as a code-only feature for the CodeCanyon product and **must not be deployed to production yet**.

## What works

- Takes a verified, read-only file plan from `VerifiedReleaseFilePlanV2` (draft PR #82).
- Runs only in the PHP CLI process, creates private hashed file copies, checks baseline integrity and rejects symlinks, traversal, protected runtime paths, unsafe backup roots and duplicate entries.
- Produces a private SHA-256-verifiable backup receipt with original file hashes, package digest, file count and recovery evidence.
- Tests isolated fixture backup and restoration, plus tampering and refusal paths with `php tools/verified-update-backup-v2-smoke.php`.
- Does not download a release, access SQL, replace customer website files, modify the active installation or enable the update UI.

## Integration and recovery limits

The service is intentionally **file-only**. It does **not** back up MySQL data, file uploads, licensing secrets, application settings or configuration. Its fixture restore is not a tested production rollback. It has no privileged worker or HTTP endpoint.

Before enabling the **Update Now** control:

1. Implement authenticated server-side release acquisition, verifying signature, publisher entitlement, archive digest, and installation binding at the point of download.
2. Add a private, consistent database backup and test restoring both files and MySQL in a disposable copy of a full customer installation. Check available disk space.
3. Add one shared update lock, installation sequencing and recovery journal; enforce compatibility and migration policies. Refuse unsafe or unsupported migrations before applying files.
4. Complete a staging-only full update and failure-injection rehearsal, including interruption, rollback, no-write denials, old-version compatibility and CodeCanyon purchase-code entitlement.
5. Audit the exact customer ZIP for CodeCanyon packaging, ensure publisher secrets and private backups are excluded, and run browser/Apache/Nginx CI.

No one-click installation is authorized by this phase. **Update Now remains disabled.**

## Branch/PR order

- PR #81: Customer-facing simple Auto Updater UI.
- PR #82: Signed release file-plan preflight (stacked on PR #81).
- This branch: exact file-only backup class and fixture QA (stacked on PR #82).

Merge/release only after independent code review and complete CI. Do not change production while a stacked draft PR is still being verified.
