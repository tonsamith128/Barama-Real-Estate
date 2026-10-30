# Migration status (foundation)

Source: main branch Barama v1.0.0, custom Pure PHP framework.
Target: this separate Laravel 13 branch. NO existing production changes.

| Module | Target folder | Status |
|---|---|---|
| Framework/router | bootstrap, routes | Foundation |
| Frontend/Home | app/Modules/Frontend/Home | Static foundation only |
| Auth/Login/Register/Password/Email | app/Modules/Auth | Not migrated |
| Account | app/Modules/Account | Not migrated |
| Developer Workspace | app/Modules/DeveloperWorkspace | Not migrated |
| Admin/Dashboard/Developer | app/Modules/Admin | Not migrated |
| Admin/Appearance/Header/Footer | app/Modules/Admin/Appearance | Not migrated |
| Admin/Finance/MediaLibrary/PolicyCenter | app/Modules/Admin | Not migrated |
| MySQL schema/data | database/migrations | Not migrated |
| Installer/License/Updater/DemoImporter | app/Modules/System | Not migrated |

## Migration sequence

1. Inventory legacy routes, tables, keys, license behavior, and data model; preserve a recoverable v1 baseline.
2. Implement native Laravel authentication with throttling, verified email, CSRF and tests.
3. Migrate company ownership/permissions and real-estate domain rules.
4. Migrate marketplace, customer account, developer, and administrator workspaces with regression tests.
5. Migrate installer, licensing, signed updater, backups, payment workflows, and demo importer.
6. Consolidate frontend CSS into one source `app.css` and admin CSS into one source `app.css`, each with clearly labeled feature sections. Keep two JavaScript entry points with organized feature modules.
7. Build exact customer ZIP and test MySQL, aaPanel/cPanel, browsers, data migration and rollback.
8. **Only after all acceptance gates are green**, remove every legacy Pure PHP framework file, legacy asset bundle, deprecated patch file, duplicate view, old installer/updater and temporary migration shim from the **active application, final source and customer package**.
9. Run `php scripts/verify-no-legacy.php` on the final source and extracted customer ZIP; verify the target Laravel-only release.
10. After separately approved cutover, move the default branch to verified Laravel-only code and retire or archive legacy development branches. Do not destroy recovery backups as part of code cleanup.

See [Legacy Retirement](LEGACY_RETIREMENT.md) for the mandatory sign-off checklist. The initial Laravel v2 branch already excludes the old application tree; the active v1 Main/live sites remain untouched until the new product is ready.
