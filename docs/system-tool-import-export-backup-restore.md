# System → Tool (v1.0.0)

URL: `/dashboard/system/tool` (administrator or super-administrator only).

Four premium tabs are available: **Importer**, **Exporter**, **Backup**, **Restore**. All POST mutations are protected by existing session authentication, CSRF and the site's `ChangeConfirmationMiddleware` 6-digit passcode grant when enabled.

## Importer

Uploads UTF-8 CSV (up to 5 MiB and 5,000 rows) into an existing marketplace dataset. Supported datasets are discovered from the current schema: `locations`, `amenities`, `companies`, `projects`, `properties`.

CSV headers must match actual database columns (including required foreign keys when applicable). Generated MySQL columns cannot be inserted. Each import is **insert-only and transactional**, with no silent update or overwrite. **Validate / Preview** executes schema and constraint checks and rolls back before commit. **Import records** commits once all rows have passed; duplicate keys, unknown columns, malformed files or foreign-key violations abort the entire transaction.

Import can create records with statuses present in the uploaded CSV. Review source data carefully and use only trusted administrator-generated files. CSV export/import is not a substitute for the full logical snapshot.

## Exporter

Exports an allowlisted marketplace dataset to CSV (up to 50,000 rows / 16 MiB) through an administrator-only, no-store HTTP response. CSV is UTF-8 with BOM. The file may contain owner information and marketplace business data, so treat it as private. Spreadsheet formula-like values are escaped.

## Backup

A snapshot contains **database rows only**. It does **not** include application PHP/JS files, media uploads, Cloudflare R2, or other cloud stores. Keep an independent file/media backup for complete disaster recovery.

The tool uses a private vault **outside the website root**, named `.barama-system-tools-<installation-hash>` alongside the install. Permissions are `0700` for the directory and `0600` for archived data. The first root-authorized deployment provisions the vault for the aaPanel `www` account. A backup uses a repeatable-read InnoDB transaction, ZIPs base64-safe JSONL rows and a schema/row manifest, writes a SHA-256 receipt, and verifies the ZIP before downloading. The web workflow is bounded to 128 MiB logical data and a 32 MiB ZIP; a larger installation requires an external, operator-managed MySQL backup process. This web tool does not claim to be an unlimited backup solution.

## Restore

Restore only **locally generated, digest-verified** database archives already in the private vault. The tool does not accept uploaded SQL files or external ZIP paths. It checks the entire archive, exact table set, per-table column list, schema signature, counts and SHA-256 before any database change. It creates a separate pre-restore safety snapshot, then replaces rows in a single InnoDB transaction with a global, nonblocking file lock. It fails closed if a table is not InnoDB, the archive/schema differs, the vault is unsafe, or a backup cannot be created. It does not migrate schema or restore application files.

The operator must select the exact snapshot and type `RESTORE DATABASE`; where enabled, the 6-digit Change Confirmation passcode is required through the established single-use security grant. Restore should be used only during a maintenance window with an independent off-server backup. A successful web response does **not** certify full site/media recovery or independently test rollback after corruption.

## Live Production handoff

Production remained on the successfully signed **v0.9.24** when the new Tool feature was developed on GitHub Main v0.9.25. Do not overwrite the Production app with Main. Use the **additive, guarded** handoff `tools/aaPanel-system-tool-deploy.py` from the GitHub source archive.

1. Transfer the corresponding GitHub branch/archive ZIP to `/root` on aaPanel and unzip privately.
2. Run `python3 -B tools/aaPanel-system-tool-deploy.py --check` from the extracted source root. This checks the exact v0.9.24 installed version, the approved signed JavaScript SHA-256, PHP syntax and route/sidebar anchors.
3. After the read-only preflight passes, run `python3 -B tools/aaPanel-system-tool-deploy.py --deploy --confirm INSTALL_BARAMA_SYSTEM_TOOL_PRODUCTION_ONLY`.
4. Verify the menu at `/dashboard/system/tool` and perform **staging** importer/exporter/backup/restore acceptance before any live data restore.

Deployment only installs the isolated Tool service, controller, view, CSS and JS, and inserts small route/sidebar changes. It takes a root-private rollback snapshot of modified source files and provisions the sibling private vault. It does **not** start imports, create backups, perform restores or touch the updater/publisher/license database.

Manual rollback of code: `python3 -B tools/aaPanel-system-tool-deploy.py --rollback <BACKUP_ID> --confirm ROLLBACK_BARAMA_SYSTEM_TOOL_PRODUCTION_ONLY`. Private database snapshots are deliberately preserved.

## CI evidence

- `tests/system_tool_contract_v100.php`: menu/routes, admin gates, CSRF, passcode grants, private paths and restore assertions.
- `tests/system_tool_mysql_integration_v100.php`: real isolated MySQL 8 snapshot, SHA-verified archive, deliberate data changes, transaction restore, auto-safety snapshot, and generated-column recovery. This test refuses to run unless `SYSTEM_TOOL_INTEGRATION=isolated-ci`.
- `.github/workflows/system-tool.yml`: PHP 8.2 / PHP 8.4 lint, JS syntax and isolated MySQL tests.

No full Production database restore was performed in development or acceptance.
