# Independent physical-host and device acceptance — Barama Laravel 13

**DRAFT — not executed on physical hardware.**
The current GitHub Actions CI only validates simulated/CI MySQL
installations, Apache rewriting and browser/server behavior. It is NOT
evidence of actual aaPanel/cPanel or physical iPhone/Android/tablet success.

## Evidence handling

Use a separate **disposable** QA host and distinct QA MySQL database.
Never rename or replace the live Pure PHP webroot
`/www/wwwroot/baramarealestate.tonsamith.com`; do not use its DB for tests.
Keep `.env`, passwords, access tokens, real customer attachments, SQL backups,
personal data and device screenshots containing PII OFF GitHub and ChatGPT.
Store signed logs privately in an encrypted operations vault; share only
sanitized outcome summaries and file hashes.

For each test, record private date/time, environment build SHA, device/browser
version, tester, outcome **PASS/FAIL/NOT_TESTED** and sanitized evidence hash.
Leave every incomplete test as `NOT_TESTED`. A checklist without physical
evidence is not a passing result.

## Real aaPanel test matrix

- Clean Ubuntu aaPanel host: document root isolated to QA `public/`.
- PHP-FPM 8.3 and the commercial target supported PHP runtime (8.4/8.5 as
  applicable), with required extensions enabled.
- Nginx `-t` and reload only on isolated QA configuration.
- Fresh MySQL 8 installer, unique setup key, secret configuration exclusion
  from public web and verify install lock; no installer bypass after lock.
- Debug disabled, `.env`, vendor, storage, source routes deny all HTTP reads.
- Confirm generated assets, public listing search, company page, real 404
  and HTTPS redirect, session and CSRF rules.
- Test optional media intake with a synthetic file, approval gates, denied
  private media access, interrupted upload and malformed payload.
- Backup, rollback and restore to a **fresh QA clone**, verify exact counts
  and checksums and fail on missing media.
- No production live DB connection or auto-publication from imported records.

### Actual aaPanel read-only probe

Review and integrity-pin `support/barama_clone_host_acceptance.sh` from
the selected GitHub commit. On the QA host only:

    export BARAMA_HOST_TYPE=aaPanel
    export BARAMA_QA_ROOT=/isolated/path/barama-laravel-rehearsal
    export BARAMA_QA_URL=https://private-qa.example.invalid
    export BARAMA_QA_PHP=/path/to/php
    bash support/barama_clone_host_acceptance.sh

The probe requires a marker file inside the QA root containing exactly
`BAR_REHEARSAL_ONLY`. It does not connect to the database or modify
site files. Do not use a production hostname for this probe.
Capture its sanitized output privately.

## Real cPanel test matrix

- Fresh account without existing Barama code/data; no reuse of live credentials.
- Validate PHP Selector/MultiPHP, Apache/LiteSpeed rewrite rules and
  document root under `public/`.
- Run independent five-step installer and migration with a test MySQL 8 schema.
- Check installer lock, production debug disabled and no deployment secrets
  or `vendor` directory accessible through the web.
- Test package extraction mode, writable private storage and file permission
  acceptance under the actual hosting account.
- Check HTTPS, email sandbox mode only, queue/cron disabled or isolated,
  license installation and updates using test keys only.
- Encrypted backup/rollback/restore rehearsal, confirmed by independent
  checksums; maintain all evidence outside webroot.

Set `BARAMA_HOST_TYPE=cPanel` and the actual separate cPanel QA root,
HTTPS URL and PHP path for the same read-only probe.

## Physical-device journeys

Complete on **real** hardware; browser emulation is insufficient.

| Journey | iPhone Safari | Android Chrome | Tablet Safari/Chrome |
|---|---|---|---|
| Responsive header / menu / login | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Register, verified login and password recovery | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Listing filters and global geography hierarchy | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Saved listings, saved searches and comparisons | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Customer viewing request and cancellation | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Company approval and reschedule proposal | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Customer accept/decline alternate time | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Admin holds, releases and audit records | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Company media/gallery permissions | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| File upload interrupted and mobile reconnect | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Accessibility, touch controls and orientation | NOT_TESTED | NOT_TESTED | NOT_TESTED |
| Logout, access revocation and stale-session rejection | NOT_TESTED | NOT_TESTED | NOT_TESTED |

Use synthetic QA users and sample media for device testing.
If customer data is restored in a private environment, do not display it
on public-facing devices or screenshots.

## Production decision

Physical host/device results can only be signed off by an independent
operator who executed them. The release reviewer also needs original
backup integrity, the real-data conversion reconciliation,
financial/security governance, PR merge checks, license/privacy review and
a separately authorized production change window.

**As of 2026-11-11, no physical-host or physical-device results are claimed.**
