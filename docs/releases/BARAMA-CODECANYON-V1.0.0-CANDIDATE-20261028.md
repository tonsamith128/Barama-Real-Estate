# Barama Real Estate — CodeCanyon v1.0.0 Release Candidate

**Prepared:** 28 October 2026  
**Source version:** `1.0.0`  
**Rights holder:** Ton Samith  
**Official domain:** https://tonsamith.com  
**Submission status:** **CANDIDATE — not yet uploaded, approved, or published on CodeCanyon**  
**Release branch:** `release/codecanyon-v1.0.0-docs-20261028`

## Identity and official resources

- Author and licensor: Ton Samith — https://tonsamith.com
- Live Demo: https://baramarealestate.tonsamith.com
- Online Manual: https://docs.tonsamith.com/docs/?=228711-barama-real-estate-property-marketplace-developer-management-php-script
- Product Preview: https://demo.tonsamith.com/demo/257404-barama-real-estate-property-marketplace-developer-management-php-script
- Customer Support: https://tonsamith.com/contact
- Market: CodeCanyon (submission intended; no listing or item ID claimed)

## Files to deliver to customers

The exact GitHub Actions verified package `Barama-Real-Estate-1.0.0.zip` contains the PHP application, the five-step installer, application updater, protected Media Library upload, and offline `documentation/index.html`; it must not contain CI/developer-only resources, test fixtures, private signing keys, runtime secrets, installation state, arbitrary backups or demo customer databases.

Retain the matching:
1. `Barama-Real-Estate-1.0.0.zip.sha256`
2. `Barama-Real-Estate-1.0.0.artifact.json` (must bind the exact source commit and ZIP hash)
3. The GitHub Actions CodeCanyon Staging Readiness report as internal supporting acceptance evidence.

A workflow artifact may be a *wrapper*: extract it locally and upload only the **inner customer ZIP** to CodeCanyon. Do not upload the acceptance bundle, a GitHub checkout or an archive of the wrapper.

## Documented v1.0.0 modules

Marketplace listings, properties, projects, companies, developer workspace, customer accounts, administrator dashboards, finance tools, secure private Media Library and batch uploader, signed licensed Demo Importer, policy administration, installer, license enforcement and Auto Updater.

## Security and hosting constraints

- PHP 8.2+, MySQL 8.0+ or MariaDB 10.6+, required extensions; Apache 2.4+ or secure Nginx front-controller routing.
- First administrator created by customer installer, never seeded.
- Media Library: up to 20 files per batch; app cap 50 MB/file, further limited by host/PHP settings; permissions, CSRF and MIME enforcement; private previews.
- Private local media is the safe default. Cloud S3/R2 needs an existing verified active configuration.
- Demo Content v0.9.26 was successfully installed on **application v0.9.25** on real cPanel sites. With the format-based importer correction, the same immutable, correctly signed ZIP is eligible on **v1.0.0** if its signed content schema is supported and a read-only destination database-column preflight passes. App-version labels in older manifests are advisory. A new signed demo is required only for incompatible content/schema changes. Preserve license/domain, SHA-256/Ed25519, backup, policy, empty-marketplace and confirmation guards.
- A source version bump never deploys code to live servers or performs a database upgrade.

## Release gates and evidence

Automated, exact-commit GitHub Actions gates:
- Quality (PHP/JS, migrations, exact ZIP, Apache/Nginx, browser/accessibility).
- CodeCanyon Staging Readiness (source version/docs alignment, zip integrity, manifest, installation, upgrade, routing).
- Check the complete workflow conclusion, not individual green steps.

Real-host/human gates **remain NOT VERIFIED by this version change**:
- Fresh exact ZIP installation and active license activation on disposable cPanel/Apache and aaPanel/Nginx domains.
- Valid/invalid Envato purchase-code behavior, installation lock and safe upgrade behavior.
- Protected Media Library upload, preview, max-size rejection, unauthenticated access, CSRF rejection.
- Files + database backup verification; restore rehearsal, guarded rollback, and repeat/demo import denial after installation.
- Verify re-use of the exact previously signed Demo Content v0.9.26 archive under the v1.0.0 importer on a disposable licensed domain, with successful database-schema preflight and no signature modifications.
- Production email delivery and scheduled jobs; desktop, tablet and mobile; light/dark; Safari/Edge/Chrome; English/Khmer/Chinese review.
- Artwork rights, third-party notices and actual marketplace upload-form/package review.
- Public online documentation parity and support contact verification.

Use `docs/RELEASE_GATES.md`, `docs/RC1_CHECKLIST.md`, `docs/CODECANYON_STAGING_ACCEPTANCE.md` and `docs/CODECANYON_SUBMISSION.md` for the detailed checks. Preserve pending results in `docs/RC1_ACCEPTANCE.json` until independently verified. No completion evidence was fabricated by this release preparation.

## Publication

After all required checks pass, upload the validated **inner** ZIP to CodeCanyon, attach the same release version and product information, preserve the exact SHA-256 and source commit in internal release records, and record the actual marketplace publication date and item ID separately. Do not describe the item as CodeCanyon-approved before approval.
