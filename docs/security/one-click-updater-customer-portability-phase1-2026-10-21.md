# Barama One-click Auto Updater — Customer Portability Phase 1
2026-10-21 · Draft engineering contract · **No customer installation enabled**

## Starting point (verified, not customer-general)

Staging installation completed `0.9.22-dev → 0.9.23` through an independent privileged worker. Original file baselines, SQL backup provenance, disposable SQL restore (61 tables, 2 triggers), and combined file/SQL restoration passed. The temporary worker is stopped/disabled. An isolated recovered application HTTP boot has **not** been tested.

This repository contains the **unprivileged application/UI/queue/downloader side only**. The approved privileged worker is external, root-owned on the staging server. A GitHub merge cannot install a root service or configure protected signing trust for customer installations.

## Verified staging-coupled code to replace only AFTER a new security contract is proven

- `application/app/Services/Updates/OneClickUpdateQueueV1.php`: fixed SITE `/www/wwwroot/baramastaging.tonsamith.com`, fixed `STAGING_ONLY` request/worker status, root-signal schema, guarded, per-session exact-job result. It is intentionally **fail-closed for other installations**.
- `application/bin/oneclick-unprivileged-delivery.php`: fixed staging root, non-root CLI only, publisher-authenticated signed-envelope delivery; cannot be repointed by user-editable configuration.
- Root-owned external worker `worker.py`: independent fixed SITE/TRUST/VAULT/BRIDGE/DOWNLOAD binary paths, delivery blob pin, protected root-owned status files, extra checks. Do not change its constants from PHP.
- `TrustedReleaseFeed::check`: independent signed manifest verification with installation/license binding; **license eligibility is not a release-specific deployment allowlist**.
- The website must not treat an arbitrary web-writable queue file, a UI click, or a fetched ZIP as privileged update authorization.

## Required portable deployment profile (DESIGN, NOT APPROVED IMPLEMENTATION)

1. **Supported environment:** First support an explicitly provisioned managed VPS/aaPanel installation with a dedicated root-controlled worker service. Do not promise automated root provisioning on generic shared hosting. Preserve a guarded/manual update path when root installation is unavailable.
2. **Per-installation identity:** The worker independently pins canonical site root, deployment ID, application domain, installation ID, product slug, public trust chain, immutable source digests, and dedicated local queue/result paths. Never accept a site-root or executable path supplied by the web request or mutable customer config without an independent root-controlled installation binding.
3. **Provisioning:** Separate owner-authorized installation with minimal rights, pinned package integrity, exact root-owned trust directory/file permissions, service confinement, symlink/ancestor checks, installer cleanup, and inspectable rollback. A web request must never start, install, reconfigure, or execute a root worker.
4. **Release-specific authorization:** For **both discovery and artifact delivery**, require authoritative publisher release targeting to this exact activation/install/domain/channel/version and package SHA; independently verify Ed25519 signature and HTTPS artifact digest. Deny signed-but-out-of-scope releases. Require negative tests against another entitled customer install. No broadened publication or release without separate owner approval.
5. **Job and recovery correctness:** Retain server-side administrator role/CSRF gates, nonce-backed authenticated audit, single pending request, replay/expiry rejection, exact-job status, normalized bounded progress, rollback journal, file baseline hash proof, database backup verification, incompatible migration stop, and manual review for uncertain recovery. A past `completed` must not authorize a new request.
6. **UX:** Progress messages may use only allowlisted worker states; do not expose DB credentials, protected paths, raw exceptions, SQL, tokens, or private signatures. JS may display status and reload server-rendered controls, but may never enable or submit `Update Now`.

## Phase 1 implementation

- Read-only browser messages for all ten allowlisted states, including explicit `recovery_review_required`, on the draft updater branch.
- 21 Node VM regression tests to verify read-only authenticated same-origin polling, typed state validation, no button bypass, and readiness-triggered server refresh.
- CI checks that the current staging-only queue and downloader guards remain present. Generalization must be an explicit code review with its own negative tests, never a silent path substitution.

## Acceptance gates before enabling customer installations

- [ ] Root-controlled portable deployment identity/provisioning and uninstall/recovery package designed, reviewed, and tested in a **different disposable customer-like site**.
- [ ] Independent publisher release-specific allowlist at discovery **and** authenticated artifact endpoint, with non-target denial tests.
- [ ] No user-editable or web-writable file can impersonate worker readiness, signed release authorization, or installation result.
- [ ] Supported PHP, MariaDB, filesystem and privilege matrices established; shared hosting explicitly classified.
- [ ] Disposable per-install end-to-end signed update, recovery and crash tests pass without touching staging/production.
- [ ] Real recovered application startup test under safe isolated DB/runtime, if full DR certification is claimed.
- [ ] Branch dependency PR #109, customer security model, release packaging, and CI against the **actual proposed Main diff** reviewed and approved.

**Current declaration:** `STAGING_SIGNED_UPDATE=VERIFIED`; `CUSTOMER_PORTABLE_WORKER=NOT_IMPLEMENTED`; `CUSTOMER_AUTO_INSTALL=DISABLED`; `MAIN_MERGE=HOLD`. Documentation and UI changes do not authorize a root worker deployment or publisher release.
