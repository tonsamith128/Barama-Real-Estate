# Barama One-Click Updater Phase 3A: isolated transaction rehearsal

## What this implements

`IsolatedUpdateTransactionV1::rehearse()` exercises in PHP CLI:
- pinned file baselines; rejects unauthorized file changes, symlinks and protected paths
- a single non-blocking file lock, including refusal when held by another worker
- a verified private code snapshot from the already merged `VerifiedUpdateBackupV2` class
- atomic per-file replacement of *disposable fixture files*, digest-based postchecks, a private transaction journal
- automatic recovery after an injected failure, including removal of fixture-only added files

The method accepts only fixture root pairs named `/tmp/barama-oneclick-txn-<random>/site` and `/tmp/barama-oneclick-txn-<random>/vault`, with private modes. Real staging and production roots are rejected even when running as root. The smoke test additionally preserves a fake customer-data marker while exercising success, rollback, bad SHA, dangerous path, symlink, and lock-conflict cases.

## Site contract

- Staging `baramastaging.tonsamith.com` holds structure only, not production customer data.
- Production `baramarealestate.tonsamith.com` holds the complete real database, media, properties, users, credentials and application configuration.
- The aaPanel ZIP for this phase will install only this inert class in each codebase, staging first. It does not invoke this rehearsal on a website or change either site database.

## What Phase 3A does NOT prove

This is a **controlled transaction rehearsal, not a production-ready updater**. It does not acquire/download an entitled signed release, restore a real MySQL database, preserve live transaction durability through host crashes/power loss, run migrations, or support live rollback. It does not authorize updating production or enabling `Update Now`.

## Subsequent safety gates

1. Verify private, consistent SQL backup and isolated database restoration using an independent disposable MySQL database and no production credentials.
2. Integrate trusted publisher download, signed plan, private code + SQL backups, durable per-file journal/locks and crash-recovery guarantees.
3. Complete staging update with full rollback and fault injection, preserving staging's structure-only status.
4. Only after these pass, enable a gated, authenticated administrative update action; require fresh full verified production backup and owner review.

## CI test

```bash
php -l application/app/Services/Updates/IsolatedUpdateTransactionV1.php
php tools/isolated-update-transaction-smoke.php
```

The test never opens production or staging site files or databases. `AUTO_INSTALL=DISABLED` remains the operational rule.
