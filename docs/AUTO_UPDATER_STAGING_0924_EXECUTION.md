# Barama Auto Updater — Staging v0.9.24 Execution Handoff

**Record:** 2026-10-22. **Next acceptance:** one authorized, dashboard-initiated installation of `0.9.23 → 0.9.24` with exact worker result, rollback receipt, database-recovery evidence and healthy HTTP afterward. **This document does not authorize execution.** It is subordinate to [AUTO_UPDATER_MASTER_PLAN.md](AUTO_UPDATER_MASTER_PLAN.md).

## Existing real worker — do not rebuild

- Staging site: `/www/wwwroot/baramastaging.tonsamith.com`, installed `0.9.23` as last verified.
- Systemd: `barama-oneclick-staging.service` at `/etc/systemd/system/barama-oneclick-staging.service`.
- Root worker: `/root/Barama-OneClick-Worker/worker-v1.0.1/worker.py`, companion `verify.php`.
- Independent PHP bridge: `application/bin/oneclick-bridge.php` under Staging; administrator queue `application/storage/app/updates/one-click-queue`.
- Root-only trusted signals: `/etc/barama/staging/publisher-trust/oneclick-worker-readiness.json`, `oneclick-worker-result.json`, and short-lived opt-in marker `oneclick-worker-enabled.json`.
- Existing owner-controlled session script from previous work: `Barama-v0.9.24-Staging-UpdateNow-Session-v1.0.0.sh`. It requires literal `I_APPROVE_BARAMA_0924_STAGING_UPDATE_NOW_SESSION` and is not a web endpoint.
- Pinned historical SHA-256, independently rechecked on site before any worker session: `worker.py` `c730bfb876147de3f986727ca27c289bf788237b496a41ace645346f4a2427f5`; `verify.php` `4fe2b93cfb164049cdbdd4e98766b38f630fa007977655518a40f1183bd6c233`; `oneclick-bridge.php` `603a3ea6a6536026328898d731e2794302731b0e5be3f5f5304d74e2cb3466c0`; unit `07f4d76bc1b688e3d767effd244a406e4cf3c383eb2657784578ce6c28a6e6d9`.

## Exact missing gate; no generic infrastructure re-audit

The owner-verified Staging dashboard still reports the trusted worker **offline** and no new job. A signed v0.9.24 manifest was previously verified, but signed status alone does **not** establish live publisher release **audience/entitlement** or intact database recovery. Prior records about v0.9.24 publication/registration conflict; **the current independent bridge probe must be authoritative**.

First inspect the worker in read-only mode and run the existing non-root bridge probe:

```bash
set -Eeuo pipefail
echo '========== STAGING WORKER READ-ONLY =========='
systemctl show barama-oneclick-staging.service -p ActiveState -p UnitFileState --no-pager
python3 -B /root/Barama-OneClick-Worker/worker-v1.0.1/worker.py --check
echo '========== CURRENT PUBLISHER / LICENSE / MIGRATIONS =========='
runuser -u www -- /www/server/php/85/bin/php /www/wwwroot/baramastaging.tonsamith.com/application/bin/oneclick-bridge.php --probe |
  python3 -c 'import json,sys;x=json.load(sys.stdin);print("BRIDGE_SCHEMA="+str(x.get("schema")));print("FEED_STATUS="+str(x.get("feed_status")));print("ENTITLEMENT="+str(x.get("entitlement")));print("PENDING_MIGRATIONS="+str(x.get("pending_migrations")))'
echo 'MODE=READ_ONLY'
```

No release upload, entitlement grant, service start, enable marker or queued job is performed by these commands. Keep stderr redacted if the probe fails; never print the bridge's complete JSON or activation token.

## Owner-approved execution (separate event, not part of PR)

1. Verify current Staging VERSION `0.9.23`, service disabled/inactive, no pending prior job, exact pinned worker/verifier/bridge/unit hashes and intact private backup/recovery contract.
2. Require `barama-oneclick-bridge-v1`, `feed_status=available`, `entitlement=Eligible`, `pending_migrations=0` for **this exact Staging installation**. Confirm v0.9.24 signed release audience, digest, expiry, compatibility and complete recovery backup availability.
3. Only then use the owner-held session script with its **explicit owner approval phrase**. It places a 30-minute root-owned marker and starts the already approved service, without forging a request or auto-publishing. Its monitor remains attached.
4. A **real authenticated administrator** opens the Staging Auto Updater, refreshes **Check for updates** if required, and clicks **Update Now once**. The UI only sends an untrusted audited intent; independent root worker rechecks every condition.
5. Worker result must be bound to that newly submitted job ID. Verify `completed`, actual VERSION `0.9.24`, HTTPS 200 before and after, complete DB/file recovery receipts and no pending migrations. If failed, blocked or recovery-review-required, **do not retry or force-stop**: preserve the worker and recovery evidence.
6. Shut down the worker and remove only the exact created marker **when its session reports that doing so is safe**. Preserve trusted signals, audited job evidence and backups.

**Production:** no service, release publication or entitlement changes. Production activation #8 is tester with no individual stable-update grant; never copy Staging authorization. **Customer portability:** requires a separately reviewed ordinary-hosting executor, not this root-only Staging service.

## Evidence needed to mark Gate D complete

Owner-run job ID and worker final state; signed release ID/channel/target without keys or tokens; installed VERSION hashes, HTTP result, migration counts, private backup/recovery drill and disabled worker/removed marker after safe completion. An online indicator or a signed-release preview is not acceptance.
