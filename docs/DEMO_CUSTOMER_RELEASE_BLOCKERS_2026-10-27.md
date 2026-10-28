# Demo Importer customer-domain release gate — 2026-10-27

Status: **BLOCKED for customer installs**. Manual ZIP verification and licensed Auto Download are working, but staging the signed archive is not permission to change customer data.

## Current Main (Barama 0.9.25)

- Native installer source merged through PR #137, but `DemoImporterController::RELEASE_GATE` remains `download_only_staging_pending`.
- The only previous permitted install used the approved `baramastaging.tonsamith.com` root-owned staging acceptance marker. **Do not copy or create this marker on customer sites.**
- Previously signed publisher demo ZIP v0.9.25 (DCP-20261024-0F8516E6) contains 5,255 marketplace records and 53 image records, but **0 Policy Center public policies**.
- Legacy ZIP declares `compatible_app_versions: ["0.9.24"]`, not the currently installed app `0.9.25`.
- Editing the signed ZIP, changing its version or bypassing signature/entitlement/backup gates is forbidden.

## Customer release gate candidate

PR #140 includes a fail-closed `DemoCustomerReleasePolicyV1`. Its CI tests pass, but **do not merge or deploy solely based on CI**. Author release evidence and acceptance are outstanding.

Before customer installation can be enabled:
1. Assemble a *new unsigned* complete demo candidate v0.9.26+ with **20 approved public Policy Center records**, consistent `compatible_app_versions` including the exact customer-app version, valid media and marketplace records.
2. Clear rights and redistribution for all 53 demo images and public policy content. Keep author-only and private records out.
3. Submit the new candidate as Draft to TonSamith.com Demo Content publisher for independent Ed25519 signing; review receipt, package digest and entitlement, then publish only with author approval.
4. On a new isolated licensed customer-style domain and empty database, verify both Manual Upload and Auto Download of the *same* signed ZIP. Check backup, install and rollback, 5,255 marketplace records, 53 images, 20 public policies, frontend URLs and repeat-import denial.
5. Only then review/merge PR #140, rerun full exact-package CI and deploy an exact-file guarded customer patch with rollback.

Production and customer databases must not be used to rehearse a first import. Verify publisher SHA, signing identity and domain-bound entitlement immediately before any import, never from a UI flag alone.

Related: https://github.com/tonsamith128/Barama-Real-Estate/pull/140
