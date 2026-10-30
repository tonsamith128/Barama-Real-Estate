# Barama Laravel 13 — feature parity and release gate

**Branch:** feature/laravel-integrated-marketplace-20261030  
**Baseline:** historical Pure PHP Barama v1 source on archive/barama-v1-main-before-laravel-20261030  
**Target:** complete Laravel-only marketplace for shared hosting and CodeCanyon.  
**Status:** partial development build, not production approved.

| Feature | Laravel migration state | Testing evidence |
|---|---|---|
| Five-step fresh installer | Implemented | PHP + MySQL 8 HTTP acceptance |
| Login/register/reset/verify | Implemented foundation | PHPUnit |
| Super administrator identity | Implemented | Installer + role tests |
| Companies/projects/properties/listings public browse | Implemented foundation | Publication visibility tests |
| Company-scoped developer workspace | Implemented foundation | Membership isolation tests |
| Developer property draft/submission | Implemented | Company-scoped write and review tests |
| Admin company onboarding and listing moderation | Implemented foundation | Auth/approval tests |
| Inquiry capture and inboxes | Implemented foundation | Tenant isolation tests |
| Original v0.9.26 demo archive preview | Implemented | ZIP structure and synthetic signed fixture |
| Signed demo import | **Staging-only** | Crypto + mapping tests; real detached signature/entitlement still needed |
| Demo media gallery and public delivery | Implemented for imported demo | Hidden/published image visibility tests |
| Legal-policy content | Draft import only | Template status asserted |
| Appearance/site settings | Inactive import only | Settings status asserted |
| Media Library upload/editor | Not migrated | Pending |
| Full company/project/property management/editing | Partial | Pending |
| Notifications/viewing requests/saved searches | Not migrated | Pending |
| Legacy user/password/role/MFA migration | Not migrated | Pending |
| Policy Center publishing and legal editor | Not migrated | Pending |
| Appearance builder/homepage and footer | Not migrated | Pending |
| Localization/Khmer and SEO | Not migrated | Pending |
| Developer team invitations and billing | Not migrated | Pending |
| Invoice, payment and subscriptions | Not migrated | Pending |
| License activation and entitlement | Not migrated | Pending |
| Signed updater and rollback | Not migrated | Pending |
| Demo publisher API, signed download/license binding | Not migrated | Pending |
| Database v1-to-Laravel data upgrade | Not migrated | Pending |
| CPanel/aaPanel live-hosting QA | Not verified | Pending |
| CodeCanyon exact customer ZIP and rights audit | Not approved | Pending |

## Safe integrated testing

See [docs/INTEGRATED_ACCEPTANCE.md](INTEGRATED_ACCEPTANCE.md). CI builds a **development QA ZIP**, tests the five-step fresh installation on MySQL 8 from the extracted package and runs the combined Laravel feature suite. This is not evidence of complete legacy functionality. Keep production websites and databases unchanged.

## Non-negotiable release gates

- All legacy production modules rebuilt or explicitly removed by owner-approved design and covered by feature tests.
- Database backup/restore, reentrant installer prevention, license validation and migration dry-run.
- aaPanel and cPanel shared-hosting acceptance of the exact signed customer ZIP, with uploaded demo and media.
- Review of rights and legal-policy templates before publishing.
- Production rollout requires an independent rollback plan and explicit owner authorization.

