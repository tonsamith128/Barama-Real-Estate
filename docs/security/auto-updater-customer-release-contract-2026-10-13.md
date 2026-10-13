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
