# Barama signed Demo Content — Policy Center snapshot v1.1.4

**Status:** source implementation in PR #137; no production deployment or publisher upload.

## Fixed for future installs

- Signed demo image bytes are written under `application/storage/uploads/private/demo/<sha>.<ext>`. `media_assets.path` now stores `private/demo/<sha>.<ext>`, the relative path expected by `PublicMediaController`.
- The actual verified image byte length is persisted as `size_bytes`, preventing stale source metadata discrepancies such as staging media #59.
- Author-only `tools/demo-export-source-v100.php` now exports a bounded, public-only `policy_center` object into `content.json`. This means policy data is inside the **same ZIP checksum** covered by the publisher's Ed25519 signature; policy files are **not** downloaded from an uncontrolled URL.
- Public snapshot includes policy display fields, published/public status, referenced public categories, and center/per-policy FAQs. It **excludes** private/unpublished policies, private-only categories, feedback, compliance notes, reviewers, version history and other operator information.
- Native archive inspector rejects invalid/malformed, oversized, unsafe HTML, duplicate or unknown policy content; legacy signed ZIPs without this optional component remain readable.
- Native installer writes the Policy Center store at `BASE_PATH/storage/policy-center/data.json` with no-clobber atomic linking, only on an otherwise fresh policy store. It is linked to the demo import transaction's failure cleanup; existing customer policies are never overwritten. Author URLs are rewritten to the recipient domain.
- New isolated policy test and expanded native MySQL fixture cover public filtering, malicious content, no overwrite, rollback, imported image lengths, and recipient URL rewriting.

## Release and acceptance restrictions

1. Existing signed package `DCP-20261024-0F8516E6` (v0.9.25) **does not** contain a Policy Center snapshot. The staging-only copy of 20 policies was made separately. No historical ZIP can be modified without invalidating its signature.
2. Author must review the 20 public policies, especially operator identity, jurisdiction, liability, contact, privacy and payment language, before including them in a customer-distributed demo. They must not be presented as a customer's binding legal statements without adaptation.
3. Only prepare a **new** manifest release version after source-review approval and clean CI, then upload as **Draft** for publisher signing and verify on a **fresh isolated customer installation**. Do not run the importer twice on the already populated staging site.
4. Production remains untouched. `PR #137` and `PR #138` must not merge unless their required CI and verification gates pass. PR #137 includes the source media path fix also covered by #138; avoid duplicated merges.

## Staging acceptance evidence

- Signed v0.9.25: 5,255 marketplace records, 53 images, 73 media attachments, database backup confirmed by importer.
- Post-import fix: all 53 physical SHA-256 values match, 53 media DB paths repaired, subsequent check `DB_PATHS_NEED_REPAIR=0`.
- One media size discrepancy in imported source data: ID 59, DB 613593 vs 613703 actual bytes; this remains unmodified in staging. Future imports derive size from verified bytes.
- Staging policy restoration: 20 public policies in root-level `storage/policy-center/data.json`, PHP-FPM read test PASS and HTTP status /policies 200. Independently confirm the browser shows 20 rather than relying on an HTTP 200 response alone.
