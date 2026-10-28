# Barama Real Estate v1.0.0 — Fresh Website / CodeCanyon Acceptance

**Stage:** pre-submission QA candidate; not a certificate of live acceptance.  
**Source:** exact GitHub Main/PR build; record merge SHA, customer ZIP SHA-256 and hosting environment before testing.  
**Owner:** Ton Samith · **product version:** 1.0.0.

## Non-negotiable preflight

- [ ] Use a brand-new disposable cPanel shared-hosting site; record PHP version and enabled extensions, MySQL/MariaDB, HTTPS, Apache rules and file ownership.
- [ ] Verify the ZIP against the supplied SHA-256; run ZIP integrity check; unpack only the **inner** customer ZIP from the GitHub artifact.
- [ ] Verify fresh database contains no previous Barama data, then perform the five-step installer with a newly created owner/admin account. Never reuse production keys, demo customers or a real payment credential.
- [ ] Verify license domain binding, rejected/expired/foreign-license paths and private installer lock.
- [ ] Capture file and database backups and verify restore on a disposable instance. Do not restore over a live customer site.

## Test each role using separate browsers/incognito profiles

For **every** scenario record PASS/FAIL/BLOCKED/NOT TESTED, URL, expected vs actual, screenshot, PHP error log reference and browser console output. Do not paste passwords, license keys or private tokens in reports.

| Persona | Mandatory test areas |
|---|---|
| Visitor (logged out) | Home, hero/search, properties (filters/detail), projects (filters/detail), company directory/detail, plans/pricing, about, contact, legal pages, navigation, footer, locale, responsive/mobile, search/indexing/SEO |
| New customer | Register, email verification, login/logout, reset password, user menu, profile/avatar, security/password/sessions, favorites/watchlist if enabled, inquiries/contact, notifications, plan checkout cancellation |
| Approved company/developer | Authorized dashboard, company profile, team access, projects, property/listing submissions, media attachments, lead/inquiry workflow, appointments and approvals; verify separate team permissions |
| Administrator | Dashboard, users/roles, moderation, approvals, settings, CMS pages, homepage builder, appearance, navigation/footer, media, finance/plans, email templates, analytics, audit logs, system health, logs |
| Restricted employee/team member | Minimum RBAC permissions, unauthorized direct URL rejection, unauthorized POST rejection, CSRF checks, attachment private preview denial |
| Suspended/unlicensed user | Login/permissions/checkout fail safely, no access to restricted business data, no unauthorized mutation |
| Independent buyer/license holder | Clean installer, author-license validation, updater discovery and signed release denial tests, correct missing-license feedback |

## Feature verification groups

- [ ] **Media Library:** valid image/document upload, 20-file batch ceiling, 50 MB app ceiling vs lower PHP limit, private preview permissions, MIME denial, alt text, attachment linking, no broken image thumbnails.
- [ ] **Demo Importer:** publisher TLS + key fingerprint pin; signed ZIP verifies; old signed content reusable when data schema matches; incompatible schema blocked; empty-marketplace guard; database+file backup before installation; 5,255 records / 53 images / 20 *private draft* policies if approved official snapshot; deny repeat import.
- [ ] **Policy Center:** imported drafts remain private; compliance checklist gates; specific failed-publish reasons; rejected publish creates **zero new versions**; approved Publish creates one version; first-visit guide dismiss and reopen.
- [ ] **Finance:** plan creation/upgrade, pricing, payment provider settings, native post, CSRF/access denial, sandbox/fake checkout, invoices, cancellation/refund messaging, customer vs admin access.
- [ ] **Security/Account:** register, passkey/MFA when configured, recovery, password change, session revocation, audit events, login throttling, admin RBAC.
- [ ] **Appearance:** public header/search across screen sizes, footer (no missing stylesheet request), Security Center six-layer CSS now unified, About editor, Plans CSS, payment provider controls, dark/light contrast.
- [ ] **Email/Jobs:** SMTP test with dedicated test account, verification/password-reset email, queued messages and schedule/cron setup, bounce/failed mail evidence.
- [ ] **Updater:** signed update trusted discovery, package staging, backup/restore, rollback and denial paths; do not upgrade a production customer domain as the first test.
- [ ] **CPanel vs aaPanel:** repeat essential installer/login, public routes, admin, upload and backup checks on disposable Nginx/PHP hosting.
- [ ] **Compatibility:** desktop Chrome/Firefox/Safari/Edge, iOS/Android responsive sizes, English/Khmer/Chinese as configured, keyboard/aria dialogs, zero 404 assets, zero PHP fatals, browser-console and network clean.

## Release verdict

Only mark **READY FOR CODECANYON** when: all required CI workflows are green on the *exact* package source SHA; the latest ZIP checksum matches the manifest; independent cPanel and aaPanel fresh-installs complete; license/import/private-media/legal/finance/auth acceptance is signed off; third-party rights and security notices are reviewed; and operator-owned legal template policies remain drafts until approved.

**No real-host or human acceptance is claimed merely because CI passes.** Keep the original customer ZIP immutable during testing and write discovered defects back to GitHub before preparing a replacement with a new SHA-256.

## Cleanup scope

Wave 1: 15 canonical assets, 49 obsolete copies removed in PR #150.  
Wave 2: 12 additional canonical CSS/JS assets, 47 layered or unused old files removed; 246 literal CSS/JS references audited, all currently valid, and missing Footer stylesheet reference corrected. The historical code remains accessible through GitHub, not inside the distributed customer archive. Versioned PHP classes identifying actual API/data-contract versions are intentionally retained.
