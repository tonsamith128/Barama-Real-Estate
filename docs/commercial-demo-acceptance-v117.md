# Barama commercial Demo v0.9.26 — Owner release acceptance v1.1.7

**Status (2026-10-28): PUBLISHER-SIGNED DRAFT VERIFIED (ID #7 / DCP-20261028-BB4A3B29); NOT APPROVED FOR PUBLICATION OR CUSTOMER INSTALLATION.** Related: [previous pinned handoff](barama-demo-v0926-customer-release-handoff.md), [PR #140](https://github.com/tonsamith128/Barama-Real-Estate/pull/140). This is an evidence ledger and fail-closed release procedure; **do not repeat completed discovery/exports/keyword scans**.

## Publisher signed-Draft checkpoint verified — 2026-10-28

Owner screenshot: `https://tonsamith.com/dashboard/demo-content/packages/7`, package **#7 / DCP-20261028-BB4A3B29**, version 0.9.26, `Draft`, zero downloads, SHA256 `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`.

This is **stronger than a screenshot-only status check**: the assistant cryptographically verified the detached Ed25519 signature shown in the owner's screenshot over the raw SHA-256 digest of the exact locally retained ZIP, with the screenshot public key fingerprint matching the independent Barama trust pin `be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287` in `DemoPublisherApiV1`. Signature math **PASS**. The live publisher catalog response / entitlement was **not separately fetched**; the screenshot establishes Draft status, not public installation access.

Full non-repeat signature evidence and public verification values are in [publisher handoff v1.1.9](barama-demo-v0926-publisher-draft-handoff-v118.md).

**Completed and do not repeat:** splitting/download SHA parity, native ZIP CRC, 53 image-byte parity, publisher Draft creation and screenshot signature verification. **Still blocked:** actual fresh license-bound manual/auto installations and rollback, plus owner final license/legal adoption and commercial publication authorization. The importer requires a **published publisher catalog record**; do not make global live publication solely to get through QA. Establish restricted QA entitlement and access control in the publisher first, or obtain explicit owner-approved release activation after a guarded plan. PR #140 remains unmerged.

## Verified baseline — no need to redo

- Official author content origin is **https://baramarealestate.tonsamith.com**, not QA \`baramastaging.tonsamith.com\`.
- Owner's private author export: \`/root/Barama-Demo-Author-Release-0926/public-policies.json\`, SHA256 \`3a01f0b6d83672895c22f482d3ca81740be232863cc5cd07dfdec99a29664021\`; exactly 20 policies in seven categories.
- Owner's previously structurally validated **UNSIGNED** candidate: \`/root/Barama-Demo-Author-Release-0926/Barama-Demo-Content-v0.9.26-UNSIGNED-DRAFT.zip\`, SHA256 \`250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5\`; version 0.9.26, app compatibility 0.9.25, 5,255 records, 53 hash-matching images, 73 attachments, 20 policy **source** records.
- Owner's per-image rights CSV \`/root/Barama-Demo-Author-Release-0926/MEDIA-RIGHTS-INVENTORY-v0.9.26.csv\` previously had **53/53 pending redistribution and identifiable-person/property review**. Image bytes are not in GitHub; neither an inventory row nor image hash proves legal rights.
- Public author legal JSON includes 20 \`published/public\` policies with administrator-review notices. **These are not authorized customer legal documents.** Source-specific issues include incomplete refund, jurisdiction/disputes, data-retention, privacy/cookie, contact and service-provider terms. Prior per-policy review tracker exists in the private owner review kit.
- PR #140 (\`d8afa2bb7186be84be942119468ac5c74498716c\` last accepted baseline) passed Demo Importer PHP 8.2/8.4 with private/legal template tests; Foundation QA and Browser/Accessibility QA passed on Quality retry #2. **CI acceptance is not commercial permission.** Existing published publisher release \`DCP-20261024-0F8516E6\` (v0.9.25) must remain untouched.


## Verified OWNER-SERVER preflight — completed 2026-10-28 (do not repeat)

Owner pasted terminal output from the **actual aaPanel host**, not from a local synthetic fixture. The command's final line was mangled in the interactive paste, but the independent final output printed the JSON report and exit status. The verification steps completed and the private read-only preflight returned **exit code 2**, which is the intended fail-closed outcome.

| Evidence from aaPanel stdout | Verified value |
|---|---|
| Review-only kit ZIP SHA-256 and archive test | **PASS** (`sha256sum -c` OK; `unzip -tq` no errors) |
| Disposable offline fixture tests | **2/2 PASS** |
| Preflight action | `READ_ONLY` |
| Draft | Version **0.9.26**, ZIP SHA-256 `250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5` |
| Compatible app versions | **[`0.9.25`]** (not 1.0.0) |
| Policy source | SHA-256 `3a01f0b6d83672895c22f482d3ca81740be232863cc5cd07dfdec99a29664021`, **20** policies, **7** categories, **20** with editorial notices |
| Demo content | **5,255** marketplace records, **53** media records, **53** archived image files, **73** media attachments |
| Media rights inventory | **53** rows; **53 unapproved** |
| Blockers | `IMAGE_RIGHTS_REVIEW_PENDING`; `POLICIES_REQUIRE_OPERATOR_LEGAL_REVIEW` |
| Human approvals | Legal: **false**; image rights: **false** |
| Publisher signature verified | **false** |
| Ready to submit to publisher | **false** |
| Exit code | **2** — expected review hold, NOT tool failure |
| Private result path | `/root/Barama-Commercial-QA-MxyqU9tV/preflight-results.json` |

**Attestation boundary:** this is authenticated as a user-supplied aaPanel terminal transcript, not a live assistant SSH session or a formal owner signature. Do not infer counsel approval, title/property/person clearances, signing or customer installation from a checksum pass. No release, publisher, production or customer writes were requested or performed.

**Next single prerequisite:** complete and document an individual rights disposition for all 53 media records and operator/legal dispositions for all 20 policy templates. Keep the original private rights CSV and signed-source contract intact; record reviewer, date, claim of rights, specific asset hash, visual/model/property/brand review and supporting evidence. Do not flip all rows to approved in bulk, and do not insert the buyer's legal identity into an author-owned public template. Once supported human decisions are recorded, rebuild or recheck the frozen candidate (if assets/text changed), then request an explicitly authorized **publisher Draft-only** signing workflow. Real customer installation follows separately and requires a published verified catalog record plus license-bound isolated customer test.

## Original archive + CSV independently inspected — 2026-10-28 (NEW, do not repeat)

User uploaded the **actual unsigned ZIP and 53-row asset rights CSV** into the review environment. This supersedes earlier assumptions based only on owner-server stdout. Original ZIP SHA-256 matches the pinned candidate:
`250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5`.
CSV SHA-256:
`afcb5e14693f83da261d3e8029be5e5f6f83df12d13a6b25738d0c73c6ad7d5f`.

**Independent archive + image QA:** native ZIP CRC PASS; 56 entries (53 images + 3 JSON files); each of 53 image contents matches CSV SHA-256 and byte count **and** its manifest `media_assets` metadata. All 53 media IDs are referenced by 73 attachments. Source `content.json.policy_center` matches the uploaded `public-policies.json` at the data-object level (20 policies, 7 categories); all 20 are still `published/public` **inside the author archive**, each with an editorial administrator-review notice. This remains safe for a customer only if PR #140's recipient-side draft/private conversion is installed and passes fresh customer acceptance. No verified detached signature exists.

**Visual screening:** All 53 images were visually inspected in five indexed contact sheets. The sample consists of architectural render-like interiors/exteriors, landscaping, skyline and waterfront scenes. No obvious human face/person, prominent brand logo or legible sign was observed at contact-sheet resolution. This is **not** proof of ownership or absence of smaller rights issues. A manual review grouped the 53 distinct-hash files into approximately ten **similar scene families**: coastal terrace (7), tropical tiled-roof villa (8), modern glass/wood villa (10), high-rise living room (3), rooftop sunset/pool (4), commercial riverside promenade (7), compact studio interior (3), standalone sunset villa (1), lakeside mountain terrace (4), and row houses (6). Repeated scene variants must not imply unique actual properties or independent photographs.

**Rights data:** CSV claims ChatGPT generation and owner Ton Samith for 53/53, but contains **no generation provenance evidence**. `redistribution_review=PENDING`, `people_property_review=PENDING`, and blank `evidence_or_notes` for **all 53**. **0/53 formally approved**; no full-resolution third-party architectural/likeness/brand clearance performed. One PNG (1677×938), remaining 52 JPEG (1440×810); images contain no observed embedded EXIF fields, which does not establish AI provenance.

**Additional newly found commercial seed-data issue:** `content.json.tables.companies` includes one company record titled **USA Real Estate** with a non-placeholder Gmail address and international phone number. Confirm owner distribution permission or replace those details with clearly synthetic reserved examples before shipping sample data to unrelated customers. Do **not** reproduce the exact address or phone in GitHub. All 20 demo listings are marked `published`; visible disclosure must state that sample inventory and AI-generated images are illustrative, not actual real-estate offers.

**Review artifacts (owner-only, do not commit image binaries):** `Barama-v0.9.26-53-Image-Visual-and-Legal-Review-v1.0.0.zip` (ChatGPT conversation attachment), SHA-256 `54489e53d82520e49aa333c98011c9feb4a207dc389d8dbbc2fca1195ba79aba`. Contains 53-line CSV, Excel tracker, five indexed contact sheets, report and machine-readable integrity evidence; NOT a signed demo ZIP. The pinned archive, original image files, and personal contacts are **not** uploaded to GitHub.

**Disposition: STOP for signing, publisher Draft, customer installation, Main merge or public CodeCanyon sale.** Operator/legal signoff and provenance-based per-asset rights clearance remain unverified; source record contact and illustrative-listing truthfulness need review. A manual viewer's observation of no obvious people/logos does **not** change any review status. Do not mark images approved en masse or re-run integrity audits unless source hashes change.

## 2026-10-28 owner factual-data correction and revised signable draft (LATEST)

The owner has **explicitly confirmed** that all Live Demo identities, company/contact details, usernames/passwords, addresses, prices, property inventory and other content are **fictional demo testing data**; earlier owner statements identify the 53 images as ChatGPT-generated. The previous potential-real-contact-data question is **resolved as a fictional-data source classification**, not as a claim of legal identity/third-party rights clearance. Do not ask the owner to re-prove that these test personas are real people. The native signed source archive contains no account/password tables.

**Updated owner-review candidate prepared**: `Barama-Demo-Content-v0.9.26-OWNER-REVIEWED-UNSIGNED.zip`, SHA256 **`b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`**, 35,336,780 bytes. This is **NEW**, not the prior `250c918...` candidate. The original remains intact. Full handoff `Barama-v0.9.26-Publisher-Draft-Handoff-v1.0.0.zip` SHA256 `d32ba4a6ddaca7c7cc2e2a4d0faaf1338d530b7d723c7206eb3db12eed6c1d1a`.

The new native ZIP retains 56 entries, 53 byte-identical images and digests, 73 media attachments, all 20 exact source policies, 5,255 records and app 0.9.25 compatibility. Changed only: `manifest.description`, the homepage hero's notice about fictional sample data, a single company email/phone replaced with reserved `example.com` and `555` testing values, and `review.json` documenting **owner statements, not publisher or legal approval**. Native ZIP CRC, records, media SHA/byte parity and policy-object preservation pass offline.

**Do not confuse:** the new candidate is ready to be offered to the **owner-controlled publisher as an unpublished DRAFT for signature review**, but the publisher has not actually signed it. The signature has to cover the **new** SHA `b577010...`. Owner adoption of illustrative-image license and licensing decisions are documented as draft and not digitally signed; third-party rights are not independently proven. Imported policies still require PR #140's draft/private guard and customer editing before public use.

See permanent targeted guides:
- [Owner fictional-demo statement and illustrative-image license](barama-demo-v0926-owner-content-license-v118.md)
- [New exact candidate and publisher Draft-only procedure](barama-demo-v0926-publisher-draft-handoff-v118.md)

**Next protected actions:** actual publisher upload/signature; domain-bound signed release authorization; real fresh isolated Manual + Auto customer imports and rollback receipts. None is achievable by changing repository docs or unsigned ZIP metadata alone. No Main merge, public publisher publication or customer install has been done.

## Stage A — legal decisions (technical source review done, formal review still pending)

1. Resolve per-owner operator name/address and support/privacy/IP/accessibility contacts; billing/cancellation/refund implementation; governing law and dispute provisions; actual privacy disclosures, cookie consent, processors, retention and cross-border data; platform and accessibility representations.
2. Record per-policy reviewer, date, decision, and supporting evidence privately. The source date \`2026-10-05\` must not become a customer's policy effective date.
3. Recipient-side importer must keep all 20 as editable **draft/private** records with cleared effective dates, visible only to the customer admin. Later publication requires customer-specific compliance review; do not publish source author policies directly. The technical gate is in PR #140 but no legal approval is inferred.

## Stage B — all 53 media files (byte integrity previously checked; rights NOT cleared)

- Treat each original archived image hash as a separate row. Document generation/source evidence, original input rights, visual check for recognizable person/likeness, logo, copyrighted architectural design/artwork, property/privacy issues, model/property release where applicable, and whether the photograph deceptively represents an actual listing.
- Owner reports images generated by ChatGPT. OpenAI's output ownership assignment **does not guarantee uniqueness or clear other third-party rights**; review actual assets and license scope. Any unreviewed/unlicensed asset is a **STOP**. Replace doubtful images in a **new** unsigned candidate rather than silently claiming clearance.
- Draft license language for owner/legal review only: \`These sample property images are illustrative demo assets licensed for display in authorized Barama Real Estate installations; they must not be misrepresented as photographs of actually listed properties or resold as standalone stock images. Third-party rights and needed consents remain with their holders.\` Do not issue a blanket legal warranty without evidence.

## Stage C — owner-side read-only preflight

A standalone reviewed (non-installable) artifact \`Barama-Commercial-Acceptance-ReviewOnly-v1.1.7.zip\` was prepared in ChatGPT with a Python read-only ZIP/policies/rights checker and disposable synthetic tests. Extract the tool **privately**, not under a webroot. It does **not** sign/publish/install or convert \`PENDING\` to approved.

From the extracted bundle directory, run on the **author aaPanel server** (replace \`$KIT\` with its actual extracted path):

\`\`\`bash
python3 -B "$KIT/tests/demo_commercial_preflight_v117_test.py"
python3 -B "$KIT/tools/demo_commercial_preflight_v117.py" \
  --draft /root/Barama-Demo-Author-Release-0926/Barama-Demo-Content-v0.9.26-UNSIGNED-DRAFT.zip \
  --policies /root/Barama-Demo-Author-Release-0926/public-policies.json \
  --rights-csv /root/Barama-Demo-Author-Release-0926/MEDIA-RIGHTS-INVENTORY-v0.9.26.csv \
  --expected-draft-sha256 250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5 \
  --expected-policies-sha256 3a01f0b6d83672895c22f482d3ca81740be232863cc5cd07dfdec99a29664021
\`\`\`

**Exit 2 / block is the correct result while rights/legal review is pending.** Missing hashes, ZIP mismatch, missing media, unexplained legal snippets, unbound rights metadata or missing evidence are not bypassable. Review-only test success is not live image inspection or signing authority. Private owner evidence and original image bytes must never be copied into public GitHub.

## Stage D — signed publisher **Draft** (blocked pending Stages A–C and owner decision)

1. Verify no discrepancy between frozen 5,255/53/73 content and actual latest official Live Demo; reconcile or document approved scope. Rebuild a new unsigned archive with a new pinned digest if content changes.
2. Only after clear operator-specific legal disposition and all 53 rights decisions, upload the exact reviewed **unsigned** v0.9.26 ZIP to **https://tonsamith.com/dashboard/demo-content** as **Draft only** with explicit owner approval.
3. The independently trusted publisher—not ChatGPT or GitHub—must sign its ZIP SHA256 with the official Ed25519 key. Separately verify signature, key id/public-key fingerprint, catalog ID, immutable digest, version and exact compatible app list. A DRAFT catalog record is not a published customer-install entitlement.
4. Never replace/re-sign/retire existing publisher v0.9.25 entry. Never put private signing keys, license keys or live customer DB credentials in GitHub.

## Stage E — new isolated customer acceptance (blocked until published signed test release)

Two **new** disposable licensed domains with separate empty MySQL databases and **no existing Policy Center store**: one for Manual ZIP Import and one for licensed Auto Import. Do not reuse populated staging, official Live Demo or an existing customer without verifying emptiness and explicit authorization.

- Confirm app exact compatibility (current v0.9.26 manifest authorizes **0.9.25**, not automatically v1.0.0); fresh authenticated publisher catalog and domain-bound active license, immutable signed ZIP hash; admin exact typed confirmation.
- Capture an independently verified **pre-install backup receipt**; import 5,255 records / 53 byte-hashed accessible images / 73 attachments / **20 imported private drafts**; public Policy Center must not show those documents prior to individual customer approval.
- Inject a controlled failure in a disposable site, observe atomic DB/file/Policy Center rollback and no leftover uploaded media; repeat import fails without overwriting existing customer data. Test wrong-domain license, revoked entitlement, changed publisher hash, unsigned archive, missing backup and incompatible app: denied.
- Preserve redacted logs, hashes, install receipts and rollback proofs; do not claim real customer-domain acceptance from CI fixture tests.

## Release-block ledger

- **Completed:** author export, draft ZIP structural checks (owner-reported), full policy source review, 53 image integrity hashes (owner-reported), PR #140 core/private-template CI, Quality browser retry, read-only synthetic preflight test kit, **owner's private aaPanel v1.1.7 preflight (report exit code 2, legitimate review hold).**
- **Not completed:** human 20-policy authorization; **53/53** asset rights decisions; live-versus-frozen snapshot parity; authenticated publisher signature and Draft record; owner approval to publish; genuine new licensed manual+auto imports; real rollback receipts; merge approval.
- **No protected action performed:** no customer install, publisher signing/upload, production edit, published release change, or Main merge.
