# Barama One-Click Updater — Implementation Gates

> **Historical phase reference; not the current implementation status.** The authoritative scope, latest evidence, branch reconciliation, acceptance tests and next work are now in [AUTO_UPDATER_MASTER_PLAN.md](AUTO_UPDATER_MASTER_PLAN.md). Do not revive or deploy historical phase code without reconciling it against current `main`, accepted site changes and the master plan.


Status: **development/inactive**. GitHub source is not deployed by this pull request.

## Components currently present

| Component | Source | Working scope |
| --- | --- | --- |
| Signed Publisher discovery | `Updates/TrustedReleaseFeed.php` | Read-only discovery; authenticated license entitlement |
| Signed archive validation | `Updates/SignedReleaseInspector.php` | Ed25519, digest, entry path and bomb checks; no install |
| Private authenticated ZIP transfer | `Updates/PrivateArtifactHttpClientV2.php`, `PrivateArtifactReceiverV2.php` | Root-CLI-only source component; not connected to web |
| Database safety | `Updates/PrivateDatabaseBackupV2.php` | Staging-only backup primitive; fixture smoke; **real backup not performed** |
| File safety (this change) | `Updates/PrivateUpdateFileSnapshotV1.php` | Root-private staging snapshot + SHA-256 receipt, read-only verification; isolated fixture-only rollback rehearsal |
| Browser `/updater/` | `UpgradeService.php` | Applies pending migrations after manual package upload. **Not a one-click package installer.** |

All class paths above are under `application/app/Services/`.

## File-snapshot safety contract

- Requires CLI, verified literal staging site path, root-private storage, and reviewed archive filenames; disposable fixture option is restricted to a dedicated `/tmp/barama-updater-fixture-*` tree.
- Rejects relative-path traversal, dot entries, protected paths, symlink ancestors, case-insensitive duplicates, files over limits, unsafe ownership and directories.
- Snapshots only paths specified by the caller. Existing files are copied privately; previously nonexistent files are represented in the receipt so a future rollback engine can distinguish new files.
- SHA-256 receipt and file hashes must verify independently. The receipt digest must be retained by an external trusted caller.
- **Rollback is exposed only for disposable fixtures, not staging or production**. No database connection, ZIP extraction, migration, product UI change or automatic installation is authorized.
- The caller is responsible for requiring successful `SignedReleaseInspector` verification **before** passing archive file paths. This component is not an installation entry point.

Run fixture tests on CI or a disposable development checkout:

```bash
php -l application/app/Services/Updates/PrivateUpdateFileSnapshotV1.php
php -l tools/private-update-file-snapshot-smoke.php
php tools/private-update-file-snapshot-smoke.php
```

## Remaining full-functionality gates

1. **Publisher trust chain**: keep pinned Ed25519 key validation, entitlement, version monotonicity and archive digest mandatory before staging.
2. **Archive staging and dry-run plan**: translate a verified archive into an exact change set; reject protected files and symlinks; pin plan hash.
3. **Database identity and restoration proof**: independently prove the staging DB is nonproduction, perform approved staging logical dump, restore into a disposable DB, validate schema/data/migrations and cleanup.
4. **Update transaction**: maintenance mode, exclusive lock, secure file snapshot and DB snapshot, file replace with atomic/no-clobber operations, incremental migration step, health checks, commit marker.
5. **Failure recovery**: snapshot restoration, new-file deletion, DB recovery under separately approved constraints, crash-restart journal, retry and stop behavior, audit events.
6. **Customer update UX**: admin-only CSRF-protected Check, Review changes, Download, Backup, Update Now, progress, failure details and verified rollback availability. No direct web process access to `/root`.
7. **Release acceptance**: signed test package, mixed PHP/MySQL versions, CodeCanyon package integrity, migration rollback policy and isolated aaPanel validation.

**Do not call the updater complete or enable Update Now** until all gates have automated tests and a separately authorized staging end-to-end rehearsal. Production remains out of scope.
