# Barama Real Estate — SIMPLE Portable Auto Updater Scope (v1)

**Date:** 2026-10-22  
**Decision status:** PROPOSED IMPLEMENTATION SCOPE — NOT SHIPPED  
**Priority:** P0, ahead of cosmetic Auto Updater work  
**Goal:** A customer who installed Barama from CodeCanyon can update safely from the existing administrator Auto Updater page on shared hosting, a VPS, or a dedicated server, using their existing Ton Samith License Manager / CodeCanyon-purchase-code activation. No root access, systemd, SSH, local signing key, Node.js, or mandatory cron.

## 0. State of record and why this scope exists

1. **Real Staging success:** Owner confirmed an authenticated administrator click updated the *live Staging server* from v0.9.23 to v0.9.24 through its specially provisioned root worker. Root receipt `job-dd99c0e7660b51104a7f7126a1369d5b`, exact installed file hashes, HTTP 200, 0 pending migrations and safe shutdown passed. The separate pre-update snapshot restored 731 files and 61 InnoDB tables in isolation. **This is NOT customer/shared-hosting acceptance.** Automatic full database rollback for the worker has not been proven.
2. **Actual GitHub main, checked today:** `TrustedReleaseFeed.php`, `SignedReleaseInspector.php`, `Installation.php`, and `LicenseManagerClient.php` provide useful trust/licensing foundations. `OneClickUpdateQueueV1.php` hardcodes the owner's Staging path and web-only security boundary. `PrivateArtifactReceiverV2.php` explicitly requires a CLI and a `/root/` stage. `VerifiedUpdateBackupV2.php` is CLI-only and file-only. `StagingPublisherCandidateV1.php` is intentionally non-installing. A `PortableCustomerUpdateV1.php` file and a customer `portable-update-now` route were **not found** on `main` during the scope audit; the UI references this class conditionally. These are **verified gaps**, not merely UI defects.
3. **Release/publisher:** The existing commercial release registry at `tonsamith.com/dashboard/systems/commercial-updates` already shows products, versions, ZIPs, channels and publication states. Its customer licensing API and V2 signed-release flow must be reconciled into one tested contract. The v0.9.24 two-file, staging-targeted pilot must **not** be mistaken for a broadly authorized customer release.
4. **Codebase parity risk:** `README.md` on main still identifies `0.9.22-dev`, while owner's deployed Staging version is `0.9.24`. Reconcile the intended **full product source and release artifact** against accepted Staging changes before packaging v1.0.0. Never overwrite a healthy deployed site from an unrelated historic PR. PR #124 covers a separate Staging lifecycle-display improvement; this scope does not merge or deploy it.
5. **Actual runtime:** Repository README requires **PHP 8.2+**, PDO MySQL/MariaDB, Apache or Nginx. The publisher's per-release `min_php` is an additional gate and must never lower the application's minimum. Exact PHP extensions (curl, sodium, ZipArchive, PDO MySQL, JSON, etc.) and configured filesystem limits must be checked by the installer/updater before offering One-Click.

## 1. User-facing contract — one simple flow

**Check for updates → Download & verify → Backup → Install → Verify → Complete.**

- **Check for updates:** Show installed version, newest **eligible** signed version, release notes, package bytes, and license/update eligibility. A recognized CodeCanyon purchase code alone must not imply update entitlement; distinguish activation, update license/terms and availability. Do not confuse Envato support-period rules with the right to receive releases.
- **Update Now:** Shown only to an authenticated administrator with CSRF protection when all preliminary checks pass, the site is writable/recoverable and there is a strictly newer correctly targeted signed package. One click starts one unique lock-bound job, never a direct arbitrary ZIP upload or user-supplied URL.
- **Honest status:** Show one actual stage, confirmation time and error/recovery action. Never show synthetic percentages, a success message solely because a request was queued, or `Update Now` again while a job is pending. After refresh, completed status must reflect the durable journal, installed version and release identity, not merely browser session data.
- **Successful outcome:** The website still works, installed version is correct, upgrade migration ledger is consistent, the job is marked complete and a backup/recovery receipt remains available. No routine manual terminal command is required for supported small updates.

## 2. One publisher contract, no second license system

- Continue to use the **existing Ton Samith License Manager** for license-key or Envato/CodeCanyon purchase-code verification. Bind product slug, license, activation token, canonical domain, installation ID, and applicable release channel/entitlement. Keep customer purchase code and activation token private.
- Reuse **Publisher V2 signed manifests and customer-facing verified HTTPS artifact delivery** where supported. Require authorized discovery *and* authorized download with a fresh server-side audience check; verify Ed25519 against a bundled public-key catalog, digest, byte count, allowed version path, target product and archive entries. **Never distribute publisher private signing keys to customers.**
- For a generic commercial release, the publisher must select the latest **eligible release for that exact activation**, not select a global latest that masks earlier authorized versions. A staging-pilot release must be excluded from other installations, regardless of a broader product grant.
- The commercial upload/publish UI's general V1 contract and the signed V2 manifest/delivery contract must have a single clearly documented mapping and explicit acceptance tests. Do **not** create another parallel uploader, license store or release registry just for Barama.
- Publisher publication is a separate audited decision; no auto-publishing releases, changing CodeCanyon entitlements or granting Production access as part of this scope.

## 3. Customer-side architecture: pure PHP, request-driven

A minimal, testable, **customer-installed** service, e.g. `PortableCustomerUpdateV1`, owned by the existing Barama application:

1. **Preflight (no writes):** Validate installation and active admin, granted entitlement, product/domain/installation/channel, monotonic version, signed envelope, runtime extensions, PHP version, maintenance/migration policy, upload_max/etc only as needed, writable directories, disk budget, available DB credentials and backup capability. If any step cannot be done safely, show **Manual update required** with a truthful reason.
2. **Private download (no install):** HTTPS authenticated transfer to a private storage path under the installation's protected `application/storage/app/updates/`; streaming size/digest check; no web-accessible ZIP or credentials; no symlink/path traversal; no production root requirement.
3. **Allowlisted staging:** ZIP entry count/total expanded bytes/file sizes/time limits, no absolute paths, `..`, executable scripts outside approved application paths, symlinks/hardlinks/devices/duplicates or surprise modifications to installer/licensing/config/runtime storage. Verify every allowlisted file before replacement. Keep the updater's own recovery bootstrap available independently of files it will replace.
4. **Recoverability first:** Take a private, verified backup of **each file to be modified** plus relevant database backup/snapshot before any DB-changing update. Check free space and restore/readability. Shared hosts may have no `mysqldump` executable: use a reviewed PDO-based backup strategy for eligible bounded datasets, or **refuse One-Click and offer manual maintenance** if a consistent, recoverable DB backup cannot be completed. Never claim a full MySQL restore merely because a SQL file exists.
5. **Transactional-ish apply:** Durable journal/lock, maintenance mode and staged file copies; perform only permit-listed ordered **additive** migrations, with idempotent and versioned receipts. Atomic filesystem/DB transaction across MySQL and PHP files is impossible; explicitly journal checkpoints, recoverable operations and stop states. Do not modify user uploads, media, license receipts, runtime configuration, private secrets or custom data.
6. **Interrupted hosting request:** Use bounded, authenticated continuation requests (or optional safe cron on capable hosts) so common shared-hosting request timeouts do not make success ambiguous. A page refresh/admin re-entry must inspect the same durable job and continue or enter a safe recovery state; it must never create a second installation automatically. No required background daemon, SSH, PHP CLI, shell `exec`, systemd or root.
7. **Postcheck and result:** Confirm files, PHP syntax when available (not requiring PHP CLI), app/bootstrap and DB state, migration count, actual installed version and HTTP health. Mark completed only **after** these checks. Otherwise execute verified allowed rollback, or mark `recovery_review_required` without pretending success. Preserve backup and redacted event history.
8. **Permissions/security:** Respect open_basedir; protect private storage under both Apache and Nginx (verify HTTP denial); lock races and replay; no client-controlled server paths/URL, no remote PHP eval, least privilege, no leaked activation token/SQL/stack trace, explicit compatibility policy for read-only ownership or symlinked deployments.

**Keep existing Staging's privileged worker as a separately protected internal test harness.** It is *not* the customer shipping architecture. Reuse pure security/verification logic where correct rather than copying its fixed paths, /root vault and systemd assumptions.

## 4. Deliberately narrow MVP

**Initially supported One-Click:** signed, targeted **file-only or proven-safe additive** Barama update with a finite file list and an individually verified backup/restore plan; no user-data deletion. The smallest acceptance release should change one small application file + VERSION, no DB migration, and prove end-to-end restore under fault injection.

**Later expansion only after the first customer portable update passes:** additive schema updates with proven MySQL consistency/recovery; large or slow updates using bounded continuation; other products using the *same verified publisher contract*. Major/destructive database changes, database engine conversions, deleted columns, large-media rewrites or unbounded exports use **Manual maintenance update required** until separately engineered and demonstrated. Never silently downgrade that policy.

**Out of scope:** UI redesign, broad license-policy changes, direct Envato API calls from customer sites, new publisher dashboard, customer-host root deployment, a global “works on every host” guarantee, changing installer/theme or rewriting Barama core, PHP < 8.2, VPS-only alternatives presented as customer One-Click.

## 5. Hosting compatibility acceptance matrix

| Hosting profile | Minimum demonstration | Does not require |
| --- | --- | --- |
| **Shared hosting — mandatory first** | Clean CodeCanyon ZIP through browser installer, PHP 8.2+, MySQL/MariaDB with PDO, HTTPS, ZIP/sodium/curl extensions; writable app code/storage; Apache .htaccess case; open_basedir and short request timeout; `exec` disabled; no cron. Authorized update, failure recovery and cache/OPcache behavior pass. | root, SSH, systemd, shell commands, local signing keys, `mysqldump` |
| **Managed VPS** | Same *portable* PHP path on Nginx/PHP-FPM + MySQL, file owner/write constraints, HTTPS and private-storage access controls. Optional CLI/cron may optimize but must not be needed for MVP. | extra owner-supplied worker |
| **Dedicated server** | Same customer artifact/installer/updater on Apache or Nginx, multiple vhosts and correct installation/domain isolation; no cross-site update authority. | publisher private keys on host |
| **Unsupported host** | Clearly report missing extensions, insufficient filesystem permissions, inadequate backup space/DB privileges, untrusted SSL, or incompatible time/memory limits; fail closed with manual-update option. | unsafe bypasses |

A release's signed `min_php` and the app's own PHP 8.2+ requirement must *both* pass. Do not claim compatibility from a version number alone.

## 6. Implementation packages — keep development small

**P0-A — Verify customer artifact parity (no deployment):** Produce a **full clean CodeCanyon-installable Barama candidate** from the selected coherent commit, inspect for missing files/routes/placeholder classes, compare accepted Staging with main, list actual customer PHP requirements and map publisher V1/V2 endpoints. Root-only/Staging code must not be a buyer requirement. Output: clean install acceptance report + exact source inventory and SHA pins.

**P0-B — Customer portable engine:** Implement only the portable PHP updater and narrow admin route/recoverable journal/backup/verified installer, reusing current V2 signing/entitlement foundations. Do not rebuild central Commercial Updates. Output: source PR + tests; no production deployment.

**P0-C — Shared-hosting golden test:** Fresh disposable shared-host fixture installed from **final candidate ZIP**, CodeCanyon activation through Ton Samith License Manager, signed eligible customer release, download, verify, backup, file install, version change, completed UI. Concurrent click, malformed ZIP, wrong token/domain, expired signature, false entitlement, interrupted request, backup failure and postcheck failure must fail safely. Verify wrong-audience pilot is denied *when the non-target has its own valid entitlement*.

**P0-D — VPS + dedicated golden tests:** Same customer code and signed release without special server-specific paths; verify Apache/Nginx privacy, PHP-FPM/open_basedir, low disk space, read-only code paths, idempotent resume and health.

**P1 — Additive DB migration contract:** Use reversible/idempotent migration design and prove full relevant SQL recovery under injected failures and hard process termination in isolated MySQL before allowing auto DB migrations on customers. Otherwise keep manual only.

**P1 — Publisher cutover/CodeCanyon v1.0.0:** Verify full source tree, ZIP contents and version, customer purchase-code activation, public key catalog, exact release audience, release notes, update packaging and docs. Separate staged approval for Production release; no automatic public rollout.

## 7. Definition of DONE for SIMPLE Auto Updater

All must be true:

- [ ] A clean **customer package** is installable without unpublished files, private Staging root worker, server-owner setup or patched GitHub history.
- [ ] A CodeCanyon purchase code/license key is authenticated using existing License Manager; the publisher reports entitled/not entitled/none/unavailable accurately.
- [ ] Signed V2 release is product/domain/installation/channel/version/audience bound; unauthorized access denied for both manifest and archive.
- [ ] Administrator can use **Check for updates** and **Update Now** on ordinary shared hosting with no CLI/daemon/cron; same implementation works on VPS and dedicated.
- [ ] Verified private backup is created and tested before writes; file-only rollout and injected rollback succeed; unsupported DB changes are safely refused.
- [ ] Failure, timeout, partial file replacement, duplicate click and interrupted request never result in falsely showing `completed` or deleting data.
- [ ] The actual installed version, running code, migration ledger and non-sensitive durable job receipts agree; updater shows correct state after reload.
- [ ] Clean install, upgrade, outage/recovery, Apache, Nginx and host-restriction fixture tests pass using the **exact shipping ZIP**, not a developer checkout.
- [ ] Core app/licensing/publisher trust, user uploads and data remain intact; no secret or root key shipped.
- [ ] Publication, customer eligibility and Production rollout each require their own explicit approval; no automatic release from GitHub merge.

## 8. Stop/go rules

- **Do not merge incomplete prototypes, historic branches or the staging-only worker wholesale into customer release.**
- **Do not advertise CodeCanyon-ready One-Click until the shared-hosting golden test and recoverability tests pass.**
- Keep main and deployed Staging unchanged during this scope phase. Work in one focused PR at a time; **PR #124** is a distinct Staging status-only draft, not customer portability proof.
- No arbitrary duration promises. Report each gate as `PASS`, `BLOCKED` or `NOT_TESTED`; finish the smallest file-only portable update before expanding scope.
- Preserve the owner's real Staging v0.9.24 success and protected recovery artifacts. Do not rerun its update job.

## 9. First code task after scope acceptance

Prepare **P0-A customer artifact parity report** from current `main` and verified Staging source without installing anything. Then implement **P0-B** in a separate, focused feature PR. A correct portability milestone is a complete customer ZIP running a real signed **small file-only update** on a disposable shared host — not more green preflights for the root worker.
