# One-Click Updater — Database Safety (source-only v1.0.0)

**Status:** Offline fixture verified; staging environment audited read-only. The database backup class is **not wired into** the updater, installer, web UI, scheduler, or any deployment script. Never expose it through a web route.

## Source and safe test

- `application/app/Services/Updates/PrivateDatabaseBackupV2.php`: PHP 8.2+ CLI-only staging-restricted logical export prerequisite; private backup location, SHA-256 dump and receipt verification.
- `tools/private-database-backup-safety-smoke.php`: disposable SQL-text **fixture only**, no MySQL/MariaDB connection, no website mutations, no restoration.
- `.github/workflows/quality.yml`: PHP syntax and fixture rehearsal are checked on pushes and pull requests.

Run from the repository root:

```bash
php -l application/app/Services/Updates/PrivateDatabaseBackupV2.php
php -l tools/private-database-backup-safety-smoke.php
php tools/private-database-backup-safety-smoke.php
```

Expected fixture result: `TEST_ASSERTIONS=18`, `DATABASE_SAFETY_REHEARSAL=PASS`, `DB_CONNECTIONS=NONE`, `RESTORE_TESTED=NO`, `AUTOMATIC_INSTALLATION_ENABLED=NO`.

## aaPanel acceptance — 2026-10-18

The owner-provided, read-only server audit of `/www/wwwroot/baramastaging.tonsamith.com` reported:

| Check | Result |
| --- | --- |
| Exact staging path, no symlink | Verified |
| Private directory | `/root`, root-owned, mode `550` |
| Private storage free | `62,731,736 KiB` at audit time |
| Dump binaries | `/usr/bin/mariadb-dump`, `/www/server/mysql/bin/mysqldump` |
| PHP runtimes | `8.5.11` and `8.4.26` |
| Real database connections / dumps / restores | None |
| Staging and production application writes | None |

Original offline package: `Barama-OneClick-DatabaseSafety-aaPanel-v1.0.0.zip`;
SHA-256 `6e53104aa56fa96ca7bf4e1bb22bfbe14549df0727c1e5ddf7f94abd49851863`.
The offline checks reported 14/14 Python tests and 18/18 disposable PHP assertions.

## Restrictions and next approval gates

1. The class restricts non-fixture exports to the hard-coded staging site; the presence of code on `main` **does not authorize** invocation of a real export.
2. Do not connect it to a web endpoint or automatic updater; never bundle database secrets, SQL dumps, receipts or customer data in GitHub.
3. Before any staging database operation, independently verify that the database and credentials resolve to the **staging** database, enforce safe credential-file permissions and interrupted-export cleanup, and approve the exact operation.
4. After separate authorization, restore to an isolated disposable database and validate schema, routines, events, constraints and data integrity before marking the backup recoverable. A checksum is **not** proof of restore success.
5. Signed package verification, staging install, migrations, file rollback and recovery testing remain separate future stages.
6. Production modification and automatic installation remain **disabled** until separately reviewed and approved.

This source-only merge does not change the current product version, customer interface, staging installation, or production deployment.
