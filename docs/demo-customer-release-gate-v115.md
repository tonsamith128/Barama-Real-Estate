# Customer-domain signed Demo Importer — release gate v1.1.5

**Status:** Pre-release customer safety implementation (PR #140). Do not deploy to production or publish a CodeCanyon ZIP before the acceptance below.

## Actual previously observed blockers

- The earlier importer accepted installations only for `baramastaging.tonsamith.com` with a root-owned staging acceptance marker. Genuine licensed customer domains could not install anything.
- DCP `DCP-20261024-0F8516E6`, signed Demo ZIP v0.9.25, contains 5,255 marketplace records and 53 unique images but no `policy_center` snapshot. It declares `compatible_app_versions: ["0.9.24"]` while the candidate app is 0.9.25.
- All 53 demo images were valid after staged import path repair; one stale `media_assets.size_bytes` field in the old archive differed from the physical bytes. New importer derives physical image size from digest-verified bytes.

## Corrected customer behavior

Manual Import and Auto Import both require a bound active license with server-side publisher catalog authorization, exact package SHA-256 and independently pinned Ed25519 signature.

The installer accepts a candidate **only after**:
1. A published publisher-catalog record still exists for the exact staged ZIP and its ID, digest, signature, signing key and version are unchanged. Fresh publisher entitlement discovery occurs immediately before install.
2. The publisher-signed manifest declares a version **0.9.26 or later** and explicitly lists the exact installed app version (for the current candidate, `0.9.25`).
3. `content.json.policy_center` has exactly 20 validated *published, public* policies, plus positive marketplace record and media counts.
4. Admin authentication, Change Confirmation and the exact `IMPORT LIVE DEMO` typed confirmation pass.
5. Marketplace content tables are empty; a real pre-import backup completes; transactional insert, per-file SHA-256, MIME validation and no-clobber Policy Center write pass.

Neither the UI flag nor a local writable marker can override these requirements. Legacy signed v0.9.25 ZIP may be reviewed, but **cannot be installed**.

## Publisher-signed demo production workflow

The author's preparation handoff reads the existing approved v0.9.25 signed ZIP and the **actual author-owned** Policy Center JSON. It creates a *new unsigned* ZIP v0.9.26, updates compatibility to app 0.9.25, fixes obsolete review metadata and one size field, and exports exactly 20 public policy records via strict allowlists. It does **not** sign, publish, write the database, or copy private feedback.

Only the independently trusted TonSamith.com Demo Content publisher may create the new detached Ed25519 signature. Upload the new v0.9.26 ZIP as **Draft**, review the resulting DCP metadata, and publish **only after explicit author approval**, legal policy review and redistribution clearance.

## Mandatory external acceptance

1. Verify publisher SHA-256, signed Ed25519 identity, and 0.9.26 catalog availability for a new activated license; legacy/revoked/wrong-domain variants denied.
2. Use **a new isolated customer-style domain and empty MySQL database**, not the populated staging site or production.
3. Test manual ZIP upload and licensed Auto Import separately against the **same published** archive.
4. Inspect backup receipt before writes, 5,255 record inserts, 53 working local images, all 73 attachments, 20 policy pages and the homepage/marketplace/detail/gallery routes.
5. Test interrupted installs, rollback and repeat import (must fail safely without customer data overwrite).
6. Confirm no customer-legal terms or author-sensitive identifiers inadvertently remain in public imported policies.
7. Record evidence of redistribution rights and model/property releases where necessary for **all 53 demo photographs**, plus rights for shipped application imagery. Lack of proof is a blocker, not a test pass.
8. Run exact-app-package MySQL install, upgrade, Apache/Nginx, security, and browser CI on the final source commit. Check CodeCanyon listing and documentation.

The app v1.0.0 (CodeCanyon final version) is **not** implicitly compatible with a manifest limited to 0.9.25. Rebuild and re-sign a compatible demo snapshot when that release is independently accepted.

## References

- Support: https://tonsamith.com/contact
- Documentation: https://docs.tonsamith.com/docs/?=228711-barama-real-estate-property-marketplace-developer-management-php-script
- Publisher: https://tonsamith.com/dashboard/demo-content
- Live Demo: https://baramarealestate.tonsamith.com
