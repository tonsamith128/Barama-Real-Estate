# Barama Real Estate — independent five-step Laravel 13 installer

**Status:** implementation staged on a feature branch; production/customer deployment requires passing automated fresh-MySQL acceptance and aaPanel/cPanel testing. Existing Pure PHP Barama v1 data migration is **not** provided by this installer.

## Before opening the installer

1. Deploy on a **new** test domain/subdomain with a **new, completely empty MySQL database**. Never run this on an existing customer database.
2. Set the domain document root to the Laravel **public/** directory, not the project root. Enable HTTPS.
3. Include Composer vendor/autoload.php and built public/build/manifest.json in the package. PHP 8.3+ is required.
4. Ensure storage/app, storage/framework, bootstrap/cache and the app root are writable for setup. No .env may exist beforehand.
5. Optional read-only server preflight: `php Installer/check.php`.
6. Visit `https://your-domain.example/installer/`. The first request generates **storage/app/installer.token** in private storage. Read it using cPanel/aaPanel File Manager or SSH; the browser never displays the token.

## Five steps

1. **Requirements** — enter the private setup token; verify PHP extensions, Composer, assets, permissions, empty .env and missing install lock.
2. **Database** — enter MySQL host, port, name, username and password. The database must contain **zero tables/views**, verified again just before schema migrations.
3. **Application** — specify the final HTTPS app URL (HTTP only for localhost development); a random application encryption key and production .env are generated automatically.
4. **Administrator** — set name, email and a password of at least 12 characters. This step runs native Laravel migrations, hashes the password, creates the first `super_admin`, and writes an install lock.
5. **Finish** — follow completion instructions, log in using /login, and **delete both Installer/ and public/installer/**. Keep storage/app/installed.lock and the .env private.

## Security and failure handling

- Works outside Laravel until migrations are necessary; standalone root Installer/ is not publicly served.
- Requires an HTTPS public/ document root, private setup token, CSRF token, HttpOnly SameSite=Strict session, and server-side sequential step gating.
- The admin password is **never stored in a PHP session**. DB connection credentials are stored temporarily in the installer's short-lived server session and removed after installation.
- The installer rejects populated databases and refuses to run when .env or an install lock exists. The install mutex serializes concurrent submission.
- MySQL DDL is not fully transactionally reversible. If a failure occurs after .env or tables were created, do not retry on the same database or automatically truncate tables. Preserve logs, investigate, and restart on a separate clean database after recovery.
- The role field exists, but complete administrator capabilities and permission policies **are not release-accepted yet**. Installer success is not full commercial feature parity.

## Acceptance

- [x] Independent root installer + removable public bridge
- [x] Requirements, DB validation, application configuration, admin and finish workflow authored
- [x] Lock, fail-closed legacy database handling, no password persistence in sessions
- [ ] Current commit passed automated MySQL-backed end-to-end HTTP acceptance
- [ ] Independently tested on aaPanel PHP 8.3+/MySQL 8
- [ ] Independently tested on cPanel PHP 8.3+/MySQL 8
- [ ] Full original Barama marketplace/admin/developer/media/licensing feature parity
- [ ] CodeCanyon signed/packaged release and security audit

**Do not install onto any currently running production Barama domain.**
