# Barama frontend, account and administrator workspace QA

## Product boundaries

Barama Real Estate supports guest customers, authenticated customers, verified company developers/managers, and super administrators. **Instructor** and **student** are roles in Learmingo (LMS), not Barama; their course, enrollment and grading features belong to the separate Learmingo project and are not silently inserted into the real estate database.

## Routes and workspaces

| Area | Routes | Built behavior |
|---|---|---|
| Public | /, /properties, /projects, /company | Published, approved listings and related companies/projects, search, media |
| Public info | /about, /faq, /contact, /legal | Accessible navigation, FAQ, support form persisted to administrator inbox |
| Account | /account | Real saved/inquiry/viewing/unread counts with recent activities |
| Account | /account/profile | Name/email update with current-password requirement for email change; password update |
| Account | /account/saved | Owner-isolated saved listings; hiding delisted records |
| Account | /account/inquiries | Owner-isolated inquiry history |
| Account | /account/viewings | Viewing requests on published listings, cancellation; not confirmed appointments |
| Account | /account/notifications | Per-user read/unread notification history |
| Developer | /developer | Approved company memberships, property draft and gallery management |
| Administrator | /dashboard | Live entity/user/support metrics, recent inquiries/messages |
| Administrator | /dashboard/users | Super-admin-only user directory (read-only) |
| Administrator | /dashboard/contacts | Super-admin-only support inbox and reviewed state |
| Administrator | /dashboard/catalog | Company onboarding and listing moderation |
| Administrator | /dashboard/media, /dashboard/policies | Media library and legal publication |
| Administrator | /dashboard/system/demo-importer | Safe signed demo import, staging-only |

## Permissions and data integrity acceptance

1. Guest requests to any /account page redirect to login; guests cannot save or request viewings.
2. Saved listings are unique per user and disappear from the account view if publication ends; other accounts cannot list/remove another user's favorites.
3. Viewing requests require a publicly visible listing and a valid future date. A request must not be displayed as confirmed. Only the owning customer can cancel.
4. Inquiries and notifications appear only to the owning customer; shared account data does not leak through list routes.
5. Updating an account email requires the current password and resets email verification. Password updates require old password and strong new password.
6. Public contact form enforces CSRF, input limits, honeypot and rate-limit. Submitted content is escaped and readable only to super-admin.
7. User directory is read-only to protect elevated role privileges; non-admins receive 403.
8. Compiled Vite CSS exists in public/build/assets and both frontend/admin manifest entries point to valid files. No version numbers in source filenames.
9. New migrations only create saved_listings, viewing_requests, account_notifications and contact_messages; no existing tables are dropped or modified.
10. Exact-package QA must include MySQL fresh-install, migration, role authorization, required assets and PHP tests.

## Outstanding commercial features (not claimed)

Advanced Appearance Builder, search facets and location map, saved-search notifications, messaging workflows, property CRUD edit/delete and review history, billing/subscriptions, license enforcement, signed updater/rollback, production demo import, legacy data migrator, multilingual frontend and accessibility review. The current code is a **development QA milestone** and must not be marketed as fully equivalent to historic Barama.

## cPanel QA upgrade

The staging overlay contains the exact files and two additive migrations. Make verified application and MySQL backups. Preserve .env, vendor, storage and installer lock. Copy upgrade files into the existing Laravel application root, run `php artisan migrate --force` followed by `php artisan optimize:clear`, and verify the homepage and account/admin routes. Revert from backup if validation fails. Do not use on legacy PHP production.
