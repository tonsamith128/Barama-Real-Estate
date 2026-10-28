# Barama Real Estate — Customer-signed Demo Import Acceptance (2026-10-28)

## Scope and release identity

- Application version on both customer-style cPanel environments: **0.9.25**. The official **demo-content** version is **0.9.26**; this is **not** a Barama PHP application upgrade to 0.9.26.
- Published release: **DCP-20261028-BB4A3B29**, publisher package #7, demo version **0.9.26**, declared compatible with app **0.9.25**.
- Exact publisher archive SHA-256: `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`. The original unsigned-author filename can be misleading: the publisher's Ed25519 signature is **detached metadata** verified against this exact ZIP hash and licensed published catalog. Do not modify or repackage that archive.
- Expected complete signed demo: **5,255** content records, **53** signed image files, **20** source policies, **7** policy categories, **3** design/config groups.
- These tests used real cPanel shared hosting and domain-bound licensing; no license bypass, public staging flag or unsigned release override was used.

## AUTO import — salabandet.com: PASSED

- Fresh installation; staged marketplace tables empty; Policy Center store absent prior to import.
- Original v0.9.25 application received the reviewed, exact-baseline **seven-file code-only release gate/policy draft safety overlay**, with source checksum guards and private rollback.
- License/catalog discovery, HTTPS/public key, published release selection, download, Ed25519 verification and private staging succeeded.
- Owner clicked the explicit install action. Server success response: **"Official demo installed: 5255 content records and 53 signed images. A database backup was created before installation."**
- Public homepage and property/project/company images rendered; demonstrated **20 published listings, 12 published projects, 21 approved companies**.
- Independent customer cPanel CLI post-install result:

```text
POLICIES=20
CATEGORIES=7
UNSAFE_POLICIES=0
POLICY_SAFETY=PASS
BACKUP_ID=20261028034240-de61e1c464bbd25a
BACKUP_SHA256=VERIFIED
BACKUP_MANIFEST=PASS
BACKUP_STATUS=PASS
FINAL_ACCEPTANCE_CHECK=PASS
```

- The policy check verified every imported policy had `status=draft`, `visibility=private`, `imported_demo_template=true`, `demo_template_approved=false` and empty `effective_date`. No source policy automatically published.
- Backup SHA-256 matched its private receipt and a matching `barama-db-snapshot-v1` manifest existed. This is **not** proof of an executable restore rehearsal.

## MANUAL import — barama.salabandet.com: PASSED for signed installation and public rendering

- Separate subdomain, separate fresh database and license activation; real five-step cPanel installer concluded with all displayed System Health checks passing under PHP **8.5.12**.
- Owner uploaded and verified the separate subdomain seven-file code-only patch; post-install CLI: `SOURCE_FILES_MATCH_BASELINE=0`, `SOURCE_FILES_MATCH_TARGET=7`, `PATCH_STATUS=ALREADY_APPLIED`, `CODE_ONLY_PATCH=PASS`; no DB/license/publisher modifications. Private source rollback copies created.
- Site's top-level application/assets/documentation/installer/tools/updater directory permissions normalized to **0755**. Policy Center store was **absent** before demo import.
- Owner's Mac confirmed the exact v0.9.26 ZIP SHA-256 above and `unzip -tq` success. Manual Upload → Verify package returned Ed25519 verified, publisher catalog-bound exact release **DCP-20261028-BB4A3B29**, 5,255 records, 53 images, 20 source policies and customer release eligible.
- Owner explicitly completed the separate Manual Import install. The server's success response matched Auto: **"Official demo installed: 5255 content records and 53 signed images. A database backup was created before installation."** The staged archive cleared afterwards.
- Owner's screenshots of admin **Policy Center** show **20 Total / 20 Draft / 0 Published**, and sampled rows marked **Private**, with no effective date and compliance **Not reviewed**.
- Owner's public screenshots show the fictional-content disclaimer, restored homepage and image cards, **20 property listings, 12 projects, 21 companies**, with images loading correctly.

## Recorded acceptance boundaries

The two real shared-hosting import workflows and their signed verification, actual install and visual frontend outcomes are accepted. The production-signature and license checks MUST remain fail-closed. However:
- Manual host still requires an **independent filesystem/backup receipt verification** equivalent to the completed Auto host CLI check; the Policy Center screenshot alone cannot prove each JSON flag.
- A full **restore rehearsal**, fail/rollback injection, repeat-import denial after successful installation, and complete media attachment/filesystem counts have not yet been independently evidenced across both hosts.
- Do not claim a fully audited commercial CodeCanyon v1.0.0 release or full disaster recovery sign-off on this record alone.
- Keep the successful customer-style QA websites intact; no reimport or destructive rollback on either. Rehearse recovery on a **third disposable isolated fixture** if needed.
- The separate Media Library Upload draft PR #142 is **not part of this customer-signed Demo Import acceptance**, and must pass its own validation before inclusion.
- No customer data, passwords, activation tokens or private keys are included in this acceptance document.

## Deployment/packaging

Merge PR #140 source under normal GitHub checks after owner approval. It adds a fail-closed signed customer release policy, recipient-specific policy draft isolation, installer protection, integration tests, customer documentation and two exact-root guarded patch kits. Neither live website is changed by merging to GitHub. This record does not change `VERSION`; Barama application remains **0.9.25** and CodeCanyon v1.0.0 remains pending the independent release gates.
