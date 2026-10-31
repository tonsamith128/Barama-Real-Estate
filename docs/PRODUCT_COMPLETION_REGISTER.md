# Barama Real Estate — Product Completion Register

**Date:** 2026-10-31  
**Application:** Barama Real Estate, Laravel 13, PHP 8.3+, MySQL 8  
**Release designation:** DEVELOPMENT QA ONLY — NOT CODECANYON READY

This register separates a tested development implementation from live cPanel acceptance and from the historic commercial feature parity requirement. A passing unit test or available URL **does not mean the original v1 feature is fully migrated**.

## Implementation status

| Workstream | Status | Verification or missing acceptance |
|---|---|---|
| Laravel-only codebase and nested MVC structure | Foundation implemented | Full architecture/quality audit before marketplace submission |
| Five-step standalone installer and default-domain forwarding | Automated QA passed | End-user shared-hosting installer acceptance with exact customer ZIP |
| Registration, login, email verification, password reset | Foundation implemented | Email provider deliverability, account recovery / multi-device session testing |
| Super administrator dashboard and role isolation | Implemented foundation | Existing cPanel site still shows HTTP 500 in reported screenshot; verify actual Laravel log and migrations |
| Public home, property/project/company catalog and responsive layout | Foundation implemented | Final visual/content parity, search/map, mobile cross-browser testing |
| Customer account, favorites, inquiry tracking, viewing requests, notifications | Foundation implemented | Multi-channel alerts, bulk actions, user acceptance |
| Company developer workspaces and membership isolation | Foundation implemented | Final navigation, team permissions and invitations |
| Company team assignment (existing verified users only) | New QA implementation | Does not yet send invitations or support invitation expiration/acceptance |
| Property draft creation and image gallery | Foundation implemented | Image editor/variant generation, advanced fields |
| Property editing after draft/rejection | New QA implementation | Multi-offer editing, robust versioned change tracking |
| Project create/edit/submit, admin review | New QA implementation | Advanced project galleries/floorplans and workflow approvals |
| Admin approve/reject/unpublish, moderation reasons and audit | New QA implementation | Granular moderator permissions, customer impact notifications |
| Admin and developer inquiry/viewing review | Implemented foundation | Actual messaging replies, calendar/mail delivery, appointments integration |
| Media Library upload, protected preview and gallery | **PARTIAL** | Search/filter, alt text and recorded rights-holder/license metadata implemented; image variants, batch actions, independent license verification and image editor remain pending |
| Legal Policy Center and publication | Foundation implemented | Rich content editing, consent/version audit, professional legal review |
| Appearance Manager / homepage page builder | **PARTIAL** | Admin brand, hero/footer, theme, safe homepage sections and ordered desktop/mobile menu draft and publishing implemented; visual drag-and-drop builder, media placement and per-page templates still pending |
| Localization, Khmer/English, language manager | **NOT MIGRATED** | Translation persistence, locale switching, RTL, QA |
| Advanced filters, maps, geocoding, saved-search alerts | **NOT MIGRATED** | User-owned search preferences, index optimizations |
| SEO metadata, social sharing, sitemap/schema | **NOT MIGRATED** | Canonicals, per-listing metadata, sitemap generation |
| Invoice/finance/subscriptions, gateways and tax | **NOT MIGRATED** | Payments, reconciliation, customer receipts, refund policy |
| License integration / purchase-code entitlement | **PARTIAL** | Admin activation bridge exists; installer/customer-domain production validation incomplete |
| License customer dashboard and license policies | **NOT MIGRATED** | Entitlement lifecycle, revocation, renewals, offline grace |
| Signed updater and rollback system | **NOT MIGRATED** | Signed manifest, backups, file/schema staging and rollback |
| Demo ZIP inspection / signing workflow | **STAGING ONLY** | Real signed customer archive, content mapping, rights check, production installer path |
| Legacy PHP v1 data import and account migrations | **NOT MIGRATED** | Password/role transformation, conflict handling and dry-run |
| Customer-facing documentation and fresh deployment ZIP | **PARTIAL** | Install/upgrade/rollback docs, screenshots, rights and support evidence |
| Security audit and acceptance | **PARTIAL** | Threat model, penetration review, file ACLs on shared hosting, secret management |
| CodeCanyon commercial release | **NOT READY** | Feature parity, legal licensing, compatibility acceptance, release QA |

## Release gates, in order

1. **Restore current cPanel QA site**. Use private Laravel error logs to determine whether the dashboard 500 is a missing migration or another exception. Create verified backups and do not reset the database.
2. Finish project/property/gallery functionality and team membership browser tests on a clean test DB and a migrated existing DB.
3. Rebuild Appearance and media management using consolidated frontend/admin CSS and a correct nested Laravel structure.
4. Implement commercial license entitlement, demo customer-domain importer and signed updater as independently verified modules.
5. Implement billing, search/localization/SEO and historic data migration with test fixtures. Validate externally connected payment/email/map providers with real sandbox credentials.
6. Build the exact customer installer ZIP. Validate shared hosting on aaPanel **and** cPanel from a clean database and upgrade an existing installation with rollback drills.
7. Final quality/security review, CodeCanyon documentation/media/rights audits, source release tag and independent customer installation acceptance.

## Deployment policy

Changes to Main are **source-control updates only**. They do not deploy to `codecanyon.salabandet.com` or to `baramarealestate.tonsamith.com`.

Never copy a full fresh-installer ZIP on top of a live installation or use `migrate:fresh` on customer data. Version the full release in Git tags and metadata; keep PHP/Blade/CSS/JS source filenames free of release-number suffixes.

## Product boundary

Instructor/student LMS features belong to **Learmingo**, not Barama. The Barama product roles are administrator, real estate company owner/manager/member and customer.
