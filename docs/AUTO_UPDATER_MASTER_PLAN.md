# Barama Auto Updater — Canonical Delivery Plan and Status

**Status:** REAL STAGING CORE INSTALLATION VERIFIED / AUTOMATED DATABASE ROLLBACK, CUSTOMER PORTABILITY, AND PRODUCTION ROLLOUT OPEN  
**Recorded:** 2026-10-22 (Day 6 review)  
**Owner:** Ton Samith  
**Authoritative branch:** `main`; proposed features are **not shipped** merely because they appear on a draft branch.  
**Scope:** **Auto Updater only**. This is the single current delivery/status document. Earlier updater design, phase and acceptance documents are *historical evidence*, **not** instructions to resurrect their code or claims that a feature is deployed.

> **North star:** A verified administrator can check entitlement and see a genuinely offered, correctly signed, installation-specific release, request **Update Now**, observe honest progress, and finish with the *actual* installed version updated and a verified recovery path. A CodeCanyon customer install must ultimately support this without root/server-owner privileges.

## Accepted real Staging installation — 2026-10-22 (owner-verified, supersedes older pending claims)

**Actual administrator Update Now installation: PASS.** Starting from Staging 0.9.23, the signed barama-0.9.24-staging-pilot request was queued through the real dashboard and processed by the previously pinned root-owned worker. Owner terminal reported:

- WORKER_STATE=completed, STAGING_VERSION=0.9.24, STAGING_HTTP_BEFORE_AFTER=200/200 and STAGING_UPDATE_NOW_SESSION=COMPLETED_AND_WORKER_STOPPED.
- Post-install exact file SHA-256 checks passed: VERSION c9e75dc8129553646c44f2d1d35dcccae884bcab82610369b358b98dee424e02 and PrivateArtifactReceiverV2.php 66a8112132aae407fd4094fb64e42bf8d1b34dd6d5994a5c0b6aaf5f0c690097; PHP syntax passed.
- Trusted root-owned completion receipt matched job-dd99c0e7660b51104a7f7126a1369d5b; the job recovery vault contained 7 files, including one SQL dump candidate; PENDING_MIGRATIONS=0; Staging HTTPS returned 200; worker is inactive/disabled and enable marker removed.
- October 22 private recovery snapshot's 731 website files and 61 InnoDB tables were actually restored and read in a separate isolated environment **before installation**.
- **Remaining risk:** a SQL dump candidate and a successful isolated pre-update snapshot restore do **not** establish the worker's automatic *full database* rollback under crash/partial-commit faults. That remains an independent acceptance gate.
- Only one current active stable-channel grant was observed in the publisher entitlement registry, bound to Staging activation #19. Production's non-target request was denied by entitlement, so direct positive-entitled non-target audience isolation remains inconclusive. No general-customer release approval is implied.
- The premium layout was previously accepted on Staging and Production, but post-install browser state/notification synchronization needs separate review. Do not reinstall v0.9.24 as a UI test.
- GitHub changes on a separate branch are reviewable source only; no GitHub commit modifies the owner's aaPanel sites. Earlier sections below describe historical pre-installation states and must not override this result.

**Next stage:** align the UI's completed-job lifecycle to trusted root signals and the installed version, then prove worker failure/rollback behavior using an isolated database fixture, followed by portable non-root customer execution. Never grant Production the Staging license or implicitly initiate a Production update.

## Protected Staging Auto Updater core — GitHub parity work (2026-10-22)

- **UI accepted on both Staging and Production**. Do not change the accepted v1.0.2 layout further unless a specific regression is reproduced.
- [PR #122](https://github.com/tonsamith128/Barama-Real-Estate/pull/122) is the **current consolidated Staging code-parity effort**, branching from latest `main` after #120 and #121. Historical stacked updater PRs #109/#110/#115 remain review evidence, **not instructions to deploy them**.
- The new branch preserves the exact previously accepted Staging queue `eb9af59200eeeacb2ee1ad4907f3a371c0bfdf72`, root-owned trust-path class `e772b19f7ac0c7578b889474a0b861e33f8797bd`, and source-pinned unprivileged downloader `ab2c471ba73412c197e9d6f5221caf69a99e3342`. It adds the administrator+CSRF intent route, binding to a **fresh** signed Publisher discovery result, session-bound no-cache job status and worker progress display on the accepted v103 view.
- No web page can authorize installation: the queued job contains `authorized_to_install=false`, a short-lived nonce, a durable admin audit, lock and release digest. A separately approved root worker must reverify entitlement, audience, signed ZIP and recovery. **PRODUCTION_ONLY queue and root worker remain disabled**, preserving distinct Production licensing policy.
- **This source integration is NOT the remaining live blocker.** Staging's previously accepted controller/queue already exists on the owner-run server; its independent root worker reports OFFLINE. Do **not** overwrite live Staging full files with the GitHub branch. The remaining executable gate is worker service readiness, current authorized v0.9.24 audience, backup and actual job processing under the existing source-digest pin.
- Next evidence must be restricted to *root worker service name/state*, two allowlisted root-owned readiness/result signals, pending queue count, and installed v0.9.23. No broad theme/SQL/signing re-audits, credential output or automatic job submission. After verified recovery and explicit owner authorization, execute one Staging dashboard Update Now job and validate installed version/recovery.
- This code work alone does not implement the portable shared-hosting customer executor. It remains a separate definition-of-done gate before CodeCanyon feature acceptance.

## Accepted v1.0.2 server-side layout deployment — 2026-10-22 (operator verified)

**Staging and Production deployment receipts: PASS; user screenshots confirm premium server-rendered layout visually WORKING on both sites (2026-10-22). This UI milestone is complete, but the Auto Updater installation workflow remains OPEN.**

- Exact aaPanel package: `Barama-AutoUpdater-Premium-Visible-Fix-aaPanel-v1.0.2.zip`, SHA-256 `e3afa2a8ffd0d690c4cff61f45ddb51eb65dd3aa67f261706dea3cdd40ab2bf1`.
- Owner-reported package: outer ZIP checksum, `unzip -tq`, every internal `SHA256SUMS.txt` file and offline Staging/Production installation+rollback fixture **PASS**. Fixtures explicitly showed `NO_LIVE_WEBSITE_FILES_WRITTEN=YES`.
- Read-only preflights for **both** sites: `LIFECYCLE_FIRST_SERVER_MARKUP=PASS`, `FLOATING_NOTIFICATION_SERVER_MARKUP=PASS`, `NEW_ASSET_FILENAMES_NO_OLD_CACHE=PASS`, `PHP_SYNTAX=PASS`, `UPDATE_AUTHORIZATION_CODE=UNCHANGED`, `READY_FOR_PRESENTATION_ONLY_DEPLOY=YES`.
- **Actual Staging deployment:** `PREMIUM_VISIBLE_UI_INSTALL=PASS`, view SHA-256 `e0adbc9c05f8b3b535fced6564d0a09667a5c11ae50a3b4ac478b42f6cce864c` → `d1dc29f459c9631c9b8f9ec6747cda028ae195839f563aef37ab6efd02d861e4`. Root-private backup: `/root/Barama-AutoUpdater-VisibleUX-Backups/staging/20261022T031341Z-cf7d9fb6`. Owner-reported rollback command from package: `python3 -B deploy.py --site staging --rollback 20261022T031341Z-cf7d9fb6 --confirm ROLLBACK_VISIBLE_PREMIUM_UX_STAGING_ONLY`. Not executed.
- **Actual Production deployment:** `PREMIUM_VISIBLE_UI_INSTALL=PASS`, view SHA-256 `224c140a3842e02f548d6afcd233a88729575a30300bbd19ea7202a509567e13` → `6d2e750bada4272af64fb6d3d75f3a490c808620c69525f7239dd224d0c8c343`. Root-private backup: `/root/Barama-AutoUpdater-VisibleUX-Backups/production/20261022T031442Z-f700509d`. Owner-reported rollback command: `python3 -B deploy.py --site production --rollback 20261022T031442Z-f700509d --confirm ROLLBACK_VISIBLE_PREMIUM_UX_PRODUCTION_ONLY`. Not executed.
- New asset Git blobs on both sites: CSS `8bac3d28430e60d5e633d26382e06281445caf2d`, JS `f3e37d1d9846a3e7e6ca259aae1ecce9dee17399`; installed view links these cache-distinct filenames. GitHub [PR #120](https://github.com/tonsamith128/Barama-Real-Estate/pull/120) already merged the canonical source implementation.
- A separate attempt from `/root` initially invoked the wrong `deploy.py` and failed with `unrecognized arguments: --site production`; this caused **no production view install**, and the corrected command from the extracted package subsequently succeeded.
- Scope and safety: `VERSION_CHANGED=NO; DATABASE_CHANGED=NO; WORKER_STARTED=NO; UPDATE_JOB_SUBMITTED=NO`. No license-entitlement or signing-key change. Both application versions remain as previously recorded; no new release was installed by this UI package.
- **UI accepted:** owner provided screenshots verifying both Staging and Production render the new lifecycle-first layout. Do not reopen UI work unless a new reproducible defect appears. **Next exact engineering acceptance:** a real, independently authorized, recoverable Staging Update Now run; no routine re-audits of accepted CSS/JS.

## Premium UI browser-visibility correction v1.0.2 — 2026-10-22 (installed on both sites; visual confirmation pending)

- Owner screenshots at 10:00 Cambodia time show **both** Staging and Production still displayed the old layout despite successful v1.0.1 CSS/JS disk installation receipts. **The UI visual outcome of v1.0.1 was not accepted.**
- Root cause in verified GitHub and prior Staging view: server-rendered PHP still places versions/readiness **above** Update Lifecycle and keeps stale cache-identifiable v100/v101 stylesheet/script URLs. Dynamic JS-only DOM reordering is not a reliable primary layout contract.
- [PR #120](https://github.com/tonsamith128/Barama-Real-Estate/pull/120) reorders the actual PHP view for GitHub `main`, creates fresh v103 CSS/JS asset filenames, and includes CI PHP syntax and section order checks; it **does not change installer authorization**.
- Owner-operated **`Barama-AutoUpdater-Premium-Visible-Fix-aaPanel-v1.0.2.zip`**, SHA-256 `e3afa2a8ffd0d690c4cff61f45ddb51eb65dd3aa67f261706dea3cdd40ab2bf1`, patches each site's **current** PHP view only by exact source anchors. Staging also requires accepted current view SHA256 `e0adbc9c05f8b3b535fced6564d0a09667a5c11ae50a3b4ac478b42f6cce864c`; both sites require accepted previous CSS/JS Git blobs. New filenames ensure no prior asset URL is reused. The original Staging authenticated Update Now / status contract remains intact.
- Local offline acceptance: PHP syntax and structural checks, simulated Staging-style and Production-style installation and private rollback **PASSED**, with no website files contacted. **Production's actual live PHP view has not been fetched; its source-guarded read-only preflight must pass before any deploy.**
- **Current live state:** v1.0.2 server-side layout correction is **installed on both Staging and Production**, confirmed by owner-run commands and exact payload receipts below. Browser visual acceptance remains pending; do not claim it is seen correctly until screenshots verify the result.
- **Core completion remains OPEN:** the independently authorized, recoverable Staging v0.9.23→v0.9.24 Update Now workflow is not executed by this presentation-only patch.

## Accepted two-site Premium Auto Updater UI deployment — 2026-10-22 (operator verified)

**Milestone: UI-only deployment PASS on Staging and Production. Auto Updater end-to-end installation remains OPEN.**

- Exact root-private aaPanel package: `Barama-AutoUpdater-PremiumUX-BothSites-aaPanel-v1.0.1.zip`; SHA-256 `77bda7470e6afa7e0739c58961e488cce7a07b24faf16afe130244d6b0b67257`. Supersedes halted v1.0.0.
- Owner verified outer ZIP hash, `unzip -tq`, every `SHA256SUMS.txt` entry, and `tests/fixture_smoke.py`. The fixture passed Staging install/rollback, Production install/rollback and changed-JS rejection, and explicitly reported `NO_LIVE_WEBSITE_FILES_WRITTEN` for fixture operations.
- **Independent read-only live preflights:** both sites showed exact CSS and JS baseline matches, candidates `8912f550e37db4a704147cbffaaa83782d200a9d` and `c297cc4a9c8ee50a9a5456a451f00fabf02fe59e`, and `READY_FOR_UI_ONLY_DEPLOY=YES`.
- **Actual Staging owner deployment:** `python3 -B deploy.py --site staging --deploy --confirm INSTALL_PREMIUM_UI_STAGING_ONLY` returned `PREMIUM_UI_DEPLOY=PASS`, private backup `/root/Barama-AutoUpdater-PremiumUX-Backups/staging/20261022T025843Z-7b090821`. Exact printed rollback: `python3 -B deploy.py --site staging --rollback 20261022T025843Z-7b090821 --confirm ROLLBACK_PREMIUM_UI_STAGING_ONLY`.
- **Actual Production owner deployment:** `python3 -B deploy.py --site production --deploy --confirm INSTALL_PREMIUM_UI_PRODUCTION_ONLY` returned `PREMIUM_UI_DEPLOY=PASS`, private backup `/root/Barama-AutoUpdater-PremiumUX-Backups/production/20261022T025852Z-c7862e9b`. Exact printed rollback: `python3 -B deploy.py --site production --rollback 20261022T025852Z-c7862e9b --confirm ROLLBACK_PREMIUM_UI_PRODUCTION_ONLY`.
- **Scope:** existing Auto Updater CSS and JS only; server receipts show `VERSION_CHANGED=NO; SQL_CHANGED=NO; WORKER_STARTED=NO; UPDATE_JOB_SUBMITTED=NO`. Earlier accepted view/controller, license category, entitlement, signing trust and worker execution contract were not modified by this package.
- **Not yet evidenced:** user/browser visual validation of both themes and breakpoints after cache refresh; a new Staging `0.9.23 → 0.9.24` dashboard-origin update; real end-to-end recovery. Do not conflate successful premium UI deployment with completed Auto Updater execution or Production tester entitlement.
- **Next core milestone:** inspect current Staging worker readiness with the available signed `0.9.24` release, confirm Staging-targeted audience and recovery, and run one explicitly approved protected dashboard job. No additional UI rewrites or generic server re-audits.

## Accepted Staging integration — 2026-10-22 (operator verified)

**Status: Staging unified Auto Updater *view-only* integration INSTALLED / PASS; Auto Updater end-to-end completion remains OPEN.**

- Operator verified and installed exact-package `Barama-AutoUpdater-Staging-UnifiedView-Integration-v1.0.0.zip` (SHA-256 `c83e5b5afee063992c3f6dfb605232dda35e14440f2a995bb87848257bc7fd7c`).
- `sha256sum -c SHA256SUMS.txt` passed every bundled file; `php tests/view-render-smoke.php` passed all 10 checks. `python3 -B deploy.py --check` confirmed **all eight** pinned live source identities and PHP syntax.
- Explicit owner-run Staging-only command: `python3 -B deploy.py --deploy --confirm INSTALL_STAGING_UNIFIED_VIEW_ONLY_NO_UPDATE`, result `STAGING_VIEW_INSTALL=PASS`.
- Exact **one changed application file**: `application/resources/views/workspace/auto-updater-unified-v100.php`, accepted SHA-256 `224c140a3842e02f548d6afcd233a88729575a30300bbd19ea7202a509567e13` → installed payload `e0adbc9c05f8b3b535fced6564d0a09667a5c11ae50a3b4ac478b42f6cce864c`.
- Root-private backup: `/root/Barama-AutoUpdater-Staging-View-Backups/20261022T022701Z-8f334a6a`. Rollback from extracted package: `python3 -B deploy.py --rollback 20261022T022701Z-8f334a6a --confirm ROLLBACK_STAGING_VIEW_ONLY` if independently warranted; not executed.
- Staging `VERSION=0.9.23`. `ROOT_WORKER_STARTED=NO`; `UPDATE_JOB_SUBMITTED=NO`; database, Production, and application release version unchanged. **This accepted result does not certify a dashboard-initiated update.**
- The accepted live controller, root-owned trust paths, queue, downloader, routes and status JS were preserved. **Do not overwrite accepted Staging files with the prototype #110 or draft #115 full-file payloads.** PR #115 remains integration/reference work; reconcile git source from accepted live hashes first.
- **Next exact acceptance:** inspect the Staging dashboard's disabled/active state, then independently verify that a *newer, Staging-audience-bound, currently signed release* is legitimately published. Only afterward execute a separately authorized, recoverable dashboard-submitted job under the protected worker; capture exact-job result, version change, and recovery receipts. Do not run Production or change tester grants automatically.

## Premium UI rollout correction — exact one-newline live JS variant (2026-10-22)

- **Earlier aaPanel ZIP v1.0.0 was valid but failed read-only preflight** (`STOP=ACCEPTED_JS_GIT_BLOB_CHANGED`); no application deployment occurred. Do not rerun or weaken that original package's checks.
- Owner verified **both** Staging and Production have identical CSS SHA-256 `466410814f40b7abd09e1a8349dfbbd53691113f37163f9965ea67caf7d03109` and JS 1398-byte Git blob `437665a2300b8c8c87eb77214d6c899bdcb27866` (SHA-256 `34f96cb9edb2742b3c15494aeaba4ee2aee8ab5a7631f47cce77a4e8b830abc3`). The **only** difference from GitHub's accepted 1397-byte JS baseline blob `c4e56754ad13cdfb9aa2d6c040f0d6a018176314` is an extra terminal newline; not a code change.
- Corrected ZIP **`Barama-AutoUpdater-PremiumUX-BothSites-aaPanel-v1.0.1.zip`**, SHA-256 `77bda7470e6afa7e0739c58961e488cce7a07b24faf16afe130244d6b0b67257` supersedes v1.0.0. It requires the **exact** live JS blob above and confirms that removing exactly one trailing LF reconstructs the GitHub baseline before append. The resulting JS Git blob is **exactly** GitHub `main` `c297cc4a9c8ee50a9a5456a451f00fabf02fe59e`; CSS output matches `8912f550e37db4a704147cbffaaa83782d200a9d`.
- Local offline fixture: exact source preflight, install and rollback **passed independently for Staging and Production**; a mutated JS file was denied. Source files are backed up byte-for-byte including the original newline in private root-only backup directories. No live server changes were made by this fixture.
- **Historical status at package preparation, superseded by the accepted two-site installation receipts above.** No PHP/controller/license/signing/worker/real updater execution changes.

## UI Experience v1.0.2 — Source prepared, live deployment pending (2026-10-22)

- GitHub [PR #117](https://github.com/tonsamith128/Barama-Real-Estate/pull/117) adds **append-only** presentation improvements to the currently accepted updater theme stylesheet (`assets/css/barama-auto-updater-theme-v101.css`) and existing browser script (`assets/js/barama-auto-updater-unified-v100.js`).
- Lifecycle-first layout, versions/readiness below workflow, premium Manage license link, and a non-blocking, dismissible Check for updates notification. Existing exact publisher message text, check form, server result, worker status and install-button disabled state are not modified.
- A scoped Node DOM behavior test checks order, popup dismissal, license action, idempotence and unchanged disabled button. Explicit light/dark, mobile and focus styles are preserved.
- Owner-operated aaPanel ZIP: `Barama-AutoUpdater-PremiumUX-BothSites-aaPanel-v1.0.0.zip`, SHA-256 `ae65c1c4844815e6ad7bb59f4536f56a6a4bc9707e2b02fdad81a602d58da9d1`; exact source and Git blob guards, separate Staging/Production backups and rollback; *not deployed by GitHub merge*.
- **Live site status:** Not yet installed on Staging or Production. Do not report visual acceptance or successful remote update installation. Production's tester entitlement #8 remains NOT CONFIGURED; Staging worker remains governed by independent real worker readiness. A discovered v0.9.24 signature **is not** authorization to install.

## Current engineering handoff — core Staging worker integration (2026-10-22)

- The premium PHP-first UI is **visually accepted** on Staging and Production. **No further UI reconstruction** is on the critical path.
- **[PR #123](https://github.com/tonsamith128/Barama-Real-Estate/pull/123)** reconciles the previously owner-accepted Staging-only admin POST/CSRF route, protected queue, publisher trust, internal signed downloader, worker readiness and exact-session job progress against the current `main` v103 layout. It is **source parity, not a deployed new release**; do not overwrite the accepted live controller/view with a wholesale old branch.
- The independent Staging root worker **already exists**: `barama-oneclick-staging.service` and `/root/Barama-OneClick-Worker/worker-v1.0.1`. The exact worker and bridge fingerprints, existing owner approval session script, read-only `--check` and non-root `--probe` are captured in [AUTO_UPDATER_STAGING_0924_EXECUTION.md](AUTO_UPDATER_STAGING_0924_EXECUTION.md). **Do not invent a second worker architecture.**
- **Blocking gate:** verify current Staging bridge `feed_status=available`, `entitlement=Eligible`, `pending_migrations=0`; use an owner-approved single Staging v0.9.24 session with exact new administrator job ID and worker/recovery result, **never a fake queue item**. An earlier v0.9.24 signature is not proof of current publication/audience.
- Production tester activation #8 is **not** eligible without distinct exact authorization. No production worker, signing key, license change or customer root privilege.

## 1. What "done" means — executable acceptance, not a UI mockup

The Auto Updater is complete **only** when all of these pass against the *exact* candidate and current source SHA:

1. **Check for updates:** authenticated publisher answers `available`, `eligible/no update`, `denied`, `not configured` and `unavailable` correctly; the UI never mistakes a recognized activation for update entitlement.
2. **Trust & eligibility:** installation/domain/product/license/channel binding, release audience, publisher Ed25519 key, exact release ZIP SHA-256/size, compatibility, expiry, monotonic version and permissions are checked immediately before installation—not inferred from previous session state.
3. **One real action:** administrator-only, CSRF-protected, single-use **Update Now** creates one authenticated intent; concurrent submissions and stale/unauthorized requests fail closed. Displayed progress comes from the worker's real, job-bound results.
4. **Real transaction:** acquire verified artifact via protected HTTPS delivery; bounded extraction; verify allowlisted paths and permissions; take recoverable private file + full relevant MySQL backups; use journal/lock and controlled maintenance; install; run permitted additive migrations; perform health checks; atomically mark success only after verification.
5. **Recovery:** deliberately injected failure and abrupt process termination prove file + database recovery in an isolated realistic installation, then a controlled staging rehearsal with real, reviewed evidence. If recovery requires manual intervention, UI reports that exact state, not success.
6. **Staging demonstration:** an explicitly authorized signed update is installed on `baramastaging.tonsamith.com`. Database and files survive; recorded VERSION changes because the real release was applied, not because someone edited `VERSION`. Capture sanitized release/backup/rollback/health evidence.
7. **Customer portability:** the same trusted release workflow must work on a clean customer ZIP in supported PHP/MySQL/Apache/Nginx/shared-hosting conditions, **without assuming /root, aaPanel, a systemd root worker, or private server-side publisher keys on the customer's host**. Use a privilege-separated portable executor appropriate for customer hosting.
8. **Production rollout:** only after staging, portability, the **production installation's distinct license grant/audience**, backups and rollback all pass; requires separate owner approval and never borrows staging entitlement.

No green CI fixture, source merge, dashboard design, signed manifest, key addition, or `VERSION` change is proof of successful installation.

## 2. State of record — grounded snapshot (2026-10-22)

| Area | Verified state | Evidence/next implication |
| --- | --- | --- |
| GitHub `main` | `6a180598c9a9b8b25c07cccab713a10eb9fb6619`; `VERSION=0.9.22-dev` | Keep app version independent from UI/tool package versions. |
| Live staging | `0.9.23`; light/dark verified; root staging worker **stopped/disabled**, no submitted job confirmed | **v0.9.24 not installed.** Need one real signed staging transaction later. |
| Live production | `0.9.22-dev`; light/dark verified | Do not change application version until authorized release install succeeds. |
| Theme | PR [#111](https://github.com/tonsamith128/Barama-Real-Estate/pull/111) merged; deployed/visually accepted on both sites | **DONE for theme**, not updater execution. |
| PHP 8.5 `curl_close()` cleanup | PR [#112](https://github.com/tonsamith128/Barama-Real-Estate/pull/112) merged; all three GitHub QA jobs passed | **GitHub source done**; do not claim live deployment of this separate source patch. |
| R2 release public-key trust | Production key `barama-prod-2026-10-r2` additively trusted; old `barama-prod-2026-10` remains `active`. Signature validated. | **DONE for this public-key repair**; no further routine key rotation. Old-key retirement is separate security policy review. |
| Signed v0.9.23 candidate | 2-file update (`VERSION`, `PrivateArtifactReceiverV2.php`), SHA-256 `17f887aaa1a5dfeadf7e62ec0edbfb772c094793fe00d05f039f44405ba4a146`, Ed25519 valid; manifest `stable`, min `0.9.22-dev`, expires 2026-10-27 | **Signature ≠ entitlement/audience/install approval.** Not approved for production install. |
| Publisher entitlement, staging | activation #19: tester, `allow` stable grant, current validity, license #7, domain binding matched at observed check | Supports staging discovery only; still needs release audience, worker and recovery checks. |
| Publisher entitlement, production | activation #8: tester, exact activation/license/domain/product matched; **NO row** in `commercial_update_entitlements_v2` | Publisher `entitlement_not_configured` is **consistent with enforced tester policy**. Do not classify as a code regression or silently grant updates. Determine owner-authorized production tester terms, then create **one narrowly bound, time-limited, audited** grant only if intended. |
| Current GitHub UI | `auto-updater-unified-v100.php` is active. Its staging branch says discovery only; current `main` has no `/update-now` route | Missing functional staging Update Now is a **real integration gap**, not a CSS bug. |
| Tested components | Signature/ZIP preflights, private delivery, backup/recovery *fixtures*, and CI exist across main and draft branches | Pieces present != a demonstrated real installer. Avoid repeating passing audits without changed code or a failing gate. |

### Accepted live receipts: do not silently overwrite

These are **operator-reported 2026-10-22 hashes**, not substitutes for checking the current live file just before an approved patch:

| Accepted deployed artifact | SHA-256 / protected backup |
| --- | --- |
| Unified updater PHP view (both sites, after theme link) | `224c140a3842e02f548d6afcd233a88729575a30300bbd19ea7202a509567e13` |
| Unified original base CSS (both sites) | `a0d4cc5b36a2fbaf071c70aa4adc0a9ef8847f45df3419404c81e2623278ec96` |
| Scoped theme CSS (both sites) | `466410814f40b7abd09e1a8349dfbbd53691113f37163f9965ea67caf7d03109` |
| Staging theme rollback | `/root/Barama-AutoUpdater-Theme-Backups/staging-v101-rokzbdis` |
| Production theme rollback | `/root/Barama-AutoUpdater-Theme-Backups/production-v101-sbom8rak` |
| Production additive R2 public catalog | `94d6f3909d55e17f33291fa124031d6692af4d1673f1e3c0f68f7f1dcd8fc1be` |
| Production R2 public-trust rollback | `/root/Barama-Production-R2-PublicTrust-Backups/r2-additive-20261022T004659Z-a7e70905` |

Do not promote an older GitHub file over these accepted deployed changes or equate the Git blob hash with a disk SHA-256. Every deployment must compare actual current local hashes, review the intended delta and stop on drift. Never write production from a staging-specific artifact.

**Important boundaries:** The Production tester grant is a product/licensing decision, **not a reason to modify signing keys**. A tester grant, if approved, must have independently verified installation/license binding, expiry and the applicable audience; it must not confer staging-only release access. The already working staging entitlement must not be copied to production.

## 3. GitHub branch/PR reconciliation — no blind merges

| PR | Current role | Decision |
| --- | --- | --- |
| [#111](https://github.com/tonsamith128/Barama-Real-Estate/pull/111) | Unified theme | **MERGED/ACCEPTED**. |
| [#112](https://github.com/tonsamith128/Barama-Real-Estate/pull/112) | PHP 8.5 cURL cleanup | **MERGED/ACCEPTED** in GitHub. |
| [#113](https://github.com/tonsamith128/Barama-Real-Estate/pull/113) | Draft staging unified-view Update Now guard | **HOLD**. Depends on backend not on main. Do not deploy or merge alone; review together with one-click backend. |
| [#110](https://github.com/tonsamith128/Barama-Real-Estate/pull/110) | Draft admin-intent queue, controller endpoints, worker status and provisioning | **INTEGRATION INPUT ONLY**. Its base is the draft branch of [#109](https://github.com/tonsamith128/Barama-Real-Estate/pull/109), not `main`; must reconcile against current `main` and audited live source. |
| [#109](https://github.com/tonsamith128/Barama-Real-Estate/pull/109) | Draft trust/worker/recovery design | **REVIEW INPUT ONLY**, not a standalone release. |
| [#108](https://github.com/tonsamith128/Barama-Real-Estate/pull/108), [#107](https://github.com/tonsamith128/Barama-Real-Estate/pull/107), [#81–83](https://github.com/tonsamith128/Barama-Real-Estate/pulls), older #62–69 | Earlier proposals, many stacked on other drafts | **HISTORICAL/SUPERSEDED proposals** for selection, not release trains to merge in order. No automatic version increments. |
| One canonical new implementation PR | Branch from **current main**, cherry-pick/adapt only reviewed needed parts of #109/#110/#113 | **NEXT IMPLEMENTATION TARGET**. Must show one coherent end-to-end change and CI/acceptance evidence. |

**Policy:** No feature branch may simply copy old source over newer working code. Compare the currently deployed site's file SHA, latest `main`, and exact intended patch; stop on drift. No mass merges of stacked draft PRs. No claiming WIP branches are live.

## 4. Critical path — ONLY this sequence

### Gate A — Freeze scope and accepted baseline (done by this record)

- Recognize already fixed theme and R2 public-key trust; no further key rotations, theme rewrites or generic aaPanel/MySQL audits without new evidence.
- One plan, one canonical implementation branch from `main`, one current-status table and one checklist. Superseded proposals are explicitly non-authoritative.
- Track **application** release number separately from aaPanel patch-package, updater-module, and documentation versions.

### Gate B — Entitlement correctness (partially done, **policy decision remains**)

- Evidence already identifies Production as **tester** with a missing grant. This response is currently **correct fail-closed behavior**, not a verified code bug.
- Owner decides whether the Production tester activation is intended to receive stable releases, for what period. If yes, prepare separate exact activation #8 / license #7 / domain / installation / channel grant with explicit validity, approval, backup and audit; verify allowed and denied neighboring identities. If no, leave it denied and fix any misleading UI wording only.
- Stage remains its own activation #19. Neither entitlement proves audience for a signed ZIP.

### Gate C — Build one integrated admin → queue → executor → UI (NOT done)

- Reconcile #109/#110/#113 against current `main`. The active unified view must use its matching endpoint and status script, not the obsolete `system-center.php` UI.
- Make trusted worker readiness real, not simulated; preserve stopped state until reviewed launch. Never rely on a web-writable job file alone: require authenticated admin, CSRF, durable audit, nonce/expiry, lock, signed release/entitlement revalidation in independent executor.
- Connect the worker to a real, scoped file+DB transaction and job-state journal. Make "Update Now" enabled **only after all gates**; disabled UI explains exact failed gate.
- Do not treat a draft route, idle worker or successful mock run as "done."

### Gate D — Prove one complete staging update (NOT done)

- One approved audience-bound signing release (currently the v0.9.24 staging pilot is the proposed candidate, **not installed**); verify exact package and separate Publisher authorization.
- Confirm real staging DB backup + file snapshot, restore trial and failed-install/crash rehearsal. Run a **single explicitly approved** real staging job. Capture only sanitized receipts, before/after app hashes/version, migration results, health, completion and rollback.
- Any failure stops progression; fix only the failed contract. No repeated full audits when nothing changed.

### Gate E — Validate real customer portability (NOT done)

- On exact customer ZIP, test updater without root/aaPanel/systemd; test authorized Direct/CodeCanyon/staging-denied cases and unsupported hosting; prove backups and recovery and a safe failure message.
- Do not promote a staging-only privileged worker as a universal CodeCanyon Auto Updater.

### Gate F — Production decision (NOT approved)

- Require a distinct approved Production release and audience, owner-confirmed tester entitlement (or valid customer eligibility), independent signature verification, full production backups and dry-run recovery, explicit approval and rollback receipt.
- Only then install and re-verify true application version. **Never change `VERSION` alone, copy Staging state, or treat public-key trust as an installation grant.**

## 5. Definition-of-done test matrix (one result per contract)

| Scenario | Required outcome | Now |
| --- | --- | --- |
| Active entitled staging tester + available correctly targeted signed release | Can request; independent worker verifies and installs; displayed status and real VERSION accurate | **OPEN** |
| Unauthorized, mismatched domain/installation, expired, missing tester grant | Denied; no download/install; stable error | **Partially evidenced**; Production missing-grant denial verified |
| Old/replayed job and two simultaneous Update Now clicks | Cannot install twice; pending/recovery state explicit | **OPEN** |
| Corrupt/unsigned/revoked key/changed digest/expired ZIP | Hard stop before applying source or migrations | **Fixture coverage exists; real path OPEN** |
| Real file and complete MySQL backup followed by successful update | Independently verified recovery evidence | **OPEN** |
| Failure between files and migrations, SIGKILL and restart | Restore or fail-closed manual recovery with correct status | **Fixtures exist; end-to-end OPEN** |
| Exact CodeCanyon customer package on supported hosting without root | Install or safe unsupported-state handling as documented | **OPEN** |
| Distinct Production approval and target | No staging artifact or grant crosses the boundary | **OPEN** |

### Evidence rules
- Every status update must include: date, environment, source commit SHA, exact changed files/hash, type **(fixture/CI/staging live/production live)**, operator evidence, and next blocker. Never label "passed" for a gate based only on a prior chat's summary.
- Testing strategy: first check the **smallest changed contract**; run required full CI at the merge/release boundary; execute live/staging tests only when the changed behavior requires them. Avoid repeating stable baseline commands as conversation warm-up.
- Update this document **in the same PR** when a gate changes. Link exact PR/commit, not just a version number.
- Production backups must remain inaccessible to web requests and secrets must never enter commits, CI artifacts, issue text or logs.

## 6. Working agreement for every new chat

**Start here, not from an old conversational plan.**

1. Read this document, current `main` commit, relevant open PRs, and the latest recorded acceptance outcome **once**.
2. State **one** current blocking gate (B, C, D, E or F) and **one measurable change** to make in this turn. Prefer shipping code/test/docs over another generic preflight.
3. Work in a focused PR from current `main`; inspect live source/hash only for the exact files affected. No competing architectures or version bumps unrelated to application releases.
4. Prove the changed behavior with deterministic tests; record concrete evidence here. Merge only after review and appropriate CI; never silently overwrite already accepted work.
5. Don't expand scope while a prior stage has no real end-to-end proof. If safety gate fails, leave live installations unchanged and fix that precise gate.

## 7. Go / no-go checkpoint

**Continue**, but with this bounded plan. The immediate success checkpoint is **one real authorized staging installation and recovery demonstration**, not another themed UI or signing audit. If the team cannot integrate a safe executor or cannot prove recovery under supported hosting, explicitly mark the project **PAUSED** and ship it only as "signed release discovery"—never pretend it is a completed Auto Updater.

**Current next engineering action:** reconcile the functional core in #110 with the active unified UI in #113 into a **new single branch based on current main**, while independently deciding whether Production's missing *tester* entitlement is intended policy or requires an owner-approved exact grant. Do not deploy either until the contract tests and acceptance gates pass.
