# Barama Demo v0.9.26 — Revised Owner-Reviewed Unsigned Publisher Draft v1.1.8

**Status:** COMPLETE for offline candidate preparation and content QA; **NOT** publisher-signed, DCP-created, production-published, or customer-installed. Keep PR #140 unmerged until signed customer acceptance. **Do not repeat old v1.1.7 preflight on the old ZIP.**

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
