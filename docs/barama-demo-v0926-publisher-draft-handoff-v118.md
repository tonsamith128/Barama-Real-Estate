# Barama Demo v0.9.26 — Publisher-signed Draft handoff v1.1.9

**Current status:** Official **publisher-signed Draft** independently signature-verified; NOT published or customer-installed. Keep PR #140 unmerged until fresh customer acceptance. Older statements below describe the historical preparation state. Do not repeat existing checks.

## Publisher Draft signature — VERIFIED 2026-10-28 (LATEST STATUS)

The owner supplied a screenshot from `https://tonsamith.com/dashboard/demo-content/packages/7` confirming the actual publisher **Draft**, and the assistant independently verified the detached Ed25519 signature against the owner-reviewed ZIP's SHA256 digest and Barama's previously pinned trusted public-key fingerprint.

| Field | Verification |
|---|---|
| Publisher package database ID | **7** (observed screenshot URL) |
| DCP release ID | **DCP-20261028-BB4A3B29** |
| Display name | Official Barama Real Estate Live Demo |
| Version | **0.9.26** |
| Demo slug | `live` |
| Status | **Draft** — NOT published |
| Downloads at screenshot | **0** |
| Package size shown | **33.70 MB** |
| Exact ZIP SHA256 | `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e` — matches original locally retained ZIP |
| Key ID | `be53fa7e3e531da5a7a21e9f` — matches trusted fingerprint prefix |
| Pinned public key SHA256 | `be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287` — matches `DemoPublisherApiV1::PINNED_PUBLIC_KEY_SHA256` |
| Cryptographic signature | **Ed25519 VERIFIED** over the **raw 32-byte SHA256 digest** (not hex text, ZIP contents or manifest text) using screenshot public key and signature |
| GitHub acceptance CI (current pre-checkpoint commit) | **14 success, one configured skip, zero failure**, PR #140 OPEN/mergeable-clean |

This was independently checked using a local copy of the **exact** ZIP (hash calculated again) with `cryptography.hazmat.primitives.asymmetric.ed25519.Ed25519PublicKey.verify()`; the public key base64 hash matched the prior repo trust anchor. This establishes authenticity of the **screenshot-supplied** signature for the locally retained bytes; it does **not** by itself independently query publisher's live database/catalog, prove customer entitlement or authorize publication.

Public verification material (not secret):
- Public key (base64): `Snx8bURtBMx9bBilso+SUZAAA8I7elfLOHT7R00XkMI=`
- Detached signature (base64): `19/TDsxg9ezr/ehqA2szP8dS57CeRgybV3LaFjH5Ub9xWUxceiYPfi0Z3/plb67HzdtWbEHNnGAu0d97eBOlCA==`
- Signed SHA256 hex: `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`

**No repeat signing or ZIP rebuild is required for this exact immutable candidate.** Do NOT click `Publish` yet: Draft approval is not customer install acceptance. Do NOT click `Move to Draft` (already Draft) or `Disable Downloads` without a documented purpose. Keep existing v0.9.25 publisher record intact.

### Next: two isolated customer QA installations

1. Confirm two disposable clean, licensed test origins (manual and auto) with empty fresh DBs and absent `storage/policy-center/data.json`; do not reuse production or populated staging.
2. Check whether the publisher offers **test-only entitled access to a Draft**. Current importer requires an independently verified **published** catalog record before installation; **do not bypass** this gate, fabricate a published response, or globally publish without explicit owner authorization and a controlled QA plan.
3. Prepare backups, sign-in/license test fixtures and rollback drills. After the release is explicitly authorized for entitled test distribution, verify exact DCP ID/key/SHA, 5,255 records, 53 images, 73 attachments, 20 private customer legal drafts, and manual/auto import plus injected-failure cleanup.
4. Only promote to production/commercial publication after owner approval of rights/legal declarations and actual isolated customer acceptance. Retain PR #140 unmerged pending acceptance.

---

## Why the candidate was revised

Owner statement dated 2026-10-28: **all** original Live Demo records and contact details are fictional; usernames/passwords/addresses and properties are sample testing data; previously confirmed 53 images ChatGPT-generated.

To eliminate accidental real-world contact or misleading representation, the prepared **NEW** unsigned candidate changes only (a) `manifest.description`; (b) the homepage hero eyebrow/description to disclose fictional sample listings and AI visuals; (c) the one non-reserved company email and phone to `demo@example.com` and `+1 202-555-0148`; (d) `review.json` to record owner statements, explicitly noting that detached publisher signature is absent and third-party rights have not been independently verified.

**Input original immutable source:** `Barama-Demo-Content-v0.9.26-UNSIGNED-DRAFT.zip` SHA256 `250c918ff1e17681ac9a18e0d64e98473f907de45be9488133f95b060c11b5c5`.

**New INNER candidate to submit as publisher DRAFT:**
- Filename: `Barama-Demo-Content-v0.9.26-OWNER-REVIEWED-UNSIGNED.zip`
- SHA256: `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`
- Exact bytes: **35,336,780**
- Native ZIP entries: **56** (53 original unchanged image bytes + `manifest.json`, `content.json`, `review.json`)
- Declared version: **0.9.26**; compatible app **0.9.25 only**
- Counts: **5,255 marketplace records, 53 byte-for-byte original images, 73 attachments, 20 unmodified source legal policies** in seven categories
- Source policy statuses are still `published/public`; customer importer MUST use PR #140's `draft/private` conversion.

**Proof produced locally:** ZIP CRC pass; file-by-file 53 image byte parity with pinned original; all 53 media SHA and byte lengths verify; all source policy objects and non-contact marketplace data rows unchanged; 20 policies, 73 media attachments and compatibility preserved. The original ZIP is retained unchanged.

## Deliverable and review registers

Prepared **owner handoff package** `Barama-v0.9.26-Publisher-Draft-Handoff-v1.0.0.zip` SHA256 `d32ba4a6ddaca7c7cc2e2a4d0faaf1338d530b7d723c7206eb3db12eed6c1d1a`, containing:
1. **Inner** revised unsigned native publisher ZIP (exact pinned SHA above).
2. `OWNER-DEMO-DATA-DECLARATION.md` (fictional-data owner statement; not digitally signed).
3. `LICENSE-ILLUSTRATIVE-IMAGES-OWNER-DRAFT.md` (rights-holder adoption draft; not legal warranty).
4. `53-ASSET-OWNER-EVIDENCE-REGISTER.csv` (53 exact hashes, owner statement, contact-sheet screen, explicit evidence limits).
5. Original rights CSV unchanged, legal-template notice, and machine-readable release validation.

The original rights CSV marked every item PENDING. **Do not falsify these fields**; owner attestation and visual screening are distinguished from independent third-party clearance. All 53 image bytes verified, but no original image-generation logs or third-party releases were supplied.

## Exact next protected steps

1. On owner-operated publisher `https://tonsamith.com/dashboard/demo-content`, upload **only the inner unsigned ZIP** as **unpublished Draft** after owner adoption of license terms. The outer handoff ZIP is **NOT** a native demo and must not be uploaded as the publisher payload.
2. Verify actual returned DCP ID, detached publisher Ed25519 signature against official pinned key, SHA256 exact match to **`b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`**, app compatibility and trusted catalog metadata. Never invent a DCP ID or claim signing occurred.
3. After separate authorization for an installable catalog record, execute **two fresh isolated licensed customer installs** (manual + auto) with empty MySQL and no policy store. Verify backups/rollback, 53 media images, 73 attachments, 5,255 records, 20 draft/private customer policy templates, denial of unsigned/wrong-domain/revoked/old ZIP.
4. Only then consider PR #140 merge and commercial publication. The existing signed v0.9.25 DCP remains unchanged.

**Limitations:** The assistant does not have owner-host or publisher signing authority in the current chat. No actual publisher upload, detached signature, Main merge or customer-site write occurred. Never treat CI/mock fixtures as a genuine installed customer acceptance.
