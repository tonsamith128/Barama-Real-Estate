# cPanel installation recovery — DNS, document root, and archive permissions

This describes the 2026-10-30 shared-hosting staging attempt. **No database migrations or customer data changes are authorized by this checklist.** This application is still a development QA release.

## Identify the requested hostname

- Browser address: `codecanyon.tonsamith.com` — Chrome shows `DNS_PROBE_FINISHED_NXDOMAIN`.
- cPanel File Manager directory: `/home/salalsxn/codecanyon.salabandet.com` — a different hostname.
- Use `https://codecanyon.salabandet.com/installer/` only if that domain is configured and has a valid certificate. Otherwise create `codecanyon.tonsamith.com` as a domain in cPanel, and add the missing Cloudflare DNS record for `codecanyon` **pointing to the cPanel hosting shared IP, not to an unrelated VPS**.
- After DNS resolves, obtain a valid TLS certificate (cPanel AutoSSL or another supported certificate). The installer rejects plain HTTP outside localhost.

## Required document root

The Laravel application root is:

`/home/salalsxn/codecanyon.salabandet.com`

The ONLY correct public website document root is:

`/home/salalsxn/codecanyon.salabandet.com/public`

In cPanel > Domains > Manage, assign the actual selected hostname to this `public/` directory. If cPanel cannot change it, request the host to update the vhost. Do not expose Laravel's application root or workaround with a public copy of `.env`, `vendor`, or `storage`.

## Correct archive permissions

The original QA build used a restrictive packaging umask (077), creating directories with 0700 and plain text files with 0600. The corrected QA build now ships standard extracted permissions and verifies them in GitHub Actions.

On an *existing staging copy*, an authorized cPanel Terminal session may repair only the web-serving paths:

```bash
set -Eeuo pipefail
APP=/home/salalsxn/codecanyon.salabandet.com
test -d "$APP/public/installer"
test -f "$APP/public/index.php"
chmod 755 "$APP" "$APP/public"
find "$APP/public" -type d -exec chmod 755 {} +
find "$APP/public" -type f -exec chmod 644 {} +
```

Use cPanel File Manager > Permissions (755 directory, 644 web files) where Terminal isn't available. **Never** make `.env`, `storage/app/installer.token`, database credentials, or backups world-readable; those are private server files. Keep the application outside the served public document root.

## Installer preflight and database

- Select PHP 8.3 or newer and required extensions, including pdo_mysql, openssl, mbstring, fileinfo, xml, ctype and tokenizer.
- Verify `vendor/autoload.php` and `public/build/manifest.json` exist.
- Optional SSH/Terminal read-only preflight: `php Installer/check.php`.
- Check the target database from phpMyAdmin. A fresh installation requires **zero tables/views** (the installer independently rechecks this); do not drop any existing table.
- Confirm `.env` does not already exist. The installer will create it after gathering credentials.
- Open `https://<selected-and-configured-hostname>/installer/`, retrieve the private server-side token from `storage/app/installer.token`, and complete five steps.
- Remove **both** `Installer/` and `public/installer/` only after success; keep the install lock `storage/app/installed.lock`.

## Error interpretation

| Error | Meaning / action |
| --- | --- |
| NXDOMAIN | DNS record missing or wrong hostname. Resolve DNS first. |
| SSL or connection error | Configure hostname, TLS certificate, host/vhost and firewall. |
| 403: document root | Change cPanel domain document root to Laravel `public/`. |
| 403: permission denied | Check 755 permissions on public directories and 644 for web files. |
| 404 at /installer/ | Check `public/installer/index.php`, hostname vhost and document root. |
| 500 | Examine cPanel error logs and PHP requirements; don't enable APP_DEBUG publicly. |
| Failing Step 1 | Resolve extensions, build assets, directory owner/write access or stale `.env`. |
| Non-empty DB | Create a separate empty database; never truncate production data. |

**Release status:** A successful fresh installation does not prove complete Pure PHP Barama feature parity, nor authorize replacing a live website.


## Domain-first installer routing (corrected QA build)

The updated QA package supports **two** webroot layouts:

**Preferred:** set the domain Document Root to `<application>/public`. Then the browser visits `https://your-domain/` and the pre-Laravel entry point redirects to `/installer/` (HTTP 302) automatically.

**Protected cPanel fallback:** if hosting does not permit changing the document root, use `<application>` as the document root and install the supplied **root** `.htaccess` file from the updated ZIP. It disables directory listing, hides all Laravel private application folders, routes public requests internally to `public/`, and preserves a clean browser URL without `/public`. In this configuration the standalone installer checks a rewrite-generated server marker and rejects direct `/public/installer/` requests.

Do not manually copy the private application folders into `public/`, create a top-level duplicate Laravel `index.php`, edit the vendor autoloader, remove the installer document-root check, or add a broad catch-all redirect to `public/`.

For existing cPanel sites previously showing **Index of /**, replace the outdated root `.htaccess` with the new tested version. **Back up** existing cPanel rewrite/TLS rules before replacement and confirm the vhost allows `.htaccess` rewrites. Until the directory listing is secured, remove any ZIP files containing application source or secrets from the web-serving directory.

Expected results after installing the new QA archive:

| URL | Before installation | After installation |
| --- | --- | --- |
| `https://your-domain/` | HTTP 302 to `/installer/` | Laravel homepage |
| `https://your-domain/installer/` | Five-step setup form | 410 / removed installer |
| `https://your-domain/public/installer/` with protected root bridge | 403 | 403 |
| `https://your-domain/vendor/autoload.php` with protected root bridge | 403 | 403 |
| `https://your-domain/storage/app/installer.token` with protected root bridge | 403 | 403 |

The release QA now tests this fallback using an actual Apache PHP request, not only Laravel feature tests. If the web host ignores the root `.htaccess`, the protected fallback **cannot be used safely**: change the domain document root to `public/` or contact the hosting provider instead.
