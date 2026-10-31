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
| Inquiry capture and inboxes | Admin + company manager response status and customer notifications implemented; actual replies still manual | Tenant isolation + response audit tests |
| Original v0.9.26 demo archive preview | Implemented | ZIP structure and synthetic signed fixture |
| Signed demo import | **Staging-only** | Crypto + mapping tests; real detached signature/entitlement still needed |
| Demo media gallery and public delivery | Implemented for imported demo | Hidden/published image visibility tests |
| Legal-policy content | Draft import only | Template status asserted |
| Appearance/site settings | Inactive import only | Settings status asserted |
| Media Library upload/editor | **Partially implemented:** tenant-scoped validated image upload, private listing galleries, administrative inventory, and protected previews | 46 Laravel QA tests / 223 assertions (foundation workflow) |
| Full company/project/property management/editing | Partial — developer drafts, listing gallery and administrator approval are available | Tenant-isolation tests; full editing pending |
| Account saved properties, viewing requests and notifications | Implemented customer foundations; verified admin and company viewing decisions and notifications | 68 Laravel tests / 365 assertions on development branch |
| Legacy user/password/role/MFA migration | Not migrated | Pending |
| Policy Center publishing and legal editor | **Implemented foundation:** admin draft editing, publish/unpublish, safe HTML and public pages; full editor/consents pending | 51 Laravel tests / 254 assertions (development foundation) |
| Appearance builder/homepage and footer | Not migrated | Pending |
| Localization/Khmer and SEO | Not migrated | Pending |
| Developer team invitations and billing | Not migrated | Pending |
| Invoice, payment and subscriptions | Not migrated | Pending |
| License activation and entitlement | Not migrated | Pending |
| Signed updater and rollback | Not migrated | Pending |
| Demo publisher API, signed download/license binding | Not migrated | Pending |
| Database v1-to-Laravel data upgrade | Not migrated | Pending |
| CPanel/aaPanel live-hosting QA | MySQL/Apache CI simulated; live cPanel changes must be tested separately | Not yet verified on customer domain |
| CodeCanyon exact customer ZIP and rights audit | Not approved | Pending |

## Safe integrated testing

See [docs/INTEGRATED_ACCEPTANCE.md](INTEGRATED_ACCEPTANCE.md). CI builds a **development QA ZIP**, tests the five-step fresh installation on MySQL 8 from the extracted package and runs the combined Laravel feature suite. This is not evidence of complete legacy functionality. Keep production websites and databases unchanged.

## Non-negotiable release gates

- All legacy production modules rebuilt or explicitly removed by owner-approved design and covered by feature tests.
- Database backup/restore, reentrant installer prevention, license validation and migration dry-run.
- aaPanel and cPanel shared-hosting acceptance of the exact signed customer ZIP, with uploaded demo and media.
- Review of rights and legal-policy templates before publishing.
- Production rollout requires an independent rollback plan and explicit owner authorization.


## Current media milestone

See [Media Library acceptance](MEDIA_LIBRARY_ACCEPTANCE.md) for content validation, private storage, company permissions, publication and rejection tests. Bulk media editing, responsive variants and rights approval remain pending. No production deployment has occurred.

## Policy Center milestone

See [Policy Center acceptance](POLICY_CENTER_ACCEPTANCE.md) for draft/edit/publish test cases, XSS sanitization, audit events and legal publication access controls. Historical demo legal drafts must be reviewed before publication; no legal-compliance score blocks the action.

## Frontend, Customer and Admin milestone

[Frontend/Account/Admin acceptance](FRONTEND_ACCOUNT_ADMIN_ACCEPTANCE.md) covers public navigation, About/FAQ/Contact, account metrics and profile/security, saved properties, viewings, inquiry history, notifications, user directory and contact inbox. **Student/Instructor roles are Learmingo LMS scope**, not Barama.

## Dashboard recovery and customer relationships

See [Dashboard/Viewing acceptance](DASHBOARD_VIEWING_ACCEPTANCE.md). When cPanel receives application code before corresponding database migrations, the administrator overview now shows missing-module schema status instead of triggering an unhandled missing-table query. Viewing decisions and inquiry handled status are audited, customer notices are in-app only, and company manager access is tenant-scoped. The real cPanel error log has not yet been reviewed, so the exact 500 root cause remains a hypothesis pending host verification.
