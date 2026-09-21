# Live Server Acceptance

This checklist is the final server-side acceptance procedure for Barama Real Estate before v1.0.0-RC1 promotion.

Use the exact validated customer ZIP intended for release. Do not test from a source checkout and do not reuse an older extracted package.

## Test environment

Use an isolated test domain or subdomain with:

- HTTPS enabled
- PHP 8.2 or newer
- MySQL 8.0+ or MariaDB 10.6+
- the same PHP-FPM/runtime model expected for customers
- a disposable empty database
- a real supported outbound mail provider
- a Barama-specific Direct test license
- a Barama-specific CodeCanyon/Envato test purchase code when available

Keep the test isolated from production customer data.

Record:

- package filename
- package SHA-256
- Git commit
- PHP version
- database server/version
- web server type/version
- test domain
- test date

Never copy license keys, purchase codes, SMTP passwords, database passwords, or activation tokens into the acceptance report.

### Optional read-only preflight

Before entering installer credentials, the repository's internal QA utility can inspect the extracted exact customer package and its public HTTPS surface without touching the database or license service:

```bash
php tools/live-server-preflight.php \
  --root=/absolute/path/to/extracted/barama \
  --url=https://barama-test.example.com
```

The preflight checks PHP/extensions, required package files, canonical CSS/JavaScript, runtime-directory writability, HTTPS/TLS reachability, canonical installer redirect, no-cache/noindex behavior, installer security headers/session-cookie attributes, public assets, and protected application paths.

It is an internal acceptance utility and must not be copied into the CodeCanyon customer ZIP.

## 1. Fresh package integrity

Before extraction:

1. verify the ZIP SHA-256 against the release checksum
2. confirm the package contains only the customer runtime/help files
3. confirm no .env, runtime installation state, logs, SQL dumps, QA scripts, node_modules, vendor directory, or test credentials are present
4. extract into the intended website root

Expected result: opening the main domain redirects to the canonical trailing-slash `/installer/` URL with no-cache behavior.

## 2. Installer Step 1 — License & Terms

The customer sees one purchase-code/license field. No verification-method selector is shown.

### Validate-first flow

- entering a non-empty code enables Validate before terms are accepted
- an invalid credential produces a clear inline error
- a valid internal test key is routed automatically and activates successfully
- a valid CodeCanyon/Envato purchase code is routed automatically and activates successfully
- product binding is Barama Real Estate
- domain binding matches the current test domain
- installation ID is created and remains stable
- the original purchase/license value is not stored after activation
- purchase/license input is not echoed into logs or persistent runtime files

### Terms gate

- license validation does not require prior terms acceptance
- the agreement checkbox is shown on the left
- every attempt to check an unaccepted agreement opens the Terms modal automatically
- the customer reviews the Envato license terms, author disclaimer, and support terms
- closing the modal without acceptance leaves the checkbox unchecked
- Accept Terms records acceptance and is the only action that checks the agreement
- Verify & Continue remains disabled until both license validation and terms acceptance are complete
- server-side continuation rejects a bypassed or missing agreement

Where practical, also test a revoked/deactivated or deliberately invalid credential and confirm access is rejected without exposing License Manager internals.

## 3. Installer Step 2 — Requirements

Verify:

- PHP version passes
- required PHP extensions pass
- required package files pass
- runtime directories are writable
- canonical CSS check passes
- canonical JavaScript check passes
- HTTPS is correctly detected
- no separate Permissions step exists
- requirements are displayed as a clean table/list without boxed row backgrounds
- Light/Dark field contrast and larger installer typography remain readable
- header language control shows the language icon and flag + language options

If a required extension or permission is intentionally disabled for testing, continuation must be blocked with Fix Required.

## 4. Installer Step 3 — Database

Verify both setup modes.

### Existing Database

1. select Existing Database
2. confirm host/port and database/name/username fields align cleanly
3. enter an invalid password and confirm connection failure is clear
4. correct the credentials
5. confirm connection succeeds without restarting the installer
6. confirm an empty supported MySQL 8.0+ or MariaDB 10.6+ database is accepted

### Create Database

Use disposable names and a database administrator account intended for acceptance testing.

1. select Create Database
2. verify the button changes to Create Database & Continue
3. enter an invalid database-administrator password and confirm provisioning is rejected
4. enter valid admin host/port/username/password
5. enter a new database name, new dedicated username, password, and confirmation
6. confirm Barama creates the database and dedicated database user
7. confirm the new user can connect and has access to the new database
8. confirm the database administrator credentials are not stored in runtime state
9. confirm only the new application database credentials proceed to Step 4
10. if a controlled provisioning failure is induced, confirm Barama attempts cleanup of only the resources it just created

Do not run Create Database against production customer databases or reuse an existing database name.


## 5. Installer Step 4 — Website

Verify:

- site name
- exact HTTPS site URL
- timezone
- default language
- administrator name/email
- generated administrator password
- password confirmation

Confirm there is no SMTP/email-delivery setup form and no test-email requirement in the installer.

After installation, verify protected runtime configuration initializes:

- PHP mail as the default mail driver
- administrator email as the From address
- website name as the From name

SMTP remains an application setting to configure later when required.

## 6. Installer Step 5 — Finish

Confirm health results for:

- database configuration
- database connection
- schema/migrations
- administrator account
- website settings
- installation lock
- runtime directories
- canonical stylesheet
- canonical JavaScript
- email delivery configuration

Expected result: installation completes successfully and the installer seals itself.

Reopening `/installer/` after installation must redirect away from the installer.

## 7. HTTP and protected-path checks

Verify normal application routes:

- `/`
- `/properties`
- `/projects`
- `/company`
- `/login`
- `/register`

Verify protected source/runtime paths are not publicly readable, including representative paths under:

- `/application/`
- `/docs/`
- `/.github/`

Verify static assets load normally.

For Apache, confirm mod_rewrite/AllowOverride behavior.

For Nginx, confirm Step 2 reports either clean application routing or the no-configuration query-routing fallback. On a host without clean rewrites, verify generated Sign in/Register/Account links use the fallback and remain functional without manual Nginx changes.

## 8. Authentication and customer account

Verify:

- administrator login
- customer registration
- email verification delivery and confirmation
- logout/login
- forgot-password delivery
- password reset
- customer profile update
- privacy/recent-view controls
- saved properties
- compare
- alerts
- notifications
- account security actions

Confirm stale sessions are invalidated after password/security changes where designed.

## 9. Company / Vendor workflow

Using a verified normal customer account:

1. request company access
2. confirm the company remains pending
3. confirm Developer access is not granted before approval
4. approve the company as administrator
5. confirm Developer workspace becomes available
6. invite another verified test account
7. confirm invitation alone grants no Developer access
8. accept the invitation
9. confirm scoped company membership and Developer access

Verify a member cannot manage another unrelated company.

## 10. Administrator workflow

Verify:

- dashboard loads
- moderation sections load
- Users management loads
- Security Audit loads
- user suspension/restoration
- administrator grant/revoke safeguards
- self-lockout protection
- final-active-administrator protection

Do not use the only real production administrator for destructive acceptance tests.

## 11. Marketplace workflow

Create controlled test records through the supported management UI:

- location/amenity
- company
- project
- property
- listing

Exercise approval/publication.

Then verify public discovery and details for:

- sale
- rent
- project
- company

Test:

- save
- compare
- viewing request
- inquiry
- inquiry-to-lead flow
- media display

Remove or isolate acceptance data after the test.

## 12. Localization and visual review

Review representative pages in:

- English
- Khmer
- Chinese

Review at desktop, tablet, and mobile widths in Light and Dark modes.

Confirm:

- no horizontal overflow
- legible Khmer/Chinese typography
- no untranslated shared navigation/account strings in the reviewed surfaces
- visible keyboard focus
- mobile menu/drawer keyboard behavior
- no major Safari/iOS or Edge/Chrome layout regressions

## 13. Upgrade acceptance

After the fresh-install acceptance passes:

1. keep the installed test site and create a complete backup
2. upload a supported newer test package
3. open the main domain
4. confirm automatic redirect to `/updater/`
5. confirm missing backup acknowledgment is rejected
6. confirm wrong administrator password is rejected
7. authenticate correctly
8. confirm installed license validation succeeds
9. apply pending migrations
10. confirm the application version advances
11. confirm application data remains intact
12. reopen updater and confirm the current-version path is a clean no-op

Downgrade attempts must remain blocked.

## 14. Final acceptance record

Record only non-secret evidence:

- pass/fail for each section
- screenshots where useful
- received test-email timestamp
- browser/device names
- web/PHP/database versions
- package SHA-256
- commit SHA
- any defects and the commit that fixed them

Do not promote `VERSION` to `1.0.0-RC1` until all required live-server and human gates in `docs/RC1_CHECKLIST.md` have passed and the pending GitHub Actions gates have executed successfully.
