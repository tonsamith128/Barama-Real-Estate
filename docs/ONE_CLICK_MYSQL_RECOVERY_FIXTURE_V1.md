# Barama One-Click Updater — Phase 3B: disposable MySQL recovery proof

This phase adds **real SQL export and restore testing**, but only in GitHub Actions' disposable MySQL 8 test service. It intentionally does not provide a customer database restoration endpoint.

## Tested boundary

- CLI class: `IsolatedMysqlRecoveryV1::rehearse()`
- Runs only when GitHub Actions identifies the exact Barama repository, `APP_ENV=testing`, the existing `barama_test` and `barama_package_test` CI database names, localhost-only package database configuration and an explicit CI-only authorization flag.
- Uses the CI MySQL service's **test-only root password**, not customer or production credentials.
- Generates two uniquely named `barama_ci_source_*` and `barama_ci_target_*` databases; creates sample data, exports a narrowly defined fixture SQL script to a private file, verifies SHA-256, restores into the new target schema, and verifies all sample rows including UTF-8 and binary BLOBs.
- Tampering with the SQL file must be rejected before import. Deletes fixture databases and private SQL before completing.
- Never connects to or queries the websites or existing application databases; no migrations, customer rows, uploads, source replacement, or update activation.

## Installation on user's aaPanel

The standalone v1.6.0 ZIP installs only the inert CI-only PHP class, staging first, production second, then verifies source hashes and versions. It does not invoke `rehearse()` on aaPanel and does not open MySQL credentials, modify schemas, or create SQL dumps. The separate installer maintains private rollback copies of the installed PHP file.

- Staging: `baramastaging.tonsamith.com` — application structure without customer data.
- Production: `baramarealestate.tonsamith.com` — full existing property/customer/media/database data preserved.

## Why automatic updates remain disabled

A narrowly scoped test SQL exporter is **not** the genuine production `mysqldump` + full schema restoration integration. Before enabling *Update Now* we still need independent recovery of a representative full installation (without production customer data); coherent code/SQL backups; locked journaled code replacement; safe migration gating, web health checks, rollback of both code and database; signed licensed publisher delivery; and real staging acceptance.

## CI

```bash
php -l application/app/Services/Updates/IsolatedMysqlRecoveryV1.php
php tools/isolated-mysql-recovery-smoke.php --guard

# The next command is reserved to GitHub Actions' disposable MySQL service
BARAMA_CI_ISOLATED_MYSQL_RECOVERY=ALLOW_DISPOSABLE_TEST_SCHEMAS_ONLY \
  php tools/isolated-mysql-recovery-smoke.php --ci
```

On aaPanel, the first two commands are safe; the final command MUST be rejected.
