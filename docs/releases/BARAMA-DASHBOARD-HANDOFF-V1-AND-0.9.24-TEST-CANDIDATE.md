# Barama One-click Updater: dashboard handoff contract and v0.9.24 staging test candidate

## Scope and verified baseline

As of 2026-10-19 the real owner-operated, signed v0.9.23 native upgrade
passed staging installation, independent HTTP 200 post-health, 30 applied /
zero pending migrations and version-file/metadata/MySQL consistency.
The publisher release remains draft and auto-install remains disabled.

The **new source** `DashboardInstallHandoffV1::review()` is the first
nonprivileged review boundary for a future dashboard-to-worker flow. Its
inputs are expressly **untrusted** session/discovery hints; it refuses
production roots, stale/invalid publisher results, inconsistent versions,
unsigned/unmatched staged artifact hints, and fake install flags.

The staging-only authenticated, CSRF-protected `POST /dashboard/system/auto-updater/request-review` now uses fresh publisher discovery and this guard before writing one short-lived, **untrusted** review ticket outside the web root. Its `install_authorized`, `install_executed` and `root_worker_invoked` fields are **always false**. The ticket writer changes no application code or database.

A separately reviewed privileged worker still needs to implement:
1. Independently authenticate the administrator's update consent, validate
   license and fresh publisher entitlement, and verify a newly signed
   Ed25519 release envelope against an immutable trusted public-key catalog.
2. Acquire and verify exact private ZIP bytes without web-user root access.
3. Back up full code and database into a private root-controlled vault;
   verify recovery and lock/journal discipline, prevent downgrade and TOCTOU.
4. Use Barama's existing native `UpgradeService::upgrade()` without bypassing
   administrator validation, publisher license checks, migrations, or installed
   metadata; verify HTTP 200 and all three version records before completion.
5. Keep ambiguous partial DB commits in recovery-review state; never silently
   restore only PHP files after the DB version has changed.

One staging-only review-request web route **is** added; it remains blocked on production and cannot start an installer. No sudo/root invocation, cron/systemd service, privileged queue consumer, signing key, publisher publication, existing staging update, or production site is changed by this PR.

## New isolated smoke and CI

`php tools/dashboard-install-handoff-smoke.php` checks the publisher review, staging-only route, private ticket fixture and denied cases. `python3 -B tools/oneclick-inbox-review-smoke.py` checks root-side read-only handling of stale, forged, oversized, symlinked and unsafe tickets. CI performs these disposable checks without production credentials, network or MySQL.

## Unsigned next-version **test candidate**, reviewed locally

An author-only Mac offline signing kit has been prepared separately, **NOT
committed to the customer repository**:

- Bundle: `Barama-v0.9.24-Staging-Signing-Review-v1.0.0.zip`
- Bundle SHA256: `e29092c53a8d05c713a01c96eab4772c6e60675a37a7171822991691eede12e7`
- Candidate `Barama-Real-Estate-V2-Update-0.9.24-UNSIGNED.zip`
- Candidate ZIP SHA256: `6f34a54448836ac30a4902259f1462d7322bd50b351ffd54437c2893120aa724`
- Candidate files: `VERSION` (`0.9.24`) and the byte-identical already
  staging-tested `PrivateArtifactReceiverV2.php`, SHA256
  `66a8112132aae407fd4094fb64e42bf8d1b34dd6d5994a5c0b6aaf5f0c690097`
- Purpose: **staging-only version-coordination smoke test** after the real
  dashboard worker has been reviewed; not a functional feature release.
- Offline Mac `--check` passed bundle hashes, deterministic ZIP inspection,
  disposable Ed25519 signer/verification fixtures and negative trust tests.
- Candidate is not signed, registered, published or installed. Offline
  publisher private key never leaves the trusted Mac.

The kit's `run-mac.sh --sign` is an independent manual signing step and
does not publish a release. Publisher draft registration, one-click execution
and production publishing each require their own review/authorization.

## Gate

The existing staging v0.9.23 **must not be reinstalled**. Do not merge this
contract as a claim of a finished browser One-click Update. Real end-to-end
acceptance requires a separately signed newer release, a verified root/web
privilege boundary, and a successful single browser-triggered *staging* run
including negative and recovery tests. No deployment of this branch is
authorized by its CI passing.

## Web-to-root staging review inbox (NOT an installer)

The root-owned parent `/var/lib/barama-oneclick` is designed for mode 0711;
its `staging-inbox` child is mode 0700 owned by the PHP-FPM runtime user
(`www`). Both are outside the document root and must be provisioned
separately after owner approval; neither exists merely because the code is
merged. Until then, the staging review button is disabled.

Tickets contain only an opaque request ID, pinned staging path, source and
target versions, signed archive SHA-256, and a two-minute expiry. There are
no passwords, tokens, arbitrary commands, URLs, or ZIP payloads. A compromised
web worker could still forge or replay tickets; **tickets are never authority**.

`tools/oneclick-inbox-review.py` is a root-side **read-only** inspector which
rejects unsafe permissions, symlinks, expiry, invalid versions, and bogus
installation actions. It cannot install or authorize any update. The fully
independent privileged worker with publisher/license revalidation, backups,
locks, native upgrade and rollback/recovery remains a separate release gate.
