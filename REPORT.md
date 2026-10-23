# REPORT — Barama Real Estate Auto Updater

> **2026-10-23 07:xx ICT — v1.0.0 guarded QA deployment STOP (NO DEPLOY) and corrected v1.0.1:** Owner ran exact v1.0.0 zip, all 12 tests passed, live read-only preflight confirmed `PREVIOUS_QA_ROLLBACK=VERIFIED`, `OLD_INSTALLED_METADATA=PASS`, index.php candidate PASS, then `STOP=PORTABLE_SERVICE_SAFETY_INCOMPATIBLE`. ROOT CAUSE verified against unchanged existing live-service baseline from GitHub commit 73bd720: v1.0.0 safety check searched for inline `'state'=>'rolled_back'` while actual portable coordinator assigns `$current['state']='rolled_back';`. This is a **false string-matcher rejection**, not a runtime recovery failure. No PHP files or DB changed in this attempt. Corrected package `Barama-QA-Portable-Bootstrap-Metadata-Fix-aaPanel-v1.0.1.zip` SHA256 `70b26b3829b814d0b12b637b1b4763a2455c90059e89e9d4ae92dc5efe75cbc5`, 14 local tests pass, including live-format assignment fixture and fail-closed absent-marker test. All code patches, approval scope, QA path, backup location and safety checks unchanged otherwise. **NEXT:** upload verified v1.0.1 package and run SHA/internal checks, --check and guarded --deploy on SAME QA site; no need to repeat publisher release signing, entitlement, download and rollback acceptance. Once deployed perform ordinary browser one-click end-to-end verified completion. Manifest expires 2026-10-23 10:07 ICT.\n\n> **UPDATE 2026-10-23 morning ICT — FIRST REAL ROLLBACK PASS, BOOTSTRAP/METADATA PATCH PREPARED:** Owner executed the existing bound QA portable recovery through PHP CLI as `www`, after verifying the private install journal, installed file digests and original private backup digests. Output confirmed `PORTABLE_QA_RECOVERY=PASS`, `ORIGINAL_QA_VERSION_RESTORED=PASS`, `ALL_ORIGINAL_FILES_RESTORED=PASS`, `ACTIVE_JOB_CLEARED=PASS`, `QA_ROLLBACK_ACCEPTANCE=PASS`, QA login HTTP 200. Previous updated VERSION was 0.9.23-qa.1; restored original VERSION 0.9.22-dev. Warnings about realpath(/root) were CLI-only under open_basedir and did not fail recovery. **First true installation + file rollback now verified.** Root cause of browser deadlock: root index.php version mismatch redirected all requests to the legacy DB upgrader, blocking portable-finish/recover; separate installed.php version metadata was not synchronized. Two surgical draft-PR commits (index.php f0b1c9ce26f18f9ca820e10cd550b865e6079359; PortableCustomerUpdateV1.php 7ef9d2d70b529c90e44dbc2654815d08ff1e6a90) fix these: narrowly allow verified pending health_pending, protected admin/CSRF portable finish/recover and login routes, and update installed.php metadata ONLY after signed-file final verification; interrupted finish can restore metadata during recovery. **Changes NOT yet deployed to QA**, Main untouched. Owner QA-only two-file zip package: `Barama-QA-Portable-Bootstrap-Metadata-Fix-aaPanel-v1.0.0.zip`, SHA256 `d2a843598af75acf82bee33ef0e7fcb7fb6e539dd0310d3b94943493932fd44d`, 12 tests passed including actual PHP route allow/deny harness. Next: root verify/check then guarded two-file QA deploy, from existing QA browser perform signed discovery + ONE standard one-click Install verified update; verify complete stage & installed.php metadata; no new QA site, no repeated signing, no DB migrations. Test signed release expiry 2026-10-23 10:07 ICT. UI progress counter still uses legacy staged-state and requires follow-up if not reconciled.

> **CRITICAL BLOCKER FOUND — 2026-10-23 ~00:32 ICT:** Following a successful actual one-stage portable install (state `health_pending`), browsing the QA dashboard gets redirected to the separate legacy `/updater/` upgrade page, making `/dashboard/system/auto-updater/portable-status`, `/portable-recover`, and `/portable-finish` inaccessible. Source confirmed: repo-root `index.php` unconditionally redirects all normal requests to `/updater/` whenever `Installation::metadata()['version'] !== APP_VERSION`. After the file-only QA install, APP_VERSION is `0.9.23-qa.1` from the newly installed VERSION file, but `installed.php` installation metadata remains `0.9.22-dev`. The old `/updater/` form is a separate DB-migration upgrade process; do not submit it. **Immediate recovery:** root-run read-only vault and backup journal/hash audit followed, if verified, by direct `runuser -u www` PHP CLI `PortableCustomerUpdateV1::recover()` against the exact bound pending QA transaction, then verify VERSION and backup restoration. This is a QA-only recovery command; no new test site. **Permanent PR fix required before another install:** guarded bootstrap routing for the pending portable transaction (admin+CSRF routes only), plus coherent post-`finish` installed metadata and crash-safe rollback behavior. Do not claim one-click works until this is fixed and the QA browser completes its normal flow.

> **LATEST 2026-10-23 ~00:28 ICT — QA INSTALL reached health_pending:** Owner's actual QA terminal independently confirmed `QA_DOWNLOAD_ACCEPTANCE=PASS` on 71,486-byte SHA256-pinned signed ZIP (receipt/envelope metadata, exactly two ZIP entries, no install at that check). Owner then executed separately authenticated `POST /dashboard/system/auto-updater/portable-install` from Chrome. Screenshot shows `{http:200,success:true,state:'health_pending',message:'Files installed; application verification is pending.'}`. **QA files were applied according to server response, but independent private backup audit, restore/recovery, finalization, website smoke tests and Main release remain NOT VERIFIED.** Do NOT call `/dashboard/system/auto-updater/portable-finish` yet. Do NOT use standalone legacy `/updater/` page; it is a distinct upgrade flow. Next immediate action: inspect `current.json` state health_pending, `active.json`, install journal and verified private file backups, actual updated site VERSION/file hashes; then authenticated single-stage `/portable-recover` and independent verify restored original VERSION `0.9.22-dev` and all original file hashes. Do not delete vault or repeat download while active.

> **UPDATE — 2026-10-23 ~00:22 ICT:** REAL authenticated QA publisher ZIP download **PASSED** from the customer's `/dashboard/system/auto-updater/portable-update-now` endpoint via browser CSRF-authenticated `POST`. Owner screenshot of Chrome console confirms `{http:200, success:true, state:'downloaded', message:'Signed ZIP downloaded to private storage; nothing installed yet.'}`. Signed release `v0.9.23-qa.1`, installed `v0.9.22-dev` remains. **NO install yet.** The prior `open_basedir` QA source fix was therefore deployed sufficiently for real download to work, but the actual deployment log was not provided. **NEXT:** independent read-only audit of sibling private vault `current.json`, downloaded `release.zip` exact SHA256 `6faacfcf8323bc3a40a9760de79ac1fbb0006df7089044f429b5451bf980d369` (71486 bytes), signed `envelope.json` and `receipt.json`; then controlled one-POST QA install, verify backed-up files and `health_pending`, exercise recovery before completion. Browser's main progress UI uses an older staged-inspector state and may still read 'Not staged' after actual portable download—do not use UI indicator as proof of failed download. Do NOT repeat original signing, QA audience enablement, or new staging creation.

> **UPDATE — 2026-10-23 ~00:08 ICT (supersedes the old current-blocker line below).** The v1.0.0 preflight mismatch was reproduced locally: under restricted PHP, original reports READY=NO / STATE=unavailable and corrected code reports READY=YES / STATE=not_started. Corrected test harness v1.0.1 passed 11/11 unit tests and a local restricted PHP 8.4 smoke test; SHA-256 of zip e0ed20f9bf446a21c306047d7a934c9022d5ecd3d69926d3dfec59bf93136811. The unchanged narrow source fix was committed to **PR #126 only**, commit 73bd7206002b4607809d3ac20f4242b3a497f291, preserving private vault and missing-vault guards. **NO QA WEBSITE PATCH DEPLOYED YET; real PHP-FPM, authenticated ZIP download, installation, rollback still UNVERIFIED.** Next immediate step: owner uploads v1.0.1 QA one-file patch, runs guarded deploy (fresh check inside deploy), verifies QA browser button, then runs single-stage download-only via authenticated browser; do not click JS-chained full install. Do not re-run v1.0.0. Current test manifest expiry remains 2026-10-23 10:07:17 ICT. This addendum takes precedence over any older 'immediate stop' text in this document.

**Authoritative cross-chat handoff and acceptance report**  
**Last updated:** 2026-10-22, approximately 23:23 Asia/Phnom_Penh (ICT)  
**Project:** Barama Real Estate, Portable File-Only Auto Updater (PHP, aaPanel)  
**Status:** **NOT FINISHED** — signed release is published, the exact QA audience is enabled, and authenticated customer discovery is confirmed; ZIP download, file installation, health verification, and rollback are **NOT** verified.  
**Current immediate stop:** `STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH` in **read-only** vault/open_basedir patch package `v1.0.0`. Seven local tests passed; **do not deploy this patch yet**.

> **NEW CHAT — READ THIS FIRST:** Continue the **existing draft PR #126** and already-provisioned QA installation. **Do not start over. Do not rebuild or re-sign the release or repeat completed checks.** Fix the specific read-only QA readiness-test expectation (see section 2), verify PHP-FPM readiness, then do **one download-only** authenticated test, inspect the ZIP, and proceed to separately controlled installation and recovery. Do not merge until acceptance passes. This REPORT is the source of truth. The user is frustrated by almost seven days of repetitive preparation and wants completion, not another sequence of preliminary audits.

## 1. Summary: what is verified, and what remains

| Milestone | Real verified result (not a plan) |
|---|---|
| Fresh QA site installed | **PASS** — installed `0.9.22-dev` at `/www/wwwroot/barama-updater-qa.tonsamith.com` |
| Isolated QA networking | **PASS** — Nginx localhost-only hold; Mac SSH SOCKS proxy; browser authenticated as QA admin |
| Exact QA tester entitlement | **PASS** — scoped to one active tester, domain and installation, `stable`, short-lived grant; not a general customer entitlement |
| Portable unsigned test package | **PASS** — 2 entries, `VERSION` and inert-comment-only `assets/css/app.css`; 71,486 bytes |
| Offline Ed25519 signing | **PASS** — Mac held the encrypted key; signature independently verified |
| Publisher V2 release record and artifacts | **PASS** — guarded owner-approved publication committed; independent SHA checks and recovery journal passed |
| Exact QA audience guard activation | **PASS** — approved guarded constant change; `ALLOW_EXACT_QA_ONLY`; no other audiences unlocked |
| Authenticated QA client discovery | **PASS** — screenshot: `v0.9.22-dev` → verified signed `v0.9.23-qa.1`, `Eligible`, step 3 of 7, `No package was downloaded` |
| QA portable vault CLI | **PASS** — CLI as `www`: sodium/cURL/ZIP yes; root and vault writable, private vault exists, state `not_started`, pending NO |
| PHP-FPM portable readiness / UI button | **NOT CONFIRMED** — dashboard previously showed Install unavailable despite successful discovery |
| Latest QA readiness hotfix | **BLOCKED IN READ-ONLY PRECHECK** — `STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH`; 7 tests PASS; site NOT patched |
| Authenticated signed ZIP download into private vault | **NOT TESTED** |
| ZIP inspection and exact digest in QA private vault | **NOT TESTED** |
| Backup, file replacement, independent post-install boot | **NOT TESTED** |
| Transaction recovery and rollback | **NOT TESTED** |
| GitHub PR #126 merged / CodeCanyon-ready | **NO** — keep DRAFT and unmerged |

**Practical conclusion:** The publisher/signature/discovery pipeline is functioning. The unfinished core is the actual **portable customer download → verify → backup/install → health → rollback**. Do not repeat upstream milestones.

## 2. Current blocker — complete reproduction and likely root cause

**Latest owner aaPanel command (2026-10-22):** verified package checksum, extracted package, validated Python syntax, ran 7/7 local tests, then ran `python3 -B manage.py --check`. Output ended:

```text
Barama-QA-Vault-Parent-OpenBasedir-Fix-aaPanel-v1.0.0.zip: OK
manage.py: OK
README.md: OK
tests/test_patch.py: OK
PYTHON_SYNTAX=PASS
Ran 7 tests in 0.001s
OK
========== READ-ONLY QA PREFLIGHT ==========
STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH
```

**No `--deploy` was executed. No QA website source file was changed by this attempt.** No update ZIP downloaded or installed.

Source file of the **private extracted patch** (already on aaPanel):

`/root/Barama-QA-Vault-Parent-OpenBasedir-Fix-aaPanel-v1.0.0/manage.py`

Package digest: `af52ddae31219230cefb89cebf682af09cb615e3ccf3e3f60b751e47c5d6060d`.

What that preflight does: it creates a temporary test PHP copy under `/tmp`, calls PHP as `www` with `-d open_basedir=QA:VAULT:/tmp/`, and probes both baseline and candidate source. In its `restrict_probe()` it requires **both** the expected `READY=...` value and literal `STATE=not_started` **for each probe**, otherwise raises `STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH` without printing the actual observed values.

**Likely test-harness mistake (reasoned from inspected code, NOT yet empirically confirmed):** when the baseline `vault(false)` throws because parent `/www/wwwroot` is not accessible under `open_basedir`, `PortableCustomerUpdateV1::status()` catches the exception and returns `state=unavailable`, not `state=not_started`. Therefore the baseline can correctly produce `READY=NO` and `STATE=unavailable`, causing the preflight's hardcoded `STATE=not_started` assertion to fail prematurely. The new candidate, once tested, should give `READY=YES`, `STATE=not_started`. The failure does **not** prove the candidate patch is wrong.

**Next action — one focused correction, not a rebuild of all packages:** Modify **only the private test harness**, not live PHP, to print the observed `READY`, `STATE`, and a bounded stderr summary separately for baseline and candidate; accept `STATE=unavailable` for the baseline (when `READY=NO`), and require `STATE=not_started` and `READY=YES` for candidate. Rerun the full `--check`; if and only if it passes, use the package's already-built exact one-file guarded installer with fresh owner approval, preserving rollback. If the observed values differ, diagnose them instead of forcing a pass. Do not broaden `open_basedir` or make `/www/wwwroot` writable.

**This is a narrow likely bug in the QA probe itself, not another reason to restart the project.**

### Relevant app source

`/www/wwwroot/barama-updater-qa.tonsamith.com/application/app/Services/Updates/PortableCustomerUpdateV1.php`

The existing `vault()` method verifies the parent `/www/wwwroot` using `is_link`, `is_dir`, `realpath`; under restricted PHP-FPM that parent is outside `open_basedir` even while the existing sibling vault is allowed. The proposed one-file patch defers parent checks *only for an already existing, exact, canonical, private sibling vault*; missing vault creation retains strict parent checks; the vault's symlink, realpath, mode and writability gates remain. Treat this as an implementation proposal until real PHP-FPM readiness is measured.

**Read-only baseline values already provided:**

```text
PHP_CLI_VERSION=8.5.11
PHP_SODIUM=YES
PHP_CURL=YES
PHP_ZIP=YES
QA_ROOT_CANONICAL=YES
QA_ROOT_WRITABLE=YES
VAULT_PARENT_CANONICAL=YES
VAULT_PARENT_WRITABLE=NO
VAULT_DIRECTORY_EXISTS=YES
VAULT_NOT_SYMLINK=YES
VAULT_PRIVATE_MODE=YES
VAULT_WRITABLE=YES
CURRENT_UPDATE_JOB_PRESENT=NO
ACTIVE_INSTALL_JOB_PRESENT=NO
PROTECTED_STAGING=NO
PORTABLE_CLI_READINESS=YES
CLI_UPDATER_STATE=not_started
CLI_JOB_PENDING=NO
CLI_PORTABLE_READY=YES
```

`.user.ini` `open_basedir` (do not loosen it):

`/www/wwwroot/barama-updater-qa.tonsamith.com/:/www/wwwroot/.barama-private-updates-aeaee58ac18d79779e/:/tmp/`

Nginx virtual host has `include enable-php-85.conf;`.

**PHP-FPM result has not been pasted into chat:** authenticated read-only `GET /dashboard/system/auto-updater/portable-status` could reveal `state=not_started` or `unavailable`; don't claim it was tested. Note: endpoint JSON `status()` does not directly prove `ready()`. Inspect web-run readiness or renderer after patch rather than assuming.

## 3. Hard constraints from the owner

- **Auto Updater ONLY.** Do not resume CodeCanyon readiness, unrelated features, theme polish, or source-wide scans.
- Continue from **existing draft** [GitHub PR #126](https://github.com/tonsamith128/Barama-Real-Estate/pull/126), `tonsamith128/Barama-Real-Estate`, branch `feat/auto-updater-portable-file-only-v1-20261022`. Baseline of the source read in this discussion was SHA `dd2906fd65b2bef82736b170bd246e463d03d981` (branch may acquire new report commits); **DO NOT MERGE MAIN** until actual download/install/rollback acceptance.
- The portable updater is for ordinary PHP shared hosting. No root worker/cron/systemd in the customer package; no SQL migrations in initial file-only release.
- Never use Production or Staging as QA targets; do not re-target prior reserved staging/production release IDs.
- Preserve `V2PublicationInterlock` and `BARAMA_V2_SEPARATE_PUBLICATION_APPROVAL_REQUIRED`; no generic Barama publish bypass. Keep stable CodeCanyon/Envato targeting separate from the temporary `tester` QA grant.
- Private Ed25519 signing key **only on Mac**. Never ask owner to paste private key, passphrase, activation token, license, or DB identifiers. Only public verification data or safe console summary.
- QA website Nginx remains loopback-only; do not expose site publicly or weaken Cloudflare, DNS, Nginx allow/deny. Keep FPM `open_basedir` restricted. No broad `chmod`, no globally writable web root.
- Separate explicit approval for any **live write**, deployment, release publish, audience enable/disable, or recovery. Do not claim a read-only test installed code. Preserve all rollback journals and backups.
- Owner priority: **FINISH THE CORE**, no repeated preflight loops, no unnecessary versioned ZIP churn. State clearly what is unverified.

## 4. Infrastructure and access (verified)

**QA customer:**

- Domain `https://barama-updater-qa.tonsamith.com`
- Root `/www/wwwroot/barama-updater-qa.tonsamith.com`
- QA admin UI `/dashboard/system/auto-updater`
- Current installed `VERSION`: `0.9.22-dev`
- PHP `/www/server/php/85/bin/php`; CLI 8.5.11; all required extensions present.
- Private sibling vault `/www/wwwroot/.barama-private-updates-aeaee58ac18d79779e` exists with private mode; writable as `www`; no pending `current.json` or `active.json` during most recent audit.
- `GET /dashboard/system/auto-updater/portable-status` is admin-only read-only JSON endpoint (actual FPM JSON has not been supplied).

**Publisher:**

- `https://tonsamith.com`; root `/www/wwwroot/tonsamith.com`; PHP 8.5 binary above.
- V2 manifest endpoint: `POST /license/v2/update/manifest`.
- V2 artifact endpoint: `POST /license/v2/update/artifact`; source route `routes/license-manager.php:54`, controller `app/Http/Controllers/Api/V2/LicenseArtifactV2Controller.php`, streamer `app/CommercialUpdates/AuthorizedArtifactStreamV2.php`, resolver `EntitledArtifactResolverV2.php`.
- Actual unauthenticated artifact smoke: **HTTP 403 JSON, 51 bytes**; `ARTIFACT_HTTP_NEGATIVE_SMOKE=PASS`.
- Actual authenticated manifest discovery: **PASS** shown in QA UI, signed release verified.

**QA web access:**

- Server has localhost-only Nginx hold (`allow 127.0.0.1; allow ::1; deny all`); owner Mac SSH SOCKS proxy successfully got `QA_PROXY_HTTP_STATUS=200`.
- Working Mac configuration used local proxy port `10809` and a separate Chrome profile; owner screenshots show login and then authenticated updater dashboard. The previous direct Chrome 403 and misconfigured proxy errors were resolved; do **not** investigate them again.
- Keep QA isolation intact; the Mac Chrome profile might not persist across sessions.

## 5. Signed release: exact identity, artifacts, hashes and time limit

- Product: `barama-real-estate`
- Release ID: `barama-qa-0.9.23-qa.1-portable-v1`
- Installed version: `0.9.22-dev`
- Signed candidate version: `0.9.23-qa.1`
- Channel: `stable` (still audience-restricted, **not** unrestricted stable)
- Release source DB value: `qa-portable-v2`; not `envato` and not CodeCanyon customer channel.
- Manifest: schema `tonsamith-commercial-release-v2`; signed `release_status=published`, `migration_policy=additive`, release type `update`, package bytes `71486`, min PHP `8.3.0`, minimum app version `0.9.22-dev`.
- Active signing public key ID: `barama-prod-2026-10-r2`. Encrypted private key file on Mac was at `~/Downloads/Barama-Publisher-Offline-2026-10-20-r2-v101/PUBLISHER-PRIVATE-ENCRYPTED.pem`; mode `0600` and signature verified. **Do not send that file to server.**
- Signed manifest expires **2026-10-23T03:07:17Z**, i.e. **2026-10-23 10:07:17 ICT**. The QA tester entitlement was confirmed valid through this expiry when registered. After expiration, don't reuse manifest; any new release needs separately scoped review/signing. Guard activation is NOT automatically reversed on expiration.
- Original private unsigned ZIP (contents valid despite filename `UNSIGNED`): `/root/Barama-QA-Portable-Unsigned-v1.0.0-X57Fjowc/Barama-QA-Portable-Update-0.9.23-qa.1-UNSIGNED.zip`
- Original archive SHA256 `6faacfcf8323bc3a40a9760de79ac1fbb0006df7089044f429b5451bf980d369`, 71,486 B, entries `VERSION` and `assets/css/app.css` (inert CSS marker).
- Original QA CSS SHA256 `f49fa46444c6c4059813cee7878e992d1887618f6895e3c56b7d6c0b144b5a62`; proposed candidate CSS SHA256 `4fccd3695e0a753b4104319dd868bc2819cb29be16e83744919cd72681fb06bb`.
- Signed envelope SHA256 `bc5d655992ea3b0df2041accea05527c94dd716bb9105be00643ede0285bcd54`. Publisher independent signature verification **PASS**.
- Signed manifest in publisher: `/www/wwwroot/tonsamith.com/storage/private/commercial-updates/v2/manifests/barama-qa-0.9.23-qa.1-portable-v1.json` (SHA as above).
- Published ZIP in publisher: `/www/wwwroot/tonsamith.com/storage/private/commercial-updates/packages/Barama-QA-Portable-Update-0.9.23-qa.1.zip` (SHA as above).
- Publisher release DB row committed with exact approval and verified after transaction. Audience guard currently **ENABLED_EXACT_QA_ONLY**; do not mistake this for unrestricted public delivery.

## 6. Critical rollback and private review locations — never delete

- Publisher disabled-router rollback: `/root/Barama-QA-V2-Disabled-Router-Rollback-vhynfvrn`
- Publisher publication recovery journal: `/root/Barama-QA-V2-Publication-Transaction-v1.0.0/transaction-journal.json` (owner verified `PUBLISH_COMPLETE`).
- QA audience activation journal: `/root/Barama-QA-V2-Audience-Activation-v1.0.0/activation-journal.json` (owner verified exact-byte guard enable).
- Signed envelope/receipt review: `/root/Barama-QA-V2-Signed-Review-v1.0.0/`
- Private manifest preparation review: `/root/Barama-QA-V2-Manifest-Review-0x11me8b`
- QA release registration proposal: `/root/Barama-QA-V2-Release-Slot-Review-vvehfd5j/release-registration-PROPOSAL-DO-NOT-PUBLISH.json` (SHA256 `2978e8fdd1cf099e388afce3776140068c5b678a572a0e2b923a96d491d9391d`)
- Publication execution plan: `/root/Barama-QA-V2-Publication-Preflight-atx7trh8/publication-execution-PLAN-REVIEW-ONLY.json` (SHA256 `f1b18da99d16e1e78f0d8d3355200a73f257534b5122ac8b06beb2bf283ad266`)
- Current uninstalled QA readiness fix archive: `/root/Barama-QA-Vault-Parent-OpenBasedir-Fix-aaPanel-v1.0.0.zip` (SHA `af52ddae31219230cefb89cebf682af09cb615e3ccf3e3f60b751e47c5d6060d`), extracted directory same name sans `.zip`.
- **No rollback folder for current vault fix exists yet**, because preflight failed and no deployment was attempted.

## 7. Timeline — completed milestone trail; NEVER redo without a new factual failure

1. Established isolated QA installation at `0.9.22-dev`, verified installer, server health, Nginx loopback restriction, and QA administrator access.
2. Created narrowly bound single active tester entitlement for `stable`, separate from `envato` production/CodeCanyon audiences; verified real authorized discovery returned eligible with no release yet.
3. Built exact two-file private unsigned QA update ZIP and audited its SHA, CRC, target baseline and update eligibility.
4. Reviewed Publisher V2 manifest schema, private ZIP resolver, audience selector, publication interlock, signed discovery controller and separate HTTP ZIP streamer. Verified unauthenticated artifact endpoint returns HTTP 403; no need to rebuild HTTP route.
5. Prepared and installed disabled QA router/guard (with typed owner approval and rollback); synthetic allow-only-for-matching-tester and 14 denial fixtures passed.
6. Generated V2 unsigned signing input; Mac SHA-verified handoff; Mac encrypted Ed25519 key owner-operated signing with hidden passphrase; publisher independently verified signed envelope and exact ZIP.
7. Completed read-only release schema/collision checks and transaction plan checks. Fixed a prior validation bug (`PLAN_ROW_PIN_MISMATCH_manifest_storage_path`) in publication transaction **v1.0.1**; 15 tests and live `QA_RELEASE_TRANSACTION_CHECK=PASS`.
8. Owner explicitly approved, performed and independently post-verified private artifact staging + one exact published row; `QA_RELEASE_PUBLICATION_TRANSACTION=PASS`, `QA_PUBLICATION_POSTCHECK=PASS`; journal saved.
9. Owner separately approved QA-only guard activation; `QA_AUDIENCE_ACTIVATION=PASS`, exact-byte postcheck and journal saved; publication interlock and production/staging paths preserved.
10. Owner restored Mac browser access through SSH SOCKS; authenticated to QA dashboard; clicked **Check for updates**. Screenshot: *“A newer publisher-signed release was found. No package was downloaded.”* It reported entitlement Eligible, signed candidate `v0.9.23-qa.1`, release signature Verified, **Step 3 of 7**.
11. Detected disabled install control. PHP CLI as `www` readiness YES, FPM `.user.ini` `open_basedir` restricts QA root + existing private sibling vault + /tmp, excluding parent. Identified probable unnecessary parent check in portable vault helper.
12. Built QA one-file readiness patch with 7 unit tests PASS. **Current failure:** read-only live `manage.py --check` aborted `STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH`; **no patch deployed**. See section 2 for precise suspected fixture expectation bug and single next action.

## 8. Exactly how customer download/install is wired — avoid accidental auto-install

Current PR #126 source confirmed:

- `application/app/Services/Updates/PortableCustomerUpdateV1.php`: `begin()` authenticates and privately downloads the signed artifact, then writes current-job receipt with state `downloaded`; `install()` revalidates and creates backup before replacing allowed files with `VERSION` last; `finish()` independently verifies new boot/version/files; `recover()` restores a bound job if not finalized. Vault cannot be under site document root. No DB migrations.
- `application/app/Services/Updates/PortableUpdateDownloadV1.php`: authorized HTTPS `POST https://tonsamith.com/license/v2/update/artifact`, bounded to 8 MiB, TLS checked, no redirect, private ZIP and SHA validation.
- `application/app/Http/Controllers/SystemCenterController.php`: `portableBegin`, `portableInstall`, `portableFinish`, `portableRecover`, `portableStatus`; admin-only and CSRF via `application/routes/web.php`.
- `assets/js/barama-portable-auto-updater-v1.js`: **IMPORTANT** clicking the standard "Install verified update" can trigger a JS loop across *all three POST endpoints* `portable-update-now` → `portable-install` → `portable-finish` in one interaction. **Do not click it for download-only acceptance.** Use an explicit reviewed single-stage request, or add separate UI action to download/stage only; verify receipt before approving installation.
- View `application/resources/views/workspace/auto-updater-unified-v100.php` computes `$uReadyInstall = !$uStaging && $uPortable && $uVersionAvailable && !$uPortablePending`; when PHP-FPM `ready()` fails, the button says **Install update unavailable** even when discovery succeeded. Step indicators 4–7 remain unverified.
- Read-only admin endpoint: `/dashboard/system/auto-updater/portable-status` (does NOT itself report all `ready()` prerequisites).
- Important rollback semantics: after `finish()` sets `completed_file_only`, the app's `recover()` **rejects** finished jobs. For a true rollback acceptance, exercise rollback from `health_pending` before finish, OR use a separately approved restoration test plan for a fresh QA cycle; do not call a completed installation reversibly without testing a distinct recovery path.

**Definition of DONE** (real owner-observed results, not unit fixtures):

1. Browser PHP-FPM readiness shows private vault usable, install controls appropriate, without changing security restrictions.
2. Authenticated **download-only** request completes, stays on installed `0.9.22-dev`, state `downloaded`, private `release.zip` + signed envelope + receipt exist and SHA exact; no files installed.
3. ZIP inspection accepts exactly the allowed file changes, manifest version/hash, file size, paths, and backups; no DB changes.
4. Separately authorized QA install applies `0.9.23-qa.1` from a verified private backup and proves independent HTTP/new-PHP boot and file integrity; separately exercise rollback to `0.9.22-dev` while legally recoverable.
5. Verify both QA and publisher health, exact grants, retention of Production/Staging and generic interlock; disable the temporary QA audience when finished; document pass/fail; update PR #126 and then decide merge with owner approval.

## 9. Fast finish plan for the next assistant

**No more new signed release, entitlement, key rotation, publisher publication, or upstream source audits unless existing signed manifest has expired and continuation strictly requires a new test release.**

**A. Correct the failed QA test harness:** inspect pre-existing `/root/...Vault-Parent.../manage.py` `restrict_probe`. Before changing anything live, print actual baseline and candidate `READY` and `STATE`. Fix the baseline state expectation if it is `unavailable`, require candidate ready and idle, then test with same exact source/guards. If the probe says candidate not ready, investigate PHP warning/error within bounds rather than weakening guards. Keep `--check` read-only.

**B. Deploy ONE exact QA-only source patch, only if check passes and owner explicitly approves:** one `PortableCustomerUpdateV1.php` file, private backup/journal, no Nginx/permissions/key/DB changes. Confirm actual FPM readiness and an accessible QA admin page.

**C. Download only:** don't click JS-chained Install. Use the admin CSRF-protected `portable-update-now` step only through a reviewed single-action UI or script in authenticated QA browser; no token values in chat/logs. Confirm private ZIP exact SHA, 71,486 bytes, `downloaded`, version still `0.9.22-dev`. Validate failed/unauthorized requests fail closed. Do not accidentally invoke `portable-install`.

**D. Core functional acceptance:** separately approve install, take verified backup, install file-only, check new boot and targeted files. For rollback, use recover while health pending; consider a second fresh QA cycle if verifying both completed-install and rollback is needed. Avoid automatic three-stage click until safety and recovery results are known.

**E. Close out:** check all outcomes, disable QA guard with separately approved CLI, preserve recovery journals, update this REPORT with actual outcomes, then review draft PR #126 for possible merge. No CodeCanyon claims before this.

**Time constraint:** Current signed envelope expiration `2026-10-23 10:07:17 ICT`. Check actual clock before any authenticated download/install; expired manifest must be rejected, not bypassed or retroactively edited. If needed, a new QA signed candidate must be generated with fresh approval; only then repeat the **unavoidably time-dependent signing** steps (not seven days of earlier environment audits).

## 10. Key checkpoints in literal owner output (for audit)

```text
DRAFT_BUILD_RESULT=PASS
SIGNED_PACKAGE_PREPARATION_PREFLIGHT=PASS
AUDIENCE_REVIEW_PREPARATION=PASS
QA_WIRING_REVIEW=PASS
DISABLED_QA_ROUTER_PREFLIGHT=PASS
QA_ROUTER_INSTALL=PASS
DISABLED_QA_ROUTER_POSTCHECK=PASS
V2_SIGNING_DELIVERY_CONTRACT_AUDIT=COMPLETE
ARTIFACT_HTTP_NEGATIVE_SMOKE=PASS
MANIFEST_REVIEW_PREPARATION=PASS
OFFLINE_SIGNING_HANDOFF=PASS
MAC_HANDOFF_CHECK=PASS
MAC_OFFLINE_SIGNING=PASS
SIGNED_ENVELOPE_SERVER_REVIEW=PASS
QA_RELEASE_SLOT_REVIEW=PASS
QA_PUBLICATION_EXECUTION_PREFLIGHT=PASS
QA_RELEASE_TRANSACTION_CHECK=PASS
QA_RELEASE_PUBLICATION_TRANSACTION=PASS
QA_PUBLICATION_POSTCHECK=PASS
QA_AUDIENCE_ACTIVATION_PREFLIGHT=PASS
QA_AUDIENCE_ACTIVATION=PASS
QA_GUARD_ACTIVATION_POSTCHECK=PASS
CLI_PORTABLE_READY=YES
CLI_UPDATER_STATE=not_started
# User screenshot: Signed release found, Eligible, v0.9.23-qa.1, Step 3 of 7
# Latest read-only hotfix: 7 tests OK, STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH
# NOT SEEN: download PASS; package staging/inspection PASS; install PASS; rollback PASS
```

## 11. How to start the next ChatGPT (or any other assistant) without repeating work

Copy only this one paragraph as the next message (and provide this `REPORT.md` or its GitHub path):

> **Continue the Barama Auto Updater from `REPORT.md` on draft GitHub PR #126. The signed QA release is already published and audience-activated, and authenticated discovery passes through step 3/7. DO NOT redo signing, publishing, entitlement, installation, access, or prior audits. The latest QA vault readiness hotfix `v1.0.0` passed seven unit tests but its read-only `manage.py --check` failed `STOP=RESTRICTED_PHP_EXPECTATION_MISMATCH`; the probe likely wrongly expects baseline `state=not_started` instead of `unavailable`. Fix that exact private test-harness issue, verify PHP-FPM readiness, then do a single-stage authenticated ZIP download only. Do not click the existing JS-chained install button. Finish independently controlled install and rollback tests; keep production/staging and the generic publisher V2 interlock untouched. Don't merge PR #126 until real acceptance.**

---

**Report provenance:** consolidated from user-provided terminal output and screenshots, verified source of the existing updater in PR #126, inspection of the actual v1.0.0 vault patch `manage.py`, and maintained project handoff context. Separate direct observations from hypotheses: the latest fixture mismatch cause is **likely** based on code logic, not yet confirmed by observed probe values. No private signing key or activation token content is stored here.