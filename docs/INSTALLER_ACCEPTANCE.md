# Laravel 13 clean-install acceptance gate

**Status: BLOCKED — do not install on a customer domain or existing v1 database yet.**

The repository now includes an independent `Installer/check.php` preflight script. This is **not** a working installation wizard and must not be advertised as one. It never modifies the database or writes environment files.

Run from the application root:

```bash
php Installer/check.php
```

Passing preflight is necessary but insufficient. A shared-hosting installer is pending, including: web-accessible step routing where the site's document root is `public/`; CSRF/session protection; database connection testing with no secret disclosure; generated APP_KEY; safe initial administrator creation; migration sequencing; atomic install lock; installer removal after setup; retry and rollback; Apache and nginx/cPanel tests.

**Do not use the current `database/migrations` against an existing Pure PHP Barama schema.** Production data conversion and feature parity have not passed acceptance.

Release checklist:
- [ ] Composer vendor included and licenses reviewed
- [ ] Vite build output included
- [ ] Installer executes with `public/` document root
- [ ] Fresh MySQL 8 installation succeeds on aaPanel and cPanel
- [ ] New admin login works and is restricted
- [ ] Install lock prevents reruns and file cleanup is safe
- [ ] Installation failure recovers without leaving a half-installed application
- [ ] Existing v1 databases are rejected or handled through a separately tested upgrade path
- [ ] Public marketplace, account, admin, developer, media and licensing acceptance
- [ ] Production release/rollback approved
