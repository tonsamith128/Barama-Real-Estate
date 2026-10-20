# Barama Auto Updater — First Executable Atomic Transaction (2026-10-20)

**PR:** #108, development only; no installation on a real Barama site.

## What is actually implemented
`OneClickAtomicTransactionV1` performs **real** file replacements on disposable website clones (not a fake no-op). It rejects actual staging, customer, and production paths, and is never a browser or privileged worker entrypoint.

- Lock with `flock(LOCK_EX|LOCK_NB)`, and reject a second attempt if an existing transaction has an incomplete journal.
- Validate a supplied signed-plan-shaped file inventory, SHA-256 baseline and replacement content, version advancement, duplicate/traversal/symlink restrictions, maximum count and bytes.
- Create private file-baseline copies and a write-ahead journal. Write and `fsync` each temporary replacement and atomically `rename` into the destination.
- On simulated process loss after a file replacement, the journal and backups survive.
- Separate `recover` operation checks **every** current file and private backup against the old/new digests before changing any file. Reject third-party modifications, replay, symlinked paths, corrupt backups, and recovery of already committed transactions.
- Real atomic file writes and post-crash restore are exercised on disposable `/tmp/barama-oneclick-transaction-fixture-*` roots; the tests create and remove only disposable test websites.

## Verified evidence
Local `php -l` and `php tools/oneclick-atomic-transaction-smoke.php` PASS for actual file application, post-crash recovery, uninterrupted retry-after-recovery, interrupted second update rejection, traversal and symlink refusal, modified release bytes, malicious third-party edits, and rollback replay. The PHP class and test are byte-identical to their GitHub blobs at the PR head. GitHub runner-based CI is separately pending.

User-facing independent aaPanel ZIP:
`Barama-OneClick-Atomic-Worker-Fixture-v1.0.0.zip`.
This package does **not** install source, create live backups, connect to MySQL, or modify websites.

## Why it is not yet a complete automatic updater
This executor is **file-only and fixture-only**. It cannot be extended to a real website merely by changing a configuration flag:

1. The input must be obtained by verifying the actual Ed25519 publisher-signed release and immutable file bytes **inside** the privileged worker, not by trusting session or plan arrays.
2. Website PHP file permissions/ownership, directory `fsync`, atomicity across multiple files, health checks, and reentrancy must be reviewed and tested under aaPanel PHP-FPM.
3. A coherent root-private **complete** code/site + SQL snapshot, independent SQL restore proof, and coordinated migration handling must exist. MySQL DDL is not always transactional, so file-only rollback after SQL modifications is unsafe.
4. The root/web trust boundary, independent admin/owner approval, fresh license/entitlement verification and replay registry must be enforced on every real install.
5. The current staging baseline is `0.9.22-dev`; earlier snapshots from a `0.9.23` installation are not valid proof for it.
6. GitHub Quality, exact customer ZIP QA, authenticated browser flows, negative crash tests, and a genuinely signed newer test release must all pass before any live Install button is enabled.

The existing authenticated manual `/updater/` remains the shipping-safe upgrade mechanism until these requirements close. Do not advertise unattended updates as complete for CodeCanyon v1.0.0.
