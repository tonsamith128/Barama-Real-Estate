# Barama One-click Auto Updater — staging acceptance and Main merge gates
Date: 2026-10-21
Scope: Barama Real Estate **staging** `baramastaging.tonsamith.com` only.

## Confirmed from operator-run aaPanel evidence

- Owner-triggered, publisher-authenticated, signed release `barama-2026-0-9-23-receiver-racefix` installed through the independent root worker: `0.9.22-dev` → `0.9.23`.
- Completed exact worker job: `job-bbd7f9c92166cf5cbca9c6e9fe1fbeb3`. Worker returned to **stopped/disabled** and the short-lived readiness marker was removed.
- Recovery receipt, journal binding, release ZIP SHA256, SQL dump SHA256, two original-file `.bin` backups, and both installed file hashes independently verified.
- Read-only bridge confirmed database version `0.9.23`, install metadata `0.9.23`, and **zero** pending migrations. Bridge does not promise an `installed_version` key; verifier v1.0.1 correctly treats it as optional.
- 24 isolated recovery / version-contract tests passed.
- Independent disposable socket-only MariaDB import of exact SQL backup passed: **61 tables, 2 triggers**; disposable instance removed.
- Combined recovery rehearsal passed: reconstructs both original update-target files using pinned worker rollback, version `0.9.22-dev`; imports SQL in isolated MariaDB with 61 tables and 2 triggers. **Application HTTP boot of a recovered clone not tested.**
- Dashboard auto-refresh v1.0.0 deployed with exact-hash file checks and root-private rollback backup; `v0.9.23` remained unchanged, staging HTTPS returned HTTP 200, worker still disabled, no update job submitted.
- Staging screenshot shows correct `v0.9.23`, trusted worker offline, and disabled `Update Now`. `Installation: completed` describes the prior session job, **not** a new active installation.

## CI and branch boundaries

- Integration branch: `feature/one-click-updater-integration-20261020`.
- PR #110 currently targets draft trust branch `feature/one-click-updater-secure-trust-plan-20261020`, not Main.
- PR #109 is also draft and targets Main. Both are a staged dependency chain.
- At commit `ee86ab4b3fa3cffee8537466442c87869fe4adbd`, **Quality**, **One-click Updater Integration**, and **Protected Publisher Trust** workflows all passed.
- Comparing `main` against the integration branch at this point: **51 commits, 18 changed paths** (not a dashboard-only fix). Recheck before any merge.
- The privileged installation worker lives outside the application repository as a root-controlled deployment component; a GitHub code merge does not provision or activate it.

## Release and Main merge gates (NOT yet certified)

1. **Target decision:** Explicitly choose whether Main should contain staging-gated WIP code or only a customer-release-ready updater. Do not silently turn staging-only code into public installer functionality.
2. **Trust/release targeting:** Verify independent, fail-closed release-specific authorization for discovery **and** artifact download, including denial for every non-target installation; no re-publication or entitlement widening without owner approval.
3. **Customer installation design:** Define supported environments, worker provisioning and least privilege, auth/CSRF, runtime configuration, version/migration contracts, queue retention, retry/replay protection, recovery and rollback policies, and upgrading from existing installs. Current `SITE` guards are staging-specific; no claim of general deployment readiness.
4. **Production impact:** Confirm what deployment is triggered by merging Main; keep existing production update routes disabled until product-ready code is separately accepted.
5. **Evidence & CI:** Re-run full CI on the actual proposed merge head/base; review source/diff for all 18 paths, new scripts, secrets boundaries, and migrations. Address reviews, branch protection, and test gaps. Preserve staging evidence and root-private recovery artifacts.
6. **Optional enhanced disaster-recovery certification:** Full isolated application boot against a disposable DB has not been tested; do not describe combined file/SQL recovery as an end-to-end application boot.

## Current decision

**STAGING_FUNCTIONAL_ACCEPTANCE=PASS** for the specific verified v0.9.23 signed update and recovery rehearsals.

**CUSTOMER_GENERAL_AUTO_UPDATER=NOT_CERTIFIED**; **MAIN_MERGE_READINESS=HOLD_PENDING_SCOPE_AND_SECURITY_GATES**.

Until approved, keep the trusted root worker stopped/disabled, do not submit another job, do not change staging/production database, and do not publish an additional release. This file records evidence and outstanding gates; it grants no deployment or publication approval.
