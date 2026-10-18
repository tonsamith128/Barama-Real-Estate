# Barama One-Click Updater: Phase 2 — Verified private code backup

Status: source-only safety component; production automatic updates remain DISABLED.

## Functionality

`VerifiedUpdateBackupV2::prepare()` accepts a file-change plan produced by `VerifiedReleaseFilePlanV2` and writes existing *code files* to a private vault with a SHA-256 receipt. It checks installed-file baseline digests, counts files/bytes, rejects protected paths and symlinks, enforces private receipt/file permissions, and re-verifies the backup. `verify()` checks private snapshot integrity. PHP CLI only.

The standalone smoke test exercises disposable fixtures in `/tmp` and proves that copying an original file back into **the fixture** restores its contents. There is no supported live-site restore operation in this phase.

**No actual customer data, SQL/database, media, license secrets, configuration, or uploads are backed up by this class.** That is intentional: an application code-only snapshot is not a substitute for a consistent full backup.

## Our two websites

- **Staging** `/www/wwwroot/baramastaging.tonsamith.com`: application structure only; do not import production customer records.
- **Production** `/www/wwwroot/baramarealestate.tonsamith.com`: full real user/property/media/DB data; must not be modified by the offline fixtures.

The standalone aaPanel installer used for this class installs only the inert PHP source into both sites (staging first) with a rollback snapshot of the replaced class itself. It does **not** call `prepare()` on the real websites or perform SQL work.

## Remaining requirements before a working one-click install

1. Publisher-signed and licensed package download into protected temporary storage.
2. Strictly pinned file baseline and signed change plan, immutable plan/checksum re-verification under one upgrade lock.
3. Private consistent code + real SQL backups, disk-space checks, isolated database *restore* rehearsal and verified rollback.
4. Controlled file replacement and compatibility/migration policy, crash journal and automatic recovery from interrupted updates.
5. Health validation on isolated staging, then owner-reviewed production gate.
6. Only after all above enable an authenticated, CSRF-protected `Update Now` control with truthful status.

## QA

```bash
php -l application/app/Services/Updates/VerifiedUpdateBackupV2.php
php tools/verified-update-snapshot-v2-smoke.php
```

No extra server setup, GitHub authentication or live database migration is required to install the source-only Phase 2 class.
