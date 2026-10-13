# Barama Auto Updater — Scope Freeze (2026-10-13)

## Direction from the owner

**Auto Updater ONLY before the CodeCanyon release.** Treat the earlier CodeCanyon-readiness audit as the established baseline rather than reopening general security, PHP bootstrap, Router, webhook, UI, documentation or feature work. Other improvements are deferred until after publication. Do not run more unrelated diagnostic kits.

## Existing baseline: keep, do not rebuild

Owner's October 12 accepted production progress is newer than the pinned GitHub `main` reference and must NOT be overwritten from it:
- Barama `0.9.22-dev` existing installer, unified licensing, canonical upgrade service and System Center.
- Ton Samith Update Center / Publisher V2 discovery, entitlement separation and Ed25519 verification.
- Auto Updater v1.5.4: signed ZIP inspection, archive/path/storage protection, private staging and a recovery receipt; server evidence reports those checks passed.
- A verified 62-table backup was created, but actual restore rehearsal was NOT proven.
- V2 stream bridge is staged; **remote update installation remains disabled**. A successful customer download/install/recovery transaction is NOT yet proven.

## Work remaining before enabling customer Auto Updater (no scope expansion)

1. **V2 entitlement-bound artifact delivery**: use an approved Publisher V2 grant with product + domain + installation binding, version and signed artifact identity. Do not switch to legacy V1 download to bypass V2 grants. Keep signing private keys on author infrastructure.
2. **Verified customer download**: bounded transfer into private staging; verify Ed25519 signature, digest, size and ZIP safety; reject bad/downgrade/wrong-item deliveries. Preserve the existing v1.5.4 trust checks and releases.
3. **Recoverable installation**: actual secure backup of customer files+database, protected config/uploads/license state preserved, lock and maintenance policy, migration recovery, persistent receipt and verified file/DB rollback. Do not enable until controlled disposable-clone rehearsal passes.
4. **One real release test**: authorized test activation + V2 grant + signed release, check → download → verify → install → restore test. Record PASS/FAIL for this flow only, then prepare updater-inclusive customer ZIP. Keep unattended automatic install disabled unless specifically proven and approved.

## Non-goals / boundaries

No new or expanded general CodeCanyon audit. No Router/webhook/PHP bootstrap tangent. No redesign. No unrelated customer features. No duplicate installer/updater or versioned source trees. No production changes, publication or unconfined PHP execution as a consequence of this document. Do not take action on the older GitHub main source as if it is the newer aaPanel source without exact source reconciliation of updater-related files only. Preserve previous accepted release readiness and mark only the **new Auto Updater** incomplete until its end-to-end acceptance.

The next implementable unit is V2 entitlement-bound HTTP artifact download using the already-staged V2 stream bridge. Do not start again with discovery, package structure or general static audits.


## 2026-10-13 — v1.5.7 accepted, v1.5.8 recovery rehearsal prepared

Owner aaPanel run of v1.5.7: outer/internal SHA256 and PHP lint PASS; `V157_ASSERTIONS=19`; `BARAMA_AUTO_UPDATER_V157_VERIFIED_FIXTURE_PIPELINE=PASS`; captured V2 downloader/receiver used offline with synthetic Ed25519 release; no production writes; genuine Publisher HTTP unused; real DB restore not tested; auto install disabled.

v1.5.8 package **`Barama-Auto-Updater-V2-Coordinated-Recovery-Rehearsal-v1.5.8.zip`** SHA256 **`3c85a7860f49da1d05d0f1b5678ac1a107032786c806bf42e3e4eac78b67ad72`** (28,644 bytes) is prepared, local and clean-extract tested, *not yet run on aaPanel*. Repeats 19 v1.5.7 integration assertions plus **37** fixture file+SQLite backup/migration/recovery assertions. Uses genuine captured V2 PHP downloader/receiver logic with synthetic offline transport/signature; reuses v1.5.5 file transaction; SQLite DB snapshot uses native Python sqlite3 backup API on a disposable fixture. Failure cases include after-file crash, mid/additive SQLite migration, post-migration/pre-finalization interruption, tampered backup, concurrent DB change, corrupted journal, receipt replay. ZIP/internals, PHP lint, shell syntax, fresh extracted package and explicit `--deploy` rejection passed. **This is not production MySQL/MariaDB backup/restore nor a complete customer upgrade engine**. No real Publisher HTTP, production database, or source modifications. `AUTO_INSTALL=DISABLED`; CodeCanyon general readiness baseline not reopened.

Next updater-only gate after owner aaPanel v1.5.8: disposable MySQL/MariaDB snapshot/restore using real test DB and existing updater migration/catalog, followed by a controlled end-to-end entitlement-authorized V2 signed release. Do not enable live updates prematurely or repeat unrelated security checks.
