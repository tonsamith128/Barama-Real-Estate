# Barama Auto Updater — Canonical Delivery Plan and Status

**Status:** IN PROGRESS / NOT END-TO-END COMPLETE  
**Recorded:** 2026-10-22 (Day 6 review)  
**Owner:** Ton Samith  
**Authoritative branch:** \`main\`; proposed features are **not shipped** merely because they appear on a draft branch.  
**Scope:** **Auto Updater only**. This is the single current delivery/status document. Earlier updater design, phase and acceptance documents are *historical evidence*, **not** instructions to resurrect their code or claims that a feature is deployed.

> **North star:** A verified administrator can check entitlement and see a genuinely offered, correctly signed, installation-specific release, request **Update Now**, observe honest progress, and finish with the *actual* installed version updated and a verified recovery path. A CodeCanyon customer install must ultimately support this without root/server-owner privileges.

## 1. What "done" means — executable acceptance, not a UI mockup

The Auto Updater is complete **only** when all of these pass against the *exact* candidate and current source SHA:

1. **Check for updates:** authenticated publisher answers \`available\`, \`eligible/no update\`, \`denied\`, \`not configured\` and \`unavailable\` correctly; the UI never mistakes a recognized activation for update entitlement.
2. **Trust & eligibility:** installation/domain/product/license/channel binding, release audience, publisher Ed25519 key, exact release ZIP SHA-256/size, compatibility, expiry, monotonic version and permissions are checked immediately before installation—not inferred from previous session state.
3. **One real action:** administrator-only, CSRF-protected, single-use **Update Now** creates one authenticated intent; concurrent submissions and stale/unauthorized requests fail closed. Displayed progress comes from the worker's real, job-bound results.
4. **Real transaction:** acquire verified artifact via protected HTTPS delivery; bounded extraction; verify allowlisted paths and permissions; take recoverable private file + full relevant MySQL backups; use journal/lock and controlled maintenance; install; run permitted additive migrations; perform health checks; atomically mark success only after verification.
5. **Recovery:** deliberately injected failure and abrupt process termination prove file + database recovery in an isolated realistic installation, then a controlled staging rehearsal with real, reviewed evidence. If recovery requires manual intervention, UI reports that exact state, not success.
6. **Staging demonstration:** an explicitly authorized signed update is installed on \`baramastaging.tonsamith.com\`. Database and files survive; recorded VERSION changes because the real release was applied, not because someone edited \`VERSION\`. Capture sanitized release/backup/rollback/health evidence.
7. **Customer portability:** the same trusted release workflow must work on a clean customer ZIP in supported PHP/MySQL/Apache/Nginx/shared-hosting conditions, **without assuming /root, aaPanel, a systemd root worker, or private server-side publisher keys on the customer's host**. Use a privilege-separated portable executor appropriate for customer hosting.
8. **Production rollout:** only after staging, portability, the **production installation's distinct license grant/audience**, backups and rollback all pass; requires separate owner approval and never borrows staging entitlement.

No green CI fixture, source merge, dashboard design, signed manifest, key addition, or \`VERSION\` change is proof of successful installation.

## 2. State of record — grounded snapshot (2026-10-22)

| Area | Verified state | Evidence/next implication |
| --- | --- | --- |
| GitHub \`main\` | \`6a180598c9a9b8b25c07cccab713a10eb9fb6619\`; \`VERSION=0.9.22-dev\` | Keep app version independent from UI/tool package versions. |
| Live staging | \`0.9.23\`; light/dark verified; root staging worker **stopped/disabled**, no submitted job confirmed | **v0.9.24 not installed.** Need one real signed staging transaction later. |
| Live production | \`0.9.22-dev\`; light/dark verified | Do not change application version until authorized release install succeeds. |
| Theme | PR [#111](https://github.com/tonsamith128/Barama-Real-Estate/pull/111) merged; deployed/visually accepted on both sites | **DONE for theme**, not updater execution. |
| PHP 8.5 \`curl_close()\` cleanup | PR [#112](https://github.com/tonsamith128/Barama-Real-Estate/pull/112) merged; all three GitHub QA jobs passed | **GitHub source done**; do not claim live deployment of this separate source patch. |
| R2 release public-key trust | Production key \`barama-prod-2026-10-r2\` additively trusted; old \`barama-prod-2026-10\` remains \`active\`. Signature validated. | **DONE for this public-key repair**; no further routine key rotation. Old-key retirement is separate security policy review. |
| Signed v0.9.23 candidate | 2-file update (\`VERSION\`, \`PrivateArtifactReceiverV2.php\`), SHA-256 \`17f887aaa1a5dfeadf7e62ec0edbfb772c094793fe00d05f039f44405ba4a146\`, Ed25519 valid; manifest \`stable\`, min \`0.9.22-dev\`, expires 2026-10-27 | **Signature ≠ entitlement/audience/install approval.** Not approved for production install. |
| Publisher entitlement, staging | activation #19: tester, \`allow\` stable grant, current validity, license #7, domain binding matched at observed check | Supports staging discovery only; still needs release audience, worker and recovery checks. |
| Publisher entitlement, production | activation #8: tester, exact activation/license/domain/product matched; **NO row** in \`commercial_update_entitlements_v2\` | Publisher \`entitlement_not_configured\` is **consistent with enforced tester policy**. Do not classify as a code regression or silently grant updates. Determine owner-authorized production tester terms, then create **one narrowly bound, time-limited, audited** grant only if intended. |
| Current GitHub UI | \`auto-updater-unified-v100.php\` is active. Its staging branch says discovery only; current \`main\` has no \`/update-now\` route | Missing functional staging Update Now is a **real integration gap**, not a CSS bug. |
| Tested components | Signature/ZIP preflights, private delivery, backup/recovery *fixtures*, and CI exist across main and draft branches | Pieces present != a demonstrated real installer. Avoid repeating passing audits without changed code or a failing gate. |

**Important boundaries:** The Production tester grant is a product/licensing decision, **not a reason to modify signing keys**. A tester grant, if approved, must have independently verified installation/license binding, expiry and the applicable audience; it must not confer staging-only release access. The already working staging entitlement must not be copied to production.

## 3. GitHub branch/PR reconciliation — no blind merges

| PR | Current role | Decision |
| --- | --- | --- |
| [#111](https://github.com/tonsamith128/Barama-Real-Estate/pull/111) | Unified theme | **MERGED/ACCEPTED**. |
| [#112](https://github.com/tonsamith128/Barama-Real-Estate/pull/112) | PHP 8.5 cURL cleanup | **MERGED/ACCEPTED** in GitHub. |
| [#113](https://github.com/tonsamith128/Barama-Real-Estate/pull/113) | Draft staging unified-view Update Now guard | **HOLD**. Depends on backend not on main. Do not deploy or merge alone; review together with one-click backend. |
| [#110](https://github.com/tonsamith128/Barama-Real-Estate/pull/110) | Draft admin-intent queue, controller endpoints, worker status and provisioning | **INTEGRATION INPUT ONLY**. Its base is the draft branch of [#109](https://github.com/tonsamith128/Barama-Real-Estate/pull/109), not \`main\`; must reconcile against current \`main\` and audited live source. |
| [#109](https://github.com/tonsamith128/Barama-Real-Estate/pull/109) | Draft trust/worker/recovery design | **REVIEW INPUT ONLY**, not a standalone release. |
| [#108](https://github.com/tonsamith128/Barama-Real-Estate/pull/108), [#107](https://github.com/tonsamith128/Barama-Real-Estate/pull/107), [#81–83](https://github.com/tonsamith128/Barama-Real-Estate/pulls), older #62–69 | Earlier proposals, many stacked on other drafts | **HISTORICAL/SUPERSEDED proposals** for selection, not release trains to merge in order. No automatic version increments. |
| One canonical new implementation PR | Branch from **current main**, cherry-pick/adapt only reviewed needed parts of #109/#110/#113 | **NEXT IMPLEMENTATION TARGET**. Must show one coherent end-to-end change and CI/acceptance evidence. |

**Policy:** No feature branch may simply copy old source over newer working code. Compare the currently deployed site's file SHA, latest \`main\`, and exact intended patch; stop on drift. No mass merges of stacked draft PRs. No claiming WIP branches are live.

## 4. Critical path — ONLY this sequence

### Gate A — Freeze scope and accepted baseline (done by this record)

- Recognize already fixed theme and R2 public-key trust; no further key rotations, theme rewrites or generic aaPanel/MySQL audits without new evidence.
- One plan, one canonical implementation branch from \`main\`, one current-status table and one checklist. Superseded proposals are explicitly non-authoritative.
- Track **application** release number separately from aaPanel patch-package, updater-module, and documentation versions.

### Gate B — Entitlement correctness (partially done, **policy decision remains**)

- Evidence already identifies Production as **tester** with a missing grant. This response is currently **correct fail-closed behavior**, not a verified code bug.
- Owner decides whether the Production tester activation is intended to receive stable releases, for what period. If yes, prepare separate exact activation #8 / license #7 / domain / installation / channel grant with explicit validity, approval, backup and audit; verify allowed and denied neighboring identities. If no, leave it denied and fix any misleading UI wording only.
- Stage remains its own activation #19. Neither entitlement proves audience for a signed ZIP.

### Gate C — Build one integrated admin → queue → executor → UI (NOT done)

- Reconcile #109/#110/#113 against current \`main\`. The active unified view must use its matching endpoint and status script, not the obsolete \`system-center.php\` UI.
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
- Only then install and re-verify true application version. **Never change \`VERSION\` alone, copy Staging state, or treat public-key trust as an installation grant.**

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

1. Read this document, current \`main\` commit, relevant open PRs, and the latest recorded acceptance outcome **once**.
2. State **one** current blocking gate (B, C, D, E or F) and **one measurable change** to make in this turn. Prefer shipping code/test/docs over another generic preflight.
3. Work in a focused PR from current \`main\`; inspect live source/hash only for the exact files affected. No competing architectures or version bumps unrelated to application releases.
4. Prove the changed behavior with deterministic tests; record concrete evidence here. Merge only after review and appropriate CI; never silently overwrite already accepted work.
5. Don't expand scope while a prior stage has no real end-to-end proof. If safety gate fails, leave live installations unchanged and fix that precise gate.

## 7. Go / no-go checkpoint

**Continue**, but with this bounded plan. The immediate success checkpoint is **one real authorized staging installation and recovery demonstration**, not another themed UI or signing audit. If the team cannot integrate a safe executor or cannot prove recovery under supported hosting, explicitly mark the project **PAUSED** and ship it only as "signed release discovery"—never pretend it is a completed Auto Updater.

**Current next engineering action:** reconcile the functional core in #110 with the active unified UI in #113 into a **new single branch based on current main**, while independently deciding whether Production's missing *tester* entitlement is intended policy or requires an owner-approved exact grant. Do not deploy either until the contract tests and acceptance gates pass.
