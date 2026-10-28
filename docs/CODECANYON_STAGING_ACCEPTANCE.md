# Barama Real Estate — CodeCanyon Staging Acceptance

**Version:** CodeCanyon customer-package candidate **1.0.0** (source VERSION, customer ZIP and checksum must match). **Marketplace submission/approval and full release certification remain pending independent evidence.** This artifact is for cPanel and aaPanel fresh-site acceptance.

## Download and package structure

The GitHub Actions artifact is a wrapper containing the following files:

- **Barama-Real-Estate-<version>.zip** — the actual customer-uploadable product.
- **Barama-Real-Estate-<version>.zip.sha256** — checksum of that exact ZIP.
- **Barama-Real-Estate-<version>.artifact.json** — source commit, exact ZIP size, version, SHA-256 and timestamp.
- **STAGING_HOSTING_CHECKLIST.md** — the independent staging test instructions.
- **STAGING_READINESS_REPORT.txt** — automated checks and pending live acceptance gates.

**Extract the wrapper locally. Upload only the INNER customer ZIP contents to a staging website.** Never unpack the GitHub Actions wrapper itself into the website document root.

## Common staging rules

1. Use a new disposable test site, with a separate DB and separate license entitlement. Do not reset, overwrite, or reinstall the live Barama Production site.
2. PHP 8.2 or later, PDO MySQL, curl, mbstring, fileinfo and ZIP support; MySQL 8 or compatible MariaDB; TLS/HTTPS and enough disk capacity for backups.
3. Check the SHA-256 sidecar before extraction. Compare artifact manifest source_commit to the GitHub Actions run SHA, and its source_version to the embedded VERSION.
4. Avoid chmod 777. Allow runtime writes only to documented private directories; disallow public access to application code, private files, runtime secrets and backup data.
5. Record screenshots, PHP/server versions, clean-url HTTP status, health, installer lock, mail delivery and redacted error logs. Never include secrets in test reports.

## cPanel / Apache 2.4 test

1. Create a fresh cPanel subdomain/site and separate MySQL database/user; select supported PHP version with MultiPHP Manager.
2. Upload and extract the customer ZIP's contents into the *new staging docroot*, preserving the root index.php, .htaccess, installer/, application/, assets/ and documentation/ layout.
3. Confirm .htaccess overrides and mod_rewrite work and deny public access to protected application/private paths.
4. Visit the staging domain and complete all 5 real installer steps: License/Terms, Requirements, Database, Website/Admin, and System Health/Finish. Do not reuse a Production activation secret.
5. Test /login, /register, /properties, admin dashboard, user roles, project/property/company pages, uploads and notification scheduling. Confirm installer reentry is locked, HTTPS and clean routes work.
6. Check desktop, tablet, mobile, light/dark themes, user permissions and actual mail delivery.

## aaPanel / Nginx + PHP-FPM test

1. Create a *separate* aaPanel staging site/DB; back up existing staging website and vhost before any changes.
2. Use PHP 8.2+ with the required extensions. Configure standard clean URL front-controller routing with: try_files $uri $uri/ /index.php?$query_string; Follow the shipped documentation/NGINX_CLEAN_URLS.md.
3. Nginx does not read .htaccess. Configure protected-directory and sensitive-file denial directly in the staging Nginx vhost. Do not overwrite the whole vhost or bypass those protections.
4. Extract the exact customer ZIP into the correct docroot; ensure PHP-FPM ownership/writable private runtime directories without opening world-write permissions.
5. Run the five-step browser installer on HTTPS, using a staging-eligible license and fresh database; verify routing, assets, schema, admin, installer lock and health.
6. Test Auto Updater discovery, license recognition, System Tool, Demo Importer Manual/Auto signed ZIP download and *private staging only*. Avoid actual application/database modification until a separate test is planned and backed up.

## Release-version and feature boundary

- The **v1.0.0 source/package candidate** is not an automatic deployment. Existing real customer/demo sites may still run **0.9.25** or other validated versions. Do not overwrite them merely to align displayed labels.
- CodeCanyon public v1.0.0 submission requires the independent human/live acceptance gates in docs/RELEASE_GATES.md and docs/CODECANYON_SUBMISSION.md.
- The Demo Content package published at TonSamith.com, with its properties, images, menus and footer, is separately publisher-signed and is NOT part of this customer installer ZIP.
- Successfully staging or verifying Demo Content does not mean the complete DB/media import is enabled or accepted. Do not bypass installation safety gates.
- Run backup/restore rehearsals only against disposable staging databases. Capture failures as FAIL / NOT TESTED, never silently as PASS.
- Signed Demo Content v0.9.26 previously accepted on app v0.9.25 requires a separately approved signed manifest explicitly compatible with **1.0.0**; never bypass compatibility or signature checks.
- Verify authorized Media Library Upload (JPG/WebP, private preview, file-size limits, permissions, CSRF rejection, and rollback) on both hosting families.

## Minimum cPanel + aaPanel acceptance record

For each host record: host, PHP/DB versions, checksum, source commit, HTTPS, clean routes, installer all 5 steps, license/domain entitlement, schema, admin auth, media uploads, front-end rendering, account permission, email, cron, updater discovery, demo signed-ZIP staging, rollback tests, mobile/light/dark and manual browser screenshots.

**This is a staging readiness artifact, not a production upgrade or final CodeCanyon approval.**
