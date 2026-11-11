# Barama Real Estate — isolated historical-data and hosting release gates

**Development phase:** draft PR #190 through #192, followed by a separate read-only release-readiness reconciliation branch. The live site remains Pure PHP. All real-data and physical-host tests remain unperformed until independently executed.

**Historical code authority:** archived Pure PHP commit a0af1e5d4d29f38441e3357ae6001b263f1c79cd. Source-code parity is not a backup of live customer data.


## 0. Updated financial, security and geography release gates (November 11, 2026)

The release-readiness branch now includes all missing **audit-only** artifacts
from draft geography PR #184, without reverting the newer location,
catalog, company, property or project implementations. See
`docs/GEOGRAPHY_PR184_RECONCILIATION.md`.

### Financial and security backup-restore integrity

Use **two independently restored copies** of the exact same historical
Pure PHP database inside an offline, operator-controlled environment.
Do not touch the original database and do not run this against the
Laravel destination database. The same backup hash and separate restored
schemas must be independently checked by the host administrator.

`support/barama_sensitive_restore_compare.php --compare-restores` uses
only SELECT-only localhost connections and an in-process random HMAC key
to verify row-level integrity on historical billing, payment, company
subscription, roles, security factors, reset tokens, notification
delivery and communication tables. It compares schemas, primary keys,
row counts and complete in-memory row digests. It **never prints
customer values, secret fields, raw row hashes, or credentials**, and
never writes any database or filesystem files.

Separate private environment credentials:

    APP_ENV=testing
    BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED
    BARAMA_RESTORED_OFFLINE_VERIFIED=yes
    BARAMA_SOURCE_HOST=127.0.0.1
    BARAMA_SOURCE_DB=barama_legacy_rehearsal_<nonce>
    BARAMA_SOURCE_USER=<readonly_user>
    BARAMA_SOURCE_PASSWORD=<private>
    BARAMA_RESTORED_HOST=127.0.0.1
    BARAMA_RESTORED_DB=barama_legacy_rehearsal_<different_nonce>
    BARAMA_RESTORED_USER=<other_readonly_user>
    BARAMA_RESTORED_PASSWORD=<private>

Run from a source-reviewed, pinned commit:

    php support/barama_sensitive_restore_compare.php --compare-restores

**Comparison success does not authorize activating old security secrets,
password hashes, payment provider credentials, webhook events, saved
search alerts or historic customer communications.** Such records need
new credentials/re-enrollment, legal retention review and explicit
separate production adapters. Even two matching backups leave the
production release **BLOCKED**.

The script is intentionally unavailable for regular production DB names,
remote database hosts, or an active application environment.

### Physical acceptance

Use `support/barama_clone_host_acceptance.sh` only on a standalone,
marked disposable aaPanel or cPanel Laravel QA installation.
Document actual QA host Nginx/Apache/LiteSpeed and PHP-FPM behavior.
The `docs/PHYSICAL_HOST_AND_DEVICE_ACCEPTANCE.md` matrix covers
physical iPhone, Android and tablet tests, including viewing reschedules,
company authorization, media upload failures, and offline recovery.
No physical acceptance is inferred from GitHub browser emulation.

## 1. Private restoration by authorized host administrator

Create an encrypted, offsite private backup of the original MySQL database, media, configuration and install lock. Keep customer data, backups, passwords and SQL rows out of ChatGPT, GitHub Actions, online storage without approved encryption and public QA websites. Privately validate restoration and SHA-256/backup size.

On an isolated host, restore the original database **as a copy** in a distinct local schema named barama_legacy_rehearsal_<nonce>. Create a new fresh Laravel 13 database named barama_laravel_rehearsal_<nonce>. The importer only accepts these names or explicitly disposable CI fixture names, both on localhost. Never use the original installed DB name or webroot. Give the source user SELECT-only privileges; the target user may write only to the empty Laravel clone.

Place a read-only copy of source media under a directory whose path contains **barama-legacy-media-rehearsal**. Files must be arranged to match the source media_assets.path relative paths exactly. The destination private Laravel storage root must be under a separate path containing **barama-laravel-media-rehearsal**, and must match the actual Laravel local Storage disk root in that isolated installation. Neither path may point, directly or through a symlink, to the live aaPanel site.

Run only on an offline/private QA environment with mail, webhooks, SMS, payment callbacks, queues and cron disabled. All connection secrets must be supplied from an access-controlled 0600 local environment file or the host secret store. Do not share those values or use shell tracing.

## 2. Read-only preflight and plan

Required environment configuration (values private):

- BARAMA_SOURCE_HOST=127.0.0.1, BARAMA_SOURCE_DB, BARAMA_SOURCE_USER, BARAMA_SOURCE_PASSWORD
- BARAMA_TARGET_HOST=127.0.0.1, BARAMA_TARGET_DB, BARAMA_TARGET_USER, BARAMA_TARGET_PASSWORD
- BARAMA_REHEARSAL_APPROVED=TWO_DISPOSABLE_CLONES_VERIFIED
- BARAMA_MEDIA_CLONE_ROOT and BARAMA_TARGET_MEDIA_ROOT
- APP_ENV=testing

Run from a reviewed, SHA-pinned GitHub checkout:

    php support/barama_isolated_clone_inventory.php
    php support/barama_isolated_media_inventory.php
    php support/barama_isolated_clone_stage.php --plan

These produce only aggregate counts, missing/duplicate-UUID checks, source relationship anomalies and private image hashes. They print no customer names, emails, UUID values, file paths, SQL rows or credentials. The tools do not write database records. Any missing or mismatched data blocks the migration.

## 3. Isolated target-only staging

After both clones and the media integrity report have been verified, authorize:

    BARAMA_STAGE_APPROVED=WRITE_ONLY_FRESH_DISPOSABLE_LARAVEL_CLONE
    BARAMA_MAIL_DISABLED=yes
    BARAMA_TARGET_OFFLINE_VERIFIED=yes

Then run, against a **fresh Laravel QA schema**, never production:

    php support/barama_isolated_clone_stage.php --stage

Before any subsequent testing or account re-enrollment, run an **independent read-only reconciliation**:

    php support/barama_isolated_clone_reconcile.php

Review the mapped_core_counts, integrity_blockers, and unconverted_nonempty_source_tables values privately. Only MAPPED_CORE_VERIFIED is a structural pass; production_release_status remains BLOCKED regardless. No password, private media path or customer text is printed. Confirm the source and destination counts again from your independently restored encrypted backups.

Behavior:
- Source PDO is read-only; all SQL imports run in a target-only transaction.
- Imported users get new random unusable passwords, unverified email status and unprivileged user roles. Old password hashes and security tokens are not copied.
- Companies, projects, properties and listings are imported as unpublished private drafts, regardless of prior source publication. The original status is preserved in legacy_identity_maps for separate moderator review.
- Company memberships are imported as inactive ordinary members; source member roles/status remain in legacy_company_membership_reviews, awaiting independent reauthorization.
- Legacy numeric IDs and UUIDs are mapped to new target IDs. Never reuse historical integer primary keys as Laravel foreign keys.
- Customer saved-listing relationships, comparison lists, recent views and saved-search evidence are mapped to the corresponding private account IDs. Original viewing dates and states are retained in a historical review-only state; no historic appointment is re-confirmed automatically.
- The read-only plan enumerates **every legacy table** found in the isolated source schema, not just a fixed list. Any table without a safe adapter is counted under unmapped_legacy_data_counts, including previously unrecognized legacy tables.
- Supported source images are verified by size, MIME, safe path and SHA-256, copied into the private Laravel local disk, and marked as needing rights review. Missing media, unsafe paths, unrecognized formats and bad hashes abort staging.
- Invalid relationships, duplicates, unrepresentable field lengths and nonempty target tables abort staging rather than silently delete or truncate data.
- On error, target SQL is rolled back and only the new staging files created by that attempt are removed.
- Replaying staging into a nonempty target is denied.

**Scope is not full production migration.** Historical leads and company-only inquiries are now mapped into administrator-only private review tables, not active CRM/customer conversations. Financial ledgers, payments, security factors, old tokens, notification-delivery history, non-image media and original role grants still need independent reconciliation and approved operational adapters. The source clone and encrypted original backup remain authoritative; never discard their unconverted records. Staging success always reports **BLOCKED** for production release.

## 4. Independent automated evidence

GitHub Actions now exercises:
- Pinned archived PHP migrations in a synthetic MySQL 8 source schema.
- A second fresh Laravel MySQL target schema.
- The read-only source integrity/media auditors, and --plan mode.
- The target-only importer with legacy UUID/ID mapping, new unprivileged users, private listing states, saved-listing links and verified copied media.
- The independent read-only post-import verifier compares UUIDs, source IDs and destination rows, tests exact saved-listing and company-member relationships, and detects permission/publication bypass. It always blocks production release even on a green core mapping.
- Rejection of importer replay into nonempty target and rejection of deliberately tampered source media.
- Eleven additive Laravel migrations (through the viewing and historical customer review milestones) with synthetic backup, rollback, reapplication and SQL restoration.
- QA package, private-path denial, Apache rewrite, fresh five-step installer, Laravel PHP feature tests and asset build.

Synthetic CI is **not a tested copy of live customer data**. A release requires real authorized-clone results, full record reconciliation and privacy approval.

## 5. Real aaPanel and cPanel QA acceptance

Deploy a standalone *QA-only* Laravel package to a separate hostname and application path containing barama-laravel-rehearsal. Do not overwrite the original Pure PHP vhost or database. Create a private marker file in the QA root named .barama-disposable-rehearsal containing exactly BAR_REHEARSAL_ONLY.

Use the actual QA host path and installed PHP binary, setting:

    BARAMA_HOST_TYPE=aaPanel
    BARAMA_QA_ROOT=/path/to/barama-laravel-rehearsal
    BARAMA_QA_URL=https://a-separate-qa-domain.example.invalid
    BARAMA_QA_PHP=/path/to/php
    bash support/barama_clone_host_acceptance.sh

For the separate cPanel account use BARAMA_HOST_TYPE=cPanel and its own QA path, PHP and HTTPS address. The checker is read-only: it verifies PHP, package entries, QA root marker, valid TLS, root response and denial of private files, and verifies Nginx configuration syntax when available or presence of cPanel .htaccess. It **does not** install, migrate, test physical devices, or approve production cutover.

Real-host acceptance additionally requires both PHP-FPM/LiteSpeed runtime paths, DB/socket permissions, private image delivery, install/upgrade/rollback, verified encrypted backup restoration, user login/recovery, moderation and queue/cron/SMTP behavior (with non-real recipients).

## 6. Device/browser acceptance

Use synthetic QA users/images. For each physical device, record model, OS/browser version, commit SHA, operator, date/time, pass/fail and redacted screenshots.

| Physical device | Journeys to validate |
|---|---|
| iPhone / Safari | Responsive public navigation, listings/search, gallery, authentication, submission |
| Android phone / Chrome | Same plus upload, company roles, moderation notifications |
| iPad / Safari | Portrait/landscape dashboard, grouped navigation, modal and paginated data |
| Android tablet / Chrome | Project/property form CRUD, galleries, permission denial |
| macOS / Safari and Chrome | Admin/Developer/Account journeys, password recovery, approved visibility |
| Windows / Edge and Chrome | Installer, private-file denial, restore, forms, tables and accounts |

Critical negative cases: cross-company access, unsigned media, unpublished listing visibility, account privilege escalation, duplicate imports, real-customer notifications and unsafe uploads.

CI browser emulation does not replace physical hardware acceptance.

## 7. PR integration and final release decision

Current stacked drafts: #185 → #186 → #187 → #188 → #189 → #190. Geographic PR #184 is independent from main. The canonical geographic model, hierarchy service, migration and tests are already present in #189/#190 with exact Git blob parity; resolve duplication before merging. Review older PR #182/#183 for overlapping catalog changes. Do not auto-merge any draft branch.

**NO-GO until:** every source table including leads, invoices and contact histories is reconciled; actual source UUIDs/IDs and all attachments are mapped; roles and verified emails have safe re-enrollment; live-only updater workers are reviewed; physical aaPanel/cPanel and device matrices pass; encrypted rollback evidence exists; and the owner explicitly approves a production cutover. Green synthetic CI alone is insufficient.

**The production site and live database remain unchanged.**
