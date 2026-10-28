# Barama Demo v0.9.26 — Verified release handoff & no-repeat ledger

**Updated:** 2026-10-28 (Asia/Phnom_Penh) · **Owner:** Ton Samith · **Tracking:** PR #140 · **Status:** BLOCKED FOR CUSTOMER INSTALLATION

This document is the durable project record. **Do not repeat completed discovery, export, image hashing or policy keyword-scanning steps unless the underlying source checksums change.** Do not interpret structural ZIP QA as signature verification or legal approval.

## Source-of-truth boundaries

- **Official populated Live Demo**: https://baramarealestate.tonsamith.com, aaPanel `/www/wwwroot/baramarealestate.tonsamith.com` (app 0.9.25 at last owner check). **Never substitute** `baramastaging.tonsamith.com` — that is QA, not commercial demo source.
- **Publisher**: https://tonsamith.com/dashboard/demo-content. Preserve signed/published v0.9.25 package **DCP-20261024-0F8516E6** (package #6).
- **Customer test**: use a genuinely fresh isolated license-bound installation. `salabandet.com` is the existing cPanel QA/customer context; it must not be treated as an authorized write target before verification.
- **GitHub**: private `tonsamith128/Barama-Real-Estate`, customer-install PR #140 branch `fix/customer-signed-demo-release-gate-v115-20261025`; remain unmerged until acceptance.

## Completed checkpoints (owner aaPanel evidence)

| Milestone | Verified data |
|---|---|
| Author read-only exporter | Private toolkit `Barama-Demo-Content-v0.9.26-LiveDemo-Source-v1.0.2`; SHA + tests + PHP syntax passed |
| Public policy export | `/root/Barama-Demo-Author-Release-0926/public-policies.json`; **20 public policies, seven categories**, private feedback/versions excluded, 0600/48,055 bytes |
| Public policy JSON SHA256 | `3a01f0b6d83672895c22f482d3ca81740be232863cc5cd07dfdec99a29664021` |
| v0.9.25 source ZIP SHA256 | `1b80c017ec2eb194eb293cbd01482e488d1f8545cd2a8e66a2df0211af895c1e`; owner matched the publisher catalog field, but the legacy detached Ed25519 signature was **not independently verified** in logs |
| v0.9.26 unsigned draft | `/root/Barama-Demo-Author-Release-0926/Barama-Demo-Content-v0.9.26-UNSIGNED-DRAFT.zip`; mode 0600; native ZIP inspector PASS; unsigned |
| v0.9.26 draft SHA256 | `250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5` |
| Demo content | **5,255 marketplace records, 53 content-addressed images, 73 media attachments, 20 public source policies; app compatibility 0.9.25** |
| Image verification | 53/53 image content digests pass; separate rights/recognizable-person/property review **53/53 pending** |
| Rights CSV | `/root/Barama-Demo-Author-Release-0926/MEDIA-RIGHTS-INVENTORY-v0.9.26.csv` |
| Policy-context report | `/root/Barama-Demo-Author-Release-0926/LEGAL-CONTEXT-REVIEW-v0.9.26.txt`; source JSON independently reviewed after upload |

**No full JSON with author/private data, image binaries, secrets, or signing private keys are committed here.** Original Live Demo, previously signed v0.9.25 and customer sites were unchanged during author preparation.

## Confirmed legal issues — all 20 policies are demo TEMPLATES

Every source policy has `status=published`, `visibility=public` and begins with **Administrator review required before publication**; this describes editable author demo content, not accepted legal terms for the customer. Author dates (`2026-10-05`) are NOT the buyer's legal effective dates.

Specific source-text issues needing actual owner/legal decisions:

- **Terms** §7–8: liability, jurisdiction and operator contact.
- **Privacy/Cookies**: actual data processing, providers, rights contacts, international transfers, retention, analytics and consent configuration.
- **Fees/Refunds** §5: explicit `Insert the operator's actual refund rules` instruction.
- **Complaints/Disputes** §5: explicit instruction to insert formal governing-law, court/venue and other dispute terms.
- **Retention** §2: actual retention periods absent.
- **IP, Third Party, Accessibility**: contact, rights, service/vendor and accessibility assertions must fit each buyer.

The owner-only file `Barama-v0.9.26-Policy-Review-and-Private-Templates-v1.0.0.zip` is **review-only** and deliberately **non-importable**. Never upload it to the publisher or overwrite the live Policy Center.

## Proposed customer-template safety fix (PR #140)

- Keep strict `DemoPolicySnapshotV1::validate()` accepting only publisher-signed **published/public source** policies. Never mutate or reinterpret the signed ZIP before signature, manifest, entitlement and compatibility checks.
- On `DemoPolicySnapshotV1::installFresh()`, create **recipient-side** private drafts with original editable text, cleared effective date and featured flag, zero initial approval, `imported_demo_template=true`, `demo_template_approved=false`, and compliance review required. The no-clobber fresh-file and rollback contract remains mandatory.
- In `PolicyCenterRepository`, never expose an imported policy on public routes unless positively approved and compliant, even if a status field says published. Explicit admin `save_mode=publish` + `visibility=public` + complete reviewer/checklist + removal of draft editorial instructions are mandatory; ignore form attempts to disable the template gate. Restore/duplicate revert to private unapproved drafts.
- Admin builder shows a clear imported-template warning. This is a technical review flow, **not** legal approval.
- New negative tests: old source unchanged, fresh store private, no status/visibility field bypass, placeholder text blocks publish, explicit compliant publish, restore/duplicate remain private, existing store never overwritten. Maintain earlier `tests/demo_policy_snapshot_v114.php` behavior.

## Strict next-step ledger — only pending work

1. **CI and source QA:** Run `php -l` for patched files; `php tests/demo_policy_snapshot_v114.php` and `php tests/demo_policy_customer_templates_v116.php` in a PHP environment with mbstring+dom, plus existing CI. The assistant's local PHP runtime lacks mbstring/dom; only the read/recipient snapshot portions have been exercised locally. **Do not claim full test pass until CI or aaPanel evidence arrives.**
2. **Reviewer approval:** Complete confidential per-policy operator/legal review and separate per-image redistribution/person/property review; some images reportedly AI-generated, which does not by itself resolve third-party rights.
3. **Live content parity:** Before commercial release, compare the existing 5,255/53/73 snapshot with the **current official Live Demo**, and re-export from that site if it has changed. Do NOT replace with QA staging data.
4. **New publisher DRAFT:** Only after approvals and QA, submit newly rebuilt/verified v0.9.26 ZIP to TonSamith.com's **unpublished Draft** signing process; validate exact ZIP SHA, detached Ed25519 signature and trusted key, active domain-bound entitlement, and compatible app version. Do not mutate v0.9.25 package #6.
5. **Fresh installation acceptance:** Verify manual/auto import, installed record/media counts, 20 policies **private/admin only**, independent domain-bound license, empty recipient content, pre-install verified backup, atomic rollback and no residual media on failure; repeat installation denied.
6. **Release gate:** Review CI + legal/rights + signed-record + customer test evidence and only then merge PR #140 and consider explicitly authorized cPanel rollout.

## Stop conditions and non-repetition

**STOP** on wrong source domain, policy store present (even empty), missing class, unsigned/unmatched ZIP, invalid entitlement, failed structural validator, missing legal/image approval, unexpected records, missing backup, or incomplete rollback. Never bypass these conditions with a filesystem flag or force-upload.

Avoid repeated questions/commands for already verified hashes, archive locations or 20-policy scans. When progressing, report **only newly verified evidence**, outstanding blockers and the next single safe action.

**Audit note:** The signed policy data is public author demonstration content, not customer authorization; signature validation is separate from recipient publication approval.